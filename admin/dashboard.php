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

// Get total number of employees
$employeeQuery = "SELECT COUNT(*) as total FROM users WHERE admin = 0 AND hr = 0";
$employeeResult = mysqli_query($conn, $employeeQuery);
$employeeCount = mysqli_fetch_assoc($employeeResult)['total'];

// Get pending leave requests
$pendingQuery = "SELECT COUNT(*) as pending FROM leave_requests WHERE Status = 'Pending'";
$pendingResult = mysqli_query($conn, $pendingQuery);
$pendingCount = mysqli_fetch_assoc($pendingResult)['pending'];

// Get approved leave requests this month
$approvedQuery = "SELECT COUNT(*) as approved FROM leave_requests 
                 WHERE Status = 'Approved' 
                 AND MONTH(StartDate) = MONTH(CURRENT_DATE())
                 AND YEAR(StartDate) = YEAR(CURRENT_DATE())";
$approvedResult = mysqli_query($conn, $approvedQuery);
$approvedCount = mysqli_fetch_assoc($approvedResult)['approved'];

// Get recent leave requests
$recentQuery = "SELECT lr.*, u.Fullnames, lt.LeaveName
               FROM leave_requests lr
               JOIN users u ON lr.UserID = u.UserID
               JOIN leave_types lt ON lr.LeaveTypeID = lt.LeaveTypeID
               ORDER BY lr.AppliedOn DESC
               LIMIT 5";
$recentResult = mysqli_query($conn, $recentQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Leave Management System</title>
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
                            <a class="nav-link active" href="dashboard.php">
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
                            <a class="nav-link" href="email_settings.php">
                                <i class="fas fa-envelope"></i> Email Settings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="leave_types.php">
                                <i class="fas fa-tasks"></i> Leave Types
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
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group mr-2">
                            <a href="reports.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-download"></i> Generate Report
                            </a>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle">
                            <i class="fas fa-calendar"></i> This Month
                        </button>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="card text-white bg-primary mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Employees</h6>
                                        <h2 class="mb-0"><?php echo $employeeCount; ?></h2>
                                    </div>
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a class="small text-white stretched-link" href="manage_employees.php">View Details</a>
                                <i class="fas fa-angle-right"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-warning mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Pending Requests</h6>
                                        <h2 class="mb-0"><?php echo $pendingCount; ?></h2>
                                    </div>
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a class="small text-white stretched-link" href="leave_requests.php">View Details</a>
                                <i class="fas fa-angle-right"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-success mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Approved This Month</h6>
                                        <h2 class="mb-0"><?php echo $approvedCount; ?></h2>
                                    </div>
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                            </div>
                            <div class="card-footer d-flex align-items-center justify-content-between">
                                <a class="small text-white stretched-link" href="reports.php">View Details</a>
                                <i class="fas fa-angle-right"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Leave Requests -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-table mr-1"></i>
                        Recent Leave Requests
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                        <th>Applied On</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($recentResult)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['Fullnames']); ?></td>
                                        <td><?php echo htmlspecialchars($row['LeaveName']); ?></td>
                                        <td><?php echo date('d M Y', strtotime($row['StartDate'])); ?></td>
                                        <td><?php echo date('d M Y', strtotime($row['EndDate'])); ?></td>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo $row['Status'] == 'Approved' ? 'success' : 
                                                    ($row['Status'] == 'Pending' ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo $row['Status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d M Y H:i', strtotime($row['AppliedOn'])); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>