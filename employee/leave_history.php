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

// Pagination settings
$recordsPerPage = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $recordsPerPage;

// Filter by status if provided
$statusFilter = isset($_GET['status']) && in_array($_GET['status'], ['All', 'Pending', 'Approved', 'Rejected']) 
               ? $_GET['status'] : 'All';

// Get total records based on filter
$countQuery = "SELECT COUNT(*) as total FROM leave_requests WHERE UserID = $userID";
if ($statusFilter != 'All') {
    $countQuery .= " AND Status = '$statusFilter'";
}
$countResult = mysqli_query($conn, $countQuery);
$totalRecords = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get leave requests with pagination and optional filter
$query = "SELECT lr.*, lt.LeaveName 
          FROM leave_requests lr 
          JOIN leave_types lt ON lr.LeaveTypeID = lt.LeaveTypeID 
          WHERE lr.UserID = $userID";

if ($statusFilter != 'All') {
    $query .= " AND lr.Status = '$statusFilter'";
}

$query .= " ORDER BY lr.AppliedOn DESC LIMIT $offset, $recordsPerPage";
$result = mysqli_query($conn, $query);

// Function to get substitute information for a leave request
function getSubstituteInfo($requestID, $conn) {
    // Check if leave_substitutes table exists
    $tableCheckQuery = "SHOW TABLES LIKE 'leave_substitutes'";
    $tableResult = mysqli_query($conn, $tableCheckQuery);
    
    if (mysqli_num_rows($tableResult) == 0) {
        return null; // Table doesn't exist
    }
    
    $query = "SELECT ls.SubstituteID, ls.Status, u.Fullnames 
              FROM leave_substitutes ls
              JOIN users u ON ls.SubstituteID = u.UserID
              WHERE ls.RequestID = $requestID";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave History - Leave Management System</title>
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
                    <h1 class="h2">Leave History</h1>
                    <div class="user-info">
                        <span>Welcome, <?php echo htmlspecialchars($username); ?></span>
                    </div>
                </div>
                
                <!-- Filter Controls -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="form-inline">
                            <div class="form-group mr-2">
                                <label for="status" class="mr-2">Filter by Status:</label>
                                <select class="form-control" id="status" name="status" onchange="this.form.submit()">
                                    <option value="All" <?php echo $statusFilter == 'All' ? 'selected' : ''; ?>>All</option>
                                    <option value="Pending" <?php echo $statusFilter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Approved" <?php echo $statusFilter == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="Rejected" <?php echo $statusFilter == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Leave History Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Leave Applications</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Leave Type</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Days</th>
                                        <th>Reason</th>
                                        <th>Applied On</th>
                                        <th>Substitute</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if (mysqli_num_rows($result) > 0) {
                                        while ($row = mysqli_fetch_assoc($result)) {
                                            // Calculate days
                                            $start = new DateTime($row['StartDate']);
                                            $end = new DateTime($row['EndDate']);
                                            $interval = $start->diff($end);
                                            $days = $interval->days + 1; // Include both start and end days
                                            
                                            // Get substitute information
                                            $substituteInfo = getSubstituteInfo($row['RequestID'], $conn);
                                            
                                            // Set status badge color
                                            $statusClass = '';
                                            switch($row['Status']) {
                                                case 'Approved':
                                                    $statusClass = 'badge-success';
                                                    break;
                                                case 'Rejected':
                                                    $statusClass = 'badge-danger';
                                                    break;
                                                default:
                                                    $statusClass = 'badge-warning';
                                            }
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['LeaveName']); ?></td>
                                        <td><?php echo date('d-M-Y', strtotime($row['StartDate'])); ?></td>
                                        <td><?php echo date('d-M-Y', strtotime($row['EndDate'])); ?></td>
                                        <td><?php echo $days; ?></td>
                                        <td><?php echo htmlspecialchars($row['Reason']); ?></td>
                                        <td><?php echo date('d-M-Y', strtotime($row['AppliedOn'])); ?></td>
                                        <td>
                                            <?php
                                            if ($substituteInfo) {
                                                echo htmlspecialchars($substituteInfo['Fullnames']) . ' ';
                                                $subStatusClass = '';
                                                switch($substituteInfo['Status']) {
                                                    case 'Accepted':
                                                        $subStatusClass = 'badge-success';
                                                        break;
                                                    case 'Rejected':
                                                        $subStatusClass = 'badge-danger';
                                                        break;
                                                    default:
                                                        $subStatusClass = 'badge-secondary';
                                                }
                                                echo '<span class="badge ' . $subStatusClass . '">' . $substituteInfo['Status'] . '</span>';
                                            } else {
                                                echo '<span class="text-muted">None</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><span class="badge <?php echo $statusClass; ?>"><?php echo $row['Status']; ?></span></td>
                                    </tr>
                                    <?php 
                                        }
                                    } else {
                                        echo '<tr><td colspan="8" class="text-center">No leave requests found</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center mt-4">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($page - 1); ?>&status=<?php echo $statusFilter; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $statusFilter; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($page + 1); ?>&status=<?php echo $statusFilter; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
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