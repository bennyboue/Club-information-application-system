<?php
$conn = new mysqli("localhost", "root", "", "ics_project");
$message = "";

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $username = trim($_POST['username']);
    $surname = trim($_POST['surname']);
    $email = trim($_POST['email']);
    $school_id = trim($_POST['school_id']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];

    $valid = false;
    $validation_error = "";

    if ($role === 'student') {
        if (!empty($school_id)) {
            $valid = true;
        } else {
            $validation_error = "School ID is required for students.";
        }
    } 
    elseif ($role === 'club_manager') {
        if (strpos($school_id, '-') !== false) {
            list($id_part, $club_initials) = explode('-', $school_id, 2);
            if (!empty($id_part) && !empty($club_initials)) {
            
                $club_stmt = $conn->prepare("SELECT id FROM clubs WHERE initials = ?");
                $club_stmt->bind_param("s", $club_initials);
                $club_stmt->execute();
                $club_stmt->store_result();
                
                if ($club_stmt->num_rows > 0) {
                    $valid = true;
                    $school_id = $id_part;
                } else {
                    $validation_error = "Club initials '$club_initials' not found.";
                }
                $club_stmt->close();
            } else {
                $validation_error = "Club Manager format should be: ID-CLUB_INITIALS";
            }
        } else {
            $validation_error = "Club Manager format should be: ID-CLUB_INITIALS";
        }
    } 
    elseif ($role === 'admin') {
        if (strpos($school_id, '-') !== false) {
            list($id_part, $surname_part) = explode('-', $school_id, 2);
            if (!empty($id_part) && !empty($surname_part)) {
                if (strtolower($surname_part) === strtolower($surname)) {
                    $valid = true;
                    $school_id = $id_part;
                } else {
                    $validation_error = "Surname in School ID doesn't match provided surname.";
                }
            } else {
                $validation_error = "Admin format should be: ID-SURNAME";
            }
        } else {
            $validation_error = "Admin format should be: ID-SURNAME";
        }
    }

    if ($valid) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (username, email, school_id, surname, password, role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $username, $email, $school_id, $surname, $hashed_password, $role);

        if ($stmt->execute()) {
            $message = "✅ Registration successful. <a href='login.php'>Login here</a>";
        } else {
            $message = "❌ Error: " . $conn->error;
        }
        $stmt->close();
    } else {
        $message = "❌ " . $validation_error;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <style>
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

        .container {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 400px;
            max-width: 90%;
        }

        .container h2 {
            text-align: center;
            color: rgb(209, 120, 25);
            margin-bottom: 30px;
            font-size: 24px;
        }

        .container p {
            color: red;
            margin-bottom: 15px;
            font-weight: bold;
            text-align: center;
        }

        .container p.success {
            color: green;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: calc(100% - 20px);
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid rgb(209, 120, 25);
            border-radius: 5px;
            background-color: #f9f9f9;
            font-size: 14px;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        select:focus {
            outline: none;
            border-color: rgb(150, 85, 10);
            background-color: #fff;
        }

        button {
            width: 100%;
            padding: 12px;
            background-color: rgb(209, 120, 25);
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: rgb(150, 85, 10);
        }

        .info-box {
            background-color: #e8f4f8;
            border: 1px solid #bee5eb;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 12px;
            color: #0c5460;
        }

        .info-box strong {
            display: block;
            margin-bottom: 5px;
        }

        .links {
            text-align: center;
            margin-top: 20px;
        }

        .links a {
            color: rgb(209, 120, 25);
            text-decoration: none;
            margin: 0 10px;
        }

        .links a:hover {
            text-decoration: underline;
        }

        @media (max-width: 500px) {
            .container {
                width: 90%;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Register for School Club Management System</h2>
        
        <div class="info-box">
            <strong>School ID Format Requirements:</strong>
            • Student: Just your ID (e.g., "12345")<br>
            • Club Patron: ID-CLUB_INITIALS (e.g., "12345-SC" for Sports Club)<br>
            • Admin: ID-SURNAME (e.g., "12345-Smith")
        </div>

        <?php if ($message != ""): ?>
            <p class="<?php echo strpos($message, '✅') !== false ? 'success' : ''; ?>">
                <?php echo $message; ?>
            </p>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="text" name="username" placeholder="Username" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="text" name="surname" placeholder="Surname" required>
            
            <select name="role" id="role" required onchange="updateSchoolIdPlaceholder()">
                <option value="">-- Select Role --</option>
                <option value="student">Student</option>
                <option value="club_patron">Club Patron</option>
                <option value="admin">School Admin</option>
            </select>

            <input type="text" name="school_id" id="school_id" placeholder="School ID" required>
            <input type="password" name="password" placeholder="Password" required>

            <button type="submit">Register</button>
        </form>

        <div class="links">
            <a href="login.php">Already have an account? Login</a>
            <a href="home.php">Back to Home</a>
        </div>
    </div>

    <script>
        function updateSchoolIdPlaceholder() {
            const role = document.getElementById('role').value;
            const schoolIdInput = document.getElementById('school_id');
            
            switch(role) {
                case 'student':
                    schoolIdInput.placeholder = 'School ID (e.g., 12345)';
                    break;
                case 'club_patron':
                    schoolIdInput.placeholder = 'ID-CLUB_INITIALS (e.g., 12345-SC)';
                    break;
                case 'admin':
                    schoolIdInput.placeholder = 'ID-SURNAME (e.g., 12345-Smith)';
                    break;
                default:
                    schoolIdInput.placeholder = 'School ID';
            }
        }
    </script>
</body>
</html>