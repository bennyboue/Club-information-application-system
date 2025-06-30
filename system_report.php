<?php
require_once 'auth_check.php';

$conn = new mysqli("localhost", "root", "", "ics_project");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create necessary tables if they don't exist

// Clear any remaining results from multi_query
while ($conn->next_result()) {
    if ($result = $conn->store_result()) {
        $result->free();
    }
}

// Get system statistics with error handling
function getStat($conn, $query) {
    $result = $conn->query($query);
    if ($result === false) {
        // If table doesn't exist, return 0
        if (strpos($conn->error, "doesn't exist") !== false) {
            return 0;
        }
        error_log("Query failed: " . $query . " - " . $conn->error);
        return 0;
    }
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
") or die("Error fetching activity: " . $conn->error);

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>System Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Admin Dashboard Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, rgb(169, 153, 136), #a8967d);
            min-height: 100vh;
            color: #333;
        }

        /* Navigation Bar */
        .navbar {
            background: rgb(237, 222, 203);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: bold;
            color: rgb(209, 120, 25);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .nav-btn, .nav-links a {
            color: rgb(209, 120, 25);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(209, 120, 25, 0.1);
        }

        .nav-btn:hover, .nav-links a:hover {
            background: rgba(150, 85, 10, 0.2);
            color: rgb(209, 120, 25);
        }

        .user-welcome {
            color: rgb(209, 120, 25);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(150, 85, 10, 0.1);
            border-radius: 8px;
        }

        /* Main Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Report Header */
        .report-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 3px solid rgb(209, 120, 25);
        }

        .report-title {
            font-size: 2.5rem;
            color: rgb(209, 120, 25);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .report-subtitle {
            color: #555;
            font-size: 1.1rem;
            max-width: 800px;
            margin: 0 auto;
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s ease;
            border-top: 4px solid rgb(209, 120, 25);
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5rem;
            color: rgb(209, 120, 25);
            margin-bottom: 1rem;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #718096;
            font-size: 1rem;
            font-weight: 500;
        }

        /* Report Sections */
        .report-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .section-title {
            color: #2d3748;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.5rem;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 0.5rem;
            color: rgb(209, 120, 25);
        }

        /* Additional Statistics */
        .stats-list {
            list-style: none;
            padding: 0;
        }

        .stats-list li {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stats-list li:last-child {
            border-bottom: none;
        }

        .stat-name {
            font-weight: 600;
            color: #4a5568;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stat-value {
            font-weight: bold;
            font-size: 1.2rem;
            color: rgb(209, 120, 25);
        }

        /* Activity Log */
        .activity-log {
            list-style: none;
            padding: 0;
        }

        .activity-item {
            padding: 1.2rem;
            border-bottom: 1px solid #e2e8f0;
            transition: background 0.3s ease;
        }

        .activity-item:hover {
            background: rgba(237, 222, 203, 0.3);
        }

        .activity-action {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.3rem;
        }

        .activity-target {
            color: #4a5568;
            margin-bottom: 0.5rem;
            padding-left: 1.5rem;
        }

        .activity-meta {
            display: flex;
            justify-content: space-between;
            color: #718096;
            font-size: 0.9rem;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            justify-content: center;
        }

        .btn {
            background: linear-gradient(135deg, rgb(209, 120, 25) 0%, rgb(150, 85, 10) 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(209, 120, 25, 0.4);
        }

        .btn-print {
            background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%);
        }

        .btn-back {
            background: linear-gradient(135deg, #718096 0%, #4a5568 100%);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .container {
                padding: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-links {
                width: 100%;
                justify-content: center;
            }
            
            .report-title {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-file-alt"></i> School Club System - System Report
        </div>
        <div class="nav-links">
            <div class="user-welcome">
                <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
            </div>
            <a href="admin_dashboard.php"><i class="fas fa-home"></i> Home</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="report-header">
            <h1 class="report-title"><i class="fas fa-chart-line"></i> System Performance Report</h1>
            <p class="report-subtitle">Comprehensive overview of system usage, activity, and performance metrics</p>
        </div>

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
        
        <div class="report-section">
            <h2 class="section-title"><i class="fas fa-chart-pie"></i> Additional Statistics</h2>
            <ul class="stats-list">
                <li>
                    <span class="stat-name"><i class="fas fa-user-check"></i> Active Members</span>
                    <span class="stat-value"><?php echo $stats['active_members']; ?></span>
                </li>
                <li>
                    <span class="stat-name"><i class="fas fa-sign-in-alt"></i> Recent Logins (7 days)</span>
                    <span class="stat-value"><?php echo $stats['recent_logins']; ?></span>
                </li>
                <li>
                    <span class="stat-name"><i class="fas fa-database"></i> Total Clubs</span>
                    <span class="stat-value"><?php echo $stats['total_clubs']; ?></span>
                </li>
                <li>
                    <span class="stat-name"><i class="fas fa-calendar-check"></i> Active Events</span>
                    <span class="stat-value"><?php echo $stats['active_events']; ?></span>
                </li>
            </ul>
        </div>
        
        <div class="report-section">
            <h2 class="section-title"><i class="fas fa-history"></i> Recent Admin Activity</h2>
            <?php if ($activity_result->num_rows > 0): ?>
                <ul class="activity-log">
                    <?php while($activity = $activity_result->fetch_assoc()): ?>
                    <li class="activity-item">
                        <div class="activity-action">
                            <i class="fas fa-tasks"></i> <?php echo htmlspecialchars($activity['action']); ?>
                        </div>
                        <div class="activity-target">
                            Target: <?php echo htmlspecialchars($activity['target']); ?>
                        </div>
                        <div class="activity-meta">
                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($activity['performed_by']); ?></span>
                            <span><i class="fas fa-clock"></i> <?php echo date('M j, Y g:i a', strtotime($activity['action_time'])); ?></span>
                        </div>
                    </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <div style="text-align: center; padding: 2rem; color: #718096; font-style: italic;">
                    <i class="fas fa-info-circle"></i> No recent admin activity found
                </div>
            <?php endif; ?>
        </div>

        <div class="action-buttons">
            <button class="btn btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Print Report
            </button>
            <a href="admin_dashboard.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>