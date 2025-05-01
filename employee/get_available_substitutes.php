<?php
session_start();
include "../includes/dbconnect.php";

// Check if user is logged in
if (!isset($_SESSION['UserID'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authorized']);
    exit();
}

$userID = $_SESSION['UserID'];

// Get date range from query parameters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Validate dates
if (empty($startDate) || empty($endDate)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid date range']);
    exit();
}

// Get all available employees for substitute selection 
// (excluding current user, those on leave AND those already assigned as substitutes during the requested period)
$query = "SELECT u.UserID, u.Fullnames 
          FROM users u 
          WHERE u.UserID != ? 
          AND u.hr = 0 
          AND u.admin = 0
          AND u.UserID NOT IN (
              SELECT lr.UserID 
              FROM leave_requests lr 
              WHERE lr.Status = 'Approved' 
              AND (
                  (lr.StartDate BETWEEN ? AND ?) 
                  OR (lr.EndDate BETWEEN ? AND ?)
                  OR (? BETWEEN lr.StartDate AND lr.EndDate)
                  OR (? BETWEEN lr.StartDate AND lr.EndDate)
              )
          )
          AND u.UserID NOT IN (
              SELECT ls.SubstituteID
              FROM leave_substitutes ls
              JOIN leave_requests lr ON ls.RequestID = lr.RequestID
              WHERE ls.Status = 'Accepted'
              AND lr.Status = 'Approved'
              AND (
                  (lr.StartDate BETWEEN ? AND ?) 
                  OR (lr.EndDate BETWEEN ? AND ?)
                  OR (? BETWEEN lr.StartDate AND lr.EndDate)
                  OR (? BETWEEN lr.StartDate AND lr.EndDate)
              )
          )
          ORDER BY u.Fullnames";

$stmt = $conn->prepare($query);
$stmt->bind_param("issssssssssss", 
                $userID, 
                $startDate, $endDate, $startDate, $endDate, $startDate, $endDate,
                $startDate, $endDate, $startDate, $endDate, $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

$substitutes = [];
while ($row = $result->fetch_assoc()) {
    $substitutes[] = [
        'UserID' => $row['UserID'],
        'Fullnames' => $row['Fullnames']
    ];
}

// Return substitutes as JSON
header('Content-Type: application/json');
echo json_encode($substitutes);
?>
