<?php
session_start();
include "../includes/dbconnect.php";
include "../includes/functions.php";

// Check if user is logged in and is an HR admin
if (!isset($_SESSION['UserID']) || !isset($_SESSION['hr']) || $_SESSION['hr'] != 1) {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

// Check if request ID is provided
if (!isset($_GET['id'])) {
    header("Location: leave_requests.php?error=no_id");
    exit();
}

$requestID = $_GET['id'];

// Check if RejectionReason column exists
$checkColumnQuery = "SHOW COLUMNS FROM `leave_requests` LIKE 'RejectionReason'";
$columnExists = $conn->query($checkColumnQuery)->num_rows > 0;

// Modify the query based on whether the column exists
$query = "SELECT lr.RequestID, lr.UserID, u.Fullnames, u.Username, u.EmailAddress, 
          lt.LeaveTypeID, lt.LeaveName, lt.MaxDays,
          lr.StartDate, lr.EndDate, lr.Reason, lr.Status, lr.AppliedOn, 
          lr.ProcessedBy, lr.ProcessedDate";
          
// Add RejectionReason to the query only if the column exists
if ($columnExists) {
    $query .= ", lr.RejectionReason";
}

$query .= ", DATEDIFF(lr.EndDate, lr.StartDate) + 1 AS Days
          FROM leave_requests lr
          JOIN users u ON lr.UserID = u.UserID
          JOIN leave_types lt ON lr.LeaveTypeID = lt.LeaveTypeID
          WHERE lr.RequestID = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $requestID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: leave_requests.php?error=not_found");
    exit();
}

$leaveRequest = $result->fetch_assoc();

// Get processor details if request was processed
$processorDetails = null;
if ($leaveRequest['ProcessedBy']) {
    $processorQuery = $conn->prepare("SELECT Fullnames FROM users WHERE UserID = ?");
    $processorQuery->bind_param("i", $leaveRequest['ProcessedBy']);
    $processorQuery->execute();
    $processorResult = $processorQuery->get_result();
    if ($processorResult->num_rows > 0) {
        $processorDetails = $processorResult->fetch_assoc()['Fullnames'];
    }
}

// Calculate remaining leave days for this type
$remainingDays = getRemainingLeaveDays($leaveRequest['UserID'], $leaveRequest['LeaveTypeID'], $conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Leave Request - HR Portal</title>
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

        .leave-details {
            background-color: #fff;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .leave-header {
            padding: 20px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #ecf0f1;
        }

        .leave-header .status-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .leave-header h2 {
            color: #2f3b52;
            font-size: 20px;
        }

        .leave-header .request-id {
            font-size: 14px;
            color: #7f8c8d;
            margin-top: 5px;
        }

        .status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }

        .status.pending {
            background-color: #ffeaa7;
            color: #f39c12;
        }

        .status.approved {
            background-color: #d4edda;
            color: #2ecc71;
        }

        .status.rejected {
            background-color: #f8d7da;
            color: #e74c3c;
        }

        .leave-body {
            padding: 20px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .info-item {
            margin-bottom: 20px;
        }

        .info-item h3 {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }

        .info-item p {
            font-size: 16px;
            color: #2f3b52;
            font-weight: bold;
        }

        .reason-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ecf0f1;
        }

        .reason-section h3 {
            font-size: 16px;
            color: #2f3b52;
            margin-bottom: 10px;
        }

        .reason-section p {
            color: #2f3b52;
            line-height: 1.6;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #3498db;
        }

        .action-section {
            padding: 20px;
            background-color: #f8f9fa;
            border-top: 1px solid #ecf0f1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            transition: background-color 0.3s;
        }

        .btn-back {
            background-color: #95a5a6;
            color: #fff;
        }

        .btn-back:hover {
            background-color: #7f8c8d;
        }

        .btn-approve {
            background-color: #2ecc71;
            color: #fff;
            margin-right: 10px;
        }

        .btn-approve:hover {
            background-color: #27ae60;
        }

        .btn-reject {
            background-color: #e74c3c;
            color: #fff;
        }

        .btn-reject:hover {
            background-color: #c0392b;
        }

        .processor-info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ecf0f1;
        }

        .processor-info h3 {
            font-size: 16px;
            color: #2f3b52;
            margin-bottom: 15px;
        }

        .employee-info {
            margin-top: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }

        .employee-info h3 {
            font-size: 16px;
            color: #2f3b52;
            margin-bottom: 15px;
        }

        .employee-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
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
        </ul>
        
        <div class="sidebar-footer">
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <div>
                <h1>Leave Request Details</h1>
                <div class="breadcrumb">
                    <a href="dashboard.php">Dashboard</a> &gt; 
                    <a href="leave_requests.php">Leave Requests</a> &gt; 
                    View Request
                </div>
            </div>
        </div>

        <div class="leave-details">
            <div class="leave-header">
                <div class="status-wrapper">
                    <div>
                        <h2><?php echo htmlspecialchars($leaveRequest['LeaveName']); ?> Leave Request</h2>
                        <div class="request-id">Request ID: <?php echo htmlspecialchars($leaveRequest['RequestID']); ?></div>
                    </div>
                    <span class="status <?php echo strtolower($leaveRequest['Status'] ?: 'pending'); ?>">
                        <?php echo htmlspecialchars($leaveRequest['Status'] ?: 'Pending'); ?>
                    </span>
                </div>
            </div>

            <div class="leave-body">
                <div class="info-grid">
                    <div class="info-item">
                        <h3>Employee</h3>
                        <p><?php echo htmlspecialchars($leaveRequest['Fullnames']); ?></p>
                    </div>
                    
                    <div class="info-item">
                        <h3>Leave Type</h3>
                        <p><?php echo htmlspecialchars($leaveRequest['LeaveName']); ?></p>
                    </div>
                    
                    <div class="info-item">
                        <h3>Start Date</h3>
                        <p><?php echo date('M d, Y', strtotime($leaveRequest['StartDate'])); ?></p>
                    </div>
                    
                    <div class="info-item">
                        <h3>End Date</h3>
                        <p><?php echo date('M d, Y', strtotime($leaveRequest['EndDate'])); ?></p>
                    </div>
                    
                    <div class="info-item">
                        <h3>Duration</h3>
                        <p><?php echo $leaveRequest['Days']; ?> day<?php echo $leaveRequest['Days'] > 1 ? 's' : ''; ?></p>
                    </div>
                    
                    <div class="info-item">
                        <h3>Applied On</h3>
                        <p><?php echo date('M d, Y - h:i A', strtotime($leaveRequest['AppliedOn'])); ?></p>
                    </div>
                    
                    <div class="info-item">
                        <h3>Status</h3>
                        <p><?php echo htmlspecialchars($leaveRequest['Status'] ?: 'Pending'); ?></p>
                    </div>
                    
                    <div class="info-item">
                        <h3>Max Leave Days</h3>
                        <p><?php echo $leaveRequest['MaxDays']; ?> day<?php echo $leaveRequest['MaxDays'] > 1 ? 's' : ''; ?></p>
                    </div>
                    
                    <div class="info-item">
                        <h3>Remaining Leave Days</h3>
                        <p><?php echo $remainingDays; ?> day<?php echo $remainingDays > 1 ? 's' : ''; ?></p>
                    </div>
                </div>

                <div class="reason-section">
                    <h3>Leave Reason</h3>
                    <p><?php echo nl2br(htmlspecialchars($leaveRequest['Reason'] ?: 'No reason provided.')); ?></p>
                </div>

                <?php if ($columnExists && $leaveRequest['Status'] === 'Rejected' && !empty($leaveRequest['RejectionReason'])): ?>
                <div class="reason-section" style="margin-top: 20px;">
                    <h3>Rejection Reason</h3>
                    <p style="border-left: 4px solid #e74c3c;"><?php echo nl2br(htmlspecialchars($leaveRequest['RejectionReason'])); ?></p>
                </div>
                <?php endif; ?>

                <?php if ($leaveRequest['ProcessedBy']): ?>
                <div class="processor-info">
                    <h3>Processing Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <h3>Processed By</h3>
                            <p><?php echo htmlspecialchars($processorDetails ?: 'Unknown'); ?></p>
                        </div>
                        
                        <div class="info-item">
                            <h3>Processed Date</h3>
                            <p><?php echo date('M d, Y - h:i A', strtotime($leaveRequest['ProcessedDate'])); ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="employee-info">
                    <h3>Employee Information</h3>
                    <div class="employee-grid">
                        <div class="info-item">
                            <h3>Full Name</h3>
                            <p><?php echo htmlspecialchars($leaveRequest['Fullnames']); ?></p>
                        </div>
                        
                        <div class="info-item">
                            <h3>Username</h3>
                            <p><?php echo htmlspecialchars($leaveRequest['Username']); ?></p>
                        </div>
                        
                        <div class="info-item">
                            <h3>Email</h3>
                            <p><?php echo htmlspecialchars($leaveRequest['EmailAddress']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="action-section">
                <a href="leave_requests.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> Back to Leave Requests</a>
                
                <div>
                    <button class="btn btn-approve" onclick="confirmAction(<?php echo $leaveRequest['RequestID']; ?>, 'approve')">
                        <i class="fas fa-check"></i> Approve Request
                    </button>
                    <button class="btn btn-reject" onclick="confirmAction(<?php echo $leaveRequest['RequestID']; ?>, 'reject')">
                        <i class="fas fa-times"></i> Reject Request
                    </button>
                </div>
            </div>

            <!-- Confirmation Dialog for Approve -->
            <div class="action-confirm" id="approveConfirmDialog" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
                <div style="background-color:white; padding:20px; border-radius:5px; box-shadow:0 0 15px rgba(0,0,0,0.2); max-width:400px; width:100%;">
                    <h3 style="margin-top:0; color:#2f3b52;">Confirm Approval</h3>
                    <p>Are you sure you want to approve this leave request?</p>
                    <p>This action will update the employee's leave balance.</p>
                    <div style="display:flex; justify-content:flex-end; margin-top:20px; gap:10px;">
                        <button class="btn" style="background-color:#95a5a6;" onclick="document.getElementById('approveConfirmDialog').style.display='none'">Cancel</button>
                        <a href="#" id="approveConfirmButton" class="btn btn-approve">Approve</a>
                    </div>
                </div>
            </div>

            <!-- Confirmation Dialog for Reject -->
            <div class="action-confirm" id="rejectConfirmDialog" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
                <div style="background-color:white; padding:20px; border-radius:5px; box-shadow:0 0 15px rgba(0,0,0,0.2); max-width:400px; width:100%;">
                    <h3 style="margin-top:0; color:#2f3b52;">Confirm Rejection</h3>
                    <p>Do you want to provide a rejection reason?</p>
                    <div style="display:flex; justify-content:flex-end; margin-top:20px; gap:10px;">
                        <button class="btn" style="background-color:#95a5a6;" onclick="document.getElementById('rejectConfirmDialog').style.display='none'">Cancel</button>
                        <a href="#" id="rejectConfirmButton" class="btn btn-reject">Reject Without Reason</a>
                        <a href="#" id="rejectWithReasonButton" class="btn" style="background-color:#e67e22;">Add Reason</a>
                    </div>
                </div>
            </div>

            <script>
                function confirmAction(requestId, action) {
                    if (action === 'approve') {
                        document.getElementById('approveConfirmButton').href = 'process_leave.php?id=' + requestId + '&action=approve';
                        document.getElementById('approveConfirmDialog').style.display = 'flex';
                    } else if (action === 'reject') {
                        document.getElementById('rejectConfirmButton').href = 'process_leave.php?id=' + requestId + '&action=reject';
                        document.getElementById('rejectWithReasonButton').href = 'process_leave.php?id=' + requestId + '&action=reject';
                        document.getElementById('rejectConfirmDialog').style.display = 'flex';
                    }
                }
            </script>
        </div>
    </div>
</body>
</html>

<?php $conn->close(); ?>
