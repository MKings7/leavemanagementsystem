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

// Handle form submissions
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'add') {
        $leaveName = mysqli_real_escape_string($conn, $_POST['leave_name']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $maxDays = intval($_POST['max_days']);
        $status = isset($_POST['status']) ? 1 : 0;
        
        // Check if leave type already exists
        $checkQuery = "SELECT COUNT(*) as count FROM leave_types WHERE LeaveName = ?";
        $stmt = mysqli_prepare($conn, $checkQuery);
        mysqli_stmt_bind_param($stmt, "s", $leaveName);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $typeExists = mysqli_fetch_assoc($result)['count'];
        
        if ($typeExists > 0) {
            $error = "Leave type already exists.";
        } else {
            $insertQuery = "INSERT INTO leave_types (LeaveName, Description, MaxDays, Status) VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insertQuery);
            mysqli_stmt_bind_param($stmt, "ssis", $leaveName, $description, $maxDays, $status);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "Leave type added successfully.";
            } else {
                $error = "Error adding leave type: " . mysqli_error($conn);
            }
        }
    } 
    elseif ($_POST['action'] == 'edit') {
        $leaveTypeID = intval($_POST['leave_type_id']);
        $leaveName = mysqli_real_escape_string($conn, $_POST['leave_name']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $maxDays = intval($_POST['max_days']);
        $status = isset($_POST['status']) ? 1 : 0;
        
        // Check if another leave type with the same name exists
        $checkQuery = "SELECT COUNT(*) as count FROM leave_types WHERE LeaveName = ? AND LeaveTypeID != ?";
        $stmt = mysqli_prepare($conn, $checkQuery);
        mysqli_stmt_bind_param($stmt, "si", $leaveName, $leaveTypeID);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $typeExists = mysqli_fetch_assoc($result)['count'];
        
        if ($typeExists > 0) {
            $error = "Another leave type with this name already exists.";
        } else {
            $updateQuery = "UPDATE leave_types SET LeaveName = ?, Description = ?, MaxDays = ?, Status = ? WHERE LeaveTypeID = ?";
            $stmt = mysqli_prepare($conn, $updateQuery);
            mysqli_stmt_bind_param($stmt, "ssiii", $leaveName, $description, $maxDays, $status, $leaveTypeID);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "Leave type updated successfully.";
            } else {
                $error = "Error updating leave type: " . mysqli_error($conn);
            }
        }
    } 
    elseif ($_POST['action'] == 'delete') {
        $leaveTypeID = intval($_POST['leave_type_id']);
        
        // Check if there are any leave requests for this type
        $checkQuery = "SELECT COUNT(*) as count FROM leave_requests WHERE LeaveTypeID = ?";
        $stmt = mysqli_prepare($conn, $checkQuery);
        mysqli_stmt_bind_param($stmt, "i", $leaveTypeID);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $requestCount = mysqli_fetch_assoc($result)['count'];
        
        if ($requestCount > 0) {
            $error = "Cannot delete this leave type as it is used in leave requests.";
        } else {
            $deleteQuery = "DELETE FROM leave_types WHERE LeaveTypeID = ?";
            $stmt = mysqli_prepare($conn, $deleteQuery);
            mysqli_stmt_bind_param($stmt, "i", $leaveTypeID);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "Leave type deleted successfully.";
            } else {
                $error = "Error deleting leave type: " . mysqli_error($conn);
            }
        }
    }
}

// Get all leave types
$query = "SELECT * FROM leave_types ORDER BY LeaveTypeID";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Types - Leave Management System</title>
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
                    <h1 class="h2">Leave Types</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#addLeaveTypeModal">
                            <i class="fas fa-plus"></i> Add Leave Type
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

                <!-- Leave Types Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Leave Type</th>
                                        <th>Description</th>
                                        <th>Maximum Days</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($result) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td><?php echo $row['LeaveTypeID']; ?></td>
                                        <td><?php echo htmlspecialchars($row['LeaveName']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Description'] ?? 'No description'); ?></td>
                                        <td><?php echo $row['MaxDays']; ?></td>
                                        <td>
                                            <?php if ($row['Status'] == 1): ?>
                                            <span class="badge badge-success">Active</span>
                                            <?php else: ?>
                                            <span class="badge badge-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info edit-btn" 
                                                    data-toggle="modal" 
                                                    data-target="#editLeaveTypeModal"
                                                    data-id="<?php echo $row['LeaveTypeID']; ?>"
                                                    data-name="<?php echo htmlspecialchars($row['LeaveName']); ?>"
                                                    data-description="<?php echo htmlspecialchars($row['Description'] ?? ''); ?>"
                                                    data-days="<?php echo $row['MaxDays']; ?>"
                                                    data-status="<?php echo $row['Status']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-btn"
                                                    data-toggle="modal"
                                                    data-target="#deleteModal"
                                                    data-id="<?php echo $row['LeaveTypeID']; ?>"
                                                    data-name="<?php echo htmlspecialchars($row['LeaveName']); ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No leave types found</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Leave Type Modal -->
    <div class="modal fade" id="addLeaveTypeModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Leave Type</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="form-group">
                            <label>Leave Type Name</label>
                            <input type="text" class="form-control" name="leave_name" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Maximum Days</label>
                            <input type="number" class="form-control" name="max_days" min="1" value="1" required>
                        </div>
                        <div class="form-group form-check">
                            <input type="checkbox" class="form-check-input" id="add_status" name="status" checked>
                            <label class="form-check-label" for="add_status">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Leave Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Leave Type Modal -->
    <div class="modal fade" id="editLeaveTypeModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Leave Type</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="leave_type_id" id="edit_id">
                        <div class="form-group">
                            <label>Leave Type Name</label>
                            <input type="text" class="form-control" name="leave_name" id="edit_name" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Maximum Days</label>
                            <input type="number" class="form-control" name="max_days" id="edit_days" min="1" required>
                        </div>
                        <div class="form-group form-check">
                            <input type="checkbox" class="form-check-input" id="edit_status" name="status">
                            <label class="form-check-label" for="edit_status">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Leave Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Leave Type</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="leave_type_id" id="delete_id">
                        <p>Are you sure you want to delete <strong><span id="delete_name"></span></strong> leave type?</p>
                        <p class="text-danger">This action cannot be undone. Leave types that are used in leave requests cannot be deleted.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Leave Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Edit leave type
            $('.edit-btn').click(function() {
                $('#edit_id').val($(this).data('id'));
                $('#edit_name').val($(this).data('name'));
                $('#edit_description').val($(this).data('description'));
                $('#edit_days').val($(this).data('days'));
                
                if ($(this).data('status') == 1) {
                    $('#edit_status').prop('checked', true);
                } else {
                    $('#edit_status').prop('checked', false);
                }
            });

            // Delete leave type
            $('.delete-btn').click(function() {
                $('#delete_id').val($(this).data('id'));
                $('#delete_name').text($(this).data('name'));
            });
        });
    </script>
</body>
</html>