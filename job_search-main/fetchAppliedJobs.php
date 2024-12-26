<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db_conn.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Session expired');
}

try {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $applied_jobs_query = "SELECT 
        a.*, 
        a.status as app_status,
        a.applicationDate,
        a.proposal,
        j.title, 
        j.description, 
        j.status as job_status, 
        q.quotations_id, 
        q.price, 
        q.description as quotation_description, 
        q.status as quotation_status,
        j.jobs_id
    FROM 
        applications a 
    JOIN 
        jobs j ON a.jobid = j.jobs_id 
    LEFT JOIN 
        quotations q ON a.applications_id = q.applications_id
    WHERE 
        a.userid = ?";

    $stmt = $conn->prepare($applied_jobs_query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $appliedJobs = $stmt->get_result();

    // Debug output
    error_log("User ID: " . $_SESSION['user_id']);
    error_log("Number of applied jobs found: " . $appliedJobs->num_rows);

    if ($appliedJobs && $appliedJobs->num_rows > 0) {
        while ($job = $appliedJobs->fetch_assoc()) {
            error_log("Processing job: " . $job['title']);
            ?>
            <div class="job-card" data-jobid="<?php echo htmlspecialchars($job['jobs_id']); ?>">
                <div class="job-content">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="mb-1"><?php echo htmlspecialchars($job['title']); ?></h5>
                            <div class="d-flex align-items-center gap-2">
                                <span class="status-badge <?php echo strtolower($job['app_status']); ?>">
                                    <?php echo htmlspecialchars($job['app_status']); ?>
                                </span>
                                <span class="text-muted">•</span>
                                <span class="text-muted">Applied on <?php echo date('M j, Y', strtotime($job['applicationDate'])); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="proposal-section bg-white p-3 rounded border mb-3">
                        <h6 class="fw-bold mb-2">Your Proposal</h6>
                        <p class="mb-0 text-secondary">
                            <?php echo nl2br(htmlspecialchars($job['proposal'] ?? '')); ?>
                        </p>
                    </div>

                    <?php if (isset($job['quotations_id'])): ?>
                        <div class="quotation-section bg-light p-3 rounded border mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0">Quotation Details</h6>
                                <span class="status-badge <?php echo strtolower($job['quotation_status'] ?? 'pending'); ?>">
                                    <?php echo htmlspecialchars($job['quotation_status'] ?? 'Pending'); ?>
                                </span>
                            </div>
                            <p class="mb-2"><strong>Amount:</strong> PHP <?php echo number_format($job['price'], 2); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="button-container">
                        <?php if (isset($job['quotations_id'])): ?>
                            <button class="btn btn-primary btn-sm" onclick="viewQuotation('<?php echo htmlspecialchars($job['jobs_id']); ?>')">
                                View Details
                            </button>
                            <button class="btn btn-danger btn-sm delete-application" 
                                    data-application-id="<?php echo htmlspecialchars($job['applications_id']); ?>">
                                Cancel Job
                            </button>
                        <?php else: ?>
                            <button class="btn btn-primary btn-sm" onclick="viewQuotation('<?php echo htmlspecialchars($job['jobs_id']); ?>')">
                                View Details
                            </button>
                            <button class="btn btn-danger btn-sm delete-application" 
                                    data-application-id="<?php echo htmlspecialchars($job['applications_id']); ?>">
                                Delete Application
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php
        }
    } else {
        echo '<div class="alert alert-info">You haven\'t applied to any jobs yet.</div>';
    }

} catch (Exception $e) {
    error_log("Error in fetchAppliedJobs.php: " . $e->getMessage());
    http_response_code(500);
    echo '<div class="alert alert-danger">Error loading applied jobs. Please refresh the page.</div>';
}
?>
