<?php
/**
 * Leave Expiry Notification Script
 * 
 * This script should be set up to run daily via cron job or scheduled task.
 * Example cron job:
 * 0 8 * * * php /path/to/send_expiry_notifications.php
 */

// Include required files
require_once "../includes/dbconnect.php";
require_once "../includes/leave_expiry_notifications.php";

// Set execution time limit to 5 minutes
set_time_limit(300);

// Log file
$logFile = "../logs/notification_log.txt";

// Ensure log directory exists
if (!file_exists("../logs")) {
    mkdir("../logs", 0755, true);
}

// Log function
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Start logging
logMessage("Starting leave expiry notification process");

try {
    // Connect to database - connection is already established in dbconnect.php
    
    // Send notifications for leaves ending in 2 days
    $daysBeforeExpiry = 2;
    logMessage("Checking for leaves ending in $daysBeforeExpiry days");
    
    $results = sendLeaveExpiryNotifications($conn, $daysBeforeExpiry);
    
    // Log results
    logMessage("Successfully sent " . count($results['success']) . " notifications");
    
    if (count($results['error']) > 0) {
        logMessage("Failed to send " . count($results['error']) . " notifications");
        foreach ($results['error'] as $error) {
            logMessage("Error for request ID " . $error['request_id'] . ": " . $error['error']);
        }
    }
    
    logMessage("Leave expiry notification process completed");
} catch (Exception $e) {
    logMessage("Error in notification process: " . $e->getMessage());
}

// Close database connection
mysqli_close($conn);
?>
