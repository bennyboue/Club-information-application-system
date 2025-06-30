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
$username = $_SESSION['username'];

// Fetch user's clubs
$clubs_stmt = $conn->prepare("
    SELECT c.id, c.name, c.initials, c.description, m.joined_at 
    FROM clubs c 
    JOIN memberships m ON c.id = m.club_id 
    WHERE m.user_id = ? 
    ORDER BY m.joined_at DESC
");
$clubs_stmt->bind_param("i", $user_id);
$clubs_stmt->execute();
$clubs_result = $clubs_stmt->get_result();

// Fetch upcoming events from user's clubs
$events_stmt = $conn->prepare("
    SELECT e.id, e.title, e.description, e.event_date, c.name as club_name, c.id as club_id
    FROM events e 
    JOIN clubs c ON e.club_id = c.id 
    JOIN memberships m ON c.id = m.club_id 
    WHERE m.user_id = ? AND (e.event_date >= CURDATE() OR e.event_date IS NULL)
    ORDER BY e.event_date ASC, e.created_at DESC
    LIMIT 10
");
$events_stmt->bind_param("i", $user_id);
$events_stmt->execute();
$events_result = $events_stmt->get_result();

// Get user profile info
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
    <title>Student Dashboard - School Club Management</title>
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

        .dashboard-content {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .welcome-header {
            background-color: #fff;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            text-align: center;
        }

        .welcome-header h1 {
            color: rgb(209, 120, 25);
            margin: 0 0 10px 0;
            font-size: 28px;
        }

        .welcome-subtitle {
            color: #666;
            font-size: 16px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .dashboard-section {
            background-color: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .section-title {
            color: rgb(209, 120, 25);
            font-size: 20px;
            margin-bottom: 15px;
            border-bottom: 2px solid rgb(209, 120, 25);
            padding-bottom: 5px;
        }

        .club-item {
            background-color: rgb(237, 222, 203);
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            border-left: 4px solid rgb(209, 120, 25);
            transition: transform 0.2s ease;
        }

        .club-item:hover {
            transform: translateX(5px);
        }

        .club-name {
            font-weight: bold;
            color: rgb(209, 120, 25);
            margin-bottom: 5px;
        }

        .club-initials {
            background-color: rgb(209, 120, 25);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-right: 10px;
        }

        .club-joined {
            color: #666;
            font-size: 12px;
        }

        .event-item {
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

        .event-club {
            color: #666;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .event-date {
            color: #666;
            font-size: 14px;
            font-weight: bold;
        }

        .no-content {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 20px;
        }

        .profile-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .profile-field {
            padding: 10px;
            background-color: rgb(237, 222, 203);
            border-radius: 5px;
        }

        .profile-label {
            font-weight: bold;
            color: rgb(209, 120, 25);
            display: block;
            margin-bottom: 5px;
        }

        .stats-row {
            display: flex;
            justify-content: space-around;
            text-align: center;
            margin-top: 20px;
        }

        .stat-item {
            background-color: rgb(237, 222, 203);
            padding: 15px;
            border-radius: 8px;
            flex: 1;
            margin: 0 5px;
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: rgb(209, 120, 25);
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .btn {
            background-color: rgb(209, 120, 25);
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin-top: 10px;
            transition: background-color 0.3s ease;
        }

        .btn:hover {
            background-color: rgb(150, 85, 10);
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .profile-info {
                grid-template-columns: 1fr;
            }

            .stats-row {
                flex-direction: column;
                gap: 10px;
            }

            .stat-item {
                margin: 0;
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
        <span style="color:black; font-weight:bold;">Hello, <?php echo htmlspecialchars($username); ?>!</span>
        <a href="home.php">Browse Clubs</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="dashboard-content">
    <div class="welcome-header">
        <h1>Welcome back, <?php echo htmlspecialchars($username); ?>!</h1>
        <p class="welcome-subtitle">Here's your club management dashboard</p>
        
        <div class="stats-row">
            <div class="stat-item">
                <div class="stat-number"><?php echo $clubs_result->num_rows; ?></div>
                <div class="stat-label">Clubs Joined</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $events_result->num_rows; ?></div>
                <div class="stat-label">Upcoming Events</div>
            </div>
            
            <div class="dashboard-section">
    <?php displayNotifications($mysqli, $_SESSION['user_id'], 'student'); ?>
</div>
            <div class="stat-item">
                <div class="stat-number"><?php echo ucfirst($user_profile['role']); ?></div>
                <div class="stat-label">Account Type</div>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="dashboard-section">
            <h3 class="section-title">My Clubs</h3>
            <?php if ($clubs_result->num_rows > 0): ?>
                <?php while($club = $clubs_result->fetch_assoc()): ?>
                    <div class="club-item">
                        <div class="club-name">
                            <span class="club-initials"><?php echo htmlspecialchars($club['initials']); ?></span>
                            <?php echo htmlspecialchars($club['name']); ?>
                        </div>
                        <div style="margin-bottom: 8px; color: #333;">
                            <?php echo htmlspecialchars($club['description']); ?>
                        </div>
                        <div class="club-joined">
                            Joined: <?php echo date('M j, Y', strtotime($club['joined_at'])); ?>
                        </div>
                        <a href="club_details.php?id=<?php echo $club['id']; ?>" class="btn">View Details</a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-content">
                    You haven't joined any clubs yet.
                    <br><br>
                    <a href="home.php" class="btn">Browse Available Clubs</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="dashboard-section">
            <h3 class="section-title">Upcoming Events</h3>
            <?php if ($events_result->num_rows > 0): ?>
                <?php while($event = $events_result->fetch_assoc()): ?>
                    <div class="event-item">
                        <div class="event-title"><?php echo htmlspecialchars($event['title']); ?></div>
                        <div class="event-club">
                            From: <?php echo htmlspecialchars($event['club_name']); ?>
                        </div>
                        <?php if (!empty($event['description'])): ?>
                            <div style="margin-bottom: 8px; color: #333; font-size: 14px;">
                                <?php echo htmlspecialchars($event['description']); ?>
                            </div>
                        <?php endif; ?>
                        <div class="event-date">
                            <?php 
                            if ($event['event_date']) {
                                echo date('M j, Y', strtotime($event['event_date']));
                            } else {
                                echo "Date TBD";
                            }
                            ?>
                        </div>
                        <a href="club_details.php?id=<?php echo $event['club_id']; ?>" class="btn">View Club</a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-content">
                    No upcoming events from your clubs.
                    <br><br>
                    <a href="home.php" class="btn">Join More Clubs</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Profile Information Section -->
    <div class="dashboard-section">
        <h3 class="section-title">Profile Information</h3>
        <div class="profile-info">
            <div class="profile-field">
                <span class="profile-label">Username:</span>
                <?php echo htmlspecialchars($user_profile['username']); ?>
            </div>
            <div class="profile-field">
                <span class="profile-label">Surname:</span>
                <?php echo htmlspecialchars($user_profile['surname'] ?? 'Not provided'); ?>
            </div>
            <div class="profile-field">
                <span class="profile-label">Email:</span>
                <?php echo htmlspecialchars($user_profile['email']); ?>
            </div>
            <div class="profile-field">
                <span class="profile-label">School ID:</span>
                <?php echo htmlspecialchars($user_profile['school_id'] ?? 'Not provided'); ?>
            </div>
        </div>
        <div style="text-align: center; margin-top: 20px;">
            <a href="profile.php" class="btn">Edit Profile</a>
        </div>
    </div>
</div>

</body>
</html>