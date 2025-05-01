<?php
session_start();
include "../includes/dbconnect.php";
include "../includes/functions.php";

// Check if user is logged in
if (!isset($_SESSION['UserID'])) {
    header("Location: ../login.php");
    exit();
}

$userID = $_SESSION['UserID'];
$username = $_SESSION['Username'];
$message = "";
$error = "";

// Handle accept/reject actions
if (isset($_POST['action']) && isset($_POST['request_id'])) {
    $requestID = intval($_POST['request_id']);
    $substituteID = $userID;
    $action = $_POST['action'];
    
    // Update substitute status
    $status = ($action === 'accept') ? 'Accepted' : 'Rejected';
    
    try {
        if (updateSubstituteStatus($conn, $requestID, $substituteID, $status)) {
            $message = "You have " . strtolower($status) . " the substitution request.";
        } else {
            $error = "Error updating request: " . mysqli_error($conn);
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get all substitution requests for this user
$requests = getSubstitutionRequests($conn, $userID);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Substitute Requests - Leave Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/employee_dashboard.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-none d-md-block bg-light sidebar">
                <div class="sidebar-sticky">
                    <div class="sidebar-header">
                        <h3>Employee Portal</h3>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="apply_leave.php">
                                <i class="fas fa-calendar-plus"></i> Apply for Leave
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="leave_history.php">
                                <i class="fas fa-history"></i> Leave History
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="substitute_requests.php">
                                <i class="fas fa-user-friends"></i> Substitute Requests
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="leave_reports.php">
                                <i class="fas fa-chart-bar"></i> Leave Reports
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
                    <h1 class="h2">Substitute Requests</h1>
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
                    <div class="card-body">
                        <h5 class="card-title mb-3">Requests for you to substitute</h5>
                        
                        <?php if (mysqli_num_rows($requests) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Days</th>
                                        <th>Requested On</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($requests)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['RequesterName']); ?></td>
                                        <td><?php echo htmlspecialchars($row['LeaveName']); ?></td>
                                        <td><?php echo date('d-M-Y', strtotime($row['StartDate'])); ?></td>
                                        <td><?php echo date('d-M-Y', strtotime($row['EndDate'])); ?></td>
                                        <td><?php echo $row['Days']; ?></td>
                                        <td><?php echo date('d-M-Y', strtotime($row['DateAssigned'])); ?></td>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo $row['Status'] == 'Accepted' ? 'success' : 
                                                    ($row['Status'] == 'Rejected' ? 'danger' : 'warning'); 
                                            ?>">
                                                <?php echo $row['Status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info view-btn" 
                                                    data-toggle="modal" 
                                                    data-target="#viewRequestModal"
                                                    data-employee="<?php echo htmlspecialchars($row['RequesterName']); ?>"
                                                    data-type="<?php echo htmlspecialchars($row['LeaveName']); ?>"
                                                    data-start="<?php echo date('d-M-Y', strtotime($row['StartDate'])); ?>"
                                                    data-end="<?php echo date('d-M-Y', strtotime($row['EndDate'])); ?>"
                                                    data-days="<?php echo $row['Days']; ?>"
                                                    data-reason="<?php echo htmlspecialchars($row['Reason']); ?>"
                                                    data-status="<?php echo $row['Status']; ?>"
                                                    data-id="<?php echo $row['RequestID']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if ($row['Status'] == 'Pending'): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="request_id" value="<?php echo $row['RequestID']; ?>">
                                                <button type="submit" name="action" value="accept" class="btn btn-sm btn-success">
                                                    <i class="fas fa-check"></i> Accept
                                                </button>
                                                <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php 
                                        endwhile;
                                    else:
                                    ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No substitute requests found.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Guidelines Card -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Substitute Guidelines</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li><i class="fas fa-info-circle text-info mr-2"></i> When you accept a request, you agree to cover for the employee during their leave period.</li>
                            <li><i class="fas fa-tasks text-primary mr-2"></i> You will be responsible for their duties during their absence.</li>
                            <li><i class="fas fa-exclamation-circle text-warning mr-2"></i> Please respond to requests promptly to help with leave planning.</li>
                            <li><i class="fas fa-clock text-success mr-2"></i> You can only be a substitute if you are not on leave during the requested period.</li>
                        </ul>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>