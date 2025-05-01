<?php
session_start();
include "../includes/dbconnect.php";

// Check if user is logged in and is an HR admin
if (!isset($_SESSION['UserID']) || !isset($_SESSION['hr']) || $_SESSION['hr'] != 1) {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$userID = $_SESSION['UserID'];
$username = $_SESSION['Username'];

// Check if request ID and action are provided
if (!isset($_GET['id']) || !isset($_GET['action'])) {
    header("Location: dashboard.php?error=invalid_request");
    exit();
}

$requestID = $_GET['id'];
$action = $_GET['action'];

// Validate action
if ($action !== 'approve' && $action !== 'reject') {
    header("Location: dashboard.php?error=invalid_action");
    exit();
}

// Check if RejectionReason column exists
$checkColumnQuery = "SHOW COLUMNS FROM `leave_requests` LIKE 'RejectionReason'";
$columnExists = $conn->query($checkColumnQuery)->num_rows > 0;

// Process the action
if ($action === 'approve' || ($action === 'reject' && $_SERVER['REQUEST_METHOD'] !== 'POST')) {
    $status = ($action === 'approve') ? 'Approved' : 'Rejected';
    $currentTime = date('Y-m-d H:i:s');
    
    // Regular update for approvals or rejections without reason
    $stmt = $conn->prepare("UPDATE leave_requests SET Status = ?, ProcessedBy = ?, ProcessedDate = ? WHERE RequestID = ?");
    $stmt->bind_param("sssi", $status, $userID, $currentTime, $requestID);
    
    if ($stmt->execute()) {
        // Fetch employee details
        $requestQuery = $conn->prepare("SELECT UserID, StartDate, EndDate, LeaveTypeID FROM leave_requests WHERE RequestID = ?");
        $requestQuery->bind_param("i", $requestID);
        $requestQuery->execute();
        $result = $requestQuery->get_result();
        $leaveRequest = $result->fetch_assoc();
        $employeeID = $leaveRequest['UserID'];
        
        // Send email notification
        include_once "../includes/email_functions.php";
        $emailStatus = "Not sent";
        
        try {
            // Check if email notifications are enabled
            if (areNotificationsEnabled($conn)) {
                if (sendLeaveStatusNotification($conn, $requestID, $status)) {
                    $emailStatus = "Success";
                }
            } else {
                $emailStatus = "Disabled";
            }
            
            // Always add a database notification for the employee
            $message = "Your leave request has been " . strtolower($status) . ".";
            $conn->query("INSERT INTO notifications (UserID, Message, Type, IsRead) VALUES ($employeeID, '$message', 'leave_status', 0)");
            
            // If approved, check for substitute and notify them
            if ($status === 'Approved') {
                try {
                    // Modified query to avoid using ls.UserID column
                    $subQuery = $conn->prepare("SELECT 
                            ls.SubstituteID, 
                            u.Fullnames AS EmployeeName, 
                            u2.Fullnames AS SubstituteName, 
                            u2.EmailAddress AS SubstituteEmail, 
                            lt.LeaveName
                        FROM leave_substitutes ls
                        JOIN leave_requests lr ON ls.RequestID = lr.RequestID 
                        JOIN users u ON lr.UserID = u.UserID
                        JOIN users u2 ON ls.SubstituteID = u2.UserID
                        JOIN leave_types lt ON lr.LeaveTypeID = lt.LeaveTypeID
                        WHERE ls.RequestID = ?");
                    
                    $subQuery->bind_param("i", $requestID);
                    $subQuery->execute();
                    $subResult = $subQuery->get_result();
                    
                    if ($subResult->num_rows > 0) {
                        $substitute = $subResult->fetch_assoc();
                        
                        // Format dates for display
                        $startDate = date('d-M-Y', strtotime($leaveRequest['StartDate']));
                        $endDate = date('d-M-Y', strtotime($leaveRequest['EndDate']));
                        $resumeDate = date('d-M-Y', strtotime('+1 day', strtotime($leaveRequest['EndDate'])));
                        
                        // Create notification for substitute
                        $subMessage = "Leave for {$substitute['EmployeeName']} has been approved. You are assigned as substitute from $startDate to $endDate for {$substitute['LeaveName']} leave. They will resume duty on $resumeDate.";
                        $conn->query("INSERT INTO notifications (UserID, Message, Type, IsRead) 
                                    VALUES ({$substitute['SubstituteID']}, '$subMessage', 'substitute_assigned', 0)");
                        
                        // Send email to substitute
                        if (areNotificationsEnabled($conn)) {
                            try {
                                sendSubstituteNotification($conn, $requestID);
                            } catch (Exception $e) {
                                error_log("Failed to send substitute email: " . $e->getMessage());
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error processing substitute notification: " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            // Log error but continue processing
            error_log("Error sending notification: " . $e->getMessage());
            $emailStatus = "Failed: " . $e->getMessage();
        }
        
        // Log the action
        $logMessage = "Leave request #$requestID was $status by user $userID at $currentTime | Email: $emailStatus\n";
        file_put_contents('../leave_actions.log', $logMessage, FILE_APPEND);
        
        header("Location: leave_requests.php?success=request_" . strtolower($status) . "&email=" . urlencode($emailStatus));
        exit();
    } else {
        header("Location: leave_requests.php?error=db_error&message=" . urlencode($stmt->error));
        exit();
    }
    
    $stmt->close();
} elseif ($action === 'reject') {
    // Get leave request details for the form
    $detailsQuery = $conn->prepare("
        SELECT lr.RequestID, u.Fullnames, lt.LeaveName, lr.StartDate, lr.EndDate,
        DATEDIFF(lr.EndDate, lr.StartDate) + 1 AS Days
        FROM leave_requests lr
        JOIN users u ON lr.UserID = u.UserID
        JOIN leave_types lt ON lr.LeaveTypeID = lt.LeaveTypeID
        WHERE lr.RequestID = ?
    ");
    
    $detailsQuery->bind_param("i", $requestID);
    $detailsQuery->execute();
    $result = $detailsQuery->get_result();
    
    if ($result->num_rows === 0) {
        header("Location: leave_requests.php?error=not_found");
        exit();
    }
    
    $leaveDetails = $result->fetch_assoc();
    
    // If form is submitted with rejection reason
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['rejection_reason'])) {
        $status = 'Rejected';
        $currentTime = date('Y-m-d H:i:s');
        $rejectionReason = $_POST['rejection_reason'];
        
        // Update with rejection reason if column exists
        if ($columnExists) {
            $stmt = $conn->prepare("UPDATE leave_requests SET Status = ?, ProcessedBy = ?, ProcessedDate = ?, RejectionReason = ? WHERE RequestID = ?");
            $stmt->bind_param("ssssi", $status, $userID, $currentTime, $rejectionReason, $requestID);
        } else {
            // Fallback if column doesn't exist
            $stmt = $conn->prepare("UPDATE leave_requests SET Status = ?, ProcessedBy = ?, ProcessedDate = ? WHERE RequestID = ?");
            $stmt->bind_param("sssi", $status, $userID, $currentTime, $requestID);
        }
        
        if ($stmt->execute()) {
            // Send email notification
            include_once "../includes/email_functions.php";
            $emailStatus = "Not sent";
            
            try {
                // Check if email notifications are enabled
                if (areNotificationsEnabled($conn)) {
                    if (sendLeaveStatusNotification($conn, $requestID, $status)) {
                        $emailStatus = "Success";
                    }
                } else {
                    $emailStatus = "Disabled";
                }
                
                // Always add a database notification
                $requestQuery = $conn->prepare("SELECT UserID FROM leave_requests WHERE RequestID = ?");
                $requestQuery->bind_param("i", $requestID);
                $requestQuery->execute();
                $result = $requestQuery->get_result();
                $employeeID = $result->fetch_assoc()['UserID'];
                
                // Create notification with rejection reason
                $message = "Your leave request has been rejected. Reason: " . $rejectionReason;
                $conn->query("INSERT INTO notifications (UserID, Message, Type, IsRead) VALUES ($employeeID, '$message', 'leave_status', 0)");
            } catch (Exception $e) {
                // Log error but continue processing
                error_log("Error sending notification: " . $e->getMessage());
                $emailStatus = "Failed: " . $e->getMessage();
            }
            
            header("Location: leave_requests.php?success=request_rejected&email=" . urlencode($emailStatus));
            exit();
        } else {
            header("Location: leave_requests.php?error=db_error");
            exit();
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reject Leave Request - HR Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            display: flex;
            min-height: 100vh;
            background-color: #f5f6fa;
        }

        .sidebar {
            width: 250px;
            background-color: #2f3b52;
            color: #fff;
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #3a4a66;
        }

        .sidebar-header h2 {
            font-size: 24px;
            color: #fff;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: block;
            padding: 12px 20px;
            color: #bdc3c7;
            text-decoration: none;
            transition: all 0.3s;
        }

        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: #3a4a66;
            color: #fff;
        }

        .sidebar-menu a i {
            margin-right: 10px;
        }

        .sidebar-footer {
            position: absolute;
            bottom: 20px;
            width: 100%;
            padding: 0 20px;
        }

        .sidebar-footer a {
            display: block;
            padding: 12px 20px;
            color: #bdc3c7;
            text-decoration: none;
            transition: all 0.3s;
        }

        .sidebar-footer a:hover {
            color: #fff;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            margin-left: 250px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .header h1 {
            font-size: 24px;
            color: #2f3b52;
        }

        .header .breadcrumb {
            font-size: 14px;
            color: #7f8c8d;
        }

        .header .breadcrumb a {
            color: #3498db;
            text-decoration: none;
        }

        .form-container {
            background-color: #fff;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .form-container h2 {
            margin-bottom: 20px;
            color: #2f3b52;
            padding-bottom: 10px;
            border-bottom: 1px solid #ecf0f1;
        }

        .leave-details {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border-left: 3px solid #e74c3c;
        }

        .leave-details p {
            margin: 5px 0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #2f3b52;
        }

        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            min-height: 120px;
        }

        .button-group {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
            gap: 10px;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: background-color 0.3s;
            text-decoration: none;
        }

        .btn-cancel {
            background-color: #95a5a6;
            color: #fff;
        }

        .btn-cancel:hover {
            background-color: #7f8c8d;
        }

        .btn-reject {
            background-color: #e74c3c;
            color: #fff;
        }

        .btn-reject:hover {
            background-color: #c0392b;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>HR Portal</h2>
        </div>
        
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="manage_employees.php"><i class="fas fa-users"></i> Employees</a></li>
            <li><a href="leave_requests.php" class="active"><i class="fas fa-calendar-check"></i> Leave Requests</a></li>
            <li><a href="leave_types.php"><i class="fas fa-list"></i> Leave Types</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <?php if (isset($_SESSION['admin']) && $_SESSION['admin'] == 1): ?>
            <li><a href="../admin/scheduled_tasks.php"><i class="fas fa-clock"></i> Scheduled Tasks</a></li>
            <?php endif; ?>
        </ul>
        
        <div class="sidebar-footer">
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <div>
                <h1>Reject Leave Request</h1>
                <div class="breadcrumb">
                    <a href="dashboard.php">Dashboard</a> &gt; 
                    <a href="leave_requests.php">Leave Requests</a> &gt; 
                    Reject Request
                </div>
            </div>
        </div>

        <div class="form-container">
            <h2>Reject Leave Request #<?php echo $leaveDetails['RequestID']; ?></h2>
            
            <div class="leave-details">
                <p><strong>Employee:</strong> <?php echo htmlspecialchars($leaveDetails['Fullnames']); ?></p>
                <p><strong>Leave Type:</strong> <?php echo htmlspecialchars($leaveDetails['LeaveName']); ?></p>
                <p><strong>Period:</strong> <?php echo htmlspecialchars($leaveDetails['StartDate']); ?> to <?php echo htmlspecialchars($leaveDetails['EndDate']); ?> (<?php echo $leaveDetails['Days']; ?> days)</p>
            </div>
            
            <form action="process_leave.php?id=<?php echo $requestID; ?>&action=reject" method="post">
                <div class="form-group">
                    <label for="rejection_reason">Reason for Rejection:</label>
                    <textarea id="rejection_reason" name="rejection_reason" required placeholder="Please provide a reason for rejecting this leave request..."></textarea>
                </div>
                
                <div class="button-group">
                    <a href="leave_requests.php" class="btn btn-cancel">Cancel</a>
                    <button type="submit" class="btn btn-reject">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
<?php
}

$conn->close();
?>
