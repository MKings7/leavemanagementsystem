<?php
session_start();
include "../includes/dbconnect.php";
include "../includes/functions.php";

// Check if user is logged in
if (!isset($_SESSION['UserID'])) {
    header("Location: ../login.php");
    exit();
}

$userID = $_SESSION['UserID'];
$message = '';
$error = '';

// Check if user has an active leave currently in progress
$activeLeaveCheck = $conn->prepare("
    SELECT RequestID FROM leave_requests 
    WHERE UserID = ? 
    AND Status = 'Approved' 
    AND CURDATE() BETWEEN StartDate AND EndDate
");
$activeLeaveCheck->bind_param("i", $userID);
$activeLeaveCheck->execute();
$activeLeaveResult = $activeLeaveCheck->get_result();
$hasActiveLeave = $activeLeaveResult->num_rows > 0;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($hasActiveLeave) {
        $error = "You cannot apply for leave while you are on active leave.";
        header("Location: apply_leave.php?error=" . urlencode($error));
        exit();
    }
    
    $leaveTypeID = $_POST['leave_type'];
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $reason = $_POST['reason'];
    $substituteID = isset($_POST['substitute']) ? $_POST['substitute'] : null;
    
    // Verify dates are valid
    if (strtotime($startDate) > strtotime($endDate)) {
        $error = "End date cannot be before start date.";
    } else {
        // Check for overlapping leave periods
        $overlapCheck = $conn->prepare("
            SELECT COUNT(*) as count FROM leave_requests 
            WHERE UserID = ? 
            AND Status IN ('Approved', 'Pending') 
            AND (
                (StartDate BETWEEN ? AND ?) OR 
                (EndDate BETWEEN ? AND ?) OR 
                (? BETWEEN StartDate AND EndDate) OR
                (? BETWEEN StartDate AND EndDate)
            )
        ");
        $overlapCheck->bind_param("issssss", $userID, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate);
        $overlapCheck->execute();
        $overlapResult = $overlapCheck->get_result();
        $overlapCount = $overlapResult->fetch_assoc()['count'];
        
        if ($overlapCount > 0) {
            $error = "You already have an approved or pending leave request for this period.";
        } else {
            try {
                // Begin transaction
                $conn->begin_transaction();
                
                // Insert leave request
                $stmt = $conn->prepare("INSERT INTO leave_requests 
                                      (UserID, LeaveTypeID, StartDate, EndDate, Reason, Status, AppliedOn) 
                                      VALUES (?, ?, ?, ?, ?, 'Pending', NOW())");
                
                $stmt->bind_param("iisss", $userID, $leaveTypeID, $startDate, $endDate, $reason);
                
                if ($stmt->execute()) {
                    $requestID = $conn->insert_id;
                    
                    // If substitute is selected, add to leave_substitutes table
                    if ($substituteID) {
                        // Check the table structure to ensure we have the correct columns
                        // Include both UserID and RequestID in the insert statement
                        $substmt = $conn->prepare("INSERT INTO leave_substitutes 
                                                (RequestID, UserID, SubstituteID) 
                                                VALUES (?, ?, ?)");
                        if (!$substmt) {
                            // Log the error for debugging
                            error_log("SQL Error in substitute preparation: " . $conn->error);
                            throw new Exception("Database error while preparing substitute statement");
                        }
                        
                        $substmt->bind_param("iii", $requestID, $userID, $substituteID);
                        if (!$substmt->execute()) {
                            // Log the error for debugging
                            error_log("SQL Error in substitute execution: " . $substmt->error);
                            throw new Exception("Database error while assigning substitute");
                        }
                        
                        // Notify substitute
                        $notifyStmt = $conn->prepare("
                            INSERT INTO notifications (UserID, Message, Type, IsRead)
                            VALUES (?, ?, 'substitute_request', 0)
                        ");
                        
                        // Get employee name
                        $nameStmt = $conn->prepare("SELECT Fullnames FROM users WHERE UserID = ?");
                        $nameStmt->bind_param("i", $userID);
                        $nameStmt->execute();
                        $nameResult = $nameStmt->get_result();
                        $employeeName = $nameResult->fetch_assoc()['Fullnames'];
                        
                        $notifyMessage = $employeeName . " has requested you to be their substitute from " . 
                                  date('d-M-Y', strtotime($startDate)) . " to " . 
                                  date('d-M-Y', strtotime($endDate)) . ". Please respond to this request.";
                        
                        $notifyStmt->bind_param("is", $substituteID, $notifyMessage);
                        $notifyStmt->execute();
                    }
                    
                    // Send email notification
                    try {
                        include_once "../includes/email_functions.php";
                        sendLeaveRequestNotification($conn, $requestID);
                    } catch (Exception $e) {
                        // Log error but continue processing
                        error_log("Failed to send email notification: " . $e->getMessage());
                    }
                    
                    $conn->commit();
                    $message = "Leave request submitted successfully!";
                } else {
                    throw new Exception("Failed to submit leave request: " . $stmt->error);
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
                
                // Log detailed error for debugging
                error_log("Leave submission error: " . $error . 
                         "\nQuery attempted with UserID=$userID, LeaveTypeID=$leaveTypeID");
            }
        }
    }
}

// Get available leave types
$leaveTypesQuery = $conn->query("SELECT * FROM leave_types WHERE Status = 1 ORDER BY LeaveName");

// Get potential substitutes (other employees)
$substitutesQuery = $conn->query("SELECT UserID, Fullnames FROM users 
                                WHERE UserID != $userID AND hr = 0 AND admin = 0 
                                ORDER BY Fullnames");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Leave</title>
    <!-- Page styling here -->
</head>
<body>
    <!-- Form content here -->
</body>
</html>
