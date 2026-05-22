<?php
/**
 * API: Tasks — Save module settings
 * Upserts tasks_* keys into system_settings.
 */
session_start();
require_once '../../config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['analyst_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$settings = $input['settings'] ?? [];

if (empty($settings)) {
    echo json_encode(['success' => false, 'error' => 'No settings provided']);
    exit;
}

try {
    $conn = connectToDatabase();

    $allowed = ['calendar_span_mode', 'card_fields'];
    $cardFieldKeys = ['priority', 'assignee', 'team', 'start_date',
                      'due_date', 'description', 'subtasks', 'links'];

    foreach ($settings as $key => $value) {
        if (!in_array($key, $allowed, true)) continue;

        if ($key === 'calendar_span_mode') {
            // Whitelist guards against junk
            if (!in_array($value, ['deadline', 'span', 'repeat'], true)) continue;
        } elseif ($key === 'card_fields') {
            // Rebuild from known keys only, coercing each to 0/1
            $clean = [];
            foreach ($cardFieldKeys as $fk) {
                $clean[$fk] = (is_array($value) && !empty($value[$fk])) ? 1 : 0;
            }
            $value = json_encode($clean);
        }

        $dbKey = 'tasks_' . $key;

        // UPSERT: try update first, then insert
        $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([$value, $dbKey]);

        if ($stmt->rowCount() === 0) {
            $stmt = $conn->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt->execute([$dbKey, $value]);
        }
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
