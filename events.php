<?php
// Include config first to set session parameters
require_once 'config.php';

// Now start the session
session_start();

// Check if user is logged in - if not, set as guest
$user_logged_in = isset($_SESSION['user_id']);
$user_id = $user_logged_in ? $_SESSION['user_id'] : null;
$user_role = $user_logged_in ? $_SESSION['role'] : 'guest';

// Function to get all events with club information
function getAllEvents($conn) {
    $sql = "SELECT e.*, c.name as club_name, c.initials as club_initials,
                   u.username as creator_name
            FROM events e
            JOIN clubs c ON e.club_id = c.id
            LEFT JOIN club_managers cm ON c.id = cm.club_id
            LEFT JOIN users u ON cm.user_id = u.id
            ORDER BY e.event_date DESC, e.created_at DESC";
    
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Function to get events for clubs the user is a member of
function getUserClubEvents($conn, $user_id) {
    $sql = "SELECT e.*, c.name as club_name, c.initials as club_initials,
                   u.username as creator_name
            FROM events e
            JOIN clubs c ON e.club_id = c.id
            JOIN memberships m ON c.id = m.club_id
            LEFT JOIN club_managers cm ON c.id = cm.club_id
            LEFT JOIN users u ON cm.user_id = u.id
            WHERE m.user_id = ? AND m.status = 'approved'
            ORDER BY e.event_date DESC, e.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Function to get events for clubs the user manages
function getClubManagerEvents($conn, $user_id) {
    $sql = "SELECT e.*, c.name as club_name, c.initials as club_initials,
                   u.username as creator_name
            FROM events e
            JOIN clubs c ON e.club_id = c.id
            JOIN club_managers cm ON c.id = cm.club_id
            LEFT JOIN users u ON cm.user_id = u.id
            WHERE cm.user_id = ? AND cm.status = 'active'
            ORDER BY e.event_date DESC, e.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Function to get events for a specific club
function getClubEvents($conn, $club_id) {
    $sql = "SELECT e.*, c.name as club_name, c.initials as club_initials,
                   u.username as creator_name
            FROM events e
            JOIN clubs c ON e.club_id = c.id
            LEFT JOIN club_managers cm ON c.id = cm.club_id
            LEFT JOIN users u ON cm.user_id = u.id
            WHERE e.club_id = ?
            ORDER BY e.event_date DESC, e.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $club_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Function to check if user manages any clubs
function getUserManagedClubs($conn, $user_id) {
    $sql = "SELECT c.id, c.name, c.initials
            FROM clubs c
            JOIN club_managers cm ON c.id = cm.club_id
            WHERE cm.user_id = ? AND cm.status = 'active'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Get events based on user role and filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$club_filter = isset($_GET['club']) ? (int)$_GET['club'] : 0;

// Handle different user roles and filters
if ($user_role === 'admin') {
    // Admin only sees all events
    if ($club_filter > 0) {
        $events = getClubEvents($conn, $club_filter);
    } else {
        $events = getAllEvents($conn);
    }
} else if ($user_role === 'club_manager') {
    // Club managers see all events, but "my clubs" shows only clubs they manage
    if ($club_filter > 0) {
        $events = getClubEvents($conn, $club_filter);
    } else if ($filter === 'my_clubs') {
        $events = getClubManagerEvents($conn, $user_id);
    } else {
        $events = getAllEvents($conn);
    }
} else if ($user_role === 'student') {
    // Students see all events, but "my clubs" shows only clubs they're members of
    if ($club_filter > 0) {
        $events = getClubEvents($conn, $club_filter);
    } else if ($filter === 'my_clubs') {
        $events = getUserClubEvents($conn, $user_id);
    } else {
        $events = getAllEvents($conn);
    }
} else {
    // Guest users - only see all events, no "my clubs" option
    if ($club_filter > 0) {
        $events = getClubEvents($conn, $club_filter);
    } else {
        $events = getAllEvents($conn);
    }
}

// Get all clubs for filter dropdown
$clubs_sql = "SELECT id, name, initials FROM clubs ORDER BY name";
$clubs_result = $conn->query($clubs_sql);
$all_clubs = $clubs_result ? $clubs_result->fetch_all(MYSQLI_ASSOC) : [];

// Format event dates
function formatEventDate($date) {
    if (!$date) return 'Date TBD';
    
    $event_date = new DateTime($date);
    $today = new DateTime();
    $tomorrow = new DateTime('+1 day');
    
    if ($event_date->format('Y-m-d') === $today->format('Y-m-d')) {
        return 'Today - ' . $event_date->format('g:i A');
    } else if ($event_date->format('Y-m-d') === $tomorrow->format('Y-m-d')) {
        return 'Tomorrow - ' . $event_date->format('g:i A');
    } else {
        return $event_date->format('M j, Y - g:i A');
    }
}

// Check if event is upcoming
function isUpcoming($date) {
    if (!$date) return false;
    return new DateTime($date) > new DateTime();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Events - ICS Club Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
    font-family: 'Times New Roman', Times, serif;
    margin: 0;
    padding: 0;
    background-color: rgb(169, 153, 136);
    line-height: 1.6;
}

.page-header {
    background-color: rgb(237, 222, 203);
    color: rgb(209, 120, 25);
    padding: 40px 0;
    margin-bottom: 30px;
    border-radius: 0 0 20px 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.page-header h1 {
    color: rgb(209, 120, 25);
    font-size: 28px;
    margin: 0;
}

.page-header p {
    color: #666;
    font-size: 16px;
    margin-bottom: 0;
}

.btn {
    background-color: rgb(209, 120, 25);
    color: white;
    padding: 10px 15px;
    text-decoration: none;
    border-radius: 5px;
    border: none;
    transition: background-color 0.3s ease, transform 0.3s ease;
    display: inline-block;
    cursor: pointer;
}

.btn:hover {
    background-color: rgb(150, 85, 10);
    transform: scale(1.05);
    color: white;
}

.btn-light {
    background-color: #fff;
    color: rgb(209, 120, 25);
    border: 2px solid rgb(209, 120, 25);
}

.btn-light:hover {
    background-color: rgb(209, 120, 25);
    color: white;
}

.filter-tabs {
    background: #fff;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.nav-pills {
    margin-bottom: 0;
}

.nav-pills .nav-link {
    border-radius: 8px;
    margin: 0 5px;
    color: #666;
    border: none;
    background-color: rgb(237, 222, 203);
    padding: 10px 20px;
    transition: all 0.3s ease;
}

.nav-pills .nav-link:hover {
    background-color: rgb(209, 120, 25);
    color: white;
    transform: translateY(-2px);
}

.nav-pills .nav-link.active {
    background-color: rgb(209, 120, 25);
    color: white;
}

.form-select {
    border: 2px solid rgb(237, 222, 203);
    border-radius: 5px;
    padding: 10px;
    font-family: 'Times New Roman', Times, serif;
    background-color: #fff;
}

.form-select:focus {
    border-color: rgb(209, 120, 25);
    box-shadow: 0 0 0 0.2rem rgba(209, 120, 25, 0.25);
}

.event-card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    margin-bottom: 20px;
    background-color: #fff;
}

.event-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.event-header {
    background-color: rgb(237, 222, 203);
    color: rgb(209, 120, 25);
    border-radius: 10px 10px 0 0;
    padding: 20px;
    border-bottom: 3px solid rgb(209, 120, 25);
}

.event-date {
    background: rgba(209, 120, 25, 0.1);
    border-radius: 8px;
    padding: 10px;
    text-align: center;
    margin-bottom: 10px;
    color: rgb(209, 120, 25);
    font-weight: bold;
}

.club-badge {
    background-color: rgb(209, 120, 25);
    color: white;
    border-radius: 12px;
    padding: 5px 15px;
    font-size: 0.8rem;
    display: inline-block;
}

.event-body {
    padding: 20px;
}

.event-description {
    color: #666;
    line-height: 1.6;
    margin-bottom: 15px;
}

.upcoming-badge {
    background-color: rgb(209, 120, 25);
    color: white;
    padding: 3px 10px;
    border-radius: 15px;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.past-badge {
    background: #6c757d;
    color: white;
    padding: 3px 10px;
    border-radius: 15px;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.no-events {
    text-align: center;
    padding: 60px 20px;
    color: #666;
    background-color: #fff;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.no-events i {
    font-size: 4rem;
    color: rgb(209, 120, 25);
    margin-bottom: 20px;
}

.no-events h3 {
    color: rgb(209, 120, 25);
    margin-bottom: 15px;
}

.creator-info {
    color: #888;
    font-size: 0.9rem;
    margin-top: 10px;
}

.text-muted {
    color: #666 !important;
}

.event-body h6 {
    color: rgb(209, 120, 25);
    font-weight: bold;
    margin-bottom: 10px;
}

.event-header h5 {
    color: rgb(209, 120, 25);
    font-weight: bold;
    margin-bottom: 15px;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 15px;
}

.guest-notice {
    background-color: rgba(209, 120, 25, 0.1);
    border: 1px solid rgb(209, 120, 25);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
    color: white;
    text-align: center;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .filter-tabs .row {
        flex-direction: column;
        gap: 15px;
    }
    
    .nav-pills {
        flex-wrap: wrap;
    }
    
    .nav-pills .nav-link {
        margin: 2px;
        font-size: 0.9rem;
    }
    
    .page-header {
        text-align: center;
    }
    
    .event-card {
        margin-bottom: 15px;
    }
}
    </style>
</head>
<body>
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1><i class="fas fa-calendar-alt me-3"></i>Club Events</h1>
                    <p class="mb-0">Discover and participate in exciting club activities</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <?php if ($user_logged_in): ?>
                        <a href="home.php" class="btn btn-light">
                            <i class="fas fa-arrow-left me-2"></i>Back to Home
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </a>
                        <a href="register.php" class="btn btn-light ms-2">
                            <i class="fas fa-user-plus me-2"></i>Register
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (!$user_logged_in): ?>
            <div class="guest-notice">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Viewing as Guest:</strong> Login to see events from clubs you're a member of and get personalized recommendations.
            </div>
        <?php endif; ?>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <ul class="nav nav-pills">
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($filter === 'all') ? 'active' : ''; ?>" 
                               href="?filter=all">
                                <i class="fas fa-globe me-1"></i>All Events
                            </a>
                        </li>
                        <?php if ($user_logged_in && $user_role !== 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($filter === 'my_clubs') ? 'active' : ''; ?>" 
                               href="?filter=my_clubs">
                                <i class="fas fa-users me-1"></i>My Clubs
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-md-6">
                    <select class="form-select" onchange="filterByClub(this.value)">
                        <option value="0">Filter by Club</option>
                        <?php foreach ($all_clubs as $club): ?>
                        <option value="<?php echo $club['id']; ?>" 
                                <?php echo ($club_filter == $club['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($club['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Events Grid -->
        <div class="row">
            <?php if (empty($events)): ?>
                <div class="col-12">
                    <div class="no-events">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Events Found</h3>
                        <?php if ($filter === 'my_clubs' && $user_logged_in): ?>
                            <p>You don't have any events from your clubs yet. Join clubs or check back later!</p>
                            <a href="clubs.php" class="btn">
                                <i class="fas fa-search me-2"></i>Browse Clubs
                            </a>
                        <?php else: ?>
                            <p>There are currently no events to display. Check back later for exciting club activities!</p>
                        <?php endif; ?>
                        
                        <?php if ($user_logged_in && $user_role === 'club_manager'): ?>
                        <a href="manage_events.php" class="btn ms-2">
                            <i class="fas fa-plus me-2"></i>Create Event
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card event-card">
                            <div class="event-header">
                                <div class="event-date">
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    <?php echo formatEventDate($event['event_date']); ?>
                                </div>
                                <h5 class="mb-2"><?php echo htmlspecialchars($event['title']); ?></h5>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="club-badge">
                                        <?php echo htmlspecialchars($event['club_initials']); ?>
                                    </span>
                                    <?php if (isUpcoming($event['event_date'])): ?>
                                        <span class="upcoming-badge">Upcoming</span>
                                    <?php else: ?>
                                        <span class="past-badge">Past</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="event-body">
                                <h6 class="text-muted mb-2">
                                    <i class="fas fa-users me-1"></i>
                                    <?php echo htmlspecialchars($event['club_name']); ?>
                                </h6>
                                
                                <?php if ($event['description']): ?>
                                <div class="event-description">
                                    <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($event['creator_name']): ?>
                                <div class="creator-info">
                                    <i class="fas fa-user me-1"></i>
                                    Organized by <?php echo htmlspecialchars($event['creator_name']); ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="creator-info">
                                    <i class="fas fa-clock me-1"></i>
                                    Posted <?php echo date('M j, Y', strtotime($event['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function filterByClub(clubId) {
            const currentUrl = new URL(window.location.href);
            if (clubId > 0) {
                currentUrl.searchParams.set('club', clubId);
            } else {
                currentUrl.searchParams.delete('club');
            }
            window.location.href = currentUrl.toString();
        }
    </script>
</body>
</html>