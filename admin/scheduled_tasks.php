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

// Handle task updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_task') {
        $taskId = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $frequencyMinutes = isset($_POST['frequency_minutes']) ? intval($_POST['frequency_minutes']) : 1440;
        $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
        
        if ($taskId > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE scheduled_tasks SET frequency_minutes = ?, is_enabled = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "iii", $frequencyMinutes, $isEnabled, $taskId);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "Task updated successfully";
            } else {
                $error = "Error updating task: " . mysqli_error($conn);
            }
        }
    } elseif ($_POST['action'] === 'run_now') {
        $taskId = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        
        if ($taskId > 0) {
            // Get task details
            $taskQuery = mysqli_query($conn, "SELECT task_name FROM scheduled_tasks WHERE id = $taskId");
            $task = mysqli_fetch_assoc($taskQuery);
            
            if ($task) {
                require_once "../includes/auto_scheduler.php";
                $scheduler = new AutoScheduler($conn);
                $scheduler->checkAndRunTasks();
                $message = "Task '" . htmlspecialchars($task['task_name']) . "' has been triggered manually";
            } else {
                $error = "Task not found";
            }
        }
    }
}

// Get all scheduled tasks
$tasksQuery = "SELECT * FROM scheduled_tasks ORDER BY task_name";
$tasksResult = mysqli_query($conn, $tasksQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scheduled Tasks - Leave Management System</title>
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
                            <a class="nav-link active" href="scheduled_tasks.php">
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
                    <h1 class="h2">Scheduled Tasks</h1>
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

                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-clock mr-1"></i>
                        Automated Tasks
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> These tasks run automatically in the background when users visit the site. No server-level cron job configuration is required.
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Task Name</th>
                                        <th>Description</th>
                                        <th>Frequency</th>
                                        <th>Last Run</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($tasksResult) > 0): ?>
                                        <?php while ($task = mysqli_fetch_assoc($tasksResult)): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($task['task_name']); ?></td>
                                                <td>
                                                    <?php 
                                                    switch ($task['task_name']) {
                                                        case 'leave_expiry_notification':
                                                            echo 'Sends notifications to employees whose leave is about to expire';
                                                            break;
                                                        default:
                                                            echo 'System task';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <form method="post" class="form-inline">
                                                        <input type="hidden" name="action" value="update_task">
                                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                        <div class="form-group mb-2">
                                                            <select name="frequency_minutes" class="form-control form-control-sm">
                                                                <option value="60" <?php echo $task['frequency_minutes'] == 60 ? 'selected' : ''; ?>>Hourly</option>
                                                                <option value="720" <?php echo $task['frequency_minutes'] == 720 ? 'selected' : ''; ?>>Every 12 hours</option>
                                                                <option value="1440" <?php echo $task['frequency_minutes'] == 1440 ? 'selected' : ''; ?>>Daily</option>
                                                                <option value="10080" <?php echo $task['frequency_minutes'] == 10080 ? 'selected' : ''; ?>>Weekly</option>
                                                            </select>
                                                        </div>
                                                </td>
                                                <td>
                                                    <?php echo $task['last_run'] ? date('d-M-Y H:i', strtotime($task['last_run'])) : 'Never'; ?>
                                                </td>
                                                <td>
                                                    <div class="custom-control custom-switch">
                                                        <input type="checkbox" class="custom-control-input" id="task_enabled_<?php echo $task['id']; ?>" name="is_enabled" value="1" <?php echo $task['is_enabled'] ? 'checked' : ''; ?>>
                                                        <label class="custom-control-label" for="task_enabled_<?php echo $task['id']; ?>">
                                                            <?php echo $task['is_enabled'] ? 'Enabled' : 'Disabled'; ?>
                                                        </label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <button type="submit" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-save"></i> Save
                                                    </button>
                                                    </form>
                                                    
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="action" value="run_now">
                                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-warning">
                                                            <i class="fas fa-play"></i> Run Now
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No scheduled tasks found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-file-alt mr-1"></i>
                        Task Logs
                    </div>
                    <div class="card-body">
                        <?php
                        $logFile = '../logs/auto_scheduler.log';
                        if (file_exists($logFile)) {
                            $logs = file_get_contents($logFile);
                            $logLines = array_slice(array_filter(explode("\n", $logs)), -20);
                            echo '<div class="log-container bg-dark text-light p-3" style="max-height: 300px; overflow-y: auto; font-family: monospace;">';
                            foreach ($logLines as $line) {
                                echo htmlspecialchars($line) . '<br>';
                            }
                            echo '</div>';
                        } else {
                            echo '<div class="alert alert-warning">No log file found.</div>';
                        }
                        ?>
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
        // Update switch label on toggle
        $('.custom-control-input').change(function() {
            var label = $(this).next('label');
            if (this.checked) {
                label.text('Enabled');
            } else {
                label.text('Disabled');
            }
        });
    });
    </script>
</body>
</html>
