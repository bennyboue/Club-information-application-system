<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "ics_project");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$id = $_SESSION['id'];
$username = $_SESSION['username'];

// Include notifications functions
require_once 'notifications_functions.php';

// Handle pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Handle filter parameters
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_club = isset($_GET['club']) ? intval($_GET['club']) : 0;

// Build WHERE clause based on filters
$where_conditions = ["n.id = ?"];
$params = [$user_id];
$param_types = "i";

if ($filter_type !== 'all') {
    $where_conditions[] = "n.type = ?";
    $params[] = $filter_type;
    $param_types .= "s";
}

if ($filter_status !== 'all') {
    if ($filter_status === 'read') {
        $where_conditions[] = "n.is_read = 1";
    } else {
        $where_conditions[] = "n.is_read = 0";
    }
}

if ($filter_club > 0) {
    $where_conditions[] = "n.club_id = ?";
    $params[] = $filter_club;
    $param_types .= "i";
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total 
    FROM notifications n 
    LEFT JOIN clubs c ON n.club_id = c.id 
    WHERE {$where_clause}
";

$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$total_notifications = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_notifications / $per_page);

// Get notifications with pagination
$notifications_query = "
    SELECT n.*, c.name as club_name, c.initials as club_initials
    FROM notifications n 
    LEFT JOIN clubs c ON n.club_id = c.id 
    WHERE {$where_clause}
    ORDER BY n.created_at DESC 
    LIMIT ? OFFSET ?
";

$notifications_stmt = $conn->prepare($notifications_query);
$params[] = $per_page;
$params[] = $offset;
$param_types .= "ii";
$notifications_stmt->bind_param($param_types, ...$params);
$notifications_stmt->execute();
$notifications_result = $notifications_stmt->get_result();

// Get user's clubs for filter dropdown
$clubs_stmt = $conn->prepare("
    SELECT c.id, c.name, c.initials 
    FROM clubs c 
    JOIN memberships m ON c.id = m.club_id 
    WHERE m.user_id = ? 
    ORDER BY c.name ASC
");
$clubs_stmt->bind_param("i", $user_id);
$clubs_stmt->execute();
$user_clubs = $clubs_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get notification statistics
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
        SUM(CASE WHEN type = 'event' THEN 1 ELSE 0 END) as events,
        SUM(CASE WHEN type = 'announcement' THEN 1 ELSE 0 END) as announcements,
        SUM(CASE WHEN type = 'membership' THEN 1 ELSE 0 END) as membership
    FROM notifications 
    WHERE user_id = ?
");
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Notifications - School Club Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            margin: 0;
            padding: 0;
            background-color: rgb(169, 153, 136);
            line-height: 1.6;
        }

        .navbar {
            background-color: rgb(237, 222, 203);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .navbar .logo {
            font-size: 20px;
            font-weight: bold;
            color: black;
        }

        .navbar a {
            background-color: rgb(209, 120, 25);
            color: #fff;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 5px;
            margin-left: 10px;
            transition: background-color 0.3s ease, transform 0.3s ease;
        }

        .navbar a:hover {
            background-color: rgb(150, 85, 10);
            transform: scale(1.05);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .page-title {
            color: rgb(209, 120, 25);
            font-size: 28px;
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-subtitle {
            color: #666;
            margin: 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .stat-card {
            background: rgb(237, 222, 203);
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: rgb(209, 120, 25);
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .filters-section {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .filters-title {
            color: rgb(209, 120, 25);
            font-size: 18px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-label {
            font-weight: bold;
            color: #333;
            font-size: 14px;
        }

        .filter-select {
            padding: 8px 12px;
            border: 2px solid rgb(237, 222, 203);
            border-radius: 5px;
            background: white;
            font-family: inherit;
            transition: border-color 0.3s ease;
        }

        .filter-select:focus {
            outline: none;
            border-color: rgb(209, 120, 25);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            background-color: rgb(209, 120, 25);
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-family: inherit;
            font-size: 14px;
            transition: background-color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn:hover {
            background-color: rgb(150, 85, 10);
        }

        .btn-secondary {
            background-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #545b62;
        }

        .btn-danger {
            background-color: #dc3545;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }

        .notifications-section {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .section-title {
            color: rgb(209, 120, 25);
            font-size: 20px;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .bulk-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .notification-item {
            background: rgb(237, 222, 203);
            border-radius: 8px;
            padding: 20px;
            border-left: 4px solid rgb(209, 120, 25);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        .notification-item.unread {
            border-left-color: #dc3545;
            background: rgb(255, 248, 240);
        }

        .notification-item:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .notification-checkbox {
            margin-top: 2px;
        }

        .notification-content {
            flex: 1;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
            gap: 10px;
        }

        .notification-title {
            font-weight: 600;
            color: rgb(209, 120, 25);
            font-size: 16px;
            margin: 0;
        }

        .notification-status {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-unread {
            background: #dc3545;
            color: white;
        }

        .status-read {
            background: #28a745;
            color: white;
        }

        .type-badge {
            background: rgb(209, 120, 25);
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            text-transform: capitalize;
        }

        .notification-message {
            color: #333;
            margin-bottom: 10px;
            line-height: 1.5;
        }

        .notification-meta {
            display: flex;
            gap: 15px;
            font-size: 12px;
            color: #666;
            flex-wrap: wrap;
        }

        .club-tag {
            background: rgb(209, 120, 25);
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .notification-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }

        .action-btn {
            padding: 4px 8px;
            font-size: 12px;
            border-radius: 4px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            transform: translateY(-1px);
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
            flex-wrap: wrap;
        }

        .pagination a, .pagination span {
            padding: 8px 12px;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .pagination a {
            background: rgb(237, 222, 203);
            color: rgb(209, 120, 25);
        }

        .pagination a:hover {
            background: rgb(209, 120, 25);
            color: white;
        }

        .pagination .current {
            background: rgb(209, 120, 25);
            color: white;
            font-weight: bold;
        }

        .no-notifications {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .no-notifications-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
        }

        .success-message, .error-message {
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            display: none;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .section-header {
                flex-direction: column;
                align-items: stretch;
            }

            .bulk-actions {
                justify-content: center;
            }

            .notification-item {
                padding: 15px;
            }

            .notification-header {
                flex-direction: column;
                align-items: stretch;
            }

            .notification-meta {
                flex-direction: column;
                gap: 5px;
            }

            .pagination {
                gap: 5px;
            }

            .pagination a, .pagination span {
                padding: 6px 10px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>

<div class="navbar">
    <div class="logo">
        <a href="home.php" style="color: black; text-decoration: none;">School Club System</a>
    </div>
    <div>
        <span style="color:black; font-weight:bold;">Hello, <?php echo htmlspecialchars($username); ?>!</span>
        <a href="student_dashboard.php">Dashboard</a>
        <a href="home.php">Browse Clubs</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">📬 Notifications</h1>
        <p class="page-subtitle">Manage your club notifications and stay updated</p>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Notifications</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['unread']; ?></div>
                <div class="stat-label">Unread</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['events']; ?></div>
                <div class="stat-label">Events</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['announcements']; ?></div>
                <div class="stat-label">Announcements</div>
            </div>
        </div>
    </div>

    <div class="success-message" id="successMessage"></div>
    <div class="error-message" id="errorMessage"></div>

    <div class="filters-section">
        <h3 class="filters-title">🔍 Filter Notifications</h3>
        <form method="GET" action="notifications.php" id="filtersForm">
            <div class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label">Status:</label>
                    <select name="status" class="filter-select">
                        <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="unread" <?php echo $filter_status === 'unread' ? 'selected' : ''; ?>>Unread Only</option>
                        <option value="read" <?php echo $filter_status === 'read' ? 'selected' : ''; ?>>Read Only</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Type:</label>
                    <select name="type" class="filter-select">
                        <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="event" <?php echo $filter_type === 'event' ? 'selected' : ''; ?>>Events</option>
                        <option value="announcement" <?php echo $filter_type === 'announcement' ? 'selected' : ''; ?>>Announcements</option>
                        <option value="membership" <?php echo $filter_type === 'membership' ? 'selected' : ''; ?>>Membership</option>
                        <option value="system" <?php echo $filter_type === 'system' ? 'selected' : ''; ?>>System</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Club:</label>
                    <select name="club" class="filter-select">
                        <option value="0">All Clubs</option>
                        <?php foreach ($user_clubs as $club): ?>
                            <option value="<?php echo $club['id']; ?>" <?php echo $filter_club === $club['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($club['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn">🔍 Apply Filters</button>
                <a href="notifications.php" class="btn btn-secondary">🔄 Clear Filters</a>
            </div>
        </form>
    </div>

    <div class="notifications-section">
        <div class="section-header">
            <h3 class="section-title">📋 Your Notifications</h3>
            <div class="bulk-actions">
                <button onclick="selectAll()" class="btn btn-secondary">☑️ Select All</button>
                <button onclick="markSelectedAsRead()" class="btn">✅ Mark as Read</button>
                <button onclick="deleteSelected()" class="btn btn-danger">🗑️ Delete Selected</button>
            </div>
        </div>

        <?php if ($notifications_result->num_rows > 0): ?>
            <div class="notifications-list">
                <?php while ($notification = $notifications_result->fetch_assoc()): ?>
                    <?php 
                    $read_class = $notification['is_read'] ? 'read' : 'unread';
                    $time_ago = timeAgo($notification['created_at']);
                    ?>
                    <div class="notification-item <?php echo $read_class; ?>" 
                         data-notification-id="<?php echo $notification['id']; ?>">
                        
                        <input type="checkbox" class="notification-checkbox" 
                               value="<?php echo $notification['id']; ?>" 
                               onchange="updateBulkActions()">
                        
                        <div class="notification-content">
                            <div class="notification-header">
                                <h4 class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></h4>
                                <div class="notification-status">
                                    <span class="type-badge"><?php echo ucfirst($notification['type']); ?></span>
                                    <span class="status-badge status-<?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                                        <?php echo $notification['is_read'] ? 'Read' : 'Unread'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="notification-message">
                                <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                            </div>
                            
                            <div class="notification-meta">
                                <?php if ($notification['club_name']): ?>
                                    <span class="club-tag">
                                        📍 <?php echo htmlspecialchars($notification['club_initials'] ?? $notification['club_name']); ?>
                                    </span>
                                <?php endif; ?>
                                <span>🕒 <?php echo $time_ago; ?></span>
                                <span>📅 <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></span>
                            </div>
                            
                            <div class="notification-actions">
                                <?php if (!$notification['is_read']): ?>
                                    <button onclick="markAsRead(<?php echo $notification['id']; ?>)" 
                                            class="action-btn btn">Mark as Read</button>
                                <?php endif; ?>
                                <button onclick="deleteNotification(<?php echo $notification['id']; ?>)" 
                                        class="action-btn btn btn-danger">Delete</button>
                                <?php if ($notification['club_id']): ?>
                                    <a href="club_details.php?id=<?php echo $notification['club_id']; ?>" 
                                       class="action-btn btn btn-secondary">View Club</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&<?php echo http_build_query(array_filter(['type' => $filter_type !== 'all' ? $filter_type : null, 'status' => $filter_status !== 'all' ? $filter_status : null, 'club' => $filter_club > 0 ? $filter_club : null])); ?>">
                            ← Previous
                        </a>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter(['type' => $filter_type !== 'all' ? $filter_type : null, 'status' => $filter_status !== 'all' ? $filter_status : null, 'club' => $filter_club > 0 ? $filter_club : null])); ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&<?php echo http_build_query(array_filter(['type' => $filter_type !== 'all' ? $filter_type : null, 'status' => $filter_status !== 'all' ? $filter_status : null, 'club' => $filter_club > 0 ? $filter_club : null])); ?>">
                            Next →
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="no-notifications">
                <div class="no-notifications-icon">📭</div>
                <h3>No notifications found</h3>
                <p>
                    <?php if ($filter_type !== 'all' || $filter_status !== 'all' || $filter_club > 0): ?>
                        Try adjusting your filters to see more notifications.
                    <?php else: ?>
                        You don't have any notifications yet. Join some clubs to start receiving updates!
                    <?php endif; ?>
                </p>
                <?php if ($filter_type !== 'all' || $filter_status !== 'all' || $filter_club > 0): ?>
                    <a href="notifications.php" class="btn">Clear Filters</a>
                <?php else: ?>
                    <a href="home.php" class="btn">Browse Clubs</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
let selectedNotifications = [];

function updateBulkActions() {
    const checkboxes = document.querySelectorAll('.notification-checkbox:checked');
    selectedNotifications = Array.from(checkboxes).map(cb => parseInt(cb.value));
    
    const bulkActions = document.querySelector('.bulk-actions');
    const hasSelected = selectedNotifications.length > 0;
    
    bulkActions.style.opacity = hasSelected ? '1' : '0.6';
}

function selectAll() {
    const checkboxes = document.querySelectorAll('.notification-checkbox');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    
    checkboxes.forEach(cb => cb.checked = !allChecked);
    updateBulkActions();
}

function markAsRead(notificationId) {
    const ids = notificationId ? [notificationId] : selectedNotifications;
    
    if (ids.length === 0) {
        showMessage('Please select notifications to mark as read.', 'error');
        return;
    }
    
    fetch('mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            notification_ids: ids
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Notifications marked as read successfully!', 'success');
            
            // Update UI
            ids.forEach(id => {
                const item = document.querySelector(`[data-notification-id="${id}"]`);
                if (item) {
                    item.classList.remove('unread');
                    item.classList.add('read');
                    
                    // Update status badge
                    const statusBadge = item.querySelector('.status-badge');
                    if (statusBadge) {
                        statusBadge.className = 'status-badge status-read';
                        statusBadge.textContent = 'Read';
                    }
                    
                    // Remove mark as read button
                    const markReadBtn = item.querySelector('.action-btn:not(.btn-danger):not(.btn-secondary)');
                    if (markReadBtn) {
                        markReadBtn.remove();
                    }
                }
            });
            
            // Clear selections
            document.querySelectorAll('.notification-checkbox:checked').forEach(cb => cb.checked = false);
            updateBulkActions();
            
            // Refresh page after 2 seconds to update stats
            setTimeout(() => location.reload(), 2000);
        } else {
            showMessage('Error marking notifications as read: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Error marking notifications as read. Please try again.', 'error');
}
</script>

</body>
</html>

function markSelectedAsRead() {
    markAsRead();
}

function deleteNotification(notificationId) {
    if (!confirm('Are you sure you want to delete this notification?')) {
        return;
    }
    
    const ids = notificationId ? [notificationId] : selectedNotifications;
    
    if (ids.length === 0) {
        showMessage('Please select notifications to delete.', 'error');
        return;
    }
    
    fetch('delete_notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            notification_ids: ids
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Notifications deleted successfully!', 'success');
            
            // Remove items from UI
            ids.forEach(id => {
                const item = document.querySelector(`[data-notification-id="${id}"]`);
                if (item) {
                    item.style.transition = 'all 0.3s ease';
                    item.style.opacity = '0';
                    item.style.transform = 'translateX(-100%)';
                    setTimeout(() => item.remove(), 300);
                }
            });
            
            // Clear selections
            document.querySelectorAll('.notification-checkbox:checked').forEach(cb => cb.checked = false);
            updateBulkActions();
            
            // Refresh page after 2 seconds to update stats and pagination
            setTimeout(() => location.reload(), 2000);
        } else {
            showMessage('Error deleting notifications: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Error deleting notifications. Please try again.', 'error');
    });
}

function deleteSelected() {
    if (selectedNotifications.length === 0) {
        showMessage('Please select notifications to delete.', 'error');
        return;
    }
    
    const count = selectedNotifications.length;
    if (!confirm(`Are you sure you want to delete ${count} notification${count > 1 ? 's' : ''}?`)) {
        return;
    }
    
    deleteNotification();
}

function showMessage(message, type) {
    const messageDiv = document.getElementById(type === 'error' ? 'errorMessage' : 'successMessage');
    const otherDiv = document.getElementById(type === 'error' ? 'successMessage' : 'errorMessage');
    
    // Hide other message type
    otherDiv.style.display = 'none';
    
    // Show message
    messageDiv.textContent = message;
    messageDiv.style.display = 'block';
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        messageDiv.style.display = 'none';
    }, 5000);
    
    // Scroll to top to show message
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Auto-refresh notification count every 2 minutes
setInterval(function() {
    if (document.hasFocus()) {
        fetch('get_notification_count.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update unread count in stats
                const unreadStat = document.querySelector('.stat-card:nth-child(2) .stat-number');
                if (unreadStat && data.unread_count !== undefined) {
                    unreadStat.textContent = data.unread_count;
                }
            }
        })
        .catch(error => console.error('Error refreshing notification count:', error));
    }
}, 120000); // 2 minutes

// Initialize bulk actions state
document.addEventListener('DOMContentLoaded', function() {
    updateBulkActions();
    
    // Add click handlers for notification items (but not checkboxes or buttons)
    document.querySelectorAll('.notification-item').forEach(item => {
        item.addEventListener('click', function(e) {
            // Don't trigger if clicking on checkbox, button, or link
            if (e.target.type === 'checkbox' || 
                e.target.tagName === 'BUTTON' || 
                e.target.tagName === 'A' ||
                e.target.closest('button') ||
                e.target.closest('a')) {
                return;
            }
            
            const notificationId = parseInt(this.dataset.notificationId);
            if (this.classList.contains('unread')) {
                markAsRead(notificationId);
            }
        });
    });
    
    // Auto-submit form when filters change
    document.querySelectorAll('.filter-select').forEach(select => {
        select.addEventListener('change', function() {
            // Add a small delay to allow multiple selections
            setTimeout(() => {
                document.getElementById('filtersForm').submit();
            }, 100);
        });
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + A to select all
    if ((e.ctrlKey || e.metaKey) && e.key === 'a' && !e.target.matches('input, textarea, select')) {
        e.preventDefault();
        selectAll();
    }
    
    // Delete key to delete selected
    if (e.key === 'Delete' && selectedNotifications.length > 0 && !e.target.matches('input, textarea, select')) {
        e.preventDefault();
        deleteSelected();
    }
    
    // Enter key to mark selected as read
    if (e.key === 'Enter' && selectedNotifications.length > 0 && !e.target.matches('input, textarea, select, button')) {
        e.preventDefault();
        markSelectedAsRead();
    }
});

// Handle browser back/forward navigation
window.addEventListener('popstate', function() {
    location.reload();
});

// Add loading state for async operations
function setLoadingState(element, loading) {
    if (loading) {
        element.disabled = true;
        element.style.opacity = '0.6';
        element.innerHTML = element.innerHTML.replace(/^/, '⏳ ');
    } else {
        element.disabled = false;
        element.style.opacity = '1';
        element.innerHTML = element.innerHTML.replace('⏳ ', '');
    }
}

// Enhanced error handling
window.addEventListener('error', function(e) {
    console.error('JavaScript error:', e.error);
    showMessage('An unexpected error occurred. Please refresh the page and try again.', 'error');
});

// Service worker for offline functionality (optional)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js').then(function(registration) {
            console.log('ServiceWorker registration successful');
        }, function(err) {
            console.log('ServiceWorker registration failed');
        });
    });
}