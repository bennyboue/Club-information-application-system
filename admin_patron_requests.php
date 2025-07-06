[file name]: admin_patron_requests.php
[file content begin]
<?php
require __DIR__ . '/vendor/autoload.php';
session_start();

// Verify system admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$mysqli = new mysqli("localhost", "root", "", "ics_project");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$message = "";
$message_type = "";

// Handle request actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['request_id'])) {
        $request_id = intval($_POST['request_id']);
        $action = $_POST['action'];
        
        // Get request details
        $stmt = $mysqli->prepare("SELECT * FROM patron_requests WHERE id = ?");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        
        if (!$request) {
            $message = "Request not found";
            $message_type = "error";
        } else {
            $mysqli->begin_transaction();
            try {
                if ($action === 'approve') {
                    // Assign patron to club
                    $assign_stmt = $mysqli->prepare("INSERT INTO club_managers (user_id, club_id, is_patron) VALUES (?, ?, 1)");
                    $assign_stmt->bind_param("ii", $request['patron_id'], $request['club_id']);
                    $assign_stmt->execute();
                    
                    // Update request status
                    $update_stmt = $mysqli->prepare("UPDATE patron_requests SET status = 'approved' WHERE id = ?");
                    $update_stmt->bind_param("i", $request_id);
                    $update_stmt->execute();
                    
                    // Update user role
                    $role_stmt = $mysqli->prepare("UPDATE users SET role = 'club_manager' WHERE id = ?");
                    $role_stmt->bind_param("i", $request['patron_id']);
                    $role_stmt->execute();
                    
                    $message = "Patron assigned successfully!";
                    $message_type = "success";
                } elseif ($action === 'reject') {
                    // Update request status
                    $update_stmt = $mysqli->prepare("UPDATE patron_requests SET status = 'rejected' WHERE id = ?");
                    $update_stmt->bind_param("i", $request_id);
                    $update_stmt->execute();
                    
                    $message = "Request rejected!";
                    $message_type = "success";
                }
                
                $mysqli->commit();
            } catch (Exception $e) {
                $mysqli->rollback();
                $message = "Error processing request: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
}

// Get pending patron requests
$requests_query = "
    SELECT pr.id, pr.created_at, pr.request_notes, 
           c.name AS club_name, 
           u.username AS patron_name,
           um.username AS requested_by
    FROM patron_requests pr
    JOIN clubs c ON pr.club_id = c.id
    JOIN users u ON pr.patron_id = u.id
    JOIN users um ON pr.requested_by = um.id
    WHERE pr.status = 'pending'
    ORDER BY pr.created_at DESC
";

$requests_result = $mysqli->query($requests_query);
if ($requests_result === false) {
    die("Error fetching requests: " . $mysqli->error);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Pending Patron Requests</title>
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
        
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .title {
            color: rgb(123, 71, 14);
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .back-btn {
            background: linear-gradient(135deg, rgb(209, 120, 25) 0%, rgb(150, 85, 10) 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(209, 120, 25, 0.4);
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
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert.success {
            background: linear-gradient(135deg, rgba(209, 120, 25, 0.9), rgba(150, 85, 10, 0.9));
            color: white;
        }
        
        .alert.error {
            background: linear-gradient(135deg, rgba(150, 85, 10, 0.9), rgba(123, 71, 14, 0.9));
            color: white;
        }
        
        .table-container {
            background: rgba(237, 222, 203, 0.95);
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(237, 222, 203, 0.95);
        }
        
        th {
            background: linear-gradient(135deg, rgb(237, 222, 203), rgb(169, 153, 136));
            padding: 20px;
            text-align: left;
            font-weight: 700;
            color: rgb(123, 71, 14);
            border-bottom: 2px solid rgb(209, 120, 25);
            font-size: 16px;
        }
        
        td {
            padding: 20px;
            border-bottom: 1px solid rgba(209, 120, 25, 0.3);
            vertical-align: middle;
            color: rgb(123, 71, 14);
        }
        
        tr:hover {
            background: rgba(209, 120, 25, 0.05);
        }
        
        .request-info {
            display: flex;
            flex-direction: column;
        }
        
        .request-club {
            font-weight: 600;
            font-size: 18px;
        }
        
        .request-detail {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 8px;
            font-size: 14px;
        }
        
        .request-detail i {
            color: rgb(209, 120, 25);
        }
        
        .request-notes {
            background: rgba(209, 120, 25, 0.1);
            padding: 15px;
            border-radius: 10px;
            margin-top: 10px;
            font-style: italic;
        }
        
        .actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-approve {
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.9), rgba(56, 142, 60, 0.9));
            color: white;
        }
        
        .btn-reject {
            background: linear-gradient(135deg, rgba(244, 67, 54, 0.9), rgba(211, 47, 47, 0.9));
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .no-requests {
            padding: 40px;
            text-align: center;
            color: rgb(123, 71, 14);
            font-size: 18px;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="title"><i class="fas fa-user-tie"></i> Pending Patron Requests</h1>
            <a href="admin_dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <?php if ($message): ?>
            <div class="alert <?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="table-container">
            <?php if ($requests_result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Club</th>
                            <th>Patron</th>
                            <th>Requested By</th>
                            <th>Date Requested</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($request = $requests_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="request-info">
                                        <div class="request-club"><?= htmlspecialchars($request['club_name']) ?></div>
                                        <?php if (!empty($request['request_notes'])): ?>
                                            <div class="request-notes">
                                                <i class="fas fa-sticky-note"></i> 
                                                <?= htmlspecialchars($request['request_notes']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="request-detail">
                                        <i class="fas fa-user-tie"></i>
                                        <?= htmlspecialchars($request['patron_name']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="request-detail">
                                        <i class="fas fa-user"></i>
                                        <?= htmlspecialchars($request['requested_by']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="request-detail">
                                        <i class="fas fa-calendar"></i>
                                        <?= date('M j, Y g:i a', strtotime($request['created_at'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="actions">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-approve">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-reject">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-requests">
                    <i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 20px; color: rgb(209, 120, 25);"></i>
                    <p>No pending patron requests at this time</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
[file content end]