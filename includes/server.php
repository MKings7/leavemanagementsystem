<?php
session_start();
require_once 'dbconnect.php';

/* ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); */

// Initialize logging
$logFile = 'login_logs.txt';
$logMessage = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = md5($_POST['password']); // Using MD5 to match existing password hashing
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    //$timestamp = date('Y-m-d H:i:s');

    date_default_timezone_set('Africa/Nairobi'); // Set timezone to Kenya
$timestamp = date('Y-m-d H:i:s');

    // Validate input
    if (empty($username) || empty($password)) {
       $logMessage = "[$timestamp] Failed login attempt - Empty fields - IP: $ipAddress\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        die("Username and password are required");
    }

    // Prepare and execute query
    $stmt = $conn->prepare("SELECT UserID, Username, admin, hr FROM users WHERE Username = ? AND Password = ?");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $_SESSION['UserID'] = $user['UserID'];
        $_SESSION['Username'] = $user['Username'];
        $_SESSION['admin'] = $user['admin'];
        $_SESSION['hr'] = $user['hr']; // Store HR status in session
        
     //Log successful login
     $logMessage = "[$timestamp] Successful login - UserID: {$user['UserID']} - IP: $ipAddress\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
      
        // Redirect based on role
        if($user['hr'] == 1) {
            header("Location: hr/dashboard.php");
            exit();
        } elseif ($user['admin'] == 1) {
            header("Location: admin/dashboard.php");  
            exit();
        } else {
            header("Location: employee/dashboard.php");
            exit();
        }

    } else {
        // Log failed login attempt
        $logMessage = "[$timestamp] Failed login attempt - Invalid credentials - Username: $username - IP: $ipAddress\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        
        die("Invalid username or password");
    }
    
   

}
?>
