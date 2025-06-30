<?php
// mark_notification_read.php - AJAX handler for marking notifications as read

session_start();
require_once 'config.php'; // Your database connection

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['notification_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Notification ID required']);
    exit;
}

$user_id = $_SESSION['user_id'];
$notification_id = intval($input['notification_id']);

try {
    // Mark notification as read
    $query = "UPDATE user_notifications 
              SET is_read = 1, read_at = NOW() 
              WHERE user_id = ? AND notification_id = ? AND is_read = 0";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("ii", $user_id, $notification_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Notification not found or already read']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>

<?php
// notifications.php - Full notifications page

session_start();
require_once 'config.php';
require_once 'notifications_functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Handle mark all as read
if (isset($_POST['mark_all_read'])) {
    $query = "UPDATE user_notifications 
              SET is_read = 1, read_at = NOW() 
              WHERE user_id = ? AND is_read = 0";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    header('Location: notifications.php');
    exit;
}

// Get notifications with pagination
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
          ORDER BY n.created_at DESC
          LIMIT ? OFFSET ?";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("iii", $user_id, $per_page, $offset);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total 
                FROM user_notifications un
                JOIN notifications n ON un.notification_id = n.id
                WHERE un.user_id = ? 
                  AND n.is_active = 1 
                  AND (n.expires_at IS NULL OR n.expires_at > NOW())";
$count_stmt = $mysqli->prepare($count_query);
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$total_notifications = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_notifications / $per_page);

$unread_count = getUnreadNotificationCount($mysqli, $user_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <div class="notifications-page">
            <div class="page-header">
                <h1>All Notifications</h1>
                <div class="header-actions">
                    <?php if ($unread_count > 0): ?>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="mark_all_read" class="btn btn-secondary">
                                Mark All as Read (<?php echo $unread_count; ?>)
                            </button>
                        </form>
                    <?php endif; ?>
                    <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
                </div>
            </div>

            <?php if (empty($notifications)): ?>
                <div class="no-notifications">
                    <h3>📭 No notifications</h3>
                    <p>You're all caught up! Check back later for new announcements.</p>
                </div>
            <?php else: ?>
                <div class="notifications-grid">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-card <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>" 
                             data-notification-id="<?php echo $notification['id']; ?>">
                            
                            <div class="notification-header">
                                <h3><?php echo htmlspecialchars($notification['title']); ?></h3>
                                <?php if (!$notification['is_read']): ?>
                                    <span class="new-badge">NEW</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="notification-body">
                                <p><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                            </div>
                            
                            <div class="notification-footer">
                                <div class="notification-meta">
                                    <?php if ($notification['club_name']): ?>
                                        <span class="club-badge">📍 <?php echo htmlspecialchars($notification['club_name']); ?></span>
                                    <?php endif; ?>
                                    <span class="date">🕒 <?php echo date('M j, Y \a\t g:i A', strtotime($notification['created_at'])); ?></span>
                                    <span class="author">👤 <?php echo htmlspecialchars($notification['created_by_name']); ?></span>
                                </div>
                                
                                <?php if (!$notification['is_read']): ?>
                                    <button onclick="markAsRead(<?php echo $notification['id']; ?>)" class="btn btn-sm btn-outline">
                                        Mark as Read
                                    </button>
                                <?php else: ?>
                                    <span class="read-status">✓ Read on <?php echo date('M j', strtotime($notification['read_at'])); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>" 
                               class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
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
                    location.reload();
                }
            })
            .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>