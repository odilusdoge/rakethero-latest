<?php
include 'db_conn.php';
include 'notification_helper.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "Error: User not logged in";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $jobId = $_POST['jobId'];
    $proposal = $_POST['proposal'];
    $job_seeker_id = $_POST['job_seeker_id'];
    $userid = $_SESSION['user_id'];

    // Verify that job_seeker_id matches session user_id for security
    if ($job_seeker_id != $userid) {
        echo "Error: Invalid user credentials";
        exit();
    }

    // Connect to database
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Check if user has already applied
    $check_query = "SELECT * FROM applications WHERE jobid = ? AND userid = ?";
    $check_stmt = $conn->prepare($check_query);
    
    if (!$check_stmt) {
        die("Error preparing check statement: " . $conn->error);
    }
    
    $check_stmt->bind_param("ii", $jobId, $userid);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        echo "You have already applied for this job";
    } else {
        // Insert application with job_seeker_id
        $query = "INSERT INTO applications (jobid, userid, proposal, applicationDate, status, job_seeker_id) 
                 VALUES (?, ?, ?, NOW(), 'Pending', ?)";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            die("Error preparing insert statement: " . $conn->error);
        }
        
        $stmt->bind_param("issi", $jobId, $userid, $proposal, $job_seeker_id);
        
        if ($stmt->execute()) {
            // Get employer ID for the job
            $getEmployerSql = "SELECT employerId FROM jobs WHERE jobs_id = ?";
            $employerStmt = $conn->prepare($getEmployerSql);
            
            if ($employerStmt) {
                $employerStmt->bind_param("i", $jobId);
                $employerStmt->execute();
                $employerResult = $employerStmt->get_result();
                $employerData = $employerResult->fetch_assoc();
                
                if ($employerData) {
                    $employerId = $employerData['employerId'];
                    
                    // Create notification using notification helper
                    createNotification(
                        $conn,
                        $employerId,         // employer's user ID (recipient)
                        $job_seeker_id,      // job seeker's user ID (sender)
                        $jobId,              // job ID
                        'new_application',   // notification type
                        'New application received for your job posting'
                    );
                }
            }
            
            echo "Application submitted successfully";
        } else {
            echo "Error submitting application: " . $stmt->error;
        }
    }

    $conn->close();
} else {
    echo "Invalid request method";
}
?>
