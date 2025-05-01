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

// Set default report parameters
$reportType = isset($_GET['report_type']) ? $_GET['report_type'] : 'leave_summary';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'); // Last day of current month
$employeeFilter = isset($_GET['employee']) ? $_GET['employee'] : '';
$leaveTypeFilter = isset($_GET['leave_type']) ? $_GET['leave_type'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';

// Get employees for filter dropdown
$employees = $conn->query("SELECT UserID, Fullnames FROM users WHERE hr = 0 ORDER BY Fullnames ASC");

// Get leave types for filter dropdown
$leaveTypes = $conn->query("SELECT LeaveTypeID, LeaveName FROM leave_types ORDER BY LeaveName ASC");

// Generate report based on selected type
$reportData = [];
$reportTitle = '';

switch ($reportType) {
    case 'leave_summary':
        $reportTitle = 'Leave Summary Report';
        
        // Build query with filters
        $query = "SELECT lr.RequestID, u.UserID, u.Fullnames, lt.LeaveTypeID, lt.LeaveName, 
                 lr.StartDate, lr.EndDate, lr.Status, lr.AppliedOn, lr.ProcessedDate,
                 DATEDIFF(lr.EndDate, lr.StartDate) + 1 AS Days
                 FROM leave_requests lr
                 JOIN users u ON lr.UserID = u.UserID
                 JOIN leave_types lt ON lr.LeaveTypeID = lt.LeaveTypeID
                 WHERE (lr.StartDate BETWEEN ? AND ? OR lr.EndDate BETWEEN ? AND ?)";
        
        $params = [$startDate, $endDate, $startDate, $endDate];
        $types = "ssss";
        
        if (!empty($employeeFilter)) {
            $query .= " AND u.UserID = ?";
            $params[] = $employeeFilter;
            $types .= "i";
        }
        
        if (!empty($leaveTypeFilter)) {
            $query .= " AND lt.LeaveTypeID = ?";
            $params[] = $leaveTypeFilter;
            $types .= "i";
        }
        
        if (!empty($statusFilter)) {
            $query .= " AND lr.Status = ?";
            $params[] = $statusFilter;
            $types .= "s";
        }
        
        $query .= " ORDER BY lr.AppliedOn DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $reportData[] = $row;
        }
        break;
        
    case 'leave_balance':
        $reportTitle = 'Employee Leave Balance Report';
        
        // Get all employees or filtered employee
        $employeeQuery = "SELECT UserID, Fullnames FROM users WHERE hr = 0";
        if (!empty($employeeFilter)) {
            $employeeQuery .= " AND UserID = $employeeFilter";
        }
        $employeeQuery .= " ORDER BY Fullnames";
        
        $employeesResult = $conn->query($employeeQuery);
        
        // Get all leave types or filtered leave type
        $leaveTypeQuery = "SELECT LeaveTypeID, LeaveName, MaxDays FROM leave_types";
        if (!empty($leaveTypeFilter)) {
            $leaveTypeQuery .= " AND LeaveTypeID = $leaveTypeFilter";
        }
        $leaveTypeQuery .= " ORDER BY LeaveName";
        
        $leaveTypesResult = $conn->query($leaveTypeQuery);
        
        // Build report data
        $balanceData = [];
        
        while ($emp = $employeesResult->fetch_assoc()) {
            $employeeData = [
                'UserID' => $emp['UserID'],
                'Fullnames' => $emp['Fullnames'],
                'LeaveBalance' => []
            ];
            
            $leaveTypesResult->data_seek(0); // Reset pointer
            while ($lt = $leaveTypesResult->fetch_assoc()) {
                $used = getUsedLeaveDays($emp['UserID'], $lt['LeaveTypeID'], $conn);
                $remaining = max(0, $lt['MaxDays'] - $used);
                
                $employeeData['LeaveBalance'][] = [
                    'LeaveTypeID' => $lt['LeaveTypeID'],
                    'LeaveName' => $lt['LeaveName'],
                    'MaxDays' => $lt['MaxDays'],
                    'UsedDays' => $used,
                    'RemainingDays' => $remaining
                ];
            }
            
            $balanceData[] = $employeeData;
        }
        
        $reportData = $balanceData;
        break;
        
    case 'monthly_stats':
        $reportTitle = 'Monthly Leave Statistics';
        
        // Generate monthly report for the selected date range
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        
        // Add a day to include the end date
        $end->modify('+1 day');
        
        $interval = DateInterval::createFromDateString('1 month');
        $period = new DatePeriod($start->modify('first day of this month'), $interval, $end);
        
        $monthlyStats = [];
        
        foreach ($period as $dt) {
            $month = $dt->format('Y-m');
            $monthStart = $dt->format('Y-m-01');
            $monthEnd = $dt->format('Y-m-t');
            
            $query = "SELECT 
                    COUNT(*) as TotalRequests,
                    SUM(CASE WHEN Status = 'Approved' THEN 1 ELSE 0 END) as Approved,
                    SUM(CASE WHEN Status = 'Rejected' THEN 1 ELSE 0 END) as Rejected,
                    SUM(CASE WHEN Status = 'Pending' THEN 1 ELSE 0 END) as Pending,
                    SUM(CASE WHEN Status = 'Approved' THEN DATEDIFF(EndDate, StartDate) + 1 ELSE 0 END) as TotalDays
                FROM leave_requests 
                WHERE (StartDate BETWEEN ? AND ? OR EndDate BETWEEN ? AND ?)";
            
            $params = [$monthStart, $monthEnd, $monthStart, $monthEnd];
            $types = "ssss";
            
            if (!empty($employeeFilter)) {
                $query .= " AND UserID = ?";
                $params[] = $employeeFilter;
                $types .= "i";
            }
            
            if (!empty($leaveTypeFilter)) {
                $query .= " AND LeaveTypeID = ?";
                $params[] = $leaveTypeFilter;
                $types .= "i";
            }
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $stats = $result->fetch_assoc();
            
            $monthlyStats[] = [
                'Month' => $dt->format('F Y'),
                'TotalRequests' => $stats['TotalRequests'],
                'Approved' => $stats['Approved'] ?: 0,
                'Rejected' => $stats['Rejected'] ?: 0,
                'Pending' => $stats['Pending'] ?: 0,
                'TotalDays' => $stats['TotalDays'] ?: 0
            ];
        }
        
        $reportData = $monthlyStats;
        break;
        
    case 'department_stats':
        $reportTitle = 'Department Leave Statistics';
        
        // Since there is no department field in the default structure,
        // we'll group by employee for demonstration purposes
        $query = "SELECT 
                u.UserID, u.Fullnames,
                COUNT(lr.RequestID) as TotalRequests,
                SUM(CASE WHEN lr.Status = 'Approved' THEN 1 ELSE 0 END) as Approved,
                SUM(CASE WHEN lr.Status = 'Rejected' THEN 1 ELSE 0 END) as Rejected,
                SUM(CASE WHEN lr.Status = 'Pending' THEN 1 ELSE 0 END) as Pending,
                SUM(CASE WHEN lr.Status = 'Approved' THEN DATEDIFF(lr.EndDate, lr.StartDate) + 1 ELSE 0 END) as TotalDays
            FROM users u
            LEFT JOIN leave_requests lr ON u.UserID = lr.UserID 
                AND (lr.StartDate BETWEEN ? AND ? OR lr.EndDate BETWEEN ? AND ?)
            WHERE u.hr = 0
            GROUP BY u.UserID, u.Fullnames
            ORDER BY u.Fullnames";
        
        $params = [$startDate, $endDate, $startDate, $endDate];
        $types = "ssss";
        
        if (!empty($employeeFilter)) {
            $query = str_replace("WHERE u.hr = 0", "WHERE u.hr = 0 AND u.UserID = ?", $query);
            $params[] = $employeeFilter;
            $types .= "i";
        }
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $reportData[] = $row;
        }
        break;
}

// Calculate summary statistics for certain report types
$summaryStats = [];
if ($reportType == 'leave_summary') {
    $totalRequests = count($reportData);
    $approvedRequests = 0;
    $rejectedRequests = 0;
    $pendingRequests = 0;
    $totalDays = 0;
    
    foreach ($reportData as $row) {
        if ($row['Status'] == 'Approved') {
            $approvedRequests++;
            $totalDays += $row['Days'];
        } elseif ($row['Status'] == 'Rejected') {
            $rejectedRequests++;
        } elseif ($row['Status'] == 'Pending' || empty($row['Status'])) {
            $pendingRequests++;
        }
    }
    
    $summaryStats = [
        'TotalRequests' => $totalRequests,
        'ApprovedRequests' => $approvedRequests,
        'RejectedRequests' => $rejectedRequests,
        'PendingRequests' => $pendingRequests,
        'TotalDays' => $totalDays
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - HR Portal</title>
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

        .reports-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .report-types {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
            background-color: #fff;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .report-type {
            display: inline-block;
            padding: 10px 15px;
            background-color: #fff;
            color: #2f3b52;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
        }

        .report-type:hover, .report-type.active {
            background-color: #3498db;
            color: #fff;
            border-color: #3498db;
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #2f3b52;
            font-size: 14px;
        }

        .filter-group select, .filter-group input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .filter-buttons {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .report-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }

        .summary-card {
            flex: 1;
            min-width: 200px;
            padding: 15px;
            border-radius: 5px;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .summary-card h3 {
            font-size: 16px;
            margin-bottom: 5px;
            color: #7f8c8d;
        }

        .summary-card .value {
            font-size: 24px;
            font-weight: bold;
            color: #2f3b52;
        }

        .report-table-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .report-table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .report-table-header h2 {
            font-size: 18px;
            color: #2f3b52;
        }

        .export-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-export {
            background-color: #27ae60;
        }

        .btn-export:hover {
            background-color: #219653;
        }

        .btn-print {
            background-color: #9b59b6;
        }

        .btn-print:hover {
            background-color: #8e44ad;
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
            font-weight: bold;
            color: #2f3b52;
            background-color: #f8f9fa;
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

        .status.pending, .status.Pending {
            background-color: #ffeaa7;
            color: #f39c12;
        }

        .status.approved, .status.Approved {
            background-color: #d4edda;
            color: #2ecc71;
        }

        .status.rejected, .status.Rejected {
            background-color: #f8d7da;
            color: #e74c3c;
        }

        .no-data-message {
            text-align: center;
            padding: 20px;
            color: #7f8c8d;
        }

        .employee-balance-table {
            width: 100%;
            border-collapse: collapse;
        }

        .employee-balance-table th, .employee-balance-table td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: center;
        }

        .employee-balance-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #2f3b52;
        }

        .employee-balance-table .leave-name {
            text-align: left;
            font-weight: bold;
        }

        .employee-balance-table .days-remaining {
            font-weight: bold;
            color: #2ecc71;
        }

        .employee-balance-table .days-used {
            color: #e74c3c;
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
            <li><a href="leave_requests.php"><i class="fas fa-calendar-check"></i> Leave Requests</a></li>
            <li><a href="leave_types.php"><i class="fas fa-list"></i> Leave Types</a></li>
            <li><a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a></li>
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
            <h1>Reports</h1>
        </div>
        
        <div class="reports-container">
            <div class="report-types">
                <a href="?report_type=leave_summary<?php echo !empty($startDate) ? '&start_date='.$startDate : ''; echo !empty($endDate) ? '&end_date='.$endDate : ''; echo !empty($employeeFilter) ? '&employee='.$employeeFilter : ''; echo !empty($leaveTypeFilter) ? '&leave_type='.$leaveTypeFilter : ''; echo !empty($statusFilter) ? '&status='.$statusFilter : ''; ?>" class="report-type <?php echo $reportType == 'leave_summary' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i> Leave Summary
                </a>
                <a href="?report_type=leave_balance<?php echo !empty($startDate) ? '&start_date='.$startDate : ''; echo !empty($endDate) ? '&end_date='.$endDate : ''; echo !empty($employeeFilter) ? '&employee='.$employeeFilter : ''; echo !empty($leaveTypeFilter) ? '&leave_type='.$leaveTypeFilter : ''; ?>" class="report-type <?php echo $reportType == 'leave_balance' ? 'active' : ''; ?>">
                    <i class="fas fa-balance-scale"></i> Leave Balance
                </a>
                <a href="?report_type=monthly_stats<?php echo !empty($startDate) ? '&start_date='.$startDate : ''; echo !empty($endDate) ? '&end_date='.$endDate : ''; echo !empty($employeeFilter) ? '&employee='.$employeeFilter : ''; echo !empty($leaveTypeFilter) ? '&leave_type='.$leaveTypeFilter : ''; ?>" class="report-type <?php echo $reportType == 'monthly_stats' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i> Monthly Statistics
                </a>
                <a href="?report_type=department_stats<?php echo !empty($startDate) ? '&start_date='.$startDate : ''; echo !empty($endDate) ? '&end_date='.$endDate : ''; echo !empty($employeeFilter) ? '&employee='.$employeeFilter : ''; ?>" class="report-type <?php echo $reportType == 'department_stats' ? 'active' : ''; ?>">
                    <i class="fas fa-building"></i> Employee Statistics
                </a>
            </div>
            
            <form action="" method="get" id="filters-form">
                <input type="hidden" name="report_type" value="<?php echo $reportType; ?>">
                
                <div class="filters">
                    <div class="filter-group">
                        <label for="start_date">Start Date:</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="end_date">End Date:</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="employee">Employee:</label>
                        <select id="employee" name="employee">
                            <option value="">All Employees</option>
                            <?php while ($employee = $employees->fetch_assoc()): ?>
                                <option value="<?php echo $employee['UserID']; ?>" <?php echo $employeeFilter == $employee['UserID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($employee['Fullnames']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="leave_type">Leave Type:</label>
                        <select id="leave_type" name="leave_type">
                            <option value="">All Leave Types</option>
                            <?php while ($leaveType = $leaveTypes->fetch_assoc()): ?>
                                <option value="<?php echo $leaveType['LeaveTypeID']; ?>" <?php echo $leaveTypeFilter == $leaveType['LeaveTypeID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($leaveType['LeaveName']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <?php if ($reportType == 'leave_summary'): ?>
                    <div class="filter-group">
                        <label for="status">Status:</label>
                        <select id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?php echo $statusFilter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Approved" <?php echo $statusFilter == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="Rejected" <?php echo $statusFilter == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="filter-buttons">
                        <button type="submit" class="btn">Apply Filters</button>
                        <a href="?report_type=<?php echo $reportType; ?>" class="btn" style="background-color: #95a5a6;">Reset Filters</a>
                    </div>
                </div>
            </form>
            
            <?php if ($reportType == 'leave_summary' && !empty($summaryStats)): ?>
            <div class="report-summary">
                <div class="summary-card">
                    <h3>Total Requests</h3>
                    <div class="value"><?php echo $summaryStats['TotalRequests']; ?></div>
                </div>
                <div class="summary-card">
                    <h3>Approved</h3>
                    <div class="value" style="color: #2ecc71;"><?php echo $summaryStats['ApprovedRequests']; ?></div>
                </div>
                <div class="summary-card">
                    <h3>Rejected</h3>
                    <div class="value" style="color: #e74c3c;"><?php echo $summaryStats['RejectedRequests']; ?></div>
                </div>
                <div class="summary-card">
                    <h3>Pending</h3>
                    <div class="value" style="color: #f39c12;"><?php echo $summaryStats['PendingRequests']; ?></div>
                </div>
                <div class="summary-card">
                    <h3>Total Days</h3>
                    <div class="value"><?php echo $summaryStats['TotalDays']; ?></div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="report-table-container">
                <div class="report-table-header">
                    <h2><?php echo $reportTitle; ?></h2>
                    <div class="export-buttons">
                        <a href="javascript:printReport()" class="btn btn-print"><i class="fas fa-print"></i> Print</a>
                        <a href="javascript:exportToCSV()" class="btn btn-export"><i class="fas fa-file-csv"></i> Export CSV</a>
                    </div>
                </div>
                
                <?php if ($reportType == 'leave_summary'): ?>
                    <?php if (!empty($reportData)): ?>
                    <table id="report-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Employee</th>
                                <th>Leave Type</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Days</th>
                                <th>Status</th>
                                <th>Applied On</th>
                                <th>Processed On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['RequestID']); ?></td>
                                <td><?php echo htmlspecialchars($row['Fullnames']); ?></td>
                                <td><?php echo htmlspecialchars($row['LeaveName']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['StartDate'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['EndDate'])); ?></td>
                                <td><?php echo htmlspecialchars($row['Days']); ?></td>
                                <td>
                                    <span class="status <?php echo strtolower($row['Status'] ?: 'pending'); ?>">
                                        <?php echo htmlspecialchars($row['Status'] ?: 'Pending'); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($row['AppliedOn'])); ?></td>
                                <td><?php echo $row['ProcessedDate'] ? date('M d, Y', strtotime($row['ProcessedDate'])) : 'N/A'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="no-data-message">
                        <p>No leave requests found for the selected criteria.</p>
                    </div>
                    <?php endif; ?>
                <?php elseif ($reportType == 'leave_balance'): ?>
                    <?php if (!empty($reportData)): ?>
                    <?php foreach ($reportData as $employee): ?>
                        <h3 style="margin: 20px 0 10px;"><?php echo htmlspecialchars($employee['Fullnames']); ?></h3>
                        <table class="employee-balance-table" id="report-table">
                            <thead>
                                <tr>
                                    <th>Leave Type</th>
                                    <th>Maximum Days</th>
                                    <th>Used Days</th>
                                    <th>Remaining Days</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($employee['LeaveBalance'] as $balance): ?>
                                <tr>
                                    <td class="leave-name"><?php echo htmlspecialchars($balance['LeaveName']); ?></td>
                                    <td><?php echo htmlspecialchars($balance['MaxDays']); ?></td>
                                    <td class="days-used"><?php echo htmlspecialchars($balance['UsedDays']); ?></td>
                                    <td class="days-remaining"><?php echo htmlspecialchars($balance['RemainingDays']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="no-data-message">
                        <p>No employees found for the selected criteria.</p>
                    </div>
                    <?php endif; ?>
                <?php elseif ($reportType == 'monthly_stats'): ?>
                    <?php if (!empty($reportData)): ?>
                    <table id="report-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Total Requests</th>
                                <th>Approved</th>
                                <th>Rejected</th>
                                <th>Pending</th>
                                <th>Total Days</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['Month']); ?></td>
                                <td><?php echo htmlspecialchars($row['TotalRequests']); ?></td>
                                <td><?php echo htmlspecialchars($row['Approved']); ?></td>
                                <td><?php echo htmlspecialchars($row['Rejected']); ?></td>
                                <td><?php echo htmlspecialchars($row['Pending']); ?></td>
                                <td><?php echo htmlspecialchars($row['TotalDays']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="no-data-message">
                        <p>No data found for the selected date range.</p>
                    </div>
                    <?php endif; ?>
                <?php elseif ($reportType == 'department_stats'): ?>
                    <?php if (!empty($reportData)): ?>
                    <table id="report-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Total Requests</th>
                                <th>Approved</th>
                                <th>Rejected</th>
                                <th>Pending</th>
                                <th>Total Days</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['Fullnames']); ?></td>
                                <td><?php echo htmlspecialchars($row['TotalRequests'] ?: 0); ?></td>
                                <td><?php echo htmlspecialchars($row['Approved'] ?: 0); ?></td>
                                <td><?php echo htmlspecialchars($row['Rejected'] ?: 0); ?></td>
                                <td><?php echo htmlspecialchars($row['Pending'] ?: 0); ?></td>
                                <td><?php echo htmlspecialchars($row['TotalDays'] ?: 0); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="no-data-message">
                        <p>No data found for the selected criteria.</p>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function printReport() {
            var printWindow = window.open('', '_blank');
            printWindow.document.write('<html><head><title><?php echo $reportTitle; ?></title>');
            printWindow.document.write('<style>');
            printWindow.document.write('body { font-family: Arial, sans-serif; }');
            printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }');
            printWindow.document.write('table th, table td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
            printWindow.document.write('table th { background-color: #f2f2f2; }');
            printWindow.document.write('h1 { text-align: center; margin-bottom: 20px; }');
            printWindow.document.write('</style></head><body>');
            printWindow.document.write('<h1><?php echo $reportTitle; ?></h1>');
            
            // Print report filters
            printWindow.document.write('<p><strong>Report Period:</strong> <?php echo date("M d, Y", strtotime($startDate)); ?> to <?php echo date("M d, Y", strtotime($endDate)); ?></p>');
            
            if (document.getElementById('report-table')) {
                var table = document.getElementById('report-table').cloneNode(true);
                printWindow.document.write(table.outerHTML);
            } else {
                printWindow.document.write('<p>No data available for printing.</p>');
            }
            
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.print();
        }
        
        function exportToCSV() {
            var table = document.getElementById('report-table');
            if (!table) return;
            
            var rows = table.querySelectorAll('tr');
            var csv = [];
            
            for (var i = 0; i < rows.length; i++) {
                var row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (var j = 0; j < cols.length; j++) {
                    // Get the text content and clean it
                    var text = cols[j].innerText.replace(/\r?\n|\r/g, ' ').trim();
                    // Escape double quotes with double quotes
                    text = text.replace(/"/g, '""');
                    // Add the text wrapped in quotes to handle commas and other characters
                    row.push('"' + text + '"');
                }
                
                csv.push(row.join(','));
            }
            
            // Create a CSV file and download it
            var csvContent = csv.join('\n');
            var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            
            var fileName = '<?php echo strtolower(str_replace(' ', '_', $reportTitle)); ?>_<?php echo date('Y-m-d'); ?>.csv';
            
            if (navigator.msSaveBlob) { // IE 10+
                navigator.msSaveBlob(blob, fileName);
            } else {
                var url = URL.createObjectURL(blob);
                link.href = url;
                link.setAttribute('download', fileName);
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }
    </script>
</body>
</html>
