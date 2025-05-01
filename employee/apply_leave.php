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
$username = $_SESSION['Username'];
$message = "";
$error = "";

// Get count of pending substitute requests for the badge
$pendingSubsQuery = "SELECT COUNT(*) as count FROM leave_substitutes 
                    WHERE SubstituteID = ? AND Status = 'Pending'";
$pendingStmt = $conn->prepare($pendingSubsQuery);
$pendingStmt->bind_param("i", $userID);
$pendingStmt->execute();
$pendingResult = $pendingStmt->get_result();
$pendingSubstitutes = $pendingResult->fetch_assoc()['count'];

// Check if the user has any pending leave requests
$hasPendingRequests = hasPendingLeaveRequests($userID, $conn);

// Get all leave types
$leaveTypesQuery = "SELECT * FROM leave_types ORDER BY LeaveName";
$leaveTypesResult = mysqli_query($conn, $leaveTypesQuery);

// Get all available employees for substitute selection (excluding current user and those on leave OR serving as substitutes)
$employeesQuery = "SELECT u.UserID, u.Fullnames 
                  FROM users u 
                  WHERE u.UserID != $userID 
                  AND u.hr = 0 
                  AND u.admin = 0
                  AND u.UserID NOT IN ( 
                      SELECT lr.UserID 
                      FROM leave_requests lr 
                      WHERE lr.Status = 'Approved' 
                      AND (
                          (lr.StartDate <= DATE_ADD(NOW(), INTERVAL 14 DAY) AND lr.EndDate >= NOW())
                          OR (lr.StartDate <= DATE_ADD(NOW(), INTERVAL 14 DAY) AND lr.EndDate >= NOW())
                      )
                  )
                  AND u.UserID NOT IN (
                      SELECT ls.SubstituteID
                      FROM leave_substitutes ls
                      JOIN leave_requests lr ON ls.RequestID = lr.RequestID
                      WHERE lr.Status = 'Approved'
                      AND ls.Status = 'Accepted'
                      AND NOW() BETWEEN lr.StartDate AND lr.EndDate
                  )
                  ORDER BY u.Fullnames";
$employeesResult = mysqli_query($conn, $employeesQuery);

// Check if user has an active leave currently in progress
$activeLeaveCheck = $conn->prepare("
    SELECT lr.RequestID, lt.LeaveName, lr.StartDate, lr.EndDate 
    FROM leave_requests lr
    JOIN leave_types lt ON lr.LeaveTypeID = lt.LeaveTypeID
    WHERE lr.UserID = ? 
    AND lr.Status = 'Approved' 
    AND CURDATE() BETWEEN lr.StartDate AND lr.EndDate
");
$activeLeaveCheck->bind_param("i", $userID);
$activeLeaveCheck->execute();
$activeLeaveResult = $activeLeaveCheck->get_result();
$hasActiveLeave = $activeLeaveResult->num_rows > 0;
$activeLeave = $hasActiveLeave ? $activeLeaveResult->fetch_assoc() : null;

// Process leave application form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($hasPendingRequests) {
        $error = "You already have a pending leave request. Please wait for approval before applying for another leave.";
    } elseif ($hasActiveLeave) {
        $error = "You cannot apply for leave while you are on active leave. Your current leave ends on " . 
                date('d-M-Y', strtotime($activeLeave['EndDate'])) . ".";
    } else {
        $leaveTypeID = mysqli_real_escape_string($conn, $_POST['leaveType']);
        $startDate = mysqli_real_escape_string($conn, $_POST['startDate']);
        $endDate = mysqli_real_escape_string($conn, $_POST['endDate']);
        $reason = mysqli_real_escape_string($conn, $_POST['reason']);
        $substituteID = isset($_POST['substitute']) ? mysqli_real_escape_string($conn, $_POST['substitute']) : null;

        // Validate substitute
        if (empty($substituteID)) {
            $error = "You must select a substitute employee who will cover for you during your leave.";
        } else {
            // Check if substitute is available during the leave period (not on leave and not assigned as substitute)
            $substituteAvailabilityQuery = "SELECT 
                    (SELECT COUNT(*) FROM leave_requests 
                        WHERE UserID = ? 
                        AND Status = 'Approved'
                        AND (
                            (StartDate BETWEEN ? AND ?) 
                            OR (EndDate BETWEEN ? AND ?)
                            OR (StartDate <= ? AND EndDate >= ?)
                        )
                    ) +
                    (SELECT COUNT(*) FROM leave_substitutes ls
                        JOIN leave_requests lr ON ls.RequestID = lr.RequestID
                        WHERE ls.SubstituteID = ?
                        AND ls.Status = 'Accepted'
                        AND lr.Status = 'Approved'
                        AND (
                            (lr.StartDate BETWEEN ? AND ?) 
                            OR (lr.EndDate BETWEEN ? AND ?)
                            OR (lr.StartDate <= ? AND EndDate >= ?)
                        )
                    ) as conflict_count";
                    
            $stmt = mysqli_prepare($conn, $substituteAvailabilityQuery);
            mysqli_stmt_bind_param($stmt, "issssssissssss", 
                                 $substituteID, 
                                 $startDate, $endDate, $startDate, $endDate, $startDate, $endDate,
                                 $substituteID,
                                 $startDate, $endDate, $startDate, $endDate, $startDate, $endDate);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $conflict = mysqli_fetch_assoc($result)['conflict_count'] > 0;

            if ($conflict) {
                $error = "The selected substitute is not available during your leave period. Please select another employee.";
            } else {
                // Calculate number of days requested
                $start = new DateTime($startDate);
                $end = new DateTime($endDate);
                $interval = $start->diff($end);
                $daysRequested = $interval->days + 1; // Include both start and end days

                // Get remaining leave days for this type
                $remainingDays = getRemainingLeaveDays($userID, $leaveTypeID, $conn);

                // Validate dates
                if ($end < $start) {
                    $error = "End date cannot be before start date.";
                } elseif ($daysRequested > $remainingDays) {
                    $error = "You don't have enough leave days. You have $remainingDays days remaining but requested $daysRequested days.";
                } else {
                    // Begin transaction
                    mysqli_begin_transaction($conn);
                    try {
                        // Insert leave request
                        $insertQuery = "INSERT INTO leave_requests (UserID, LeaveTypeID, StartDate, EndDate, Reason, Status, AppliedOn) 
                                      VALUES (?, ?, ?, ?, ?, 'Pending', NOW())";
                        
                        $stmt = mysqli_prepare($conn, $insertQuery);
                        mysqli_stmt_bind_param($stmt, "iisss", $userID, $leaveTypeID, $startDate, $endDate, $reason);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $requestID = mysqli_insert_id($conn);
                            
                            // Check the structure of the leave_substitutes table
                            $checkTableQuery = "SHOW COLUMNS FROM leave_substitutes LIKE 'UserID'";
                            $checkResult = mysqli_query($conn, $checkTableQuery);
                            $hasUserIdColumn = mysqli_num_rows($checkResult) > 0;
                            
                            // Create or update leave_substitutes table if needed
                            if (!$hasUserIdColumn) {
                                // Table exists but doesn't have UserID column - add it
                                $alterTableQuery = "ALTER TABLE leave_substitutes ADD COLUMN UserID INT(11) NOT NULL AFTER RequestID";
                                mysqli_query($conn, $alterTableQuery);
                            } else if (mysqli_num_rows($checkResult) == 0) {
                                // Table doesn't exist - create it
                                $createTableQuery = "CREATE TABLE IF NOT EXISTS leave_substitutes (
                                    ID INT(11) AUTO_INCREMENT PRIMARY KEY,
                                    RequestID INT(11) NOT NULL,
                                    UserID INT(11) NOT NULL,
                                    SubstituteID INT(11) NOT NULL,
                                    Status ENUM('Pending', 'Accepted', 'Rejected') DEFAULT 'Pending',
                                    NotificationSent BOOLEAN DEFAULT FALSE,
                                    DateAssigned TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                    FOREIGN KEY (RequestID) REFERENCES leave_requests(RequestID) ON DELETE CASCADE
                                )";
                                mysqli_query($conn, $createTableQuery);
                            }
                            
                            // Now insert the substitute record with appropriate columns
                            $insertSubQuery = "";
                            
                            if ($hasUserIdColumn) {
                                $insertSubQuery = "INSERT INTO leave_substitutes (RequestID, UserID, SubstituteID, Status) 
                                                VALUES (?, ?, ?, 'Pending')";
                                $stmtSub = mysqli_prepare($conn, $insertSubQuery);
                                mysqli_stmt_bind_param($stmtSub, "iii", $requestID, $userID, $substituteID);
                            } else {
                                // Fallback if we couldn't add the UserID column for some reason
                                $insertSubQuery = "INSERT INTO leave_substitutes (RequestID, SubstituteID, Status) 
                                                VALUES (?, ?, 'Pending')";
                                $stmtSub = mysqli_prepare($conn, $insertSubQuery);
                                mysqli_stmt_bind_param($stmtSub, "ii", $requestID, $substituteID);
                            }
                            
                            if (!$stmtSub) {
                                throw new Exception("SQL prepare error: " . mysqli_error($conn));
                            }
                            
                            $subResult = mysqli_stmt_execute($stmtSub);
                            if (!$subResult) {
                                throw new Exception("Error assigning substitute: " . mysqli_stmt_error($stmtSub));
                            }
                            
                            // Create notifications table if it doesn't exist
                            $createNotifTableQuery = "CREATE TABLE IF NOT EXISTS notifications (
                                ID INT(11) AUTO_INCREMENT PRIMARY KEY,
                                UserID INT(11) NOT NULL,
                                Message TEXT NOT NULL,
                                Type VARCHAR(50) NOT NULL,
                                IsRead BOOLEAN DEFAULT FALSE,
                                CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                FOREIGN KEY (UserID) REFERENCES users(UserID)
                            )";
                            mysqli_query($conn, $createNotifTableQuery);

                            // Get requester's name
                            $requesterQuery = "SELECT Fullnames FROM users WHERE UserID = ?";
                            $stmtReq = mysqli_prepare($conn, $requesterQuery);
                            mysqli_stmt_bind_param($stmtReq, "i", $userID);
                            mysqli_stmt_execute($stmtReq);
                            $requesterResult = mysqli_stmt_get_result($stmtReq);
                            $requesterName = mysqli_fetch_assoc($requesterResult)['Fullnames'];

                            // Insert notification for substitute
                            $notificationMsg = "$requesterName has requested you to be their substitute from " . 
                                            date('d-M-Y', strtotime($startDate)) . " to " . 
                                            date('d-M-Y', strtotime($endDate)) . 
                                            ". Please respond to this request.";
                            
                            $insertNotifQuery = "INSERT INTO notifications (UserID, Message, Type) VALUES (?, ?, 'substitute_request')";
                            $stmtNotif = mysqli_prepare($conn, $insertNotifQuery);
                            mysqli_stmt_bind_param($stmtNotif, "is", $substituteID, $notificationMsg);
                            mysqli_stmt_execute($stmtNotif);

                            mysqli_commit($conn);
                            $message = "Leave application submitted successfully! The selected substitute will be notified.";
                            header("Location: dashboard.php");
                            exit();
                        }
                    } catch (Exception $e) {
                        mysqli_rollback($conn);
                        $error = "Error submitting leave application: " . $e->getMessage();
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Leave - Leave Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/employee_dashboard.css">
</head>
<body>
<div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-none d-md-block bg-dark sidebar">
                <div class="sidebar-sticky">
                    <div class="sidebar-header">
                        <h3>Leave Portal</h3>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="apply_leave.php">
                                <i class="fas fa-calendar-plus"></i> Apply for Leave
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="leave_history.php">
                                <i class="fas fa-history"></i> Leave History
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="substitute_requests.php">
                                <i class="fas fa-user-friends"></i> Substitute Requests
                                <?php if ($pendingSubstitutes > 0): ?>
                                <span class="badge badge-warning ml-2"><?php echo $pendingSubstitutes; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="leave_reports.php">
                                <i class="fas fa-chart-bar"></i> Leave Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            <!-- Main content -->
            <main role="main" class="col-md-10 ml-sm-auto px-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Apply for Leave</h1>
                    <div class="user-info">
                        <span>Welcome, <?php echo htmlspecialchars($username); ?></span>
                    </div>
                </div>
                
                <?php if ($hasPendingRequests): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-circle"></i> You already have a pending leave request. Please wait for it to be processed before applying for another leave.
                </div>
                <?php endif; ?>
                
                <?php if (!empty($message)): ?>
                <div class="alert alert-success">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <!-- Display active leave warning if user is currently on leave -->
                <?php if ($hasActiveLeave): ?>
                    <div class="alert alert-warning">
                        <strong><i class="fas fa-exclamation-triangle"></i> You are currently on leave!</strong>
                        <p>You are currently on <?php echo htmlspecialchars($activeLeave['LeaveName']); ?> leave until 
                        <?php echo date('d-M-Y', strtotime($activeLeave['EndDate'])); ?>. 
                        You cannot apply for another leave until your current leave ends.</p>
                        <p>You are expected to resume duty on 
                        <?php echo date('d-M-Y', strtotime('+1 day', strtotime($activeLeave['EndDate']))); ?>.</p>
                    </div>
                <?php endif; ?>
                
                <!-- Leave Application Form -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Leave Application Form</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="apply_leave.php">
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="leaveType">Leave Type <span class="text-danger">*</span></label>
                                    <select class="form-control" id="leaveType" name="leaveType" required <?php echo $hasPendingRequests || $hasActiveLeave ? 'disabled' : ''; ?>>
                                        <option value="">Select Leave Type</option>
                                        <?php 
                                        if (mysqli_num_rows($leaveTypesResult) > 0) {
                                            while ($row = mysqli_fetch_assoc($leaveTypesResult)) {
                                                $leaveTypeID = $row['LeaveTypeID'];
                                                $remainingDays = getRemainingLeaveDays($userID, $leaveTypeID, $conn);
                                                echo '<option value="' . $row['LeaveTypeID'] . '">' . 
                                                     htmlspecialchars($row['LeaveName']) . ' (' . $remainingDays . ' days remaining)' . 
                                                     '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="substitute">Designate Substitute <span class="text-danger">*</span></label>
                                    <select class="form-control" id="substitute" name="substitute" required <?php echo $hasPendingRequests || $hasActiveLeave ? 'disabled' : ''; ?>>
                                        <option value="">Select Employee to Step In During Your Leave</option>
                                        <?php 
                                        if (mysqli_num_rows($employeesResult) > 0) {
                                            while ($row = mysqli_fetch_assoc($employeesResult)) {
                                                echo '<option value="' . $row['UserID'] . '">' . 
                                                     htmlspecialchars($row['Fullnames']) . 
                                                     '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                    <small class="form-text text-muted">Only employees who are available during your leave period are shown.</small>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="startDate">Start Date</label>
                                    <input type="date" class="form-control" id="startDate" name="startDate" required <?php echo $hasPendingRequests || $hasActiveLeave ? 'disabled' : ''; ?> onchange="updateAvailableSubstitutes()">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="endDate">End Date</label>
                                    <input type="date" class="form-control" id="endDate" name="endDate" required <?php echo $hasPendingRequests || $hasActiveLeave ? 'disabled' : ''; ?> onchange="updateAvailableSubstitutes()">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="reason">Reason for Leave</label>
                                <textarea class="form-control" id="reason" name="reason" rows="3" <?php echo $hasPendingRequests || $hasActiveLeave ? 'disabled' : ''; ?>></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary" <?php echo $hasPendingRequests || $hasActiveLeave ? 'disabled' : ''; ?>>Submit Application</button>
                        </form>
                    </div>
                </div>

                <!-- Substitute Guidelines Card -->
                <div class="card mt-4"></div>
                    <div class="card-header">
                        <h5 class="card-title mb-0">Substitute Guidelines</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled"></ul></ul>
                            <li><i class="fas fa-info-circle text-info mr-2"></i> The substitute employee will be notified of your request.</li>
                            <li><i class="fas fa-clock text-warning mr-2"></i> They must accept or reject the substitution request.</li>
                            <li><i class="fas fa-exclamation-triangle text-danger mr-2"></i> If they reject it, you'll need to select another substitute.</li>
                            <li><i class="fas fa-check-circle text-success mr-2"></i> Only employees who are not on leave during your requested period are shown.</li>
                        </ul>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Date validation - Start date cannot be in the past and end date cannot be before start date
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('startDate').setAttribute('min', today);
            
            document.getElementById('startDate').addEventListener('change', function() {
                document.getElementById('endDate').setAttribute('min', this.value);
            });
        });

        // Function to update available substitutes based on selected dates
        function updateAvailableSubstitutes() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            
            if (startDate && endDate) {
                // Use fetch to call a server endpoint to get available substitutes
                fetch('get_available_substitutes.php?start_date=' + startDate + '&end_date=' + endDate)
                    .then(response => response.json())
                    .then(data => {
                        const substituteSelect = document.getElementById('substitute');
                        // Clear current options except the first one
                        while (substituteSelect.options.length > 1) {
                            substituteSelect.remove(1);
                        }
                        
                        // Add available substitutes
                        data.forEach(sub => {
                            const option = document.createElement('option');
                            option.value = sub.UserID;
                            option.textContent = sub.Fullnames;
                            substituteSelect.appendChild(option);
                        });
                    })
                    .catch(error => console.error('Error fetching substitutes:', error));
            }
        }
    </script>
</body>
</html>