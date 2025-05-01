<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Use Composer autoloader instead of direct file includes
require_once __DIR__ . '/../vendor/autoload.php';

// Check if function already exists to prevent redeclaration
if (!function_exists('getEmailSettings')) {
    function getEmailSettings($conn) {
        $query = "SELECT * FROM email_settings LIMIT 1";
        $result = mysqli_query($conn, $query);
        return mysqli_fetch_assoc($result);
    }
}

if (!function_exists('setupMailer')) {
    function setupMailer($settings) {
        $mail = new PHPMailer(true);
        
        try {
            $mail->isSMTP();
            $mail->Host = $settings['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $settings['smtp_username'];
            $mail->Password = $settings['smtp_password'];
            $mail->SMTPSecure = $settings['smtp_encryption'];
            $mail->Port = $settings['smtp_port'];
            
            $mail->setFrom($settings['smtp_from_email'], $settings['smtp_from_name']);
            
            return $mail;
        } catch (Exception $e) {
            throw new Exception("Error setting up mailer: " . $e->getMessage());
        }
    }
}

if (!function_exists('sendLeaveRequestNotification')) {
    function sendLeaveRequestNotification($conn, $requestID) {
        $settings = getEmailSettings($conn);
        if (!$settings) {
            throw new Exception("Email settings not configured");
        }

        // Get request details - Fix using EmailAddress instead of Email
        $query = "SELECT lr.*, u.EmailAddress as UserEmail, u.Fullnames, lt.LeaveName
                  FROM leave_requests lr
                  JOIN users u ON lr.UserID = u.UserID
                  JOIN leave_types lt ON lr.LeaveTypeID = lt.LeaveTypeID
                  WHERE lr.RequestID = ?";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $requestID);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $request = mysqli_fetch_assoc($result);

        if (!$request) {
            throw new Exception("Leave request not found");
        }

        try {
            $mail = setupMailer($settings);
            
            // Send to employee
            $mail->addAddress($request['UserEmail'], $request['Fullnames']);
            $mail->Subject = 'Leave Request Submitted - Pending Approval';
            
            // Email body
            $body = "Dear " . $request['Fullnames'] . ",\n\n";
            $body .= "Your leave request has been submitted successfully and is pending approval.\n\n";
            $body .= "Details:\n";
            $body .= "Leave Type: " . $request['LeaveName'] . "\n";
            $body .= "Start Date: " . date('d-M-Y', strtotime($request['StartDate'])) . "\n";
            $body .= "End Date: " . date('d-M-Y', strtotime($request['EndDate'])) . "\n";
            $body .= "Status: Pending\n\n";
            $body .= "You will be notified once your request has been processed.\n\n";
            $body .= "Best regards,\n";
            $body .= $settings['smtp_from_name'];
            
            $mail->Body = $body;
            $mail->send();
            
            // Send to admin/HR - Fix using EmailAddress instead of Email
            $mail->clearAddresses();
            $adminQuery = "SELECT EmailAddress as Email, Fullnames FROM users WHERE admin = 1 OR hr = 1";
            $adminResult = mysqli_query($conn, $adminQuery);
            
            while ($admin = mysqli_fetch_assoc($adminResult)) {
                $mail->addAddress($admin['Email'], $admin['Fullnames']);
            }
            
            $mail->Subject = 'New Leave Request Pending Approval';
            $body = "A new leave request has been submitted and requires your attention.\n\n";
            $body .= "Employee: " . $request['Fullnames'] . "\n";
            $body .= "Leave Type: " . $request['LeaveName'] . "\n";
            $body .= "Start Date: " . date('d-M-Y', strtotime($request['StartDate'])) . "\n";
            $body .= "End Date: " . date('d-M-Y', strtotime($request['EndDate'])) . "\n";
            $body .= "Reason: " . $request['Reason'] . "\n\n";
            $body .= "Please log in to the system to approve or reject this request.\n\n";
            $body .= "Best regards,\n";
            $body .= $settings['smtp_from_name'];
            
            $mail->Body = $body;
            $mail->send();
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Error sending email: " . $e->getMessage());
        }
    }
}

if (!function_exists('sendLeaveStatusNotification')) {
    function sendLeaveStatusNotification($conn, $requestID, $status) {
        $settings = getEmailSettings($conn);
        if (!$settings) {
            throw new Exception("Email settings not configured");
        }

        // Check if RejectionReason column exists
        $checkColumnQuery = "SHOW COLUMNS FROM `leave_requests` LIKE 'RejectionReason'";
        $columnExists = mysqli_query($conn, $checkColumnQuery)->num_rows > 0;

        // Get request details with rejection reason if available
        $query = "SELECT lr.*, u.EmailAddress as UserEmail, u.Fullnames, lt.LeaveName";
        
        if ($columnExists) {
            $query .= ", lr.RejectionReason";
        }
        
        $query .= " FROM leave_requests lr
                  JOIN users u ON lr.UserID = u.UserID
                  JOIN leave_types lt ON lr.LeaveTypeID = lt.LeaveTypeID
                  WHERE lr.RequestID = ?";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $requestID);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $request = mysqli_fetch_assoc($result);

        if (!$request) {
            throw new Exception("Leave request not found");
        }

        try {
            $mail = setupMailer($settings);
            $mail->addAddress($request['UserEmail'], $request['Fullnames']);
            
            $statusText = ucfirst(strtolower($status));
            $mail->Subject = "Leave Request $statusText";
            
            $body = "Dear " . $request['Fullnames'] . ",\n\n";
            $body .= "Your leave request has been $statusText.\n\n";
            $body .= "Details:\n";
            $body .= "Leave Type: " . $request['LeaveName'] . "\n";
            $body .= "Start Date: " . date('d-M-Y', strtotime($request['StartDate'])) . "\n";
            $body .= "End Date: " . date('d-M-Y', strtotime($request['EndDate'])) . "\n";
            $body .= "Status: $statusText\n\n";
            
            // Add resume duty date information
            if ($status == 'APPROVED' || $status == 'Approved') {
                $resumeDate = date('d-M-Y', strtotime('+1 day', strtotime($request['EndDate'])));
                $body .= "You are expected to resume your duties on $resumeDate.\n\n";
            }
            
            if ($status == 'REJECTED' || $status == 'Rejected') {
                if ($columnExists && !empty($request['RejectionReason'])) {
                    $body .= "Reason for rejection: " . $request['RejectionReason'] . "\n\n";
                } else {
                    $body .= "If you have any questions regarding this rejection, please contact your supervisor.\n\n";
                }
            }
            
            $body .= "Best regards,\n";
            $body .= $settings['smtp_from_name'];
            
            $mail->Body = $body;
            $mail->send();
            
            return true;
        } catch (Exception $e) {
            error_log("Email send error: " . $e->getMessage());
            throw new Exception("Error sending email: " . $e->getMessage());
        }
    }
}

// Add a new function to check if email notifications are enabled
if (!function_exists('areNotificationsEnabled')) {
    function areNotificationsEnabled($conn) {
        // Check if email settings exist and are properly configured
        $settings = getEmailSettings($conn);
        if (!$settings || empty($settings['smtp_host']) || empty($settings['smtp_username']) || 
            empty($settings['smtp_password']) || empty($settings['smtp_from_email'])) {
            return false;
        }
        
        return true;
    }
}

// Add a new function to send notifications to substitutes when leave is approved
if (!function_exists('sendSubstituteNotification')) {
    function sendSubstituteNotification($conn, $requestID, $substitute = null) {
        $settings = getEmailSettings($conn);
        if (!$settings) {
            throw new Exception("Email settings not configured");
        }

        // If substitute details not provided, fetch them
        if (!$substitute) {
            $query = "SELECT ls.SubstituteID, u.Fullnames AS EmployeeName, u2.Fullnames AS SubstituteName, 
                     u2.EmailAddress AS SubstituteEmail, lt.LeaveName, lr.StartDate, lr.EndDate
                     FROM leave_substitutes ls
                     JOIN users u ON ls.UserID = u.UserID
                     JOIN users u2 ON ls.SubstituteID = u2.UserID
                     JOIN leave_requests lr ON ls.RequestID = lr.RequestID
                     JOIN leave_types lt ON lr.LeaveTypeID = lt.LeaveTypeID
                     WHERE ls.RequestID = ?";
            
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "i", $requestID);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $substitute = mysqli_fetch_assoc($result);
            
            if (!$substitute) {
                throw new Exception("No substitute assigned for this leave request");
            }
        }

        try {
            $mail = setupMailer($settings);
            $mail->addAddress($substitute['SubstituteEmail'], $substitute['SubstituteName']);
            
            $mail->Subject = "Leave Substitution Notification";
            
            // Format dates for display
            $startDate = date('d-M-Y', strtotime($substitute['StartDate'] ?? $_POST['start_date']));
            $endDate = date('d-M-Y', strtotime($substitute['EndDate'] ?? $_POST['end_date']));
            
            // Build email body
            $body = "Dear " . $substitute['SubstituteName'] . ",\n\n";
            $body .= "You have been assigned as a substitute for " . $substitute['EmployeeName'] . " during their leave period.\n\n";
            $body .= "Leave Details:\n";
            $body .= "- Employee: " . $substitute['EmployeeName'] . "\n";
            $body .= "- Leave Type: " . $substitute['LeaveName'] . "\n";
            $body .= "- Start Date: " . $startDate . "\n";
            $body .= "- End Date: " . $endDate . "\n\n";
            $body .= "Please ensure you are prepared to handle their responsibilities during this period.\n\n";
            $body .= "Best regards,\n";
            $body .= $settings['smtp_from_name'];
            
            $mail->Body = $body;
            $mail->send();
            
            return true;
        } catch (Exception $e) {
            error_log("Error sending substitute notification email: " . $e->getMessage());
            throw new Exception("Error sending email: " . $e->getMessage());
        }
    }
}

// Modify leave expiry notification to include resume duty reminder
if (!function_exists('sendLeaveExpiryNotifications')) {
    function sendLeaveExpiryNotifications($conn, $daysBeforeExpiry = 2) {
        // Check if email notifications are enabled
        if (!areNotificationsEnabled($conn)) {
            return false;
        }

        // Get email settings
        $settings = getEmailSettings($conn);
        if (!$settings) {
            throw new Exception("Email settings not configured");
        }

        // Get leave requests that are about to expire
        $query = "SELECT lr.RequestID, lr.EndDate, u.EmailAddress, u.Fullnames, lt.LeaveName
                  FROM leave_requests lr
                  JOIN users u ON lr.UserID = u.UserID
                  JOIN leave_types lt ON lr.LeaveTypeID = lt.LeaveTypeID
                  WHERE lr.Status = 'APPROVED' AND lr.EndDate = DATE_ADD(CURDATE(), INTERVAL ? DAY)";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $daysBeforeExpiry);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        // Process each leave request
        while ($leave = mysqli_fetch_assoc($result)) {
            try {
                $mail = setupMailer($settings);
                $mail->addAddress($leave['EmailAddress'], $leave['Fullnames']);
                $mail->Subject = "Leave Expiry Reminder";
                
                // Build email body
                $body = "Dear " . $leave['Fullnames'] . ",\n\n";
                $body .= "This is a reminder that your " . $leave['LeaveName'] . " leave will end in $daysBeforeExpiry days on " . date('d-M-Y', strtotime($leave['EndDate'])) . ".\n\n";
                $body .= "Leave Details:\n";
                $body .= "- Type: " . $leave['LeaveName'] . "\n";
                $body .= "- Start Date: " . date('d-M-Y', strtotime($leave['StartDate'])) . "\n";
                $body .= "- End Date: " . date('d-M-Y', strtotime($leave['EndDate'])) . "\n\n";
                
                // Add resume duty date information
                $resumeDate = date('d-M-Y', strtotime('+1 day', strtotime($leave['EndDate'])));
                $body .= "You are expected to resume your duties on $resumeDate.\n\n";
                $body .= "Please make necessary arrangements to ensure a smooth transition back to work.\n\n";
                
                $body .= "Best regards,\n";
                $body .= $settings['smtp_from_name'];
                
                $mail->Body = $body;
                $mail->send();
                
                // Also add notification to database with resume duty information
                $message = "Your " . $leave['LeaveName'] . " leave will end in $daysBeforeExpiry days (on " . 
                          date('d-M-Y', strtotime($leave['EndDate'])) . "). You are expected to resume duties on " . 
                          $resumeDate . ". Leave ID: " . $leave['RequestID'];
                
                // Insert notification into database
                $notificationQuery = "INSERT INTO notifications (UserID, Message, CreatedAt) VALUES (?, ?, NOW())";
                $notificationStmt = mysqli_prepare($conn, $notificationQuery);
                mysqli_stmt_bind_param($notificationStmt, "is", $leave['UserID'], $message);
                mysqli_stmt_execute($notificationStmt);
            } catch (Exception $e) {
                error_log("Error sending leave expiry notification email: " . $e->getMessage());
            }
        }
        
        return true;
    }
}