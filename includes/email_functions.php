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

        // Get request details
        $query = "SELECT lr.*, u.Email as UserEmail, u.Fullnames, lt.LeaveName
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
            
            // Send to admin/HR
            $mail->clearAddresses();
            $adminQuery = "SELECT Email, Fullnames FROM users WHERE admin = 1 OR hr = 1";
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