<?php
include 'db_conn.php';
session_start();

// Redirect employers to their correct add profile page
if (isset($_SESSION['userType']) && $_SESSION['userType'] === 'employer') {
    header("Location: employer-add-profile.php");
    exit();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userid = $_SESSION['user_id'];
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $middle_initial = $_POST['middle_initial'];
    $email = $_POST['email'];
    $contactNo = $_POST['contactNo'];
    $age = $_POST['age'];
    $location = $_POST['location'];
    $overview = $_POST['overview'];
    $title = $_POST['title'];
    $skills = isset($_POST['skills']) ? $_POST['skills'] : '';

    try {
        // Start transaction
        $conn->begin_transaction();

        // Check if profile already exists
        $check_query = "SELECT userid FROM user_info WHERE userid = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("i", $userid);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        // Handle image upload
        $imagePath = null;
        if (isset($_FILES['profileImage']) && $_FILES['profileImage']['error'] === UPLOAD_ERR_OK) {
            $uploadsDir = 'uploads/profile_pictures/';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0777, true);
            }

            $fileTmpPath = $_FILES['profileImage']['tmp_name'];
            $fileType = mime_content_type($fileTmpPath);
            $fileExtension = pathinfo($_FILES['profileImage']['name'], PATHINFO_EXTENSION);

            if (in_array($fileType, ['image/jpeg', 'image/png', 'image/gif'])) {
                $fileName = 'profile_' . $userid . '.' . $fileExtension;
                $destination = $uploadsDir . $fileName;

                if (move_uploaded_file($fileTmpPath, $destination)) {
                    $imagePath = $destination;
                }
            }
        }

        if ($result->num_rows > 0) {
            // Update existing profile
            $query = "UPDATE user_info SET 
                fname = ?, lname = ?, middle_initial = ?, 
                email = ?, contactNo = ?, age = ?, 
                location = ?, overview = ?, title = ?" .
                ($imagePath ? ", image_path = ?" : "") .
                " WHERE userid = ?";

            $stmt = $conn->prepare($query);
            
            if ($imagePath) {
                $stmt->bind_param("ssssssssssi", 
                    $fname, $lname, $middle_initial, 
                    $email, $contactNo, $age, 
                    $location, $overview, $title,
                    $imagePath, $userid
                );
            } else {
                $stmt->bind_param("sssssssssi", 
                    $fname, $lname, $middle_initial, 
                    $email, $contactNo, $age, 
                    $location, $overview, $title,
                    $userid
                );
            }
        } else {
            // Insert new profile
            $query = "INSERT INTO user_info (
                userid, fname, lname, middle_initial, 
                email, contactNo, age, location, 
                overview, title, image_path
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("isssssissss", 
                $userid, $fname, $lname, $middle_initial, 
                $email, $contactNo, $age, $location, 
                $overview, $title, $imagePath
            );
        }

        if (!$stmt->execute()) {
            throw new Exception("Error saving profile: " . $stmt->error);
        }

        // Handle skills
        if (!empty($skills)) {
            // Delete existing skills
            $delete_skills = $conn->prepare("DELETE FROM user_skills WHERE userid = ?");
            $delete_skills->bind_param("i", $userid);
            $delete_skills->execute();

            // Insert new skills
            $skills_array = array_map('trim', explode(',', $skills));
            $insert_skill = $conn->prepare("INSERT INTO user_skills (userid, skill_name) VALUES (?, ?)");
            
            foreach ($skills_array as $skill) {
                if (!empty($skill)) {
                    $insert_skill->bind_param("is", $userid, $skill);
                    $insert_skill->execute();
                }
            }
        }

        // Commit transaction
        $conn->commit();

        // Redirect with success parameter
        header("Location: profile.php?profile_updated=1");
        exit();

    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        error_log("Error in add-profile.php: " . $e->getMessage());
        $error = $e->getMessage();
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Profile | RaketHero</title>
     <!-- Favicons -->
     <link rel="apple-touch-icon" sizes="180x180" href="assets/img/favicons/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicons/favicon.ico">
        <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicons/favicon.ico">
        <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicons/favicon.ico">
        <link rel="manifest" href="assets/img/favicons/manifest.json">
        <meta name="msapplication-TileImage" content="assets/img/favicons/favicon.ico">
        <meta name="theme-color" content="#ffffff">

    <link href="assets/css/theme.css" rel="stylesheet">
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

    <!-- Add Profile Section -->
    <div class="container-fluid py-5 bg-light">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-8">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-4">
                        <h3 class="text-center mb-4">Add Profile</h3>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="fname" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="fname" name="fname" required>
                            </div>
                            <div class="mb-3">
                                <label for="lname" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="lname" name="lname" required>
                            </div>
                            <div class="mb-3">
                                <label for="middle_initial" class="form-label">Middle Initial</label>
                                <input type="text" class="form-control" id="middle_initial" name="middle_initial">
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="contactNo" class="form-label">Contact Number</label>
                                <input type="text" class="form-control" id="contactNo" name="contactNo" required>
                            </div>
                            <div class="mb-3">
                                <label for="age" class="form-label">Age</label>
                                <input type="number" class="form-control" id="age" name="age" required>
                            </div>
                            <div class="mb-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location" required>
                            </div>
                            <div class="mb-3">
                                <label for="overview" class="form-label">Overview</label>
                                <textarea class="form-control" id="overview" name="overview" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="title" name="title">
                            </div>
                            <div class="mb-3">
                                <label for="skills" class="form-label">Skills (comma-separated)</label>
                                <input type="text" class="form-control" id="skills" name="skills">
                            </div>
                            <div class="mb-3">
                                <label for="profileImage" class="form-label">Profile Picture</label>
                                <input type="file" class="form-control" id="profileImage" name="profileImage" accept="image/*">
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Save Profile</button>
                        </form>
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
