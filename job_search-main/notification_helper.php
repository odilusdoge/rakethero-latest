<?php
function createNotification($conn, $userId, $fromUserId, $type, $jobId, $message = null) {
    // Get job and user details
    $stmt = $conn->prepare("
        SELECT j.title as job_title, 
               u.username as from_username,
               u.userType as from_userType
        FROM jobs j 
        JOIN users u ON u.users_id = ?
        WHERE j.jobs_id = ?
    ");
    $stmt->bind_param("ii", $fromUserId, $jobId);
    $stmt->execute();
    $result = $stmt->get_result();
    $details = $result->fetch_assoc();

    // Generate appropriate message based on type
    if (!$message) {
        switch ($type) {
            // Employer notifications
            case 'new_application':
                $message = "{$details['from_username']} applied for your job {$details['job_title']}";
                break;
            case 'new_quotation':
                $message = "{$details['from_username']} submitted an offer for {$details['job_title']}";
                break;
            case 'counter_offer_received':
                $message = "{$details['from_username']} sent a counter offer for {$details['job_title']}";
                break;
            case 'quotation_declined':
                $message = "{$details['from_username']} declined your quotation and canceled {$details['job_title']}";
                break;
            case 'quotation_accepted':
                $message = "{$details['from_username']} accepted your offer and accepted {$details['job_title']}";
                break;
            case 'new_employer_rating':
                $message = "{$details['from_username']} rated you. Congratulations!";
                break;

            // Jobseeker notifications
            case 'proposal_accepted':
                $message = "{$details['from_username']} accepted your proposal for {$details['job_title']}";
                break;
            case 'proposal_declined':
                $message = "{$details['from_username']} declined your proposal and canceled {$details['job_title']}";
                break;
            case 'job_cancelled':
                $message = "{$details['from_username']} cancelled the job {$details['job_title']}";
                break;
            case 'employer_counter_offer':
                $message = "{$details['from_username']} sent a counter offer for {$details['job_title']}";
                break;
            case 'job_declined':
                $message = "{$details['from_username']} declined {$details['job_title']}";
                break;
            case 'new_jobseeker_rating':
                $message = "{$details['from_username']} rated you. Congratulations!";
                break;
        }
    }

    // Insert notification
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, from_user_id, type, message, job_id, created_at, is_read) 
        VALUES (?, ?, ?, ?, ?, NOW(), 0)
    ");
    $stmt->bind_param("iissi", $userId, $fromUserId, $type, $message, $jobId);
    $stmt->execute();
}
?> 