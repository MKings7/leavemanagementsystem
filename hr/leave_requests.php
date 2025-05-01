<?php
session_start();
include "../includes/dbconnect.php";
include "../includes/functions.php";

// Check if user is logged in and is an HR admin
if (!isset($_SESSION['UserID']) || !isset($_SESSION['hr']) || $_SESSION['hr'] != 1) {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

$userID = $_SESSION['UserID'];
$username = $_SESSION['Username'];

// Handle filters
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$employeeFilter = isset($_GET['employee']) ? $_GET['employee'] : '';
$leaveTypeFilter = isset($_GET['leave_type']) ? $_GET['leave_type'] : '';

// Modify the query to check if RejectionReason column exists
$checkColumnQuery = "SHOW COLUMNS FROM `leave_requests` LIKE 'RejectionReason'";
$columnExists = $conn->query($checkColumnQuery)->num_rows > 0;

// Build the query based on whether the column exists
$query = "SELECT lr.RequestID, u.UserID, u.Fullnames, lt.LeaveTypeID, lt.LeaveName, 
          lr.StartDate, lr.EndDate, lr.Reason, lr.Status, lr.AppliedOn";
          
// Add RejectionReason to the query only if the column exists
if ($columnExists) {
    $query .= ", lr.RejectionReason";
}

$query .= ", DATEDIFF(lr.EndDate, lr.StartDate) + 1 AS Days
          FROM leave_requests lr
          JOIN users u ON lr.UserID = u.UserID
          JOIN leave_types lt ON lr.LeaveTypeID = lt.LeaveTypeID
          WHERE 1=1";

// Add filters if provided
if (!empty($statusFilter)) {
    $query .= " AND lr.Status = '$statusFilter'";
}

if (!empty($employeeFilter)) {
    $query .= " AND u.UserID = '$employeeFilter'";
}

if (!empty($leaveTypeFilter)) {
    $query .= " AND lt.LeaveTypeID = '$leaveTypeFilter'";
}

// Add sorting
$query .= " ORDER BY 
            CASE WHEN lr.Status = 'Pending' THEN 0 ELSE 1 END,
            lr.AppliedOn DESC";

$result = $conn->query($query);

// Get employees for filter dropdown
$employees = $conn->query("SELECT UserID, Fullnames FROM users WHERE hr = 0 ORDER BY Fullnames ASC");

// Get leave types for filter dropdown
$leaveTypes = $conn->query("SELECT LeaveTypeID, LeaveName FROM leave_types ORDER BY LeaveName ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Requests - HR Portal</title>
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

        .filters {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .form-group {
            flex: 1;
            min-width: 200px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #2f3b52;
        }

        .form-group select, .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #3498db;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #2980b9;
        }

        .btn-filter {
            background-color: #3498db;
        }

        .btn-reset {
            background-color: #95a5a6;
            margin-left: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
            overflow: hidden;
        }

        table th, table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }

        table th {
            background-color: #f8f9fa;
            color: #2f3b52;
            font-weight: bold;
        }

        table tbody tr:hover {
            background-color: #f9f9f9;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
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

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-view {
            background-color: #3498db;
            padding: 5px 10px;
            font-size: 12px;
        }

        .btn-approve {
            background-color: #2ecc71;
            padding: 5px 10px;
            font-size: 12px;
        }

        .btn-reject {
            background-color: #e74c3c;
            padding: 5px 10px;
            font-size: 12px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .pagination a {
            display: inline-block;
            padding: 8px 16px;
            text-decoration: none;
            background-color: #fff;
            color: #2f3b52;
            border: 1px solid #ddd;
            margin: 0 4px;
            transition: all 0.3s;
        }

        .pagination a:hover {
            background-color: #f1f1f1;
        }

        .pagination a.active {
            background-color: #3498db;
            color: white;
            border: 1px solid #3498db;
        }
        
        .tooltip {
            position: relative;
            display: inline-block;
            cursor: help;
        }
        
        .tooltip .tooltiptext {
            visibility: hidden;
            width: 250px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 10px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            font-weight: normal;
            font-size: 12px;
        }
        
        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
        
        .action-confirm {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .confirm-dialog {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 15px rgba(0,0,0,0.2);
            max-width: 400px;
            width: 100%;
        }
        
        .confirm-dialog h3 {
            margin-top: 0;
            color: #2f3b52;
        }
        
        .confirm-buttons {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
            gap: 10px;
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
            <h1>Manage Leave Requests</h1>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <?php 
                    if ($_GET['success'] === 'request_approved') {
                        echo "Leave request has been approved successfully.";
                    } elseif ($_GET['success'] === 'request_rejected') {
                        echo "Leave request has been rejected.";
                    }
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                    if ($_GET['error'] === 'db_error') {
                        echo "Database error occurred. Please try again.";
                    } elseif ($_GET['error'] === 'invalid_request') {
                        echo "Invalid request. Please try again.";
                    } elseif ($_GET['error'] === 'invalid_action') {
                        echo "Invalid action. Please try again.";
                    }
                ?>
            </div>
        <?php endif; ?>

        <div class="filters">
            <form action="" method="get" class="filter-form">
                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="">All Statuses</option>
                        <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Approved" <?php echo $statusFilter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="Rejected" <?php echo $statusFilter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="employee">Employee</label>
                    <select name="employee" id="employee">
                        <option value="">All Employees</option>
                        <?php while ($employee = $employees->fetch_assoc()): ?>
                            <option value="<?php echo $employee['UserID']; ?>" <?php echo $employeeFilter == $employee['UserID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($employee['Fullnames']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="leave_type">Leave Type</label>
                    <select name="leave_type" id="leave_type">
                        <option value="">All Leave Types</option>
                        <?php while ($leaveType = $leaveTypes->fetch_assoc()): ?>
                            <option value="<?php echo $leaveType['LeaveTypeID']; ?>" <?php echo $leaveTypeFilter == $leaveType['LeaveTypeID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($leaveType['LeaveName']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group" style="display: flex; align-items: flex-end;">
                    <button type="submit" class="btn btn-filter">Apply Filters</button>
                    <a href="leave_requests.php" class="btn btn-reset">Reset</a>
                </div>
            </form>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Employee</th>
                        <th>Leave Type</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Days</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Applied On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['RequestID']); ?></td>
                            <td><?php echo htmlspecialchars($row['Fullnames']); ?></td>
                            <td><?php echo htmlspecialchars($row['LeaveName']); ?></td>
                            <td><?php echo htmlspecialchars($row['StartDate']); ?></td>
                            <td><?php echo htmlspecialchars($row['EndDate']); ?></td>
                            <td><?php echo htmlspecialchars($row['Days']); ?></td>
                            <td><?php echo htmlspecialchars(substr($row['Reason'], 0, 30)) . (strlen($row['Reason']) > 30 ? '...' : ''); ?></td>
                            <td>
                                <?php if ($columnExists && $row['Status'] === 'Rejected' && !empty($row['RejectionReason'])): ?>
                                <div class="tooltip">
                                    <span class="status <?php echo strtolower($row['Status']); ?>">
                                        <?php echo htmlspecialchars($row['Status']); ?>
                                    </span>
                                    <span class="tooltiptext"><?php echo htmlspecialchars($row['RejectionReason']); ?></span>
                                </div>
                                <?php else: ?>
                                <span class="status <?php echo strtolower($row['Status'] ?: 'pending'); ?>">
                                    <?php echo htmlspecialchars($row['Status'] ?: 'Pending'); ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($row['AppliedOn'])); ?></td>
                            <td class="action-buttons">
                                <a href="view_request.php?id=<?php echo $row['RequestID']; ?>" class="btn btn-view">View</a>
                                <?php if ($row['Status'] === 'Pending'): ?>
                                    <button class="btn btn-approve" onclick="confirmAction(<?php echo $row['RequestID']; ?>, 'approve')">Approve</button>
                                    <button class="btn btn-reject" onclick="confirmAction(<?php echo $row['RequestID']; ?>, 'reject')">Reject</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="background-color: #fff; padding: 20px; border-radius: 5px; text-align: center;">
                <p>No leave requests found matching your criteria.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Confirmation Dialog for Approve -->
    <div class="action-confirm" id="approveConfirmDialog">
        <div class="confirm-dialog">
            <h3>Confirm Approval</h3>
            <p>Are you sure you want to approve this leave request?</p>
            <p>This action will update the employee's leave balance.</p>
            <div class="confirm-buttons">
                <button class="btn" style="background-color: #95a5a6;" onclick="document.getElementById('approveConfirmDialog').style.display='none'">Cancel</button>
                <a href="#" id="approveConfirmButton" class="btn btn-approve">Approve</a>
            </div>
        </div>
    </div>

    <!-- Confirmation Dialog for Reject -->
    <div class="action-confirm" id="rejectConfirmDialog">
        <div class="confirm-dialog">
            <h3>Confirm Rejection</h3>
            <p>Do you want to provide a rejection reason?</p>
            <div class="confirm-buttons">
                <button class="btn" style="background-color: #95a5a6;" onclick="document.getElementById('rejectConfirmDialog').style.display='none'">Cancel</button>
                <a href="#" id="rejectConfirmButton" class="btn btn-reject">Reject Without Reason</a>
                <a href="#" id="rejectWithReasonButton" class="btn" style="background-color: #e67e22;">Add Reason</a>
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
</body>
</html>

<?php $conn->close(); ?>
