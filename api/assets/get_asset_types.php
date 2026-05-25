<?php
/**
 * API Endpoint: Get asset types
 */
// read_and_close releases the session lock immediately so multiple
// AJAX calls from the same page can run in parallel rather than queueing
// behind PHP's default exclusive session lock (see #388).
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['analyst_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    $conn = connectToDatabase();

    $sql = "SELECT id, name, description, is_active, display_order, created_datetime
            FROM asset_types
            ORDER BY display_order, name";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $asset_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($asset_types as &$type) {
        $type['is_active'] = (bool)$type['is_active'];
    }

    echo json_encode([
        'success' => true,
        'asset_types' => $asset_types
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>
