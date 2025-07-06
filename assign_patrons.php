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

// Check if is_patron column exists
$column_check = $conn->query("SHOW COLUMNS FROM club_managers LIKE 'is_patron'");
$has_patron_column = ($column_check->num_rows > 0);

$message = "";
$message_type = "";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_patron'])) {
        $patron_id = intval($_POST['patron_id']);
        $club_id = intval($_POST['club_id']);

        // Check if assignment already exists
        $check_stmt = $conn->prepare("SELECT id FROM club_managers WHERE user_id = ? AND club_id = ?");
        $check_stmt->bind_param("ii", $patron_id, $club_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $message = "This patron is already assigned to this club!";
            $message_type = "error";
        } else {
            // Create new manager record (with patron flag if column exists)
            $sql = $has_patron_column 
                ? "INSERT INTO club_managers (user_id, club_id, is_patron, status) VALUES (?, ?, 1, 'active')"
                : "INSERT INTO club_managers (user_id, club_id, status) VALUES (?, ?, 'active')";
            
            $insert_stmt = $conn->prepare($sql);
            $insert_stmt->bind_param("ii", $patron_id, $club_id);
            
            if ($insert_stmt->execute()) {
                // Update user role if needed
                $update_role = $conn->prepare("UPDATE users SET role = 'club_manager' WHERE id = ? AND role != 'admin'");
                $update_role->bind_param("i", $patron_id);
                $update_role->execute();
                
                $message = "Patron assigned to club successfully!";
                $message_type = "success";
            } else {
                $message = "Error assigning patron: " . $conn->error;
                $message_type = "error";
            }
        }
    }

    if (isset($_POST['unassign_patron'])) {
        $assignment_id = intval($_POST['assignment_id']);
        
        $delete_stmt = $conn->prepare("DELETE FROM club_managers WHERE id = ?");
        $delete_stmt->bind_param("i", $assignment_id);
        
        if ($delete_stmt->execute()) {
            $message = "Patron unassigned from club successfully!";
            $message_type = "success";
        } else {
            $message = "Error unassigning patron: " . $conn->error;
            $message_type = "error";
        }
    }
}

// Get all clubs
$clubs = $conn->query("SELECT id, name, initials FROM clubs ORDER BY name");
if ($clubs === false) {
    die("Error fetching clubs: " . $conn->error);
}

// Get all patrons (club_managers)
$patrons_query = $has_patron_column 
    ? "SELECT u.id, u.username, 
              GROUP_CONCAT(c.name SEPARATOR ', ') as assigned_clubs,
              GROUP_CONCAT(cm.id SEPARATOR ',') as assignment_ids
       FROM users u
       JOIN club_managers cm ON u.id = cm.user_id
       JOIN clubs c ON cm.club_id = c.id
       WHERE cm.is_patron = 1
       GROUP BY u.id
       ORDER BY u.username"
    : "SELECT u.id, u.username, 
              GROUP_CONCAT(c.name SEPARATOR ', ') as assigned_clubs,
              GROUP_CONCAT(cm.id SEPARATOR ',') as assignment_ids
       FROM users u
       JOIN club_managers cm ON u.id = cm.user_id
       JOIN clubs c ON cm.club_id = c.id
       GROUP BY u.id
       ORDER BY u.username";

$patrons = $conn->query($patrons_query);
if ($patrons === false) {
    die("Error fetching patrons: " . $conn->error);
}

// Get available patrons (users with club_manager role not assigned as patrons)
$available_patrons_query = $has_patron_column
    ? "SELECT u.id, u.username 
       FROM users u
       WHERE u.role = 'club_manager'
         AND u.id NOT IN (
             SELECT user_id FROM club_managers WHERE is_patron = 1
         )
       ORDER BY u.username"
    : "SELECT u.id, u.username 
       FROM users u
       WHERE u.role = 'club_manager'
         AND u.id NOT IN (
             SELECT user_id FROM club_managers
         )
       ORDER BY u.username";

$available_patrons = $conn->query($available_patrons_query);
if ($available_patrons === false) {
    die("Error fetching available patrons: " . $conn->error);
}

$conn->close();
?>


<!DOCTYPE html>
<html>
<head>
    <title>Assign Club Patrons</title>
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .assignment-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .assignment-form {
            background-color: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            border: 3px solid rgb(209, 120, 25);
        }
        
        .form-header {
            color: rgb(209, 120, 25);
            font-size: 1.3rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgb(237, 222, 203);
        }
        
        .club-badge {
            display: inline-block;
            background-color: rgb(209, 120, 25);
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        .unassign-form {
            display: inline;
            margin-right: 5px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }
        
        @media (max-width: 768px) {
            .assignment-section {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-cogs"></i> School Club System - Assign Patrons
        </div>
        <div class="nav-links">
            <div class="user-welcome">
                <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
            </div>
            <a href="admin_dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert <?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <h1 class="section-title"><i class="fas fa-user-tie"></i> Assign Patrons to Clubs</h1>
        
        <div class="assignment-section">
            <!-- Assign Patron Form -->
            <div class="assignment-form">
                <h2 class="form-header"><i class="fas fa-link"></i> Assign New Patron</h2>
                <form method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Select Patron</label>
                        <select name="patron_id" class="form-control" required>
                            <?php if ($available_patrons->num_rows > 0): ?>
                                <?php while($patron = $available_patrons->fetch_assoc()): ?>
                                    <option value="<?php echo $patron['id']; ?>">
                                        <?php echo htmlspecialchars($patron['username']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <option value="" disabled>No available patrons</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-club"></i> Select Club</label>
                        <select name="club_id" class="form-control" required>
                            <?php 
                            // Reset clubs pointer
                            $clubs->data_seek(0); 
                            ?>
                            <?php if ($clubs->num_rows > 0): ?>
                                <?php while($club = $clubs->fetch_assoc()): ?>
                                    <option value="<?php echo $club['id']; ?>">
                                        <?php echo htmlspecialchars($club['name']); ?> (<?php echo htmlspecialchars($club['initials']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <option value="" disabled>No clubs available</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <button type="submit" name="assign_patron" class="btn" <?php echo ($available_patrons->num_rows == 0 || $clubs->num_rows == 0) ? 'disabled' : ''; ?>>
                        <i class="fas fa-user-plus"></i> Assign Patron
                    </button>
                </form>
            </div>
            
            <!-- Current Assignments -->
            <div class="assignment-form">
                <h2 class="form-header"><i class="fas fa-list"></i> Current Patron Assignments</h2>
                
                <?php if ($patrons->num_rows > 0): ?>
                    <div class="patrons-list">
                        <?php while($patron = $patrons->fetch_assoc()): ?>
                            <div class="patron-item" style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                                <h3 style="margin-bottom: 10px;">
                                    <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($patron['username']); ?>
                                </h3>
                                
                                <?php if (!empty($patron['assigned_clubs'])): ?>
                                    <div style="margin-bottom: 10px;">
                                        <strong>Patron For Clubs:</strong>
                                        <?php 
                                            $assigned_clubs = explode(', ', $patron['assigned_clubs']);
                                            $assignment_ids = explode(',', $patron['assignment_ids']);
                                            
                                            foreach ($assigned_clubs as $index => $club_name) {
                                                echo '<span class="club-badge">' . htmlspecialchars($club_name) . '</span>';
                                            }
                                        ?>
                                    </div>
                                    
                                    <div>
                                        <strong>Unassign:</strong>
                                        <?php foreach ($assignment_ids as $index => $assignment_id): ?>
                                            <form method="POST" class="unassign-form">
                                                <input type="hidden" name="assignment_id" value="<?php echo $assignment_id; ?>">
                                                <button type="submit" name="unassign_patron" class="btn btn-danger btn-sm" 
                                                        onclick="return confirm('Unassign this patron from the club?')">
                                                    <i class="fas fa-unlink"></i> <?php echo htmlspecialchars(explode(', ', $patron['assigned_clubs'])[$index]); ?>
                                                </button>
                                            </form>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div style="color: #666; font-style: italic;">Not assigned to any clubs</div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data">No patron assignments found</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>