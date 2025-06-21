<?php
session_start();
include '../config.php';

// Only proceed if there's an active session
if (isset($_SESSION['user_id']) && isset($_SESSION['session_id'])) {
    try {
        // Record logout time in user_sessions table
        $logout_time = date('Y-m-d H:i:s');
        $session_id = $_SESSION['session_id'];
        
        $stmt = $conn->prepare("
            UPDATE user_sessions 
            SET logout_time = ? 
            WHERE session_id = ? AND user_id = ?
        ");
        $stmt->bind_param("sii", $logout_time, $session_id, $_SESSION['user_id']);
        $stmt->execute();
        
        // Optional: Update last_active in users table
        $update_stmt = $conn->prepare("
            UPDATE users 
            SET last_active = ? 
            WHERE user_id = ?
        ");
        $update_stmt->bind_param("si", $logout_time, $_SESSION['user_id']);
        $update_stmt->execute();
        
        $stmt->close();
        $update_stmt->close();
    } catch (Exception $e) {
        error_log("Logout error: " . $e->getMessage());
        // Continue with logout even if tracking fails
    }
}

// Clear all session data
$_SESSION = array();

// Destroy the session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Redirect to login page
header("Location: ../../login.php");
exit();
?>
