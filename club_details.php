<?php
session_start();

$conn = new mysqli("localhost", "root", "", "ics_project");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get club ID from URL
$club_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch club details
$club_stmt = $conn->prepare("SELECT c.*, u.username as creator_name FROM clubs c LEFT JOIN users u ON c.created_by = u.id WHERE c.id = ?");
$club_stmt->bind_param("i", $club_id);
$club_stmt->execute();
$club_result = $club_stmt->get_result();

if ($club_result->num_rows == 0) {
    header("Location: home.php");
    exit();
}

$club = $club_result->fetch_assoc();

// Fetch club events
$events_stmt = $conn->prepare("SELECT * FROM events WHERE club_id = ? ORDER BY event_date DESC");
$events_stmt->bind_param("i", $club_id);
$events_stmt->execute();
$events_result = $events_stmt->get_result();

// Fetch club members
$members_stmt = $conn->prepare("SELECT u.username, u.role, m.joined_at FROM memberships m JOIN users u ON m.user_id = u.id WHERE m.club_id = ? ORDER BY m.joined_at DESC");
$members_stmt->bind_param("i", $club_id);
$members_stmt->execute();
$members_result = $members_stmt->get_result();

// Check if current user is a member
$is_member = false;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
if ($user_id > 0) {
    $member_check = $conn->prepare("SELECT id FROM memberships WHERE user_id = ? AND club_id = ?");
    $member_check->bind_param("ii", $user_id, $club_id);
    $member_check->execute();
    $is_member = $member_check->get_result()->num_rows > 0;
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($club['name']); ?> - Club Details</title>
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
        }

        .navbar .logo {
            font-size: 18px;
            font-weight: bold;
            color: black;
        }

        .navbar a {
            background-color: rgb(209, 120, 25);
            color: #fff;
            padding: 10px 18px;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
            margin-left: 10px;
        }

        .navbar a:hover {
            background-color: rgb(150, 85, 10);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: bold;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .message.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .club-header {
            background-color: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .club-title {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .club-initials-badge {
            background-color: rgb(209, 120, 25);
            color: white;
            padding: 10px 15px;
            border-radius: 50%;
            font-weight: bold;
            font-size: 20px;
        }

        .club-name {
            font-size: 28px;
            color: rgb(209, 120, 25);
            margin: 0;
        }

        .club-meta {
            color: #666;
            margin-bottom: 15px;
        }

        .club-description {
            font-size: 16px;
            color: #333;
        }

        .action-buttons {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s ease;
            font-weight: bold;
        }

        .btn-primary {
            background-color: rgb(209, 120, 25);
            color: white;
        }

        .btn-primary:hover {
            background-color: rgb(150, 85, 10);
        }

        .btn-secondary {
            background-color: #666;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #555;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        .section {
            background-color: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            color: rgb(209, 120, 25);
            font-size: 20px;
            margin-bottom: 15px;
            border-bottom: 2px solid rgb(209, 120, 25);
            padding-bottom: 5px;
        }

        .event-item, .member-item {
            background-color: rgb(237, 222, 203);
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            border-left: 4px solid rgb(209, 120, 25);
        }

        .event-title {
            font-weight: bold;
            color: rgb(209, 120, 25);
            margin-bottom: 5px;
        }

        .event-date {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .member-name {
            font-weight: bold;
            color: rgb(209, 120, 25);
        }

        .member-role {
            background-color: rgb(209, 120, 25);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 10px;
        }

        .no-content {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 20px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .club-header {
                padding: 20px;
            }

            .club-title {
                flex-direction: column;
                text-align: center;
            }

            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="logo">
            <a href="home.php" style="color: black; text-decoration: none;">
                School Club Management System
            </a>
        </div>
        <div>
            <?php if (isset($_SESSION['username'])): ?>
                <span style="color: black; margin-right: 10px;">
                    Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!
                </span>
                <a href="home.php">Home</a>
                <a href="student_dashboard.php">Dashboard</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="home.php">Home</a>
                <a href="login.php">Login</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message <?php echo $_SESSION['message_type']; ?>">
                <?php 
                    echo htmlspecialchars($_SESSION['message']); 
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                ?>
            </div>
        <?php endif; ?>

        <div class="club-header">
            <div class="club-title">
                <div class="club-initials-badge"><?php echo htmlspecialchars($club['initials']); ?></div>
                <div>
                    <h1 class="club-name"><?php echo htmlspecialchars($club['name']); ?></h1>
                    <div class="club-meta">
                        Created by: <?php echo htmlspecialchars($club['creator_name'] ?? 'Unknown'); ?> | 
                        Founded: <?php echo date('F j, Y', strtotime($club['created_at'])); ?>
                    </div>
                </div>
            </div>
            
            <div class="club-description">
                <?php echo htmlspecialchars($club['description']); ?>
            </div>

            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="action-buttons">
                    <?php if ($is_member): ?>
                        <span class="btn btn-success">✓ You are a member</span>
                        <a href="leave_club.php?id=<?php echo $club_id; ?>" class="btn btn-secondary" 
                           onclick="return confirm('Are you sure you want to leave this club?')">Leave Club</a>
                    <?php else: ?>
                        <a href="join_club.php?id=<?php echo $club_id; ?>" class="btn btn-primary">Join Club</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="action-buttons">
                    <a href="login.php" class="btn btn-primary">Login to Join Club</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2 class="section-title">Upcoming Events</h2>
            <?php if ($events_result->num_rows > 0): ?>
                <?php while($event = $events_result->fetch_assoc()): ?>
                    <div class="event-item">
                        <div class="event-title"><?php echo htmlspecialchars($event['title']); ?></div>
                        <div class="event-date">
                            <?php echo $event['event_date'] ? date('F j, Y', strtotime($event['event_date'])) : 'Date TBD'; ?>
                        </div>
                        <div><?php echo htmlspecialchars($event['description']); ?></div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-content">No upcoming events</div>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2 class="section-title">Club Members (<?php echo $members_result->num_rows; ?>)</h2>
            <?php if ($members_result->num_rows > 0): ?>
                <?php while($member = $members_result->fetch_assoc()): ?>
                    <div class="member-item">
                        <span class="member-name"><?php echo htmlspecialchars($member['username']); ?></span>
                        <span class="member-role"><?php echo htmlspecialchars($member['role']); ?></span>
                        <span style="color: #666; font-size: 12px; margin-left: 10px;">
                            Joined: <?php echo date('M j, Y', strtotime($member['joined_at'])); ?>
                        </span>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-content">No members yet</div>
            <?php endif; ?>
        </div>
    </div>
</body>
<?php
session_start();

$conn = new mysqli("localhost", "root", "", "ics_project");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get club ID from URL
$club_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch club details
$club_stmt = $conn->prepare("SELECT c.*, u.username as creator_name FROM clubs c LEFT JOIN users u ON c.created_by = u.id WHERE c.id = ?");
$club_stmt->bind_param("i", $club_id);
$club_stmt->execute();
$club_result = $club_stmt->get_result();

if ($club_result->num_rows == 0) {
    header("Location: home.php");
    exit();
}

$club = $club_result->fetch_assoc();

// Fetch club events
$events_stmt = $conn->prepare("SELECT * FROM events WHERE club_id = ? ORDER BY event_date DESC");
$events_stmt->bind_param("i", $club_id);
$events_stmt->execute();
$events_result = $events_stmt->get_result();

// Fetch club members
$members_stmt = $conn->prepare("SELECT u.username, u.role, m.joined_at FROM memberships m JOIN users u ON m.user_id = u.id WHERE m.club_id = ? ORDER BY m.joined_at DESC");
$members_stmt->bind_param("i", $club_id);
$members_stmt->execute();
$members_result = $members_stmt->get_result();

// Check if current user is a member
$is_member = false;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
if ($user_id > 0) {
    $member_check = $conn->prepare("SELECT id FROM memberships WHERE user_id = ? AND club_id = ?");
    $member_check->bind_param("ii", $user_id, $club_id);
    $member_check->execute();
    $is_member = $member_check->get_result()->num_rows > 0;
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($club['name']); ?> - Club Details</title>
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
        }

        .navbar .logo {
            font-size: 18px;
            font-weight: bold;
            color: black;
        }

        .navbar a {
            background-color: rgb(209, 120, 25);
            color: #fff;
            padding: 10px 18px;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
            margin-left: 10px;
        }

        .navbar a:hover {
            background-color: rgb(150, 85, 10);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: bold;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .message.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .club-header {
            background-color: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .club-title {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .club-initials-badge {
            background-color: rgb(209, 120, 25);
            color: white;
            padding: 10px 15px;
            border-radius: 50%;
            font-weight: bold;
            font-size: 20px;
        }

        .club-name {
            font-size: 28px;
            color: rgb(209, 120, 25);
            margin: 0;
        }

        .club-meta {
            color: #666;
            margin-bottom: 15px;
        }

        .club-description {
            font-size: 16px;
            color: #333;
        }

        .action-buttons {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s ease;
            font-weight: bold;
        }

        .btn-primary {
            background-color: rgb(209, 120, 25);
            color: white;
        }

        .btn-primary:hover {
            background-color: rgb(150, 85, 10);
        }

        .btn-secondary {
            background-color: #666;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #555;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        .section {
            background-color: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            color: rgb(209, 120, 25);
            font-size: 20px;
            margin-bottom: 15px;
            border-bottom: 2px solid rgb(209, 120, 25);
            padding-bottom: 5px;
        }

        .event-item, .member-item {
            background-color: rgb(237, 222, 203);
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            border-left: 4px solid rgb(209, 120, 25);
        }

        .event-title {
            font-weight: bold;
            color: rgb(209, 120, 25);
            margin-bottom: 5px;
        }

        .event-date {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .member-name {
            font-weight: bold;
            color: rgb(209, 120, 25);
        }

        .member-role {
            background-color: rgb(209, 120, 25);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 10px;
        }

        .no-content {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 20px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .club-header {
                padding: 20px;
            }

            .club-title {
                flex-direction: column;
                text-align: center;
            }

            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="logo">
            <a href="home.php" style="color: black; text-decoration: none;">
                School Club Management System
            </a>
        </div>
        <div>
            <?php if (isset($_SESSION['username'])): ?>
                <span style="color: black; margin-right: 10px;">
                    Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!
                </span>
                <a href="home.php">Home</a>
                <a href="student_dashboard.php">Dashboard</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="home.php">Home</a>
                <a href="login.php">Login</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message <?php echo $_SESSION['message_type']; ?>">
                <?php 
                    echo htmlspecialchars($_SESSION['message']); 
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                ?>
            </div>
        <?php endif; ?>

        <div class="club-header">
            <div class="club-title">
                <div class="club-initials-badge"><?php echo htmlspecialchars($club['initials']); ?></div>
                <div>
                    <h1 class="club-name"><?php echo htmlspecialchars($club['name']); ?></h1>
                    <div class="club-meta">
                        Created by: <?php echo htmlspecialchars($club['creator_name'] ?? 'Unknown'); ?> | 
                        Founded: <?php echo date('F j, Y', strtotime($club['created_at'])); ?>
                    </div>
                </div>
            </div>
            
            <div class="club-description">
                <?php echo htmlspecialchars($club['description']); ?>
            </div>

            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="action-buttons">
                    <?php if ($is_member): ?>
                        <span class="btn btn-success">✓ You are a member</span>
                        <a href="leave_club.php?id=<?php echo $club_id; ?>" class="btn btn-secondary" 
                           onclick="return confirm('Are you sure you want to leave this club?')">Leave Club</a>
                    <?php else: ?>
                        <a href="join_club.php?id=<?php echo $club_id; ?>" class="btn btn-primary">Join Club</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="action-buttons">
                    <a href="login.php" class="btn btn-primary">Login to Join Club</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2 class="section-title">Upcoming Events</h2>
            <?php if ($events_result->num_rows > 0): ?>
                <?php while($event = $events_result->fetch_assoc()): ?>
                    <div class="event-item">
                        <div class="event-title"><?php echo htmlspecialchars($event['title']); ?></div>
                        <div class="event-date">
                            <?php echo $event['event_date'] ? date('F j, Y', strtotime($event['event_date'])) : 'Date TBD'; ?>
                        </div>
                        <div><?php echo htmlspecialchars($event['description']); ?></div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-content">No upcoming events</div>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2 class="section-title">Club Members (<?php echo $members_result->num_rows; ?>)</h2>
            <?php if ($members_result->num_rows > 0): ?>
                <?php while($member = $members_result->fetch_assoc()): ?>
                    <div class="member-item">
                        <span class="member-name"><?php echo htmlspecialchars($member['username']); ?></span>
                        <span class="member-role"><?php echo htmlspecialchars($member['role']); ?></span>
                        <span style="color: #666; font-size: 12px; margin-left: 10px;">
                            Joined: <?php echo date('M j, Y', strtotime($member['joined_at'])); ?>
                        </span>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-content">No members yet</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>