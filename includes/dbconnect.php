<?php
$host="localhost";
$user="root";
$pwd="";
$db="dbportal";
$conn=mysqli_connect($host,$user,$pwd,$db);
if($conn)
{
    //echo "connection to database server established<br>";
}
else {
error_log("Database connection failed: " . mysqli_connect_error());
echo "Database connection failed. Check the error log for details.";

}
?>
