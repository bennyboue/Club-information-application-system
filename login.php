<?php
session_start();
$conn = new mysqli("localhost", "root", "", "ics_project");
$message = "";

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $username = trim($_POST['username']);
    $input_school_id = trim($_POST['school_id']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT id, username, password, role, school_id, surname FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $db_username, $hashed_password, $role, $db_school_id, $db_surname);
        $stmt->fetch();

        $valid = false;

        // Custom validation logic based on role
        if ($role === 'student') {
            // Student: Just ID (exact match)
            $valid = ($input_school_id === $db_school_id);
        } elseif ($role === 'club_manager') {
            // Club Manager: Check if the ID part matches and the user has the club initials in their school_id
            if (strpos($db_school_id, '-') !== false) {
                list($id_part, $club_initials) = explode('-', $db_school_id, 2);
                // Check if input matches either the full ID (ID-CLUB) or just the ID part
                $valid = ($input_school_id === $db_school_id) || ($input_school_id === $id_part);
            } else {
                // For backward compatibility
                $valid = ($input_school_id === $db_school_id);
            }
            
            if (!$valid) {
                $message = "❌ Club Manager: Use either your full ID (ID-CLUB) or just the ID part";
            }
        } elseif ($role === 'admin') {
            // Admin: ID-SURNAME format
            if (strpos($input_school_id, '-') !== false) {
                list($id_part, $surname_part) = explode('-', $input_school_id, 2);
                if ($db_school_id === $id_part && strtolower($surname_part) === strtolower($db_surname)) {
                    $valid = true;
                }
            }
            if (!$valid) {
                $message = "❌ Admin format should be: ID-SURNAME";
            }
        }

        if ($valid) {
            if (password_verify($password, $hashed_password)) {
                $_SESSION['user_id'] = $id;
                $_SESSION['username'] = $db_username;
                $_SESSION['role'] = $role;
                header("Location: home.php");
                exit();
            } else {
                $message = "❌ Invalid password.";
            }
        } elseif (empty($message)) {
            $message = "❌ Incorrect School ID format for role '$role'.";
        }
    } else {
        $message = "❌ Username not found.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <style>
      /* General body styling */
body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    background-color: rgb(169, 153, 136);
}

/* Container styling */
.container {
    background-color: #fff;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    padding: 40px;
    width: 400px;
    max-width: 90%;
    text-align: center;
}

/* Heading style */
.container h2 {
    color: rgb(209, 120, 25);
    margin-bottom: 30px;
    font-size: 24px;
}

/* Message/error styling */
.container p {
    color: red;
    margin-bottom: 15px;
    font-weight: bold;
}

/* Info box styling */
.info-box {
    background-color: #e8f4f8;
    border: 1px solid #bee5eb;
    border-radius: 5px;
    padding: 15px;
    margin-bottom: 20px;
    font-size: 12px;
    color: #0c5460;
    text-align: left;
}

.info-box strong {
    display: block;
    margin-bottom: 5px;
    font-size: 14px;
}

/* Input fields styling */
input[type="text"],
input[type="password"] {
    width: calc(100% - 20px);
    padding: 12px;
    margin-bottom: 15px;
    border: 1px solid rgb(209, 120, 25);
    border-radius: 5px;
    background-color: #f9f9f9;
    font-size: 14px;
    transition: all 0.3s ease;
}

input[type="text"]:focus,
input[type="password"]:focus {
    outline: none;
    border-color: rgb(150, 85, 10);
    background-color: #fff;
    box-shadow: 0 0 0 3px rgba(209, 120, 25, 0.2);
}

/* Button styling */
button {
    width: 100%;
    padding: 12px;
    background-color: rgb(209, 120, 25);
    color: #fff;
    border: none;
    border-radius: 5px;
    font-size: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
    margin-top: 10px;
}

button:hover {
    background-color: rgb(150, 85, 10);
    transform: translateY(-2px);
}

/* Links styling */
.links {
    margin-top: 20px;
    display: flex;
    justify-content: center;
    gap: 15px;
}

.links a {
    color: rgb(209, 120, 25);
    text-decoration: none;
    font-size: 14px;
    transition: all 0.3s ease;
}

.links a:hover {
    color: rgb(150, 85, 10);
    text-decoration: underline;
}

/* Responsive design */
@media (max-width: 500px) {
    .container {
        width: 90%;
        padding: 25px;
    }
    
    .container h2 {
        font-size: 20px;
    }
    
    button {
        padding: 10px;
    }
    
    .links {
        flex-direction: column;
        gap: 10px;
    }
}

/* Animation for better UX */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.container {
    animation: fadeIn 0.5s ease-out;
}

/* Focus styles for accessibility */
button:focus, 
input:focus {
    outline: 2px solid rgb(209, 120, 25);
    outline-offset: 2px;
}

/* Placeholder styling */
::placeholder {
    color: #999;
    opacity: 1;
}

:-ms-input-placeholder {
    color: #999;
}

::-ms-input-placeholder {
    color: #999;
}
    </style>
</head>
<body>
    <div class="container">
        <h2>Login to School Club Management System</h2>
        
        <div class="info-box">
            <strong>School ID Format:</strong>
            • Student: Just your ID (e.g., "12345")<br>
            • Club Manager: Either full ID (e.g., "12345-SC") or just the ID part (e.g., "12345")<br>
            • Admin: ID-SURNAME (e.g., "12345-Smith")
        </div>

        <?php if ($message != "") echo "<p>$message</p>"; ?>
        
        <form method="POST" action="">
            <input type="text" name="username" placeholder="Username" required>
            <input type="text" name="school_id" placeholder="School ID (see format above)" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        <div class="links">
            <a href="register.php">Don't have an account? Register</a>
            <a href="home.php">Back to Home</a>
        </div>
    </div>
</body>
</html>