<?php
session_start();
require '../config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["message" => "User not logged in"]);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $mobile = $_POST['mobile'];
    $dob = $_POST['dob'];
    $gender = $_POST['gender'];
    $city = $_POST['city'];
    $course = $_POST['course'];

    // Handle Profile Picture Upload
    if (!empty($_FILES['photo']['name'])) {
        $photo = $_FILES['photo'];
        $photoPath = "uploads/" . basename($photo["name"]);
        move_uploaded_file($photo["tmp_name"], $photoPath);

        $update_photo = ", photo='$photoPath'";
    } else {
        $update_photo = "";
    }

    // Update user details
    $sql = "UPDATE users SET name='$name', email='$email', mobile='$mobile', dob='$dob', gender='$gender', city='$city', course='$course' $update_photo WHERE id='$user_id'";

    if ($conn->query($sql) === TRUE) {
        echo json_encode(["message" => "Profile updated successfully!"]);
    } else {
        echo json_encode(["message" => "Error updating profile: " . $conn->error]);
    }
}

$conn->close();
?>
