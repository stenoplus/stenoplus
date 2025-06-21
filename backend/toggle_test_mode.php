<?php
// Turn OFF all error display (but log them)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Set JSON header FIRST
header('Content-Type: application/json');

// Start output buffering to catch any stray output
ob_start();

require 'config.php';

try {
    // Verify request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method', 405);
    }

    // Validate session
    session_start();
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized', 401);
    }

    // Validate input
    if (!isset($_POST['test_id']) || !isset($_POST['mode'])) {
        throw new Exception('Missing parameters', 400);
    }

    $test_id = (int)$_POST['test_id'];
    $mode = in_array($_POST['mode'], ['0', '1']) ? (int)$_POST['mode'] : null;

    if ($test_id <= 0 || $mode === null) {
        throw new Exception('Invalid parameters', 400);
    }

    // Verify database connection
    if ($conn->connect_error) {
        throw new Exception('Database connection failed', 500);
    }

    // Prepare and execute
    $stmt = $conn->prepare("UPDATE tests SET test_mode = ? WHERE test_id = ?");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error, 500);
    }

    $stmt->bind_param("ii", $mode, $test_id);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error, 500);
    }

    // Clean any output before JSON
    ob_end_clean();

    echo json_encode([
        'status' => 'success',
        'new_mode' => $mode,
        'mode_text' => $mode ? 'Exam Mode' : 'Test Mode'
    ]);

} catch (Exception $e) {
    // Clean any output before JSON error
    ob_end_clean();
    
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    exit;
}