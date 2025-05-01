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

// Handle user actions (delete, change role, etc.)
if (isset($_POST['action'])) {
    $targetUserID = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    
    if ($_POST['action'] == 'delete' && $targetUserID > 0) {
        // Check if there are any leave requests associated with this user
        $checkQuery = "SELECT COUNT(*) as count FROM leave_requests WHERE UserID = ?";
        $stmt = mysqli_prepare($conn, $checkQuery);
        mysqli_stmt_bind_param($stmt, "i", $targetUserID);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $leaveCount = mysqli_fetch_assoc($result)['count'];
        
        if ($leaveCount > 0) {
            $error = "Cannot delete user as they have leave requests in the system.";
        } else {
            // Delete the user
            $deleteQuery = "DELETE FROM users WHERE UserID = ?";
            $stmt = mysqli_prepare($conn, $deleteQuery);
            mysqli_stmt_bind_param($stmt, "i", $targetUserID);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "User deleted successfully.";
            } else {
                $error = "Error deleting user: " . mysqli_error($conn);
            }
        }
    } elseif ($_POST['action'] == 'change_role' && $targetUserID > 0) {
        $role = $_POST['role'];
        $admin = ($role == 'admin') ? 1 : 0;
        $hr = ($role == 'hr') ? 1 : 0;
        
        $updateQuery = "UPDATE users SET admin = ?, hr = ? WHERE UserID = ?";
        $stmt = mysqli_prepare($conn, $updateQuery);
        mysqli_stmt_bind_param($stmt, "iii", $admin, $hr, $targetUserID);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = "User role updated successfully.";
        } else {
            $error = "Error updating user role: " . mysqli_error($conn);
        }
    } elseif ($_POST['action'] == 'create') {
        $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $password = md5($_POST['password']); // Note: Consider using a more secure hashing method
        $role = $_POST['role'];
        $admin = ($role == 'admin') ? 1 : 0;
        $hr = ($role == 'hr') ? 1 : 0;
        
        // Check if username or email already exists
        $checkQuery = "SELECT COUNT(*) as count FROM users WHERE Username = ? OR EmailAddress = ?";
        $stmt = mysqli_prepare($conn, $checkQuery);
        mysqli_stmt_bind_param($stmt, "ss", $username, $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $userExists = mysqli_fetch_assoc($result)['count'];
        
        if ($userExists > 0) {
            $error = "Username or email already exists.";
        } else {
            $insertQuery = "INSERT INTO users (Fullnames, Username, Password, EmailAddress, admin, hr) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insertQuery);
            mysqli_stmt_bind_param($stmt, "ssssis", $fullname, $username, $password, $email, $admin, $hr);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "User created successfully.";
            } else {
                $error = "Error creating user: " . mysqli_error($conn);
            }
        }
    }
}

// Get list of users with pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$itemsPerPage = 10;
$offset = ($page - 1) * $itemsPerPage;

// Get total users count
$countQuery = "SELECT COUNT(*) as total FROM users";
$countResult = mysqli_query($conn, $countQuery);
$totalUsers = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalUsers / $itemsPerPage);

// Get filtered users
$searchTerm = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$roleFilter = isset($_GET['role']) ? $_GET['role'] : 'all';

$query = "SELECT * FROM users WHERE 1=1";

if (!empty($searchTerm)) {
    $query .= " AND (Fullnames LIKE '%$searchTerm%' OR Username LIKE '%$searchTerm%' OR EmailAddress LIKE '%$searchTerm%')";
}

if ($roleFilter != 'all') {
    if ($roleFilter == 'admin') {
        $query .= " AND admin = 1";
    } elseif ($roleFilter == 'hr') {
        $query .= " AND hr = 1";
    } elseif ($roleFilter == 'employee') {
        $query .= " AND admin = 0 AND hr = 0";
    }
}

$query .= " ORDER BY UserID DESC LIMIT $offset, $itemsPerPage";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Employees - Leave Management System</title>
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
                            <a class="nav-link active" href="manage_employees.php">
                                <i class="fas fa-users"></i> Manage Employees
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_departments.php">
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
                    <h1 class="h2">Manage Employees</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#createUserModal">
                            <i class="fas fa-user-plus"></i> Add New Employee
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

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="get" class="form-row align-items-center">
                            <div class="col-md-5 mb-2">
                                <label class="sr-only" for="search">Search</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text"><i class="fas fa-search"></i></div>
                                    </div>
                                    <input type="text" class="form-control" id="search" name="search" placeholder="Search by name, username or email" value="<?php echo $searchTerm; ?>">
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="sr-only" for="role">Role</label>
                                <select class="form-control" id="role" name="role">
                                    <option value="all" <?php echo $roleFilter == 'all' ? 'selected' : ''; ?>>All Roles</option>
                                    <option value="admin" <?php echo $roleFilter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="hr" <?php echo $roleFilter == 'hr' ? 'selected' : ''; ?>>HR</option>
                                    <option value="employee" <?php echo $roleFilter == 'employee' ? 'selected' : ''; ?>>Employee</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-2">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Employees Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Registered On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($result) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td><?php echo $row['UserID']; ?></td>
                                        <td><?php echo htmlspecialchars($row['Fullnames']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Username']); ?></td>
                                        <td><?php echo htmlspecialchars($row['EmailAddress']); ?></td>
                                        <td>
                                            <?php 
                                                if ($row['admin'] == 1) {
                                                    echo '<span class="badge badge-primary">Admin</span>';
                                                } elseif ($row['hr'] == 1) {
                                                    echo '<span class="badge badge-info">HR</span>';
                                                } else {
                                                    echo '<span class="badge badge-secondary">Employee</span>';
                                                }
                                            ?>
                                        </td>
                                        <td><?php echo date('d-M-Y', strtotime($row['RegistrationDate'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info edit-btn" 
                                                    data-toggle="modal" 
                                                    data-target="#editRoleModal"
                                                    data-id="<?php echo $row['UserID']; ?>"
                                                    data-name="<?php echo htmlspecialchars($row['Fullnames']); ?>"
                                                    data-role="<?php echo $row['admin'] == 1 ? 'admin' : ($row['hr'] == 1 ? 'hr' : 'employee'); ?>">
                                                <i class="fas fa-user-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger delete-btn"
                                                    data-toggle="modal"
                                                    data-target="#deleteModal"
                                                    data-id="<?php echo $row['UserID']; ?>"
                                                    data-name="<?php echo htmlspecialchars($row['Fullnames']); ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No employees found</td>
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
                                    <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo $searchTerm; ?>&role=<?php echo $roleFilter; ?>">Previous</a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo $searchTerm; ?>&role=<?php echo $roleFilter; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo $searchTerm; ?>&role=<?php echo $roleFilter; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Employee</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" class="form-control" name="fullname" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <select class="form-control" name="role" required>
                                <option value="employee">Employee</option>
                                <option value="hr">HR</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Employee</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Role Modal -->
    <div class="modal fade" id="editRoleModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change User Role</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="change_role">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <p>Change role for <strong><span id="edit_user_name"></span></strong>:</p>
                        <div class="form-group">
                            <label>Role</label>
                            <select class="form-control" name="role" id="edit_role" required>
                                <option value="employee">Employee</option>
                                <option value="hr">HR</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Role</button>
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
                    <h5 class="modal-title">Delete User</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" id="delete_user_id">
                        <p>Are you sure you want to delete <strong><span id="delete_user_name"></span></strong>?</p>
                        <p class="text-danger">This action cannot be undone. Users with leave requests cannot be deleted.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete User</button>
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
            // Edit role
            $('.edit-btn').click(function() {
                $('#edit_user_id').val($(this).data('id'));
                $('#edit_user_name').text($(this).data('name'));
                $('#edit_role').val($(this).data('role'));
            });

            // Delete user
            $('.delete-btn').click(function() {
                $('#delete_user_id').val($(this).data('id'));
                $('#delete_user_name').text($(this).data('name'));
            });
        });
    </script>
</body>
</html>