<?php
// Database connection

include 'db_conn.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['jobId'])) {
    $jobId = intval($_GET['jobId']);  // Sanitize input
    $query = "SELECT title, description, employerName, location, postedDate, price, payType, status 
              FROM jobs 
              WHERE jobs_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $jobId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        echo json_encode($data);  // Return data as JSON
    } else {
        echo json_encode(["error" => "Job not found"]);
    }
} else {
    echo json_encode(["error" => "Invalid request"]);
}

$conn->close();
?>
