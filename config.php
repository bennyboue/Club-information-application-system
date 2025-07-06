<?php
// Database configuration
define('DB_HOST', '127.0.0.1');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'ics_project');

// Session configuration - MUST be set before session_start()
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
    session_set_cookie_params([
        'lifetime' => 86400, // 24 hours
        'path' => '/',
        'domain' => '',
        'secure' => false, // Set to true if using HTTPS
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

// Create database connection
try {
    $conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to utf8mb4
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    // Log error and show user-friendly message
    error_log("Database connection error: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

// Application configuration
define('SITE_NAME', 'ICS Club Management System');
define('SITE_URL', 'http://localhost/ics_project');
define('ADMIN_EMAIL', 'admin@strathmore.edu');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Africa/Nairobi');

// Helper functions
function sanitize_input($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function showAlert($message, $type = 'info') {
    $_SESSION['alert'] = [
        'message' => $message,
        'type' => $type
    ];
}

function displayAlert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        $alertClass = '';
        
        switch ($alert['type']) {
            case 'success':
                $alertClass = 'alert-success';
                break;
            case 'error':
                $alertClass = 'alert-danger';
                break;
            case 'warning':
                $alertClass = 'alert-warning';
                break;
            default:
                $alertClass = 'alert-info';
        }
        
        echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($alert['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
        
        unset($_SESSION['alert']);
    }
}

// Check if user is logged in
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        redirect('login.php');
    }
}

// Check if user has specific role
function requireRole($required_role) {
    requireLogin();
    
    if ($_SESSION['role'] !== $required_role) {
        showAlert('Access denied. Insufficient permissions.', 'error');
        redirect('dashboard.php');
    }
}

// Check if user has any of the specified roles
function requireAnyRole($roles) {
    requireLogin();
    
    if (!in_array($_SESSION['role'], $roles)) {
        showAlert('Access denied. Insufficient permissions.', 'error');
        redirect('dashboard.php');
    }
}

// Get user information
function getCurrentUser() {
    global $conn;
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

// Check if user manages a specific club
function userManagesClub($user_id, $club_id) {
    global $conn;
    
    $sql = "SELECT id FROM club_managers WHERE user_id = ? AND club_id = ? AND status = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $club_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}

// Log admin activity
function logAdminActivity($action, $target, $performed_by) {
    global $conn;
    
    $sql = "INSERT INTO admin_activity (action, target, performed_by, action_time) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $action, $target, $performed_by);
    $stmt->execute();
}

// Format date for display
function formatDate($date, $format = 'M j, Y g:i A') {
    if (!$date) return 'N/A';
    
    try {
        $dateObj = new DateTime($date);
        return $dateObj->format($format);
    } catch (Exception $e) {
        return 'Invalid Date';
    }
}

// Generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Validate email format
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Generate random password
function generateRandomPassword($length = 8) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[random_int(0, strlen($characters) - 1)];
    }
    
    return $password;
}

// Hash password securely
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Get system settings
function getSystemSetting($key, $default = null) {
    global $conn;
    
    $sql = "SELECT setting_value FROM system_settings WHERE setting_key = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    
    return $default;
}

// Set system setting
function setSystemSetting($key, $value) {
    global $conn;
    
    $sql = "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $key, $value, $value);
    return $stmt->execute();
}

// Clean old sessions (call this periodically)
function cleanOldSessions() {
    // This would typically be implemented with a proper session handler
    // For now, we'll just clean old notification logs as an example
    global $conn;
    
    $sql = "DELETE FROM notification_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $conn->query($sql);
}
?>