// After successful quotation insertion
include 'notification_helper.php';

// Get the employer ID for the job
$stmt = $conn->prepare("
    SELECT j.employerId 
    FROM jobs j 
    JOIN applications a ON j.jobs_id = a.jobid 
    WHERE a.applications_id = ?
");
$stmt->bind_param("i", $application_id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();

if ($job) {
    // Create notification for employer
    createNotification(
        $conn,
        $job['employerId'],    // employer's user ID
        $_SESSION['user_id'],  // applicant's user ID
        $jobId,                // job ID
        'new_proposal',        // notification type
        'New proposal received'
    );
} 