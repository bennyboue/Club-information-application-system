<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Get clubs managed by the current user
$manager_id = $_SESSION['user_id'];
$club_query = $conn->prepare("
    SELECT c.id, c.name, c.description 
    FROM clubs c
    INNER JOIN club_managers cm ON c.id = cm.club_id
    WHERE cm.user_id = ? AND cm.status = 'active'
");

if (!$club_query) {
    die("Error preparing club query: " . $conn->error);
}

$club_query->bind_param("i", $manager_id);
$club_query->execute();
$club_result = $club_query->get_result();

if ($club_result->num_rows === 0) {
    $_SESSION['message'] = "You are not a patron of any club. Please request patron status.";
    header('Location: request_patron.php');
    exit();
}

$clubs = [];
while ($club = $club_result->fetch_assoc()) {
    $clubs[] = $club;
}

// Determine selected club_id
if (isset($_POST['club_id'])) {
    $club_id = intval($_POST['club_id']);
} elseif (isset($_GET['club_id'])) {
    $club_id = intval($_GET['club_id']);
} else {
    $club_id = $clubs[0]['id'];
}

// Find the current club info for display
$current_club = null;
foreach ($clubs as $club) {
    if ($club['id'] == $club_id) {
        $current_club = $club;
        break;
    }
}
if (!$current_club) {
    $current_club = $clubs[0];
    $club_id = $current_club['id'];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify the user manages this club
    $valid_club = false;
    foreach ($clubs as $club) {
        if ($club['id'] == $club_id) {
            $valid_club = true;
            break;
        }
    }
    
    if (!$valid_club) {
        die("Invalid club selection");
    }

    if (isset($_POST['add_member'])) {
        // Add member logic
        $student_id = $_POST['student_id'];
        $add_member = $conn->prepare("INSERT INTO memberships (club_id, user_id) VALUES (?, ?)");
        $add_member->bind_param("ii", $club_id, $student_id);
        $add_member->execute();
        
       $_SESSION['message'] = "Member added successfully!";
header("Location: club_manager_dashboard.php?club_id=$club_id");
exit();
    } 
    elseif (isset($_POST['remove_member'])) {
        // Remove member from club
        $member_id = $_POST['member_id'];
        $remove_member = $conn->prepare("DELETE FROM memberships WHERE club_id = ? AND user_id = ?");
        $remove_member->bind_param("ii", $club_id, $member_id);
        $remove_member->execute();
        
       $_SESSION['message'] = "Member removed successfully!";
header("Location: club_manager_dashboard.php?club_id=$club_id");
exit();
    } 
    elseif (isset($_POST['post_announcement'])) {
        $title = $conn->real_escape_string(trim($_POST['announcement_title']));
        $content = $conn->real_escape_string(trim($_POST['announcement_content']));
        
        $post_announcement = $conn->prepare("
            INSERT INTO announcements (club_id, title, content, created_by, status) 
            VALUES (?, ?, ?, ?, 'published')
        ");
        if (!$post_announcement) {
            die("Error preparing announcement query: " . $conn->error);
        }
        
        $post_announcement->bind_param("issi", $club_id, $title, $content, $manager_id);
        if ($post_announcement->execute()) {
            $_SESSION['message'] = "Announcement posted successfully!";
        } else {
            $_SESSION['message'] = "Error posting announcement: " . $post_announcement->error;
        }
        header("Location: club_manager_dashboard.php?club_id=$club_id");
        exit();
    } 
    elseif (isset($_POST['add_event'])) {
        $event_name = $conn->real_escape_string(trim($_POST['event_name']));
        $event_date = $conn->real_escape_string(trim($_POST['event_date']));
        $event_description = $conn->real_escape_string(trim($_POST['event_description']));
        
        $add_event = $conn->prepare("
            INSERT INTO events (club_id, title, event_date, description) 
            VALUES (?, ?, ?, ?)
        ");
        if (!$add_event) {
            die("Error preparing event query: " . $conn->error);
        }
        
        $add_event->bind_param("isss", $club_id, $event_name, $event_date, $event_description);
        if ($add_event->execute()) {
            $_SESSION['message'] = "Event added successfully!";
        } else {
            $_SESSION['message'] = "Error adding event: " . $add_event->error;
        }
        header("Location: club_manager_dashboard.php?club_id=$club_id");
        exit();
    }
    elseif (isset($_POST['approve_request'])) {
        $request_id = intval($_POST['request_id']);
        $student_id = intval($_POST['student_id']);
        
        // Update request status
        $approve_request = $conn->prepare("
            UPDATE membership_requests 
            SET status = 'approved' 
            WHERE id = ? AND club_id = ?
        ");
        if (!$approve_request) {
            die("Error preparing approve request query: " . $conn->error);
        }
        
        $approve_request->bind_param("ii", $request_id, $club_id);
        $approve_request->execute();
        
        // Add to memberships
        $add_member = $conn->prepare("INSERT INTO memberships (club_id, user_id) VALUES (?, ?)");
        $add_member->bind_param("ii", $club_id, $student_id);
        $add_member->execute();
        
       $_SESSION['message'] = "Membership request approved!";
header("Location: club_manager_dashboard.php?club_id=$club_id");
exit();
    }
    elseif (isset($_POST['reject_request'])) {
        $request_id = intval($_POST['request_id']);
        
        // Reject request
        $reject_request = $conn->prepare("UPDATE membership_requests SET status = 'rejected' WHERE id = ?");
        $reject_request->bind_param("i", $request_id);
        $reject_request->execute();
        
       $_SESSION['message'] = "Membership request rejected!";
header("Location: club_manager_dashboard.php?club_id=$club_id");
exit();
    }
}

// Get club members
$members_query = $conn->prepare("
    SELECT u.id, u.username, u.email 
    FROM users u 
    JOIN memberships m ON u.id = m.user_id 
    WHERE m.club_id = ? AND m.status = 'approved'
");
if (!$members_query) {
    die("Error preparing members query: " . $conn->error);
}
$members_query->bind_param("i", $club_id);
$members_query->execute();
$members_result = $members_query->get_result();

// Get all students not in this club
$students_query = $conn->prepare("
    SELECT u.id, u.username 
    FROM users u 
    WHERE u.role = 'student' 
    AND u.id NOT IN (
        SELECT m.user_id 
        FROM memberships m 
        WHERE m.club_id = ? AND m.status = 'approved'
    )
");
if (!$students_query) {
    die("Error preparing students query: " . $conn->error);
}
$students_query->bind_param("i", $club_id);
$students_query->execute();
$students_result = $students_query->get_result();

// Get announcements
$announcements_query = $conn->prepare("
    SELECT a.id, a.title, a.content, a.created_at, u.username 
    FROM announcements a 
    JOIN users u ON a.created_by = u.id 
    WHERE a.club_id = ? 
    ORDER BY a.created_at DESC 
    LIMIT 5
");
if (!$announcements_query) {
    die("Error preparing announcements query: " . $conn->error);
}
$announcements_query->bind_param("i", $club_id);
$announcements_query->execute();
$announcements_result = $announcements_query->get_result();

// Get events
$events_query = $conn->prepare("
    SELECT id, title, event_date, description 
    FROM events 
    WHERE club_id = ? 
    ORDER BY event_date ASC
");
if (!$events_query) {
    die("Error preparing events query: " . $conn->error);
}
$events_query->bind_param("i", $club_id);
$events_query->execute();
$events_result = $events_query->get_result();

// Get pending membership requests - Updated to match your schema
$requests_query = $conn->prepare("
    SELECT m.id, u.id as user_id, u.username, u.email, m.joined_at as created_at 
    FROM memberships m 
    JOIN users u ON m.user_id = u.id 
    WHERE m.club_id = ? AND m.status = 'pending'
");

if (!$requests_query) {
    die("Error preparing requests query: " . $conn->error);
}

$requests_query->bind_param("i", $club_id);
if (!$requests_query->execute()) {
    die("Error executing requests query: " . $requests_query->error);
}
$requests_result = $requests_query->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Club Patron Dashboard</title>
    <style>
        body {
            font-family:'Times New Roman', Times, serif;
            margin: 0;
            padding: 0;
            background-color:rgb(169, 153, 136);
            color: #333;
        }
        
        .manager-navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color:rgb(237, 222, 203);
            padding: 15px 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .logo h1 {
            color: black;
            margin: 0;
            font-size: 24px;
        }
        
        .manager-welcome {
            color:#fff;
            background-color:rgb(209, 120, 25);
            padding: 10px 15px;
            border-radius: 20px;
            font-weight: 500;
            margin-right: 15px;
        }
        
        .manager-navbar a {
            color: black;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 4px;
            transition: background-color 0.3s;
            font-weight: 500;
        }
        
        .manager-navbar a:hover {
            background-color:rgb(150, 85, 10);
        }
        
        .manager-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .club-selection {
            margin-bottom: 25px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .club-selection label {
            font-weight: 600;
            margin-right: 10px;
            color: #2c3e50;
        }
        
        .club-selection select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            width: 300px;
        }
        
        .tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 12px 25px;
            cursor: pointer;
            background-color: #f1f1f1;
            border: 1px solid #ddd;
            border-bottom: none;
            border-radius: 5px 5px 0 0;
            margin-right: 5px;
            transition: background-color 0.3s;
        }
        
        .tab:hover {
            background-color: #e9ecef;
        }
        
        .tab.active {
            background-color: white;
            border-bottom: 1px solid white;
            margin-bottom: -1px;
            font-weight: 600;
            color: rgb(150, 85, 10);
        }
        
        .tab-content {
            display: none;
            padding: 20px;
            background: white;
            border-radius: 0 0 5px 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .tab-content.active {
            display: block;
        }
        
        .manager-card {
            margin-bottom: 30px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            background: white;
        }
        
        .manager-card-header {
            background-color: rgb(150, 85, 10);
            color: white;
            padding: 15px 20px;
            margin: 0;
            font-size: 18px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        table tr:hover {
            background-color: #f8f9fa;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #495057;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-actions {
            margin-top: 20px;
            text-align: right;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #218838;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 14px;
        }
        
        .announcement-card, .event-card {
            border: 1px solid #eee;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            background: #f8f9fa;
        }
        
        .announcement-title {
            font-weight: 600;
            font-size: 18px;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .announcement-date {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .announcement-content {
            line-height: 1.6;
        }
        
        .event-name {
            font-weight: 600;
            font-size: 18px;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .event-date {
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .club-info-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        
        .club-name {
            font-size: 24px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .club-description {
            color: #6c757d;
            line-height: 1.6;
        }
        
        @media (max-width: 768px) {
            .manager-navbar {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .manager-navbar > div {
                width: 100%;
                margin-top: 15px;
                display: flex;
                flex-wrap: wrap;
            }
            
            .manager-welcome {
                margin-bottom: 10px;
            }
            
            .club-selection select {
                width: 100%;
            }
            
            .tabs {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="manager-navbar">
        <div class="logo">
            <h1>Club Patron Dashboard</h1>
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
        <div style="background:#ffeeba;color:#856404;padding:10px 20px;border-radius:5px;margin-bottom:20px;">
            <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
        </div>
    <?php endif; ?>
        <!-- Club selection dropdown -->
        <div class="club-selection">
            <label for="club_selector">Select Club:</label>
            <select id="club_selector" onchange="updateClubDisplay(this.value)">
                <?php foreach ($clubs as $club): ?>
                    <option value="<?= $club['id'] ?>"><?= htmlspecialchars($club['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="club-info-card">
    <div class="club-name"><?php echo htmlspecialchars($current_club['name']); ?></div>
    <div class="club-description"><?php echo htmlspecialchars($current_club['description']); ?></div>
</div>

        <div class="tabs">
            <div class="tab active" onclick="openTab(event, 'members')">Members</div>
            <div class="tab" onclick="openTab(event, 'requests')">Membership Requests</div>
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
                                    <input type="hidden" name="club_id" value="<?= $club_id ?>">
                                    <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                    <button type="submit" name="remove_member" class="btn btn-danger btn-sm" 
                                            onclick="return confirm('Are you sure you want to remove this member?')">
                                        Remove
                                    </button>
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
                    <input type="hidden" name="club_id" value="<?= $club_id ?>">
                    <div class="form-group">
                        <label for="student_id">Select Student</label>
                        <select id="student_id" name="student_id" class="form-control" required>
                            <option value="">-- Select a student --</option>
                            <?php while ($student = $students_result->fetch_assoc()): ?>
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

        <!-- Membership Requests Tab -->
        <div id="requests" class="tab-content">
            <div class="manager-card">
                <h2 class="manager-card-header">Pending Membership Requests</h2>
                
                <?php if ($requests_result->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Request Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($request = $requests_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['username']); ?></td>
                                <td><?php echo htmlspecialchars($request['email']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($request['created_at'])); ?></td>
                                <td>
                                    <form method="POST" style="display:inline-block;">
                                        <input type="hidden" name="club_id" value="<?= $club_id ?>">
                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                        <input type="hidden" name="student_id" value="<?php echo $request['user_id']; ?>">
                                        <button type="submit" name="approve_request" class="btn btn-success btn-sm">
                                            Approve
                                        </button>
                                    </form>
                                    <form method="POST" style="display:inline-block;">
                                        <input type="hidden" name="club_id" value="<?= $club_id ?>">
                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                        <button type="submit" name="reject_request" class="btn btn-danger btn-sm"
                                                onclick="return confirm('Are you sure you want to reject this request?')">
                                            Reject
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No pending membership requests.</p>
                <?php endif; ?>
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
                        <div class="announcement-date">
                            Posted by <?php echo htmlspecialchars($announcement['username']); ?> 
                            on <?php echo date('F j, Y g:i a', strtotime($announcement['created_at'])); ?>
                        </div>
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
                    <input type="hidden" name="club_id" value="<?= $club_id ?>">
                    <div class="form-group">
                        <label for="announcement_title">Title</label>
                        <input type="text" id="announcement_title" name="announcement_title" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="announcement_content">Content</label>
                        <textarea id="announcement_content" name="announcement_content" class="form-control" required></textarea>
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
                    <input type="hidden" name="club_id" value="<?= $club_id ?>">
                    <div class="form-group">
                        <label for="event_name">Event Name</label>
                        <input type="text" id="event_name" name="event_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="event_date">Date</label>
                        <input type="date" id="event_date" name="event_date" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="event_description">Description</label>
                        <textarea id="event_description" name="event_description" class="form-control" required></textarea>
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
        
        function updateClubDisplay(clubId) {
            // Reload the page with the selected club
            window.location.href = `club_manager_dashboard.php?club_id=${clubId}`;
        }
        
        // Set the selected club in dropdown
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const clubId = urlParams.get('club_id');
            
            if (clubId) {
                document.getElementById('club_selector').value = clubId;
            }
        });
    </script>
</body>
</html>