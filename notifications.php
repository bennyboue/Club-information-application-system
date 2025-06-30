<?php
session_start();

// Redirect to login if not logged in - FIXED SESSION VARIABLE
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "ics_project");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// FIXED: Use 'user_id' instead of 'id'
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_role = $_SESSION['role'] ?? 'student';

// Handle pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Handle filter parameters
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_club = isset($_GET['club']) ? intval($_GET['club']) : 0;

// Get user's club memberships
$user_clubs_query = "SELECT club_id FROM memberships WHERE user_id = ? AND status = 'approved'";
$user_clubs_stmt = $conn->prepare($user_clubs_query);
$user_clubs_stmt->bind_param("i", $user_id);
$user_clubs_stmt->execute();
$user_clubs_result = $user_clubs_stmt->get_result();
$user_club_ids = [];
while ($row = $user_clubs_result->fetch_assoc()) {
    $user_club_ids[] = $row['club_id'];
}

// Create a comprehensive notifications query that includes:
// 1. Direct user notifications (from user_notifications table)
// 2. Club announcements for clubs the user is a member of
// 3. System announcements targeted at students or all users
// 4. General announcements that are public

$notifications_query = "
    (
        -- Direct user notifications
        SELECT 
            n.id,
            n.title,
            n.message as content,
            n.type,
            n.created_at,
            n.expires_at,
            c.name as club_name,
            c.initials as club_initials,
            un.is_read,
            un.read_at,
            'user_notification' as source_type,
            u.username as created_by_name
        FROM user_notifications un
        JOIN notifications n ON un.notification_id = n.id
        LEFT JOIN clubs c ON n.club_id = c.id
        LEFT JOIN users u ON n.created_by = u.id
        WHERE un.user_id = ? AND (n.expires_at IS NULL OR n.expires_at > NOW())
    )
    
    UNION ALL
    
    (
        -- Club announcements for user's clubs
        SELECT 
            a.id,
            a.title,
            a.content,
            a.announcement_type as type,
            a.created_at,
            a.expire_date as expires_at,
            c.name as club_name,
            c.initials as club_initials,
            0 as is_read,
            NULL as read_at,
            'club_announcement' as source_type,
            u.username as created_by_name
        FROM announcements a
        JOIN clubs c ON a.club_id = c.id
        LEFT JOIN users u ON a.created_by = u.id
        WHERE a.club_id IN (" . implode(',', array_fill(0, count($user_club_ids), '?')) . ")
        AND a.status = 'published'
        AND a.is_public = 1
        AND (a.expire_date IS NULL OR a.expire_date > NOW())
        AND (a.publish_date IS NULL OR a.publish_date <= NOW())
    )
    
    UNION ALL
    
    (
        -- System announcements for students
        SELECT 
            sa.id,
            sa.title,
            sa.content,
            sa.announcement_type as type,
            sa.created_at,
            sa.expire_date as expires_at,
            NULL as club_name,
            NULL as club_initials,
            0 as is_read,
            NULL as read_at,
            'system_announcement' as source_type,
            u.username as created_by_name
        FROM system_announcements sa
        LEFT JOIN users u ON sa.created_by = u.id
        WHERE sa.status = 'published'
        AND (sa.target_audience = 'all' OR sa.target_audience = 'students')
        AND (sa.expire_date IS NULL OR sa.expire_date > NOW())
        AND (sa.publish_date IS NULL OR sa.publish_date <= NOW())
    )
    
    UNION ALL
    
    (
        -- General public announcements (not club-specific)
        SELECT 
            a.id,
            a.title,
            a.content,
            a.announcement_type as type,
            a.created_at,
            a.expire_date as expires_at,
            NULL as club_name,
            NULL as club_initials,
            0 as is_read,
            NULL as read_at,
            'general_announcement' as source_type,
            u.username as created_by_name
        FROM announcements a
        LEFT JOIN users u ON a.created_by = u.id
        WHERE a.club_id IS NULL
        AND a.status = 'published'
        AND a.is_public = 1
        AND (a.expire_date IS NULL OR a.expire_date > NOW())
        AND (a.publish_date IS NULL OR a.publish_date <= NOW())
    )
";

// Build the final query with filters
$final_query = "SELECT * FROM ($notifications_query) as all_notifications WHERE 1=1";
$params = [$user_id];
$param_types = "i";

// Add user club IDs to params if user has clubs
if (!empty($user_club_ids)) {
    foreach ($user_club_ids as $club_id) {
        $params[] = $club_id;
        $param_types .= "i";
    }
} else {
    // If user has no clubs, modify the query to avoid empty IN clause
    $notifications_query = str_replace(
        "WHERE a.club_id IN (" . implode(',', array_fill(0, count($user_club_ids), '?')) . ")",
        "WHERE 1=0", // No results for club announcements
        $notifications_query
    );
    $final_query = "SELECT * FROM ($notifications_query) as all_notifications WHERE 1=1";
    $params = [$user_id];
    $param_types = "i";
}

// Apply filters
if ($filter_type !== 'all') {
    $final_query .= " AND type = ?";
    $params[] = $filter_type;
    $param_types .= "s";
}

if ($filter_status !== 'all') {
    if ($filter_status === 'read') {
        $final_query .= " AND is_read = 1";
    } else {
        $final_query .= " AND is_read = 0";
    }
}

if ($filter_club > 0) {
    $final_query .= " AND club_name = (SELECT name FROM clubs WHERE id = ?)";
    $params[] = $filter_club;
    $param_types .= "i";
}

// Add ordering and pagination
$final_query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$param_types .= "ii";

// Execute the query
$stmt = $conn->prepare($final_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$notifications_result = $stmt->get_result();

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM ($notifications_query) as all_notifications WHERE 1=1";
$count_params = [$user_id];
$count_param_types = "i";

if (!empty($user_club_ids)) {
    foreach ($user_club_ids as $club_id) {
        $count_params[] = $club_id;
        $count_param_types .= "i";
    }
}

// Apply same filters to count query
if ($filter_type !== 'all') {
    $count_query .= " AND type = ?";
    $count_params[] = $filter_type;
    $count_param_types .= "s";
}

if ($filter_status !== 'all') {
    if ($filter_status === 'read') {
        $count_query .= " AND is_read = 1";
    } else {
        $count_query .= " AND is_read = 0";
    }
}

if ($filter_club > 0) {
    $count_query .= " AND club_name = (SELECT name FROM clubs WHERE id = ?)";
    $count_params[] = $filter_club;
    $count_param_types .= "i";
}

$count_stmt = $conn->prepare($count_query);
if (!empty($count_params)) {
    $count_stmt->bind_param($count_param_types, ...$count_params);
}
$count_stmt->execute();
$total_notifications = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_notifications / $per_page);

// Get user's clubs for filter dropdown
$clubs_stmt = $conn->prepare("
    SELECT c.id, c.name, c.initials 
    FROM clubs c 
    JOIN memberships m ON c.id = m.club_id 
    WHERE m.user_id = ? AND m.status = 'approved'
    ORDER BY c.name ASC
");
$clubs_stmt->bind_param("i", $user_id);
$clubs_stmt->execute();
$user_clubs = $clubs_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get notification statistics
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
        SUM(CASE WHEN type = 'event' THEN 1 ELSE 0 END) as events,
        SUM(CASE WHEN type = 'announcement' OR type = 'general' THEN 1 ELSE 0 END) as announcements,
        SUM(CASE WHEN type = 'reminder' THEN 1 ELSE 0 END) as reminders,
        SUM(CASE WHEN type = 'alert' OR type = 'urgent' THEN 1 ELSE 0 END) as alerts
    FROM ($notifications_query) as all_notifications
";

$stats_params = [$user_id];
$stats_param_types = "i";

if (!empty($user_club_ids)) {
    foreach ($user_club_ids as $club_id) {
        $stats_params[] = $club_id;
        $stats_param_types .= "i";
    }
}

$stats_stmt = $conn->prepare($stats_query);
if (!empty($stats_params)) {
    $stats_stmt->bind_param($stats_param_types, ...$stats_params);
}
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Notifications - Club Management System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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

        .dashboard-content {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .welcome-header {
            background-color: #fff;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            text-align: center;
        }

        .welcome-header h1 {
            color: rgb(209, 120, 25);
            margin: 0 0 10px 0;
            font-size: 28px;
        }

        /* Statistics Section */
        .stats-section {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 15px;
            text-align: center;
        }

        .stat-item {
            background-color: rgb(237, 222, 203);
            padding: 20px;
            border-radius: 8px;
            transition: transform 0.2s ease;
            border-left: 4px solid rgb(209, 120, 25);
        }

        .stat-item:hover {
            transform: translateY(-2px);
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: rgb(209, 120, 25);
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            font-weight: bold;
        }

        /* Filters Section */
        .filters-section {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: bold;
            color: rgb(209, 120, 25);
            margin-bottom: 8px;
        }

        .filter-group select {
            padding: 10px;
            border: 2px solid rgb(237, 222, 203);
            border-radius: 5px;
            background-color: #fff;
            font-family: 'Times New Roman', Times, serif;
            color: #333;
        }

        .filter-group select:focus {
            outline: none;
            border-color: rgb(209, 120, 25);
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            background-color: rgb(209, 120, 25);
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-family: 'Times New Roman', Times, serif;
            font-size: 14px;
            transition: background-color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover {
            background-color: rgb(150, 85, 10);
        }

        .btn-outline {
            background-color: transparent;
            color: rgb(209, 120, 25);
            border: 2px solid rgb(209, 120, 25);
        }

        .btn-outline:hover {
            background-color: rgb(209, 120, 25);
            color: white;
        }

        /* Notifications Section */
        .notifications-section {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .section-title {
            color: rgb(209, 120, 25);
            font-size: 24px;
            margin-bottom: 20px;
            border-bottom: 2px solid rgb(209, 120, 25);
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .notification-item {
            background: rgb(237, 222, 203);
            border-radius: 8px;
            padding: 20px;
            border-left: 4px solid rgb(209, 120, 25);
            transition: all 0.3s ease;
            position: relative;
        }

        .notification-item.unread {
            border-left-color: #dc3545;
            background: rgb(255, 248, 240);
        }

        .notification-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .notification-content {
            flex: 1;
        }

        .notification-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .notification-title {
            font-weight: bold;
            color: rgb(209, 120, 25);
            font-size: 18px;
            margin-bottom: 8px;
            flex: 1;
        }

        .notification-badges {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
        }

        .notification-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }

        .badge-new {
            background-color: #dc3545;
            animation: pulse 2s infinite;
        }

        .badge-type {
            background-color: rgb(209, 120, 25);
        }

        .badge-club {
            background-color: #17a2b8;
        }

        .badge-source {
            background-color: #6c757d;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }

        .notification-message {
            color: #333;
            font-size: 16px;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .notification-meta {
            display: flex;
            gap: 20px;
            font-size: 14px;
            color: #666;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .no-notifications {
            text-align: center;
            color: #666;
            padding: 60px 20px;
            font-style: italic;
        }

        .no-notifications i {
            font-size: 48px;
            margin-bottom: 20px;
            color: #ccc;
        }

        .no-notifications h3 {
            color: rgb(209, 120, 25);
            margin-bottom: 10px;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
        }

        .pagination a {
            background-color: rgb(237, 222, 203);
            color: rgb(209, 120, 25);
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 5px;
            border: 2px solid rgb(209, 120, 25);
            transition: all 0.3s ease;
        }

        .pagination a:hover,
        .pagination a.active {
            background-color: rgb(209, 120, 25);
            color: white;
        }

        .pagination .disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .stats-row {
                grid-template-columns: repeat(3, 1fr);
            }

            .filters-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .filter-buttons {
                justify-content: center;
            }

            .notification-meta {
                flex-direction: column;
                gap: 8px;
            }

            .notification-badges {
                flex-wrap: wrap;
            }
        }

        @media (max-width: 480px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }

            .dashboard-content {
                padding: 15px;
            }

            .notification-item {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="logo">
            <i class="fas fa-bell"></i> All Notifications
        </div>
        <div>
            <a href="dashboard.php">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <div class="dashboard-content">
        <div class="welcome-header">
            <h1><i class="fas fa-bell"></i> Notification Center</h1>
            <p class="welcome-subtitle">Stay updated with all your notifications and announcements</p>
        </div>

        <!-- Statistics Section -->
        <div class="stats-section">
            <div class="stats-row">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" style="color: #dc3545;"><?php echo $stats['unread']; ?></div>
                    <div class="stat-label">Unread</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" style="color: #28a745;"><?php echo $stats['events']; ?></div>
                    <div class="stat-label">Events</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" style="color: #17a2b8;"><?php echo $stats['announcements']; ?></div>
                    <div class="stat-label">Announcements</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" style="color: #ffc107;"><?php echo $stats['reminders']; ?></div>
                    <div class="stat-label">Reminders</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" style="color: #6c757d;"><?php echo $stats['alerts']; ?></div>
                    <div class="stat-label">Alerts</div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="type">Filter by Type</label>
                        <select name="type" id="type">
                            <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <option value="announcement" <?php echo $filter_type === 'announcement' ? 'selected' : ''; ?>>Announcements</option>
                            <option value="event" <?php echo $filter_type === 'event' ? 'selected' : ''; ?>>Events</option>
                            <option value="reminder" <?php echo $filter_type === 'reminder' ? 'selected' : ''; ?>>Reminders</option>
                            <option value="alert" <?php echo $filter_type === 'alert' ? 'selected' : ''; ?>>Alerts</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="status">Filter by Status</label>
                        <select name="status" id="status">
                            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="unread" <?php echo $filter_status === 'unread' ? 'selected' : ''; ?>>Unread</option>
                            <option value="read" <?php echo $filter_status === 'read' ? 'selected' : ''; ?>>Read</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="club">Filter by Club</label>
                        <select name="club" id="club">
                            <option value="0">All Clubs</option>
                            <?php foreach ($user_clubs as $club): ?>
                                <option value="<?php echo $club['id']; ?>" <?php echo $filter_club == $club['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($club['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <div class="filter-buttons">
                            <button type="submit" class="btn">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="?" class="btn btn-outline">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Notifications Section -->
        <div class="notifications-section">
            <h3 class="section-title">
                <i class="fas fa-inbox"></i> Your Notifications
                <?php if ($stats['unread'] > 0): ?>
                    <span class="badge-new unread-badge"><?php echo $stats['unread']; ?></span>
                <?php endif; ?>
            </h3>

            <div class="notifications-list">
                <?php if ($notifications_result->num_rows > 0): ?>
                    <?php while ($notification = $notifications_result->fetch_assoc()): ?>
                        <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                            <div class="notification-content">
                                <div class="notification-title">
                                    <?php echo htmlspecialchars($notification['title']); ?>
                                </div>
                                
                                <div class="notification-badges">
                                    <?php if (!$notification['is_read']): ?>
                                        <span class="notification-badge badge-new">NEW</span>
                                    <?php endif; ?>
                                    <span class="notification-badge badge-type">
                                        <?php echo ucfirst($notification['type']); ?>
                                    </span>
                                    <?php if ($notification['club_name']): ?>
                                        <span class="notification-badge badge-club">
                                            <i class="fas fa-users"></i> <?php echo htmlspecialchars($notification['club_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="notification-badge badge-source">
                                        <?php echo ucfirst(str_replace('_', ' ', $notification['source_type'])); ?>
                                    </span>
                                </div>
                                
                                <div class="notification-message">
                                    <?php echo nl2br(htmlspecialchars($notification['content'])); ?>
                                </div>
                                
                                <div class="notification-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                    </div>
                                    <?php if ($notification['created_by_name']): ?>
                                        <div class="meta-item">
                                            <i class="fas fa-user"></i>
                                            <?php echo htmlspecialchars($notification['created_by_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($notification['expires_at']): ?>
                                        <div class="meta-item">
                                            <i class="fas fa-calendar-times"></i>
                                            Expires: <?php echo date('M j, Y', strtotime($notification['expires_at'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-notifications">
                        <i class="fas fa-bell-slash"></i>
                        <h3>No notifications found</h3>
                        <p>You don't have any notifications matching your current filters.</p>
                        <a href="?" class="btn">Reset Filters</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page-1 ?><?= $filter_type !== 'all' ? '&type='.$filter_type : '' ?><?= $filter_status !== 'all' ? '&status='.$filter_status : '' ?><?= $filter_club > 0 ? '&club='.$filter_club : '' ?>">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-left"></i> Previous</span>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($page + 2, $total_pages); $i++): ?>
                        <a href="?page=<?= $i ?><?= $filter_type !== 'all' ? '&type='.$filter_type : '' ?><?= $filter_status !== 'all' ? '&status='.$filter_status : '' ?><?= $filter_club > 0 ? '&club='.$filter_club : '' ?>" 
                           class="<?= $i == $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page+1 ?><?= $filter_type !== 'all' ? '&type='.$filter_type : '' ?><?= $filter_status !== 'all' ? '&status='.$filter_status : '' ?><?= $filter_club > 0 ? '&club='.$filter_club : '' ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled">Next <i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Add click handler to mark notifications as read
        document.querySelectorAll('.notification-item.unread').forEach(item => {
            item.addEventListener('click', function() {
                this.classList.remove('unread');
                
                // Find and remove the NEW badge
                const badge = this.querySelector('.badge-new');
                if (badge) {
                    badge.remove();
                }
                
                // Update unread count in stats
                const unreadCount = document.querySelector('.stat-number[style*="color: #dc3545"]');
                if (unreadCount) {
                    const count = parseInt(unreadCount.textContent);
                    if (count > 1) {
                        unreadCount.textContent = count - 1;
                    } else {
                        unreadCount.textContent = '0';
                        document.querySelector('.unread-badge').remove();
                    }
                }
            });
        });
    </script>
</body>
</html>