<?php
session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "ics_project");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

// Include notifications functions
require_once 'notifications_functions.php';

$user_id = $_SESSION['user_id'];

try {
    // Get notifications data
    $notifications = getUserNotifications($conn, $user_id, 5);
    $unread_count = getUnreadNotificationCount($conn, $user_id);
    
    // Format notifications for JSON response
    $formatted_notifications = [];
    foreach ($notifications as $notification) {
        $formatted_notifications[] = [
            'id' => $notification['id'],
            'title' => htmlspecialchars($notification['title']),
            'message' => htmlspecialchars(substr($notification['message'], 0, 100)) . '...',
            'type' => $notification['type'],
            'is_read' => (bool)$notification['is_read'],
            'club_name' => $notification['club_name'] ? htmlspecialchars($notification['club_name']) : null,
            'created_by_name' => $notification['created_by_name'] ? htmlspecialchars($notification['created_by_name']) : null,
            'time_ago' => timeAgo($notification['created_at']),
            'created_at' => $notification['created_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $formatted_notifications,
        'unread_count' => $unread_count,
        'total_count' => count($formatted_notifications)
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_notifications_ajax.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to fetch notifications']);
}

$conn->close();
?>