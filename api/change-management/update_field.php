<?php
/**
 * API Endpoint: Update a single field on a change record.
 *
 * Companion to save.php for the change Table view's inline cell editing.
 * Unlike save.php (which rewrites the whole record — fine for the full edit
 * form, destructive if fed a partial payload), this updates exactly one
 * whitelisted column and writes exactly one audit-trail row. Only low-risk
 * list-level fields are editable here; status is intentionally excluded so the
 * CAB-vote / approval-workflow paths can't be bypassed from a cell. Longtext
 * fields (description, plans, risk) are never touched.
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['analyst_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}
$analystId = (int)$_SESSION['analyst_id'];

$input = json_decode(file_get_contents('php://input'), true);
$changeId = !empty($input['id']) ? (int)$input['id'] : null;
$field = $input['field'] ?? '';
$value = array_key_exists('value', $input) ? $input['value'] : null;

if (!$changeId) {
    echo json_encode(['success' => false, 'error' => 'Missing change id']);
    exit;
}

// Whitelist: incoming field => [db column, audit label, lookup table | null].
// A lookup table means `value` arrives as a NAME and is resolved to its id;
// null means `value` is a raw FK id (assignee) or null.
$map = [
    'priority'       => ['priority_id',    'Priority',    'change_priorities'],
    'impact'         => ['impact_id',      'Impact',      'change_impacts'],
    'change_type'    => ['change_type_id', 'Type',        'change_types'],
    'assigned_to_id' => ['assigned_to_id', 'Assigned To', null],
];
if (!isset($map[$field])) {
    echo json_encode(['success' => false, 'error' => 'Field not editable here']);
    exit;
}
list($column, $label, $lookupTable) = $map[$field];

try {
    $conn = connectToDatabase();

    $oldStmt = $conn->prepare("SELECT * FROM changes WHERE id = ?");
    $oldStmt->execute([$changeId]);
    $oldRow = $oldStmt->fetch(PDO::FETCH_ASSOC);
    if (!$oldRow) {
        echo json_encode(['success' => false, 'error' => 'Change not found']);
        exit;
    }

    $newColVal = null;
    $oldDisplay = '(empty)';
    $newDisplay = '(empty)';

    if ($lookupTable) {
        // Resolve incoming name -> id (null/'' clears the field).
        $name = ($value === null || $value === '') ? null : trim($value);
        if ($name !== null) {
            $s = $conn->prepare("SELECT id, name FROM `$lookupTable` WHERE name = ? LIMIT 1");
            $s->execute([$name]);
            $r = $s->fetch(PDO::FETCH_ASSOC);
            if (!$r) {
                echo json_encode(['success' => false, 'error' => 'Unknown value for ' . $label]);
                exit;
            }
            $newColVal = (int)$r['id'];
            $newDisplay = $r['name'];
        }
        $oldId = $oldRow[$column] ?? null;
        if ($oldId) {
            $s = $conn->prepare("SELECT name FROM `$lookupTable` WHERE id = ? LIMIT 1");
            $s->execute([$oldId]);
            $oldDisplay = $s->fetchColumn() ?: '(empty)';
        }
    } else {
        // assigned_to_id: a raw analyst id (or null to unassign).
        $newColVal = ($value === null || $value === '') ? null : (int)$value;
        $analystName = function ($id) use ($conn) {
            if (!$id) return '(empty)';
            $s = $conn->prepare("SELECT full_name FROM analysts WHERE id = ? LIMIT 1");
            $s->execute([$id]);
            return $s->fetchColumn() ?: '(empty)';
        };
        $oldDisplay = $analystName($oldRow[$column] ?? null);
        $newDisplay = $analystName($newColVal);
    }

    $u = $conn->prepare("UPDATE changes SET `$column` = ?, modified_datetime = UTC_TIMESTAMP() WHERE id = ?");
    $u->execute([$newColVal, $changeId]);

    // One audit row, only when the value actually changed.
    $oldNorm = ($oldDisplay === '(empty)') ? null : $oldDisplay;
    $newNorm = ($newDisplay === '(empty)') ? null : $newDisplay;
    if ($oldNorm !== $newNorm) {
        $a = $conn->prepare("INSERT INTO change_audit (change_id, analyst_id, action_type, field_name, old_value, new_value, created_datetime)
                             VALUES (?, ?, 'field_change', ?, ?, ?, UTC_TIMESTAMP())");
        $a->execute([$changeId, $analystId, $label, $oldDisplay, $newDisplay]);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
