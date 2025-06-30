<?php
session_start();

// Fetch clubs from database for dropdown
$conn = new mysqli("localhost", "root","", "ics_project");
$clubs = [];
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$result = $conn->query("SELECT id, name, initials, description FROM clubs ORDER BY name");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $clubs[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>School Club Management System - Home</title>
 <style>
    /* Base Styles */
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        margin: 0;
        padding: 0;
        background-color: rgb(169, 153, 136);
        line-height: 1.6;
        color: #333;
    }

    /* Typography Improvements */
    h1, h2, h3, h4, h5, h6 {
        margin-top: 0;
        line-height: 1.2;
    }

    p {
        margin-bottom: 1em;
    }

    /* Navbar Enhancements */
    .navbar {
        background-color: rgb(237, 222, 203);
        padding: 15px 5%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        position: sticky;
        top: 0;
        z-index: 1000;
    }

    .navbar .logo {
        font-size: 1.1rem;
        font-weight: bold;
        color: black;
    }

    .navbar .logo p {
        margin: 5px 0 0;
        font-size: 0.9rem;
        font-weight: normal;
        color: #555;
    }

    .navbar a {
        background-color: rgb(209, 120, 25);
        color: #fff;
        padding: 10px 18px;
        text-decoration: none;
        border-radius: 5px;
        transition: all 0.3s ease;
        font-weight: 500;
        margin-left: 10px;
        font-size: 0.95rem;
    }

    .navbar a:hover {
        background-color: rgb(150, 85, 10);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .user-welcome {
        background-color: rgb(209, 120, 25);
        color: #fff;
        padding: 10px 18px;
        border-radius: 5px;
        font-weight: bold;
        margin-right: 10px;
        display: inline-block;
    }

    /* Image Gallery Improvements */
    .image-gallery {
        display: flex;
        justify-content: center;
        gap: 25px;
        margin: 40px auto;
        max-width: 1200px;
        padding: 0 20px;
        flex-wrap: wrap;
    }

    .image-container {
        position: relative;
        width: 22%;
        min-width: 250px;
        display: flex;
        flex-direction: column;
        transition: all 0.3s ease;
    }

    .image-gallery img {
        width: 100%;
        height: 280px;
        object-fit: cover;
        border-radius: 10px;
        transition: all 0.3s ease;
        cursor: pointer;
        border: 3px solid transparent;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .image-gallery img:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        border-color: rgb(209, 120, 25);
    }

    .image-gallery img.active {
        border-color: rgb(209, 120, 25);
        transform: translateY(-3px);
    }

    /* Club Dropdown Enhancements */
    .club-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background-color: rgba(255, 255, 255, 0.98);
        border-radius: 0 0 10px 10px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        display: none;
        z-index: 1000;
        max-height: 400px;
        overflow-y: auto;
        margin-top: 5px;
        backdrop-filter: blur(5px);
        border-top: 3px solid rgb(209, 120, 25);
    }

    .club-dropdown.show {
        display: block;
        animation: slideDown 0.3s ease-out;
    }

    .dropdown-header {
        background-color: rgb(209, 120, 25);
        color: white;
        padding: 15px;
        text-align: center;
        font-weight: bold;
        font-size: 1.1rem;
        position: sticky;
        top: 0;
        z-index: 1;
    }

    .club-item {
        background-color: rgb(237, 222, 203);
        margin: 10px;
        padding: 15px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid transparent;
        position: relative;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    }

    .club-item:hover {
        background-color: rgb(209, 120, 25);
        color: white;
        transform: translateX(5px);
        border-color: rgb(150, 85, 10);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .club-name {
        font-weight: bold;
        font-size: 1rem;
        margin-bottom: 8px;
    }

    .club-initials {
        background-color: rgb(209, 120, 25);
        color: white;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.75rem;
        display: inline-block;
        margin-bottom: 8px;
        font-weight: bold;
    }

    .club-item:hover .club-initials {
        background-color: white;
        color: rgb(209, 120, 25);
    }

    .club-description {
        font-size: 0.85rem;
        color: #666;
        line-height: 1.5;
        margin-bottom: 10px;
    }

    .club-item:hover .club-description {
        color: rgba(255, 255, 255, 0.9);
    }

    .join-btn {
        background-color: rgb(209, 120, 25);
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 0.8rem;
        font-weight: bold;
        transition: all 0.3s ease;
        position: absolute;
        bottom: 10px;
        right: 10px;
    }

    .join-btn:hover {
        background-color: rgb(150, 85, 10);
        transform: scale(1.05);
    }

    .club-item:hover .join-btn {
        background-color: white;
        color: rgb(209, 120, 25);
    }

    .join-btn.joined {
        background-color: #28a745;
        cursor: default;
    }

    .join-btn.joined:hover {
        background-color: #28a745;
        transform: none;
    }

    /* Encouragement Section Improvements */
    .encouragement-section {
        background-color: rgb(237, 222, 203);
        margin: 50px 5%;
        padding: 50px;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        border: 3px solid rgb(209, 120, 25);
    }

    .encouragement-header {
        text-align: center;
        color: rgb(209, 120, 25);
        font-size: 2rem;
        font-weight: bold;
        margin-bottom: 30px;
        position: relative;
        padding-bottom: 15px;
    }

    .encouragement-header:after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 100px;
        height: 3px;
        background-color: rgb(209, 120, 25);
    }

    .encouragement-content {
        font-size: 1.1rem;
        line-height: 1.7;
        color: #333;
        text-align: center;
        margin-bottom: 40px;
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;
    }

    .benefits-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 25px;
        margin: 40px 0;
    }

    .benefit-card {
        background-color: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        border-left: 5px solid rgb(209, 120, 25);
        position: relative;
        overflow: hidden;
    }

    .benefit-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 25px rgba(0, 0, 0, 0.1);
    }

    .benefit-icon {
        font-size: 2rem;
        margin-bottom: 15px;
        display: block;
        color: rgb(209, 120, 25);
    }

    .benefit-title {
        font-weight: bold;
        color: rgb(209, 120, 25);
        font-size: 1.2rem;
        margin-bottom: 12px;
    }

    .benefit-description {
        color: #666;
        font-size: 0.95rem;
        line-height: 1.6;
    }

    /* Events Section Improvements */
    .events-section {
        background: linear-gradient(135deg, rgb(169, 153, 136), rgb(237, 222, 203));
        margin: 50px 5%;
        padding: 50px;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    .events-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 30px;
        margin-top: 40px;
    }

    .event-card {
        background-color: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        border-top: 4px solid rgb(209, 120, 25);
        position: relative;
    }

    .event-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 25px rgba(0, 0, 0, 0.1);
    }

    .event-title {
        font-weight: bold;
        color: rgb(209, 120, 25);
        font-size: 1.3rem;
        margin-bottom: 12px;
    }

    .event-club {
        color: #666;
        font-style: italic;
        margin-bottom: 15px;
        font-size: 0.9rem;
    }

    .event-description {
        color: #333;
        line-height: 1.7;
        margin-bottom: 20px;
        font-size: 0.95rem;
    }

    .event-impact {
        background-color: rgb(237, 222, 203);
        padding: 12px;
        border-radius: 8px;
        font-style: italic;
        color: rgb(150, 85, 10);
        font-size: 0.9rem;
        border-left: 3px solid rgb(209, 120, 25);
    }

    /* Memories Section Improvements */
    .memories-section {
        background-color: rgb(237, 222, 203);
        margin: 50px 5%;
        padding: 50px;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        border: 3px solid rgb(209, 120, 25);
    }

    .memories-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 25px;
        margin-top: 40px;
    }

    .memory-card {
        background-color: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        position: relative;
        border-left: 4px solid rgb(209, 120, 25);
        transition: all 0.3s ease;
    }

    .memory-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .memory-quote {
        font-style: italic;
        color: #333;
        font-size: 1rem;
        line-height: 1.7;
        margin-bottom: 20px;
        position: relative;
        padding-left: 20px;
    }

    .memory-quote:before {
        content: '"';
        position: absolute;
        left: 0;
        top: 0;
        font-size: 2rem;
        color: rgba(209, 120, 25, 0.2);
        line-height: 1;
    }

    .memory-author {
        color: rgb(209, 120, 25);
        font-weight: bold;
        font-size: 0.95rem;
    }

    .memory-club {
        color: #666;
        font-size: 0.85rem;
        margin-top: 5px;
    }

    /* Call to Action Improvements */
    .call-to-action {
        background: linear-gradient(135deg, rgb(209, 120, 25), rgb(150, 85, 10));
        color: white;
        padding: 40px;
        border-radius: 10px;
        text-align: center;
        margin: 50px 0 0;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .call-to-action h3 {
        font-size: 1.5rem;
        margin-bottom: 15px;
    }

    .call-to-action p {
        font-size: 1.1rem;
        max-width: 700px;
        margin-left: auto;
        margin-right: auto;
    }

    .cta-button {
        background-color: white;
        color: rgb(209, 120, 25);
        padding: 15px 35px;
        border: none;
        border-radius: 30px;
        font-size: 1.1rem;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-top: 20px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    .cta-button:hover {
        background-color: rgb(237, 222, 203);
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    }

    /* Footer Improvements */
    .footer {
        background-color: rgb(237, 222, 203);
        color: #333;
        padding: 60px 0 0;
        margin-top: 80px;
        border-top: 3px solid rgb(209, 120, 25);
    }

    .footer-content {
        max-width: 1200px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 40px;
        padding: 0 30px;
    }

    .footer-section {
        margin-bottom: 30px;
    }

    .footer-section h3 {
        color: rgb(209, 120, 25);
        font-size: 1.3rem;
        margin-bottom: 20px;
        padding-bottom: 10px;
        position: relative;
    }

    .footer-section h3:after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 50px;
        height: 2px;
        background-color: rgb(209, 120, 25);
    }

    .footer-section p, .footer-section ul {
        margin: 15px 0;
        line-height: 1.7;
        font-size: 0.95rem;
    }

    .footer-section ul {
        list-style: none;
        padding: 0;
    }

    .footer-section ul li {
        margin: 12px 0;
        padding-left: 25px;
        position: relative;
    }

    .footer-section ul li:before {
        content: "▶";
        color: rgb(209, 120, 25);
        position: absolute;
        left: 0;
        font-size: 0.8rem;
    }

    .contact-info {
        background-color: rgb(209, 120, 25);
        color: white;
        padding: 15px;
        border-radius: 8px;
        margin-top: 20px;
        font-size: 0.95rem;
    }

    .contact-info strong {
        display: block;
        margin-bottom: 8px;
        font-size: 1rem;
    }

    .footer-bottom {
        text-align: center;
        margin-top: 50px;
        padding: 20px 0;
        border-top: 1px solid rgba(209, 120, 25, 0.3);
        color: #666;
        font-size: 0.9rem;
    }

    /* Success Message */
    .success-message {
        position: fixed;
        top: 20px;
        right: 20px;
        background-color: #28a745;
        color: white;
        padding: 15px 25px;
        border-radius: 5px;
        z-index: 10000;
        display: none;
        animation: slideInRight 0.3s ease-out;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    /* Animations */
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-15px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    /* Responsive Design */
    @media (max-width: 992px) {
        .image-gallery {
            gap: 20px;
        }
        
        .image-container {
            width: 45%;
        }
    }

    @media (max-width: 768px) {
        .navbar {
            flex-direction: column;
            padding: 15px;
        }
        
        .navbar .logo {
            margin-bottom: 15px;
            text-align: center;
        }
        
        .navbar a {
            margin: 5px;
        }
        
        .user-welcome {
            margin: 10px 0;
            display: block;
            text-align: center;
        }
        
        .image-container {
            width: 100%;
            max-width: 350px;
        }
        
        .club-dropdown {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 400px;
            max-height: 70vh;
        }
        
        .encouragement-section,
        .events-section,
        .memories-section {
            padding: 30px;
            margin: 30px 3%;
        }
        
        .footer-content {
            grid-template-columns: 1fr;
            padding: 0 20px;
        }
    }

    @media (max-width: 480px) {
        .encouragement-section,
        .events-section,
        .memories-section {
            padding: 25px 15px;
            margin: 25px 2%;
        }
        
        .encouragement-header {
            font-size: 1.5rem;
        }
        
        .call-to-action {
            padding: 25px 15px;
        }
        
        .cta-button {
            padding: 12px 25px;
            font-size: 1rem;
        }
    }
</style>
</head>
<body>

    <div class="navbar">
        <div class="logo">
            <h1>Welcome to the School Club Management System</h1>
            <p>Manage your clubs and events efficiently.</p>
        </div>
        <div>
            <?php if (isset($_SESSION['username'])): ?>
                <span class="user-welcome">
                    Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!
                </span>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="admin_dashboard.php">Admin Dashboard</a>
                <?php elseif ($_SESSION['role'] === 'club_manager'): ?>
                    <a href="manager_dashboard.php">Manager Dashboard</a>
                <?php else: ?>
                    <a href="student_dashboard.php">Dashboard</a>
                <?php endif; ?>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="register.php">Register</a>
                <a href="login.php">Login</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Success message -->
    <div class="success-message" id="successMessage"></div>

    <!-- Overlay for closing dropdown -->
    <div class="dropdown-overlay" id="dropdownOverlay"></div>

    <div class="image-gallery">
        <!-- Brain/Academic Oriented Clubs -->
        <div class="image-container">
            <img src="download (1).jpg" alt="Brain & Academic Clubs" onclick="toggleDropdown(0, 'brain')">
            <div class="club-dropdown" id="dropdown-0">
                <div class="dropdown-header">Brain & Academic Clubs</div>
                <div id="clubs-brain">
                    <?php 
                    $brain_clubs = ['Chess Club', 'Scrabble Society', 'Mathematics Olympiad Club', 'Debate & Critical Thinking Club'];
                    $found_brain = false;
                    foreach($clubs as $club): 
                        if (in_array($club['name'], $brain_clubs)):
                            $found_brain = true;
                    ?>
                        <div class="club-item" onclick="goToClub(<?php echo $club['id']; ?>)">
                            <div class="club-name"><?php echo htmlspecialchars($club['name']); ?></div>
                            <span class="club-initials"><?php echo htmlspecialchars($club['initials']); ?></span>
                            <div class="club-description"><?php echo htmlspecialchars($club['description']); ?></div>
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'student'): ?>
                                <button class="join-btn" onclick="joinClub(<?php echo $club['id']; ?>, event)" data-club-id="<?php echo $club['id']; ?>">Join Club</button>
                            <?php endif; ?>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    if (!$found_brain): ?>
                        <div class="no-clubs">No brain & academic clubs available</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Artistic/Creative Clubs -->
        <div class="image-container">
            <img src="download (2).jpg" alt="Artistic & Creative Clubs" onclick="toggleDropdown(1, 'artistic')">
            <div class="club-dropdown" id="dropdown-1">
                <div class="dropdown-header">Artistic & Creative Clubs</div>
                <div id="clubs-artistic">
                    <?php 
                    $artistic_clubs = ['Painting & Visual Arts Club', 'Sculpting & 3D Arts Club', 'Music Harmony Club', 'Creative Writing & Literature Club'];
                    $found_artistic = false;
                    foreach($clubs as $club): 
                        if (in_array($club['name'], $artistic_clubs)):
                            $found_artistic = true;
                    ?>
                        <div class="club-item" onclick="goToClub(<?php echo $club['id']; ?>)">
                            <div class="club-name"><?php echo htmlspecialchars($club['name']); ?></div>
                            <span class="club-initials"><?php echo htmlspecialchars($club['initials']); ?></span>
                            <div class="club-description"><?php echo htmlspecialchars($club['description']); ?></div>
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'student'): ?>
                                <button class="join-btn" onclick="joinClub(<?php echo $club['id']; ?>, event)" data-club-id="<?php echo $club['id']; ?>">Join Club</button>
                            <?php endif; ?>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    if (!$found_artistic): ?>
                        <div class="no-clubs">No artistic & creative clubs available</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Science & Technology Clubs -->
        <div class="image-container">
            <img src="download (3).jpg" alt="Science & Technology Clubs" onclick="toggleDropdown(2, 'science')">
            <div class="club-dropdown" id="dropdown-2">
                <div class="dropdown-header">Science & Technology Clubs</div>
                <div id="clubs-science">
                    <?php 
                    $science_clubs = ['Physics & Astronomy Club', 'Chemistry Lab Enthusiasts', 'Biology & Environmental Science Club', 'Robotics & Engineering Club'];
                    $found_science = false;
                    foreach($clubs as $club): 
                        if (in_array($club['name'], $science_clubs)):
                            $found_science = true;
                    ?>
                        <div class="club-item" onclick="goToClub(<?php echo $club['id']; ?>)">
                            <div class="club-name"><?php echo htmlspecialchars($club['name']); ?></div>
                            <span class="club-initials"><?php echo htmlspecialchars($club['initials']); ?></span>
                            <div class="club-description"><?php echo htmlspecialchars($club['description']); ?></div>
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'student'): ?>
                                <button class="join-btn" onclick="joinClub(<?php echo $club['id']; ?>, event)" data-club-id="<?php echo $club['id']; ?>">Join Club</button>
                            <?php endif; ?>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    if (!$found_science): ?>
                        <div class="no-clubs">No science & technology clubs available</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sports & Physical Activities Clubs -->
        <div class="image-container">
            <img src="Sports Logo PNG vector in SVG, PDF, AI, CDR format.jpg" alt="Sports & Physical Activities" onclick="toggleDropdown(3, 'sports')">
            <div class="club-dropdown" id="dropdown-3">
                <div class="dropdown-header">Sports & Physical Activities</div>
                <div id="clubs-sports">
                    <?php 
                    $sports_clubs = ['Football Champions Club', 'Basketball Elite Club', 'Track & Field Athletics Club', 'Swimming & Aquatics Club'];
                    $found_sports = false;
                    foreach($clubs as $club): 
                        if (in_array($club['name'], $sports_clubs)):
                            $found_sports = true;
                    ?>
                        <div class="club-item" onclick="goToClub(<?php echo $club['id']; ?>)">
                            <div class="club-name"><?php echo htmlspecialchars($club['name']); ?></div>
                            <span class="club-initials"><?php echo htmlspecialchars($club['initials']); ?></span>
                            <div class="club-description"><?php echo htmlspecialchars($club['description']); ?></div>
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'student'): ?>
                                <button class="join-btn" onclick="joinClub(<?php echo $club['id']; ?>, event)" data-club-id="<?php echo $club['id']; ?>">Join Club</button>
                            <?php endif; ?>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    if (!$found_sports): ?>
                        <div class="no-clubs">No sports & physical activities clubs available</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!--ENCOURAGEMENT SECTION -->
    <div class="encouragement-section">
        <h2 class="encouragement-header">🌟 Why Join a Club? Transform Your University Experience! 🌟</h2>
        <div class="encouragement-content">
            <p>University is more than just lectures and exams - it's about discovering who you are, building lifelong friendships, and developing skills that will serve you for years to come. Our clubs are the gateway to an enriched, fulfilling university experience!</p>
        </div>
        
        <div class="benefits-grid">
            <div class="benefit-card">
                <span class="benefit-icon">🤝</span>
                <div class="benefit-title">Build Lasting Friendships</div>
                <div class="benefit-description">Connect with like-minded peers who share your interests and passions. Many of our alumni still maintain friendships formed in clubs decades later!</div>
            </div>
            
            <div class="benefit-card">
                <span class="benefit-icon">🚀</span>
                <div class="benefit-title">Develop Leadership Skills</div>
                <div class="benefit-description">Take on leadership roles, organize events, and develop skills that employers value. Our club leaders often become successful entrepreneurs and executives.</div>
            </div>
            
            <div class="benefit-card">
                <span class="benefit-icon">🎯</span>
                <div class="benefit-title">Discover Your Passion</div>
                <div class="benefit-description">Explore new interests, discover hidden talents, and find what truly excites you. Many students have changed their career paths after discovering new passions in clubs.</div>
            </div>
            
            <div class="benefit-card">
                <span class="benefit-icon">💼</span>
                <div class="benefit-title">Boost Your Resume</div>
                <div class="benefit-description">Stand out to employers with demonstrated extracurricular involvement, leadership experience, and practical skills gained through club activities.</div>
            </div>
            
            <div class="benefit-card">
                <span class="benefit-icon">🌍</span>
                <div class="benefit-title">Make a Difference</div>
                <div class="benefit-description">Contribute to your community, organize charity events, and be part of initiatives that create positive change on campus and beyond.</div>
            </div>
            
            <div class="benefit-card">
                <span class="benefit-icon">🧠</span>
                <div class="benefit-title">Learn Beyond the Classroom</div>
                <div class="benefit-description">Gain practical experience, develop creative skills, and learn from peers in a fun, supportive environment that complements your academic studies.</div>
            </div>
        </div>
        
        <div class="call-to-action">
            <h3>Ready to Transform Your University Experience?</h3>
            <p>Don't let this opportunity pass you by! Join a club today and become part of something amazing.</p>
            <button class="cta-button" onclick="scrollToClubs()">Explore Clubs Above ↑</button>
        </div>
    </div>

    <!-- RENOWNED CLUB EVENTS SECTION -->
    <div class="events-section">
        <h2 class="encouragement-header">🏆 Renowned Club Events & Achievements 🏆</h2>
        <div class="encouragement-content">
            <p>Our clubs don't just meet - they make history! Here are some of our most celebrated events and achievements that have put our university on the map.</p>
        </div>
        
        <div class="events-grid">
            <div class="event-card">
                <div class="event-title">Annual Inter-University Chess Championship</div>
                <div class="event-club">Chess Club</div>
                <div class="event-description">
                    Our Chess Club hosts the region's most prestigious inter-university chess tournament, attracting over 200 participants from 15 universities. The event has been running for 12 years and has produced 3 national champions.
                </div>
                <div class="event-impact">
                    "This tournament launched my career as a professional chess player. The level of competition and organization was exceptional!" - Alumni Winner 2019
                </div>
            </div>
            
            <div class="event-card">
                <div class="event-title">Innovation & Robotics Expo</div>
                <div class="event-club">Robotics & Engineering Club</div>
                <div class="event-description">
                    An annual showcase of cutting-edge technology and innovation, featuring student-built robots, AI demonstrations, and tech startups. Last year's expo attracted over 5,000 visitors including industry leaders and venture capitalists.
                </div>
                <div class="event-impact">
                    Two startups launched at our expo have received over $2M in funding and are now successful tech companies!
                </div>
            </div>
            
            <div class="event-card">
                <div class="event-title">Art Gallery Night & Cultural Festival</div>
                <div class="event-club">Painting & Visual Arts Club</div>
                <div class="event-description">
                    A magical evening where our campus transforms into an art gallery, featuring student artwork, live performances, and cultural exhibitions. The event has become the most anticipated cultural celebration of the year.
                </div>
                <div class="event-impact">
                    Several student artists have been discovered at this event and now have their work displayed in professional galleries!
                </div>
            </div>
            
            <div class="event-card">
                <div class="event-title">National Debate Championship</div>
                <div class="event-club">Debate & Critical Thinking Club</div>
                <div class="event-description">
                    Our debate team has won the National University Debate Championship 3 times in the last 5 years. They tackle contemporary issues and have debated in front of government officials and policy makers.
                </div>
                <div class="event-impact">
                    Many of our debaters have gone on to become lawyers, politicians, and public speakers who shape national discourse.
                </div>
            </div>
            
            <div class="event-card">
                <div class="event-title">Sports Excellence Awards Gala</div>
                <div class="event-club">All Sports Clubs</div>
                <div class="event-description">
                    An annual celebration of athletic achievement where we honor outstanding student-athletes, coaches, and sports achievements. The gala attracts professional scouts and sponsors.
                </div>
                <div class="event-impact">
                    15+ students have received sports scholarships and professional contracts through connections made at this event!
                </div>
            </div>
            
            <div class="event-card">
                <div class="event-title">Science Fair & Research Symposium</div>
                <div class="event-club">Science & Technology Clubs</div>
                <div class="event-description">
                    A platform for students to present their research projects and scientific innovations. The event attracts researchers, professors, and industry experts who provide mentorship and collaboration opportunities.
                </div>
                <div class="event-impact">
                    Student research presented here has been published in academic journals and patent applications have been filed!
                </div>
            </div>
        </div>
    </div>

    <!-- MEMORABLE MOMENTS SECTION -->
    <div class="memories-section">
        <h2 class="encouragement-header">💭 Memorable Moments & Student Testimonials 💭</h2>
        <div class="encouragement-content">
            <p>Don't just take our word for it - hear from students whose lives have been transformed by their club experiences!</p>
        </div>
        
        <div class="memories-grid">
            <div class="memory-card">
                <div class="memory-quote">
                    "Joining the Chess Club was the best decision I made in university. Not only did I improve my strategic thinking, but I also met my best friends and even my future business partner. We now run a successful consulting firm together!"
                </div>
                <div class="memory-author">- Sarah M., Business Graduate 2022</div>
                <div class="memory-club">Chess Club Alumni</div>
            </div>
            
            <div class="memory-card">
                <div class="memory-quote">
                    "The Robotics Club taught me more about engineering than any textbook ever could. Building robots with my teammates prepared me for my dream job at a leading tech company. The hands-on experience was invaluable."
                </div>
                <div class="memory-author">- David K., Engineering Graduate 2023</div>
                <div class="memory-club">Robotics & Engineering Club Alumni</div>
            </div>
            
            <div class="memory-card">
                <div class="memory-quote">
                    "I was shy and struggled to make friends until I joined the Music Club. Through jam sessions and performances, I found my voice - literally and figuratively. I'm now a confident performer and music teacher."
                </div>
                <div class="memory-author">- Emma L., Music Education Graduate 2021</div>
                <div class="memory-club">Music Harmony Club Alumni</div>
            </div>
            
            <div class="memory-card">
                <div class="memory-quote">
                    "The Debate Club transformed me from someone who was afraid to speak in public to someone who now addresses boardrooms with confidence. The critical thinking skills I developed have been crucial in my legal career."
                </div>
                <div class="memory-author">- Michael R., Law Graduate 2020</div>
                <div class="memory-club">Debate & Critical Thinking Club Alumni</div>
            </div>
            
            <div class="memory-card">
                <div class="memory-quote">
                    "Painting Club wasn't just about art - it was therapy, friendship, and self-discovery all rolled into one. I discovered my passion for art therapy and now help others heal through creative expression."
                </div>
                <div class="memory-author">- Lisa T., Psychology Graduate 2022</div>
                <div class="memory-club">Painting & Visual Arts Club Alumni</div>
            </div>
            
            <div class="memory-card">
                <div class="memory-quote">
                    "The Basketball Club taught me teamwork, discipline, and perseverance. Even though I didn't go professional, the leadership skills I developed as team captain have been essential in my management career."
                </div>
                <div class="memory-author">- James P., Business Administration Graduate 2021</div>
                <div class="memory-club">Basketball Elite Club Alumni</div>
            </div>
            
            <div class="memory-card">
                <div class="memory-quote">
                    "Through the Creative Writing Club, I published my first short story, won a university literary award, and gained the confidence to pursue my dream of becoming a novelist. My first book comes out next year!"
                </div>
                <div class="memory-author">- Anna C., Literature Graduate 2023</div>
                <div class="memory-club">Creative Writing & Literature Club Alumni</div>
            </div>
            
            <div class="memory-card">
                <div class="memory-quote">
                    "The Science Club opened doors I never knew existed. Presenting my research at the annual symposium led to an internship opportunity that turned into my current PhD program. Science club changed my life trajectory!"
                </div>
                <div class="memory-author">- Robert H., Chemistry Graduate 2022</div>
                <div class="memory-club">Chemistry Lab Enthusiasts Alumni</div>
            </div>
        </div>
        
        <div class="call-to-action">
            <h3>Your Story Could Be Next!</h3>
            <p>These are just some of the amazing stories from our club alumni. What will your club story be?</p>
            <button class="cta-button" onclick="scrollToClubs()">Start Your Journey Today ↑</button>
        </div>
    </div>

    <script>
        let currentDropdown = null;

        // Load user's joined clubs on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadUserMemberships();
        });

        function toggleDropdown(index, category) {
            const dropdown = document.getElementById(`dropdown-${index}`);
            const overlay = document.getElementById('dropdownOverlay');
            const img = dropdown.previousElementSibling;
            const container = img.parentElement;

            // Close current dropdown if different one is clicked
            if (currentDropdown && currentDropdown !== dropdown) {
                currentDropdown.classList.remove('show');
                currentDropdown.previousElementSibling.classList.remove('active');
                currentDropdown.closest('.image-container').classList.remove('dropdown-open');
                overlay.classList.remove('show');
            }

            // Toggle clicked dropdown
            if (dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
                img.classList.remove('active');
                container.classList.remove('dropdown-open');
                overlay.classList.remove('show');
                currentDropdown = null;
            } else {
                dropdown.classList.add('show');
                img.classList.add('active');
                container.classList.add('dropdown-open');
                overlay.classList.add('show');
                currentDropdown = dropdown;
            }
        }

        function goToClub(clubId) {
            // Redirect to individual club page
            window.location.href = `club_details.php?id=${clubId}`;
        }

        function joinClub(clubId, event) {
            event.stopPropagation(); // Prevent triggering goToClub
            
            const button = event.target;
            
            // Check if already joined
            if (button.classList.contains('joined')) {
                return;
            }

            // Send AJAX request to join club
            fetch('join_club.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'club_id=' + clubId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.textContent = 'Joined ✓';
                    button.classList.add('joined');
                    showSuccessMessage('Successfully joined the club!');
                } else {
                    if (data.message === 'already_member') {
                        button.textContent = 'Joined ✓';
                        button.classList.add('joined');
                        showSuccessMessage('You are already a member of this club!');
                    } else {
                        alert('Error joining club: ' + data.message);
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while joining the club.');
            });
        }

        function loadUserMemberships() {
            // Load user's current memberships and update buttons
            fetch('get_user_memberships.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    data.memberships.forEach(clubId => {
                        const button = document.querySelector(`[data-club-id="${clubId}"]`);
                        if (button) {
                            button.textContent = 'Joined ✓';
                            button.classList.add('joined');
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Error loading memberships:', error);
            });
        }

        function showSuccessMessage(message) {
            const successMsg = document.getElementById('successMessage');
            successMsg.textContent = message;
            successMsg.classList.add('show');
            
            setTimeout(() => {
                successMsg.classList.remove('show');
            }, 3000);
        }

        function scrollToClubs() {
            document.querySelector('.image-gallery').scrollIntoView({
                behavior: 'smooth'
            });
        }

        // Close dropdown when clicking overlay
        document.getElementById('dropdownOverlay').addEventListener('click', function() {
            if (currentDropdown) {
                currentDropdown.classList.remove('show');
                currentDropdown.previousElementSibling.classList.remove('active');
                currentDropdown.closest('.image-container').classList.remove('dropdown-open');
                this.classList.remove('show');
                currentDropdown = null;
            }
        });

        // Close dropdown when pressing Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && currentDropdown) {
                currentDropdown.classList.remove('show');
                currentDropdown.previousElementSibling.classList.remove('active');
                currentDropdown.closest('.image-container').classList.remove('dropdown-open');
                document.getElementById('dropdownOverlay').classList.remove('show');
                currentDropdown = null;
            }
        });
    </script>

    <!-- Footer Section -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>About Us</h3>
                <p>The School Club Management System is dedicated to fostering student engagement and personal growth through organized extracurricular activities. Since our establishment, we have been the bridge connecting students with their passions and helping them discover new interests.</p>

                <p>Our platform serves as the central hub for all club activities, bringing together diverse communities of learners who share common interests in academics, arts, sciences, and sports.</p>
                
                <p><strong>Our Mission:</strong> To enrich the university experience by providing students with opportunities to develop leadership skills, build meaningful relationships, and pursue their passions beyond the classroom.</p>
                
                <p><strong>Our Vision:</strong> To create a vibrant campus community where every student finds their place, develops their potential, and contributes to positive change.</p>
            </div>
            
            <div class="footer-section">
                <h3>Contact Information</h3>
                <p>For any inquiries about our clubs, events, or membership, feel free to reach out to us:</p>
                
                <div class="contact-info">
                    <strong>📧 Email:</strong>  info@strathmore.edu
                </div>
                
                <div class="contact-info">
                    <strong>📞 Phone:</strong> +254 (0) 730-734000/200/300
                </div>
                
                <div class="contact-info">
                    <strong>⏰ Office Hours:</strong>
                    Monday - Friday: 8:00 AM - 5:00 PM<br>
                    Saturday: 9:00 AM - 1:00 PM
                </div>
            </div>
            
            <div class="footer-section">
                <h3>Helpdesk Location</h3>
                <p>Visit our helpdesk for immediate assistance with club registrations, event information, and general support:</p>
                
                <ul>
                    <li><strong>Main Campus:</strong> Phase 1, Central Building, First Floor</li>
                    <li><strong>Student Center:</strong> Phase 2, Strathmore Student Center, Second Floor</li>
                </ul>
                
                <div class="contact-info">
                    <strong>🏫 Main Helpdesk Address:</strong>
                    Phase 1, Central Building, First Floor<br>
                    Phase 2, Strathmore Student Center, Second Floor<br>
                </div>
                
                <p style="margin-top: 15px;"><em>Our friendly staff are always ready to help you find the perfect club to join and answer any questions about campus activities!</em></p>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; 2024 School Club Management System. All rights reserved. | Empowering student engagement through organized club activities.</p>
        </div>
    </footer>

</body>
</html>