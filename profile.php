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
$message = "";
$error = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $surname = trim($_POST['surname']);
    $email = trim($_POST['email']);
    $school_id = trim($_POST['school_id']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($username) || empty($email)) {
        $error = "Username and Email are required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (!empty($new_password) && strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters long.";
    } else {
        // Check if username or email already exists for other users
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $check_stmt->bind_param("ssi", $username, $email, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Username or email already exists. Please choose different ones.";
        } else {
            // If password change is requested, verify current password
            if (!empty($new_password)) {
                $pass_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $pass_stmt->bind_param("i", $user_id);
                $pass_stmt->execute();
                $pass_result = $pass_stmt->get_result();
                $user_data = $pass_result->fetch_assoc();
                
                if (!password_verify($current_password, $user_data['password'])) {
                    $error = "Current password is incorrect.";
                } else {
                    // Update with new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_stmt = $conn->prepare("UPDATE users SET username = ?, surname = ?, email = ?, school_id = ?, password = ? WHERE id = ?");
                    $update_stmt->bind_param("sssssi", $username, $surname, $email, $school_id, $hashed_password, $user_id);
                }
            } else {
                // Update without password change
                $update_stmt = $conn->prepare("UPDATE users SET username = ?, surname = ?, email = ?, school_id = ? WHERE id = ?");
                $update_stmt->bind_param("ssssi", $username, $surname, $email, $school_id, $user_id);
            }
            
            if (empty($error) && $update_stmt->execute()) {
                // Update session username if it changed
                $_SESSION['username'] = $username;
                $message = "Profile updated successfully!";
            } else {
                $error = "Failed to update profile. Please try again.";
            }
        }
    }
}

// Fetch current user data
$profile_stmt = $conn->prepare("SELECT username, surname, email, school_id, role FROM users WHERE id = ?");
$profile_stmt->bind_param("i", $user_id);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();
$user_profile = $profile_result->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Profile - School Club Management</title>
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

        .container {
            padding: 30px;
            max-width: 800px;
            margin: 0 auto;
        }

        .profile-header {
            background-color: #fff;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            text-align: center;
        }

        .profile-header h1 {
            color: rgb(209, 120, 25);
            margin: 0;
            font-size: 28px;
        }

        .form-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .form-section {
            margin-bottom: 30px;
        }

        .section-title {
            color: rgb(209, 120, 25);
            font-size: 20px;
            margin-bottom: 15px;
            border-bottom: 2px solid rgb(209, 120, 25);
            padding-bottom: 5px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: rgb(209, 120, 25);
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: rgb(209, 120, 25);
        }

        .form-group small {
            color: #666;
            font-size: 12px;
            display: block;
            margin-top: 5px;
        }

        .btn {
            background-color: rgb(209, 120, 25);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-right: 10px;
        }

        .btn:hover {
            background-color: rgb(150, 85, 10);
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s ease;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .password-section {
            background-color: rgb(237, 222, 203);
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 15px;
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
        <span style="color:black; font-weight:bold;">Hello, <?php echo htmlspecialchars($user_profile['username']); ?>!</span>
        <a href="student_dashboard.php">Dashboard</a>
        <a href="home.php">Browse Clubs</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <div class="profile-header">
        <h1>Edit Profile</h1>
        <p>Update your account information</p>
    </div>

    <div class="form-container">
        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-section">
                <h3 class="section-title">Basic Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user_profile['username']); ?>" required>
                        <small>Your unique username for the system</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="surname">Surname</label>
                        <input type="text" id="surname" name="surname" value="<?php echo htmlspecialchars($user_profile['surname'] ?? ''); ?>">
                        <small>Your last name or family name</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_profile['email']); ?>" required>
                        <small>We'll use this for important notifications</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="school_id">School ID</label>
                        <input type="text" id="school_id" name="school_id" value="<?php echo htmlspecialchars($user_profile['school_id'] ?? ''); ?>">
                        <small>Your official school identification number</small>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="section-title">Change Password</h3>
                <div class="password-section">
                    <p style="margin-top: 0; color: #666; font-size: 14px;">
                        Leave password fields empty if you don't want to change your password.
                    </p>
                    
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password">
                        <small>Enter your current password to change it</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password">
                            <small>Minimum 6 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password">
                            <small>Re-enter your new password</small>
                        </div>
                    </div>
                </div>
            </div>

            <div style="text-align: center; margin-top: 30px;">
                <button type="submit" class="btn">Update Profile</button>
                <a href="student_dashboard.php" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
// Add some client-side validation
document.querySelector('form').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const currentPassword = document.getElementById('current_password').value;
    
    // If trying to change password
    if (newPassword || confirmPassword || currentPassword) {
        if (!currentPassword) {
            alert('Please enter your current password to change it.');
            e.preventDefault();
            return;
        }
        
        if (newPassword !== confirmPassword) {
            alert('New passwords do not match.');
            e.preventDefault();
            return;
        }
        
        if (newPassword.length < 6) {
            alert('New password must be at least 6 characters long.');
            e.preventDefault();
            return;
        }
    }
});

// Show confirmation message when profile is updated successfully
<?php if (!empty($message)): ?>
    setTimeout(function() {
        const alert = document.querySelector('.alert-success');
        if (alert) {
            alert.style.display = 'none';
        }
    }, 5000);
<?php endif; ?>
</script>

</body>
</html>