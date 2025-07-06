
<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "ics_project");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if user is already a club patron
$patron_check = $conn->prepare("
    SELECT cm.id 
    FROM club_managers cm
    WHERE cm.user_id = ? AND cm.is_patron = 1
");
$patron_check->bind_param("i", $_SESSION['user_id']);
$patron_check->execute();
$patron_result = $patron_check->get_result();

if ($patron_result->num_rows > 0) {
    // User is already a patron, redirect to dashboard
    header('Location: club_manager_dashboard.php');
    exit();
}

$message = '';
$has_pending_request = false;

// Check if user already has a pending request - FIXED COLUMN NAME
$request_check = $conn->prepare("
    SELECT id, status 
    FROM patron_requests 
    WHERE user_id = ? AND status = 'pending'
");
$request_check->bind_param("i", $_SESSION['user_id']);
$request_check->execute();
$request_result = $request_check->get_result();

if ($request_result->num_rows > 0) {
    $has_pending_request = true;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request']) && !$has_pending_request) {
    $club_id = $_POST['club_id'];
    $request_notes = trim($_POST['request_notes']);
    
    // Insert request with requested_by field - FIXED COLUMN NAME
    $insert = $conn->prepare("
        INSERT INTO patron_requests (user_id, club_id, request_notes, status, requested_by)
        VALUES (?, ?, ?, 'pending', ?)
    ");
    $insert->bind_param("iisi", $_SESSION['user_id'], $club_id, $request_notes, $_SESSION['user_id']);
    
    if ($insert->execute()) {
        $has_pending_request = true;
        $message = "Your request has been submitted successfully!";
    } else {
        $message = "Error submitting request: " . $conn->error;
    }
}

// Get all clubs for dropdown
$clubs_query = $conn->query("SELECT id, name FROM clubs ORDER BY name");
$clubs = [];
while ($club = $clubs_query->fetch_assoc()) {
    $clubs[] = $club;
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Request Club Patron Status</title>
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
            max-width: 800px;
            margin: 50px auto;
            padding: 0 20px;
        }
        
        .request-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .request-header {
            text-align: center;
            margin-bottom: 30px;
            color: #2c3e50;
        }
        
        .request-header h2 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .status-card {
            background: #e9f7ef;
            border-left: 4px solid #28a745;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 4px;
        }
        
        .status-card.pending {
            background: #fff3cd;
            border-left-color: #ffc107;
        }
        
        .status-card h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #495057;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
            font-family: 'Times New Roman', Times, serif;
        }
        
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
        }
        
        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: background-color 0.3s;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background-color: rgb(150, 85, 10);
            color: white;
            width: 100%;
        }
        
        .btn-primary:hover {
            background-color: rgb(120, 68, 8);
        }
        
        .btn-disabled {
            background-color: #cccccc;
            color: #666666;
            cursor: not-allowed;
            width: 100%;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            text-align: center;
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
        
        .club-list {
            list-style: none;
            padding: 0;
        }
        
        .club-list li {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
        }
        
        .club-list li:last-child {
            border-bottom: none;
        }
        
        .club-name {
            font-weight: 500;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: rgb(150, 85, 10);
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="manager-navbar">
        <div class="logo">
            <h1>Club Management System</h1>
        </div>
        <div>
            <span class="manager-welcome">
                Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>
            </span>
            <a href="home.php">Home</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="manager-container">
        <div class="request-container">
            <div class="request-header">
                <h2>Request Club Patron Status</h2>
                <p>Submit a request to become a club patron for administration privileges</p>
            </div>
            
            <?php if ($message): ?>
                <div class="message <?php echo strpos($message, 'Error') === false ? 'success' : 'error'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($has_pending_request): ?>
                <div class="status-card pending">
                    <h3>Request Pending Review</h3>
                    <p>Your patron status request has been submitted and is awaiting approval from the system administrator.</p>
                    <p>You will receive a notification once your request has been processed. Thank you for your patience.</p>
                </div>
                
                <h3>Available Clubs</h3>
                <ul class="club-list">
                    <?php foreach ($clubs as $club): ?>
                        <li>
                            <span class="club-name"><?php echo htmlspecialchars($club['name']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <a href="home.php" class="back-link">← Return to Home</a>
            <?php else: ?>
                <div class="status-card">
                    <h3>How to become a Club Patron</h3>
                    <p>To manage a club as a patron, please select a club below and submit your request. The system administrator will review your request and assign patron privileges if approved.</p>
                    <p>You may include additional notes about your qualifications or reasons for requesting patron status.</p>
                </div>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="club_id">Select Club</label>
                        <select id="club_id" name="club_id" class="form-control" required>
                            <option value="">-- Choose a club to manage --</option>
                            <?php foreach ($clubs as $club): ?>
                                <option value="<?php echo $club['id']; ?>"><?php echo htmlspecialchars($club['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="request_notes">Additional Notes (Optional)</label>
                        <textarea id="request_notes" name="request_notes" class="form-control" placeholder="Explain why you should be the patron for this club..."></textarea>
                    </div>
                    
                    <button type="submit" name="submit_request" class="btn btn-primary">Submit Request</button>
                </form>
                
                <a href="home.php" class="back-link">← Return to Home</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>