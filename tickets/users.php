<?php
/**
 * Users - View all users and their tickets
 */
session_start();
require_once '../config.php';

$current_page = 'users';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Desk - Users</title>
    <link rel="stylesheet" href="../assets/css/inbox.css">
    <style>
        .users-container {
            display: flex;
            flex: 1;
            overflow: hidden;
            gap: 1px;
            background-color: #e0e0e0;
        }

        .users-list-container {
            width: 400px;
            min-width: 300px;
            background-color: #fff;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .users-list-header {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            background-color: #f8f9fa;
        }

        .users-list-header h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
            color: #333;
        }

        .search-box {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .search-box:focus {
            outline: none;
            border-color: #0078d4;
            box-shadow: 0 0 0 2px rgba(0, 120, 212, 0.1);
        }

        .users-list {
            flex: 1;
            overflow-y: auto;
        }

        .user-item {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background-color 0.15s;
        }

        .user-item:hover {
            background-color: #f5f5f5;
        }

        .user-item.selected {
            background-color: #e8f4fc;
            border-left: 3px solid #0078d4;
        }

        .user-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .user-email {
            font-size: 13px;
            color: #666;
            margin-bottom: 4px;
        }

        .user-meta {
            font-size: 12px;
            color: #888;
            display: flex;
            gap: 15px;
        }

        .user-detail-container {
            flex: 1;
            background-color: #fff;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .user-detail-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            background-color: #f8f9fa;
        }

        .user-detail-name {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin: 0 0 5px 0;
        }

        .user-detail-email {
            font-size: 14px;
            color: #666;
        }

        .user-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 12px;
            color: #888;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .info-value {
            font-size: 14px;
            color: #333;
        }

        .tickets-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .tickets-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .tickets-list {
            flex: 1;
            overflow-y: auto;
        }

        .ticket-row {
            display: grid;
            grid-template-columns: 130px 1fr 100px 80px 130px;
            padding: 12px 20px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background-color 0.15s;
            align-items: center;
        }

        .ticket-row:hover {
            background-color: #f5f5f5;
        }

        .ticket-row-header {
            font-weight: 600;
            background-color: #f0f0f0;
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }

        .ticket-row-header:hover {
            background-color: #f0f0f0;
            cursor: default;
        }

        .ticket-number {
            color: #0078d4;
            font-weight: 500;
            white-space: nowrap;
        }

        .ticket-subject {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            padding-right: 15px;
        }

        .ticket-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            text-align: center;
            width: fit-content;
        }

        .ticket-priority {
            padding-left: 10px;
        }

        .ticket-priority {
            font-size: 13px;
        }

        .ticket-date {
            font-size: 13px;
            color: #666;
        }

        .empty-state {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 1;
            color: #888;
            font-size: 14px;
            padding: 40px;
            text-align: center;
        }

        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .spinner {
            width: 30px;
            height: 30px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #0078d4;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .user-count {
            font-size: 12px;
            color: #888;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="main-container users-container">
        <!-- Users List -->
        <div class="users-list-container">
            <div class="users-list-header">
                <h3>Users</h3>
                <input type="text" class="search-box" id="userSearch" placeholder="Search users..." oninput="searchUsers()">
                <div class="user-count" id="userCount"></div>
            </div>
            <div class="users-list" id="usersList">
                <div class="loading">
                    <div class="spinner"></div>
                </div>
            </div>
        </div>

        <!-- User Detail -->
        <div class="user-detail-container" id="userDetail">
            <div class="empty-state">
                Select a user to view their details and tickets
            </div>
        </div>
    </div>

    <script>
        const API_BASE = '../api/tickets/';
        let users = [];
        let selectedUserId = null;
        let searchTimeout = null;

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadUsers();
        });

        // Load users from API
        async function loadUsers(search = '') {
            try {
                const url = search ? `${API_BASE}get_users.php?search=${encodeURIComponent(search)}` : API_BASE + 'get_users.php';
                const response = await fetch(url);
                const data = await response.json();

                if (data.success) {
                    users = data.users;
                    renderUsersList();
                } else {
                    console.error('Error loading users:', data.error);
                }
            } catch (error) {
                console.error('Error loading users:', error);
            }
        }

        // Render users list
        function renderUsersList() {
            const container = document.getElementById('usersList');
            const countEl = document.getElementById('userCount');

            if (users.length === 0) {
                container.innerHTML = '<div class="empty-state">No users found</div>';
                countEl.textContent = '0 users';
                return;
            }

            countEl.textContent = `${users.length} user${users.length !== 1 ? 's' : ''}`;

            container.innerHTML = users.map(user => `
                <div class="user-item ${selectedUserId == user.id ? 'selected' : ''}" onclick="selectUser(${user.id})">
                    <div class="user-name">${escapeHtml(user.display_name || 'Unknown')}</div>
                    <div class="user-email">${escapeHtml(user.email || '')}</div>
                    <div class="user-meta">
                        <span>${user.ticket_count} ticket${user.ticket_count != 1 ? 's' : ''}</span>
                    </div>
                </div>
            `).join('');
        }

        // Search users with debounce
        function searchUsers() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const search = document.getElementById('userSearch').value;
                loadUsers(search);
            }, 300);
        }

        // Select a user and show their details
        async function selectUser(userId) {
            selectedUserId = userId;
            renderUsersList();

            const user = users.find(u => u.id == userId);
            if (!user) return;

            const detailContainer = document.getElementById('userDetail');
            detailContainer.innerHTML = `
                <div class="user-detail-header">
                    <h2 class="user-detail-name">${escapeHtml(user.display_name || 'Unknown')}</h2>
                    <div class="user-detail-email">${escapeHtml(user.email || '')}</div>
                </div>
                <div class="user-info-grid">
                    <div class="info-item">
                        <span class="info-label">Email</span>
                        <span class="info-value">${escapeHtml(user.email || '-')}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">First Seen</span>
                        <span class="info-value">${formatDate(user.created_at)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Total Tickets</span>
                        <span class="info-value">${user.ticket_count}</span>
                    </div>
                </div>
                <div class="tickets-section">
                    <div class="tickets-header">Tickets (${user.ticket_count})</div>
                    <div class="tickets-list" id="ticketsList">
                        <div class="loading"><div class="spinner"></div></div>
                    </div>
                </div>
            `;

            // Load user's tickets
            loadUserTickets(userId);
        }

        // Load tickets for selected user
        async function loadUserTickets(userId) {
            try {
                const response = await fetch(`${API_BASE}get_user_tickets.php?user_id=${userId}`);
                const data = await response.json();

                const container = document.getElementById('ticketsList');

                if (data.success) {
                    if (data.tickets.length === 0) {
                        container.innerHTML = '<div class="empty-state">No tickets found for this user</div>';
                        return;
                    }

                    container.innerHTML = `
                        <div class="ticket-row ticket-row-header">
                            <span>Ticket #</span>
                            <span>Subject</span>
                            <span>Status</span>
                            <span>Priority</span>
                            <span>Created</span>
                        </div>
                        ${data.tickets.map(ticket => {
                            const c = ticket.status_colour || '#0078d4';
                            const statusStyle = `background-color: ${c}1f; color: ${c}; border: 1px solid ${c}33;`;
                            return `
                            <div class="ticket-row" onclick="viewTicket(${ticket.id})">
                                <span class="ticket-number">${escapeHtml(ticket.ticket_number)}</span>
                                <span class="ticket-subject">${escapeHtml(ticket.subject)}</span>
                                <span class="ticket-status" style="${statusStyle}">${escapeHtml(ticket.status || 'New')}</span>
                                <span class="ticket-priority">${escapeHtml(ticket.priority || '-')}</span>
                                <span class="ticket-date">${formatDate(ticket.created_datetime)}</span>
                            </div>
                        `;}).join('')}
                    `;
                } else {
                    container.innerHTML = '<div class="empty-state">Error loading tickets</div>';
                }
            } catch (error) {
                console.error('Error loading tickets:', error);
                document.getElementById('ticketsList').innerHTML = '<div class="empty-state">Error loading tickets</div>';
            }
        }

        // View ticket in inbox
        function viewTicket(ticketId) {
            // Navigate to inbox with ticket selected
            window.location.href = `index.php?ticket_id=${ticketId}`;
        }

        // Escape HTML for safe display
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Format date for display
        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        }
    </script>
</body>
</html>
