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

// Get leave statistics for current year
$year = date('Y');
$statsQuery = "SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN Status = 'Approved' THEN 1 ELSE 0 END) as approved_requests,
    SUM(CASE WHEN Status = 'Rejected' THEN 1 ELSE 0 END) as rejected_requests,
    SUM(CASE WHEN Status = 'Pending' THEN 1 ELSE 0 END) as pending_requests,
    SUM(CASE 
        WHEN Status = 'Approved' THEN DATEDIFF(EndDate, StartDate) + 1 
        ELSE 0 
    END) as total_days_taken
FROM leave_requests 
WHERE UserID = $userID AND YEAR(StartDate) = $year";

$statsResult = mysqli_query($conn, $statsQuery);
$stats = mysqli_fetch_assoc($statsResult);

// Get pending substitute requests count
$pendingSubsQuery = "SELECT COUNT(*) as pending_count 
                    FROM leave_substitutes 
                    WHERE SubstituteID = $userID AND Status = 'Pending'";
$pendingSubsResult = mysqli_query($conn, $pendingSubsQuery);
$pendingSubstitutes = mysqli_fetch_assoc($pendingSubsResult)['pending_count'];

// Get recent leave requests
$recentRequestsQuery = "SELECT lr.*, lt.LeaveName,
                              DATEDIFF(lr.EndDate, lr.StartDate) + 1 as days
                       FROM leave_requests lr
                       JOIN leave_types lt ON lr.LeaveTypeID = lt.LeaveTypeID
                       WHERE lr.UserID = $userID
                       ORDER BY lr.AppliedOn DESC
                       LIMIT 5";
$recentRequests = mysqli_query($conn, $recentRequestsQuery);

// Get notifications
$notificationsQuery = "SELECT * FROM notifications 
                      WHERE UserID = $userID 
                      ORDER BY CreatedAt DESC 
                      LIMIT 5";
$notifications = mysqli_query($conn, $notificationsQuery);

// Get active leaves (currently on leave or starting within next 7 days)
$activeQuery = "SELECT lr.*, lt.LeaveName, 
                       ls.Status as SubstituteStatus,
                       u.Fullnames as SubstituteName
                FROM leave_requests lr
                JOIN leave_types lt ON lr.LeaveTypeID = lt.LeaveTypeID
                LEFT JOIN leave_substitutes ls ON lr.RequestID = ls.RequestID
                LEFT JOIN users u ON ls.SubstituteID = u.UserID
                WHERE lr.UserID = $userID 
                AND lr.Status = 'Approved'
                AND (
                    (CURRENT_DATE BETWEEN lr.StartDate AND lr.EndDate)
                    OR (lr.StartDate BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY))
                )
                ORDER BY lr.StartDate ASC";
$activeLeaves = mysqli_query($conn, $activeQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - Leave Management System</title>
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
                    <h1 class="h2">Employee Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="user-info">
                            Welcome, <?php echo htmlspecialchars($username); ?>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Requests</h6>
                                        <h2 class="mb-0"><?php echo $stats['total_requests']; ?></h2>
                                    </div>
                                    <i class="fas fa-calendar-alt fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Approved</h6>
                                        <h2 class="mb-0"><?php echo $stats['approved_requests']; ?></h2>
                                    </div>
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Pending</h6>
                                        <h2 class="mb-0"><?php echo $stats['pending_requests']; ?></h2>
                                    </div>
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Days Taken</h6>
                                        <h2 class="mb-0"><?php echo $stats['total_days_taken']; ?></h2>
                                    </div>
                                    <i class="fas fa-calendar-day fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Active/Upcoming Leaves -->
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Active/Upcoming Leaves</h5>
                            </div>
                            <div class="card-body">
                                <?php if (mysqli_num_rows($activeLeaves) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Leave Type</th>
                                                    <th>Start Date</th>
                                                    <th>End Date</th>
                                                    <th>Substitute</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($leave = mysqli_fetch_assoc($activeLeaves)): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($leave['LeaveName']); ?></td>
                                                    <td><?php echo date('d-M-Y', strtotime($leave['StartDate'])); ?></td>
                                                    <td><?php echo date('d-M-Y', strtotime($leave['EndDate'])); ?></td>
                                                    <td>
                                                        <?php if ($leave['SubstituteName']): ?>
                                                            <?php echo htmlspecialchars($leave['SubstituteName']); ?>
                                                            <span class="badge badge-<?php echo $leave['SubstituteStatus'] == 'Accepted' ? 'success' : 'warning'; ?>">
                                                                <?php echo $leave['SubstituteStatus']; ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">None assigned</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted mb-0">No active or upcoming leaves</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Recent Leave Requests -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Recent Leave Requests</h5>
                            </div>
                            <div class="card-body">
                                <?php if (mysqli_num_rows($recentRequests) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Leave Type</th>
                                                    <th>Days</th>
                                                    <th>Applied On</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($request = mysqli_fetch_assoc($recentRequests)): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($request['LeaveName']); ?></td>
                                                    <td><?php echo $request['days']; ?></td>
                                                    <td><?php echo date('d-M-Y', strtotime($request['AppliedOn'])); ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php 
                                                            echo $request['Status'] == 'Approved' ? 'success' : 
                                                                ($request['Status'] == 'Rejected' ? 'danger' : 'warning'); 
                                                        ?>">
                                                            <?php echo $request['Status']; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted mb-0">No recent leave requests</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Notifications -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Notifications</h5>
                            </div>
                            <div class="card-body">
                                <?php if (mysqli_num_rows($notifications) > 0): ?>
                                    <div class="notifications-list">
                                        <?php while ($notif = mysqli_fetch_assoc($notifications)): ?>
                                        <div class="notification-item">
                                            <div class="notification-icon">
                                                <i class="fas <?php 
                                                    echo $notif['Type'] == 'leave_status' ? 'fa-clipboard-check' : 
                                                        ($notif['Type'] == 'substitute_response' ? 'fa-user-friends' : 'fa-bell'); 
                                                ?>"></i>
                                            </div>
                                            <div class="notification-content">
                                                <p><?php echo htmlspecialchars($notif['Message']); ?></p>
                                                <small class="text-muted">
                                                    <?php echo date('d-M-Y H:i', strtotime($notif['CreatedAt'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted mb-0">No new notifications</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Substitute Requests -->
                        <?php if ($pendingSubstitutes > 0): ?>
                        <div class="card mt-4">
                            <div class="card-header bg-warning text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-exclamation-circle"></i> Pending Substitute Requests
                                </h5>
                            </div>
                            <div class="card-body">
                                <p>You have <?php echo $pendingSubstitutes; ?> pending substitute request(s).</p>
                                <a href="substitute_requests.php" class="btn btn-warning">
                                    <i class="fas fa-user-friends"></i> View Requests
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>