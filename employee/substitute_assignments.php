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
$message = '';
$error = '';

// Fix the query to match the actual table structure
// First, check if the table exists and has the expected structure
$tableCheckQuery = "SHOW TABLES LIKE 'leave_substitutes'";
$tableExists = $conn->query($tableCheckQuery)->num_rows > 0;

if ($tableExists) {
    // Check the structure of the table
    $columnCheckQuery = "SHOW COLUMNS FROM leave_substitutes LIKE 'UserID'";
    $userIdColumnExists = $conn->query($columnCheckQuery)->num_rows > 0;
    
    if ($userIdColumnExists) {
        // Use the original query with ls.UserID
        $query = "SELECT ls.ID, ls.RequestID, ls.Status, 
                u.Fullnames AS EmployeeName, lt.LeaveName,
                lr.StartDate, lr.EndDate, lr.Status AS LeaveStatus,
                DATEDIFF(lr.EndDate, lr.StartDate) + 1 AS Days
                FROM leave_substitutes ls
                JOIN leave_requests lr ON ls.RequestID = lr.RequestID
                JOIN users u ON ls.UserID = u.UserID
                JOIN leave_types lt ON lr.LeaveTypeID = lt.LeaveTypeID
                WHERE ls.SubstituteID = ? 
                ORDER BY lr.StartDate DESC";
    } else {
        // Use lr.UserID instead of ls.UserID if the column doesn't exist
        $query = "SELECT ls.ID, ls.RequestID, ls.Status, 
                u.Fullnames AS EmployeeName, lt.LeaveName,
                lr.StartDate, lr.EndDate, lr.Status AS LeaveStatus,
                DATEDIFF(lr.EndDate, lr.StartDate) + 1 AS Days
                FROM leave_substitutes ls
                JOIN leave_requests lr ON ls.RequestID = lr.RequestID
                JOIN users u ON lr.UserID = u.UserID
                JOIN leave_types lt ON lr.LeaveTypeID = lt.LeaveTypeID
                WHERE ls.SubstituteID = ? 
                ORDER BY lr.StartDate DESC";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Create the table if it doesn't exist
    $createTableQuery = "CREATE TABLE IF NOT EXISTS leave_substitutes (
        ID INT(11) AUTO_INCREMENT PRIMARY KEY,
        RequestID INT(11) NOT NULL,
        UserID INT(11) NOT NULL,
        SubstituteID INT(11) NOT NULL,
        Status ENUM('Pending', 'Accepted', 'Rejected') DEFAULT 'Pending',
        NotificationSent BOOLEAN DEFAULT FALSE,
        DateAssigned TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $conn->query($createTableQuery);
    $result = false; // No results yet as table was just created
}

// Handle accepting/declining substitution
if (isset($_POST['update_status'])) {
    $substituteID = $_POST['substitute_id'];
    $requestID = $_POST['request_id'];
    $status = $_POST['status']; // 'Accepted' or 'Rejected'
    
    // Update status in database
    $updateStmt = $conn->prepare("UPDATE leave_substitutes SET Status = ? WHERE ID = ? AND SubstituteID = ?");
    $updateStmt->bind_param("sii", $status, $substituteID, $userID);
    
    if ($updateStmt->execute()) {
        // Get the UserID from leave_requests if leave_substitutes.UserID might not exist
        $userIdQuery = $conn->prepare("
            SELECT lr.UserID, u.Fullnames AS EmployeeName, u2.Fullnames AS SubstituteName 
            FROM leave_substitutes ls
            JOIN leave_requests lr ON ls.RequestID = lr.RequestID 
            JOIN users u ON lr.UserID = u.UserID
            JOIN users u2 ON ls.SubstituteID = u2.UserID
            WHERE ls.ID = ?");
        $userIdQuery->bind_param("i", $substituteID);
        $userIdQuery->execute();
        $userData = $userIdQuery->get_result()->fetch_assoc();
        
        if ($userData) {
            // Create notification for the leave requester
            $notifyMessage = "Your substitute request to {$userData['SubstituteName']} has been " . strtolower($status) . ".";
            $notifyStmt = $conn->prepare("INSERT INTO notifications (UserID, Message, Type, IsRead) VALUES (?, ?, 'substitute_response', 0)");
            $notifyStmt->bind_param("is", $userData['UserID'], $notifyMessage);
            $notifyStmt->execute();
        }
        
        $message = "You have " . strtolower($status) . " the substitution request.";
    } else {
        $error = "Failed to update substitution status: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Substitute Assignments</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .assignment-card {
            background-color: #ffffff;
            border-radius: 8px;
            margin-bottom: 20px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .assignment-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 10px;
        }
        
        .assignment-title {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .assignment-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
            margin-right: 5px;
        }
        
        .status-pending {
            background-color: #ffeaa7;
            color: #f39c12;
        }
        
        .status-accepted {
            background-color: #d4edda;
            color: #2ecc71;
        }
        
        .status-rejected {
            background-color: #f8d7da;
            color: #e74c3c;
        }
        
        .status-leave-approved {
            background-color: #d1ecf1;
            color: #17a2b8;
        }
        
        .assignment-details {
            margin-bottom: 15px;
        }
        
        .assignment-details p {
            margin-bottom: 8px;
        }
        
        .assignment-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 14px;
        }
        
        .no-assignments {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            color: #6c757d;
            margin-top: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Sidebar styles */
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 0;
            box-shadow: 0 0 10px rgba(0,0,0,.1);
        }
        
        .sidebar-sticky {
            position: sticky;
            top: 0;
            height: calc(100vh);
            padding-top: 1rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        .sidebar .nav-link {
            font-weight: 500;
            color: rgba(255, 255, 255, 0.75);
            padding: 0.75rem 1rem;
        }
        
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255,255,255,.1);
        }
        
        .sidebar-header {
            padding: 1rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,.1);
        }
        
        .sidebar-header h3 {
            color: white;
            margin: 0;
            font-size: 1.5rem;
        }
        
        /* Make sure content is properly offset from sidebar */
        @media (min-width: 768px) {
            .ml-sm-auto {
                margin-left: auto !important;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-none d-md-block bg-dark sidebar">
                <div class="sidebar-sticky">
                    <div class="sidebar-header">
                        <h3>Leave Portal</h3>
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
                            <a class="nav-link active" href="substitute_assignments.php">
                                <i class="fas fa-user-friends"></i> Substitute Assignments
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
                    <h1 class="h2">My Substitute Assignments</h1>
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
                
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($assignment = $result->fetch_assoc()): ?>
                        <div class="assignment-card">
                            <div class="assignment-header">
                                <div class="assignment-title">
                                    <?php echo htmlspecialchars($assignment['EmployeeName']); ?>'s <?php echo htmlspecialchars($assignment['LeaveName']); ?> Leave
                                </div>
                                <div class="assignment-status-container">
                                    <span class="assignment-status status-<?php echo strtolower($assignment['Status'] ?: 'pending'); ?>">
                                        <?php echo $assignment['Status'] ?: 'Pending'; ?>
                                    </span>
                                    <span class="assignment-status status-leave-<?php echo strtolower($assignment['LeaveStatus']); ?>">
                                        Leave: <?php echo $assignment['LeaveStatus']; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="assignment-details">
                                <p><strong>Period:</strong> <?php echo date('d-M-Y', strtotime($assignment['StartDate'])); ?> to <?php echo date('d-M-Y', strtotime($assignment['EndDate'])); ?> (<?php echo $assignment['Days']; ?> days)</p>
                                <p><strong>Resume Duty Date:</strong> <?php echo date('d-M-Y', strtotime('+1 day', strtotime($assignment['EndDate']))); ?></p>
                                <p><strong>Status:</strong> 
                                    <?php if ($assignment['Status'] == 'Pending' || empty($assignment['Status'])): ?>
                                        You have not yet responded to this substitution request.
                                    <?php elseif ($assignment['Status'] == 'Accepted'): ?>
                                        You have agreed to substitute for <?php echo htmlspecialchars($assignment['EmployeeName']); ?>.
                                    <?php elseif ($assignment['Status'] == 'Rejected'): ?>
                                        You have declined to substitute for <?php echo htmlspecialchars($assignment['EmployeeName']); ?>.
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <?php if ($assignment['Status'] == 'Pending' || empty($assignment['Status'])): ?>
                                <div class="assignment-actions">
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="substitute_id" value="<?php echo $assignment['ID']; ?>">
                                        <input type="hidden" name="request_id" value="<?php echo $assignment['RequestID']; ?>">
                                        <input type="hidden" name="status" value="Accepted">
                                        <button type="submit" name="update_status" class="btn btn-success btn-small">Accept</button>
                                    </form>
                                    
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="substitute_id" value="<?php echo $assignment['ID']; ?>">
                                        <input type="hidden" name="request_id" value="<?php echo $assignment['RequestID']; ?>">
                                        <input type="hidden" name="status" value="Rejected">
                                        <button type="submit" name="update_status" class="btn btn-danger btn-small">Decline</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-assignments">
                        <i class="fas fa-info-circle fa-3x mb-3"></i>
                        <h3>No Substitute Assignments</h3>
                        <p>You have not been assigned as a substitute for any leave requests.</p>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <!-- JavaScript dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
