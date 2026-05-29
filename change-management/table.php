<?php
/**
 * Change Management — Full-screen table view
 *
 * Thin page over the shared data-table engine (assets/js/data-table.js +
 * assets/css/data-table.css). Inline-editable for the low-risk list fields
 * (priority, impact, type, assignee) via api/change-management/update_field.php;
 * status + the detailed fields stay in the full form (click a row to open it).
 * The change-specific columns + saves live in assets/js/change-table.js.
 */
session_start();
require_once '../config.php';

if (!isset($_SESSION['analyst_id'])) {
    header('Location: ../login.php');
    exit;
}

$current_page = 'table';
$path_prefix = '../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Desk - Change Table</title>
    <link rel="stylesheet" href="../assets/css/inbox.css">
    <link rel="stylesheet" href="../assets/css/change-management.css">
    <link rel="stylesheet" href="../assets/css/data-table.css?v=1">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="dt-page">
        <?php include '../includes/data-table-skeleton.php'; ?>
    </div>

    <script src="../assets/js/data-table.js?v=2"></script>
    <script src="../assets/js/change-table.js?v=3"></script>
</body>
</html>
