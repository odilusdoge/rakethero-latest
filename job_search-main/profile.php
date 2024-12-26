<?php
include 'db_conn.php';
session_start();

// Redirect employers to their profile page
if (isset($_SESSION['userType']) && $_SESSION['userType'] === 'employer') {
    header("Location: employersprofile.php");
    exit();
}

// Logout logic
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userid = $_SESSION['user_id'];
$hasProfile = false; // Flag to check if user has a profile
$fname = $lname = $middle_initial = $email = $contactNo = $age = $location = $overview = $title = "";
$profile_image = "assets/img/default-image.png"; // Default placeholder image
$skills = [];
$reviews = [];
$average_rating = 0;

// Add this near the top of profile.php, after session_start()
if (isset($_GET['profile_updated'])) {
    // Force a fresh fetch of user data
    $query = "SELECT u.username, ui.*, 
              (SELECT GROUP_CONCAT(skill_name SEPARATOR ', ') 
               FROM user_skills 
               WHERE userid = u.users_id) as skills
              FROM users u
              LEFT JOIN user_info ui ON u.users_id = ui.userid
              WHERE u.users_id = ? AND u.userType = 'jobseeker'";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile = $result->fetch_assoc();
}

// Fetch user data
$query = "SELECT u.username, i.* FROM users u 
          LEFT JOIN user_info i ON u.users_id = i.userid 
          WHERE u.users_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userid);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $userInfo = $result->fetch_assoc();
    if (!empty($userInfo['fname']) || !empty($userInfo['lname'])) { // Check if basic profile info exists
        $hasProfile = true;
        // Sanitize data for HTML output
        $fname = htmlspecialchars($userInfo['fname']);
        $lname = htmlspecialchars($userInfo['lname']);
        $middle_initial = htmlspecialchars($userInfo['middle_initial']);
        $email = htmlspecialchars($userInfo['email']);
        $contactNo = htmlspecialchars($userInfo['contactNo']);
        $age = htmlspecialchars($userInfo['age']);
        $location = htmlspecialchars($userInfo['location']);
        $overview = htmlspecialchars($userInfo['overview']);
        $title = htmlspecialchars($userInfo['title']);

        // Handle profile image
        if (!empty($userInfo['image_path'])) {
            $profile_image = htmlspecialchars($userInfo['image_path']);
        } else {
            $profile_image = "assets/img/default-image.png"; // Set default if no image
        }
    }
}
$stmt->close();

// Fetch user skills only if user has a profile
if ($hasProfile) {
    $query = "SELECT skill_name FROM user_skills WHERE userid = ? ORDER BY skill_name ASC";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $userid);
        $stmt->execute();
        $result = $stmt->get_result();
        $skills = [];
        while ($row = $result->fetch_assoc()) {
            $skills[] = htmlspecialchars($row['skill_name']);
        }
        $stmt->close();
    } else {
        error_log("Error preparing skills statement: " . $conn->error);
    }
}

// Fetch top 3 reviews and average rating
if ($hasProfile) {
    // Get average rating
    $rating_query = "SELECT AVG(rating) as avg_rating FROM user_ratings WHERE rated_user_id = ?";
    $stmt = $conn->prepare($rating_query);
    if ($stmt) {
        $stmt->bind_param("i", $userid);
        $stmt->execute();
        $rating_result = $stmt->get_result();
        if ($row = $rating_result->fetch_assoc()) {
            $average_rating = round($row['avg_rating'], 1);
        }
        $stmt->close();
    }

    // Get top 3 reviews
    $reviews_query = "SELECT comment, rating, created_at 
                     FROM user_ratings 
                     WHERE rated_user_id = ? 
                     ORDER BY rating DESC, created_at DESC 
                     LIMIT 3";
    $stmt = $conn->prepare($reviews_query);
    if ($stmt) {
        $stmt->bind_param("i", $userid);
        $stmt->execute();
        $reviews_result = $stmt->get_result();
        while ($row = $reviews_result->fetch_assoc()) {
            $reviews[] = [
                'comment' => htmlspecialchars($row['comment']),
                'rating' => $row['rating'],
                'date' => $row['created_at']
            ];
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | RaketHero</title>
     <!-- Favicons -->
     <link rel="apple-touch-icon" sizes="180x180" href="assets/img/favicons/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicons/favicon.ico">
        <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicons/favicon.ico">
        <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicons/favicon.ico">
        <link rel="manifest" href="assets/img/favicons/manifest.json">
        <meta name="msapplication-TileImage" content="assets/img/favicons/favicon.ico">
        <meta name="theme-color" content="#ffffff">

    <link href="assets/css/theme.css" rel="stylesheet">
    <style>
        /* Page styles */
        .container-fluid {
            max-width: 100%;
            padding-top: 5rem;
        }

        .card {
            border-radius: 15px;
            overflow: hidden;
            margin-top: 2rem;
        }

        .card-body {
            padding: 2rem;
        }

        .badge {
            font-size: 0.85rem;
            padding: 0.5em 0.7em;
            border-radius: 12px;
        }

        body {
            background-color: #f8f9fa;
            margin: 0;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top py-3 backdrop bg-light shadow-transition">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center fw-bolder fs-2 fst-italic" href="index.html">
                <div class="text-info">Raket</div><div class="text-warning">Hero</div>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="jobseekerlanding.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.html#jobs">Available Jobs</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.html#applied-jobs">Your Applied Jobs</a></li>
                    <li class="nav-item"><a class="nav-link active" href="profile.php">Profile</a></li>
                    <li class="nav-item px-2"><a class="nav-link fw-medium" href="?action=logout">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Profile Section -->
    <div class="container-fluid py-5 bg-light">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-8">
                <div class="card shadow-lg border-0">
                    <div class="card-body text-center">
                        <?php if ($hasProfile): ?>
                            <!-- Profile Picture -->
                            <img src="<?php echo !empty($profile_image) && file_exists($profile_image) ? $profile_image : 'assets/img/default-image.png'; ?>" 
                                 alt="Profile Picture" 
                                 class="rounded-circle mb-3" 
                                 loading="lazy" 
                                 style="width: 120px; height: 120px; object-fit: cover;">

                            <!-- Name -->
                            <h4 class="card-title mb-1"><?php echo $fname . " " . $lname; ?></h4>
                            <p class="text-muted mb-3"><?php echo $title ?: 'No Title Set'; ?></p>

                            <!-- Skills -->
                            <div class="mb-3">
                                <?php if (!empty($skills)): ?>
                                    <?php foreach ($skills as $skill): ?>
                                        <span class="badge bg-primary me-1 mb-1"><?php echo $skill; ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted">No skills added yet</p>
                                <?php endif; ?>
                            </div>

                            <!-- Rating Section -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-center align-items-center" style="font-size: 1.5rem; color: #f1c40f;">
                                    <?php
                                    $full_stars = floor($average_rating);
                                    $half_star = $average_rating - $full_stars >= 0.5;
                                    $empty_stars = 5 - ceil($average_rating);
                                    
                                    for ($i = 0; $i < $full_stars; $i++) echo "★";
                                    if ($half_star) echo "½";
                                    for ($i = 0; $i < $empty_stars; $i++) echo "☆";
                                    ?>
                                    <span style="font-size: 1rem; color: #6c757d; margin-left: 0.5rem;">(<?php echo $average_rating ?: '0.0'; ?>)</span>
                                </div>
                            </div>

                            <!-- Overview -->
                            <h6 class="text-start">Overview</h6>
                            <p class="text-start" style="font-size: 0.95rem;">
                                <?php echo $overview ?: 'No overview provided yet.'; ?>
                            </p>

                            <!-- Top Reviews -->
                            <h6 class="text-start mt-4">Recent Reviews</h6>
                            <?php if (!empty($reviews)): ?>
                                <div class="text-start">
                                    <?php foreach ($reviews as $review): ?>
                                        <div class="mb-3 p-3 bg-light rounded">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div style="color: #f1c40f;">
                                                    <?php for ($i = 0; $i < $review['rating']; $i++) echo "★"; ?>
                                                    <?php for ($i = $review['rating']; $i < 5; $i++) echo "☆"; ?>
                                                </div>
                                                <small class="text-muted"><?php echo date('M d, Y', strtotime($review['date'])); ?></small>
                                            </div>
                                            <p class="mb-0" style="font-size: 0.9rem;"><?php echo $review['comment']; ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No reviews yet</p>
                            <?php endif; ?>

                            <!-- Edit Profile Button -->
                            <a href="edit-profile.php" class="btn btn-primary btn-sm w-100 mb-3">Edit Profile</a>
                        <?php else: ?>
                            <!-- Show message and Add Profile button if no profile exists -->
                            <div class="text-center mb-4">
                                <img src="assets/img/default-image.png" 
                                     alt="Default Profile Picture" 
                                     class="rounded-circle mb-3" 
                                     style="width: 120px; height: 120px; object-fit: cover;">
                                <h5 class="mb-3">Complete Your Profile</h5>
                                <p class="text-muted">Add your professional details to help employers know more about you.</p>
                                <a href="add-profile.php" class="btn btn-primary btn-sm w-100">Add Profile</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="vendors/@popperjs/popper.min.js"></script>
    <script src="vendors/bootstrap/bootstrap.min.js"></script>
    <script src="vendors/is/is.min.js"></script>
    <script src="assets/js/theme.js"></script>
</body>
</html>
