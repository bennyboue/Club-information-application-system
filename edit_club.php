<?php
require_once 'auth_check.php';

$club_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$conn = new mysqli("localhost", "root", "", "ics_project");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
$message = "";
$message_type = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['club_name']);
    $initials = trim($_POST['club_initials']);
    $description = trim($_POST['club_description']);
    
    if (empty($name) || empty($initials)) {
        $message = "Club name and initials are required";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare("UPDATE clubs SET name = ?, initials = ?, description = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $initials, $description, $club_id);
        
        if ($stmt->execute()) {
            $message = "Club updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating club: " . $stmt->error;
            $message_type = "error";
        }
    }
}

// Get club details
$stmt = $conn->prepare("SELECT * FROM clubs WHERE id = ?");
$stmt->bind_param("i", $club_id);
$stmt->execute();
$club = $stmt->get_result()->fetch_assoc();

// Get current manager
$manager_stmt = $conn->prepare("
    SELECT u.id, u.username 
    FROM club_managers cm
    JOIN users u ON cm.user_id = u.id
    WHERE cm.club_id = ? AND cm.status = 'active'
");
$manager_stmt->bind_param("i", $club_id);
$manager_stmt->execute();
$current_manager = $manager_stmt->get_result()->fetch_assoc();

// Get potential managers
$admins_result = $conn->query("
    SELECT u.id, u.username 
    FROM users u 
    LEFT JOIN club_managers cm ON u.id = cm.user_id AND cm.status = 'active'
    WHERE u.role IN ('student', 'club_manager') 
    AND (cm.user_id IS NULL OR cm.user_id = " . ($current_manager ? $current_manager['id'] : 'NULL') . ")
    ORDER BY u.username
");

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Club</title>
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-cogs"></i> School Club System - Edit Club
        </div>
        <div class="nav-links">
            <a href="admin_dashboard.php"><i class="fas fa-home"></i> Home</a>
            <div class="user-welcome">
                <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
            </div>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert <?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <h1 class="section-title">Edit Club: <?php echo htmlspecialchars($club['name']); ?></h1>
        
        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-tag"></i> Club Name</label>
                <input type="text" name="club_name" class="form-control" required 
                       value="<?php echo htmlspecialchars($club['name']); ?>">
            </div>
            <div class="form-group">
                <label><i class="fas fa-hashtag"></i> Club Initials</label>
                <input type="text" name="club_initials" class="form-control" required 
                       value="<?php echo htmlspecialchars($club['initials']); ?>">
            </div>
            <div class="form-group">
                <label><i class="fas fa-align-left"></i> Description</label>
                <textarea name="club_description" class="form-control" rows="4"><?php 
                    echo htmlspecialchars($club['description']); 
                ?></textarea>
            </div>
            <div class="form-group">
                <label><i class="fas fa-user-tie"></i> Club Manager</label>
                <select name="club_admin" class="form-control">
                    <option value="">-- Select a Manager --</option>
                    <?php if ($admins_result->num_rows > 0): ?>
                        <?php while($admin = $admins_result->fetch_assoc()): ?>
                            <option value="<?php echo $admin['id']; ?>" 
                                <?php if (isset($current_manager['id']) && $current_manager['id'] == $admin['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($admin['username']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <option value="" disabled>No available managers</option>
                    <?php endif; ?>
                </select>
                <?php if (isset($current_manager)): ?>
                    <p class="current-manager">Current Manager: <?php echo htmlspecialchars($current_manager['username']); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="action-buttons">
                <button type="submit" class="btn">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="admin_dashboard.php" class="btn btn-cancel">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</body>
</html>