<?php
/**
 * Check if user has pending leave requests
 * 
 * @param int $userID The user ID to check
 * @param object $conn Database connection
 * @return bool True if pending requests exist, false otherwise
 */
function hasPendingLeaveRequests($userID, $conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM leave_requests WHERE UserID = ? AND Status = 'Pending'");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return ($row['count'] > 0);
}

/**
 * Calculate used leave days for a specific leave type
 * 
 * @param int $userID The user ID
 * @param int $leaveTypeID The leave type ID
 * @param object $conn Database connection
 * @return int Number of days used
 */
function getUsedLeaveDays($userID, $leaveTypeID, $conn) {
    $stmt = $conn->prepare("
        SELECT SUM(DATEDIFF(EndDate, StartDate) + 1) as total_days 
        FROM leave_requests 
        WHERE UserID = ? AND LeaveTypeID = ? AND (Status = 'Approved' OR Status = 'Pending')
    ");
    $stmt->bind_param("ii", $userID, $leaveTypeID);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['total_days'] ?: 0;
}

/**
 * Get maximum leave days allowed for a leave type
 * 
 * @param int $leaveTypeID The leave type ID
 * @param object $conn Database connection
 * @return int Maximum days allowed
 */
function getMaxLeaveDays($leaveTypeID, $conn) {
    $stmt = $conn->prepare("SELECT MaxDays FROM leave_types WHERE LeaveTypeID = ?");
    $stmt->bind_param("i", $leaveTypeID);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['MaxDays'];
}

/**
 * Calculate remaining leave days for a specific leave type
 * 
 * @param int $userID The user ID
 * @param int $leaveTypeID The leave type ID
 * @param object $conn Database connection
 * @return int Number of days remaining
 */
function getRemainingLeaveDays($userID, $leaveTypeID, $conn) {
    $maxDays = getMaxLeaveDays($leaveTypeID, $conn);
    $usedDays = getUsedLeaveDays($userID, $leaveTypeID, $conn);
    
    return max(0, $maxDays - $usedDays);
}

/**
 * Assign a substitute for a leave request
 * 
 * @param object $conn Database connection
 * @param int $requestID The leave request ID
 * @param int $substituteID The substitute user ID
 * @return bool True if assignment is successful, false otherwise
 */
function assignSubstitute($conn, $requestID, $substituteID) {
    $query = "INSERT INTO leave_substitutes (RequestID, SubstituteID) VALUES (?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $requestID, $substituteID);
    return mysqli_stmt_execute($stmt);
}

/**
 * Update substitute status
 * 
 * @param object $conn Database connection
 * @param int $requestID The leave request ID
 * @param int $substituteID The substitute user ID
 * @param string $status The status to update
 * @return bool True if update is successful, false otherwise
 */
function updateSubstituteStatus($conn, $requestID, $substituteID, $status) {
    $query = "UPDATE leave_substitutes SET Status = ? WHERE RequestID = ? AND SubstituteID = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sii", $status, $requestID, $substituteID);
    return mysqli_stmt_execute($stmt);
}

/**
 * Get substitute details for a leave request
 * 
 * @param object $conn Database connection
 * @param int $requestID The leave request ID
 * @return array|null Substitute details or null if not found
 */
function getSubstituteDetails($conn, $requestID) {
    $query = "SELECT ls.*, u.Fullnames, u.Email 
              FROM leave_substitutes ls
              JOIN users u ON ls.SubstituteID = u.UserID
              WHERE ls.RequestID = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $requestID);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

/**
 * Check if user is available for substitution
 * 
 * @param object $conn Database connection
 * @param int $userID The user ID
 * @param string $startDate The start date of the leave
 * @param string $endDate The end date of the leave
 * @return bool True if user is available, false otherwise
 */
function isUserAvailableForSubstitution($conn, $userID, $startDate, $endDate) {
    $query = "SELECT COUNT(*) as conflict_count 
              FROM leave_requests lr
              LEFT JOIN leave_substitutes ls ON lr.RequestID = ls.RequestID
              WHERE (lr.UserID = ? OR ls.SubstituteID = ?)
              AND lr.Status = 'Approved'
              AND (
                  (lr.StartDate BETWEEN ? AND ?) OR
                  (lr.EndDate BETWEEN ? AND ?) OR
                  (? BETWEEN lr.StartDate AND lr.EndDate)
              )";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "iissss", $userID, $userID, $startDate, $endDate, $startDate, $endDate, $startDate);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    return $row['conflict_count'] == 0;
}

/**
 * Get all substitution requests for a user
 * 
 * @param object $conn Database connection
 * @param int $userID The user ID
 * @return mysqli_result Result set containing substitution requests
 */
function getSubstitutionRequests($conn, $userID) {
    $query = "SELECT ls.*, lr.StartDate, lr.EndDate, lr.Reason,
                     u.Fullnames as RequesterName, lt.LeaveName,
                     DATEDIFF(lr.EndDate, lr.StartDate) + 1 as Days
              FROM leave_substitutes ls
              JOIN leave_requests lr ON ls.RequestID = lr.RequestID
              JOIN users u ON lr.UserID = u.UserID
              JOIN leave_types lt ON lr.LeaveTypeID = lt.LeaveTypeID
              WHERE ls.SubstituteID = ?
              ORDER BY ls.DateAssigned DESC";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $userID);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

// Include the auto scheduler at the end of the file
// This will allow background tasks to run automatically
require_once __DIR__ . '/auto_scheduler.php';

/**
 * Debug function to log SQL errors to a file
 */
function logSqlError($conn, $message) {
    $logDir = __DIR__ . '/../logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/sql_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $errorMsg = "[$timestamp] $message - SQL Error: " . mysqli_error($conn) . "\n";
    file_put_contents($logFile, $errorMsg, FILE_APPEND);
}
?>
