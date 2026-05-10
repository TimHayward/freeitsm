<?php
/**
 * API: List CMDB classes (with property count per class)
 */
session_start();
require_once '../../config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['analyst_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    $conn = connectToDatabase();

    $sql = "SELECT c.id, c.class_key, c.name, c.description, c.icon,
                   c.display_order, c.is_active,
                   (SELECT COUNT(*) FROM cmdb_class_properties WHERE class_id = c.id) AS property_count,
                   (SELECT COUNT(*) FROM cmdb_objects WHERE class_id = c.id) AS object_count
              FROM cmdb_classes c
             ORDER BY c.display_order, c.name";

    $stmt = $conn->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['display_order'] = (int)$r['display_order'];
        $r['is_active'] = (int)$r['is_active'] === 1;
        $r['property_count'] = (int)$r['property_count'];
        $r['object_count'] = (int)$r['object_count'];
    }

    echo json_encode(['success' => true, 'classes' => $rows]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
