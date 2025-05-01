<?php
/**
 * Leave Expiry Notification Script
 * 
 * This script sends notifications to employees whose leave is about to expire.
 * It should be set up to run daily via cron job or scheduled task.
 */

// Prevent direct access
if (count(get_included_files()) == 1) {
    header("Location: ../index.php");
    exit();
}

// Use absolute paths with __DIR__ to ensure files are included correctly
require_once __DIR__ . "/dbconnect.php";
require_once __DIR__ . "/email_functions.php";

// Check if function already exists to prevent redeclaration
if (!function_exists('sendLeaveExpiryNotifications')) {
    /**
     * Sends notifications to users whose leave is about to expire
     * 
     * @param object $conn Database connection
     * @param int $daysBeforeExpiry Number of days before leave ends to send notification
     * @return array Results of notification attempts
     */
    function sendLeaveExpiryNotifications($conn, $daysBeforeExpiry = 2) {
        $results = [
            'success' => [],
            'error' => []
        ];
        
        // Get today's date
        $today = date('Y-m-d');
        
        // Calculate the target date
        $targetDate = date('Y-m-d', strtotime("+$daysBeforeExpiry days"));
        
        // Find leaves ending on the target date
        $query = "SELECT lr.RequestID, lr.UserID, lr.StartDate, lr.EndDate, 
                  u.Fullnames, u.Email as UserEmail, lt.LeaveName
                  FROM leave_requests lr
                  JOIN users u ON lr.UserID = u.UserID
                  JOIN leave_types lt ON lr.LeaveTypeID = lt.LeaveTypeID
                  WHERE lr.Status = 'Approved' 
                  AND lr.EndDate = ?
                  AND NOT EXISTS (
                      SELECT 1 FROM notifications n 
                      WHERE n.Type = 'leave_expiry' 
                      AND n.Message LIKE CONCAT('%', lr.RequestID, '%')
                  )";
                  
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $targetDate);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        // Process each leave request
        while ($leave = mysqli_fetch_assoc($result)) {
            try {
                // Get email settings
                $settings = getEmailSettings($conn);
                if (!$settings) {
                    throw new Exception("Email settings not configured");
                }
                
                // Setup mailer
                $mail = setupMailer($settings);
                $mail->addAddress($leave['UserEmail'], $leave['Fullnames']);
                $mail->Subject = "Leave Expiry Reminder";
                
                // Build email body
                $body = "Dear " . $leave['Fullnames'] . ",\n\n";
                $body .= "This is a reminder that your " . $leave['LeaveName'] . " leave will end in $daysBeforeExpiry days on " . date('d-M-Y', strtotime($leave['EndDate'])) . ".\n\n";
                $body .= "Leave Details:\n";
                $body .= "- Type: " . $leave['LeaveName'] . "\n";
                $body .= "- Start Date: " . date('d-M-Y', strtotime($leave['StartDate'])) . "\n";
                $body .= "- End Date: " . date('d-M-Y', strtotime($leave['EndDate'])) . "\n\n";
                $body .= "Please make necessary arrangements to resume your duties.\n\n";
                $body .= "Best regards,\n";
                $body .= $settings['smtp_from_name'];
                
                $mail->Body = $body;
                $mail->send();
                
                // Also add notification to database
                $message = "Your " . $leave['LeaveName'] . " leave will end in $daysBeforeExpiry days (on " . date('d-M-Y', strtotime($leave['EndDate'])) . "). Leave ID: " . $leave['RequestID'];
                $insertQuery = "INSERT INTO notifications (UserID, Message, Type, IsRead) 
                               VALUES (?, ?, 'leave_expiry', 0)";
                $insertStmt = mysqli_prepare($conn, $insertQuery);
                mysqli_stmt_bind_param($insertStmt, "is", $leave['UserID'], $message);
                mysqli_stmt_execute($insertStmt);
                
                $results['success'][] = $leave['RequestID'];
            } catch (Exception $e) {
                $results['error'][] = [
                    'request_id' => $leave['RequestID'],
                    'user' => $leave['Fullnames'],
                    'error' => $e->getMessage()
                ];
                
                // Log the error
                error_log("Error sending leave expiry notification for request ID " . 
                         $leave['RequestID'] . ": " . $e->getMessage());
            }
        }
        
        return $results;
    }
}
?>
