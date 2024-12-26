<?php
session_start();
include 'db_conn.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Connection failed']);
    exit;
}

$jobs_query = "SELECT j.*, jt.jobType, u.username as employer_name, 
    COUNT(DISTINCT a.applications_id) as application_count,
    CASE 
        WHEN EXISTS (
            SELECT 1 
            FROM applications a2 
            WHERE a2.jobId = j.jobs_id 
            AND a2.userId = ?
            AND a2.status NOT IN ('Rejected')
        ) THEN 1 
        ELSE 0 
    END as has_applied
FROM jobs j
LEFT JOIN job_type jt ON j.jobTypeID = jt.jobTypeID
LEFT JOIN users u ON j.employerId = u.users_id
LEFT JOIN applications a ON j.jobs_id = a.jobid
WHERE j.status = 'Open' 
AND j.is_onlyavailable = TRUE
AND (
    NOT EXISTS (
        SELECT 1
        FROM applications a4
        WHERE a4.jobid = j.jobs_id
        AND a4.userId = ?
        AND a4.status NOT IN ('Rejected')
    )
)
AND NOT EXISTS (
    SELECT 1 
    FROM applications a3
    JOIN quotations q ON a3.applications_id = q.applications_id 
    WHERE a3.jobid = j.jobs_id 
    AND q.status = 'Accepted'
)
GROUP BY j.jobs_id
ORDER BY j.postedDate DESC";

$stmt = $conn->prepare($jobs_query);
$stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

$jobs = [];
while ($job = $result->fetch_assoc()) {
    $jobs[] = [
        'jobs_id' => $job['jobs_id'],
        'title' => htmlspecialchars($job['title']),
        'employer_name' => htmlspecialchars($job['employer_name']),
        'location' => htmlspecialchars($job['location']),
        'price' => htmlspecialchars($job['price']),
        'payType' => htmlspecialchars($job['payType']),
        'jobType' => htmlspecialchars($job['jobType']),
        'description' => htmlspecialchars($job['description']),
        'status' => htmlspecialchars($job['status']),
        'postedDate' => htmlspecialchars($job['postedDate'])
    ];
}

echo json_encode(['jobs' => $jobs]);
$conn->close(); 