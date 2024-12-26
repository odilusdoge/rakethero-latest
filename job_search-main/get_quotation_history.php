<?php
include 'db_conn.php';
session_start();

header('Content-Type: application/json'); // Set JSON header

if (!isset($_SESSION['user_id']) || !isset($_GET['jobId'])) {
    die(json_encode([
        'error' => 'Unauthorized',
        'message' => 'Missing required parameters'
    ]));
}

$jobId = intval($_GET['jobId']);
$userId = $_SESSION['user_id'];

// Update the query to prevent duplicates
$query = "SELECT DISTINCT
    q.quotations_id,
    q.DateCreated,
    q.price,
    q.description,
    q.status,
    CASE 
        WHEN q.status = 'negotiation' THEN 'Counter Offer'
        WHEN q.status = 'pending' THEN 'Initial Quote'
        ELSE q.status
    END as quote_type,
    CASE 
        WHEN j.employerId = ? THEN CONCAT(js_info.fname, ' ', js_info.lname)
        ELSE CONCAT(emp_info.fname, ' ', emp_info.lname)
    END as from_name
FROM quotations q
JOIN (
    SELECT quotations_id, MAX(DateCreated) as max_date
    FROM quotations
    GROUP BY applications_id, status, price, description
) latest ON q.quotations_id = latest.quotations_id
JOIN applications a ON q.applications_id = a.applications_id
JOIN jobs j ON q.jobId = j.jobs_id
JOIN users js ON a.userId = js.users_id
JOIN user_info js_info ON js.users_id = js_info.userid
JOIN users emp ON j.employerId = emp.users_id
JOIN user_info emp_info ON emp.users_id = emp_info.userid
WHERE q.jobId = ?
ORDER BY q.DateCreated ASC";

try {
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ii", $userId, $jobId);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $quotations = [];
    
    while ($row = $result->fetch_assoc()) {
        // Format the date
        $row['DateCreated'] = date('Y-m-d H:i:s', strtotime($row['DateCreated']));
        $quotations[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => $quotations
    ]);

} catch (Exception $e) {
    error_log("Error in get_quotation_history.php: " . $e->getMessage());
    echo json_encode([
        'error' => true,
        'message' => 'Error loading quotation history: ' . $e->getMessage()
    ]);
}
?> 