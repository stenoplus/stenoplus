<?php
// my-profile.php
session_start();

// Security headers
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// Check if user is logged in and is a student
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
//     header("Location: login.php");
//     exit();
// }

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database Connection
require 'backend/config.php';

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Helper functions
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_mobile($mobile) {
    return preg_match("/^[0-9]{10,15}$/", $mobile);
}

function log_activity($conn, $user_id, $action) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $action, $ip);
    $stmt->execute();
    $stmt->close();
}

// Get current student data
$student_query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($student_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Calculate age from date of birth
$age = '';
if (!empty($student['date_of_birth'])) {
    $dob = new DateTime($student['date_of_birth']);
    $now = new DateTime();
    $age = $now->diff($dob)->y;
}

// Handle form submission for profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid CSRF token";
    } else {
        $full_name = sanitize_input($_POST['full_name']);
        $mobile = sanitize_input($_POST['mobile']);
        $address = sanitize_input($_POST['address'] ?? '');
        $city = sanitize_input($_POST['city'] ?? '');
        $state = sanitize_input($_POST['state'] ?? '');
        $pin_code = sanitize_input($_POST['pin_code'] ?? '');
        $gender = sanitize_input($_POST['gender'] ?? '');
        $date_of_birth = sanitize_input($_POST['date_of_birth'] ?? '');
        $target_exam = sanitize_input($_POST['target_exam'] ?? '');
        
        // Mobile validation
        if (!validate_mobile($mobile)) {
            $error_message = "Invalid mobile number format";
        } else {
            try {
                $update_query = "UPDATE users SET 
                                full_name = ?,
                                mobile = ?,
                                address = ?,
                                city = ?,
                                state = ?,
                                pin_code = ?,
                                gender = ?,
                                date_of_birth = ?,
                                target_exam = ?
                                WHERE user_id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("sssssssssi", 
                    $full_name, 
                    $mobile, 
                    $address, 
                    $city, 
                    $state, 
                    $pin_code, 
                    $gender, 
                    $date_of_birth, 
                    $target_exam, 
                    $user_id);
                
                if ($stmt->execute()) {
                    $success_message = "Profile updated successfully!";
                    log_activity($conn, $user_id, "Profile updated");
                    
                    // Refresh student data
                    $stmt = $conn->prepare($student_query);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $student = $stmt->get_result()->fetch_assoc();
                    $_SESSION['user_name'] = $full_name;
                } else {
                    $error_message = "Error updating profile: " . $conn->error;
                }
                $stmt->close();
            } catch (Exception $e) {
                $error_message = "Error: " . $e->getMessage();
            }
        }
    }
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid CSRF token";
    } else {
        $upload_dir = 'uploads/student_profile/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Verify upload is genuine
        if (!is_uploaded_file($_FILES['profile_picture']['tmp_name'])) {
            $error_message = "Invalid file upload attempt";
        } else {
            $file_info = getimagesize($_FILES['profile_picture']['tmp_name']);
            $allowed_types = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif'
            ];
            
            if ($file_info && array_key_exists($file_info['mime'], $allowed_types)) {
                // Size limitation (2MB)
                if ($_FILES['profile_picture']['size'] > 2097152) {
                    $error_message = "File size must be less than 2MB";
                } else {
                    $file_ext = $allowed_types[$file_info['mime']];
                    $file_name = 'profile_' . $user_id . '_' . time() . '.' . $file_ext;
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $file_path)) {
                        // Delete old profile picture if exists
                        if (!empty($student['profile_picture']) && file_exists($student['profile_picture'])) {
                            unlink($student['profile_picture']);
                        }
                        
                        // Update database
                        $update_pic_query = "UPDATE users SET profile_picture = ? WHERE user_id = ?";
                        $stmt = $conn->prepare($update_pic_query);
                        $stmt->bind_param("si", $file_path, $user_id);
                        
                        if ($stmt->execute()) {
                            $success_message = "Profile picture updated successfully!";
                            log_activity($conn, $user_id, "Profile picture updated");
                            
                            // Refresh student data
                            $stmt = $conn->prepare($student_query);
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $student = $stmt->get_result()->fetch_assoc();
                        } else {
                            $error_message = "Error updating profile picture in database.";
                        }
                        $stmt->close();
                    } else {
                        $error_message = "Error uploading file.";
                    }
                }
            } else {
                $error_message = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    // Rate limiting
    if (!isset($_SESSION['last_password_change'])) {
        $_SESSION['last_password_change'] = time();
    } else {
        $elapsed = time() - $_SESSION['last_password_change'];
        if ($elapsed < 30) {
            $error_message = "Please wait " . (30 - $elapsed) . " seconds before trying again";
        }
    }

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid CSRF token";
    } else {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        $check_query = "SELECT password FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!password_verify($current_password, $result['password'])) {
            $error_message = "Current password is incorrect";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match";
        } elseif (strlen($new_password) < 8) {
            $error_message = "Password must be at least 8 characters long";
        } elseif (!preg_match("/[A-Z]/", $new_password)) {
            $error_message = "Password must contain at least one uppercase letter";
        } elseif (!preg_match("/[a-z]/", $new_password)) {
            $error_message = "Password must contain at least one lowercase letter";
        } elseif (!preg_match("/[0-9]/", $new_password)) {
            $error_message = "Password must contain at least one number";
        } else {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET password = ? WHERE user_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $success_message = "Password changed successfully!";
                log_activity($conn, $user_id, "Password changed");
                $_SESSION['last_password_change'] = time();
                
                // If 2FA enabled, require verification
                if ($student['2fa_enabled']) {
                    require_once 'backend/2fa_handler.php';
                    send_2fa_verification($student['email']);
                    $_SESSION['pending_password'] = $new_password;
                    header("Location: verify_2fa.php?action=password_change");
                    exit();
                }
            } else {
                $error_message = "Error changing password: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Handle account deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid CSRF token";
    } else {
        $password = $_POST['delete_password'];
        
        // Verify password
        $check_query = "SELECT password FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!password_verify($password, $result['password'])) {
            $error_message = "Password is incorrect";
        } else {
            // Delete profile picture if exists
            if (!empty($student['profile_picture']) && file_exists($student['profile_picture'])) {
                unlink($student['profile_picture']);
            }
            
            // Delete account
            $delete_query = "DELETE FROM users WHERE user_id = ?";
            $stmt = $conn->prepare($delete_query);
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                log_activity($conn, $user_id, "Account deleted");
                session_destroy();
                header("Location: login.php?account_deleted=1");
                exit();
            } else {
                $error_message = "Error deleting account: " . $conn->error;
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - StenoPlus</title>
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #F3F4F6; }
        .sidebar { 
            transition: transform 0.3s ease-in-out;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar-hidden { transform: translateX(-100%); }
        
        .dark body {
            background-color: #111827;
            color: #F3F4F6;
        }
        .dark .sidebar {
            background-color: #1F2937;
        }
        .dark .bg-white {
            background-color: #1E293B !important;
        }
        .dark .text-gray-500 {
            color: #9CA3AF !important;
        }
        .dark header {
            background-color: #1F2937 !important;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }
        .dark .form-label {
            color: #D1D5DB;
        }
        
        .form-control {
            display: block;
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #D1D5DB;
            border-radius: 0.375rem;
            background-color: white;
            color: #111827;
        }
        .dark .form-control {
            background-color: #1F2937;
            border-color: #4B5563;
            color: #F3F4F6;
        }
        
        .profile-pic {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #E5E7EB;
            cursor: pointer;
        }
        .dark .profile-pic {
            border-color: #4B5563;
        }
        
        .profile-pic-container {
            position: relative;
            display: inline-block;
        }
        
        .profile-pic-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .profile-pic-container:hover .profile-pic-overlay {
            opacity: 1;
        }
        
        .profile-pic-text {
            color: white;
            font-weight: 500;
        }
    </style>
    <script>
        tailwind.config = {
            darkMode: 'class'
        };
        
        function confirmDelete() {
            return confirm("Are you sure you want to delete your account? This action cannot be undone.");
        }
        
        function triggerFileInput() {
            document.getElementById('profile_picture').click();
        }
        
        function previewProfilePicture(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profile_image').src = e.target.result;
                    document.getElementById('uploadForm').submit();
                };
                reader.readAsDataURL(file);
            }
        }
    </script>
</head>
<body class="flex">

    <!-- Student Sidebar -->
    <?php include 'student-sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="md:ml-64 w-full lg:p-6 md:p-0">
        <!-- Top Bar -->
        <header class="flex justify-between items-center bg-white shadow p-4 rounded-none lg:rounded-lg dark:bg-gray-800">
            <button id="openSidebar" class="md:hidden">
                <i data-lucide="menu"></i>
            </button>

            <h2 class="text-xl font-semibold dark:text-white">My Profile</h2>
            
            <div class="flex items-center space-x-4">
                <i data-lucide="moon" id="darkModeToggle" class="cursor-pointer dark:text-white"></i>
                
                <?php require 'profile-dropdown.php'; ?>
            </div>
        </header>

        <!-- Profile Content -->
        <section class="mt-6 p-6 lg:p-0">
            <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                <?php if ($success_message): ?>
                <div class="mb-6 p-4 bg-green-100 text-green-700 rounded-lg dark:bg-green-900/30 dark:text-green-300">
                    <?= htmlspecialchars($success_message) ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                <div class="mb-6 p-4 bg-red-100 text-red-700 rounded-lg dark:bg-red-900/30 dark:text-red-300">
                    <?= htmlspecialchars($error_message) ?>
                </div>
                <?php endif; ?>
                
                <div class="flex flex-col md:flex-row gap-8">
                    <!-- Profile Picture Section -->
                    <div class="md:w-1/4">
                        <div class="flex flex-col items-center">
                            <form id="uploadForm" method="POST" enctype="multipart/form-data" class="text-center">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <div class="profile-pic-container mb-4">
                                    <img id="profile_image" src="<?= !empty($student['profile_picture']) ? htmlspecialchars($student['profile_picture']) : 'assets/images/student.png' ?>" 
                                         alt="Profile Picture" class="profile-pic" onclick="triggerFileInput()">
                                    <div class="profile-pic-overlay" onclick="triggerFileInput()">
                                        <span class="profile-pic-text">Change Photo</span>
                                    </div>
                                </div>
                                <input type="file" id="profile_picture" name="profile_picture" 
                                       accept="image/*" class="hidden" onchange="previewProfilePicture(event)">
                            </form>
                            
                            <h3 class="text-xl font-bold text-center dark:text-white"><?= htmlspecialchars($student['full_name']) ?></h3>
                            <p class="text-gray-500 dark:text-gray-400">Student</p>
                            
                            <div class="mt-4 w-full text-left space-y-2">
                                <div>
                                    <span class="font-medium dark:text-white">Student ID:</span>
                                    <span class="text-gray-600 dark:text-gray-300"><?= htmlspecialchars($student['student_id']) ?></span>
                                </div>
                                <div>
                                    <span class="font-medium dark:text-white">Email:</span>
                                    <span class="text-gray-600 dark:text-gray-300"><?= htmlspecialchars($student['email']) ?></span>
                                </div>
                                <?php if (!empty($age)): ?>
                                <div>
                                    <span class="font-medium dark:text-white">Age:</span>
                                    <span class="text-gray-600 dark:text-gray-300"><?= $age ?> years</span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Profile Details Section -->
                    <div class="md:w-3/4 space-y-8">
                        <!-- Personal Information -->
                        <div>
                            <h3 class="text-lg font-semibold border-b pb-2 mb-4 dark:text-white dark:border-gray-700">Personal Information</h3>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="full_name" class="form-label">Full Name</label>
                                        <input type="text" id="full_name" name="full_name" class="form-control" 
                                               value="<?= htmlspecialchars($student['full_name']) ?>" required>
                                    </div>
                                    
                                    <div>
                                        <label for="mobile" class="form-label">Mobile Number</label>
                                        <input type="tel" id="mobile" name="mobile" class="form-control" 
                                               value="<?= htmlspecialchars($student['mobile'] ?? '') ?>" 
                                               pattern="[0-9]{10,15}" title="10-15 digit phone number" required>
                                    </div>
                                    
                                    <div>
                                        <label for="gender" class="form-label">Gender</label>
                                        <select id="gender" name="gender" class="form-control">
                                            <option value="">Select Gender</option>
                                            <option value="male" <?= $student['gender'] == 'male' ? 'selected' : '' ?>>Male</option>
                                            <option value="female" <?= $student['gender'] == 'female' ? 'selected' : '' ?>>Female</option>
                                            <option value="other" <?= $student['gender'] == 'other' ? 'selected' : '' ?>>Other</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                                        <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" 
                                               value="<?= htmlspecialchars($student['date_of_birth'] ?? '') ?>">
                                    </div>
                                    
                                    <div>
                                        <label for="target_exam" class="form-label">Target Exam</label>
                                        <input type="text" id="target_exam" name="target_exam" class="form-control" 
                                               value="<?= htmlspecialchars($student['target_exam'] ?? '') ?>">
                                    </div>
                                    
                                    <div class="md:col-span-2">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea id="address" name="address" class="form-control" rows="2"><?= htmlspecialchars($student['address'] ?? '') ?></textarea>
                                    </div>
                                    
                                    <div>
                                        <label for="city" class="form-label">City</label>
                                        <input type="text" id="city" name="city" class="form-control" 
                                               value="<?= htmlspecialchars($student['city'] ?? '') ?>">
                                    </div>
                                    
                                    <div>
                                        <label for="state" class="form-label">State</label>
                                        <input type="text" id="state" name="state" class="form-control" 
                                               value="<?= htmlspecialchars($student['state'] ?? '') ?>">
                                    </div>
                                    
                                    <div>
                                        <label for="pin_code" class="form-label">PIN Code</label>
                                        <input type="text" id="pin_code" name="pin_code" class="form-control" 
                                               value="<?= htmlspecialchars($student['pin_code'] ?? '') ?>">
                                    </div>
                                </div>
                                
                                <div class="flex justify-end mt-6">
                                    <button type="submit" name="update_profile" 
                                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                        Update Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Change Password -->
                        <div>
                            <h3 class="text-lg font-semibold border-b pb-2 mb-4 dark:text-white dark:border-gray-700">Change Password</h3>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                                    </div>
                                    
                                    <div>
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" id="new_password" name="new_password" class="form-control" 
                                               minlength="8" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" 
                                               title="Must contain at least one number, one uppercase and lowercase letter, and at least 8 characters" required>
                                    </div>
                                    
                                    <div>
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                                    </div>
                                </div>
                                
                                <div class="flex justify-end mt-6">
                                    <button type="submit" name="change_password" 
                                            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                        Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Account Deletion -->
                        <div class="border-t pt-6 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-red-600 mb-4 dark:text-red-400">Danger Zone</h3>
                            <div class="bg-red-50 p-4 rounded-lg border border-red-200 dark:bg-red-900/20 dark:border-red-800">
                                <h4 class="font-bold text-red-700 dark:text-red-300">Delete Account</h4>
                                <p class="text-sm text-red-600 mb-4 dark:text-red-300">Once you delete your account, there is no going back. Please be certain.</p>
                                
                                <form method="POST" onsubmit="return confirmDelete()">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <div class="mb-4">
                                        <label for="delete_password" class="form-label">Enter your password to confirm</label>
                                        <input type="password" id="delete_password" name="delete_password" class="form-control" required>
                                    </div>
                                    <button type="submit" name="delete_account" 
                                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                                        Delete My Account
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script>
        lucide.createIcons();

        // Dark Mode Toggle
        function toggleDarkMode() {
            const isDark = document.documentElement.classList.toggle("dark");
            localStorage.setItem("darkMode", isDark ? "enabled" : "disabled");
        }
        document.getElementById("darkModeToggle").addEventListener("click", toggleDarkMode);
        if (localStorage.getItem("darkMode") === "enabled") {
            document.documentElement.classList.add("dark");
        }

        // Profile Dropdown Toggle
        // document.getElementById("profileBtn").addEventListener("click", function () {
        //     document.getElementById("profileDropdown").classList.toggle("hidden");
        // });

        // Close dropdown when clicking outside
        document.addEventListener("click", function (event) {
            const profileBtn = document.getElementById("profileBtn");
            const dropdown = document.getElementById("profileDropdown");
            
            if (!profileBtn.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.add("hidden");
            }
        });

        // Sidebar Toggle for Mobile
        const sidebar = document.getElementById("sidebar");
        const openSidebar = document.getElementById("openSidebar");
        const closeSidebar = document.getElementById("closeSidebar");

        openSidebar.addEventListener("click", function () {
            sidebar.classList.remove("sidebar-hidden");
        });
        closeSidebar.addEventListener("click", function () {
            sidebar.classList.add("sidebar-hidden");
        });
        document.addEventListener("click", function (event) {
            if (!sidebar.contains(event.target) && !openSidebar.contains(event.target) && !closeSidebar.contains(event.target)) {
                sidebar.classList.add("sidebar-hidden");
            }
        });
    </script>
</body>
</html>