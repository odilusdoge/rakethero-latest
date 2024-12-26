<?php
include 'db_conn.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit();
}

if (!isset($_POST['jobId'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Job ID is required']);
    exit();
}

try {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Fetch job details with employer information
    $job_query = "SELECT 
        j.jobs_id,
        j.title,
        j.description,
        j.status,
        j.location,
        j.payType,
        j.price,
        j.duration,
        j.postedDate,
        u.username AS employer_name,
        jt.jobType
    FROM jobs j
    JOIN users u ON j.employerId = u.users_id
    LEFT JOIN job_type jt ON j.jobTypeID = jt.jobTypeID
    WHERE j.jobs_id = ?";

    $stmt = $conn->prepare($job_query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $jobId = $_POST['jobId'];
    $stmt->bind_param("i", $jobId);
    $stmt->execute();
    $job_result = $stmt->get_result();
    $job = $job_result->fetch_assoc();

    if (!$job) {
        throw new Exception("Job not found");
    }

    // Fetch application details if exists
    $app_query = "SELECT 
        a.applications_id,
        a.status,
        a.proposal,
        a.applicationDate,
        a.userid,
        q.quotations_id,
        q.price as quotation_price,
        q.description as quotation_description,
        q.status as quotation_status
    FROM applications a
    LEFT JOIN quotations q ON a.applications_id = q.applications_id
    WHERE a.jobid = ? AND a.userid = ?";

    $stmt = $conn->prepare($app_query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ii", $jobId, $_SESSION['user_id']);
    $stmt->execute();
    $app_result = $stmt->get_result();
    $application = $app_result->fetch_assoc();

    // Format the response
    $response = [
        'success' => true,
        'job' => [
            'jobs_id' => $job['jobs_id'],
            'title' => $job['title'],
            'status' => $job['status'],
            'description' => $job['description'],
            'employer_name' => $job['employer_name'],
            'location' => $job['location'],
            'postedDate' => date('M j, Y', strtotime($job['postedDate'])),
            'price' => number_format($job['price'], 2),
            'payType' => $job['payType'],
            'jobType' => $job['jobType']
        ]
    ];

    if ($application) {
        $response['application'] = [
            'applications_id' => $application['applications_id'],
            'status' => $application['status'],
            'proposal' => $application['proposal'],
            'applicationDate' => date('M j, Y', strtotime($application['applicationDate'])),
            'quotation_status' => $application['quotation_status'] ?? null,
            'quotation_price' => $application['quotation_price'] ?? null,
            'quotation_description' => $application['quotation_description'] ?? null
        ];
        
        // Add debug logging
        error_log("Application data: " . print_r($application, true));
    }

    error_log('Job details response: ' . print_r($response, true));

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching job details: ' . $e->getMessage()
    ]);
    error_log("Error in fetch_job_details.php: " . $e->getMessage());
} 