<?php 
session_start();

// ✅ Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// ✅ Database Connection
require 'config.php';

// ✅ Fetch form data
$testId = isset($_POST['testId']) ? intval($_POST['testId']) : 0;
$testName = isset($_POST['testName']) ? trim($_POST['testName']) : '';
$examId = isset($_POST['exam']) ? intval($_POST['exam']) : 0;
$categoryId = isset($_POST['category']) ? intval($_POST['category']) : 0;
$transcriptDuration = isset($_POST['transcriptDuration']) ? intval($_POST['transcriptDuration']) : 0;
$dictationDuration = isset($_POST['dictationDuration']) ? intval($_POST['dictationDuration']) : 0;
$language = isset($_POST['language']) ? $_POST['language'] : 'en';
$wordCount = isset($_POST['wordCount']) ? intval($_POST['wordCount']) : 0;
$testMode = isset($_POST['testMode']) ? 1 : 0;

// Validate language (only allow 'en' or 'hi')
if (!in_array($language, ['en', 'hi'])) {
    $language = 'en';
}

// ✅ Fetch existing file paths if updating
$oldDictationPath = '';
$oldTranscriptPath = '';
if ($testId > 0) {
    $fetchStmt = $conn->prepare("SELECT dictation_file, transcript_file FROM tests WHERE test_id = ?");
    $fetchStmt->bind_param("i", $testId);
    $fetchStmt->execute();
    $fetchStmt->bind_result($oldDictationPath, $oldTranscriptPath);
    $fetchStmt->fetch();
    $fetchStmt->close();
}

// ✅ Ensure required fields are filled
if (!empty($testName) && $examId > 0 && $categoryId > 0 && $transcriptDuration > 0 && $dictationDuration > 0) {

    // ✅ If testId is provided, update instead of insert
    if ($testId > 0) {
        // ✅ Update existing test info
        $stmt = $conn->prepare("UPDATE tests SET test_name = ?, exam_id = ?, category_id = ?, transcript_duration = ?, dictation_duration = ?, language_code = ?, word_count = ?, test_mode = ? WHERE test_id = ?");
        $stmt->bind_param("siiissiii", $testName, $examId, $categoryId, $transcriptDuration, $dictationDuration, $language, $wordCount, $testMode, $testId);
        $stmt->execute();
    } else {
        // ✅ Insert new test
        $stmt = $conn->prepare("INSERT INTO tests (test_name, exam_id, category_id, transcript_duration, dictation_duration, language_code, word_count, test_mode) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("siiissii", $testName, $examId, $categoryId, $transcriptDuration, $dictationDuration, $language, $wordCount, $testMode);
        $stmt->execute();
        $testId = $stmt->insert_id;
    }

    $stmt->close();

    // ✅ Create folders if not exist
    $dictationDir = "../uploads/dictation/{$testId}/";
    $transcriptDir = "../uploads/transcript/{$testId}/";

    if (!file_exists($dictationDir)) {
        mkdir($dictationDir, 0755, true);
    }
    if (!file_exists($transcriptDir)) {
        mkdir($transcriptDir, 0755, true);
    }

    // ✅ File upload handling with old file cleanup
    $dictationFilePath = '';
    $transcriptFilePath = '';

    // Handle dictation file upload
    if (isset($_FILES['dictation']) && $_FILES['dictation']['error'] === UPLOAD_ERR_OK) {
        // Delete old dictation file if exists
        if (!empty($oldDictationPath) && file_exists("../".$oldDictationPath)) {
            unlink("../".$oldDictationPath);
        }

        // Sanitize filename
        $dictationFileName = preg_replace('/[^a-zA-Z0-9\.\-_]/', '', basename($_FILES['dictation']['name']));
        $dictationFilePath = "uploads/dictation/{$testId}/".$dictationFileName;
        
        // Validate file type (audio)
        $allowedAudioTypes = ['audio/mpeg', 'audio/wav', 'audio/x-wav'];
        if (in_array($_FILES['dictation']['type'], $allowedAudioTypes)) {
            move_uploaded_file($_FILES['dictation']['tmp_name'], "../".$dictationFilePath);
        } else {
            $_SESSION['error'] = "Invalid audio file type. Only MP3 and WAV are allowed.";
            header("Location: ../manage-tests.php");
            exit();
        }
    }

    // Handle transcript file upload - STRICT .TXT ONLY CHECK
    if (isset($_FILES['transcript']) && $_FILES['transcript']['error'] === UPLOAD_ERR_OK) {
        // Delete old transcript file if exists
        if (!empty($oldTranscriptPath) && file_exists("../".$oldTranscriptPath)) {
            unlink("../".$oldTranscriptPath);
        }

        // Get file extension
        $fileExt = strtolower(pathinfo($_FILES['transcript']['name'], PATHINFO_EXTENSION));
        
        // Strict check for .txt files only
        if ($fileExt !== 'txt') {
            $_SESSION['error'] = "Invalid transcript file type. Only .TXT files are allowed.";
            header("Location: ../manage-tests.php");
            exit();
        }

        // Sanitize filename
        $transcriptFileName = preg_replace('/[^a-zA-Z0-9\.\-_]/', '', basename($_FILES['transcript']['name']));
        $transcriptFilePath = "uploads/transcript/{$testId}/".$transcriptFileName;
        
        // Move the file if it passed validation
        move_uploaded_file($_FILES['transcript']['tmp_name'], "../".$transcriptFilePath);
    }

    // ✅ Update file paths if files were uploaded
    if (!empty($dictationFilePath) || !empty($transcriptFilePath)) {
        $updateSQL = "UPDATE tests SET ";
        $params = [];
        $types = "";

        if (!empty($dictationFilePath)) {
            $updateSQL .= "dictation_file = ?, ";
            $params[] = $dictationFilePath;
            $types .= "s";
        }

        if (!empty($transcriptFilePath)) {
            $updateSQL .= "transcript_file = ?, ";
            $params[] = $transcriptFilePath;
            $types .= "s";
        }

        // Remove trailing comma and space
        $updateSQL = rtrim($updateSQL, ", ");
        $updateSQL .= " WHERE test_id = ?";
        $params[] = $testId;
        $types .= "i";

        $updateStmt = $conn->prepare($updateSQL);
        $updateStmt->bind_param($types, ...$params);
        $updateStmt->execute();
        $updateStmt->close();
    }

    $_SESSION['success'] = "Test ".($testId > 0 ? "updated" : "created")." successfully!";
    header("Location: ../manage-tests.php");
    exit();

} else {
    $_SESSION['error'] = "Please fill all required fields with valid data!";
    header("Location: ../manage-tests.php");
    exit();
}

$conn->close();
?>
