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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Admin Dashboard Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Times New Roma', Times, serif;
            background: linear-gradient(135deg, rgb(169, 153, 136), #a8967d);
            min-height: 100vh;
            color: #333;
            padding: 0;
        }

        /* Navigation Bar */
        .navbar {
            background: rgb(237, 222, 203);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: bold;
            color: rgb(209, 120, 25);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .nav-btn, .nav-links a {
            color: rgb(209, 120, 25);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(209, 120, 25, 0.1);
        }

        .nav-btn:hover, .nav-links a:hover {
            background: rgba(150, 85, 10, 0.2);
            color: rgb(209, 120, 25);
        }

        .user-welcome {
            color: rgb(209, 120, 25);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(150, 85, 10, 0.1);
            border-radius: 8px;
        }

        /* Main Container */
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
        }

        /* Settings Card */
        .settings-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            margin: 0 auto;
        }

        .section-title {
            color: #2d3748;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.5rem;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 0.5rem;
            color: rgb(209, 120, 25);
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: rgba(237, 222, 203, 0.2);
            border-radius: 10px;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #4a5568;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            background: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: rgb(209, 120, 25);
            box-shadow: 0 0 0 3px rgba(209, 120, 25, 0.1);
        }

        /* Button Styles */
        .btn {
            background: linear-gradient(135deg, rgb(209, 120, 25) 0%, rgb(150, 85, 10) 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(209, 120, 25, 0.4);
        }

        .btn-cancel {
            background: linear-gradient(135deg, #718096 0%, #4a5568 100%);
        }

        .btn-cancel:hover {
            box-shadow: 0 5px 15px rgba(113, 128, 150, 0.4);
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        /* Alert Message */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert.success {
            background: rgba(56, 161, 105, 0.15);
            border-left: 4px solid #38a169;
            color: #2f855a;
        }

        .alert.error {
            background: rgba(229, 62, 62, 0.15);
            border-left: 4px solid #e53e3e;
            color: #c53030;
        }

        .alert i {
            font-size: 1.2rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .navbar {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-links {
                width: 100%;
                justify-content: center;
            }
            
            .settings-card {
                padding: 1.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-cogs"></i> School Club System - Settings
        </div>
        <div class="nav-links">
            <div class="user-welcome">
                <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
            </div>
            <a href="admin_dashboard.php"><i class="fas fa-home"></i> Home</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="settings-card">
            <?php if ($message): ?>
                <div class="alert <?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <h2 class="section-title"><i class="fas fa-cog"></i> System Settings</h2>
            
            <form method="POST">
                <div class="form-group">
                    <label for="school_name"><i class="fas fa-school"></i> School Name</label>
                    <input type="text" id="school_name" name="school_name" class="form-control" required 
                           value="<?php echo htmlspecialchars($settings['school_name']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="max_clubs"><i class="fas fa-user-graduate"></i> Max Clubs per Student</label>
                    <input type="number" id="max_clubs" name="max_clubs" class="form-control" min="1" max="5" 
                           value="<?php echo htmlspecialchars($settings['max_clubs_per_student']); ?>">
                    <small style="display: block; margin-top: 0.5rem; color: #718096;">Maximum number of clubs a student can join</small>
                </div>
                
                <div class="form-group">
                    <label for="enable_registration">
                        <input type="checkbox" id="enable_registration" name="enable_registration" value="1" 
                            <?php if ($settings['enable_registration'] == 1) echo 'checked'; ?>>
                        <i class="fas fa-user-plus"></i> Enable New User Registration
                    </label>
                    <small style="display: block; margin-top: 0.5rem; color: #718096;">Allow new users to register accounts</small>
                </div>
                
                <div class="action-buttons">
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                    <a href="admin_dashboard.php" class="btn btn-cancel">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>