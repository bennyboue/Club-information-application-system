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

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['notification_id'])) {
    echo json_encode(['success' => false, 'error' => 'Notification ID is required']);
    exit();
}

$user_id = $_SESSION['user_id'];
$notification_id = (int)$input['notification_id'];

// Validate that the notification belongs to the user
$validate_query = "SELECT un.id FROM user_notifications un
                   JOIN notifications n ON un.notification_id = n.id
                   WHERE un.user_id = ? AND un.notification_id = ? AND n.is_active = 1";

$validate_stmt = $conn->prepare($validate_query);
if (!$validate_stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit();
}

$validate_stmt->bind_param("ii", $user_id, $notification_id);
$validate_stmt->execute();
$validate_result = $validate_stmt->get_result();

if ($validate_result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Notification not found or access denied']);
    exit();
}

// Mark notification as read
$success = markNotificationAsRead($conn, $user_id, $notification_id);

if ($success) {
    // Get updated unread count
    $unread_count = getUnreadNotificationCount($conn, $user_id);
    
    echo json_encode([
        'success' => true,
        'unread_count' => $unread_count,
        'message' => 'Notification marked as read'
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to mark notification as read']);
}

$conn->close();
?>