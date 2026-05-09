<?php
/**
 * API Endpoint: Get ticket counts by department and status
 * Returns hierarchical count data for folder view
 * Respects team-based filtering for users with team assignments
 */
session_start();
require_once '../../config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['analyst_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$analystId = (int)$_SESSION['analyst_id'];

try {
    $conn = connectToDatabase();

    // Check if user has team assignments
    $teamCheckSql = "SELECT COUNT(*) as team_count FROM analyst_teams WHERE analyst_id = ?";
    $teamCheckStmt = $conn->prepare($teamCheckSql);
    $teamCheckStmt->execute([$analystId]);
    $teamCount = $teamCheckStmt->fetch(PDO::FETCH_ASSOC)['team_count'];
    $teamCheckStmt->closeCursor();

    $hasTeamFilter = ($teamCount > 0);

    if ($hasTeamFilter) {
        // User has team assignments - filter to only their departments
        // First get the list of accessible department IDs
        $accessibleDeptsSql = "SELECT DISTINCT dt.department_id as dept_id
                               FROM department_teams dt
                               INNER JOIN analyst_teams ant ON dt.team_id = ant.team_id
                               WHERE ant.analyst_id = ?";
        $accessibleDeptsStmt = $conn->prepare($accessibleDeptsSql);
        $accessibleDeptsStmt->execute([$analystId]);
        $accessibleDepts = $accessibleDeptsStmt->fetchAll(PDO::FETCH_COLUMN);
        $accessibleDeptsStmt->closeCursor();

        if (empty($accessibleDepts)) {
            // No accessible departments - just count unassigned
            $totalCount = 0;
            $departments = [];
            $deptStatusCounts = [];
            $statusCounts = [];
        } else {
            // Build IN clause with the department IDs
            $deptIdPlaceholders = implode(',', array_fill(0, count($accessibleDepts), '?'));

            // Get total counts for accessible departments only
            $totalSql = "SELECT COUNT(*) as total FROM tickets t
                         WHERE t.department_id IN ($deptIdPlaceholders) OR t.department_id IS NULL";
            $totalStmt = $conn->prepare($totalSql);
            $totalStmt->execute($accessibleDepts);
            $totalResult = $totalStmt->fetch(PDO::FETCH_ASSOC);
            $totalCount = $totalResult['total'];
            $totalStmt->closeCursor();

            // Get counts by department (filtered by team)
            $deptSql = "SELECT
                            d.id,
                            d.name,
                            d.display_order,
                            COUNT(t.id) as count
                        FROM departments d
                        LEFT JOIN tickets t ON t.department_id = d.id
                        WHERE d.is_active = 1 AND d.id IN ($deptIdPlaceholders)
                        GROUP BY d.id, d.name, d.display_order
                        ORDER BY d.display_order, d.name";
            $deptStmt = $conn->prepare($deptSql);
            $deptStmt->execute($accessibleDepts);
            $departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
            $deptStmt->closeCursor();

            // Get counts by department and status (filtered by team)
            $deptStatusSql = "SELECT
                                d.id as dept_id,
                                ts.name AS status,
                                COUNT(t.id) as count
                              FROM departments d
                              LEFT JOIN tickets t ON t.department_id = d.id
                              LEFT JOIN ticket_statuses ts ON ts.id = t.status_id
                              WHERE d.is_active = 1 AND d.id IN ($deptIdPlaceholders)
                              GROUP BY d.id, ts.name";
            $deptStatusStmt = $conn->prepare($deptStatusSql);
            $deptStatusStmt->execute($accessibleDepts);
            $deptStatusCounts = $deptStatusStmt->fetchAll(PDO::FETCH_ASSOC);
            $deptStatusStmt->closeCursor();

            // Get counts by status for accessible departments
            $statusSql = "SELECT
                            ts.name AS status,
                            COUNT(*) as count
                          FROM tickets t
                          LEFT JOIN ticket_statuses ts ON ts.id = t.status_id
                          WHERE t.department_id IN ($deptIdPlaceholders) OR t.department_id IS NULL
                          GROUP BY ts.name";
            $statusStmt = $conn->prepare($statusSql);
            $statusStmt->execute($accessibleDepts);
            $statusCounts = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
            $statusStmt->closeCursor();
        }
    } else {
        // No team assignments - show all departments
        // Get total counts
        $totalSql = "SELECT COUNT(*) as total FROM tickets";
        $totalStmt = $conn->prepare($totalSql);
        $totalStmt->execute();
        $totalResult = $totalStmt->fetch(PDO::FETCH_ASSOC);
        $totalCount = $totalResult['total'];
        $totalStmt->closeCursor();

        // Get counts by department
        $deptSql = "SELECT
                        d.id,
                        d.name,
                        d.display_order,
                        COUNT(t.id) as count
                    FROM departments d
                    LEFT JOIN tickets t ON t.department_id = d.id
                    WHERE d.is_active = 1
                    GROUP BY d.id, d.name, d.display_order
                    ORDER BY d.display_order, d.name";
        $deptStmt = $conn->prepare($deptSql);
        $deptStmt->execute();
        $departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
        $deptStmt->closeCursor();

        // Get counts by department and status
        $deptStatusSql = "SELECT
                            d.id as dept_id,
                            ts.name AS status,
                            COUNT(t.id) as count
                          FROM departments d
                          LEFT JOIN tickets t ON t.department_id = d.id
                          LEFT JOIN ticket_statuses ts ON ts.id = t.status_id
                          WHERE d.is_active = 1
                          GROUP BY d.id, ts.name";
        $deptStatusStmt = $conn->prepare($deptStatusSql);
        $deptStatusStmt->execute();
        $deptStatusCounts = $deptStatusStmt->fetchAll(PDO::FETCH_ASSOC);
        $deptStatusStmt->closeCursor();

        // Get counts by status (all departments)
        $statusSql = "SELECT
                        ts.name AS status,
                        COUNT(*) as count
                      FROM tickets t
                      LEFT JOIN ticket_statuses ts ON ts.id = t.status_id
                      GROUP BY ts.name";
        $statusStmt = $conn->prepare($statusSql);
        $statusStmt->execute();
        $statusCounts = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
        $statusStmt->closeCursor();
    }

    // Get unassigned count (always visible to users regardless of teams)
    $unassignedSql = "SELECT COUNT(*) as count FROM tickets WHERE department_id IS NULL";
    $unassignedStmt = $conn->prepare($unassignedSql);
    $unassignedStmt->execute();
    $unassignedResult = $unassignedStmt->fetch(PDO::FETCH_ASSOC);
    $unassignedStmt->closeCursor();

    // Master list of active statuses — drives the folder UI
    $statusListStmt = $conn->query(
        "SELECT id, name, colour, is_closed, is_default, display_order
         FROM ticket_statuses
         WHERE is_active = 1
         ORDER BY display_order, id"
    );
    $activeStatuses = $statusListStmt->fetchAll(PDO::FETCH_ASSOC);
    $statusListStmt->closeCursor();

    $statusMeta = array_map(function ($s) {
        return [
            'name'          => $s['name'],
            'colour'        => $s['colour'],
            'is_closed'     => (int)$s['is_closed'],
            'is_default'    => (int)$s['is_default'],
            'display_order' => (int)$s['display_order'],
        ];
    }, $activeStatuses);
    $activeStatusNames = array_column($statusMeta, 'name');

    // Build status counts by department map (only counting active statuses)
    $statusByDept = [];
    foreach ($deptStatusCounts as $row) {
        if ($row['status'] === null) continue;
        if (!in_array($row['status'], $activeStatusNames, true)) continue;
        if (!isset($statusByDept[$row['dept_id']])) {
            $statusByDept[$row['dept_id']] = [];
        }
        $statusByDept[$row['dept_id']][$row['status']] = (int)$row['count'];
    }

    // Build department structure with status subfolders
    $departmentStructure = [];
    foreach ($departments as $dept) {
        $deptId = $dept['id'];
        $deptStatusMap = isset($statusByDept[$deptId]) ? $statusByDept[$deptId] : [];
        $statuses = [];
        foreach ($activeStatusNames as $name) {
            $statuses[$name] = $deptStatusMap[$name] ?? 0;
        }

        $departmentStructure[] = [
            'id' => $deptId,
            'name' => $dept['name'],
            'count' => (int)$dept['count'],
            'statuses' => $statuses
        ];
    }

    // Build overall status counts dynamically from active statuses
    $overallStatuses = [];
    foreach ($activeStatusNames as $name) {
        $overallStatuses[$name] = 0;
    }
    foreach ($statusCounts as $row) {
        if ($row['status'] !== null && isset($overallStatuses[$row['status']])) {
            $overallStatuses[$row['status']] = (int)$row['count'];
        }
    }

    echo json_encode([
        'success' => true,
        'total_count' => $totalCount,
        'unassigned_count' => (int)$unassignedResult['count'],
        'statuses' => $statusMeta,
        'departments' => $departmentStructure,
        'overall_statuses' => $overallStatuses
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>
