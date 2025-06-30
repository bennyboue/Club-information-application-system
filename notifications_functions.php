<?php
// notifications_functions.php - Functions for displaying notifications

// Function to get notifications for a specific user
function getUserNotifications($mysqli, $user_id, $limit = 10, $unread_only = false) {
    $unread_condition = $unread_only ? "AND un.is_read = 0" : "";
    
    $query = "SELECT 
                n.id,
                n.title,
                n.message,
                n.type,
                n.created_at,
                n.expires_at,
                un.is_read,
                un.read_at,
                c.name as club_name,
                u.name as created_by_name
              FROM notifications n
              JOIN user_notifications un ON n.id = un.notification_id
              LEFT JOIN clubs c ON n.club_id = c.id
              LEFT JOIN users u ON n.created_by = u.id
              WHERE un.user_id = ? 
                AND n.is_active = 1 
                AND (n.expires_at IS NULL OR n.expires_at > NOW())
                $unread_condition
              ORDER BY n.created_at DESC
              LIMIT ?";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Function to mark notification as read
function markNotificationAsRead($mysqli, $user_id, $notification_id) {
    $query = "UPDATE user_notifications 
              SET is_read = 1, read_at = NOW() 
              WHERE user_id = ? AND notification_id = ?";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("ii", $user_id, $notification_id);
    return $stmt->execute();
}

// Function to get unread notification count
function getUnreadNotificationCount($mysqli, $user_id) {
    $query = "SELECT COUNT(*) as count 
              FROM user_notifications un
              JOIN notifications n ON un.notification_id = n.id
              WHERE un.user_id = ? 
                AND un.is_read = 0 
                AND n.is_active = 1 
                AND (n.expires_at IS NULL OR n.expires_at > NOW())";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    $result = $stmt->get_result()->fetch_assoc();
    return $result['count'];
}

// Function to display notifications in dashboard
function displayNotifications($mysqli, $user_id, $dashboard_type = 'student') {
    $notifications = getUserNotifications($mysqli, $user_id, 5);
    $unread_count = getUnreadNotificationCount($mysqli, $user_id);
    
    echo "<div class='notifications-section'>";
    echo "<div class='notifications-header'>";
    echo "<h3>Notifications";
    if ($unread_count > 0) {
        echo " <span class='unread-badge'>$unread_count</span>";
    }
    echo "</h3>";
    echo "<a href='notifications.php' class='view-all-link'>View All</a>";
    echo "</div>";
    
    if (empty($notifications)) {
        echo "<div class='no-notifications'>No notifications at this time.</div>";
    } else {
        echo "<div class='notifications-list'>";
        foreach ($notifications as $notification) {
            $read_class = $notification['is_read'] ? 'read' : 'unread';
            $time_ago = timeAgo($notification['created_at']);
            
            echo "<div class='notification-item $read_class' data-notification-id='{$notification['id']}' onclick='markAsRead({$notification['id']})'>";
            
            echo "<div class='notification-content'>";
            echo "<div class='notification-title'>{$notification['title']}</div>";
            echo "<div class='notification-message'>" . substr($notification['message'], 0, 100) . "...</div>";
            echo "<div class='notification-meta'>";
            
            if ($notification['club_name']) {
                echo "<span class='club-tag'>📍 {$notification['club_name']}</span>";
            }
            
            echo "<span class='time-ago'>🕒 $time_ago</span>";
            echo "</div>";
            echo "</div>";
            
            if (!$notification['is_read']) {
                echo "<div class='unread-indicator'></div>";
            }
            
            echo "</div>";
        }
        echo "</div>";
    }
    
    echo "</div>";
}

// Helper function to convert timestamp to "time ago" format
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    
    return floor($time/31536000) . ' years ago';
}
?>

<!-- Include this in your student_dashboard.php -->
<div class="dashboard-section">
    <?php displayNotifications($mysqli, $_SESSION['user_id'], 'student'); ?>
</div>

<!-- Include this in your club_manager_dashboard.php -->
<div class="dashboard-section">
    <?php displayNotifications($mysqli, $_SESSION['user_id'], 'club_manager'); ?>
</div>

<!-- JavaScript for handling notification interactions -->
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
            
            // Update unread count
            const badge = document.querySelector('.unread-badge');
            if (badge) {
                const currentCount = parseInt(badge.textContent);
                const newCount = currentCount - 1;
                if (newCount > 0) {
                    badge.textContent = newCount;
                } else {
                    badge.style.display = 'none';
                }
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

// Auto-refresh notifications every 30 seconds
setInterval(function() {
    if (document.hasFocus()) {
        location.reload();
    }
}, 30000);
</script>

<style>
.notifications-section {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.notifications-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.notifications-header h3 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.unread-badge {
    background: #dc3545;
    color: white;
    border-radius: 50%;
    padding: 2px 8px;
    font-size: 12px;
    font-weight: bold;
}

.notifications-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.notification-item {
    background: white;
    border-radius: 6px;
    padding: 15px;
    border-left: 4px solid #dee2e6;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-item.unread {
    border-left-color: #007bff;
    background: #f8f9ff;
}

.notification-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transform: translateY(-1px);
}

.notification-content {
    flex: 1;
}

.notification-title {
    font-weight: 600;
    color: #333;
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
    background: #e9ecef;
    padding: 2px 6px;
    border-radius: 4px;
}

.unread-indicator {
    width: 8px;
    height: 8px;
    background: #007bff;
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
    color: #007bff;
    text-decoration: none;
    font-size: 14px;
}

.view-all-link:hover {
    text-decoration: underline;
}
</style>