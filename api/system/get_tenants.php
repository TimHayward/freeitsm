<?php
/**
 * API: List companies (tenants).
 * GET - returns every company. "Company" is the user-facing word for a tenant;
 * the underlying table/code stays `tenants`.
 */
session_start(['read_and_close' => true]);
require_once '../../config.php';
require_once '../../includes/functions.php';
require_once '../../includes/tenancy.php';

header('Content-Type: application/json');

if (!isset($_SESSION['analyst_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    $conn = connectToDatabase();

    $companies = [];
    foreach (getAllTenants($conn) as $t) {
        $companies[] = [
            'id'         => (int)$t['id'],
            'name'       => $t['name'],
            'is_default' => (bool)$t['is_default'],
            'is_active'  => (bool)$t['is_active'],
        ];
    }

    echo json_encode(['success' => true, 'companies' => $companies]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
