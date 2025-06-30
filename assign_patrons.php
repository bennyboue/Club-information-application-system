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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_patron'])) {
        $patron_id = intval($_POST['patron_id']);
        $club_id = intval($_POST['club_id']);

        // Get club initials
        $club_stmt = $conn->prepare("SELECT initials FROM clubs WHERE id = ?");
        $club_stmt->bind_param("i", $club_id);
        $club_stmt->execute();
        $club_result = $club_stmt->get_result();
        
        if ($club_result->num_rows > 0) {
            $club = $club_result->fetch_assoc();
            $initials = $club['initials'];

            // Update patron's school_id with club initials
            $update_stmt = $conn->prepare("UPDATE users SET school_id = CONCAT(school_id, '-', ?) WHERE id = ?");
            $update_stmt->bind_param("si", $initials, $patron_id);
            
            if ($update_stmt->execute()) {
                $message = "Patron assigned to club successfully!";
                $message_type = "success";
            } else {
                $message = "Error assigning patron: " . $conn->error;
                $message_type = "error";
            }
        } else {
            $message = "Club not found!";
            $message_type = "error";
        }
    }

    if (isset($_POST['unassign_patron'])) {
        $patron_id = intval($_POST['patron_id']);
        $initials = trim($_POST['club_initials']);

        // Remove club initials from patron's school_id
        $update_stmt = $conn->prepare("UPDATE users SET school_id = REPLACE(school_id, CONCAT('-', ?), '') WHERE id = ?");
        $update_stmt->bind_param("si", $initials, $patron_id);
        
        if ($update_stmt->execute()) {
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

// Get all patrons (club_patron role users)
$patrons = $conn->query("
    SELECT u.id, u.username, u.school_id, 
           GROUP_CONCAT(c.name SEPARATOR ', ') as assigned_clubs
    FROM users u
    LEFT JOIN clubs c ON u.school_id LIKE CONCAT('%-', c.initials)
    WHERE u.role = 'club_patron'
    GROUP BY u.id
    ORDER BY u.username
");
if ($patrons === false) {
    die("Error fetching patrons: " . $conn->error);
}

// Get available patrons (not assigned to any club)
$available_patrons = $conn->query("
    SELECT id, username 
    FROM users 
    WHERE role = 'club_patron' AND school_id NOT LIKE '%-%'
    ORDER BY username
");
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
                <h2 class="form-header"><i class="fas fa-list"></i> Current Assignments</h2>
                
                <?php if ($patrons->num_rows > 0): ?>
                    <div class="patrons-list">
                        <?php while($patron = $patrons->fetch_assoc()): ?>
                            <div class="patron-item" style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                                <h3 style="margin-bottom: 10px;">
                                    <?php echo htmlspecialchars($patron['username']); ?>
                                    <small style="font-size: 0.8rem; color: #666;">(ID: <?php echo htmlspecialchars($patron['school_id']); ?>)</small>
                                </h3>
                                
                                <?php if (!empty($patron['assigned_clubs'])): ?>
                                    <div style="margin-bottom: 10px;">
                                        <strong>Assigned Clubs:</strong>
                                        <?php 
                                            $assigned_clubs = explode(', ', $patron['assigned_clubs']);
                                            foreach ($assigned_clubs as $club_name) {
                                                echo '<span class="club-badge">' . htmlspecialchars($club_name) . '</span>';
                                            }
                                        ?>
                                    </div>
                                    
                                    <?php 
                                        // Get club initials for unassign form
                                        $club_initials = [];
                                        if (preg_match_all('/-([A-Z]+)/', $patron['school_id'], $matches)) {
                                            $club_initials = $matches[1];
                                        }
                                    ?>
                                    
                                    <?php foreach ($club_initials as $initials): ?>
                                        <form method="POST" class="unassign-form">
                                            <input type="hidden" name="patron_id" value="<?php echo $patron['id']; ?>">
                                            <input type="hidden" name="club_initials" value="<?php echo htmlspecialchars($initials); ?>">
                                            <button type="submit" name="unassign_patron" class="btn btn-danger btn-sm" 
                                                    onclick="return confirm('Unassign this patron from the club?')">
                                                <i class="fas fa-unlink"></i> Unassign <?php echo htmlspecialchars($initials); ?>
                                            </button>
                                        </form>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div style="color: #666; font-style: italic;">Not assigned to any clubs</div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data">No patrons found</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>