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

// Handle form submission
if (isset($_POST['update_settings'])) {
    $host = mysqli_real_escape_string($conn, $_POST['smtp_host']);
    $port = intval($_POST['smtp_port']);
    $username = mysqli_real_escape_string($conn, $_POST['smtp_username']);
    $password = mysqli_real_escape_string($conn, $_POST['smtp_password']);
    $from_email = mysqli_real_escape_string($conn, $_POST['smtp_from_email']);
    $from_name = mysqli_real_escape_string($conn, $_POST['smtp_from_name']);
    $encryption = mysqli_real_escape_string($conn, $_POST['smtp_encryption']);
    
    // Check if settings exist
    $checkQuery = "SELECT COUNT(*) as count FROM email_settings";
    $result = mysqli_query($conn, $checkQuery);
    $settingsExist = mysqli_fetch_assoc($result)['count'] > 0;
    
    if ($settingsExist) {
        // Update existing settings
        $query = "UPDATE email_settings SET 
                 smtp_host = ?, 
                 smtp_port = ?, 
                 smtp_username = ?, 
                 smtp_password = ?, 
                 smtp_from_email = ?, 
                 smtp_from_name = ?, 
                 smtp_encryption = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "sissss", $host, $port, $username, $password, $from_email, $from_name, $encryption);
    } else {
        // Insert new settings
        $query = "INSERT INTO email_settings 
                 (smtp_host, smtp_port, smtp_username, smtp_password, smtp_from_email, smtp_from_name, smtp_encryption) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "sissss", $host, $port, $username, $password, $from_email, $from_name, $encryption);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        $message = "Email settings updated successfully.";
    } else {
        $error = "Error updating email settings: " . mysqli_error($conn);
    }
}

// Get current settings
$query = "SELECT * FROM email_settings LIMIT 1";
$result = mysqli_query($conn, $query);
$settings = mysqli_fetch_assoc($result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Settings - Leave Management System</title>
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
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="test_email.php" class="btn btn-sm btn-outline-secondary mr-2">
                            <i class="fas fa-paper-plane"></i> Test Email
                        </a>
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

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">SMTP Configuration</h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="smtp_host">SMTP Host</label>
                                        <input type="text" class="form-control" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>" required>
                                        <small class="form-text text-muted">e.g., smtp.gmail.com</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="smtp_port">SMTP Port</label>
                                        <input type="number" class="form-control" id="smtp_port" name="smtp_port" value="<?php echo $settings['smtp_port'] ?? 587; ?>" required>
                                        <small class="form-text text-muted">Common ports: 25, 465, 587</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="smtp_encryption">Encryption</label>
                                        <select class="form-control" id="smtp_encryption" name="smtp_encryption">
                                            <option value="tls" <?php echo (isset($settings['smtp_encryption']) && $settings['smtp_encryption'] == 'tls') ? 'selected' : ''; ?>>TLS</option>
                                            <option value="ssl" <?php echo (isset($settings['smtp_encryption']) && $settings['smtp_encryption'] == 'ssl') ? 'selected' : ''; ?>>SSL</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="smtp_username">SMTP Username</label>
                                        <input type="text" class="form-control" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>" required>
                                        <small class="form-text text-muted">Usually your email address</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="smtp_password">SMTP Password</label>
                                        <input type="password" class="form-control" id="smtp_password" name="smtp_password" value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>" required>
                                        <small class="form-text text-muted">For Gmail, use App Password instead of regular password</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="smtp_from_email">From Email</label>
                                        <input type="email" class="form-control" id="smtp_from_email" name="smtp_from_email" value="<?php echo htmlspecialchars($settings['smtp_from_email'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="smtp_from_name">From Name</label>
                                        <input type="text" class="form-control" id="smtp_from_name" name="smtp_from_name" value="<?php echo htmlspecialchars($settings['smtp_from_name'] ?? 'Leave Management System'); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Email notifications are used for leave request status updates and other system notifications.
                            </div>
                            <button type="submit" name="update_settings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Settings
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Email Templates</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> Future Enhancement: Email template customization will be available in a future update.
                        </div>
                        <p>The system currently uses the following email templates:</p>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Leave Request Notification
                                <span class="badge badge-primary badge-pill">System Default</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Leave Approval
                                <span class="badge badge-primary badge-pill">System Default</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Leave Rejection
                                <span class="badge badge-primary badge-pill">System Default</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Substitute Request
                                <span class="badge badge-primary badge-pill">System Default</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>