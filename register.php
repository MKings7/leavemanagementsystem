<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Registration Form  </title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background-image: url('leave online.jpg');
            background-size: cover;
            background-position: center;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding-bottom: 20px;
        }

        h1 {
            color: yellow;
            font-family: "Inknut Antiqua", serif;
            font-size: 32px;
            margin: 20px 0;
        }

        h2, h3 {
            color: white;
            font-family: "Inknut Antiqua", serif;
            font-size: 26px;
            margin: 10px 0;
            text-align: center;
        }

        form {
            max-width: 400px;
            width: 90%;
            margin: 20px auto;
            background: rgba(0, 0, 0, 0.7);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        form:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.7);
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            background: rgba(255, 255, 255, 0.9);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: yellow;
            box-shadow: 0 0 5px rgba(255, 255, 0, 0.5);
            outline: none;
        }

        input[type="submit"] {
            width: 100%;
            background-color: #031C01;
            color: #D8FEFC;
            padding: 14px 20px;
            margin: 8px 0;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        input[type="submit"]:hover {
            background-color: rgba(255, 255, 255, 0.2);
            color: yellow;
            transform: scale(1.02);
        }
        input[type="submit"]:active {
            transform: scale(0.98);
        }

        label {
            font-weight: bold;
            color: white;
            display: block;
            margin: 10px 0 5px;
        }

        p {
            text-align: center;
            color: white;
            font-size: 18px;
            margin: 10px 0;
        }
        a {
            color: yellow;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
        }
        a:hover {
            color: #D8FEFC;
            text-decoration: underline;
            transform: translateX(5px);
        }
        a::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 2px;
            bottom: -2px;
            left: 0;
            background-color: #D8FEFC;
            transform: scaleX(0);
            transform-origin: right;
            transition: transform 0.3s ease;
        }
        a:hover::after {
            transform: scaleX(1);
            transform-origin: left;
        }

    </style>
</head>
<body>

<?php  
echo "<h1>Employee Registration Form  </h1>";
?>

<?php
include "includes/dbconnect.php"; // Assuming this file contains code to establish a database connection


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Receive user details from the form
    $fullnames = $_POST['fullnames'];
    $username = $_POST['username'];
    $password = md5($_POST['password']);
    $email = $_POST['email'];

    // Check if the username already exists
    $checkusername = mysqli_query($conn, "SELECT * FROM users WHERE Username = '".$username."'");


    // If username already exists, display an error message
    if(mysqli_num_rows($checkusername) > 0) {
        echo "<h3>Error</h3>";
        echo "<p>Sorry, Account Creation Failed. Please Retry.</p>";
        echo "<a href='users.php'>  Retry Registration</a>";
    } else {
        // Insert user details into the table 'users'
    $registerquery = mysqli_query($conn, "INSERT INTO users (Fullnames, Username, Password, EmailAddress, admin) VALUES ('$fullnames', '$username', '$password', '$email', 0)");



        if ($registerquery) {
            // If the user details were inserted successfully, display a success message
            echo "<h3>Success</h3>";
            echo "<p>Your user account was successfully created. </p>";
            echo "<a href='login.php'>Proceed to Login</a>";
        } else {
            // If there was an error during registration, display an error message
            echo "<h1>Error</h1>";
            echo "<p>Sorry, your registration failed. Please go back and try again.</p>";
        }
    }
}
?>

<h3>Please enter your details below to register.</h3>

<form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" name="registerform">
    <label>Full Names:</label><br>
    <input type="text" name="fullnames" required><br>

    <label>Username:</label><br>
    <input type="text" name="username" required><br>

    <label>Password:</label><br>
    <input type="password" name="password" required><br>

    <label>Email Address:</label><br>
    <input type="text" name="email" required><br><br>

    <input type="submit" name="register" value="Register" /><br><br>

    <p>Back to <a href="index.php">Home</a></p>
</form>

</body>
</html>
