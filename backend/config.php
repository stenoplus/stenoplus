<?php
// Local Connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "stenoplus";

// Server Connection
// $servername = "localhost";
// $username = "u762019159_ustenoplus";
// $password = "v9[ZVNB9~S";
// $dbname = "u762019159_dstenoplus";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

