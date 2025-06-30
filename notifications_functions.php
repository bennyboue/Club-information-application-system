<?php
// notifications_functions.php - Functions for notifications system

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
                u.username as created_by_name
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
    if (!$stmt) {
        error_log("MySQL prepare error: " . $mysqli->error);
        return [];
    }
    
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Function to mark notification as read
function markNotificationAsRead($mysqli, $user_id, $notification_id) {
    $query = "UPDATE user_notifications 
              SET is_read = 1, read_at = NOW() 
              WHERE user_id = ? AND notification_id = ?";
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        error_log("MySQL prepare error: " . $mysqli->error);
        return false;
    }
    
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
    if (!$stmt) {
        error_log("MySQL prepare error: " . $mysqli->error);
        return 0;
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    if (!$result) {
        return 0;
    }
    
    $row = $result->fetch_assoc();
    return $row ? (int)$row['count'] : 0;
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

// Function to create notification when announcement is posted
function createNotificationForAnnouncement($mysqli, $title, $content, $club_id, $admin_id, $target_audience = 'all') {
    try {
        // Start transaction
        $mysqli->begin_transaction();
        
        // Insert into notifications table
        $notification_query = "INSERT INTO notifications (title, message, type, target_audience, club_id, created_by) VALUES (?, ?, 'announcement', ?, ?, ?)";
        $notification_stmt = $mysqli->prepare($notification_query);
        
        if (!$notification_stmt) {
            throw new Exception("Prepare failed: " . $mysqli->error);
        }
        
        $notification_stmt->bind_param("sssii", $title, $content, $target_audience, $club_id, $admin_id);
        $notification_stmt->execute();
        
        $notification_id = $mysqli->insert_id;
        
        // Create notification entries for all relevant users
        createUserNotifications($mysqli, $notification_id, $target_audience, $club_id);
        
        // Commit transaction
        $mysqli->commit();
        
        return $notification_id;
        
    } catch (Exception $e) {
        $mysqli->rollback();
        throw $e;
    }
}

// Function to create individual user notification entries
function createUserNotifications($mysqli, $notification_id, $target_audience, $club_id) {
    $users_query = "";
    $bind_params = [];
    $bind_types = "";
    
    switch ($target_audience) {
        case 'all':
            // All club members
            $users_query = "SELECT user_id FROM memberships WHERE club_id = ?";
            $bind_params = [$club_id];
            $bind_types = "i";
            break;
            
        case 'students':
            // Only student members
            $users_query = "SELECT m.user_id FROM memberships m 
                           JOIN users u ON m.user_id = u.id 
                           WHERE m.club_id = ? AND u.role = 'student'";
            $bind_params = [$club_id];
            $bind_types = "i";
            break;
            
        case 'admins':
            // Only admin members
            $users_query = "SELECT m.user_id FROM memberships m 
                           JOIN users u ON m.user_id = u.id 
                           WHERE m.club_id = ? AND u.role = 'admin'";
            $bind_params = [$club_id];
            $bind_types = "i";
            break;
            
        default:
            throw new Exception("Invalid target audience: " . $target_audience);
    }
    
    // Get target users
    $stmt = $mysqli->prepare($users_query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }
    
    if (!empty($bind_params)) {
        $stmt->bind_param($bind_types, ...$bind_params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Insert notification for each user
    $insert_query = "INSERT INTO user_notifications (notification_id, user_id) VALUES (?, ?)";
    $insert_stmt = $mysqli->prepare($insert_query);
    
    if (!$insert_stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }
    
    while ($user = $result->fetch_assoc()) {
        $insert_stmt->bind_param("ii", $notification_id, $user['user_id']);
        $insert_stmt->execute();
    }
}

// Function to create event notification
function createNotificationForEvent($mysqli, $event_title, $event_description, $event_date, $club_id, $admin_id) {
    $title = "New Event: " . $event_title;
    $message = "A new event has been scheduled";
    if ($event_date) {
        $message .= " for " . date('F j, Y', strtotime($event_date));
    }
    if ($event_description) {
        $message .= ". " . substr($event_description, 0, 100) . "...";
    }
    
    return createNotificationForAnnouncement($mysqli, $title, $message, $club_id, $admin_id, 'all');
}

// Function to create membership notification
function createNotificationForMembership($mysqli, $user_id, $club_id, $action) {
    try {
        $mysqli->begin_transaction();
        
        // Get club and user info
        $club_query = "SELECT name FROM clubs WHERE id = ?";
        $club_stmt = $mysqli->prepare($club_query);
        $club_stmt->bind_param("i", $club_id);
        $club_stmt->execute();
        $club_result = $club_stmt->get_result();
        $club = $club_result->fetch_assoc();
        
        $user_query = "SELECT username FROM users WHERE id = ?";
        $user_stmt = $mysqli->prepare($user_query);
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user = $user_result->fetch_assoc();
        
        if (!$club || !$user) {
            throw new Exception("Club or user not found");
        }
        
        $title = "";
        $message = "";
        
        switch ($action) {
            case 'joined':
                $title = "Welcome to " . $club['name'] . "!";
                $message = "You have successfully joined " . $club['name'] . ". Welcome to the club!";
                break;
            case 'left':
                $title = "Left " . $club['name'];
                $message = "You have left " . $club['name'] . ". Thank you for being part of our community.";
                break;
            default:
                throw new Exception("Invalid action: " . $action);
        }
        
        // Insert notification
        $notification_query = "INSERT INTO notifications (title, message, type, target_audience, club_id, created_by) VALUES (?, ?, 'membership', 'individual', ?, 1)";
        $notification_stmt = $mysqli->prepare($notification_query);
        $notification_stmt->bind_param("ssi", $title, $message, $club_id);
        $notification_stmt->execute();
        
        $notification_id = $mysqli->insert_id;
        
        // Create user notification
        $user_notification_query = "INSERT INTO user_notifications (notification_id, user_id) VALUES (?, ?)";
        $user_notification_stmt = $mysqli->prepare($user_notification_query);
        $user_notification_stmt->bind_param("ii", $notification_id, $user_id);
        $user_notification_stmt->execute();
        
        $mysqli->commit();
        return $notification_id;
        
    } catch (Exception $e) {
        $mysqli->rollback();
        throw $e;
    }
}

// Function to mark all notifications as read for a user
function markAllNotificationsAsRead($mysqli, $user_id) {
    $query = "UPDATE user_notifications un
              JOIN notifications n ON un.notification_id = n.id
              SET un.is_read = 1, un.read_at = NOW() 
              WHERE un.user_id = ? 
                AND un.is_read = 0 
                AND n.is_active = 1 
                AND (n.expires_at IS NULL OR n.expires_at > NOW())";
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        error_log("MySQL prepare error: " . $mysqli->error);
        return false;
    }
    
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}

// Function to delete expired notifications
function cleanupExpiredNotifications($mysqli) {
    $query = "UPDATE notifications 
              SET is_active = 0 
              WHERE expires_at IS NOT NULL 
                AND expires_at < NOW() 
                AND is_active = 1";
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        error_log("MySQL prepare error: " . $mysqli->error);
        return false;
    }
    
    return $stmt->execute();
}

// Function to get notifications with pagination
function getNotificationsWithPagination($mysqli, $user_id, $page = 1, $per_page = 20, $unread_only = false) {
    $offset = ($page - 1) * $per_page;
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
                u.username as created_by_name
              FROM notifications n
              JOIN user_notifications un ON n.id = un.notification_id
              LEFT JOIN clubs c ON n.club_id = c.id
              LEFT JOIN users u ON n.created_by = u.id
              WHERE un.user_id = ? 
                AND n.is_active = 1 
                AND (n.expires_at IS NULL OR n.expires_at > NOW())
                $unread_condition
              ORDER BY n.created_at DESC
              LIMIT ? OFFSET ?";
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        error_log("MySQL prepare error: " . $mysqli->error);
        return [];
    }
    
    $stmt->bind_param("iii", $user_id, $per_page, $offset);
    $stmt->execute();
    
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Function to get total notification count for pagination
function getTotalNotificationCount($mysqli, $user_id, $unread_only = false) {
    $unread_condition = $unread_only ? "AND un.is_read = 0" : "";
    
    $query = "SELECT COUNT(*) as count 
              FROM user_notifications un
              JOIN notifications n ON un.notification_id = n.id
              WHERE un.user_id = ? 
                AND n.is_active = 1 
                AND (n.expires_at IS NULL OR n.expires_at > NOW())
                $unread_condition";
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        error_log("MySQL prepare error: " . $mysqli->error);
        return 0;
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    if (!$result) {
        return 0;
    }
    
    $row = $result->fetch_assoc();
    return $row ? (int)$row['count'] : 0;
}
?>