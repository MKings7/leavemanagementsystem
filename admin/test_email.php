<?php
session_start();
require_once '../includes/dbconnect.php';
require_once '../includes/email_functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['UserID']) || $_SESSION['admin'] != 1) {
    die("Unauthorized access");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $testEmail = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);
    
    if (!$testEmail) {
        die("Invalid email address");
    }
    
    try {
        $settings = getEmailSettings($conn);
        if (!$settings) {
            die("Email settings not configured");
        }
        
        $mail = setupMailer($settings);
        $mail->addAddress($testEmail);
        $mail->Subject = 'Test Email from Leave Management System';
        $mail->Body = "This is a test email from your Leave Management System.\n\n" .
                     "If you received this email, your SMTP configuration is working correctly.\n\n" .
                     "Best regards,\n" .
                     $settings['smtp_from_name'];
        
        $mail->send();
        echo "Test email sent successfully!";
    } catch (Exception $e) {
        echo "Error sending test email: " . $e->getMessage();
    }
} else {
    echo "Invalid request";
}