<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require 'config.php';
date_default_timezone_set('UTC');

// Allowed image types with their MIME validation
$allowed_types = [
    'jpg'  => ['image/jpeg', 'image/pjpeg'],
    'jpeg' => ['image/jpeg', 'image/pjpeg'],
    'png'  => ['image/png'],
    'gif'  => ['image/gif'],
    'webp' => ['image/webp'],
    'svg'  => ['image/svg+xml']
];

try {
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
        handleExamSubmission($conn, $allowed_types);
    }

    if (isset($_GET['delete'])) {
        handleExamDeletion($conn);
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {
        handleExamUpdate($conn, $allowed_types);
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

header("Location: ../manage-exams.php");
exit();

// Function to handle new exam submission
function handleExamSubmission($conn, $allowed_types) {
    $exam_name = trim($_POST['exam_name']);
    $exam_logo_path = null;

    if (isset($_FILES['exam_logo']) && $_FILES['exam_logo']['error'] == UPLOAD_ERR_OK) {
        $exam_logo_path = processUploadedLogo($_FILES['exam_logo'], $allowed_types);
    }

    validateExamName($exam_name, $conn);

    $stmt = $conn->prepare("INSERT INTO exams (exam_name, exam_logo) VALUES (?, ?)");
    $stmt->bind_param("ss", $exam_name, $exam_logo_path);
    
    if (!$stmt->execute()) {
        throw new Exception("Error adding exam: " . $conn->error);
    }

    $_SESSION['success'] = "Exam added successfully!";
    $stmt->close();
}

// Function to handle exam deletion
function handleExamDeletion($conn) {
    $exam_id = intval($_GET['delete']);
    
    // Delete associated logo file
    $select_stmt = $conn->prepare("SELECT exam_logo FROM exams WHERE exam_id = ?");
    $select_stmt->bind_param("i", $exam_id);
    $select_stmt->execute();
    $result = $select_stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row && !empty($row['exam_logo'])) {
        $logo_path = "../" . $row['exam_logo'];
        if (file_exists($logo_path)) {
            unlink($logo_path);
        }
    }
    $select_stmt->close();

    // Delete exam record
    $delete_stmt = $conn->prepare("DELETE FROM exams WHERE exam_id = ?");
    $delete_stmt->bind_param("i", $exam_id);
    
    if (!$delete_stmt->execute()) {
        throw new Exception("Error deleting exam: " . $conn->error);
    }

    $_SESSION['success'] = "Exam deleted successfully!";
    $delete_stmt->close();
}

// Function to handle exam update
function handleExamUpdate($conn, $allowed_types) {
    $exam_id = intval($_POST['exam_id']);
    $exam_name = trim($_POST['exam_name']);
    $exam_logo_path = null;

    if (isset($_FILES['exam_logo']) && $_FILES['exam_logo']['error'] == UPLOAD_ERR_OK) {
        $exam_logo_path = processUploadedLogo($_FILES['exam_logo'], $allowed_types);
        
        // Delete old logo if new one was uploaded
        $select_stmt = $conn->prepare("SELECT exam_logo FROM exams WHERE exam_id = ?");
        $select_stmt->bind_param("i", $exam_id);
        $select_stmt->execute();
        $result = $select_stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row && !empty($row['exam_logo'])) {
            $old_logo_path = "../" . $row['exam_logo'];
            if (file_exists($old_logo_path)) {
                unlink($old_logo_path);
            }
        }
        $select_stmt->close();
    }

    validateExamName($exam_name, $conn, $exam_id);

    if ($exam_logo_path) {
        $stmt = $conn->prepare("UPDATE exams SET exam_name = ?, exam_logo = ?, updated_at = CURRENT_TIMESTAMP WHERE exam_id = ?");
        $stmt->bind_param("ssi", $exam_name, $exam_logo_path, $exam_id);
    } else {
        $stmt = $conn->prepare("UPDATE exams SET exam_name = ?, updated_at = CURRENT_TIMESTAMP WHERE exam_id = ?");
        $stmt->bind_param("si", $exam_name, $exam_id);
    }

    if (!$stmt->execute()) {
        throw new Exception("Error updating exam: " . $conn->error);
    }

    $_SESSION['success'] = "Exam updated successfully!";
    $stmt->close();
}

// Process uploaded logo file
function processUploadedLogo($file, $allowed_types) {
    $target_dir = "../uploads/exam-logos/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $file_ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $filename = 'exam_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
    $target_file = $target_dir . $filename;

    // Validate file extension
    if (!array_key_exists($file_ext, $allowed_types)) {
        throw new Exception("Invalid file type. Allowed: " . implode(', ', array_keys($allowed_types)));
    }

    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file["tmp_name"]);
    finfo_close($finfo);

    if (!in_array($mime, $allowed_types[$file_ext])) {
        throw new Exception("Invalid file content for type: $file_ext");
    }

    // Special validation for SVG
    if ($file_ext === 'svg') {
        $svg_content = file_get_contents($file["tmp_name"]);
        if (strpos($svg_content, '<?php') !== false || strpos($svg_content, '<script') !== false) {
            throw new Exception("SVG file contains potentially dangerous content");
        }
    }

    if (!move_uploaded_file($file["tmp_name"], $target_file)) {
        throw new Exception("Error uploading file");
    }

    return 'uploads/exam-logos/' . $filename;
}

// Validate exam name
function validateExamName($exam_name, $conn, $exclude_id = null) {
    if (empty($exam_name)) {
        throw new Exception("Exam name cannot be empty.");
    }

    if ($exclude_id) {
        $check_stmt = $conn->prepare("SELECT exam_id FROM exams WHERE LOWER(exam_name) = LOWER(?) AND exam_id != ?");
        $check_stmt->bind_param("si", $exam_name, $exclude_id);
    } else {
        $check_stmt = $conn->prepare("SELECT exam_id FROM exams WHERE LOWER(exam_name) = LOWER(?)");
        $check_stmt->bind_param("s", $exam_name);
    }

    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        throw new Exception("Exam name already exists!");
    }
    $check_stmt->close();
}
?>
