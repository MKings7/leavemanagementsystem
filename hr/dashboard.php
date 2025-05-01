<?php
session_start();
include "../includes/dbconnect.php";

// Add the getRemainingLeaveDays function
function getRemainingLeaveDays($userID, $leaveTypeID, $conn) {
    // Get the total leave days allocated for this type
    $allocatedQuery = $conn->query("SELECT MaxDays FROM leave_types WHERE LeaveTypeID = $leaveTypeID");
    $allocated = $allocatedQuery->fetch_assoc()['MaxDays'];
    
    // Get the current year
    $currentYear = date('Y');
    
    // Calculate used days in the current year
    $usedQuery = $conn->query("SELECT COALESCE(SUM(DATEDIFF(EndDate, StartDate) + 1), 0) as used_days 
                              FROM leave_requests 
                              WHERE UserID = $userID 
                              AND LeaveTypeID = $leaveTypeID 
                              AND Status = 'Approved'
                              AND YEAR(StartDate) = $currentYear");
    $used = $usedQuery->fetch_assoc()['used_days'];
    
    // Calculate remaining days
    return $allocated - $used;
}

if (!isset($_SESSION['UserID']) || !isset($_SESSION['hr']) || $_SESSION['hr'] != 1) {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

$userID = $_SESSION['UserID'];
$username = $_SESSION['Username'];

// Get leave statistics
$pending = $conn->query("SELECT COUNT(*) as count FROM leave_requests WHERE Status='Pending'");
$pendingCount = $pending->fetch_assoc()['count'];

$approved = $conn->query("SELECT COUNT(*) as count FROM leave_requests WHERE Status='Approved'");
$approvedCount = $approved->fetch_assoc()['count'];

$rejected = $conn->query("SELECT COUNT(*) as count FROM leave_requests WHERE Status='Rejected'");
$rejectedCount = $rejected->fetch_assoc()['count'];

// Get recent leave requests (limited to 10)
$recentLeaves = $conn->query("SELECT lr.RequestID, u.Fullnames, lt.LeaveName, lr.StartDate, lr.EndDate, 
                             lr.Status, lr.AppliedOn, DATEDIFF(lr.EndDate, lr.StartDate) + 1 AS Days
                             FROM leave_requests lr
                             JOIN users u ON lr.UserID = u.UserID
                             JOIN leave_types lt ON lr.LeaveTypeID = lt.LeaveTypeID
                             ORDER BY lr.AppliedOn DESC
                             LIMIT 10");

// Get department statistics (total employees)
$totalEmployees = $conn->query("SELECT COUNT(*) as count FROM users WHERE hr = 0");
$employeeCount = $totalEmployees->fetch_assoc()['count'];

// Get leave types
$leaveTypes = $conn->query("SELECT LeaveTypeID, LeaveName FROM leave_types");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Dashboard - Leave Management System</title>
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

        .welcome h1 {
            font-size: 24px;
            color: #2f3b52;
        }

        .welcome p {
            color: #7f8c8d;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background-color: #fff;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .stat-card .icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .stat-card.pending .icon {
            color: #f39c12;
        }

        .stat-card.approved .icon {
            color: #2ecc71;
        }

        .stat-card.rejected .icon {
            color: #e74c3c;
        }

        .stat-card.employees .icon {
            color: #3498db;
        }

        .stat-card h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #2f3b52;
        }

        .stat-card .count {
            font-size: 30px;
            font-weight: bold;
            color: #2f3b52;
        }

        .panel {
            background-color: #fff;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .panel h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #2f3b52;
            padding-bottom: 10px;
            border-bottom: 1px solid #ecf0f1;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }

        table th {
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

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .action-card {
            background-color: #fff;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: all 0.3s;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .action-card a {
            display: block;
            text-decoration: none;
            color: #2f3b52;
        }

        .action-card i {
            font-size: 36px;
            margin-bottom: 10px;
            color: #3498db;
        }

        .action-card h3 {
            font-size: 16px;
            margin-bottom: 5px;
        }

        .action-card p {
            font-size: 14px;
            color: #7f8c8d;
        }

        .btn {
            display: inline-block;
            padding: 8px 15px;
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

        .btn-view {
            background-color: #3498db;
        }

        .btn-approve {
            background-color: #2ecc71;
        }

        .btn-reject {
            background-color: #e74c3c;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>HR Portal</h2>
        </div>
        
        <ul class="sidebar-menu">
            <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="manage_employees.php"><i class="fas fa-users"></i> Employees</a></li>
            <li><a href="leave_requests.php"><i class="fas fa-calendar-check"></i> Leave Requests</a></li>
            <li><a href="leave_types.php"><i class="fas fa-list"></i> Leave Types</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <?php if (isset($_SESSION['admin']) && $_SESSION['admin'] == 1): ?>
            <li><a href="../admin/scheduled_tasks.php"><i class="fas fa-clock"></i> Scheduled Tasks</a></li>
            <?php endif; ?>
        </ul>
        
        <div class="sidebar-footer">
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <div class="welcome">
                <h1>Welcome, HR Administrator</h1>
                <p><?php echo htmlspecialchars($username); ?> | HR Department</p>
            </div>
            <div class="user-info">
                <img src="../images/user-avatar.png" alt="User Avatar">
                <span><?php echo htmlspecialchars($username); ?></span>
            </div>
        </div>

        <div class="stats-container">
            <div class="stat-card pending">
                <div class="icon"><i class="fas fa-clock"></i></div>
                <h3>Pending Requests</h3>
                <div class="count"><?php echo $pendingCount; ?></div>
            </div>
            
            <div class="stat-card approved">
                <div class="icon"><i class="fas fa-check-circle"></i></div>
                <h3>Approved Requests</h3>
                <div class="count"><?php echo $approvedCount; ?></div>
            </div>
            
            <div class="stat-card rejected">
                <div class="icon"><i class="fas fa-times-circle"></i></div>
                <h3>Rejected Requests</h3>
                <div class="count"><?php echo $rejectedCount; ?></div>
            </div>
            
            <div class="stat-card employees">
                <div class="icon"><i class="fas fa-users"></i></div>
                <h3>Total Employees</h3>
                <div class="count"><?php echo $employeeCount; ?></div>
            </div>
        </div>

        <div class="quick-actions">
            <div class="action-card">
                <a href="leave_requests.php">
                    <i class="fas fa-calendar-check"></i>
                    <h3>Manage Leave Requests</h3>
                    <p>Review and process employee leave applications</p>
                </a>
            </div>
            
            <div class="action-card">
                <a href="manage_employees.php">
                    <i class="fas fa-user-plus"></i>
                    <h3>Manage Employees</h3>
                    <p>Add, edit or view employee information</p>
                </a>
            </div>
            
            <div class="action-card">
                <a href="reports.php">
                    <i class="fas fa-chart-pie"></i>
                    <h3>Generate Reports</h3>
                    <p>Create detailed leave and employee reports</p>
                </a>
            </div>
            
            <div class="action-card">
                <a href="leave_types.php">
                    <i class="fas fa-list-alt"></i>
                    <h3>Manage Leave Types</h3>
                    <p>Add or edit leave categories and policies</p>
                </a>
            </div>
        </div>

        <div class="panel">
            <h2>Recent Leave Requests</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Employee</th>
                        <th>Leave Type</th>
                        <th>Duration</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Applied On</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recentLeaves->num_rows > 0): ?>
                        <?php while ($row = $recentLeaves->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['RequestID']); ?></td>
                                <td><?php echo htmlspecialchars($row['Fullnames']); ?></td>
                                <td><?php echo htmlspecialchars($row['LeaveName']); ?></td>
                                <td><?php echo htmlspecialchars($row['StartDate']) . ' to ' . htmlspecialchars($row['EndDate']); ?></td>
                                <td><?php echo htmlspecialchars($row['Days']); ?></td>
                                <td>
                                    <span class="status <?php echo strtolower($row['Status'] ?: 'pending'); ?>">
                                        <?php echo htmlspecialchars($row['Status'] ?: 'Pending'); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($row['AppliedOn'])); ?></td>
                                <td>
                                    <a href="view_request.php?id=<?php echo $row['RequestID']; ?>" class="btn btn-view">View</a>
                                    <?php if ($row['Status'] === 'Pending'): ?>
                                        <a href="process_leave.php?id=<?php echo $row['RequestID']; ?>&action=approve" class="btn btn-approve">Approve</a>
                                        <a href="process_leave.php?id=<?php echo $row['RequestID']; ?>&action=reject" class="btn btn-reject">Reject</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center;">No leave requests found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h2>Leave Balance Overview</h2>
            <?php
            // Get all users
            $usersQuery = $conn->query("SELECT UserID, Fullnames FROM users WHERE hr = 0 ORDER BY Fullnames ASC");
            
            if ($usersQuery->num_rows > 0):
            ?>
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <?php while ($leaveType = $leaveTypes->fetch_assoc()): ?>
                            <th><?php echo htmlspecialchars($leaveType['LeaveName']); ?></th>
                        <?php endwhile; $leaveTypes->data_seek(0); // Reset pointer ?>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $usersQuery->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['Fullnames']); ?></td>
                        <?php 
                        while ($leaveType = $leaveTypes->fetch_assoc()) {
                            $remainingDays = getRemainingLeaveDays($user['UserID'], $leaveType['LeaveTypeID'], $conn);
                            echo "<td>" . $remainingDays . " days</td>";
                        }
                        $leaveTypes->data_seek(0); // Reset pointer
                        ?>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>No employees found</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php $conn->close(); ?>
