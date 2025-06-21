<?php
session_start();
include '../config.php'; // Ensure database connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullName = $_POST['fullName'];
    $email = $_POST['signUpEmail'];
    $mobile = $_POST['signUpMobile'];
    $password = $_POST['signUpPassword'];
    $confirmPassword = $_POST['signUpRePassword'];

    // ✅ Check if user already exists with the same email or mobile number
    $checkStmt = $conn->prepare("SELECT email, mobile FROM users WHERE email = ? OR mobile = ?");
    $checkStmt->bind_param("ss", $email, $mobile);
    $checkStmt->execute();
    $checkStmt->store_result();
    $checkStmt->bind_result($existingEmail, $existingMobile);

    $emailExists = false;
    $mobileExists = false;
    
    while ($checkStmt->fetch()) {
        if ($existingEmail === $email) {
            $emailExists = true;
        }
        if ($existingMobile === $mobile) {
            $mobileExists = true;
        }
    }

    if ($emailExists && $mobileExists && $password !== $confirmPassword) {
        die("Error: User already exists with this email and mobile number, and passwords do not match!");
    } elseif ($emailExists && $mobileExists) {
        die("Error: User already exists with this email and mobile number!");
    } elseif ($emailExists && $password !== $confirmPassword) {
        die("Error: User already exists with this email, and passwords do not match!");
    } elseif ($mobileExists && $password !== $confirmPassword) {
        die("Error: User already exists with this mobile number, and passwords do not match!");
    } elseif ($emailExists) {
        die("Error: User already exists with this email!");
    } elseif ($mobileExists) {
        die("Error: User already exists with this mobile number!");
    } elseif ($password !== $confirmPassword) {
        die("Error: Passwords do not match!");
    }
    
    $checkStmt->close();

    // ✅ Generate a unique Student ID (SP + 1 random letter + 3 random numbers)
    function generateStudentID() {
        $randomLetter = chr(rand(65, 90)); // Random uppercase letter (A-Z)
        $randomNumbers = str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT); // 3-digit number (000-999)
        return "SP" . $randomLetter . $randomNumbers;
    }

    $studentID = generateStudentID();

    // ✅ Hash the password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // ✅ Insert into database (without specifying role, as it defaults to 'student')
    $stmt = $conn->prepare("INSERT INTO users (student_id, full_name, email, mobile, password) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $studentID, $fullName, $email, $mobile, $hashed_password);

    if ($stmt->execute()) {
        // ✅ Store user info in session & redirect to dashboard
        $_SESSION['user_id'] = $stmt->insert_id;
        $_SESSION['user_name'] = $fullName;
        $_SESSION['user_role'] = 'student'; // Default role
        $_SESSION['student_id'] = $studentID;

        header("Location: ../../dashboard.php");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
