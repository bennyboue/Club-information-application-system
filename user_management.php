<?php
session_start();

// Verify admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "ics_project");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";
$message_type = "";

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create new user
    if (isset($_POST['create_user'])) {
        $username = trim($_POST['username']);
        $surname = trim($_POST['surname']);
        $email = trim($_POST['email']);
        $school_id = trim($_POST['school_id']);
        $password = $_POST['password'];
        $role = $_POST['role'];

        if (empty($username) || empty($email) || empty($school_id) || empty($password)) {
            $message = "Please fill in all required fields.";
            $message_type = "error";
        } else {
            // Check for duplicates
            $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE username = ? OR email = ? OR school_id = ?");
            $check_stmt->bind_param("sss", $username, $email, $school_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                $message = "Username, email, or school ID already exists.";
                $message_type = "error";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, surname, email, school_id, password, role) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $username, $surname, $email, $school_id, $hashed_password, $role);
                
                if ($stmt->execute()) {
                    $message = "User created successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error creating user: " . $stmt->error;
                    $message_type = "error";
                }
            }
        }
    }

    // Update user role
    if (isset($_POST['update_role'])) {
        $user_id = intval($_POST['user_id']);
        $new_role = $_POST['new_role'];
        
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $new_role, $user_id);
        
        if ($stmt->execute()) {
            $message = "User role updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating role: " . $stmt->error;
            $message_type = "error";
        }
    }

    // Delete user
    if (isset($_POST['delete_user'])) {
        $user_id = intval($_POST['user_id']);
        
        // Prevent deleting yourself
        if ($user_id == $_SESSION['user_id']) {
            $message = "You cannot delete your own account.";
            $message_type = "error";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                $message = "User deleted successfully!";
                $message_type = "success";
            } else {
                $message = "Error deleting user: " . $stmt->error;
                $message_type = "error";
            }
        }
    }
}

// Get all users with their club assignments
$users_result = $conn->query("
    SELECT u.*, c.name as club_name 
    FROM users u
    LEFT JOIN club_managers cm ON u.id = cm.user_id AND cm.status = 'active'
    LEFT JOIN clubs c ON cm.club_id = c.id
    ORDER BY u.role, u.username
");

if ($users_result === false) {
    die("Error fetching users: " . $conn->error);
}

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Management - Admin Panel</title>
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-users-cog"></i> User Management
        </div>
        <div class="nav-links">
            <a href="admin_dashboard.php" class="nav-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            <a href="home.php" class="nav-btn"><i class="fas fa-home"></i> Home</a>
            <div class="user-welcome">
                <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
            </div>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert <?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'error' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-grid">
            <!-- Create New User -->
            <div class="dashboard-section">
                <h2 class="section-title"><i class="fas fa-user-plus"></i> Create New User</h2>
                
                <form method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Username *</label>
                        <input type="text" name="username" class="form-control" required maxlength="50">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user-tag"></i> Surname</label>
                        <input type="text" name="surname" class="form-control" maxlength="100">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email *</label>
                        <input type="email" name="email" class="form-control" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> School ID *</label>
                        <input type="text" name="school_id" class="form-control" required maxlength="50">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password *</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user-cog"></i> Role</label>
                        <select name="role" class="form-control" required>
                            <option value="student">Student</option>
                            <option value="club_manager">Club Manager</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <button type="submit" name="create_user" class="btn">
                        <i class="fas fa-save"></i> Create User
                    </button>
                </form>
            </div>

            <!-- User Statistics -->
            <div class="dashboard-section">
                <h2 class="section-title"><i class="fas fa-chart-bar"></i> User Statistics</h2>
                
                <?php
                $users_result->data_seek(0);
                $stats = ['student' => 0, 'club_manager' => 0, 'admin' => 0];
                while ($user = $users_result->fetch_assoc()) {
                    if (isset($stats[$user['role']])) {
                        $stats[$user['role']]++;
                    }
                }
                $users_result->data_seek(0);
                ?>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
                        <div class="stat-number"><?php echo $stats['student']; ?></div>
                        <div class="stat-label">Students</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
                        <div class="stat-number"><?php echo $stats['club_manager']; ?></div>
                        <div class="stat-label">Club Managers</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-user-shield"></i></div>
                        <div class="stat-number"><?php echo $stats['admin']; ?></div>
                        <div class="stat-label">Admins</div>
                    </div>
                </div>

                <div class="admin-tools">
                    <a href="bulk_import.php" class="btn">
                        <i class="fas fa-file-import"></i> Bulk Import
                    </a>
                    <a href="export_users.php" class="btn btn-report">
                        <i class="fas fa-download"></i> Export Users
                    </a>
                </div>
            </div>
        </div>

        <!-- All Users List -->
        <div class="dashboard-section" style="margin-top: 2rem;">
            <h2 class="section-title"><i class="fas fa-list"></i> All Users</h2>
            
            <?php if ($users_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>School ID</th>
                                <th>Role</th>
                                <th>Club Assignment</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($user = $users_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                        <?php if (!empty($user['surname'])): ?>
                                            <div class="club-description"><?php echo htmlspecialchars($user['surname']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['school_id']); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo !empty($user['club_name']) ? htmlspecialchars($user['club_name']) : '<span class="no-admin">No assignment</span>'; ?>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    <td class="actions">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <select name="new_role" class="form-control" style="width: auto; display: inline-block; margin-right: 5px;">
                                                <option value="student" <?php echo $user['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                                                <option value="club_manager" <?php echo $user['role'] === 'club_manager' ? 'selected' : ''; ?>>Club Manager</option>
                                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            </select>
                                            <button type="submit" name="update_role" class="btn" style="padding: 0.5rem;">
                                                <i class="fas fa-save"></i>
                                            </button>
                                        </form>
                                        
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="delete_user" class="btn btn-danger" style="padding: 0.5rem;"
                                                        onclick="return confirm('Delete this user permanently?')">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">No users found.</div>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .role-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .role-student { background: #e6f3ff; color: #0066cc; }
        .role-club_manager { background: #fff2e6; color: #cc6600; }
        .role-admin { background: #ffe6e6; color: #cc0000; }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            font-weight: 500;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert.success {
            background-color: rgba(40, 167, 69, 0.9);
            color: white;
            border-left-color: #218838;
        }
        .alert.error {
            background-color: rgba(220, 53, 69, 0.9);
            color: white;
            border-left-color: #c82333;
        }
    </style>
</body>
</html>