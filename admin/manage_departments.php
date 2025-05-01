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
        $departmentName = mysqli_real_escape_string($conn, $_POST['department_name']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        
        // Check if department already exists
        $checkQuery = "SELECT COUNT(*) as count FROM departments WHERE DepartmentName = ?";
        $stmt = mysqli_prepare($conn, $checkQuery);
        mysqli_stmt_bind_param($stmt, "s", $departmentName);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $deptExists = mysqli_fetch_assoc($result)['count'];
        
        if ($deptExists > 0) {
            $error = "Department already exists.";
        } else {
            $insertQuery = "INSERT INTO departments (DepartmentName, Description) VALUES (?, ?)";
            $stmt = mysqli_prepare($conn, $insertQuery);
            mysqli_stmt_bind_param($stmt, "ss", $departmentName, $description);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "Department added successfully.";
            } else {
                $error = "Error adding department: " . mysqli_error($conn);
            }
        }
    } 
    elseif ($_POST['action'] == 'edit') {
        $departmentID = intval($_POST['department_id']);
        $departmentName = mysqli_real_escape_string($conn, $_POST['department_name']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        
        // Check if another department with the same name exists
        $checkQuery = "SELECT COUNT(*) as count FROM departments WHERE DepartmentName = ? AND DepartmentID != ?";
        $stmt = mysqli_prepare($conn, $checkQuery);
        mysqli_stmt_bind_param($stmt, "si", $departmentName, $departmentID);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $deptExists = mysqli_fetch_assoc($result)['count'];
        
        if ($deptExists > 0) {
            $error = "Another department with this name already exists.";
        } else {
            $updateQuery = "UPDATE departments SET DepartmentName = ?, Description = ? WHERE DepartmentID = ?";
            $stmt = mysqli_prepare($conn, $updateQuery);
            mysqli_stmt_bind_param($stmt, "ssi", $departmentName, $description, $departmentID);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "Department updated successfully.";
            } else {
                $error = "Error updating department: " . mysqli_error($conn);
            }
        }
    } 
    elseif ($_POST['action'] == 'delete') {
        $departmentID = intval($_POST['department_id']);
        
        // Check if there are any users in this department (would need user_departments relation table)
        // For now, we'll just delete the department as there's no direct relation in the schema
        $deleteQuery = "DELETE FROM departments WHERE DepartmentID = ?";
        $stmt = mysqli_prepare($conn, $deleteQuery);
        mysqli_stmt_bind_param($stmt, "i", $departmentID);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "Department deleted successfully.";
        } else {
            $error = "Error deleting department: " . mysqli_error($conn);
        }
    }
}

// Get all departments with pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$itemsPerPage = 10;
$offset = ($page - 1) * $itemsPerPage;

// Get total departments count
$countQuery = "SELECT COUNT(*) as total FROM departments";
$countResult = mysqli_query($conn, $countQuery);
$totalDepartments = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalDepartments / $itemsPerPage);

// Get filtered departments
$searchTerm = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$query = "SELECT * FROM departments WHERE 1=1";

if (!empty($searchTerm)) {
    $query .= " AND (DepartmentName LIKE '%$searchTerm%' OR Description LIKE '%$searchTerm%')";
}

$query .= " ORDER BY DepartmentID LIMIT $offset, $itemsPerPage";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments - Leave Management System</title>
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
                            <a class="nav-link active" href="manage_departments.php">
                                <i class="fas fa-building"></i> Manage Departments
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
                    <h1 class="h2">Manage Departments</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#addDepartmentModal">
                            <i class="fas fa-plus"></i> Add Department
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

                <!-- Search -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="get" class="form-row align-items-center">
                            <div class="col-md-9 mb-2">
                                <label class="sr-only" for="search">Search</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text"><i class="fas fa-search"></i></div>
                                    </div>
                                    <input type="text" class="form-control" id="search" name="search" placeholder="Search by department name or description" value="<?php echo $searchTerm; ?>">
                                </div>
                            </div>
                            <div class="col-md-3 mb-2">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-filter"></i> Search
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Departments Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Department Name</th>
                                        <th>Description</th>
                                        <th>Created On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($result) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td><?php echo $row['DepartmentID']; ?></td>
                                        <td><?php echo htmlspecialchars($row['DepartmentName']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Description'] ?? 'No description'); ?></td>
                                        <td><?php echo date('d-M-Y', strtotime($row['CreatedAt'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info edit-btn" 
                                                    data-toggle="modal" 
                                                    data-target="#editDepartmentModal"
                                                    data-id="<?php echo $row['DepartmentID']; ?>"
                                                    data-name="<?php echo htmlspecialchars($row['DepartmentName']); ?>"
                                                    data-description="<?php echo htmlspecialchars($row['Description'] ?? ''); ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-btn"
                                                    data-toggle="modal"
                                                    data-target="#deleteModal"
                                                    data-id="<?php echo $row['DepartmentID']; ?>"
                                                    data-name="<?php echo htmlspecialchars($row['DepartmentName']); ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No departments found</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo $searchTerm; ?>">Previous</a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo $searchTerm; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo $searchTerm; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Department Modal -->
    <div class="modal fade" id="addDepartmentModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Department</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="form-group">
                            <label>Department Name</label>
                            <input type="text" class="form-control" name="department_name" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Department Modal -->
    <div class="modal fade" id="editDepartmentModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Department</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="department_id" id="edit_id">
                        <div class="form-group">
                            <label>Department Name</label>
                            <input type="text" class="form-control" name="department_name" id="edit_name" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Department</button>
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
                    <h5 class="modal-title">Delete Department</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="department_id" id="delete_id">
                        <p>Are you sure you want to delete <strong><span id="delete_name"></span></strong> department?</p>
                        <p class="text-danger">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Department</button>
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
            // Edit department
            $('.edit-btn').click(function() {
                $('#edit_id').val($(this).data('id'));
                $('#edit_name').val($(this).data('name'));
                $('#edit_description').val($(this).data('description'));
            });

            // Delete department
            $('.delete-btn').click(function() {
                $('#delete_id').val($(this).data('id'));
                $('#delete_name').text($(this).data('name'));
            });
        });
    </script>
</body>
</html>