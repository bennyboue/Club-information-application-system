<?php
require_once 'auth_check.php';

$conn = new mysqli("localhost", "root", "", "ics_project");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create system_settings table if it doesn't exist
$conn->query("
    CREATE TABLE IF NOT EXISTS `system_settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `setting_key` varchar(100) NOT NULL,
        `setting_value` text NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Insert default settings if table is empty
$result = $conn->query("SELECT COUNT(*) as count FROM system_settings");
$row = $result->fetch_assoc();
if ($row['count'] == 0) {
    $conn->query("INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
        ('school_name', 'Your School Name'),
        ('max_clubs_per_student', '3'),
        ('enable_registration', '1')
    ");
}

// Get current settings
$settings_result = $conn->query("SELECT * FROM system_settings");
$settings = [];
while ($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Handle form submission
$message = "";
$message_type = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $school_name = trim($_POST['school_name']);
    $max_clubs = intval($_POST['max_clubs']);
    $enable_registration = isset($_POST['enable_registration']) ? 1 : 0;
    
    if (empty($school_name)) {
        $message = "School name is required";
        $message_type = "error";
    } else {
        // Update settings in database
        $conn->query("UPDATE system_settings SET setting_value = '$school_name' WHERE setting_key = 'school_name'");
        $conn->query("UPDATE system_settings SET setting_value = '$max_clubs' WHERE setting_key = 'max_clubs_per_student'");
        $conn->query("UPDATE system_settings SET setting_value = '$enable_registration' WHERE setting_key = 'enable_registration'");
        
        $message = "Settings updated successfully!";
        $message_type = "success";
        
        // Update local settings array
        $settings['school_name'] = $school_name;
        $settings['max_clubs_per_student'] = $max_clubs;
        $settings['enable_registration'] = $enable_registration;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>System Settings</title>
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="admin-navbar">
        <div class="admin-navbar-brand">
            <i class="fas fa-cogs"></i> School Club System - Settings
        </div>
        <div class="admin-nav-links">
            <a href="admin_dashboard.php"><i class="fas fa-home"></i> Home</a>
            <div class="admin-user-welcome">
                <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
            </div>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="admin-container">
        <?php if ($message): ?>
            <div class="admin-alert <?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <h1 class="admin-section-title"><i class="fas fa-cog"></i> System Settings</h1>
        
        <form method="POST">
            <div class="admin-form-group">
                <label><i class="fas fa-school"></i> School Name</label>
                <input type="text" name="school_name" class="admin-form-control" required 
                       value="<?php echo htmlspecialchars($settings['school_name']); ?>">
            </div>
            
            <div class="admin-form-group">
                <label><i class="fas fa-user-graduate"></i> Max Clubs per Student</label>
                <input type="number" name="max_clubs" class="admin-form-control" min="1" max="5" 
                       value="<?php echo htmlspecialchars($settings['max_clubs_per_student']); ?>">
                <small>Maximum number of clubs a student can join</small>
            </div>
            
            <div class="admin-form-group">
                <label>
                    <input type="checkbox" name="enable_registration" value="1" 
                        <?php if ($settings['enable_registration'] == 1) echo 'checked'; ?>>
                    <i class="fas fa-user-plus"></i> Enable New User Registration
                </label>
                <small>Allow new users to register accounts</small>
            </div>
            
            <div class="admin-action-buttons">
                <button type="submit" class="admin-btn">
                    <i class="fas fa-save"></i> Save Settings
                </button>
                <a href="admin_dashboard.php" class="admin-btn admin-btn-cancel">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</body>
</html>