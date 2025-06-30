<?php
session_start();

// Check if user is logged in and is a club patron
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'club_patron') {
    header('Location: login.php');
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "ics_project");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get patron's school_id to determine which club they manage
$patron_id = $_SESSION['user_id'];
$patron_query = $conn->prepare("SELECT school_id FROM users WHERE id = ?");
$patron_query->bind_param("i", $patron_id);
$patron_query->execute();
$patron_result = $patron_query->get_result();
$patron_data = $patron_result->fetch_assoc();

if (!$patron_data || strpos($patron_data['school_id'], '-') === false) {
    die("You are not properly assigned as a patron for any club.");
}

// Extract club initials from school_id (format: ID-CLUB_INITIALS)
list($id_part, $club_initials) = explode('-', $patron_data['school_id'], 2);

// Get club information
$club_query = $conn->prepare("SELECT id, name, description FROM clubs WHERE initials = ?");
$club_query->bind_param("s", $club_initials);
$club_query->execute();
$club_result = $club_query->get_result();
$club = $club_result->fetch_assoc();

if (!$club) {
    die("No club found with your assigned initials.");
}

$club_id = $club['id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_member'])) {
        // Add new member to club
        $student_id = $_POST['student_id'];
        $add_member = $conn->prepare("INSERT INTO memberships (club_id, user_id) VALUES (?, ?)");
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
        $remove_member = $conn->prepare("DELETE FROM memberships WHERE club_id = ? AND user_id = ?");
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
        $post_announcement = $conn->prepare("INSERT INTO announcements (club_id, content, created_by) VALUES (?, ?, ?)");
        $post_announcement->bind_param("isi", $club_id, $content, $patron_id);
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
        
        $add_event = $conn->prepare("INSERT INTO events (club_id, title, event_date, location, description, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $add_event->bind_param("issssi", $club_id, $event_name, $event_date, $event_location, $event_description, $patron_id);
        $add_event->execute();
        $add_event->close();
        
        $_SESSION['message'] = "Event added successfully!";
        header("Location: club_manager_dashboard.php");
        exit();
    }
}

// Get club members
$members_query = $conn->prepare("SELECT u.id, u.username, u.email FROM users u JOIN memberships m ON u.id = m.user_id WHERE m.club_id = ?");
$members_query->bind_param("i", $club_id);
$members_query->execute();
$members_result = $members_query->get_result();

// Get all students (for adding new members)
$students_query = $conn->query("SELECT id, username FROM users WHERE role = 'student' AND id NOT IN (SELECT user_id FROM memberships WHERE club_id = $club_id)");

// Get announcements
$announcements_query = $conn->prepare("SELECT a.id, a.content, a.created_at, u.username FROM announcements a JOIN users u ON a.created_by = u.id WHERE a.club_id = ? ORDER BY a.created_at DESC LIMIT 5");
$announcements_query->bind_param("i", $club_id);
$announcements_query->execute();
$announcements_result = $announcements_query->get_result();

// Get events
$events_query = $conn->prepare("SELECT id, title as event_name, event_date, location, description FROM events WHERE club_id = ? ORDER BY event_date ASC");
$events_query->bind_param("i", $club_id);
$events_query->execute();
$events_result = $events_query->get_result();

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Club Patron Dashboard - <?php echo htmlspecialchars($club['name']); ?></title>
    <style>
        /* [Keep all your existing CSS styles from the previous dashboard] */
        /* Only updating the navbar to say "Club Patron" instead of "Club Manager" */
        .manager-navbar .logo h1 {
            font-size: 24px;
        }
        
        /* Add some responsive improvements */
        @media (max-width: 768px) {
            .manager-navbar .logo h1 {
                font-size: 20px;
            }
            
            .manager-welcome {
                font-size: 14px;
                padding: 8px 12px;
            }
            
            .manager-navbar a {
                padding: 8px 12px;
                font-size: 14px;
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
                (<?php echo htmlspecialchars($club['name']); ?>)
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
                    <div class="form-group">
                        <label for="student_id">Select Student</label>
                        <select id="student_id" name="student_id" class="form-control" required>
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
                        <div class="announcement-title">Announcement</div>
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
                        <input type="text" id="event_name" name="event_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="event_date">Date</label>
                        <input type="date" id="event_date" name="event_date" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="event_location">Location</label>
                        <input type="text" id="event_location" name="event_location" class="form-control" required>
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
    </script>
</body>
</html>