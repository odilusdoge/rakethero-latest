<?php
include 'db_conn.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => true, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['jobId'])) {
    echo json_encode(['error' => true, 'message' => 'Job ID is required']);
    exit();
}

$jobId = $_GET['jobId'];

$query = "SELECT DISTINCT
    n.negotiation_id,
    n.price,
    n.description,
    n.valid_until,
    CASE 
        WHEN q.status = 'Accepted' THEN 'Accepted'
        WHEN q.status = 'Rejected' THEN 'Rejected'
        ELSE n.status
    END as status,
    n.created_at,
    CASE 
        WHEN n.created_by = j.employerId THEN CONCAT(ui_emp.fname, ' ', ui_emp.lname)
        ELSE CONCAT(ui_js.fname, ' ', ui_js.lname)
    END as offered_by,
    CASE 
        WHEN n.created_by = j.employerId THEN 'Employer'
        ELSE 'Jobseeker'
    END as user_type
FROM negotiations n
JOIN quotations q ON n.quotation_id = q.quotations_id
JOIN applications a ON q.applications_id = a.applications_id
JOIN jobs j ON a.jobid = j.jobs_id
LEFT JOIN user_info ui_emp ON j.employerId = ui_emp.userid
LEFT JOIN user_info ui_js ON a.userId = ui_js.userid
WHERE j.jobs_id = ?
GROUP BY n.negotiation_id
ORDER BY n.created_at DESC";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['error' => true, 'message' => 'Failed to prepare statement']);
    exit();
}

$stmt->bind_param("i", $jobId);
if (!$stmt->execute()) {
    echo json_encode(['error' => true, 'message' => 'Failed to execute query']);
    exit();
}

$result = $stmt->get_result();
$history = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode(['error' => false, 'data' => $history]);
?> 