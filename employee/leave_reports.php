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
$username = isset($_SESSION['Username']) ? $_SESSION['Username'] : '';

// Get count of pending substitute requests for the badge
$pendingSubsQuery = "SELECT COUNT(*) as count FROM leave_substitutes 
                    WHERE SubstituteID = ? AND Status = 'Pending'";
$pendingStmt = $conn->prepare($pendingSubsQuery);
$pendingStmt->bind_param("i", $userID);
$pendingStmt->execute();
$pendingResult = $pendingStmt->get_result();
$pendingSubstitutes = $pendingResult->fetch_assoc()['count'];

// Get year filter (default to current year)
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Get all years that have leave requests for dropdown
$yearsQuery = "SELECT DISTINCT YEAR(StartDate) as year 
               FROM leave_requests 
               WHERE UserID = $userID 
               ORDER BY year DESC";
$yearsResult = mysqli_query($conn, $yearsQuery);

// Get leave statistics for the selected year
$statsQuery = "SELECT 
    lt.LeaveTypeID,
    lt.LeaveName,
    COUNT(lr.RequestID) as total_requests,
    SUM(CASE WHEN lr.Status = 'Approved' THEN 1 ELSE 0 END) as approved_requests,
    SUM(CASE WHEN lr.Status = 'Rejected' THEN 1 ELSE 0 END) as rejected_requests,
    SUM(CASE WHEN lr.Status = 'Pending' THEN 1 ELSE 0 END) as pending_requests,
    SUM(CASE 
        WHEN lr.Status = 'Approved' THEN DATEDIFF(lr.EndDate, lr.StartDate) + 1 
        ELSE 0 
    END) as total_days_taken
FROM leave_types lt
LEFT JOIN leave_requests lr ON lt.LeaveTypeID = lr.LeaveTypeID 
    AND lr.UserID = $userID 
    AND YEAR(lr.StartDate) = $year
GROUP BY lt.LeaveTypeID, lt.LeaveName
ORDER BY lt.LeaveName";

$statsResult = mysqli_query($conn, $statsQuery);

// Get monthly distribution of leaves
$monthlyQuery = "SELECT 
    MONTH(StartDate) as month,
    COUNT(*) as request_count,
    SUM(CASE WHEN Status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
    SUM(DATEDIFF(EndDate, StartDate) + 1) as days_requested
FROM leave_requests 
WHERE UserID = $userID 
AND YEAR(StartDate) = $year
GROUP BY MONTH(StartDate)
ORDER BY month";

$monthlyResult = mysqli_query($conn, $monthlyQuery);
$monthlyData = array_fill(1, 12, ['request_count' => 0, 'approved_count' => 0, 'days_requested' => 0]);

while ($row = mysqli_fetch_assoc($monthlyResult)) {
    $monthlyData[$row['month']] = $row;
}

// Get recent leave requests with outcomes
$recentQuery = "SELECT lr.*, lt.LeaveName,
                DATEDIFF(lr.EndDate, lr.StartDate) + 1 as days,
                CASE 
                    WHEN lr.Status = 'Approved' THEN 'success'
                    WHEN lr.Status = 'Rejected' THEN 'danger'
                    ELSE 'warning'
                END as status_class
                FROM leave_requests lr
                JOIN leave_types lt ON lr.LeaveTypeID = lt.LeaveTypeID
                WHERE lr.UserID = $userID
                AND YEAR(lr.StartDate) = $year
                ORDER BY lr.AppliedOn DESC
                LIMIT 10";

$recentResult = mysqli_query($conn, $recentQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Reports - Leave Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/employee_dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <h1 class="h2">Leave Reports</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <form class="form-inline">
                            <label class="mr-2">Year:</label>
                            <select class="form-control" name="year" onchange="this.form.submit()">
                                <?php 
                                if (mysqli_num_rows($yearsResult) > 0) {
                                    while ($yearRow = mysqli_fetch_assoc($yearsResult)) {
                                        $selected = $yearRow['year'] == $year ? 'selected' : '';
                                        echo "<option value='{$yearRow['year']}' {$selected}>{$yearRow['year']}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </form>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <?php
                    $totalRequests = 0;
                    $totalApproved = 0;
                    $totalRejected = 0;
                    $totalPending = 0;
                    $totalDays = 0;

                    if (mysqli_num_rows($statsResult) > 0) {
                        mysqli_data_seek($statsResult, 0);
                        while ($row = mysqli_fetch_assoc($statsResult)) {
                            $totalRequests += $row['total_requests'];
                            $totalApproved += $row['approved_requests'];
                            $totalRejected += $row['rejected_requests'];
                            $totalPending += $row['pending_requests'];
                            $totalDays += $row['total_days_taken'];
                        }
                        mysqli_data_seek($statsResult, 0);
                    }
                    ?>
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h5 class="card-title">Total Requests</h5>
                                <h2 class="card-text"><?php echo $totalRequests; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h5 class="card-title">Approved</h5>
                                <h2 class="card-text"><?php echo $totalApproved; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-danger">
                            <div class="card-body">
                                <h5 class="card-title">Rejected</h5>
                                <h2 class="card-text"><?php echo $totalRejected; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <h5 class="card-title">Days Taken</h5>
                                <h2 class="card-text"><?php echo $totalDays; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Monthly Leave Distribution</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="monthlyChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Leave Type Distribution</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="leaveTypeChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Statistics -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Leave Statistics by Type</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Leave Type</th>
                                        <th>Total Requests</th>
                                        <th>Approved</th>
                                        <th>Rejected</th>
                                        <th>Pending</th>
                                        <th>Days Taken</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($statsResult)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['LeaveName']); ?></td>
                                        <td><?php echo $row['total_requests']; ?></td>
                                        <td><?php echo $row['approved_requests']; ?></td>
                                        <td><?php echo $row['rejected_requests']; ?></td>
                                        <td><?php echo $row['pending_requests']; ?></td>
                                        <td><?php echo $row['total_days_taken']; ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Leave Requests -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Recent Leave Requests</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Leave Type</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Days</th>
                                        <th>Status</th>
                                        <th>Applied On</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($recentResult)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['LeaveName']); ?></td>
                                        <td><?php echo date('d-M-Y', strtotime($row['StartDate'])); ?></td>
                                        <td><?php echo date('d-M-Y', strtotime($row['EndDate'])); ?></td>
                                        <td><?php echo $row['days']; ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $row['status_class']; ?>">
                                                <?php echo $row['Status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d-M-Y', strtotime($row['AppliedOn'])); ?></td>
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

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
        // Monthly Distribution Chart
        const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const monthlyData = <?php echo json_encode(array_values($monthlyData)); ?>;
        
        new Chart(document.getElementById('monthlyChart'), {
            type: 'bar',
            data: {
                labels: monthNames,
                datasets: [{
                    label: 'Leave Requests',
                    data: monthlyData.map(m => m.request_count || 0),
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }, {
                    label: 'Days Requested',
                    data: monthlyData.map(m => m.days_requested || 0),
                    backgroundColor: 'rgba(255, 99, 132, 0.5)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Leave Type Distribution Chart
        const statsData = <?php 
            mysqli_data_seek($statsResult, 0);
            $chartData = [];
            while ($row = mysqli_fetch_assoc($statsResult)) {
                $chartData[] = [
                    'name' => $row['LeaveName'],
                    'total' => $row['total_requests']
                ];
            }
            echo json_encode($chartData);
        ?>;

        new Chart(document.getElementById('leaveTypeChart'), {
            type: 'pie',
            data: {
                labels: statsData.map(d => d.name),
                datasets: [{
                    data: statsData.map(d => d.total),
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.5)',
                        'rgba(54, 162, 235, 0.5)',
                        'rgba(255, 206, 86, 0.5)',
                        'rgba(75, 192, 192, 0.5)',
                        'rgba(153, 102, 255, 0.5)',
                        'rgba(255, 159, 64, 0.5)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    </script>
</body>
</html>