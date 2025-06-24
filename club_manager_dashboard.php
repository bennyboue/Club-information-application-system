<?php
session_start();

// Check if user is logged in and is a club manager
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'club_manager') {
    header('Location: login.php');
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "ics_project");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get manager's club information
$manager_id = $_SESSION['user_id'];
$club_query = $conn->prepare("SELECT c.id, c.name, c.description FROM clubs c JOIN club_managers cm ON c.id = cm.club_id WHERE cm.user_id = ?");
$club_query->bind_param("i", $manager_id);
$club_query->execute();
$club_result = $club_query->get_result();
$club = $club_result->fetch_assoc();

if (!$club) {
    die("You are not assigned as a manager for any club.");
}

$club_id = $club['id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_member'])) {
        // Add new member to club
        $student_id = $_POST['student_id'];
        $add_member = $conn->prepare("INSERT INTO club_memberships (club_id, user_id) VALUES (?, ?)");
        $add_member->bind_param("ii", $club_id, $student_id);
        $add_member->execute();
        $add_member->close();
        
        $_SESSION['message'] = "Member added successfully!";
        header("Location: club_manager_dashboard.php");
        exit();
    } 
    elseif (isset($_POST['remove_member'])) {
        // Remove member from club
        $member_id = $_POST['member_id'];
        $remove_member = $conn->prepare("DELETE FROM club_memberships WHERE club_id = ? AND user_id = ?");
        $remove_member->bind_param("ii", $club_id, $member_id);
        $remove_member->execute();
        $remove_member->close();
        
        $_SESSION['message'] = "Member removed successfully!";
        header("Location: club_manager_dashboard.php");
        exit();
    } 
    elseif (isset($_POST['post_announcement'])) {
        // Post new announcement
        $title = $_POST['announcement_title'];
        $content = $_POST['announcement_content'];
        $post_announcement = $conn->prepare("INSERT INTO club_announcements (club_id, title, content, posted_by) VALUES (?, ?, ?, ?)");
        $post_announcement->bind_param("issi", $club_id, $title, $content, $manager_id);
        $post_announcement->execute();
        $post_announcement->close();
        
        $_SESSION['message'] = "Announcement posted successfully!";
        header("Location: club_manager_dashboard.php");
        exit();
    } 
    elseif (isset($_POST['add_event'])) {
        // Add new event
        $event_name = $_POST['event_name'];
        $event_date = $_POST['event_date'];
        $event_location = $_POST['event_location'];
        $event_description = $_POST['event_description'];
        
        $add_event = $conn->prepare("INSERT INTO club_events (club_id, event_name, event_date, location, description, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $add_event->bind_param("issssi", $club_id, $event_name, $event_date, $event_location, $event_description, $manager_id);
        $add_event->execute();
        $add_event->close();
        
        $_SESSION['message'] = "Event added successfully!";
        header("Location: club_manager_dashboard.php");
        exit();
    }
}

// Get club members
$members_query = $conn->prepare("SELECT u.id, u.username, u.email FROM users u JOIN club_memberships cm ON u.id = cm.user_id WHERE cm.club_id = ?");
$members_query->bind_param("i", $club_id);
$members_query->execute();
$members_result = $members_query->get_result();

// Get all students (for adding new members)
$students_query = $conn->query("SELECT id, username FROM users WHERE role = 'student' AND id NOT IN (SELECT user_id FROM club_memberships WHERE club_id = $club_id)");

// Get announcements
$announcements_query = $conn->prepare("SELECT id, title, content, created_at FROM club_announcements WHERE club_id = ? ORDER BY created_at DESC LIMIT 5");
$announcements_query->bind_param("i", $club_id);
$announcements_query->execute();
$announcements_result = $announcements_query->get_result();

// Get events
$events_query = $conn->prepare("SELECT id, event_name, event_date, location, description FROM club_events WHERE club_id = ? ORDER BY event_date ASC");
$events_query->bind_param("i", $club_id);
$events_query->execute();
$events_result = $events_query->get_result();

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Club Manager Dashboard - <?php echo htmlspecialchars($club['name']); ?></title>
    <style>
        /* CSS from previous admin dashboard with some adjustments */
        body {
            font-family: 'Times New Roman', Times, serif;
            margin: 0;
            padding: 0;
            background-color: rgb(169, 153, 136);
            color: #333;
        }

        .manager-navbar {
            background-color: rgb(237, 222, 203);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .manager-navbar .logo {
            font-size: 18px;
            font-weight: bold;
            color: black;
        }

        .manager-navbar a {
            background-color: rgb(209, 120, 25);
            color: #fff;
            padding: 10px 18px;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease, transform 0.3s ease;
            text-align: center;
            margin-left: 10px;
        }

        .manager-navbar a:hover {
            background-color: rgb(150, 85, 10);
            transform: scale(1.05);
        }

        .manager-welcome {
            background-color: rgb(209, 120, 25);
            color: #fff;
            padding: 10px 18px;
            border-radius: 5px;
            font-weight: bold;
        }

        .manager-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .manager-header {
            text-align: center;
            color: rgb(209, 120, 25);
            font-size: 28px;
            margin-bottom: 30px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .club-info-card {
            background-color: rgb(237, 222, 203);
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
            border: 3px solid rgb(209, 120, 25);
            text-align: center;
        }

        .club-name {
            font-size: 24px;
            font-weight: bold;
            color: rgb(209, 120, 25);
            margin-bottom: 15px;
        }

        .club-description {
            font-size: 16px;
            line-height: 1.6;
        }

        .manager-card {
            background-color: rgb(237, 222, 203);
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
            border: 3px solid rgb(209, 120, 25);
        }

        .manager-card-header {
            color: rgb(209, 120, 25);
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgb(209, 120, 25);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: rgb(209, 120, 25);
            color: white;
            font-weight: bold;
        }

        tr:nth-child(even) {
            background-color: rgba(237, 222, 203, 0.5);
        }

        tr:hover {
            background-color: rgba(209, 120, 25, 0.1);
        }

        .btn {
            background-color: rgb(209, 120, 25);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }

        .btn:hover {
            background-color: rgb(150, 85, 10);
            transform: scale(1.05);
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .btn-danger {
            background-color: #dc3545;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }

        .btn-success {
            background-color: #28a745;
        }

        .btn-success:hover {
            background-color: #218838;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: rgb(209, 120, 25);
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Times New Roman', Times, serif;
            font-size: 16px;
        }

        textarea {
            min-height: 100px;
        }

        .form-actions {
            text-align: right;
            margin-top: 20px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .tabs {
            display: flex;
            border-bottom: 2px solid rgb(209, 120, 25);
            margin-bottom: 20px;
        }

        .tab {
            padding: 10px 20px;
            cursor: pointer;
            background-color: rgb(237, 222, 203);
            margin-right: 5px;
            border-radius: 5px 5px 0 0;
            transition: all 0.3s ease;
        }

        .tab.active {
            background-color: rgb(209, 120, 25);
            color: white;
        }

        .tab:hover:not(.active) {
            background-color: rgba(209, 120, 25, 0.2);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .announcement-card {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            border-left: 5px solid rgb(209, 120, 25);
        }

        .announcement-title {
            font-weight: bold;
            color: rgb(209, 120, 25);
            font-size: 18px;
            margin-bottom: 10px;
        }

        .announcement-date {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .announcement-content {
            line-height: 1.6;
        }

        .event-card {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            border-left: 5px solid rgb(209, 120, 25);
        }

        .event-name {
            font-weight: bold;
            color: rgb(209, 120, 25);
            font-size: 18px;
            margin-bottom: 5px;
        }

        .event-date {
            color: #666;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .event-location {
            color: #666;
            font-style: italic;
            margin-bottom: 10px;
        }

        .event-description {
            line-height: 1.6;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .manager-navbar {
                flex-direction: column;
                padding: 15px;
            }

            .manager-navbar > div {
                margin-bottom: 10px;
            }

            .manager-navbar a {
                margin: 5px 0;
                display: block;
                width: 100%;
            }

            table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="manager-navbar">
        <div class="logo">
            <h1>Club Manager Dashboard</h1>
        </div>
        <div>
            <span class="manager-welcome">
                Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!
            </span>
            <a href="home.php">Home</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="manager-container">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <div class="club-info-card">
            <div class="club-name"><?php echo htmlspecialchars($club['name']); ?></div>
            <div class="club-description"><?php echo htmlspecialchars($club['description']); ?></div>
        </div>

        <div class="tabs">
            <div class="tab active" onclick="openTab(event, 'members')">Members</div>
            <div class="tab" onclick="openTab(event, 'announcements')">Announcements</div>
            <div class="tab" onclick="openTab(event, 'events')">Events</div>
        </div>

        <!-- Members Tab -->
        <div id="members" class="tab-content active">
            <div class="manager-card">
                <h2 class="manager-card-header">Club Members</h2>
                
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($member = $members_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($member['username']); ?></td>
                            <td><?php echo htmlspecialchars($member['email']); ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                    <button type="submit" name="remove_member" class="btn btn-danger btn-sm">Remove</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div class="manager-card">
                <h2 class="manager-card-header">Add New Member</h2>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="student_id">Select Student</label>
                        <select id="student_id" name="student_id" required>
                            <option value="">-- Select a student --</option>
                            <?php while ($student = $students_query->fetch_assoc()): ?>
                            <option value="<?php echo $student['id']; ?>"><?php echo htmlspecialchars($student['username']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="add_member" class="btn btn-success">Add Member</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Announcements Tab -->
        <div id="announcements" class="tab-content">
            <div class="manager-card">
                <h2 class="manager-card-header">Recent Announcements</h2>
                
                <?php if ($announcements_result->num_rows > 0): ?>
                    <?php while ($announcement = $announcements_result->fetch_assoc()): ?>
                    <div class="announcement-card">
                        <div class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></div>
                        <div class="announcement-date">Posted on <?php echo date('F j, Y', strtotime($announcement['created_at'])); ?></div>
                        <div class="announcement-content"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No announcements yet.</p>
                <?php endif; ?>
            </div>

            <div class="manager-card">
                <h2 class="manager-card-header">Post New Announcement</h2>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="announcement_title">Title</label>
                        <input type="text" id="announcement_title" name="announcement_title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="announcement_content">Content</label>
                        <textarea id="announcement_content" name="announcement_content" required></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="post_announcement" class="btn btn-success">Post Announcement</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Events Tab -->
        <div id="events" class="tab-content">
            <div class="manager-card">
                <h2 class="manager-card-header">Upcoming Events</h2>
                
                <?php if ($events_result->num_rows > 0): ?>
                    <?php while ($event = $events_result->fetch_assoc()): ?>
                    <div class="event-card">
                        <div class="event-name"><?php echo htmlspecialchars($event['event_name']); ?></div>
                        <div class="event-date">Date: <?php echo date('F j, Y', strtotime($event['event_date'])); ?></div>
                        <div class="event-location">Location: <?php echo htmlspecialchars($event['location']); ?></div>
                        <div class="event-description"><?php echo nl2br(htmlspecialchars($event['description'])); ?></div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No upcoming events.</p>
                <?php endif; ?>
            </div>

            <div class="manager-card">
                <h2 class="manager-card-header">Add New Event</h2>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="event_name">Event Name</label>
                        <input type="text" id="event_name" name="event_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="event_date">Date</label>
                        <input type="date" id="event_date" name="event_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="event_location">Location</label>
                        <input type="text" id="event_location" name="event_location" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="event_description">Description</label>
                        <textarea id="event_description" name="event_description" required></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="add_event" class="btn btn-success">Add Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openTab(evt, tabName) {
            // Hide all tab content
            const tabContents = document.getElementsByClassName("tab-content");
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove("active");
            }

            // Remove active class from all tabs
            const tabs = document.getElementsByClassName("tab");
            for (let i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove("active");
            }

            // Show the current tab and add active class
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }
    </script>
</body>
</html>