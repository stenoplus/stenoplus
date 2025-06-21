<?php
// system-settings.php
session_start();

// Check if user is logged in and is admin
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
//     header("Location: login.php");
//     exit();
// }

// Database Connection
require 'backend/config.php';

// Initialize variables
$success_message = '';
$error_message = '';

// Get current settings
$settings_query = "SELECT * FROM system_settings LIMIT 1";
$settings_result = $conn->query($settings_query);
$current_settings = $settings_result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();
        
        // Prepare settings data
        $site_name = $conn->real_escape_string($_POST['site_name']);
        $site_email = $conn->real_escape_string($_POST['site_email']);
        $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
        $registration_allowed = isset($_POST['registration_allowed']) ? 1 : 0;
        $default_user_role = $conn->real_escape_string($_POST['default_user_role']);
        $results_per_page = (int)$_POST['results_per_page'];
        $password_min_length = (int)$_POST['password_min_length'];
        $max_login_attempts = (int)$_POST['max_login_attempts'];
        $session_timeout = (int)$_POST['session_timeout'];
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $typing_test_duration = (int)$_POST['typing_test_duration'];
        
        // Check if settings exist
        if ($current_settings) {
            // Update existing settings
            $update_query = "UPDATE system_settings SET 
                            site_name = '$site_name',
                            site_email = '$site_email',
                            maintenance_mode = $maintenance_mode,
                            registration_allowed = $registration_allowed,
                            default_user_role = '$default_user_role',
                            results_per_page = $results_per_page,
                            password_min_length = $password_min_length,
                            max_login_attempts = $max_login_attempts,
                            session_timeout = $session_timeout,
                            email_notifications = $email_notifications,
                            typing_test_duration = $typing_test_duration,
                            updated_at = NOW()";
            $conn->query($update_query);
        } else {
            // Insert new settings
            $insert_query = "INSERT INTO system_settings (
                            site_name, site_email, maintenance_mode, registration_allowed,
                            default_user_role, results_per_page, password_min_length,
                            max_login_attempts, session_timeout, email_notifications,
                            typing_test_duration, created_at, updated_at
                            ) VALUES (
                            '$site_name', '$site_email', $maintenance_mode, $registration_allowed,
                            '$default_user_role', $results_per_page, $password_min_length,
                            $max_login_attempts, $session_timeout, $email_notifications,
                            $typing_test_duration, NOW(), NOW()
                            )";
            $conn->query($insert_query);
        }
        
        $conn->commit();
        $success_message = "System settings updated successfully!";
        
        // Refresh current settings
        $settings_result = $conn->query($settings_query);
        $current_settings = $settings_result->fetch_assoc();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error updating settings: " . $e->getMessage();
    }
}

// Function to check radio/checkbox
function isChecked($value, $compare) {
    return $value == $compare ? 'checked' : '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - StenoPlus</title>
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
        
        .form-checkbox {
            width: 1rem;
            height: 1rem;
            border: 1px solid #D1D5DB;
            border-radius: 0.25rem;
            background-color: white;
        }
        .dark .form-checkbox {
            background-color: #1F2937;
            border-color: #4B5563;
        }
    </style>
    <script>
        tailwind.config = {
            darkMode: 'class'
        };
    </script>
</head>
<body class="flex">

    <!-- Admin Sidebar -->
    <?php include 'sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="md:ml-64 w-full lg:p-6 md:p-0">
        <!-- Top Bar -->
        <header class="flex justify-between items-center bg-white shadow p-4 rounded-none lg:rounded-lg dark:bg-gray-800">
            <!-- Hamburger Menu (Only for Mobile) -->
            <button id="openSidebar" class="md:hidden">
                <i data-lucide="menu"></i>
            </button>

            <h2 class="text-xl font-semibold dark:text-white">System Settings</h2>
            
            <div class="flex items-center space-x-4">
                <i data-lucide="moon" id="darkModeToggle" class="cursor-pointer dark:text-white"></i>
                
                <!-- Profile Dropdown -->
                <div class="relative">
                    <img src="assets/images/admin.png" alt="Profile" class="w-10 h-10 rounded-full cursor-pointer" id="profileBtn">
                    <div id="profileDropdown" class="hidden absolute right-0 bg-white shadow-lg rounded-md w-40 mt-2 dark:bg-gray-700">
                        <p class="p-2 text-sm dark:text-white"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></p>
                        <p class="p-2 text-xs text-gray-500 dark:text-gray-300">Role: Admin</p>
                        <hr class="dark:border-gray-600">
                        <li class="flex items-center space-x-2 p-2 text-sm hover:bg-gray-200 dark:hover:bg-gray-600">
                            <i data-lucide="user" class="mr-0 w-4 h-4"></i> 
                            <a href="admin_profile.php" class="dark:text-white">Admin Profile</a>
                        </li>
                        <li class="flex items-center space-x-2 p-2 hover:bg-red-600 hover:text-white rounded-b text-sm">
                            <i data-lucide="log-out" class="mr-0 w-4 h-4"></i> 
                            <a href="backend/authentication/logout.php" class="dark:text-white">Logout</a>
                        </li>
                    </div>
                </div>
            </div>
        </header>

        <!-- Settings Content -->
        <section class="mt-6 p-6 lg:p-0">
            <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                <?php if ($success_message): ?>
                <div class="mb-6 p-4 bg-green-100 text-green-700 rounded-lg dark:bg-green-900/30 dark:text-green-300">
                    <?= $success_message ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                <div class="mb-6 p-4 bg-red-100 text-red-700 rounded-lg dark:bg-red-900/30 dark:text-red-300">
                    <?= $error_message ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-8">
                    <!-- General Settings -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold border-b pb-2 dark:text-white dark:border-gray-700">General Settings</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="site_name" class="form-label">Site Name</label>
                                <input type="text" id="site_name" name="site_name" class="form-control" 
                                       value="<?= htmlspecialchars($current_settings['site_name'] ?? 'StenoPlus') ?>" required>
                            </div>
                            
                            <div>
                                <label for="site_email" class="form-label">Site Email</label>
                                <input type="email" id="site_email" name="site_email" class="form-control" 
                                       value="<?= htmlspecialchars($current_settings['site_email'] ?? 'admin@stenoplus.in') ?>" required>
                            </div>
                            
                            <div>
                                <label for="typing_test_duration" class="form-label">Default Test Duration (minutes)</label>
                                <input type="number" id="typing_test_duration" name="typing_test_duration" 
                                       class="form-control" min="1" max="60" 
                                       value="<?= $current_settings['typing_test_duration'] ?? 5 ?>" required>
                            </div>
                            
                            <div>
                                <label for="results_per_page" class="form-label">Results Per Page</label>
                                <input type="number" id="results_per_page" name="results_per_page" 
                                       class="form-control" min="5" max="100" 
                                       value="<?= $current_settings['results_per_page'] ?? 20 ?>" required>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                           class="form-checkbox" <?= isChecked(1, $current_settings['maintenance_mode'] ?? 0) ?>>
                                    <span class="dark:text-white">Maintenance Mode</span>
                                </label>
                                <p class="text-sm text-gray-500 mt-1 dark:text-gray-400">When enabled, only admins can access the site</p>
                            </div>
                            
                            <div>
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" id="registration_allowed" name="registration_allowed" 
                                           class="form-checkbox" <?= isChecked(1, $current_settings['registration_allowed'] ?? 1) ?>>
                                    <span class="dark:text-white">Allow New Registrations</span>
                                </label>
                                <p class="text-sm text-gray-500 mt-1 dark:text-gray-400">Disable to prevent new user signups</p>
                            </div>
                            
                            <div>
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" id="email_notifications" name="email_notifications" 
                                           class="form-checkbox" <?= isChecked(1, $current_settings['email_notifications'] ?? 1) ?>>
                                    <span class="dark:text-white">Enable Email Notifications</span>
                                </label>
                                <p class="text-sm text-gray-500 mt-1 dark:text-gray-400">System will send email notifications</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Security Settings -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold border-b pb-2 dark:text-white dark:border-gray-700">Security Settings</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="default_user_role" class="form-label">Default User Role</label>
                                <select id="default_user_role" name="default_user_role" class="form-control" required>
                                    <option value="student" <?= isChecked('student', $current_settings['default_user_role'] ?? 'student') ?>>Student</option>
                                    <option value="instructor" <?= isChecked('instructor', $current_settings['default_user_role'] ?? 'student') ?>>Instructor</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="password_min_length" class="form-label">Minimum Password Length</label>
                                <input type="number" id="password_min_length" name="password_min_length" 
                                       class="form-control" min="6" max="32" 
                                       value="<?= $current_settings['password_min_length'] ?? 8 ?>" required>
                            </div>
                            
                            <div>
                                <label for="max_login_attempts" class="form-label">Max Login Attempts</label>
                                <input type="number" id="max_login_attempts" name="max_login_attempts" 
                                       class="form-control" min="1" max="10" 
                                       value="<?= $current_settings['max_login_attempts'] ?? 5 ?>" required>
                                <p class="text-sm text-gray-500 mt-1 dark:text-gray-400">Account locks after exceeding</p>
                            </div>
                            
                            <div>
                                <label for="session_timeout" class="form-label">Session Timeout (minutes)</label>
                                <input type="number" id="session_timeout" name="session_timeout" 
                                       class="form-control" min="5" max="1440" 
                                       value="<?= $current_settings['session_timeout'] ?? 30 ?>" required>
                                <p class="text-sm text-gray-500 mt-1 dark:text-gray-400">Inactivity period before logout</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Email Settings -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold border-b pb-2 dark:text-white dark:border-gray-700">Email Settings</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="smtp_host" class="form-label">SMTP Host</label>
                                <input type="text" id="smtp_host" name="smtp_host" class="form-control" 
                                       value="<?= htmlspecialchars($current_settings['smtp_host'] ?? '') ?>">
                            </div>
                            
                            <div>
                                <label for="smtp_port" class="form-label">SMTP Port</label>
                                <input type="number" id="smtp_port" name="smtp_port" class="form-control" 
                                       value="<?= $current_settings['smtp_port'] ?? 587 ?>">
                            </div>
                            
                            <div>
                                <label for="smtp_username" class="form-label">SMTP Username</label>
                                <input type="text" id="smtp_username" name="smtp_username" class="form-control" 
                                       value="<?= htmlspecialchars($current_settings['smtp_username'] ?? '') ?>">
                            </div>
                            
                            <div>
                                <label for="smtp_password" class="form-label">SMTP Password</label>
                                <input type="password" id="smtp_password" name="smtp_password" class="form-control" 
                                       value="<?= htmlspecialchars($current_settings['smtp_password'] ?? '') ?>">
                            </div>
                            
                            <div>
                                <label for="smtp_encryption" class="form-label">SMTP Encryption</label>
                                <select id="smtp_encryption" name="smtp_encryption" class="form-control">
                                    <option value="">None</option>
                                    <option value="tls" <?= isChecked('tls', $current_settings['smtp_encryption'] ?? '') ?>>TLS</option>
                                    <option value="ssl" <?= isChecked('ssl', $current_settings['smtp_encryption'] ?? '') ?>>SSL</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="flex justify-end space-x-4 pt-6 border-t dark:border-gray-700">
                        <button type="button" onclick="window.location.reload()" 
                                class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 dark:bg-gray-700 dark:text-white dark:border-gray-600 dark:hover:bg-gray-600">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                            Save Settings
                        </button>
                    </div>
                </form>
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
        document.getElementById("profileBtn").addEventListener("click", function () {
            document.getElementById("profileDropdown").classList.toggle("hidden");
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