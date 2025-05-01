
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>About Us - E-Leave Portal</title>
<style>
body {
font-family: Arial, sans-serif;
margin: 0;
background-image: url('images/leave_online.jpg');

background-size: cover;
background-position: center;
min-height: 100vh;
display: flex;
flex-direction: column;
}

header {
display: flex;
align-items: center;
justify-content: space-between;
padding: 10px 20px;
background-color: rgba(0, 0, 0, 0.7);
}

.logo {
display: flex;
align-items: center;
}

.logo img {
width: 80px;
height: auto;
margin-right: 10px;
}

.header-name h1 {
font-size: 32px;
color: white;
font-family: 'Inknut Antiqua', serif;
margin: 0;
}

.login-signup {
display: flex;
gap: 10px;
}

.login-signup a {
background-color: #031C01;
text-decoration: none;
padding: 10px 20px;
color: #D8FEFC;
border-radius: 5px;
font-size: 18px;
transition: background-color 0.3s, color 0.3s;
}

.login-signup a:hover {
background-color: rgba(255, 255, 255, 0.2);
color: yellow;
}

nav {
display: flex;
justify-content: right;
padding: 10px;
}

nav a {
text-decoration: none;
padding: 12px 25px;
color: #D8FEFC;
border-radius: 5px;
margin-left: 15px;
font-size: 20px;
transition: background-color 0.3s, color 0.3s;
}

nav a:hover {
background-color: rgba(255, 255, 255, 0.2);
color: yellow;
}

/* Main Content */
.container {
    background: rgba(0, 0, 0, 0.7);
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
    width: 80%;
    max-width: 800px;
    margin: 120px auto 60px;
    position: relative;
}   



h1 {
color: yellow;
font-family: "Inknut Antiqua", serif;
font-size: 26px;
text-align: center;
margin-top: 20px;
}

.about-content {
color: white;
font-size: 18px;
line-height: 1.6;
text-align: justify;
}

footer {
    background-color: rgba(0, 0, 0, 0.5);
    color: #fff;
    text-align: center;
    padding: 20px;
    width: 100%;
    position: relative;
    margin-top: 40px;
}


</style>
</head>
<body>

<header>
<div class="logo">
<img src="images/logo.png" alt="NPSC Logo">

<div class="header-name">
<h1>
<?php
echo "<font color='white'><i>NPSC </i></font>";
echo "<font color='yellow'><i>E-Leave</i></font>";
echo "<br>";
echo "<font color='white'><i>Portal</i></font>";
?>
</h1>
</div>
</div>

<nav>
<a href="index.php">Home</a>
<a href="aboutus.php">About</a>
<a href="contactus.php">Contact</a>
</nav>

<div class="login-signup">
<a href="login.php">Login</a>
<a href="register.php">Sign Up</a>
</div>
</header>

<!-- Main Content -->
<div class="container">
<h1>About NPSC E-Leave Portal</h1>
<div class="about-content">
<p>The NPSC E-Leave Portal is a state-of-the-art digital solution designed to streamline and modernize the leave management process for our organization. Our mission is to provide an efficient, transparent, and user-friendly platform for managing employee leave requests.</p>
<p>Key features of our portal include:</p>
<ul>
<li>Easy leave application and tracking</li>
<li>Real-time leave status updates</li>
<li>Comprehensive leave history records</li>
<li>Secure and reliable platform</li>
</ul>
<p>Our team is committed to continuously improving the portal to better serve our employees and maintain operational efficiency. We believe in leveraging technology to create a more productive and balanced work environment.</p>
</div>
</div>

<footer>
<p>&copy; 2025 NPSC Leave Portal. All rights reserved.</p>
</footer>
</body>
</html>
