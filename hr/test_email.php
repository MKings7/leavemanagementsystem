<?php
session_start();
include "../includes/dbconnect.php";

// Check if user is logged in and is an HR admin
if (!isset($_SESSION['UserID']) || !isset($_SESSION['hr']) || $_SESSION['hr'] != 1) {
    header("Location: ../login.php?error=unauthorized");
    exit();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Use Composer autoloader
require_once '../vendor/autoload.php';

$success = false;
$error = '';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Enable verbose debug output
        $mail->isSMTP();
        $mail->Host = $_POST['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $_POST['smtp_username'];
        $mail->Password = $_POST['smtp_password'] ?: getExistingPassword($conn);
        $mail->SMTPSecure = $_POST['smtp_encryption'];
        $mail->Port = $_POST['smtp_port'];
        
        // Recipients
        $mail->setFrom($_POST['smtp_from_email'], $_POST['smtp_from_name']);
        $mail->addAddress($_SESSION['EmailAddress'] ?? 'comp.cslab@gmail.com');
        
        // Content
        $mail->isHTML(false);
        $mail->Subject = 'Test Email from Leave Management System';
        $mail->Body = "This is a test email from your Leave Management System.\n\n"
                    . "If you received this email, your email settings are configured correctly.";
        
        $mail->send();
        $success = true;
    } catch (Exception $e) {
        $error = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}

// Helper function to get existing password
function getExistingPassword($conn) {
    $query = "SELECT smtp_password FROM email_settings LIMIT 1";
    $result = mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result)['smtp_password'];
    }
    return '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Test Results</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        h1 {
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .back {
            display: inline-block;
            margin-top: 20px;
            padding: 8px 15px;
            background-color: #3498db;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
        }
        .debug {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            padding: 15px;
            margin-top: 20px;
            font-family: monospace;
            white-space: pre-wrap;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Email Test Results</h1>
        
        <?php if ($success): ?>
            <div class="success">
                <strong>Success!</strong> The test email was sent successfully.
                <p>Please check your inbox to confirm receipt.</p>
            </div>
        <?php elseif ($error): ?>
            <div class="error">
                <strong>Error!</strong> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="debug">
            <?php
            if (isset($mail) && $mail->SMTPDebug) {
                echo "SMTP Debug Information will appear here...";
            }
            ?>
        </div>
        
        <a href="settings.php" class="back">Back to Settings</a>
    </div>
</body>
</html>
