<?php
session_start();
include "../includes/dbconnect.php";

// Check if user is logged in and is an HR admin
if (!isset($_SESSION['UserID']) || !isset($_SESSION['hr']) || $_SESSION['hr'] != 1) {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

$userID = $_SESSION['UserID'];
$username = $_SESSION['Username'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Add new leave type
        if ($_POST['action'] === 'add' && isset($_POST['leave_name']) && isset($_POST['max_days'])) {
            $leaveName = $_POST['leave_name'];
            $maxDays = $_POST['max_days'];
            $description = $_POST['description'] ?? '';
            
            $stmt = $conn->prepare("INSERT INTO leave_types (LeaveName, MaxDays, Description) VALUES (?, ?, ?)");
            $stmt->bind_param("sis", $leaveName, $maxDays, $description);
            
            if ($stmt->execute()) {
                $success_message = "Leave type added successfully!";
            } else {
                $error_message = "Error adding leave type: " . $stmt->error;
            }
        }
        // Update leave type
        else if ($_POST['action'] === 'edit' && isset($_POST['leave_type_id']) && isset($_POST['leave_name']) && isset($_POST['max_days'])) {
            $leaveTypeId = $_POST['leave_type_id'];
            $leaveName = $_POST['leave_name'];
            $maxDays = $_POST['max_days'];
            $description = $_POST['description'] ?? '';
            
            $stmt = $conn->prepare("UPDATE leave_types SET LeaveName = ?, MaxDays = ?, Description = ? WHERE LeaveTypeID = ?");
            $stmt->bind_param("sisi", $leaveName, $maxDays, $description, $leaveTypeId);
            
            if ($stmt->execute()) {
                $success_message = "Leave type updated successfully!";
            } else {
                $error_message = "Error updating leave type: " . $stmt->error;
            }
        }
        // Delete leave type
        else if ($_POST['action'] === 'delete' && isset($_POST['leave_type_id'])) {
            $leaveTypeId = $_POST['leave_type_id'];
            
            // Check if leave type is in use
            $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE LeaveTypeID = ?");
            $checkStmt->bind_param("i", $leaveTypeId);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                $error_message = "Cannot delete leave type as it is linked to existing leave requests.";
            } else {
                $stmt = $conn->prepare("DELETE FROM leave_types WHERE LeaveTypeID = ?");
                $stmt->bind_param("i", $leaveTypeId);
                
                if ($stmt->execute()) {
                    $success_message = "Leave type deleted successfully!";
                } else {
                    $error_message = "Error deleting leave type: " . $stmt->error;
                }
            }
        }
    }
}

// Get all leave types
$query = "SELECT * FROM leave_types ORDER BY LeaveName";
$leaveTypes = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Leave Types - HR Portal</title>
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

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
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
            <li><a href="leave_types.php" class="active"><i class="fas fa-list"></i> Leave Types</a></li>
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
            <h1>Manage Leave Types</h1>
            <button class="btn btn-add" onclick="openAddModal()"><i class="fas fa-plus"></i> Add Leave Type</button>
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
                    <th>Leave Type</th>
                    <th>Max Days</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($leaveTypes->num_rows > 0): ?>
                    <?php while ($row = $leaveTypes->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['LeaveTypeID']); ?></td>
                            <td><?php echo htmlspecialchars($row['LeaveName']); ?></td>
                            <td><?php echo htmlspecialchars($row['MaxDays']); ?></td>
                            <td><?php echo htmlspecialchars($row['Description'] ?? 'N/A'); ?></td>
                            <td class="action-buttons">
                                <button class="btn btn-edit" onclick="openEditModal(<?php echo $row['LeaveTypeID']; ?>, '<?php echo addslashes($row['LeaveName']); ?>', <?php echo $row['MaxDays']; ?>, '<?php echo addslashes($row['Description'] ?? ''); ?>')">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-delete" onclick="confirmDelete(<?php echo $row['LeaveTypeID']; ?>, '<?php echo addslashes($row['LeaveName']); ?>')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">No leave types found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Leave Type Modal -->
    <div id="addLeaveTypeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeModal('addLeaveTypeModal')">&times;</span>
                <h2>Add Leave Type</h2>
            </div>
            <form action="" method="post">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label for="leave_name">Leave Type Name</label>
                    <input type="text" id="leave_name" name="leave_name" required>
                </div>
                <div class="form-group">
                    <label for="max_days">Maximum Days Allowed</label>
                    <input type="number" id="max_days" name="max_days" min="0" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Optional description..."></textarea>
                </div>
                <button type="submit" class="btn btn-add">Add Leave Type</button>
            </form>
        </div>
    </div>

    <!-- Edit Leave Type Modal -->
    <div id="editLeaveTypeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeModal('editLeaveTypeModal')">&times;</span>
                <h2>Edit Leave Type</h2>
            </div>
            <form action="" method="post">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit_leave_type_id" name="leave_type_id">
                <div class="form-group">
                    <label for="edit_leave_name">Leave Type Name</label>
                    <input type="text" id="edit_leave_name" name="leave_name" required>
                </div>
                <div class="form-group">
                    <label for="edit_max_days">Maximum Days Allowed</label>
                    <input type="number" id="edit_max_days" name="max_days" min="0" required>
                </div>
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description" placeholder="Optional description..."></textarea>
                </div>
                <button type="submit" class="btn btn-edit">Update Leave Type</button>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteLeaveTypeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeModal('deleteLeaveTypeModal')">&times;</span>
                <h2>Confirm Deletion</h2>
            </div>
            <p>Are you sure you want to delete the leave type <strong id="delete_leave_type_name"></strong>?</p>
            <p>This action cannot be undone.</p>
            <form action="" method="post" style="margin-top: 20px;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" id="delete_leave_type_id" name="leave_type_id">
                <button type="button" class="btn" onclick="closeModal('deleteLeaveTypeModal')" style="background-color: #95a5a6;">Cancel</button>
                <button type="submit" class="btn btn-delete">Delete</button>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openAddModal() {
            document.getElementById('addLeaveTypeModal').style.display = 'block';
        }
        
        function openEditModal(id, name, maxDays, description) {
            document.getElementById('edit_leave_type_id').value = id;
            document.getElementById('edit_leave_name').value = name;
            document.getElementById('edit_max_days').value = maxDays;
            document.getElementById('edit_description').value = description;
            document.getElementById('editLeaveTypeModal').style.display = 'block';
        }
        
        function confirmDelete(id, name) {
            document.getElementById('delete_leave_type_id').value = id;
            document.getElementById('delete_leave_type_name').textContent = name;
            document.getElementById('deleteLeaveTypeModal').style.display = 'block';
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
