<?php
require __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

session_start();

// Verify system admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$mysqli = new mysqli("localhost", "root", "", "ics_project");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// =================================================================
// ENHANCED NOTIFICATION FUNCTIONS (UPDATED TO MATCH YOUR SCHEMA)
// =================================================================
function createNotificationForAnnouncement($mysqli, $title, $content, $club_id, $admin_id, $target_audience = 'all', $priority = 'normal') {
    try {
        // Validate input parameters
        if (empty($title) || empty($content)) {
            throw new Exception("Title and content are required");
        }
        
        // Start transaction
        $mysqli->begin_transaction();
        
        // Insert into notifications table (matches your schema)
        $notification_query = "INSERT INTO notifications (
            title, 
            message, 
            type, 
            priority, 
            target_audience, 
            club_id, 
            created_by, 
            created_at, 
            expires_at,
            is_immediate,
            is_active
        ) VALUES (?, ?, 'announcement', ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 0, 1)";
        
        $notification_stmt = $mysqli->prepare($notification_query);
        
        if (!$notification_stmt) {
            throw new Exception("Failed to prepare notification statement: " . $mysqli->error);
        }
        
        $notification_stmt->bind_param("ssssii", $title, $content, $priority, $target_audience, $club_id, $admin_id);
        
        if (!$notification_stmt->execute()) {
            throw new Exception("Failed to insert notification: " . $notification_stmt->error);
        }
        
        $notification_id = $mysqli->insert_id;
        
        // Insert the announcement (matches your announcements table schema)
        $announcement_query = "INSERT INTO announcements (
            title, 
            content, 
            club_id, 
            notification_id, 
            created_by, 
            created_at,
            announcement_type,
            priority,
            status,
            is_public
        ) VALUES (?, ?, ?, ?, ?, NOW(), 'general', ?, 'published', 1)";
        
        $announcement_stmt = $mysqli->prepare($announcement_query);
        
        if (!$announcement_stmt) {
            throw new Exception("Failed to prepare announcement statement: " . $mysqli->error);
        }
        
        $announcement_stmt->bind_param("ssiis", $title, $content, $club_id, $notification_id, $admin_id, $priority);
        
        if (!$announcement_stmt->execute()) {
            throw new Exception("Failed to insert announcement: " . $announcement_stmt->error);
        }
        
        // Create notification entries for all relevant users
        $affected_users = createUserNotifications($mysqli, $notification_id, $target_audience, $club_id);
        
        // Log the notification creation
        logNotificationActivity($mysqli, $notification_id, $admin_id, 'created', $affected_users);
        
        // Commit transaction
        $mysqli->commit();
        
        return [
            'notification_id' => $notification_id,
            'affected_users' => $affected_users,
            'success' => true
        ];
        
    } catch (Exception $e) {
        $mysqli->rollback();
        error_log("Notification creation failed: " . $e->getMessage());
        throw $e;
    }
}

function createUserNotifications($mysqli, $notification_id, $target_audience, $club_id) {
    $users_query = "";
    $params = [];
    $param_types = "";
    
    switch ($target_audience) {
        case 'students':
            $users_query = "SELECT DISTINCT u.id, u.username, u.email FROM users u WHERE u.role = 'student'";
            break;
            
        case 'club_managers':
            if ($club_id) {
                $users_query = "SELECT DISTINCT u.id, u.username, u.email FROM users u 
                               JOIN club_managers cm ON u.id = cm.user_id 
                               WHERE u.role = 'club_manager' AND cm.club_id = ?";
                $params[] = $club_id;
                $param_types = "i";
            } else {
                $users_query = "SELECT DISTINCT u.id, u.username, u.email FROM users u WHERE u.role = 'club_manager'";
            }
            break;
            
        case 'club_members':
            if ($club_id) {
                $users_query = "SELECT DISTINCT u.id, u.username, u.email FROM users u 
                               JOIN memberships m ON u.id = m.user_id 
                               WHERE m.club_id = ? AND m.status = 'approved'";
                $params[] = $club_id;
                $param_types = "i";
            } else {
                throw new Exception("Club ID required for club members target audience");
            }
            break;
            
        case 'admins':
            $users_query = "SELECT DISTINCT u.id, u.username, u.email FROM users u WHERE u.role = 'admin'";
            break;
            
        case 'all':
        default:
            if ($club_id) {
                $users_query = "SELECT DISTINCT u.id, u.username, u.email FROM users u 
                               LEFT JOIN memberships m ON u.id = m.user_id 
                               WHERE (m.club_id = ? AND m.status = 'approved') OR u.role = 'admin'";
                $params[] = $club_id;
                $param_types = "i";
            } else {
                $users_query = "SELECT DISTINCT u.id, u.username, u.email FROM users u";
            }
            break;
    }
    
    // Execute user query
    $users_stmt = $mysqli->prepare($users_query);
    if (!$users_stmt) {
        throw new Exception("Failed to prepare users query: " . $mysqli->error);
    }
    
    if (!empty($params)) {
        $users_stmt->bind_param($param_types, ...$params);
    }
    
    if (!$users_stmt->execute()) {
        throw new Exception("Failed to execute users query: " . $users_stmt->error);
    }
    
    $users_result = $users_stmt->get_result();
    
    // Insert notification for each user
    $insert_user_notification = "INSERT IGNORE INTO user_notifications (
        user_id, 
        notification_id, 
        status, 
        created_at,
        is_read
    ) VALUES (?, ?, 'unread', NOW(), 0)";
    
    $user_notif_stmt = $mysqli->prepare($insert_user_notification);
    
    if (!$user_notif_stmt) {
        throw new Exception("Failed to prepare user notification statement: " . $mysqli->error);
    }
    
    $affected_users = [];
    $success_count = 0;
    
    while ($user = $users_result->fetch_assoc()) {
        $user_notif_stmt->bind_param("ii", $user['id'], $notification_id);
        if ($user_notif_stmt->execute()) {
            $success_count++;
            $affected_users[] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email']
            ];
        }
    }
    
    return [
        'count' => $success_count,
        'users' => $affected_users
    ];
}

function logNotificationActivity($mysqli, $notification_id, $admin_id, $action, $details) {
    $log_query = "INSERT INTO notification_logs (
        notification_id, 
        admin_id, 
        action, 
        details, 
        created_at
    ) VALUES (?, ?, ?, ?, NOW())";
    
    $log_stmt = $mysqli->prepare($log_query);
    
    if ($log_stmt) {
        $details_json = json_encode($details);
        $log_stmt->bind_param("iiss", $notification_id, $admin_id, $action, $details_json);
        $log_stmt->execute();
    }
}

// =================================================================
// ADMIN DASHBOARD FUNCTIONALITY
// =================================================================
$message = "";
$message_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle announcement creation
    if (isset($_POST['action']) && $_POST['action'] == 'create_announcement') {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $club_id = !empty($_POST['club_id']) ? intval($_POST['club_id']) : null;
        $target_audience = $_POST['target_audience'] ?? 'all';
        $priority = $_POST['priority'] ?? 'normal';
        $is_immediate = isset($_POST['immediate_send']);
        $send_email = isset($_POST['send_email']);
        $admin_id = $_SESSION['user_id'];
        
        // Validation
        if (empty($title)) {
            $message = "Announcement title is required.";
            $message_type = "error";
        } elseif (empty($content)) {
            $message = "Announcement content is required.";
            $message_type = "error";
        } else {
            try {
                // Create notification and announcement
                $result = createNotificationForAnnouncement($mysqli, $title, $content, $club_id, $admin_id, $target_audience, $priority);
                
                if ($is_immediate) {
                    // Mark as immediate if requested
                    $update_query = "UPDATE notifications SET is_immediate = 1, sent_at = NOW() WHERE id = ?";
                    $update_stmt = $mysqli->prepare($update_query);
                    $update_stmt->bind_param("i", $result['notification_id']);
                    $update_stmt->execute();
                }
                
                $message = "Announcement posted successfully and notifications sent to " . $result['affected_users']['count'] . " users!";
                $message_type = "success";
                
                // Send email notifications if requested
                if ($send_email && isset($result['affected_users']['users'])) {
                    $email_count = 0;
                    foreach ($result['affected_users']['users'] as $user) {
                        if (!empty($user['email'])) {
                            if (sendEmailNotification($user['email'], $title, $content, $priority)) {
                                $email_count++;
                            }
                        }
                    }
                    $message .= " Email notifications sent to {$email_count} users.";
                }
                
            } catch (Exception $e) {
                $message = "Error posting announcement: " . $e->getMessage();
                $message_type = "error";
                error_log("Announcement creation error: " . $e->getMessage());
            }
        }
    }
}


$message = "";
$message_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create new club - FIXED VERSION
    if (isset($_POST['create_club'])) {
        $name = trim($_POST['club_name']);
        $initials = trim($_POST['club_initials']);
        $description = trim($_POST['club_description']);
        $admin_id = intval($_POST['club_admin']);
        $created_by = $_SESSION['user_id'];

        // Validation
        if (empty($name) || empty($initials) || $admin_id <= 0) {
            $message = "Please fill in all required fields.";
            $message_type = "error";
        } else {
            $mysqli->begin_transaction();
            try {
                // Check if club name or initials already exist
                $check_stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM clubs WHERE name = ? OR initials = ?");
                $check_stmt->bind_param("ss", $name, $initials);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                $row = $result->fetch_assoc();
                
                if ($row['count'] > 0) {
                    throw new Exception("Club name or initials already exists.");
                }

                // Create club
                $stmt = $mysqli->prepare("INSERT INTO clubs (name, initials, description, created_by) VALUES (?, ?, ?, ?)");
                if (!$stmt) {
                    throw new Exception("Failed to prepare club statement: " . $mysqli->error);
                }
                $stmt->bind_param("sssi", $name, $initials, $description, $created_by);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to create club: " . $stmt->error);
                }
                $club_id = $mysqli->insert_id;

                // Create club manager record
                $manager_stmt = $mysqli->prepare("INSERT INTO club_managers (user_id, club_id) VALUES (?, ?)");
                if (!$manager_stmt) {
                    throw new Exception("Manager prepare failed: " . $mysqli->error);
                }
                $manager_stmt->bind_param("ii", $admin_id, $club_id);
                if (!$manager_stmt->execute()) {
                    throw new Exception("Manager execute failed: " . $manager_stmt->error);
                }

                // Update user role to club_manager if not already admin
                $role_stmt = $mysqli->prepare("UPDATE users SET role = 'club_manager' WHERE id = ? AND role != 'admin'");
                $role_stmt->bind_param("i", $admin_id);
                if (!$role_stmt->execute()) {
                    throw new Exception("Role update failed: " . $role_stmt->error);
                }

                $mysqli->commit();
                $message = "Club created successfully!";
                $message_type = "success";
                
                // Refresh the page to show the new club
                header("Location: admin_dashboard.php");
                exit();
            } catch (Exception $e) {
                $mysqli->rollback();
                $message = "Error: " . $e->getMessage();
                $message_type = "error";
                error_log("Club creation error: " . $e->getMessage());
            }
        }
    }

    // Delete club
    if (isset($_POST['delete_club'])) {
        $club_id = intval($_POST['club_id']);
        
        if ($club_id <= 0) {
            $message = "Invalid club ID.";
            $message_type = "error";
        } else {
            $mysqli->begin_transaction();
            try {
                // Delete club manager assignments first
                $manager_stmt = $mysqli->prepare("DELETE FROM club_managers WHERE club_id = ?");
                $manager_stmt->bind_param("i", $club_id);
                $manager_stmt->execute();
                
                // Delete the club
                $club_stmt = $mysqli->prepare("DELETE FROM clubs WHERE id = ?");
                $club_stmt->bind_param("i", $club_id);
                if (!$club_stmt->execute()) {
                    throw new Exception("Failed to delete club: " . $mysqli->error);
                }
                
                $mysqli->commit();
                $message = $club_stmt->affected_rows > 0 ? "Club deleted successfully!" : "No club found with that ID";
                $message_type = $club_stmt->affected_rows > 0 ? "success" : "warning";
            } catch (Exception $e) {
                $mysqli->rollback();
                $message = "Error deleting club: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }

    // Enhanced Announcement Handling
    if (isset($_POST['action']) && $_POST['action'] == 'create_announcement') {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $club_id = !empty($_POST['club_id']) ? intval($_POST['club_id']) : null;
        $target_audience = $_POST['target_audience'] ?? 'all';
        $priority = $_POST['priority'] ?? 'normal';
        $is_immediate = isset($_POST['immediate_send']);
        $send_email = isset($_POST['send_email']);
        $admin_id = $_SESSION['user_id'];
        
        // Enhanced validation
        if (empty($title)) {
            $message = "Announcement title is required.";
            $message_type = "error";
        } elseif (empty($content)) {
            $message = "Announcement content is required.";
            $message_type = "error";
        } elseif (strlen($title) > 255) {
            $message = "Title must be 255 characters or less.";
            $message_type = "error";
        } elseif (strlen($content) > 5000) {
            $message = "Content must be 5000 characters or less.";
            $message_type = "error";
        } else {
            try {
                // Validate club_id if provided
                if ($club_id) {
                    $check_club = "SELECT id, name FROM clubs WHERE id = ?";
                    $check_stmt = $mysqli->prepare($check_club);
                    $check_stmt->bind_param("i", $club_id);
                    $check_stmt->execute();
                    $club_result = $check_stmt->get_result();
                    
                    if ($club_result->num_rows == 0) {
                        throw new Exception("Selected club does not exist.");
                    }
                    $club_info = $club_result->fetch_assoc();
                }
                
                // Create notification and announcement
                if ($is_immediate) {
                    $result = sendImmediateNotification($mysqli, $title, $content, $target_audience, $club_id, $admin_id);
                    $message = "Urgent announcement sent immediately to " . $result['affected_users']['count'] . " users!";
                    $message_type = "success";
                } else {
                    $result = createNotificationForAnnouncement($mysqli, $title, $content, $club_id, $admin_id, $target_audience, $priority);
                    $message = "Announcement posted successfully and notifications sent to " . $result['affected_users']['count'] . " users!";
                    $message_type = "success";
                }
                
                // Send email notifications if requested
                if ($send_email && isset($result['affected_users']['users'])) {
                    $email_count = 0;
                    foreach ($result['affected_users']['users'] as $user) {
                        if (!empty($user['email'])) {
                            if (sendEmailNotification($user['email'], $title, $content, $priority)) {
                                $email_count++;
                            }
                        }
                    }
                    $message .= " Email notifications sent to {$email_count} users.";
                }
                
                // Add club info to success message if applicable
                if ($club_id && isset($club_info)) {
                    $message .= " (Club: " . htmlspecialchars($club_info['name']) . ")";
                }
                
            } catch (Exception $e) {
                $message = "Error posting announcement: " . $e->getMessage();
                $message_type = "error";
                error_log("Announcement creation error: " . $e->getMessage());
            }
        }
    }
}

// Get all clubs with their managers
$clubs_result = $mysqli->query("
    SELECT c.*, u.username as creator_name, cm.user_id as manager_id, um.username as manager_name 
    FROM clubs c
    LEFT JOIN users u ON c.created_by = u.id
    LEFT JOIN club_managers cm ON c.id = cm.club_id
    LEFT JOIN users um ON cm.user_id = um.id
    ORDER BY c.name
");
if ($clubs_result === false) {
    die("Error fetching clubs: " . $mysqli->error);
}

// Get potential club admins (users who are not already managing a club)
$admins_result = $mysqli->query("
    SELECT u.id, u.username 
    FROM users u 
    WHERE u.role IN ('student', 'club_manager')
    AND NOT EXISTS (
        SELECT 1 FROM club_managers cm WHERE cm.user_id = u.id
    )
    ORDER BY u.username
");
if ($admins_result === false) {
    die("Error fetching admins: " . $mysqli->error);
}

// Get recent system announcements (club_id = 0 indicates system-wide announcements)
$announcements_result = $mysqli->query("
    SELECT a.content, a.created_at, u.username 
    FROM announcements a
    JOIN users u ON a.created_by = u.id
    WHERE a.club_id = 0
    ORDER BY a.created_at DESC
    LIMIT 5
");
if ($announcements_result === false) {
    die("Error fetching announcements: " . $mysqli->error);
}

// Get system statistics with error handling
function getStat($mysqli, $query) {
    $result = $mysqli->query($query);
    if ($result === false) {
        error_log("Query failed: " . $query . " - " . $mysqli->error);
        return 0;
    }
    $row = $result->fetch_assoc();
    return $row['count'] ?? 0;
}

$stats = [
    'total_clubs' => getStat($mysqli, "SELECT COUNT(*) as count FROM clubs"),
    'total_users' => getStat($mysqli, "SELECT COUNT(*) as count FROM users"),
    'active_events' => getStat($mysqli, "SELECT COUNT(*) as count FROM events WHERE event_date >= CURDATE()"),
    'pending_requests' => getStat($mysqli, "SELECT COUNT(*) as count FROM patron_requests WHERE status = 'pending'")
];

// Reset the admins result pointer for the form
$admins_result->data_seek(0);
?>

<!DOCTYPE html>
<html>
<head>
    <title>System Admin Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, rgb(169, 153, 136) 0%, rgb(237, 222, 203) 100%);
            min-height: 100vh;
            line-height: 1.6;
        }

        .navbar {
            background: linear-gradient(135deg, rgb(150, 85, 10) 0%, rgb(209, 120, 25) 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .navbar-brand {
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            background: linear-gradient(45deg, rgb(237, 222, 203), rgb(169, 153, 136));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .nav-btn {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            padding: 12px 20px;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 500;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .nav-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .user-welcome {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.1);
            padding: 10px 16px;
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }

        .container {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .alert {
            padding: 18px 24px;
            border-radius: 16px;
            margin-bottom: 30px;
            font-weight: 600;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid;
            display: flex;
            align-items: center;
            gap: 12px;
            backdrop-filter: blur(10px);
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert.success {
            background: linear-gradient(135deg, rgba(209, 120, 25, 0.9), rgba(150, 85, 10, 0.9));
            color: white;
            border-color: rgba(209, 120, 25, 0.3);
        }

        .alert.error {
            background: linear-gradient(135deg, rgba(150, 85, 10, 0.9), rgba(123, 71, 14, 0.9));
            color: white;
            border-color: rgba(150, 85, 10, 0.3);
        }

        .alert.warning {
            background: linear-gradient(135deg, rgba(240, 129, 12, 0.9), rgba(209, 120, 25, 0.9));
            color: white;
            border-color: rgba(240, 129, 12, 0.3);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: rgba(237, 222, 203, 0.95);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(209, 120, 25, 0.2);
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, rgb(209, 120, 25), rgb(150, 85, 10));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .stat-card:hover::before {
            transform: scaleX(1);
        }

        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 60px rgba(209, 120, 25, 0.3);
        }

        .stat-card:hover .stat-icon {
            color: rgb(209, 120, 25);
            transform: scale(1.1);
        }

        .stat-card:hover .stat-number {
            color: rgb(150, 85, 10);
        }

        .stat-icon {
            font-size: 42px;
            color: rgb(209, 120, 25);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .stat-number {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 12px;
            color: rgb(123, 71, 14);
            transition: color 0.3s ease;
        }

        .stat-label {
            color: rgb(150, 85, 10);
            font-size: 16px;
            font-weight: 500;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }

        @media (min-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        .dashboard-section {
            background: rgba(237, 222, 203, 0.95);
            border-radius: 24px;
            padding: 35px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(209, 120, 25, 0.2);
        }

        .section-title {
            color: rgb(123, 71, 14);
            margin-bottom: 30px;
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .section-title i {
            color: rgb(209, 120, 25);
            background: linear-gradient(135deg, rgb(209, 120, 25), rgb(150, 85, 10));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .form-group {
            margin-bottom: 24px;
        }

        label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: rgb(123, 71, 14);
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-control, input, select, textarea {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid rgb(169, 153, 136);
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
        }

        .form-control:focus, input:focus, select:focus, textarea:focus {
            border-color: rgb(209, 120, 25);
            outline: none;
            box-shadow: 0 0 0 4px rgba(209, 120, 25, 0.1);
            background: rgba(255, 255, 255, 0.95);
        }

        .btn {
            display: inline-block;
            background: linear-gradient(135deg, rgb(209, 120, 25) 0%, rgb(150, 85, 10) 100%);
            color: white;
            border: none;
            padding: 16px 32px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: center;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(209, 120, 25, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, rgb(150, 85, 10) 0%, rgb(123, 71, 14) 100%);
        }

        .btn-danger:hover {
            box-shadow: 0 10px 30px rgba(150, 85, 10, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, rgb(169, 153, 136) 0%, rgb(149, 140, 129) 100%);
        }

        .btn-secondary:hover {
            box-shadow: 0 10px 30px rgba(169, 153, 136, 0.4);
        }

        .btn-report {
            background: linear-gradient(135deg, rgb(209, 120, 25) 0%, rgb(240, 129, 12) 100%);
        }

        .btn-report:hover {
            box-shadow: 0 10px 30px rgba(209, 120, 25, 0.4);
        }

        .btn-edit {
            background: linear-gradient(135deg, rgb(240, 129, 12) 0%, rgb(245, 131, 9) 100%);
        }

        .btn-edit:hover {
            box-shadow: 0 10px 30px rgba(240, 129, 12, 0.4);
        }

        .table-responsive {
            overflow-x: auto;
            margin-top: 25px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(237, 222, 203, 0.95);
            backdrop-filter: blur(10px);
        }

        th {
            background: linear-gradient(135deg, rgb(237, 222, 203), rgb(169, 153, 136));
            padding: 20px 24px;
            text-align: left;
            font-weight: 700;
            color: rgb(123, 71, 14);
            border-bottom: 2px solid rgb(209, 120, 25);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 20px 24px;
            border-bottom: 1px solid rgb(209, 120, 25);
            vertical-align: top;
        }

        tr:hover {
            background: linear-gradient(135deg, rgba(209, 120, 25, 0.05), rgba(150, 85, 10, 0.05));
        }

        .club-description {
            color: rgb(150, 85, 10);
            font-size: 14px;
            margin-top: 6px;
            line-height: 1.5;
        }

        .no-admin {
            color: rgb(123, 71, 14);
            font-style: italic;
            font-weight: 500;
        }

        .no-data {
            background: linear-gradient(135deg, rgb(237, 222, 203), rgb(169, 153, 136));
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            color: rgb(150, 85, 10);
            font-style: italic;
            font-size: 16px;
            border: 2px dashed rgb(209, 120, 25);
        }

        .admin-tools {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 35px;
        }

        .admin-tools .btn {
            width: 100%;
            text-align: center;
            padding: 20px;
            font-size: 15px;
        }

        .announcements-list {
            margin-bottom: 35px;
        }

        .announcement {
            background: linear-gradient(135deg, rgba(209, 120, 25, 0.1), rgba(150, 85, 10, 0.1));
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid rgb(209, 120, 25);
            backdrop-filter: blur(10px);
            transition: transform 0.2s ease;
        }

        .announcement:hover {
            transform: translateX(5px);
        }

        .announcement-content {
            margin-bottom: 15px;
            line-height: 1.6;
            color: rgb(123, 71, 14);
        }

        .announcement-meta {
            font-size: 14px;
            color: rgb(150, 85, 10);
            display: flex;
            gap: 20px;
            font-weight: 500;
        }

        /* ENHANCED NOTIFICATION STYLES */
        .announcement-form-enhanced {
            background: rgba(237, 222, 203, 0.95);
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            margin-top: 35px;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(209, 120, 25, 0.2);
        }

        .template-selector {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .template-card {
            border: 2px solid rgb(169, 153, 136);
            border-radius: 16px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
        }

        .template-card:hover {
            border-color: rgb(209, 120, 25);
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(209, 120, 25, 0.2);
            background: rgba(255, 255, 255, 0.95);
        }

        .template-card h4 {
            color: rgb(209, 120, 25);
            margin-bottom: 12px;
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .template-card p {
            font-size: 14px;
            color: rgb(150, 85, 10);
            line-height: 1.5;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }

        @media (min-width: 768px) {
            .form-row {
                grid-template-columns: 1fr 1fr;
            }
        }

        .form-options {
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
            margin: 30px 0;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            cursor: pointer;
            padding: 16px 20px;
            background: linear-gradient(135deg, rgba(209, 120, 25, 0.1), rgba(150, 85, 10, 0.1));
            border-radius: 12px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            backdrop-filter: blur(10px);
        }

        .checkbox-label:hover {
            background: linear-gradient(135deg, rgba(209, 120, 25, 0.15), rgba(150, 85, 10, 0.15));
            border-color: rgb(209, 120, 25);
            transform: translateY(-2px);
        }

        .checkbox-label input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: rgb(209, 120, 25);
        }

        .character-count {
            display: block;
            margin-top: 8px;
            font-size: 12px;
            color: rgb(150, 85, 10);
            text-align: right;
            font-weight: 500;
        }

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .view-btn {
            background: linear-gradient(135deg, rgba(237, 222, 203, 0.8), rgba(169, 153, 136, 0.8));
            color: rgb(123, 71, 14);
        }

        .view-btn:hover {
            background: linear-gradient(135deg, rgba(169, 153, 136, 0.8), rgba(149, 140, 129, 0.8));
            transform: translateY(-1px);
        }

        .delete-btn {
            background: linear-gradient(135deg, rgba(150, 85, 10, 0.2), rgba(123, 71, 14, 0.2));
            color: rgb(123, 71, 14);
        }

        .delete-btn:hover {
            background: linear-gradient(135deg, rgba(150, 85, 10, 0.4), rgba(123, 71, 14, 0.4));
            transform: translateY(-1px);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .form-options {
                flex-direction: column;
            }

            .admin-tools {
                grid-template-columns: 1fr;
            }
        }

        /* Animation for page load */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dashboard-section {
            animation: fadeInUp 0.6s ease-out;
        }

        .stat-card {
            animation: fadeInUp 0.4s ease-out;
        }

        .stat-card:nth-child(2) {
            animation-delay: 0.1s;
        }

        .stat-card:nth-child(3) {
            animation-delay: 0.2s;
        }

        .stat-card:nth-child(4) {
            animation-delay: 0.3s;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-cogs"></i> School Club System - Admin Panel
        </div>
        <div class="nav-links">
            <a href="home.php" class="nav-btn"><i class="fas fa-home"></i> Back to Home</a>
            <div class="user-welcome">
                <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
            </div>
            <a href="logout.php" class="nav-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert <?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'error' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

       <!-- Quick Stats -->
<div class="stats-grid">
    <a href="user_management.php" class="stat-card" style="display: block; text-decoration: none; color: inherit;">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-number"><?php echo $stats['total_users']; ?></div>
        <div class="stat-label">Total Users</div>
    </a>
    
    <a href="club.php" class="stat-card" style="display: block; text-decoration: none; color: inherit;">
        <div class="stat-icon"><i class="fas fa-star"></i></div>
        <div class="stat-number"><?php echo $stats['total_clubs']; ?></div>
        <div class="stat-label">Active Clubs</div>
    </a>
    
    <a href="events.php" class="stat-card" style="display: block; text-decoration: none; color: inherit;">
        <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
        <div class="stat-number"><?php echo $stats['active_events']; ?></div>
        <div class="stat-label">Upcoming Events</div>
    </a>
    
   <a href="admin_patron_requests.php" class="stat-card" style="display: block; text-decoration: none; color: inherit;">
    <div class="stat-icon"><i class="fas fa-clock"></i></div>
    <div class="stat-number"><?php echo $stats['pending_requests']; ?></div>
    <div class="stat-label">Pending Patron Requests</div>
</a>
</div>

       
            <!-- System Tools -->
            <div class="dashboard-section">
                <h2 class="section-title"><i class="fas fa-tools"></i> System Administration</h2>
                
                <div class="admin-tools">
                    <a href="user_management.php" class="btn">
                        <i class="fas fa-users-cog"></i> Manage Users
                    </a>
                    <a href="assign_patrons.php" class="btn">
                        <i class="fas fa-user-tie"></i> Assign Patrons
                    </a>
                    <a href="system_report.php" class="btn btn-report">
                        <i class="fas fa-file-alt"></i> Generate Report
                    </a>
                    <a href="settings.php" class="btn">
                        <i class="fas fa-cog"></i> System Settings
                    </a>
                </div>

                <h3><i class="fas fa-bullhorn"></i> System Announcements</h3>
                <div class="announcements-list">
                    <?php if ($announcements_result->num_rows > 0): ?>
                        <?php while($announcement = $announcements_result->fetch_assoc()): ?>
                            <div class="announcement">
                                <div class="announcement-content">
                                    <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                </div>
                                <div class="announcement-meta">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($announcement['username']); ?> 
                                    | <i class="fas fa-clock"></i> <?php echo date('M j, Y g:i a', strtotime($announcement['created_at'])); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-data">No system announcements yet</div>
                    <?php endif; ?>
                </div>

                <!-- Enhanced Announcement Form -->
                <div class="announcement-form-enhanced">
                    <h3><i class="fas fa-bullhorn"></i> Create New Announcement</h3>
                    
                    <div class="template-selector">
                        <div class="template-card" onclick="loadTemplate('meeting_reminder')">
                            <h4><i class="fas fa-calendar-check"></i> Meeting Reminder</h4>
                            <p>Remind members about upcoming meetings...</p>
                        </div>
                        <div class="template-card" onclick="loadTemplate('event_announcement')">
                            <h4><i class="fas fa-calendar-plus"></i> Event Announcement</h4>
                            <p>Announce new events to members...</p>
                        </div>
                        <div class="template-card" onclick="loadTemplate('urgent_notice')">
                            <h4><i class="fas fa-exclamation-triangle"></i> Urgent Notice</h4>
                            <p>Send critical alerts immediately...</p>
                        </div>
                    </div>

                    <form method="POST" action="">
                        <input type="hidden" name="action" value="create_announcement">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="title"><i class="fas fa-heading"></i> Title</label>
                                <input type="text" id="title" name="title" class="form-control" required maxlength="255" placeholder="Enter announcement title">
                                <span class="character-count"><span id="title-count">0</span>/255 characters</span>
                            </div>
                            
                            <div class="form-group">
                                <label for="club_id"><i class="fas fa-users"></i> Club (Optional)</label>
                                <select id="club_id" name="club_id" class="form-control">
                                    <option value="">-- Select Club --</option>
                                    <?php
                                    $clubs = $mysqli->query("SELECT id, name FROM clubs ORDER BY name");
                                    while ($club = $clubs->fetch_assoc()): ?>
                                        <option value="<?= $club['id'] ?>"><?= htmlspecialchars($club['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="content"><i class="fas fa-align-left"></i> Content</label>
                            <textarea id="content" name="content" class="form-control" rows="6" required placeholder="Enter announcement content..."></textarea>
                            <span class="character-count"><span id="content-count">0</span>/5000 characters</span>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="target_audience"><i class="fas fa-bullseye"></i> Target Audience</label>
                                <select id="target_audience" name="target_audience" class="form-control">
                                    <option value="all">All Users</option>
                                    <option value="students">Students Only</option>
                                    <option value="club_managers">Club Managers</option>
                                    <option value="club_members">Club Members</option>
                                    <option value="admins">Admins Only</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="priority"><i class="fas fa-flag"></i> Priority</label>
                                <select id="priority" name="priority" class="form-control">
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-options">
                            <label class="checkbox-label">
                                <input type="checkbox" name="immediate_send" value="1">
                                <i class="fas fa-bolt"></i> Send Immediately (High Priority)
                            </label>
                            
                            <label class="checkbox-label">
                                <input type="checkbox" name="send_email" value="1" checked>
                                <i class="fas fa-envelope"></i> Send Email Notifications
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn">
                                <i class="fas fa-paper-plane"></i> Send Announcement
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Character counters for form fields
        document.getElementById('title').addEventListener('input', function() {
            document.getElementById('title-count').textContent = this.value.length;
        });
        
        document.getElementById('content').addEventListener('input', function() {
            document.getElementById('content-count').textContent = this.value.length;
        });
        
        // Template loader function
        function loadTemplate(templateKey) {
            const templates = {
                meeting_reminder: {
                    title: "Meeting Reminder",
                    content: "This is a reminder about our upcoming meeting on [DATE] at [TIME] in [LOCATION]. Please come prepared with any materials mentioned in previous communications.",
                    priority: "normal"
                },
                event_announcement: {
                    title: "New Event Announcement",
                    content: "We are excited to announce our upcoming event: [EVENT_NAME] on [DATE]. Join us for [BRIEF_DESCRIPTION]. More details to follow.",
                    priority: "normal"
                },
                urgent_notice: {
                    title: "Urgent Notice",
                    content: "URGENT: [DESCRIBE_URGENT_MATTER]. Please take immediate action or note the following important information: [DETAILS]",
                    priority: "urgent"
                }
            };
            
            const template = templates[templateKey];
            if (template) {
                document.getElementById('title').value = template.title;
                document.getElementById('content').value = template.content;
                document.getElementById('priority').value = template.priority;
                
                // Update character counters
                document.getElementById('title-count').textContent = template.title.length;
                document.getElementById('content-count').textContent = template.content.length;
                
                // Scroll to form
                document.querySelector('.announcement-form-enhanced').scrollIntoView({
                    behavior: 'smooth'
                });
            }
        }
    </script>
</body>
</html>