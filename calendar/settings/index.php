<?php
/**
 * Calendar Settings - Manage event categories
 */
session_start();
require_once '../../config.php';

// Check if user is logged in
if (!isset($_SESSION['analyst_id'])) {
    header('Location: ../../login.php');
    exit;
}

$analyst_name = $_SESSION['analyst_name'] ?? 'Analyst';
$current_page = 'settings';
$path_prefix = '../../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Desk - Calendar settings</title>
    <link rel="stylesheet" href="../../assets/css/inbox.css">
    <style>
        /* Full-width settings page matching the canonical settings layout
           (change-management/settings, tickets/settings). */
        .container {
            height: calc(100vh - 48px);
            overflow-y: auto;
            max-width: none;
            margin: 0;
            padding: 16px 30px 24px;
        }

        .settings-section {
            background: white;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            margin-bottom: 24px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
            gap: 16px;
        }

        .section-header h2 {
            margin: 0 0 6px;
            font-size: 18px;
            color: #333;
        }

        .section-header p {
            margin: 0;
            color: #666;
            font-size: 13px;
        }

        .add-btn {
            background: #ef6c00;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            flex-shrink: 0;
        }
        .add-btn:hover { background: #e65100; }

        /* Categories table */
        .lookup-table { width: 100%; border-collapse: collapse; }
        .lookup-table th,
        .lookup-table td {
            padding: 10px 8px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        .lookup-table th {
            font-weight: 600;
            color: #666;
            background: #fafafa;
        }
        /* Force the Actions column to size to its content (width: 1%) and
           never wrap the icon buttons. Same trick the change-management
           settings table uses. */
        .lookup-table td:last-child,
        .lookup-table th:last-child {
            white-space: nowrap;
            width: 1%;
        }

        .swatch {
            display: inline-block;
            width: 18px;
            height: 18px;
            border-radius: 3px;
            vertical-align: middle;
            border: 1px solid #ddd;
            margin-right: 6px;
        }

        .badge-active {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            background: #fff3e0;
            color: #ef6c00;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-inactive {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            background: #fafafa;
            color: #999;
            font-size: 11px;
            font-weight: 600;
        }

        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            color: #666;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: middle;
        }
        .action-btn:hover { color: #ef6c00; }
        .action-btn.delete:hover { color: #c62828; }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        /* Modal */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal.active { display: flex; }

        .modal-content {
            background: #fff;
            border-radius: 8px;
            width: 450px;
            max-width: 90vw;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .modal-body { padding: 20px; }

        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn-primary { background: #ef6c00; color: white; }
        .btn-primary:hover { background: #e65100; }
        .btn-secondary { background: #f0f0f0; color: #333; border: 1px solid #ddd; }
        .btn-secondary:hover { background: #e0e0e0; }

        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 6px;
            color: #333;
            font-size: 13px;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-group input:focus,
        .form-group textarea:focus { outline: none; border-color: #ef6c00; }
        .form-group textarea { resize: vertical; min-height: 80px; }

        .form-row { display: flex; gap: 16px; }
        .form-row .form-group { flex: 1; }

        .form-group input[type="color"] {
            width: 60px;
            height: 40px;
            padding: 2px;
            cursor: pointer;
        }

        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #333;
            cursor: pointer;
        }
        .form-checkbox input { width: 18px; height: 18px; cursor: pointer; }

        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px;
        }
        .spinner {
            width: 32px;
            height: 32px;
            border: 3px solid #e0e0e0;
            border-top-color: #ef6c00;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container">
        <div class="settings-section">
            <div class="section-header">
                <div>
                    <h2>Event categories</h2>
                    <p>Manage categories used to organise calendar events. Each category can have a custom colour for easy identification.</p>
                </div>
                <button class="add-btn" onclick="openCategoryModal()">Add</button>
            </div>

            <table class="lookup-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="categoryTableBody">
                    <tr><td colspan="4"><div class="loading"><div class="spinner"></div></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Category Modal -->
    <div class="modal" id="categoryModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="categoryModalTitle">Add category</h3>
            </div>
            <div class="modal-body">
                <input type="hidden" id="categoryId" value="">
                <div class="form-group">
                    <label for="categoryName">Name *</label>
                    <input type="text" id="categoryName" placeholder="e.g. Certificate expiry">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="categoryColor">Colour</label>
                        <input type="color" id="categoryColor" value="#ef6c00">
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end; padding-bottom: 10px;">
                        <label class="form-checkbox">
                            <input type="checkbox" id="categoryActive" checked>
                            Active
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="categoryDescription">Description</label>
                    <textarea id="categoryDescription" placeholder="Optional description..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeCategoryModal()">Cancel</button>
                <button class="btn btn-primary" onclick="saveCategory()">Save</button>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = '../../api/calendar/';
        let categories = [];

        // SVG icons used in the action column. Centralised so future polish
        // (size, stroke width) only touches one place.
        const ICON_EDIT = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>';
        const ICON_DELETE = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>';

        document.addEventListener('DOMContentLoaded', function() {
            loadCategories();
        });

        async function loadCategories() {
            try {
                const response = await fetch(API_BASE + 'get_categories.php');
                const data = await response.json();

                if (data.success) {
                    categories = data.categories;
                    renderCategories();
                } else {
                    document.getElementById('categoryTableBody').innerHTML =
                        '<tr><td colspan="4"><div class="empty-state">Error loading categories</div></td></tr>';
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('categoryTableBody').innerHTML =
                    '<tr><td colspan="4"><div class="empty-state">Error loading categories</div></td></tr>';
            }
        }

        function renderCategories() {
            const tbody = document.getElementById('categoryTableBody');

            if (categories.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4"><div class="empty-state">No categories yet. Click <strong>Add</strong> to create one.</div></td></tr>';
                return;
            }

            tbody.innerHTML = categories.map(cat => `
                <tr>
                    <td>
                        <span class="swatch" style="background-color: ${cat.color}"></span>
                        ${escapeHtml(cat.name)}
                    </td>
                    <td>${cat.description ? escapeHtml(cat.description) : '<span style="color:#999">&mdash;</span>'}</td>
                    <td>
                        <span class="${cat.is_active ? 'badge-active' : 'badge-inactive'}">
                            ${cat.is_active ? 'Active' : 'Inactive'}
                        </span>
                    </td>
                    <td>
                        <button class="action-btn" onclick="editCategory(${cat.id})" title="Edit">${ICON_EDIT}</button>
                        <button class="action-btn delete" onclick="deleteCategory(${cat.id})" title="Delete">${ICON_DELETE}</button>
                    </td>
                </tr>
            `).join('');
        }

        function openCategoryModal(categoryId = null) {
            const modal = document.getElementById('categoryModal');
            const title = document.getElementById('categoryModalTitle');

            // Reset form
            document.getElementById('categoryId').value = '';
            document.getElementById('categoryName').value = '';
            document.getElementById('categoryColor').value = '#ef6c00';
            document.getElementById('categoryDescription').value = '';
            document.getElementById('categoryActive').checked = true;

            if (categoryId) {
                const cat = categories.find(c => c.id == categoryId);
                if (cat) {
                    title.textContent = 'Edit category';
                    document.getElementById('categoryId').value = cat.id;
                    document.getElementById('categoryName').value = cat.name;
                    document.getElementById('categoryColor').value = cat.color;
                    document.getElementById('categoryDescription').value = cat.description || '';
                    document.getElementById('categoryActive').checked = cat.is_active;
                }
            } else {
                title.textContent = 'Add category';
            }

            modal.classList.add('active');
        }

        function editCategory(id) {
            openCategoryModal(id);
        }

        function closeCategoryModal() {
            document.getElementById('categoryModal').classList.remove('active');
        }

        async function saveCategory() {
            const id = document.getElementById('categoryId').value;
            const name = document.getElementById('categoryName').value.trim();
            const color = document.getElementById('categoryColor').value;
            const description = document.getElementById('categoryDescription').value.trim();
            const isActive = document.getElementById('categoryActive').checked;

            if (!name) {
                alert('Please enter a category name');
                return;
            }

            const payload = {
                id: id || null,
                name,
                color,
                description,
                is_active: isActive
            };

            try {
                const response = await fetch(API_BASE + 'save_category.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await response.json();

                if (data.success) {
                    closeCategoryModal();
                    loadCategories();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error saving category');
            }
        }

        async function deleteCategory(id) {
            if (!confirm('Are you sure you want to delete this category?')) return;

            try {
                const response = await fetch(API_BASE + 'delete_category.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                const data = await response.json();

                if (data.success) {
                    loadCategories();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error deleting category');
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
