<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "ics_project");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$club_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($club_id <= 0) {
    header("Location: home.php");
    exit();
}

// Check if club exists
$club_check = $conn->prepare("SELECT id, name FROM clubs WHERE id = ?");
$club_check->bind_param("i", $club_id);
$club_check->execute();
$club_result = $club_check->get_result();

if ($club_result->num_rows == 0) {
    header("Location: home.php");
    exit();
}

$club = $club_result->fetch_assoc();

// Check if user is already a member
$member_check = $conn->prepare("SELECT id FROM memberships WHERE user_id = ? AND club_id = ?");
$member_check->bind_param("ii", $user_id, $club_id);
$member_check->execute();

if ($member_check->get_result()->num_rows > 0) {
    // User is already a member
    $_SESSION['message'] = "You are already a member of " . $club['name'];
    $_SESSION['message_type'] = "info";
} else {
    // Add user to club
    $join_stmt = $conn->prepare("INSERT INTO memberships (user_id, club_id) VALUES (?, ?)");
    $join_stmt->bind_param("ii", $user_id, $club_id);
    
    if ($join_stmt->execute()) {
        $_SESSION['message'] = "Successfully joined " . $club['name'] . "!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error joining club. Please try again.";
        $_SESSION['message_type'] = "error";
    }
}

$conn->close();

// Redirect back to club details page
header("Location: club_details.php?id=" . $club_id);
exit();
?>