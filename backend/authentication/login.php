<?php
session_start();
include '../config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['loginEmail'];
    $password = $_POST['loginPassword'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];

    $stmt = $conn->prepare("SELECT user_id, student_id, full_name, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            // Start transaction for atomic operations
            $conn->begin_transaction();
            
            try {
                // Record login session
                $login_time = date('Y-m-d H:i:s');
                $session_stmt = $conn->prepare("
                    INSERT INTO user_sessions 
                    (user_id, login_time, ip_address, device_info) 
                    VALUES (?, ?, ?, ?)
                ");
                $session_stmt->bind_param("isss", 
                    $user['user_id'], 
                    $login_time, 
                    $ip_address, 
                    $user_agent
                );
                $session_stmt->execute();
                $session_id = $conn->insert_id;
                $session_stmt->close();
                
                // Update last_active in users table
                $update_stmt = $conn->prepare("
                    UPDATE users SET last_active = ? 
                    WHERE user_id = ?
                ");
                $update_stmt->bind_param("si", $login_time, $user['user_id']);
                $update_stmt->execute();
                $update_stmt->close();
                
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['student_id'] = $user['student_id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['session_id'] = $session_id; // Store session ID for logout tracking
                
                $conn->commit();
                
                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header("Location: ../../admin.php");
                } elseif ($user['role'] === 'student') {
                    header("Location: ../../dashboard.php");
                } else {
                    echo "❌ Invalid role!";
                    exit();
                }
                exit();
                
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Login error: " . $e->getMessage());
                echo "❌ System error during login. Please try again.";
                exit();
            }
            
        } else {
            echo "❌ Incorrect password!";
        }
    } else {
        echo "❌ No user found with this email!";
    }

    $stmt->close();
    $conn->close();
}
?>