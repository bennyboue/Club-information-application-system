<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "ics_project");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Include notifications functions
require_once 'notifications_functions.php';

// Fetch user's clubs and store results in array
$clubs_stmt = $conn->prepare("
    SELECT c.id, c.name, c.initials, c.description, m.joined_at 
    FROM clubs c 
    JOIN memberships m ON c.id = m.club_id 
    WHERE m.user_id = ? 
    ORDER BY m.joined_at DESC
");
$clubs_stmt->bind_param("i", $user_id);
$clubs_stmt->execute();
$clubs_result = $clubs_stmt->get_result();

// Store clubs data in array
$clubs_data = [];
while($club = $clubs_result->fetch_assoc()) {
    $clubs_data[] = $club;
}
$clubs_stmt->close();

// Fetch upcoming events from user's clubs and store results in array
$events_stmt = $conn->prepare("
    SELECT e.id, e.title, e.description, e.event_date, c.name as club_name, c.id as club_id
    FROM events e 
    JOIN clubs c ON e.club_id = c.id 
    JOIN memberships m ON c.id = m.club_id 
    WHERE m.user_id = ? AND (e.event_date >= CURDATE() OR e.event_date IS NULL)
    ORDER BY e.event_date ASC, e.created_at DESC
    LIMIT 10
");
$events_stmt->bind_param("i", $user_id);
$events_stmt->execute();
$events_result = $events_stmt->get_result();

// Store events data in array
$events_data = [];
while($event = $events_result->fetch_assoc()) {
    $events_data[] = $event;
}
$events_stmt->close();

// Get user profile info
$profile_stmt = $conn->prepare("SELECT username, surname, email, school_id, role FROM users WHERE id = ?");
$profile_stmt->bind_param("i", $user_id);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();
$user_profile = $profile_result->fetch_assoc();
$profile_stmt->close();

// Get notifications data
$notifications = getUserNotifications($conn, $user_id, 5);
$unread_count = getUnreadNotificationCount($conn, $user_id);

// Store counts for stats
$clubs_count = count($clubs_data);
$events_count = count($events_data);

// NOW close the connection after all data is fetched
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Dashboard - School Club Management</title>
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

        .welcome-subtitle {
            color: #666;
            font-size: 16px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .dashboard-section {
            background-color: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .section-title {
            color: rgb(209, 120, 25);
            font-size: 20px;
            margin-bottom: 15px;
            border-bottom: 2px solid rgb(209, 120, 25);
            padding-bottom: 5px;
        }

        .club-item {
            background-color: rgb(237, 222, 203);
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            border-left: 4px solid rgb(209, 120, 25);
            transition: transform 0.2s ease;
        }

        .club-item:hover {
            transform: translateX(5px);
        }

        .club-name {
            font-weight: bold;
            color: rgb(209, 120, 25);
            margin-bottom: 5px;
        }

        .club-initials {
            background-color: rgb(209, 120, 25);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-right: 10px;
        }

        .club-joined {
            color: #666;
            font-size: 12px;
        }

        .event-item {
            background-color: rgb(237, 222, 203);
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            border-left: 4px solid rgb(209, 120, 25);
        }

        .event-title {
            font-weight: bold;
            color: rgb(209, 120, 25);
            margin-bottom: 5px;
        }

        .event-club {
            color: #666;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .event-date {
            color: #666;
            font-size: 14px;
            font-weight: bold;
        }

        .no-content {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 20px;
        }

        .profile-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .profile-field {
            padding: 10px;
            background-color: rgb(237, 222, 203);
            border-radius: 5px;
        }

        .profile-label {
            font-weight: bold;
            color: rgb(209, 120, 25);
            display: block;
            margin-bottom: 5px;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            text-align: center;
            margin-top: 20px;
        }

        .stat-item {
            background-color: rgb(237, 222, 203);
            padding: 15px;
            border-radius: 8px;
            transition: transform 0.2s ease;
        }

        .stat-item:hover {
            transform: translateY(-2px);
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: rgb(209, 120, 25);
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .btn {
            background-color: rgb(209, 120, 25);
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin-top: 10px;
            transition: background-color 0.3s ease;
        }

        .btn:hover {
            background-color: rgb(150, 85, 10);
        }

        /* Notifications Styles */
        .notifications-section {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .notifications-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .notifications-header h3 {
            color: rgb(209, 120, 25);
            font-size: 20px;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid rgb(209, 120, 25);
            padding-bottom: 5px;
        }

        .unread-badge {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 8px;
            font-size: 12px;
            font-weight: bold;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }

        .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .notification-item {
            background: rgb(237, 222, 203);
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid rgb(209, 120, 25);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-item.unread {
            border-left-color: #dc3545;
            background: rgb(255, 248, 240);
        }

        .notification-item:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            color: rgb(209, 120, 25);
            margin-bottom: 5px;
        }

        .notification-message {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .notification-meta {
            display: flex;
            gap: 15px;
            font-size: 12px;
            color: #888;
        }

        .club-tag {
            background: rgb(209, 120, 25);
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .unread-indicator {
            width: 8px;
            height: 8px;
            background: #dc3545;
            border-radius: 50%;
            margin-left: 10px;
        }

        .no-notifications {
            text-align: center;
            color: #666;
            padding: 20px;
            font-style: italic;
        }

        .view-all-link {
            color: rgb(209, 120, 25);
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
        }

        .view-all-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .profile-info {
                grid-template-columns: 1fr;
            }

            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }

            .notification-meta {
                flex-direction: column;
                gap: 5px;
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
        <a href="home.php">Browse Clubs</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="dashboard-content">
    <div class="welcome-header">
        <h1>Welcome back, <?php echo htmlspecialchars($username); ?>!</h1>
        <p class="welcome-subtitle">Here's your club management dashboard</p>
        
        <div class="stats-row">
            <div class="stat-item">
                <div class="stat-number"><?php echo $clubs_count; ?></div>
                <div class="stat-label">Clubs Joined</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $events_count; ?></div>
                <div class="stat-label">Upcoming Events</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $unread_count; ?></div>
                <div class="stat-label">New Notifications</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo ucfirst($user_profile['role']); ?></div>
                <div class="stat-label">Account Type</div>
            </div>
        </div>
    </div>

    <!-- Notifications Section -->
    <div class="notifications-section">
        <div class="notifications-header">
            <h3>Notifications
                <?php if ($unread_count > 0): ?>
                    <span class="unread-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </h3>
            <a href="notifications.php" class="view-all-link">View All</a>
        </div>
        
        <?php if (empty($notifications)): ?>
            <div class="no-notifications">
                📭 No notifications at this time.
            </div>
        <?php else: ?>
            <div class="notifications-list">
                <?php foreach ($notifications as $notification): ?>
                    <?php 
                    $read_class = $notification['is_read'] ? 'read' : 'unread';
                    $time_ago = timeAgo($notification['created_at']);
                    ?>
                    <div class="notification-item <?php echo $read_class; ?>" 
                         data-notification-id="<?php echo $notification['id']; ?>" 
                         onclick="markAsRead(<?php echo $notification['id']; ?>)">
                        
                        <div class="notification-content">
                            <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                            <div class="notification-message">
                                <?php echo htmlspecialchars(substr($notification['message'], 0, 100)); ?>...
                            </div>
                            <div class="notification-meta">
                                <?php if ($notification['club_name']): ?>
                                    <span class="club-tag">📍 <?php echo htmlspecialchars($notification['club_name']); ?></span>
                                <?php endif; ?>
                                <span class="time-ago">🕒 <?php echo $time_ago; ?></span>
                            </div>
                        </div>
                        
                        <?php if (!$notification['is_read']): ?>
                            <div class="unread-indicator"></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="dashboard-grid">
        <div class="dashboard-section">
            <h3 class="section-title">My Clubs</h3>
            <?php if ($clubs_count > 0): ?>
                <?php foreach($clubs_data as $club): ?>
                    <div class="club-item">
                        <div class="club-name">
                            <span class="club-initials"><?php echo htmlspecialchars($club['initials']); ?></span>
                            <?php echo htmlspecialchars($club['name']); ?>
                        </div>
                        <div style="margin-bottom: 8px; color: #333;">
                            <?php echo htmlspecialchars($club['description']); ?>
                        </div>
                        <div class="club-joined">
                            Joined: <?php echo date('M j, Y', strtotime($club['joined_at'])); ?>
                        </div>
                        <a href="club_details.php?id=<?php echo $club['id']; ?>" class="btn">View Details</a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-content">
                    You haven't joined any clubs yet.
                    <br><br>
                    <a href="home.php" class="btn">Browse Available Clubs</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="dashboard-section">
            <h3 class="section-title">Upcoming Events</h3>
            <?php if ($events_count > 0): ?>
                <?php foreach($events_data as $event): ?>
                    <div class="event-item">
                        <div class="event-title"><?php echo htmlspecialchars($event['title']); ?></div>
                        <div class="event-club">
                            From: <?php echo htmlspecialchars($event['club_name']); ?>
                        </div>
                        <?php if (!empty($event['description'])): ?>
                            <div style="margin-bottom: 8px; color: #333; font-size: 14px;">
                                <?php echo htmlspecialchars($event['description']); ?>
                            </div>
                        <?php endif; ?>
                        <div class="event-date">
                            <?php 
                            if ($event['event_date']) {
                                echo date('M j, Y', strtotime($event['event_date']));
                            } else {
                                echo "Date TBD";
                            }
                            ?>
                        </div>
                        <a href="club_details.php?id=<?php echo $event['club_id']; ?>" class="btn">View Club</a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-content">
                    No upcoming events from your clubs.
                    <br><br>
                    <a href="home.php" class="btn">Join More Clubs</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Profile Information Section -->
    <div class="dashboard-section">
        <h3 class="section-title">Profile Information</h3>
        <div class="profile-info">
            <div class="profile-field">
                <span class="profile-label">Username:</span>
                <?php echo htmlspecialchars($user_profile['username']); ?>
            </div>
            <div class="profile-field">
                <span class="profile-label">Surname:</span>
                <?php echo htmlspecialchars($user_profile['surname'] ?? 'Not provided'); ?>
            </div>
            <div class="profile-field">
                <span class="profile-label">Email:</span>
                <?php echo htmlspecialchars($user_profile['email']); ?>
            </div>
            <div class="profile-field">
                <span class="profile-label">School ID:</span>
                <?php echo htmlspecialchars($user_profile['school_id'] ?? 'Not provided'); ?>
            </div>
        </div>
        <div style="text-align: center; margin-top: 20px;">
            <a href="profile.php" class="btn">Edit Profile</a>
        </div>
    </div>
</div>

<script>
function markAsRead(notificationId) {
    fetch('mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            notification_id: notificationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI to show as read
            const notificationElement = document.querySelector(`[data-notification-id="${notificationId}"]`);
            notificationElement.classList.remove('unread');
            notificationElement.classList.add('read');
            
            // Remove unread indicator
            const indicator = notificationElement.querySelector('.unread-indicator');
            if (indicator) {
                indicator.remove();
            }
            
            // Update unread count in badge and stats
            const badge = document.querySelector('.unread-badge');
            const statNumber = document.querySelector('.stats-row .stat-item:nth-child(3) .stat-number');
            
            if (badge && statNumber) {
                const currentCount = parseInt(badge.textContent);
                const newCount = currentCount - 1;
                
                if (newCount > 0) {
                    badge.textContent = newCount;
                    statNumber.textContent = newCount;
                } else {
                    badge.style.display = 'none';
                    statNumber.textContent = '0';
                }
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

// Helper function for time ago (if not included in notifications_functions.php)
<?php if (!function_exists('timeAgo')): ?>
function timeAgo(datetime) {
    const now = new Date();
    const time = new Date(datetime);
    const diffInSeconds = Math.floor((now - time) / 1000);
    
    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return Math.floor(diffInSeconds/60) + ' minutes ago';
    if (diffInSeconds < 86400) return Math.floor(diffInSeconds/3600) + ' hours ago';
    if (diffInSeconds < 2592000) return Math.floor(diffInSeconds/86400) + ' days ago';
    if (diffInSeconds < 31536000) return Math.floor(diffInSeconds/2592000) + ' months ago';
    
    return Math.floor(diffInSeconds/31536000) + ' years ago';
}
<?php endif; ?>

// Auto-refresh notifications every 2 minutes
setInterval(function() {
    if (document.hasFocus()) {
        // Only refresh the notifications section
        fetch('get_notifications_ajax.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update notification count in stats
                const statNumber = document.querySelector('.stats-row .stat-item:nth-child(3) .stat-number');
                if (statNumber) {
                    statNumber.textContent = data.unread_count;
                }
                
                // Update badge
                const badge = document.querySelector('.unread-badge');
                if (data.unread_count > 0) {
                    if (badge) {
                        badge.textContent = data.unread_count;
                        badge.style.display = 'inline-block';
                    }
                } else if (badge) {
                    badge.style.display = 'none';
                }
            }
        })
        .catch(error => console.error('Error refreshing notifications:', error));
    }
}, 120000); // 2 minutes
</script>

</body>
</html>