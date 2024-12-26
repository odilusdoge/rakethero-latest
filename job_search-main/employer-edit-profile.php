<?php
include 'db_conn.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$employerId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $contactNo = $_POST['contactNo'];
    $location = $_POST['location'];
    $email = $_POST['email'];

    // Handle Profile Image Upload
    $imagePath = null; // Default value
    if (isset($_FILES['profileImage']) && $_FILES['profileImage']['error'] === UPLOAD_ERR_OK) {
        $uploadsDir = 'uploads/profile_pictures/'; // Directory to store images
        $fileTmpPath = $_FILES['profileImage']['tmp_name'];
        $fileType = mime_content_type($fileTmpPath);
        $fileExtension = pathinfo($_FILES['profileImage']['name'], PATHINFO_EXTENSION);

        // Validate file type
        if (in_array($fileType, ['image/jpeg', 'image/png', 'image/gif'])) {
            // Generate unique file name using user_id
            $fileName = 'profile_' . $employerId . '.' . $fileExtension;
            $destination = $uploadsDir . $fileName;

            // Create directory if it doesn't exist
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0777, true);
            }

            // Move file to destination
            if (move_uploaded_file($fileTmpPath, $destination)) {
                $imagePath = $destination; // Save path to database
            } else {
                die("Error uploading the file. Please try again.");
            }
        } else {
            die("Unsupported image type: $fileType");
        }
    }

    $query = "UPDATE user_info SET fname = ?, lname = ?, contactNo = ?, location = ?, email = ?, image_path = ? WHERE userid = ?";
    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }

    $stmt->bind_param("ssssssi", $fname, $lname, $contactNo, $location, $email, $imagePath, $employerId);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    header("Location: employersprofile.php");
    exit();
} else {
    $query = "SELECT * FROM user_info WHERE userid = ?";
    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }

    $stmt->bind_param("i", $employerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $employerData = $result->fetch_assoc();
    $stmt->close();
}
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
                                <input type="text" class="form-control" id="fname" name="fname" value="<?php echo htmlspecialchars($employerData['fname']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="lname" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="lname" name="lname" value="<?php echo htmlspecialchars($employerData['lname']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="contactNo" class="form-label">Contact Number</label>
                                <input type="text" class="form-control" id="contactNo" name="contactNo" value="<?php echo htmlspecialchars($employerData['contactNo']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location" value="<?php echo htmlspecialchars($employerData['location']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($employerData['email']); ?>" required>
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
