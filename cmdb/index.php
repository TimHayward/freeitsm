<?php
/**
 * CMDB - Configuration Management Database
 * Browse / search / detail view for objects across all classes.
 *
 * V1 status: settings page (classes, properties, relationship types, AI integration)
 * is shipping first. The browse + object detail UI lands in the next pass.
 */
session_start();
require_once '../config.php';

$current_page = 'browse';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FreeITSM - CMDB</title>
    <link rel="stylesheet" href="../assets/css/inbox.css">
    <style>
        body { overflow: auto; height: auto; background: #f5f5f5; }
        .empty-state {
            max-width: 700px;
            margin: 60px auto;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .empty-state h2 {
            color: #be185d;
            margin-bottom: 12px;
        }
        .empty-state p {
            color: #555;
            line-height: 1.6;
            margin-bottom: 16px;
        }
        .empty-state a.btn {
            display: inline-block;
            margin-top: 16px;
            padding: 10px 22px;
            background: #be185d;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
        }
        .empty-state a.btn:hover {
            background: #9d174d;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="empty-state">
        <h2>CMDB — Configuration Management Database</h2>
        <p>Model your IT estate as a graph of typed objects. Define classes (Server, Database, Application…), give each class its own properties, and link objects through a strict containment hierarchy plus user-defined relationships.</p>
        <p>Start by configuring classes, properties, and relationship types in Settings. Object browsing and the AI-powered detail view ship in the next release.</p>
        <a href="settings/" class="btn">Open Settings</a>
    </div>
</body>
</html>
