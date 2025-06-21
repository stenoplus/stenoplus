<?php
session_start();
require '../config.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "User not logged in"]);
    exit;
}

$user_id = $_SESSION['user_id'];
$sql = "SELECT name, email, mobile, dob, gender, city, course, photo FROM users WHERE id='$user_id'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo json_encode(array_merge(["success" => true], $result->fetch_assoc()));
} else {
    echo json_encode(["success" => false, "message" => "User not found"]);
}

$conn->close();
?>
