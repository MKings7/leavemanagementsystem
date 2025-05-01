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

// Fetch current user's details
$query = "SELECT * FROM users WHERE UserID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Handle profile update
if (isset($_POST['update_profile'])) {
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    
    $update_query = "UPDATE users SET Fullnames = ?, EmailAddress = ? WHERE UserID = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ssi", $fullname, $email, $userID);
    
    if ($update_stmt->execute()) {
        $profile_success = "Profile updated successfully!";
        
        // Update session data
        $_SESSION['Fullnames'] = $fullname;
        
        // Refresh user data
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
    } else {
        $profile_error = "Failed to update profile: " . $conn->error;
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = md5($_POST['current_password']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    if ($current_password != $user['Password']) {
        $password_error = "Current password is incorrect.";
    } elseif ($new_password != $confirm_password) {
        $password_error = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $password_error = "New password must be at least 6 characters long.";
    } else {
        $hashed_password = md5($new_password);
        
        $password_query = "UPDATE users SET Password = ? WHERE UserID = ?";
        $password_stmt = $conn->prepare($password_query);
        $password_stmt->bind_param("si", $hashed_password, $userID);
        
        if ($password_stmt->execute()) {
            $password_success = "Password changed successfully!";
        } else {
            $password_error = "Failed to change password: " . $conn->error;
        }
    }
}

// Get system settings
$settings = array(
    'company_name' => 'NPSC',
    'system_email' => 'hr@npsc.com',
    'leave_approval_required' => true,
    'max_pending_requests' => 3,
    'fiscal_year_start' => 'January',
    'weekend_days' => 'Saturday,Sunday',
    'notification_emails' => true
);

// Handle system settings update
if (isset($_POST['update_settings'])) {
    // In a real application, you would save these to a settings table
    // For this demo, we'll just show a success message
    $settings['company_name'] = $_POST['company_name'];
    $settings['system_email'] = $_POST['system_email'];
    $settings['leave_approval_required'] = isset($_POST['leave_approval_required']);
    $settings['max_pending_requests'] = $_POST['max_pending_requests'];
    $settings['fiscal_year_start'] = $_POST['fiscal_year_start'];
    $settings['weekend_days'] = implode(',', $_POST['weekend_days'] ?? array());
    $settings['notification_emails'] = isset($_POST['notification_emails']);
    
    $settings_success = "System settings updated successfully!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - HR Portal</title>
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

        .settings-tabs {
            display: flex;
            margin-bottom: 20px;
            background-color: #fff;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .tab-link {
            padding: 15px 20px;
            background-color: #fff;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            color: #7f8c8d;
            transition: all 0.3s;
            flex: 1;
            text-align: center;
            border-bottom: 2px solid transparent;
        }

        .tab-link.active {
            color: #3498db;
            border-bottom: 2px solid #3498db;
        }

        .tab-link:hover {
            background-color: #f9f9f9;
        }

        .tab-content {
            display: none;
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .tab-content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
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

        .form-group .checkbox-group {
            margin-top: 5px;
        }

        .form-group .checkbox-group label {
            display: inline-flex;
            align-items: center;
            font-weight: normal;
            margin-right: 15px;
        }

        .form-group .checkbox-group input[type="checkbox"] {
            width: auto;
            margin-right: 5px;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #3498db;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #2980b9;
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

        .user-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .user-info .avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #3498db;
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 36px;
            margin-right: 20px;
        }

        .user-info .details {
            flex: 1;
        }

        .user-info h2 {
            margin: 0 0 5px 0;
            color: #2f3b52;
        }

        .user-info p {
            margin: 0;
            color: #7f8c8d;
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
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a></li>
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
            <h1>Settings</h1>
        </div>
        
        <div class="settings-tabs">
            <button class="tab-link active" onclick="openTab(event, 'profile')"><i class="fas fa-user"></i> Profile</button>
            <button class="tab-link" onclick="openTab(event, 'password')"><i class="fas fa-lock"></i> Password</button>
            <button class="tab-link" onclick="openTab(event, 'system')"><i class="fas fa-cogs"></i> System Settings</button>
        </div>
        
        <!-- Profile Tab -->
        <div id="profile" class="tab-content active">
            <div class="user-info">
                <div class="avatar">
                    <?php echo substr($user['Fullnames'], 0, 1); ?>
                </div>
                <div class="details">
                    <h2><?php echo htmlspecialchars($user['Fullnames']); ?></h2>
                    <p><?php echo htmlspecialchars($user['Username']); ?> | <?php echo htmlspecialchars($user['EmailAddress']); ?></p>
                </div>
            </div>
            
            <?php if (isset($profile_success)): ?>
                <div class="alert alert-success">
                    <?php echo $profile_success; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($profile_error)): ?>
                <div class="alert alert-danger">
                    <?php echo $profile_error; ?>
                </div>
            <?php endif; ?>
            
            <form action="" method="post">
                <div class="form-group">
                    <label for="fullname">Full Name</label>
                    <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($user['Fullnames']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="username">Username (Cannot be changed)</label>
                    <input type="text" id="username" value="<?php echo htmlspecialchars($user['Username']); ?>" disabled>
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['EmailAddress']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <div style="margin-top: 5px;">
                        <?php if ($user['hr'] == 1): ?>
                            <span style="background-color: #9b59b6; color: white; padding: 5px 10px; border-radius: 3px; font-size: 14px;">HR Administrator</span>
                        <?php endif; ?>
                        
                        <?php if ($user['admin'] == 1): ?>
                            <span style="background-color: #3498db; color: white; padding: 5px 10px; border-radius: 3px; font-size: 14px; margin-left: 5px;">Admin</span>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="submit" name="update_profile" class="btn">Update Profile</button>
            </form>
        </div>
        
        <!-- Password Tab -->
        <div id="password" class="tab-content">
            <?php if (isset($password_success)): ?>
                <div class="alert alert-success">
                    <?php echo $password_success; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($password_error)): ?>
                <div class="alert alert-danger">
                    <?php echo $password_error; ?>
                </div>
            <?php endif; ?>
            
            <form action="" method="post">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" name="change_password" class="btn">Change Password</button>
            </form>
        </div>
        
        <!-- System Settings Tab -->
        <div id="system" class="tab-content">
            <?php if (isset($settings_success)): ?>
                <div class="alert alert-success">
                    <?php echo $settings_success; ?>
                </div>
            <?php endif; ?>
            
            <form action="" method="post">
                <div class="form-group">
                    <label for="company_name">Company Name</label>
                    <input type="text" id="company_name" name="company_name" value="<?php echo htmlspecialchars($settings['company_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="system_email">System Email</label>
                    <input type="email" id="system_email" name="system_email" value="<?php echo htmlspecialchars($settings['system_email']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="fiscal_year_start">Fiscal Year Start Month</label>
                    <select id="fiscal_year_start" name="fiscal_year_start">
                        <?php
                        $months = array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
                        foreach ($months as $month) {
                            $selected = ($month == $settings['fiscal_year_start']) ? 'selected' : '';
                            echo "<option value=\"$month\" $selected>$month</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="max_pending_requests">Maximum Pending Leave Requests</label>
                    <input type="number" id="max_pending_requests" name="max_pending_requests" value="<?php echo htmlspecialchars($settings['max_pending_requests']); ?>" min="1" max="10" required>
                </div>
                <div class="form-group">
                    <label>Weekend Days</label>
                    <div class="checkbox-group">
                        <?php
                        $weekdays = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
                        $weekend_days = explode(',', $settings['weekend_days']);
                        
                        foreach ($weekdays as $day) {
                            $checked = in_array($day, $weekend_days) ? 'checked' : '';
                            echo "<label><input type=\"checkbox\" name=\"weekend_days[]\" value=\"$day\" $checked> $day</label>";
                        }
                        ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>System Options</label>
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="leave_approval_required" value="1" <?php echo $settings['leave_approval_required'] ? 'checked' : ''; ?>>
                            Require approval for all leave requests
                        </label>
                    </div>
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="notification_emails" value="1" <?php echo $settings['notification_emails'] ? 'checked' : ''; ?>>
                            Send email notifications for leave status changes
                        </label>
                    </div>
                </div>
                <button type="submit" name="update_settings" class="btn">Save Settings</button>
            </form>
        </div>
    </div>
    
    <script>
        function openTab(evt, tabName) {
            // Hide all tab content
            var tabcontent = document.getElementsByClassName("tab-content");
            for (var i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            
            // Remove "active" class from all tab links
            var tablinks = document.getElementsByClassName("tab-link");
            for (var i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }
            
            // Show the current tab and add "active" class to the button
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
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
