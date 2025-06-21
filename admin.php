<?php
// admin_dashboard.php
session_start();

// Check if user is logged in and is admin
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
//     header("Location: login.php");
//     exit();
// }

// Database Connection
require 'backend/config.php';

// Get system statistics
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
                (SELECT COUNT(*) FROM tests) as total_tests,
                (SELECT COUNT(*) FROM test_results) as total_attempts,
                (SELECT AVG(typing_speed_wpm) FROM test_results) as avg_speed,
                (SELECT AVG(accuracy) FROM test_results) as avg_accuracy,
                (SELECT COUNT(*) FROM exams) as total_exams";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Get recent test attempts
$recent_query = "SELECT 
                r.result_id, 
                u.full_name, 
                t.test_name,
                r.typing_speed_wpm,
                r.accuracy,
                r.submission_time
                FROM test_results r
                JOIN users u ON r.user_id = u.user_id
                JOIN tests t ON r.test_id = t.test_id
                ORDER BY r.submission_time DESC
                LIMIT 5";
$recent_tests = $conn->query($recent_query)->fetch_all(MYSQLI_ASSOC);

// Get recent registrations
$registrations_query = "SELECT 
                       user_id, full_name, email, created_at 
                       FROM users 
                       WHERE role = 'student'
                       ORDER BY created_at DESC
                       LIMIT 5";
$recent_registrations = $conn->query($registrations_query)->fetch_all(MYSQLI_ASSOC);

// Format numbers
function formatNumber($num) {
    return number_format($num, 1);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - StenoPlus</title>
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap">
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .progress-ring__circle {
            transition: stroke-dashoffset 0.35s;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
        
        .exam-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
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

            <h2 class="text-xl font-semibold dark:text-white">Admin Dashboard</h2>
            
            <div class="flex items-center space-x-4">
                <i data-lucide="bell" class="cursor-pointer dark:text-white"></i>
                <i data-lucide="moon" id="darkModeToggle" class="cursor-pointer dark:text-white"></i>
                
                <!-- Profile Dropdown -->
                <div class="relative">
                    <img src="assets/images/student.png" alt="Profile" class="w-10 h-10 rounded-full cursor-pointer" id="profileBtn">
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

        <!-- Dashboard Content -->
        <section class="mt-6 p-6 lg:p-0">
            <div class="space-y-6">
                <!-- Welcome Banner -->
                <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg p-6 text-white shadow">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                        <div>
                            <h1 class="text-2xl font-bold">Welcome, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></h1>
                            <p class="mt-2 opacity-90">Manage your typing test platform</p>
                        </div>
                        <div class="flex gap-2 mt-4 md:mt-0">
                            <a href="manage-tests.php" class="px-4 py-2 bg-white text-blue-600 rounded-lg font-medium hover:bg-gray-100 flex items-center gap-2">
                                <i data-lucide="plus" class="w-4 h-4"></i> Create Test
                            </a>
                            <a href="manage-students.php" class="px-4 py-2 bg-white text-blue-600 rounded-lg font-medium hover:bg-gray-100 flex items-center gap-2">
                                <i data-lucide="users" class="w-4 h-4"></i> Manage Users
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Total Students -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Total Students</p>
                                <h3 class="text-2xl font-bold mt-1 dark:text-white"><?= $stats['total_students'] ?? 0 ?></h3>
                            </div>
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300">
                                <i data-lucide="users" class="w-6 h-6"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Tests -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Total Tests</p>
                                <h3 class="text-2xl font-bold mt-1 dark:text-white"><?= $stats['total_tests'] ?? 0 ?></h3>
                            </div>
                            <div class="p-3 rounded-full bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-300">
                                <i data-lucide="file-text" class="w-6 h-6"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Test Attempts -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Test Attempts</p>
                                <h3 class="text-2xl font-bold mt-1 dark:text-white"><?= $stats['total_attempts'] ?? 0 ?></h3>
                            </div>
                            <div class="p-3 rounded-full bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-300">
                                <i data-lucide="activity" class="w-6 h-6"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Average Performance -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Avg. Performance</p>
                                <h3 class="text-2xl font-bold mt-1 dark:text-white">
                                    <?= formatNumber($stats['avg_speed'] ?? 0) ?> WPM / <?= formatNumber($stats['avg_accuracy'] ?? 0) ?>%
                                </h3>
                            </div>
                            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-300">
                                <i data-lucide="zap" class="w-6 h-6"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Recent Test Attempts -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold dark:text-white">Recent Test Attempts</h3>
                            <a href="performance-reports.php" class="text-sm text-blue-600 hover:underline dark:text-blue-400">View All</a>
                        </div>
                        
                        <div class="space-y-4">
                            <?php if (!empty($recent_tests)): ?>
                                <?php foreach ($recent_tests as $test): 
                                    $submission_date = date('M d, H:i', strtotime($test['submission_time']));
                                ?>
                                <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg dark:hover:bg-gray-700">
                                    <div>
                                        <h4 class="font-medium dark:text-white"><?= htmlspecialchars($test['full_name']) ?></h4>
                                        <p class="text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($test['test_name']) ?></p>
                                        <span class="text-xs text-gray-500 dark:text-gray-400"><?= $submission_date ?></span>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-medium dark:text-white"><?= $test['typing_speed_wpm'] ?> WPM</div>
                                        <div class="text-sm <?= $test['accuracy'] < 80 ? 'text-red-600' : 'text-green-600' ?> dark:text-gray-300">
                                            <?= $test['accuracy'] ?>%
                                        </div>
                                    </div>
                                    <a href="student_result.php?result_id=<?= $test['result_id'] ?>" class="ml-2 text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                        <i data-lucide="chevron-right" class="w-5 h-5"></i>
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                    <i data-lucide="file-text" class="w-8 h-8 mx-auto mb-2"></i>
                                    <p>No test attempts yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Recent Registrations -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                        <h3 class="text-lg font-semibold mb-4 dark:text-white">Recent Registrations</h3>
                        
                        <div class="space-y-4">
                            <?php if (!empty($recent_registrations)): ?>
                                <?php foreach ($recent_registrations as $user): 
                                    $reg_date = date('M d, Y', strtotime($user['created_at']));
                                ?>
                                <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg dark:hover:bg-gray-700">
                                    <div>
                                        <h4 class="font-medium dark:text-white"><?= htmlspecialchars($user['full_name']) ?></h4>
                                        <p class="text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($user['email']) ?></p>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">Joined <?= $reg_date ?></span>
                                    </div>
                                    <a href="manage-students.php?user_id=<?= $user['user_id'] ?>" class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
                                        View
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                    <i data-lucide="user-plus" class="w-8 h-8 mx-auto mb-2"></i>
                                    <p>No recent registrations</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- System Overview -->
                <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                    <h3 class="text-lg font-semibold mb-4 dark:text-white">System Overview</h3>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-medium mb-2 dark:text-white">Performance Distribution</h4>
                            <div class="h-64 bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                                <canvas id="performanceChart"></canvas>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-medium mb-2 dark:text-white">Activity Trends</h4>
                            <div class="h-64 bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                                <canvas id="activityChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                    <h3 class="text-lg font-semibold mb-4 dark:text-white">Quick Actions</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <a href="manage-tests.php" class="p-4 bg-blue-50 rounded-lg border border-blue-200 hover:bg-blue-100 dark:bg-blue-900/20 dark:hover:bg-blue-900/30 dark:border-blue-800">
                            <div class="flex items-center gap-3">
                                <div class="p-2 rounded-full bg-blue-100 text-blue-600 dark:bg-blue-800/30 dark:text-blue-300">
                                    <i data-lucide="file-text" class="w-5 h-5"></i>
                                </div>
                                <span class="font-medium dark:text-white">Manage Tests</span>
                            </div>
                        </a>
                        <a href="manage-students.php" class="p-4 bg-green-50 rounded-lg border border-green-200 hover:bg-green-100 dark:bg-green-900/20 dark:hover:bg-green-900/30 dark:border-green-800">
                            <div class="flex items-center gap-3">
                                <div class="p-2 rounded-full bg-green-100 text-green-600 dark:bg-green-800/30 dark:text-green-300">
                                    <i data-lucide="users" class="w-5 h-5"></i>
                                </div>
                                <span class="font-medium dark:text-white">Manage Users</span>
                            </div>
                        </a>
                        <a href="manage-exams.php" class="p-4 bg-purple-50 rounded-lg border border-purple-200 hover:bg-purple-100 dark:bg-purple-900/20 dark:hover:bg-purple-900/30 dark:border-purple-800">
                            <div class="flex items-center gap-3">
                                <div class="p-2 rounded-full bg-purple-100 text-purple-600 dark:bg-purple-800/30 dark:text-purple-300">
                                    <i data-lucide="book-open" class="w-5 h-5"></i>
                                </div>
                                <span class="font-medium dark:text-white">Manage Exams</span>
                            </div>
                        </a>
                        <a href="system-settings.php" class="p-4 bg-yellow-50 rounded-lg border border-yellow-200 hover:bg-yellow-100 dark:bg-yellow-900/20 dark:hover:bg-yellow-900/30 dark:border-yellow-800">
                            <div class="flex items-center gap-3">
                                <div class="p-2 rounded-full bg-yellow-100 text-yellow-600 dark:bg-yellow-800/30 dark:text-yellow-300">
                                    <i data-lucide="settings" class="w-5 h-5"></i>
                                </div>
                                <span class="font-medium dark:text-white">System Settings</span>
                            </div>
                        </a>
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

        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Performance Distribution Chart
            const performanceCtx = document.getElementById('performanceChart').getContext('2d');
            new Chart(performanceCtx, {
                type: 'bar',
                data: {
                    labels: ['0-20 WPM', '21-40 WPM', '41-60 WPM', '61+ WPM'],
                    datasets: [{
                        label: 'Students',
                        data: [15, 32, 28, 10], // Replace with actual data from your database
                        backgroundColor: 'rgba(59, 130, 246, 0.7)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Students'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Typing Speed Range'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
            
            // Activity Trends Chart
            const activityCtx = document.getElementById('activityChart').getContext('2d');
            new Chart(activityCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [
                        {
                            label: 'Test Attempts',
                            data: [45, 60, 75, 82, 90, 105],
                            borderColor: 'rgba(16, 185, 129, 0.8)',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Registrations',
                            data: [10, 15, 12, 18, 20, 25],
                            borderColor: 'rgba(99, 102, 241, 0.8)',
                            backgroundColor: 'rgba(99, 102, 241, 0.1)',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Count'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Month'
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>