<?php
require_once 'auth_check.php';

$conn = new mysqli("localhost", "root", "", "ics_project");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get system statistics
function getStat($conn, $query) {
    $result = $conn->query($query);
    if ($result === false) return 0;
    $row = $result->fetch_assoc();
    return $row['count'] ?? 0;
}

$stats = [
    'total_clubs' => getStat($conn, "SELECT COUNT(*) as count FROM clubs"),
    'total_users' => getStat($conn, "SELECT COUNT(*) as count FROM users"),
    'active_events' => getStat($conn, "SELECT COUNT(*) as count FROM events WHERE event_date >= CURDATE()"),
    'pending_requests' => getStat($conn, "SELECT COUNT(*) as count FROM memberships WHERE status = 'pending'"),
    'active_members' => getStat($conn, "SELECT COUNT(*) as count FROM memberships WHERE status = 'approved'"),
    'recent_logins' => getStat($conn, "SELECT COUNT(*) as count FROM login_logs WHERE login_time > NOW() - INTERVAL 7 DAY")
];

// Get recent activity
$activity_result = $conn->query("
    SELECT action, target, performed_by, action_time 
    FROM admin_activity 
    ORDER BY action_time DESC 
    LIMIT 10
");

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>System Report</title>
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-cogs"></i> School Club System - System Report
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
        <h1 class="section-title"><i class="fas fa-file-alt"></i> System Report</h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-star"></i></div>
                <div class="stat-number"><?php echo $stats['total_clubs']; ?></div>
                <div class="stat-label">Active Clubs</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                <div class="stat-number"><?php echo $stats['active_events']; ?></div>
                <div class="stat-label">Upcoming Events</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo $stats['pending_requests']; ?></div>
                <div class="stat-label">Pending Requests</div>
            </div>
        </div>
        
        <div class="dashboard-grid">
            <div class="dashboard-section">
                <h2><i class="fas fa-chart-line"></i> Additional Statistics</h2>
                <ul class="stats-list">
                    <li><strong>Active Members:</strong> <?php echo $stats['active_members']; ?></li>
                    <li><strong>Logins (Last 7 Days):</strong> <?php echo $stats['recent_logins']; ?></li>
                </ul>
            </div>
            
            <div class="dashboard-section">
                <h2><i class="fas fa-history"></i> Recent Activity</h2>
                <?php if ($activity_result->num_rows > 0): ?>
                    <ul class="activity-log">
                        <?php while($activity = $activity_result->fetch_assoc()): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($activity['action']); ?></strong> 
                            <?php echo htmlspecialchars($activity['target']); ?>
                            <div class="activity-meta">
                                By <?php echo htmlspecialchars($activity['performed_by']); ?> 
                                on <?php echo date('M j, Y g:i a', strtotime($activity['action_time'])); ?>
                            </div>
                        </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <div class="no-data">No recent activity</div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="action-buttons">
            <button class="btn" onclick="window.print()">
                <i class="fas fa-print"></i> Print Report
            </button>
            <a href="admin_dashboard.php" class="btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>