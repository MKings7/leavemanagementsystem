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

// Handle employee actions
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'add' && isset($_POST['fullname'], $_POST['username'], $_POST['email'], $_POST['password'])) {
        // Add new employee
        $fullname = $_POST['fullname'];
        $username = $_POST['username'];
        $email = $_POST['email']; 
        $password = md5($_POST['password']); // Hash password using MD5 to match existing system
        $admin = isset($_POST['admin']) ? 1 : 0;
        $hr = isset($_POST['hr']) ? 1 : 0;
        
        $stmt = $conn->prepare("INSERT INTO users (Fullnames, Username, Password, EmailAddress, admin, hr) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssis", $fullname, $username, $password, $email, $admin, $hr);
        
        if ($stmt->execute()) {
            $success_message = "Employee added successfully!";
        } else {
            $error_message = "Error adding employee: " . $stmt->error;
        }
    } elseif ($_POST['action'] == 'edit' && isset($_POST['employee_id'], $_POST['fullname'], $_POST['email'])) {
        // Update employee
        $employee_id = $_POST['employee_id'];
        $fullname = $_POST['fullname'];
        $email = $_POST['email'];
        $admin = isset($_POST['admin']) ? 1 : 0;
        $hr = isset($_POST['hr']) ? 1 : 0;
        
        $sql = "UPDATE users SET Fullnames = ?, EmailAddress = ?, admin = ?, hr = ?";
        
        // Only update password if a new one is provided
        $params = [$fullname, $email, $admin, $hr];
        $types = "ssii";
        
        if (!empty($_POST['password'])) {
            $password = md5($_POST['password']);
            $sql .= ", Password = ?";
            $params[] = $password;
            $types .= "s";
        }
        
        $sql .= " WHERE UserID = ?";
        $params[] = $employee_id;
        $types .= "i";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $success_message = "Employee updated successfully!";
        } else {
            $error_message = "Error updating employee: " . $stmt->error;
        }
    } elseif ($_POST['action'] == 'delete' && isset($_POST['employee_id'])) {
        // Check if employee has leave requests before allowing delete
        $employee_id = $_POST['employee_id'];
        
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE UserID = ?");
        $check_stmt->bind_param("i", $employee_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            $error_message = "Cannot delete employee with existing leave requests.";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE UserID = ?");
            $stmt->bind_param("i", $employee_id);
            
            if ($stmt->execute()) {
                $success_message = "Employee deleted successfully!";
            } else {
                $error_message = "Error deleting employee: " . $stmt->error;
            }
        }
    }
}

// Get search query if any
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Fetch all employees with search filter if provided
$query = "SELECT UserID, Fullnames, Username, EmailAddress, admin, hr, RegistrationDate FROM users";

if (!empty($search)) {
    $query .= " WHERE Fullnames LIKE ? OR Username LIKE ? OR EmailAddress LIKE ?";
    $searchParam = "%$search%";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
} else {
    $stmt = $conn->prepare($query);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Employees - HR Portal</title>
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

        .search-box {
            display: flex;
            gap: 10px;
        }

        .search-box input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
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

        .btn-add {
            background-color: #2ecc71;
        }

        .btn-add:hover {
            background-color: #27ae60;
        }

        .btn-edit {
            background-color: #3498db;
            padding: 5px 10px;
            font-size: 12px;
        }

        .btn-delete {
            background-color: #e74c3c;
            padding: 5px 10px;
            font-size: 12px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
            overflow: hidden;
        }

        table th, table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }

        table th {
            background-color: #f8f9fa;
            color: #2f3b52;
            font-weight: bold;
        }

        table tbody tr:hover {
            background-color: #f9f9f9;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .badge {
            display: inline-block;
            padding: 3px 7px;
            font-size: 12px;
            font-weight: bold;
            border-radius: 10px;
            color: #fff;
        }

        .badge-admin {
            background-color: #3498db;
        }

        .badge-hr {
            background-color: #9b59b6;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            width: 50%;
            max-width: 500px;
        }

        .modal-header {
            padding-bottom: 15px;
            margin-bottom: 15px;
            border-bottom: 1px solid #ecf0f1;
        }

        .modal-header h2 {
            margin: 0;
            color: #2f3b52;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #2f3b52;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .checkbox-group {
            margin-top: 10px;
        }

        .checkbox-group label {
            display: inline-block;
            margin-right: 15px;
            font-weight: normal;
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
            <li><a href="manage_employees.php" class="active"><i class="fas fa-users"></i> Employees</a></li>
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
            <h1>Manage Employees</h1>
            <div class="search-box">
                <form action="" method="get">
                    <input type="text" name="search" placeholder="Search employees..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn"><i class="fas fa-search"></i> Search</button>
                </form>
                <button class="btn btn-add" onclick="openAddModal()"><i class="fas fa-user-plus"></i> Add Employee</button>
            </div>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Registered On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['UserID']); ?></td>
                            <td><?php echo htmlspecialchars($row['Fullnames']); ?></td>
                            <td><?php echo htmlspecialchars($row['Username']); ?></td>
                            <td><?php echo htmlspecialchars($row['EmailAddress']); ?></td>
                            <td>
                                <?php if ($row['admin'] == 1): ?>
                                    <span class="badge badge-admin">Admin</span>
                                <?php endif; ?>
                                
                                <?php if ($row['hr'] == 1): ?>
                                    <span class="badge badge-hr">HR</span>
                                <?php endif; ?>
                                
                                <?php if ($row['admin'] == 0 && $row['hr'] == 0): ?>
                                    Employee
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($row['RegistrationDate'])); ?></td>
                            <td class="action-buttons">
                                <button class="btn btn-edit" onclick="openEditModal(<?php echo $row['UserID']; ?>, '<?php echo addslashes($row['Fullnames']); ?>', '<?php echo addslashes($row['Username']); ?>', '<?php echo addslashes($row['EmailAddress']); ?>', <?php echo $row['admin']; ?>, <?php echo $row['hr']; ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-delete" onclick="confirmDelete(<?php echo $row['UserID']; ?>, '<?php echo addslashes($row['Fullnames']); ?>')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">No employees found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Employee Modal -->
    <div id="addEmployeeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeModal('addEmployeeModal')">&times;</span>
                <h2>Add New Employee</h2>
            </div>
            <form action="" method="post">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label for="fullname">Full Name</label>
                    <input type="text" id="fullname" name="fullname" required>
                </div>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group checkbox-group">
                    <label>
                        <input type="checkbox" name="admin" value="1"> Admin Access
                    </label>
                    <label>
                        <input type="checkbox" name="hr" value="1"> HR Access
                    </label>
                </div>
                <button type="submit" class="btn btn-add">Add Employee</button>
            </form>
        </div>
    </div>

    <!-- Edit Employee Modal -->
    <div id="editEmployeeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeModal('editEmployeeModal')">&times;</span>
                <h2>Edit Employee</h2>
            </div>
            <form action="" method="post">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit_employee_id" name="employee_id">
                <div class="form-group">
                    <label for="edit_fullname">Full Name</label>
                    <input type="text" id="edit_fullname" name="fullname" required>
                </div>
                <div class="form-group">
                    <label for="edit_username">Username (read-only)</label>
                    <input type="text" id="edit_username" disabled>
                </div>
                <div class="form-group">
                    <label for="edit_email">Email</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="edit_password">Password (leave blank to keep current)</label>
                    <input type="password" id="edit_password" name="password">
                </div>
                <div class="form-group checkbox-group">
                    <label>
                        <input type="checkbox" id="edit_admin" name="admin" value="1"> Admin Access
                    </label>
                    <label>
                        <input type="checkbox" id="edit_hr" name="hr" value="1"> HR Access
                    </label>
                </div>
                <button type="submit" class="btn btn-edit">Update Employee</button>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteEmployeeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeModal('deleteEmployeeModal')">&times;</span>
                <h2>Confirm Deletion</h2>
            </div>
            <p>Are you sure you want to delete the employee <strong id="delete_employee_name"></strong>?</p>
            <p>This action cannot be undone.</p>
            <form action="" method="post" style="margin-top: 20px;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" id="delete_employee_id" name="employee_id">
                <button type="button" class="btn" onclick="closeModal('deleteEmployeeModal')" style="background-color: #95a5a6;">Cancel</button>
                <button type="submit" class="btn btn-delete">Delete</button>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openAddModal() {
            document.getElementById('addEmployeeModal').style.display = 'block';
        }
        
        function openEditModal(id, fullname, username, email, isAdmin, isHr) {
            document.getElementById('edit_employee_id').value = id;
            document.getElementById('edit_fullname').value = fullname;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_admin').checked = isAdmin == 1;
            document.getElementById('edit_hr').checked = isHr == 1;
            document.getElementById('editEmployeeModal').style.display = 'block';
        }
        
        function confirmDelete(id, name) {
            document.getElementById('delete_employee_id').value = id;
            document.getElementById('delete_employee_name').textContent = name;
            document.getElementById('deleteEmployeeModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modals if user clicks outside modal content
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.getElementsByClassName('alert');
            for(var i = 0; i < alerts.length; i++) {
                alerts[i].style.display = 'none';
            }
        }, 5000);
    </script>
</body>
</html>
