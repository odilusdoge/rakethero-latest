<?php
include 'db_conn.php';
session_start();

// Redirect employers to their correct edit profile page
if (isset($_SESSION['userType']) && $_SESSION['userType'] === 'employer') {
    header("Location: employer-edit-profile.php");
    exit();
}

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userid = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = $_POST['fname'] ?? '';
    $lname = $_POST['lname'] ?? '';
    $middle_initial = $_POST['middle_initial'] ?? '';
    $email = $_POST['email'] ?? '';
    $contactNo = $_POST['contactNo'] ?? '';
    $age = $_POST['age'] ?? '';
    $location = $_POST['location'] ?? '';
    $overview = $_POST['overview'] ?? '';
    $title = $_POST['title'] ?? '';
    $skills = $_POST['skills'] ?? '';

    // Handle image upload
    $imagePath = null; // Default to no image path
    if (isset($_FILES['profileImage']) && $_FILES['profileImage']['error'] === UPLOAD_ERR_OK) {
        $uploadsDir = 'uploads/profile_pictures/'; // Directory to store images
        $fileTmpPath = $_FILES['profileImage']['tmp_name'];
        $fileType = mime_content_type($fileTmpPath);
        $fileExtension = pathinfo($_FILES['profileImage']['name'], PATHINFO_EXTENSION);

        // Validate file type
        if (in_array($fileType, ['image/jpeg', 'image/png', 'image/gif'])) {
            // Generate unique file name using user_id
            $fileName = 'profile_' . $userid . '.' . $fileExtension;
            $destination = $uploadsDir . $fileName;

            // Create directory if it doesn't exist
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0777, true);
            }

            // Move file to destination
            if (move_uploaded_file($fileTmpPath, $destination)) {
                $imagePath = $destination; // Save path for database update
            } else {
                die("Error uploading the file. Please try again.");
            }
        } else {
            echo "Unsupported image type: $fileType";
        }
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update user info first
        if ($imagePath) {
            $query = "UPDATE user_info SET fname = ?, lname = ?, middle_initial = ?, email = ?, contactNo = ?, age = ?, location = ?, overview = ?, title = ?, image_path = ? WHERE userid = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param(
                "ssssisssssi",
                $fname, $lname, $middle_initial, $email, $contactNo, $age, $location, $overview, $title, $imagePath, $userid
            );
        } else {
            $query = "UPDATE user_info SET fname = ?, lname = ?, middle_initial = ?, email = ?, contactNo = ?, age = ?, location = ?, overview = ?, title = ? WHERE userid = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param(
                "ssssissssi",
                $fname, $lname, $middle_initial, $email, $contactNo, $age, $location, $overview, $title, $userid
            );
        }
        $stmt->execute();
        $stmt->close();

        // Handle skills update
        if (isset($_POST['skills'])) {
            // Delete existing skills
            $delete_skills = $conn->prepare("DELETE FROM user_skills WHERE userid = ?");
            if (!$delete_skills) {
                throw new Exception("Error preparing delete statement: " . $conn->error);
            }
            $delete_skills->bind_param("i", $userid);
            $delete_skills->execute();
            $delete_skills->close();

            // Insert new skills
            $skills = array_map('trim', explode(',', $_POST['skills']));
            $skills = array_unique(array_filter($skills)); // Remove duplicates and empty values
            
            if (!empty($skills)) {
                $insert_skill = $conn->prepare("INSERT INTO user_skills (userid, skill_name) VALUES (?, ?)");
                if (!$insert_skill) {
                    throw new Exception("Error preparing insert statement: " . $conn->error);
                }
                
                foreach ($skills as $skill) {
                    if (!empty($skill)) {
                        $insert_skill->bind_param("is", $userid, $skill);
                        if (!$insert_skill->execute()) {
                            throw new Exception("Error inserting skill: " . $insert_skill->error);
                        }
                    }
                }
                $insert_skill->close();
            }
        }

        // If we get here, commit the transaction
        $conn->commit();
        header("Location: profile.php");
        exit();
    } catch (Exception $e) {
        // If there's an error, rollback the transaction
        $conn->rollback();
        error_log("Error in edit-profile.php: " . $e->getMessage());
        $error = "Error updating profile: " . $e->getMessage();
    }
}

// Fetch existing user info and skills
$query = "SELECT * FROM user_info WHERE userid = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userid);
$stmt->execute();
$result = $stmt->get_result();
$userInfo = $result->fetch_assoc();
$stmt->close();

$skills = [];
$query = "SELECT skill_name FROM user_skills WHERE userid = ? ORDER BY skill_name ASC";
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("i", $userid);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $skills[] = htmlspecialchars($row['skill_name']);
    }
    $stmt->close();
} else {
    error_log("Error preparing skills statement: " . $conn->error);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile | RaketHero</title>
    <link href="assets/css/theme.css" rel="stylesheet">
</head>
<body>
    <style>
        
    </style>
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

    <!-- Edit Profile Section -->
    <div class="container-fluid py-5 bg-light">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-8">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-4">
                        <h3 class="text-center mb-4">Edit Profile</h3>
                        <form method="POST" enctype="multipart/form-data">

                            <div class="mb-3">
                                <label for="fname" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="fname" name="fname" value="<?php echo htmlspecialchars($userInfo['fname']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="lname" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="lname" name="lname" value="<?php echo htmlspecialchars($userInfo['lname']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="middle_initial" class="form-label">Middle Initial</label>
                                <input type="text" class="form-control" id="middle_initial" name="middle_initial" value="<?php echo htmlspecialchars($userInfo['middle_initial']); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($userInfo['email']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="contactNo" class="form-label">Contact Number</label>
                                <input type="text" class="form-control" id="contactNo" name="contactNo" value="<?php echo htmlspecialchars($userInfo['contactNo']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="age" class="form-label">Age</label>
                                <input type="number" class="form-control" id="age" name="age" value="<?php echo htmlspecialchars($userInfo['age']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location" value="<?php echo htmlspecialchars($userInfo['location']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="overview" class="form-label">Overview</label>
                                <textarea class="form-control" id="overview" name="overview" rows="3"><?php echo htmlspecialchars($userInfo['overview']); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($userInfo['title']); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="skills" class="form-label">Skills (comma-separated)</label>
                                <input type="text" class="form-control" id="skills" name="skills" 
                                       value="<?php echo htmlspecialchars(implode(', ', $skills)); ?>">
                            </div>
                            <div class="mb-3">
    <label for="profileImage" class="form-label">Upload Profile Picture</label>
    <input type="file" class="form-control" id="profileImage" name="profileImage" accept="image/*">
</div>

                            <button type="submit" class="btn btn-primary w-100">Update Profile</button>
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
