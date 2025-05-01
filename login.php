<?php
#include('incUseChrome.php');
include('includes/server.php')
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E leave portal </title>
    <link rel="stylesheet" href="css/style1.css">
</head>
<body>

<br>

<p><b>Kindly Log in</b></p>

<form method="post" action="" name="loginform">

    <label><b>Username:</b></label><br>
    <input type="text" name="username"><br>

    <label><b>Password:</b></label><br>
    <input type="password" name="password"><br>

    <input type="submit" name="login" value="Login">
    <input type="reset" name="reset" value="Reset" />

    <p><b><i>Don't have an account? <a href="register.php">Click here to register</a>.</i></b></p>
</form>

</body>
</html>
