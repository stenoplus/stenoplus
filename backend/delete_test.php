<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_id'])) {
    $testId = intval($_POST['test_id']);

    try {
        // Fetch file paths from DB
        $stmt = $conn->prepare("SELECT dictation_file, transcript_file FROM tests WHERE test_id = ?");
        $stmt->bind_param("i", $testId);
        $stmt->execute();
        $stmt->bind_result($dictationFile, $transcriptFile);
        $stmt->fetch();
        $stmt->close();

        // Function to delete file and its parent directory if empty
        function deleteFileAndDirectory($filePath, $testId) {
            if (!empty($filePath)) {
                $fullPath = "../" . $filePath;
                if (file_exists($fullPath)) {
                    if (!unlink($fullPath)) {
                        error_log("Failed to delete file: " . $fullPath);
                    }
                }
                
                // Get directory path (test-specific directory)
                $dirPath = dirname($fullPath);
                
                // Delete all files in the directory and then the directory itself
                if (is_dir($dirPath)) {
                    $files = glob($dirPath . '/*');
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            unlink($file);
                        }
                    }
                    if (count(scandir($dirPath)) == 2) { // Only . and .. remain
                        rmdir($dirPath);
                    }
                }
            }
        }

        // Delete dictation files and directory
        deleteFileAndDirectory($dictationFile, $testId);
        
        // Delete transcript files and directory
        deleteFileAndDirectory($transcriptFile, $testId);

        // Delete from DB
        $deleteStmt = $conn->prepare("DELETE FROM tests WHERE test_id = ?");
        $deleteStmt->bind_param("i", $testId);
        
        if ($deleteStmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete test record']);
        }
        
        $deleteStmt->close();

    } catch (Exception $e) {
        error_log("Error deleting test: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'An error occurred while deleting']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}

$conn->close();
?>
