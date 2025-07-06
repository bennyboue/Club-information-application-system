<?php
session_start();
require __DIR__ . '/vendor/autoload.php';

// Database connection
$mysqli = new mysqli("localhost", "root", "", "ics_project");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Verify admin role
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user is admin
$user_id = $_SESSION['user_id'];
$role_check = $mysqli->prepare("SELECT role FROM users WHERE id = ?");
$role_check->bind_param("i", $user_id);
$role_check->execute();
$role_result = $role_check->get_result();

if ($role_result->num_rows === 0 || $role_result->fetch_assoc()['role'] !== 'admin') {
    header("Location: home.php");
    exit();
}

$message = "";
$message_type = "";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create new club
    if (isset($_POST['create_club'])) {
        $name = trim($_POST['club_name']);
        $initials = trim($_POST['club_initials']);
        $description = trim($_POST['club_description']);
        $patron_id = intval($_POST['club_patron']);

        // Validation
        if (empty($name) || empty($initials) || $patron_id <= 0) {
            $message = "Please fill in all required fields.";
            $message_type = "error";
        } else {
            $mysqli->begin_transaction();
            try {
                // Check if club name or initials already exist
                $check_stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM clubs WHERE name = ? OR initials = ?");
                $check_stmt->bind_param("ss", $name, $initials);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                $row = $result->fetch_assoc();
                
                if ($row['count'] > 0) {
                    throw new Exception("Club name or initials already exists.");
                }

                // Create club
                $stmt = $mysqli->prepare("INSERT INTO clubs (name, initials, description, patron_id) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sssi", $name, $initials, $description, $patron_id);
                $stmt->execute();
                $club_id = $mysqli->insert_id;
                
                // Create club manager record
                $manager_stmt = $mysqli->prepare("INSERT INTO club_managers (user_id, club_id, is_patron) VALUES (?, ?, 1)");
                $manager_stmt->bind_param("ii", $patron_id, $club_id);
                $manager_stmt->execute();

                // Update user role to club_manager if not already
                $role_update = $mysqli->prepare("UPDATE users SET role = 'club_manager' WHERE id = ? AND role = 'student'");
                $role_update->bind_param("i", $patron_id);
                $role_update->execute();

                $mysqli->commit();
                $message = "Club created successfully!";
                $message_type = "success";
            } catch (Exception $e) {
                $mysqli->rollback();
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
            $mysqli->begin_transaction();
            try {
                // Delete club manager assignments first
                $manager_stmt = $mysqli->prepare("DELETE FROM club_managers WHERE club_id = ?");
                $manager_stmt->bind_param("i", $club_id);
                $manager_stmt->execute();
                
                // Delete the club
                $club_stmt = $mysqli->prepare("DELETE FROM clubs WHERE id = ?");
                $club_stmt->bind_param("i", $club_id);
                $club_stmt->execute();
                
                $mysqli->commit();
                $message = "Club deleted successfully!";
                $message_type = "success";
            } catch (Exception $e) {
                $mysqli->rollback();
                $message = "Error deleting club: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
}

// Get all clubs with their patrons
$clubs_result = $mysqli->query("
    SELECT c.*, u.username AS patron_name 
    FROM clubs c
    LEFT JOIN users u ON c.patron_id = u.id
    ORDER BY c.name
");

// Get potential club patrons (club managers)
$patrons_result = $mysqli->query("
    SELECT u.id, u.username 
    FROM users u 
    WHERE u.role = 'club_manager'
    ORDER BY u.username
");

// Get club statistics
function getStat($mysqli, $query) {
    $result = $mysqli->query($query);
    if ($result === false) {
        error_log("Query failed: " . $query . " - " . $mysqli->error);
        return 0;
    }
    $row = $result->fetch_assoc();
    return $row['count'] ?? 0;
}

$stats = [
    'total_clubs' => getStat($mysqli, "SELECT COUNT(*) as count FROM clubs"),
    'total_members' => getStat($mysqli, "SELECT COUNT(*) as count FROM memberships WHERE status = 'approved'"),
    'active_events' => getStat($mysqli, "SELECT COUNT(*) as count FROM events"),
    'pending_requests' => getStat($mysqli, "SELECT COUNT(*) as count FROM membership_requests WHERE status = 'pending'")
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, rgb(169, 153, 136) 0%, rgb(237, 222, 203) 100%);
            min-height: 100vh;
            line-height: 1.6;
            padding: 20px;
        }

        .navbar {
            background: linear-gradient(135deg, rgb(150, 85, 10) 0%, rgb(209, 120, 25) 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            margin-bottom: 30px;
        }

        .navbar-brand {
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            background: linear-gradient(45deg, rgb(237, 222, 203), rgb(169, 153, 136));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .nav-btn {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            padding: 12px 20px;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 500;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .nav-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .user-welcome {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.1);
            padding: 10px 16px;
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .alert {
            padding: 18px 24px;
            border-radius: 16px;
            margin-bottom: 30px;
            font-weight: 600;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid;
            display: flex;
            align-items: center;
            gap: 12px;
            backdrop-filter: blur(10px);
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert.success {
            background: linear-gradient(135deg, rgba(209, 120, 25, 0.9), rgba(150, 85, 10, 0.9));
            color: white;
            border-color: rgba(209, 120, 25, 0.3);
        }

        .alert.error {
            background: linear-gradient(135deg, rgba(150, 85, 10, 0.9), rgba(123, 71, 14, 0.9));
            color: white;
            border-color: rgba(150, 85, 10, 0.3);
        }

        .alert.warning {
            background: linear-gradient(135deg, rgba(240, 129, 12, 0.9), rgba(209, 120, 25, 0.9));
            color: white;
            border-color: rgba(240, 129, 12, 0.3);
        }

        .dashboard-section {
            background: rgba(237, 222, 203, 0.95);
            border-radius: 24px;
            padding: 35px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(209, 120, 25, 0.2);
            margin-bottom: 30px;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .section-title {
            color: rgb(123, 71, 14);
            margin-bottom: 30px;
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .section-title i {
            color: rgb(209, 120, 25);
            background: linear-gradient(135deg, rgb(209, 120, 25), rgb(150, 85, 10));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .form-group {
            margin-bottom: 24px;
        }

        label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: rgb(123, 71, 14);
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-control, input, select, textarea {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid rgb(169, 153, 136);
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
        }

        .form-control:focus, input:focus, select:focus, textarea:focus {
            border-color: rgb(209, 120, 25);
            outline: none;
            box-shadow: 0 0 0 4px rgba(209, 120, 25, 0.1);
            background: rgba(255, 255, 255, 0.95);
        }

        .btn {
            display: inline-block;
            background: linear-gradient(135deg, rgb(209, 120, 25) 0%, rgb(150, 85, 10) 100%);
            color: white;
            border: none;
            padding: 16px 32px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: center;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(209, 120, 25, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, rgb(150, 85, 10) 0%, rgb(123, 71, 14) 100%);
        }

        .btn-danger:hover {
            box-shadow: 0 10px 30px rgba(150, 85, 10, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, rgb(169, 153, 136) 0%, rgb(149, 140, 129) 100%);
        }

        .btn-secondary:hover {
            box-shadow: 0 10px 30px rgba(169, 153, 136, 0.4);
        }

        .btn-report {
            background: linear-gradient(135deg, rgb(209, 120, 25) 0%, rgb(240, 129, 12) 100%);
        }

        .btn-report:hover {
            box-shadow: 0 10px 30px rgba(209, 120, 25, 0.4);
        }

        .btn-edit {
            background: linear-gradient(135deg, rgb(240, 129, 12) 0%, rgb(245, 131, 9) 100%);
        }

        .btn-edit:hover {
            box-shadow: 0 10px 30px rgba(240, 129, 12, 0.4);
        }

        .table-responsive {
            overflow-x: auto;
            margin-top: 25px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(237, 222, 203, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            overflow: hidden;
        }

        th {
            background: linear-gradient(135deg, rgb(237, 222, 203), rgb(169, 153, 136));
            padding: 20px 24px;
            text-align: left;
            font-weight: 700;
            color: rgb(123, 71, 14);
            border-bottom: 2px solid rgb(209, 120, 25);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 20px 24px;
            border-bottom: 1px solid rgb(209, 120, 25);
            vertical-align: top;
        }

        tr:hover {
            background: linear-gradient(135deg, rgba(209, 120, 25, 0.05), rgba(150, 85, 10, 0.05));
        }

        .club-description {
            color: rgb(150, 85, 10);
            font-size: 14px;
            margin-top: 6px;
            line-height: 1.5;
        }

        .no-admin {
            color: rgb(123, 71, 14);
            font-style: italic;
            font-weight: 500;
        }

        .no-data {
            background: linear-gradient(135deg, rgb(237, 222, 203), rgb(169, 153, 136));
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            color: rgb(150, 85, 10);
            font-style: italic;
            font-size: 16px;
            border: 2px dashed rgb(209, 120, 25);
        }

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .view-btn {
            background: linear-gradient(135deg, rgba(237, 222, 203, 0.8), rgba(169, 153, 136, 0.8));
            color: rgb(123, 71, 14);
        }

        .view-btn:hover {
            background: linear-gradient(135deg, rgba(169, 153, 136, 0.8), rgba(149, 140, 129, 0.8));
            transform: translateY(-1px);
        }

        .delete-btn {
            background: linear-gradient(135deg, rgba(150, 85, 10, 0.2), rgba(123, 71, 14, 0.2));
            color: rgb(123, 71, 14);
        }

        .delete-btn:hover {
            background: linear-gradient(135deg, rgba(150, 85, 10, 0.4), rgba(123, 71, 14, 0.4));
            transform: translateY(-1px);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: rgba(237, 222, 203, 0.98);
            border-radius: 24px;
            padding: 35px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(209, 120, 25, 0.3);
            animation: fadeInUp 0.4s ease-out;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .close-modal {
            font-size: 28px;
            cursor: pointer;
            color: rgb(123, 71, 14);
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            color: rgb(209, 120, 25);
            transform: scale(1.1);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: rgba(237, 222, 203, 0.95);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(209, 120, 25, 0.2);
            overflow: hidden;
            animation: fadeInUp 0.4s ease-out;
        }

        .stat-card:nth-child(2) {
            animation-delay: 0.1s;
        }

        .stat-card:nth-child(3) {
            animation-delay: 0.2s;
        }

        .stat-card:nth-child(4) {
            animation-delay: 0.3s;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, rgb(209, 120, 25), rgb(150, 85, 10));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .stat-card:hover::before {
            transform: scaleX(1);
        }

        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 60px rgba(209, 120, 25, 0.3);
        }

        .stat-card:hover .stat-icon {
            color: rgb(209, 120, 25);
            transform: scale(1.1);
        }

        .stat-card:hover .stat-number {
            color: rgb(150, 85, 10);
        }

        .stat-icon {
            font-size: 42px;
            color: rgb(209, 120, 25);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .stat-number {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 12px;
            color: rgb(123, 71, 14);
            transition: color 0.3s ease;
        }

        .stat-label {
            color: rgb(150, 85, 10);
            font-size: 16px;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .form-options {
                flex-direction: column;
            }

            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <div class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-star"></i> Club Management System
        </div>
        <div class="nav-links">
            <div class="user-welcome">
                <i class="fas fa-user-shield"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
            </div>
            <a href="admin_dashboard.php" class="nav-btn"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="logout.php" class="nav-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert <?= $message_type ?>">
                <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : ($message_type === 'error' ? 'exclamation-circle' : 'info-circle') ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-star"></i></div>
                <div class="stat-number"><?= $stats['total_clubs'] ?></div>
                <div class="stat-label">Active Clubs</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?= $stats['total_members'] ?></div>
                <div class="stat-label">Total Members</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                <div class="stat-number"><?= $stats['active_events'] ?></div>
                <div class="stat-label">Upcoming Events</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?= $stats['pending_requests'] ?></div>
                <div class="stat-label">Pending Requests</div>
            </div>
        </div>

        <!-- Create Club Form -->
        <div class="dashboard-section">
            <h2 class="section-title"><i class="fas fa-plus-circle"></i> Create New Club</h2>
            
            <form method="POST">
                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px;">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Club Name</label>
                        <input type="text" name="club_name" class="form-control" required placeholder="Enter club name">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-hashtag"></i> Club Initials</label>
                        <input type="text" name="club_initials" class="form-control" required placeholder="2-4 letters" maxlength="4">
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Description</label>
                    <textarea name="club_description" class="form-control" rows="3" placeholder="Enter club description"></textarea>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-user-tie"></i> Assign Patron</label>
                    <select name="club_patron" class="form-control" required>
                        <option value="">-- Select a Patron --</option>
                        <?php if ($patrons_result->num_rows > 0): ?>
                            <?php while($patron = $patrons_result->fetch_assoc()): ?>
                                <option value="<?= $patron['id'] ?>">
                                    <?= htmlspecialchars($patron['username']) ?>
                                </option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option value="" disabled>No club managers available</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <button type="submit" name="create_club" class="btn">
                    <i class="fas fa-save"></i> Create Club
                </button>
            </form>
        </div>

        <!-- Existing Clubs -->
        <div class="dashboard-section">
            <h2 class="section-title"><i class="fas fa-list"></i> Existing Clubs</h2>
            
            <?php if ($clubs_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Code</th>
                                <th>Patron</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($club = $clubs_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($club['name']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($club['initials']) ?></td>
                                    <td>
                                        <?= $club['patron_name'] ? htmlspecialchars($club['patron_name']) : '<span class="no-admin">Not assigned</span>' ?>
                                    </td>
                                    <td>
                                        <div class="club-description"><?= $club['description'] ? htmlspecialchars($club['description']) : 'No description' ?></div>
                                    </td>
                                    <td class="actions">
                                        <a href="edit_club.php?id=<?= $club['id'] ?>" class="btn btn-edit">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="club_id" value="<?= $club['id'] ?>">
                                            <button type="submit" name="delete_club" class="btn btn-danger" 
                                                    onclick="return confirm('Permanently delete this club and all its data?')">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </button>
                                        </form>
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
    </div>

    <script>
        // Function to confirm deletion
        function confirmDelete() {
            return confirm('Are you sure you want to delete this club? This action cannot be undone.');
        }
    </script>
</body>
</html>