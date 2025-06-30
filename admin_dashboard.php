<?php
session_start();

// Verify system admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "ics_project");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle admin actions
$message = "";
$message_type = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create new club
    if (isset($_POST['create_club'])) {
        $name = trim($_POST['club_name']);
        $initials = trim($_POST['club_initials']);
        $description = trim($_POST['club_description']);
        $admin_id = intval($_POST['club_admin']);

        // Validation
        if (empty($name) || empty($initials) || $admin_id <= 0) {
            $message = "Please fill in all required fields.";
            $message_type = "error";
        } else {
            $conn->begin_transaction();
            try {
                // Check if club name or initials already exist
                $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM clubs WHERE name = ? OR initials = ?");
                $check_stmt->bind_param("ss", $name, $initials);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                $row = $result->fetch_assoc();
                
                if ($row['count'] > 0) {
                    throw new Exception("Club name or initials already exists.");
                }

                // Create club
                $stmt = $conn->prepare("INSERT INTO clubs (name, initials, description, created_by) VALUES (?, ?, ?, ?)");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("sssi", $name, $initials, $description, $_SESSION['user_id']);
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }
                $club_id = $conn->insert_id;

                // Create club manager record instead of modifying school_id
                $manager_stmt = $conn->prepare("INSERT INTO club_managers (user_id, club_id) VALUES (?, ?)");
                if (!$manager_stmt) {
                    throw new Exception("Manager prepare failed: " . $conn->error);
                }
                $manager_stmt->bind_param("ii", $admin_id, $club_id);
                if (!$manager_stmt->execute()) {
                    throw new Exception("Manager execute failed: " . $manager_stmt->error);
                }

                // Update user role to club_manager if not already
                $role_stmt = $conn->prepare("UPDATE users SET role = 'club_manager' WHERE id = ? AND role != 'admin'");
                $role_stmt->bind_param("i", $admin_id);
                $role_stmt->execute();

                $conn->commit();
                $message = "Club created successfully!";
                $message_type = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Error: " . $e->getMessage();
                $message_type = "error";
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
            $conn->begin_transaction();
            try {
                // Delete club manager assignments first
                $manager_stmt = $conn->prepare("DELETE FROM club_managers WHERE club_id = ?");
                $manager_stmt->bind_param("i", $club_id);
                $manager_stmt->execute();
                
                // Delete the club
                $club_stmt = $conn->prepare("DELETE FROM clubs WHERE id = ?");
                $club_stmt->bind_param("i", $club_id);
                if (!$club_stmt->execute()) {
                    throw new Exception("Failed to delete club: " . $conn->error);
                }
                
                $conn->commit();
                $message = $club_stmt->affected_rows > 0 ? "Club deleted successfully!" : "No club found with that ID";
                $message_type = $club_stmt->affected_rows > 0 ? "success" : "warning";
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Error deleting club: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }

    // System announcement
    if (isset($_POST['system_announcement'])) {
        $content = trim($_POST['announcement_content']);
        
        if (empty($content)) {
            $message = "Announcement content cannot be empty.";
            $message_type = "error";
        } else {
            $stmt = $conn->prepare("INSERT INTO announcements (club_id, content, created_by) VALUES (0, ?, ?)");
            if ($stmt === false) {
                $message = "Prepare failed: " . $conn->error;
                $message_type = "error";
            } else {
                $stmt->bind_param("si", $content, $_SESSION['user_id']);
                if (!$stmt->execute()) {
                    $message = "Execute failed: " . $stmt->error;
                    $message_type = "error";
                } else {
                    $message = $stmt->affected_rows > 0 ? "System announcement posted!" : "Error posting announcement";
                    $message_type = $stmt->affected_rows > 0 ? "success" : "warning";
                }
            }
        }
    }
}

// Get all clubs with their managers
$clubs_result = $conn->query("
    SELECT c.*, u.username as creator_name, cm.user_id as manager_id, um.username as manager_name 
    FROM clubs c
    LEFT JOIN users u ON c.created_by = u.id
    LEFT JOIN club_managers cm ON c.id = cm.club_id AND cm.status = 'active'
    LEFT JOIN users um ON cm.user_id = um.id
    ORDER BY c.name
");
if ($clubs_result === false) {
    die("Error fetching clubs: " . $conn->error);
}

// Get potential club admins (users who are not already managing a club)
$admins_result = $conn->query("
    SELECT u.id, u.username 
    FROM users u 
    LEFT JOIN club_managers cm ON u.id = cm.user_id AND cm.status = 'active'
    WHERE u.role IN ('student', 'club_manager') 
    AND cm.user_id IS NULL
    ORDER BY u.username
");
if ($admins_result === false) {
    die("Error fetching admins: " . $conn->error);
}

// Get recent system announcements (club_id = 0 indicates system-wide announcements)
$announcements_result = $conn->query("
    SELECT a.content, a.created_at, u.username 
    FROM announcements a
    JOIN users u ON a.created_by = u.id
    WHERE a.club_id = 0
    ORDER BY a.created_at DESC
    LIMIT 5
");
if ($announcements_result === false) {
    die("Error fetching announcements: " . $conn->error);
}

// Get system statistics with error handling
function getStat($conn, $query) {
    $result = $conn->query($query);
    if ($result === false) {
        error_log("Query failed: " . $query . " - " . $conn->error);
        return 0;
    }
    $row = $result->fetch_assoc();
    return $row['count'] ?? 0;
}

$stats = [
    'total_clubs' => getStat($conn, "SELECT COUNT(*) as count FROM clubs"),
    'total_users' => getStat($conn, "SELECT COUNT(*) as count FROM users"),
    'active_events' => getStat($conn, "SELECT COUNT(*) as count FROM events WHERE event_date >= CURDATE()"),
    'pending_requests' => getStat($conn, "SELECT COUNT(*) as count FROM memberships WHERE status = 'pending'")
];

// Reset the admins result pointer for the form
$admins_result->data_seek(0);

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>System Admin Dashboard</title>
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
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
        .alert.warning {
            background-color: rgba(255, 193, 7, 0.9);
            color: #333;
            border-left-color: #ffc107;
        }
        .club-admin {
            font-size: 0.85rem;
            color: #666;
            font-style: italic;
            margin-top: 3px;
        }
        .no-admin {
            color: #dc3545;
            font-style: italic;
        }
        .club-admin-info {
            font-size: 0.85rem;
            margin-top: 5px;
        }
        .patron-info {
            color: #17a2b8;
            font-weight: bold;
        }
        .no-patron {
            color: #6c757d;
            font-style: italic;
        }
        .btn-sm {
            padding: 2px 5px;
            font-size: 12px;
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

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-star"></i></div>
                <div class="stat-number"><?php echo $stats['total_clubs']; ?></div>
                <div class="stat-label">Active Clubs</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                <div class="stat-number"><?php echo $stats['active_events']; ?></div>
                <div class="stat-label">Upcoming Events</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo $stats['pending_requests']; ?></div>
                <div class="stat-label">Pending Requests</div>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- Clubs Management -->
            <div class="dashboard-section">
                <h2 class="section-title"><i class="fas fa-club"></i> Clubs Management</h2>
                
                <h3><i class="fas fa-plus-circle"></i> Create New Club</h3>
                <form method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Club Name</label>
                        <input type="text" name="club_name" class="form-control" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-hashtag"></i> Club Initials (2-4 letters)</label>
                        <input type="text" name="club_initials" class="form-control" maxlength="10" required pattern="[A-Za-z]{2,4}" title="Please enter 2-4 letters only">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-align-left"></i> Description</label>
                        <textarea name="club_description" class="form-control" rows="3" maxlength="1000"></textarea>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user-tie"></i> Assign Manager</label>
                        <select name="club_admin" class="form-control" required>
                            <option value="">-- Select a Manager --</option>
                            <?php if ($admins_result->num_rows > 0): ?>
                                <?php while($admin = $admins_result->fetch_assoc()): ?>
                                    <option value="<?php echo $admin['id']; ?>">
                                        <?php echo htmlspecialchars($admin['username']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <option value="" disabled>No available users - all users are already managing clubs</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <button type="submit" name="create_club" class="btn" <?php echo $admins_result->num_rows == 0 ? 'disabled' : ''; ?>>
                        <i class="fas fa-save"></i> Create Club
                    </button>
                </form>

                <h3 style="margin-top: 30px;"><i class="fas fa-list"></i> Existing Clubs</h3>
                <?php if ($clubs_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Code</th>
                                    <th>Manager</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($club = $clubs_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($club['name']); ?></strong>
                                            <?php if (!empty($club['description'])): ?>
                                                <div class="club-description"><?php echo htmlspecialchars($club['description']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($club['initials']); ?></td>
                                        <td>
                                            <?php echo !empty($club['manager_name']) ? htmlspecialchars($club['manager_name']) : '<span class="no-admin">Not assigned</span>'; ?>
                                        </td>
                                        <td class="actions">
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="club_id" value="<?php echo $club['id']; ?>">
                                                <button type="submit" name="delete_club" class="btn btn-danger"
                                                        onclick="return confirm('Permanently delete this club and all its data?')">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </button>
                                            </form>
                                            <a href="club_report.php?club_id=<?php echo $club['id']; ?>" 
                                               class="btn btn-report">
                                                <i class="fas fa-chart-bar"></i> Report
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-data">No clubs found. Create your first club above.</div>
                <?php endif; ?>
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

                <h3 style="margin-top: 30px;"><i class="fas fa-bullhorn"></i> System Announcements</h3>
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

                <h3><i class="fas fa-edit"></i> Post System Announcement</h3>
                <form method="POST">
                    <div class="form-group">
                        <textarea name="announcement_content" class="form-control" rows="4" required
                                  placeholder="Important notice for all users..." maxlength="2000"></textarea>
                    </div>
                    <button type="submit" name="system_announcement" class="btn">
                        <i class="fas fa-paper-plane"></i> Post Announcement
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>