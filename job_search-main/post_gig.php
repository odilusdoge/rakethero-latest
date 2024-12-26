<?php
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['jobTitle'];
    $description = $_POST['jobDescription'];
    $location = $_POST['jobLocation'];
    $price = $_POST['jobSalary'];
    $payType = $_POST['payType'];
    $jobTypeID = $_POST['jobCategory'];
    $duration = $_POST['duration']; // New field for duration
    $deadline = $_POST['applicationDeadline'];

    // Debug the received values
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    $employerId = 1; // Hardcoded for now; replace with session data if logged-in user
    $postedDate = date('Y-m-d');
    $status = 'Open'; // Default status
    $remarks = ''; // Optional field, leave empty or modify

    // Insert query with duration field added
    $sql = "INSERT INTO jobs (title, description, employerId, postedDate, price, payType, duration, location, status, remarks, jobTypeID) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    // Prepare and bind
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssisssssssi', $title, $description, $employerId, $postedDate, $price, $payType, $duration, $location, $status, $remarks, $jobTypeID);

    if ($stmt->execute()) {
        echo "<script>alert('Gig posted successfully!'); window.location.href='employerlanding.php';</script>";
    } else {
        echo "Error: " . $stmt->error; // Debugging SQL errors
    }

    $stmt->close();
}

$conn->close();
    
?>
