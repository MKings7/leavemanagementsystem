<?php
session_start();
include "../includes/dbconnect.php";
include "../includes/functions.php";
include "../includes/email_functions.php";

// Check if user is logged in and is an admin
if (!isset($_SESSION['UserID']) || $_SESSION['admin'] != 1) {
    header("Location: ../login.php");
    exit();
}

$userID = $_SESSION['UserID'];
$username = $_SESSION['Username'];
$message = "";
$error = "";

// Handle leave request actions
if (isset($_POST['action']) && isset($_POST['request_id'])) {
    $requestID = intval($_POST['request_id']);
    $action = $_POST['action'];
    $comments = isset($_POST['comments']) ? mysqli_real_escape_string($conn, $_POST['comments']) : '';
    
    // Update request status
    $status = ($action === 'approve') ? 'Approved' : 'Rejected';
    $updateQuery = "UPDATE leave_requests SET Status = ?, Comments = ?, ProcessedBy = ?, ProcessedDate = NOW() WHERE RequestID = ?";
    $stmt = mysqli_prepare($conn, $updateQuery);
    mysqli_stmt_bind_param($stmt, "sssi", $status, $comments, $userID, $requestID);
    
    if (mysqli_stmt_execute($stmt)) {
        // Send email notification
        try {
            sendLeaveStatusNotification($conn, $requestID, $status);
            $message = "Leave request has been " . strtolower($status) . " successfully.";
            
            // Log the action
            $logMessage = date('Y-m-d H:i:s') . " - User ID: $userID, Username: $username, Action: " . strtolower($status) . " leave request ID: $requestID\n";
            file_put_contents("../leave_actions.log", $logMessage, FILE_APPEND);
        } catch (Exception $e) {
            $message = "Leave request " . strtolower($status) . ", but email notification failed: " . $e->getMessage();
        }
    } else {
        $error = "Error updating leave request: " . mysqli_error($conn);
    }
}

// Get filter parameters
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$departmentFilter = isset($_GET['department']) ? $_GET['department'] : 'all';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Build query based on filters
$query = "SELECT lr.*, u.Fullnames, u.EmailAddress as Email, lt.LeaveName,
                 DATEDIFF(lr.EndDate, lr.StartDate) + 1 as Days
          FROM leave_requests lr
          JOIN users u ON lr.UserID = u.UserID
          JOIN leave_types lt ON lr.LeaveTypeID = lt.LeaveTypeID
          WHERE 1=1";

if ($statusFilter !== 'all') {
    $query .= " AND lr.Status = '" . mysqli_real_escape_string($conn, $statusFilter) . "'";
}
if (!empty($startDate)) {
    $query .= " AND lr.StartDate >= '" . mysqli_real_escape_string($conn, $startDate) . "'";
}
if (!empty($endDate)) {
    $query .= " AND lr.EndDate <= '" . mysqli_real_escape_string($conn, $endDate) . "'";
}

$query .= " ORDER BY lr.AppliedOn DESC";

// Get departments for filter from departments table
$deptQuery = "SELECT DepartmentID, DepartmentName FROM departments ORDER BY DepartmentName";
$departments = mysqli_query($conn, $deptQuery);

// Execute main query
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Requests - Leave Management System</title>
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
                    <h1 class="h2">Leave Requests</h1>
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

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="get" class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select name="status" class="form-control">
                                        <option value="all" <?php echo $statusFilter == 'all' ? 'selected' : ''; ?>>All</option>
                                        <option value="Pending" <?php echo $statusFilter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="Approved" <?php echo $statusFilter == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value="Rejected" <?php echo $statusFilter == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Department</label>
                                    <select name="department" class="form-control">
                                        <option value="all">All Departments</option>
                                        <?php while ($dept = mysqli_fetch_assoc($departments)): ?>
                                        <option value="<?php echo htmlspecialchars($dept['DepartmentID']); ?>" 
                                                <?php echo $departmentFilter == $dept['DepartmentID'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['DepartmentName']); ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Start Date</label>
                                    <input type="date" name="start_date" class="form-control" value="<?php echo $startDate; ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>End Date</label>
                                    <input type="date" name="end_date" class="form-control" value="<?php echo $endDate; ?>">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-filter"></i> Filter
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Leave Requests Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Leave Type</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Days</th>
                                        <th>Applied On</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($result) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['Fullnames']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Department'] ?? 'Not Assigned'); ?></td>
                                        <td><?php echo htmlspecialchars($row['LeaveName']); ?></td>
                                        <td><?php echo date('d-M-Y', strtotime($row['StartDate'])); ?></td>
                                        <td><?php echo date('d-M-Y', strtotime($row['EndDate'])); ?></td>
                                        <td><?php echo $row['Days']; ?></td>
                                        <td><?php echo date('d-M-Y', strtotime($row['AppliedOn'])); ?></td>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo $row['Status'] == 'Approved' ? 'success' : 
                                                    ($row['Status'] == 'Rejected' ? 'danger' : 'warning'); 
                                            ?>">
                                                <?php echo $row['Status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info view-btn" 
                                                    data-toggle="modal" 
                                                    data-target="#viewRequestModal"
                                                    data-id="<?php echo $row['RequestID']; ?>"
                                                    data-employee="<?php echo htmlspecialchars($row['Fullnames']); ?>"
                                                    data-email="<?php echo htmlspecialchars($row['Email'] ?? ''); ?>"
                                                    data-department="<?php echo htmlspecialchars($row['Department'] ?? 'Not Assigned'); ?>"
                                                    data-type="<?php echo htmlspecialchars($row['LeaveName']); ?>"
                                                    data-start="<?php echo date('d-M-Y', strtotime($row['StartDate'])); ?>"
                                                    data-end="<?php echo date('d-M-Y', strtotime($row['EndDate'])); ?>"
                                                    data-days="<?php echo $row['Days']; ?>"
                                                    data-reason="<?php echo htmlspecialchars($row['Reason']); ?>"
                                                    data-status="<?php echo $row['Status']; ?>"
                                                    data-comments="<?php echo htmlspecialchars($row['RejectionReason'] ?? ''); ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($row['Status'] == 'Pending'): ?>
                                            <button class="btn btn-sm btn-success approve-btn"
                                                    data-toggle="modal"
                                                    data-target="#approveModal"
                                                    data-id="<?php echo $row['RequestID']; ?>"
                                                    data-employee="<?php echo htmlspecialchars($row['Fullnames']); ?>">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger reject-btn"
                                                    data-toggle="modal"
                                                    data-target="#rejectModal"
                                                    data-id="<?php echo $row['RequestID']; ?>"
                                                    data-employee="<?php echo htmlspecialchars($row['Fullnames']); ?>">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center">No leave requests found</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- View Request Modal -->
                <div class="modal fade" id="viewRequestModal" tabindex="-1" role="dialog">
                    <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Leave Request Details</h5>
                                <button type="button" class="close" data-dismiss="modal">
                                    <span>&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Employee:</strong> <span id="view_employee"></span></p>
                                        <p><strong>Email:</strong> <span id="view_email"></span></p>
                                        <p><strong>Department:</strong> <span id="view_department"></span></p>
                                        <p><strong>Leave Type:</strong> <span id="view_type"></span></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Start Date:</strong> <span id="view_start"></span></p>
                                        <p><strong>End Date:</strong> <span id="view_end"></span></p>
                                        <p><strong>Days:</strong> <span id="view_days"></span></p>
                                        <p><strong>Status:</strong> <span id="view_status"></span></p>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label><strong>Reason:</strong></label>
                                    <p id="view_reason"></p>
                                </div>
                                <div class="form-group">
                                    <label><strong>Comments:</strong></label>
                                    <p id="view_comments"></p>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Approve Modal -->
                <div class="modal fade" id="approveModal" tabindex="-1" role="dialog">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Approve Leave Request</h5>
                                <button type="button" class="close" data-dismiss="modal">
                                    <span>&times;</span>
                                </button>
                            </div>
                            <form method="post">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="request_id" id="approve_request_id">
                                    <p>Are you sure you want to approve the leave request for <strong><span id="approve_employee"></span></strong>?</p>
                                    <div class="form-group">
                                        <label>Comments (optional)</label>
                                        <textarea class="form-control" name="comments" rows="3"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-success">Approve Leave</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Reject Modal -->
                <div class="modal fade" id="rejectModal" tabindex="-1" role="dialog">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Reject Leave Request</h5>
                                <button type="button" class="close" data-dismiss="modal">
                                    <span>&times;</span>
                                </button>
                            </div>
                            <form method="post">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="request_id" id="reject_request_id">
                                    <p>Are you sure you want to reject the leave request for <strong><span id="reject_employee"></span></strong>?</p>
                                    <div class="form-group">
                                        <label>Reason for Rejection</label>
                                        <textarea class="form-control" name="comments" rows="3" required></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger">Reject Leave</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // View request details
        $('.view-btn').click(function() {
            $('#view_employee').text($(this).data('employee'));
            $('#view_email').text($(this).data('email'));
            $('#view_department').text($(this).data('department'));
            $('#view_type').text($(this).data('type'));
            $('#view_start').text($(this).data('start'));
            $('#view_end').text($(this).data('end'));
            $('#view_days').text($(this).data('days'));
            $('#view_reason').text($(this).data('reason'));
            $('#view_status').text($(this).data('status'));
            $('#view_comments').text($(this).data('comments'));
        });

        // Approve request
        $('.approve-btn').click(function() {
            $('#approve_request_id').val($(this).data('id'));
            $('#approve_employee').text($(this).data('employee'));
        });

        // Reject request
        $('.reject-btn').click(function() {
            $('#reject_request_id').val($(this).data('id'));
            $('#reject_employee').text($(this).data('employee'));
        });
    });
    </script>
</body>
</html>