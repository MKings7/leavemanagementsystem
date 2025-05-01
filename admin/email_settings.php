<?php
session_start();
include "../includes/dbconnect.php";
include "../includes/functions.php";
include_once "../includes/email_functions.php";

// Check if user is logged in and is an admin
if (!isset($_SESSION['UserID']) || $_SESSION['admin'] != 1) {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

$userID = $_SESSION['UserID'];
$username = $_SESSION['Username'];

// Get email settings from database
$emailSettings = getEmailSettings($conn);

// Handle email settings update
if (isset($_POST['update_email_settings'])) {
    $smtp_host = $_POST['smtp_host'];
    $smtp_port = $_POST['smtp_port'];
    $smtp_username = $_POST['smtp_username'];
    $smtp_from_email = $_POST['smtp_from_email'];
    $smtp_from_name = $_POST['smtp_from_name'];
    $smtp_encryption = $_POST['smtp_encryption'];
    
    // Only update password if a new one is provided
    $smtp_password = $_POST['smtp_password'];
    
    if (empty($emailSettings)) {
        // Insert new settings
        $query = "INSERT INTO email_settings (smtp_host, smtp_port, smtp_username, smtp_password, 
                 smtp_from_email, smtp_from_name, smtp_encryption) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sisssss", $smtp_host, $smtp_port, $smtp_username, $smtp_password, 
                         $smtp_from_email, $smtp_from_name, $smtp_encryption);
    } else {
        // Update existing settings
        if (empty($smtp_password)) {
            // Don't update password if not provided
            $query = "UPDATE email_settings SET smtp_host = ?, smtp_port = ?, smtp_username = ?, 
                    smtp_from_email = ?, smtp_from_name = ?, smtp_encryption = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sissss", $smtp_host, $smtp_port, $smtp_username, 
                            $smtp_from_email, $smtp_from_name, $smtp_encryption);
        } else {
            // Update with new password
            $query = "UPDATE email_settings SET smtp_host = ?, smtp_port = ?, smtp_username = ?, 
                    smtp_password = ?, smtp_from_email = ?, smtp_from_name = ?, smtp_encryption = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sisssss", $smtp_host, $smtp_port, $smtp_username, $smtp_password, 
                            $smtp_from_email, $smtp_from_name, $smtp_encryption);
        }
    }
    
    if ($stmt->execute()) {
        $email_settings_success = "Email settings updated successfully!";
        // Refresh email settings
        $emailSettings = getEmailSettings($conn);
    } else {
        $email_settings_error = "Failed to update email settings: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Settings - Admin Panel</title>
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
                    <h1 class="h2">Email Settings</h1>
                </div>

                <?php if (isset($email_settings_success)): ?>
                <div class="alert alert-success">
                    <?php echo $email_settings_success; ?>
                </div>
                <?php endif; ?>

                <?php if (isset($email_settings_error)): ?>
                <div class="alert alert-danger">
                    <?php echo $email_settings_error; ?>
                </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-envelope mr-1"></i>
                        SMTP Configuration
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <div class="form-group">
                                <label for="smtp_host">SMTP Server</label>
                                <input type="text" class="form-control" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($emailSettings['smtp_host'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="smtp_port">SMTP Port</label>
                                <input type="number" class="form-control" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($emailSettings['smtp_port'] ?? '587'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="smtp_encryption">Encryption</label>
                                <select class="form-control" id="smtp_encryption" name="smtp_encryption">
                                    <option value="tls" <?php echo (isset($emailSettings['smtp_encryption']) && $emailSettings['smtp_encryption'] == 'tls') ? 'selected' : ''; ?>>TLS</option>
                                    <option value="ssl" <?php echo (isset($emailSettings['smtp_encryption']) && $emailSettings['smtp_encryption'] == 'ssl') ? 'selected' : ''; ?>>SSL</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="smtp_username">SMTP Username</label>
                                <input type="text" class="form-control" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($emailSettings['smtp_username'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="smtp_password">SMTP Password (leave empty to keep current)</label>
                                <input type="password" class="form-control" id="smtp_password" name="smtp_password">
                            </div>
                            <div class="form-group">
                                <label for="smtp_from_email">From Email</label>
                                <input type="email" class="form-control" id="smtp_from_email" name="smtp_from_email" value="<?php echo htmlspecialchars($emailSettings['smtp_from_email'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="smtp_from_name">From Name</label>
                                <input type="text" class="form-control" id="smtp_from_name" name="smtp_from_name" value="<?php echo htmlspecialchars($emailSettings['smtp_from_name'] ?? 'Leave Management System'); ?>" required>
                            </div>
                            <button type="submit" name="update_email_settings" class="btn btn-primary">Save Settings</button>
                            <button type="button" onclick="testEmailSettings()" class="btn btn-info">Test Settings</button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-info-circle mr-1"></i>
                        Email Notification Information
                    </div>
                    <div class="card-body">
                        <p>This section allows you to configure the SMTP settings used for sending automated emails from the system. These emails include:</p>
                        <ul>
                            <li>Leave request notifications</li>
                            <li>Approval/rejection notifications</li>
                            <li>Leave expiry reminders</li>
                            <li>Substitute requests</li>
                        </ul>
                        <p>To use Gmail as your SMTP server, you may need to:</p>
                        <ol>
                            <li>Enable "Less secure app access" in your Google account settings</li>
                            <li>Or create an app password if you have 2-factor authentication enabled</li>
                        </ol>
                        <p>For other email providers, please check their documentation for SMTP settings.</p>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
    function testEmailSettings() {
        // Create a form data object to submit current form values
        var formData = new FormData(document.querySelector('form'));
        formData.append('test_email', 'true');
        
        // Create a temporary form and submit it in a new tab for testing
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'test_email.php';
        form.target = '_blank';
        
        for (var pair of formData.entries()) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = pair[0];
            input.value = pair[1];
            form.appendChild(input);
        }
        
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
    </script>
</body>
</html>