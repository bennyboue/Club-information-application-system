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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create new club
    if (isset($_POST['create_club'])) {
        $name = trim($_POST['club_name']);
        $initials = trim($_POST['club_initials']);
        $description = trim($_POST['club_description']);
        $admin_id = intval($_POST['club_admin']);

        $conn->begin_transaction();
        try {
            // Create club
            $stmt = $conn->prepare("INSERT INTO clubs (name, initials, description) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $initials, $description);
            $stmt->execute();
            $club_id = $conn->insert_id;

            // Assign admin
            $stmt = $conn->prepare("UPDATE users SET school_id = CONCAT(school_id, '-', ?) WHERE id = ?");
            $stmt->bind_param("si", $initials, $admin_id);
            $stmt->execute();

            $conn->commit();
            $message = "Club created successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error: " . $e->getMessage();
        }
    }

    // Delete club
    if (isset($_POST['delete_club'])) {
        $club_id = intval($_POST['club_id']);
        $conn->query("DELETE FROM clubs WHERE id = $club_id");
        $message = $conn->affected_rows > 0 ? "Club deleted!" : "Error deleting club";
    }

    // System announcement
    if (isset($_POST['system_announcement'])) {
        $content = trim($_POST['announcement_content']);
        $stmt = $conn->prepare("INSERT INTO system_announcements (content, admin_id) VALUES (?, ?)");
        $stmt->bind_param("si", $content, $_SESSION['user_id']);
        $stmt->execute();
        $message = $stmt->affected_rows > 0 ? "System announcement posted!" : "Error posting announcement";
    }
}

// Get all clubs
$clubs = $conn->query("SELECT id, name, initials FROM clubs ORDER BY name");

// Get potential club admins (users with club_patron role)
$admins = $conn->query("SELECT id, username FROM users WHERE role = 'club_patron' AND school_id NOT LIKE '%-%'");

// Get recent system announcements
$announcements = $conn->query("
    SELECT a.content, a.created_at, u.username 
    FROM system_announcements a
    JOIN users u ON a.admin_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 5
");

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>System Admin Dashboard</title>
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>
    <div class="navbar">
        <div class="navbar-brand">School Club System - Admin Panel</div>
        <div class="nav-links">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="dashboard-grid">
            <!-- Clubs Management -->
            <div class="dashboard-section">
                <h2 class="section-title">Clubs Management</h2>
                
                <h3>Create New Club</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Club Name</label>
                        <input type="text" name="club_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Club Initials (2-4 letters)</label>
                        <input type="text" name="club_initials" class="form-control" maxlength="4" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="club_description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Assign Admin</label>
                        <select name="club_admin" class="form-control" required>
                            <?php while($admin = $admins->fetch_assoc()): ?>
                                <option value="<?php echo $admin['id']; ?>">
                                    <?php echo htmlspecialchars($admin['username']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" name="create_club" class="btn">Create Club</button>
                </form>

                <h3 style="margin-top: 30px;">Existing Clubs</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($club = $clubs->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($club['name']); ?></td>
                                <td><?php echo htmlspecialchars($club['initials']); ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="club_id" value="<?php echo $club['id']; ?>">
                                        <button type="submit" name="delete_club" class="btn btn-danger"
                                                onclick="return confirm('Permanently delete this club and all its data?')">
                                            Delete
                                        </button>
                                    </form>
                                    <a href="club_report.php?club_id=<?php echo $club['id']; ?>" 
                                       class="btn btn-report">
                                        Report
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- System Tools -->
            <div class="dashboard-section">
                <h2 class="section-title">System Administration</h2>
                
                <div class="admin-tools">
                    <a href="user_management.php" class="btn">Manage Users</a>
                    <a href="system_report.php" class="btn btn-report">Generate System Report</a>
                </div>

                <h3 style="margin-top: 30px;">System Announcements</h3>
                <div class="announcements-list">
                    <?php if ($announcements->num_rows > 0): ?>
                        <?php while($announcement = $announcements->fetch_assoc()): ?>
                            <div class="announcement">
                                <div class="announcement-content">
                                    <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                </div>
                                <div class="announcement-meta">
                                    Posted by <?php echo htmlspecialchars($announcement['username']); ?> 
                                    on <?php echo date('M j, Y', strtotime($announcement['created_at'])); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-data">No system announcements</div>
                    <?php endif; ?>
                </div>

                <h3>Post System Announcement</h3>
                <form method="POST">
                    <div class="form-group">
                        <textarea name="announcement_content" class="form-control" rows="3" required
                                  placeholder="Important notice for all users..."></textarea>
                    </div>
                    <button type="submit" name="system_announcement" class="btn">Post Announcement</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>