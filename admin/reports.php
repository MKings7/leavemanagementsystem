<?php
session_start();
include "../includes/dbconnect.php";
include "../includes/functions.php";

// Check if user is logged in and is an admin
if (!isset($_SESSION['UserID']) || $_SESSION['admin'] != 1) {
    header("Location: ../login.php");
    exit();
}

$userID = $_SESSION['UserID'];
$username = $_SESSION['Username'];
$message = "";
$error = "";

// Get filter parameters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'); // Last day of current month
$reportType = isset($_GET['report_type']) ? $_GET['report_type'] : 'summary';
$departmentFilter = isset($_GET['department']) ? $_GET['department'] : 'all';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Get departments for filter
$deptQuery = "SELECT DepartmentID, DepartmentName FROM departments ORDER BY DepartmentName";
$departments = mysqli_query($conn, $deptQuery);

// Reports queries
if ($reportType == 'summary') {
    // Summary Report - Count of leaves by type and status
    $query = "SELECT lt.LeaveName, lr.Status, COUNT(*) AS Count, SUM(DATEDIFF(lr.EndDate, lr.StartDate) + 1) AS TotalDays
              FROM leave_requests lr
              JOIN leave_types lt ON lr.LeaveTypeID = lt.LeaveTypeID
              WHERE lr.StartDate BETWEEN ? AND ?";
    
    if ($statusFilter != 'all') {
        $query .= " AND lr.Status = ?";
    }
    
    $query .= " GROUP BY lt.LeaveName, lr.Status
                ORDER BY lt.LeaveName, lr.Status";
    
    $stmt = mysqli_prepare($conn, $query);
    
    if ($statusFilter != 'all') {
        mysqli_stmt_bind_param($stmt, "sss", $startDate, $endDate, $statusFilter);
    } else {
        mysqli_stmt_bind_param($stmt, "ss", $startDate, $endDate);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    // Also get total counts
    $totalQuery = "SELECT COUNT(*) AS TotalRequests, 
                  SUM(CASE WHEN Status = 'Approved' THEN 1 ELSE 0 END) AS Approved,
                  SUM(CASE WHEN Status = 'Rejected' THEN 1 ELSE 0 END) AS Rejected,
                  SUM(CASE WHEN Status = 'Pending' THEN 1 ELSE 0 END) AS Pending,
                  SUM(DATEDIFF(EndDate, StartDate) + 1) AS TotalDays
                  FROM leave_requests
                  WHERE StartDate BETWEEN ? AND ?";
    
    if ($statusFilter != 'all') {
        $totalQuery .= " AND Status = ?";
    }
    
    $totalStmt = mysqli_prepare($conn, $totalQuery);
    
    if ($statusFilter != 'all') {
        mysqli_stmt_bind_param($totalStmt, "sss", $startDate, $endDate, $statusFilter);
    } else {
        mysqli_stmt_bind_param($totalStmt, "ss", $startDate, $endDate);
    }
    
    mysqli_stmt_execute($totalStmt);
    $totalResult = mysqli_stmt_get_result($totalStmt);
    $totalData = mysqli_fetch_assoc($totalResult);
} 
elseif ($reportType == 'detailed') {
    // Detailed Report - All leave requests with details
    $query = "SELECT lr.*, u.Fullnames, u.EmailAddress, lt.LeaveName, DATEDIFF(lr.EndDate, lr.StartDate) + 1 AS Days
              FROM leave_requests lr
              JOIN users u ON lr.UserID = u.UserID
              JOIN leave_types lt ON lr.LeaveTypeID = lt.LeaveTypeID
              WHERE lr.StartDate BETWEEN ? AND ?";
    
    if ($statusFilter != 'all') {
        $query .= " AND lr.Status = ?";
    }
    
    $query .= " ORDER BY lr.AppliedOn DESC";
    
    $stmt = mysqli_prepare($conn, $query);
    
    if ($statusFilter != 'all') {
        mysqli_stmt_bind_param($stmt, "sss", $startDate, $endDate, $statusFilter);
    } else {
        mysqli_stmt_bind_param($stmt, "ss", $startDate, $endDate);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
}
elseif ($reportType == 'employee') {
    // Employee Report - Group by employee
    $query = "SELECT u.UserID, u.Fullnames, COUNT(lr.RequestID) AS RequestCount, 
              SUM(CASE WHEN lr.Status = 'Approved' THEN 1 ELSE 0 END) AS ApprovedCount,
              SUM(CASE WHEN lr.Status = 'Rejected' THEN 1 ELSE 0 END) AS RejectedCount,
              SUM(CASE WHEN lr.Status = 'Pending' THEN 1 ELSE 0 END) AS PendingCount,
              SUM(DATEDIFF(lr.EndDate, lr.StartDate) + 1) AS TotalDays
              FROM users u
              LEFT JOIN leave_requests lr ON u.UserID = lr.UserID AND lr.StartDate BETWEEN ? AND ?";
    
    if ($statusFilter != 'all') {
        $query .= " AND lr.Status = ?";
    }
    
    $query .= " WHERE u.admin = 0 AND u.hr = 0
                GROUP BY u.UserID, u.Fullnames
                ORDER BY RequestCount DESC";
    
    $stmt = mysqli_prepare($conn, $query);
    
    if ($statusFilter != 'all') {
        mysqli_stmt_bind_param($stmt, "sss", $startDate, $endDate, $statusFilter);
    } else {
        mysqli_stmt_bind_param($stmt, "ss", $startDate, $endDate);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Leave Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/admin_dashboard.css">
</head>
<body>
<div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-none d-md-block bg-dark sidebar">
                <div class="sidebar-sticky">
                    <div class="sidebar-header">
                        <h3>Admin Portal</h3>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="leave_requests.php">
                                <i class="fas fa-calendar-check"></i> Leave Requests
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_employees.php">
                                <i class="fas fa-users"></i> Manage Employees
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_departments.php">
                                <i class="fas fa-building"></i> Manage Departments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="email_settings.php">
                                <i class="fas fa-envelope"></i> Email Settings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="leave_types.php">
                                <i class="fas fa-tasks"></i> Leave Types
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="scheduled_tasks.php">
                                <i class="fas fa-clock"></i> Scheduled Tasks
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar"></i> Reports
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
                    <h1 class="h2">Reports</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                            <i class="fas fa-print"></i> Print Report
                        </button>
                    </div>
                </div>

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

                <!-- Report Filters -->
                <div class="card mb-4 print-hide">
                    <div class="card-body">
                        <form method="get" class="form-row align-items-end">
                            <div class="col-md-2 mb-2">
                                <label for="report_type">Report Type</label>
                                <select class="form-control" id="report_type" name="report_type">
                                    <option value="summary" <?php echo $reportType == 'summary' ? 'selected' : ''; ?>>Summary Report</option>
                                    <option value="detailed" <?php echo $reportType == 'detailed' ? 'selected' : ''; ?>>Detailed Report</option>
                                    <option value="employee" <?php echo $reportType == 'employee' ? 'selected' : ''; ?>>Employee Report</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <label for="start_date">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label for="end_date">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label for="status">Status</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="all" <?php echo $statusFilter == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                    <option value="Pending" <?php echo $statusFilter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Approved" <?php echo $statusFilter == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="Rejected" <?php echo $statusFilter == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-filter"></i> Generate Report
                                </button>
                            </div>
                            <div class="col-md-2 mb-2">
                                <a href="reports.php" class="btn btn-secondary btn-block">
                                    <i class="fas fa-sync"></i> Reset Filters
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Report Content -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <?php 
                                if ($reportType == 'summary') echo 'Leave Summary Report';
                                elseif ($reportType == 'detailed') echo 'Detailed Leave Report';
                                else echo 'Employee Leave Report';
                            ?>
                        </h5>
                        <small>Period: <?php echo date('d M Y', strtotime($startDate)); ?> to <?php echo date('d M Y', strtotime($endDate)); ?></small>
                    </div>
                    <div class="card-body">
                        <?php if ($reportType == 'summary'): ?>
                            <!-- Summary Report -->
                            <?php if (isset($totalData)): ?>
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <h5 class="card-title">Total Requests</h5>
                                            <h2><?php echo $totalData['TotalRequests']; ?></h2>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-success text-white">
                                        <div class="card-body text-center">
                                            <h5 class="card-title">Approved</h5>
                                            <h2><?php echo $totalData['Approved']; ?></h2>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-danger text-white">
                                        <div class="card-body text-center">
                                            <h5 class="card-title">Rejected</h5>
                                            <h2><?php echo $totalData['Rejected']; ?></h2>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-warning text-white">
                                        <div class="card-body text-center">
                                            <h5 class="card-title">Pending</h5>
                                            <h2><?php echo $totalData['Pending']; ?></h2>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Leave Type</th>
                                            <th>Status</th>
                                            <th>Count</th>
                                            <th>Total Days</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (mysqli_num_rows($result) > 0): ?>
                                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['LeaveName']); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php 
                                                        echo $row['Status'] == 'Approved' ? 'success' : 
                                                            ($row['Status'] == 'Rejected' ? 'danger' : 'warning'); 
                                                    ?>">
                                                        <?php echo $row['Status']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $row['Count']; ?></td>
                                                <td><?php echo $row['TotalDays']; ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No leave requests found for the selected period</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php elseif ($reportType == 'detailed'): ?>
                            <!-- Detailed Report -->
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Employee</th>
                                            <th>Leave Type</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>Days</th>
                                            <th>Reason</th>
                                            <th>Status</th>
                                            <th>Applied On</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (mysqli_num_rows($result) > 0): ?>
                                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                            <tr>
                                                <td><?php echo $row['RequestID']; ?></td>
                                                <td><?php echo htmlspecialchars($row['Fullnames']); ?></td>
                                                <td><?php echo htmlspecialchars($row['LeaveName']); ?></td>
                                                <td><?php echo date('d-M-Y', strtotime($row['StartDate'])); ?></td>
                                                <td><?php echo date('d-M-Y', strtotime($row['EndDate'])); ?></td>
                                                <td><?php echo $row['Days']; ?></td>
                                                <td><?php echo htmlspecialchars($row['Reason']); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php 
                                                        echo $row['Status'] == 'Approved' ? 'success' : 
                                                            ($row['Status'] == 'Rejected' ? 'danger' : 'warning'); 
                                                    ?>">
                                                        <?php echo $row['Status']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d-M-Y', strtotime($row['AppliedOn'])); ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="9" class="text-center">No leave requests found for the selected period</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <!-- Employee Report -->
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Employee ID</th>
                                            <th>Employee Name</th>
                                            <th>Total Requests</th>
                                            <th>Approved</th>
                                            <th>Rejected</th>
                                            <th>Pending</th>
                                            <th>Total Days</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (mysqli_num_rows($result) > 0): ?>
                                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                            <tr>
                                                <td><?php echo $row['UserID']; ?></td>
                                                <td><?php echo htmlspecialchars($row['Fullnames']); ?></td>
                                                <td><?php echo $row['RequestCount']; ?></td>
                                                <td><?php echo $row['ApprovedCount']; ?></td>
                                                <td><?php echo $row['RejectedCount']; ?></td>
                                                <td><?php echo $row['PendingCount']; ?></td>
                                                <td><?php echo $row['TotalDays'] ?? 0; ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center">No data found for the selected period</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-muted">
                        <div class="row">
                            <div class="col-md-6">
                                Generated on: <?php echo date('d M Y H:i:s'); ?>
                            </div>
                            <div class="col-md-6 text-right">
                                Generated by: <?php echo htmlspecialchars($username); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <style>
        @media print {
            .sidebar, .print-hide, .btn, .navbar {
                display: none !important;
            }
            main {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .card {
                border: none !important;
            }
            .card-header, .card-footer {
                background-color: white !important;
            }
        }
    </style>
</body>
</html>