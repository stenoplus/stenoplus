<?php
// student_dashboard.php
session_start();

// Check if user is logged in and is a student
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
//     header("Location: login.php");
//     exit();
// }

// Database Connection
require 'backend/config.php';

$user_id = $_SESSION['user_id'];

// Get student statistics
$stats_query = "SELECT 
                COUNT(r.result_id) as tests_taken,
                AVG(r.typing_speed_wpm) as avg_speed,
                AVG(r.accuracy) as avg_accuracy,
                MAX(r.typing_speed_wpm) as best_speed,
                MAX(r.accuracy) as best_accuracy
                FROM test_results r
                WHERE r.user_id = ?";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get recent test attempts
$recent_query = "SELECT 
                r.result_id, 
                t.test_name,
                r.typing_speed_wpm,
                r.accuracy,
                r.submission_time,
                e.exam_name
                FROM test_results r
                JOIN tests t ON r.test_id = t.test_id
                LEFT JOIN exams e ON t.exam_id = e.exam_id
                WHERE r.user_id = ?
                ORDER BY r.submission_time DESC
                LIMIT 5";
$stmt = $conn->prepare($recent_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_tests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get recommended tests
$recommended_query = "SELECT 
                    t.test_id,
                    t.test_name,
                    e.exam_name,
                    COUNT(r.result_id) as attempts
                    FROM tests t
                    LEFT JOIN exams e ON t.exam_id = e.exam_id
                    LEFT JOIN test_results r ON t.test_id = r.test_id AND r.user_id = ?
                    WHERE (r.result_id IS NULL OR r.accuracy < 90)
                    GROUP BY t.test_id
                    ORDER BY attempts ASC, t.test_id DESC
                    LIMIT 3";
$stmt = $conn->prepare($recommended_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recommended_tests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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
    <title>Student Dashboard - StenoPlus</title>
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

    <!-- Student Sidebar -->
    <?php include 'student-sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="md:ml-64 w-full lg:p-6 md:p-0">
        <!-- Top Bar -->
        <header class="flex justify-between items-center bg-white shadow p-4 rounded-none lg:rounded-lg dark:bg-gray-800">
            <button id="openSidebar" class="md:hidden">
                <i data-lucide="menu"></i>
            </button>

            <h2 class="text-xl font-semibold dark:text-white">Dashboard</h2>
            
            <div class="flex items-center space-x-4">
                <i data-lucide="bell" class="cursor-pointer dark:text-white"></i>
                <i data-lucide="moon" id="darkModeToggle" class="cursor-pointer dark:text-white"></i>
                
                <!-- Profile Dropdown -->
                <?php require 'profile-dropdown.php'; ?>
            </div>
        </header>

        <!-- Dashboard Content -->
        <section class="mt-6 p-6 lg:p-0">
            <div class="space-y-6">
                <!-- Welcome Banner -->
                <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg p-6 text-white shadow">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                        <div>
                            <h1 class="text-2xl font-bold">Welcome back, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Student') ?>!</h1>
                            <p class="mt-2 opacity-90">Keep practicing to improve your typing skills</p>
                        </div>
                        <a href="skills-test.php" class="mt-4 md:mt-0 px-4 py-2 bg-white text-blue-600 rounded-lg font-medium hover:bg-gray-100 flex items-center gap-2">
                            <i data-lucide="plus" class="w-4 h-4"></i> Take New Test
                        </a>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Tests Taken -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Tests Taken</p>
                                <h3 class="text-2xl font-bold mt-1 dark:text-white"><?= $stats['tests_taken'] ?? 0 ?></h3>
                            </div>
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300">
                                <i data-lucide="file-text" class="w-6 h-6"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Average Speed -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Average Speed</p>
                                <h3 class="text-2xl font-bold mt-1 dark:text-white"><?= formatNumber($stats['avg_speed'] ?? 0) ?> WPM</h3>
                            </div>
                            <div class="p-3 rounded-full bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-300">
                                <i data-lucide="zap" class="w-6 h-6"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Average Accuracy -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Average Accuracy</p>
                                <h3 class="text-2xl font-bold mt-1 dark:text-white"><?= formatNumber($stats['avg_accuracy'] ?? 0) ?>%</h3>
                            </div>
                            <div class="p-3 rounded-full bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-300">
                                <i data-lucide="target" class="w-6 h-6"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Best Speed -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Best Speed</p>
                                <h3 class="text-2xl font-bold mt-1 dark:text-white"><?= formatNumber($stats['best_speed'] ?? 0) ?> WPM</h3>
                            </div>
                            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-300">
                                <i data-lucide="award" class="w-6 h-6"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity and Recommended Tests -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Recent Test Attempts -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold dark:text-white">Recent Test Attempts</h3>
                            <a href="my-performance.php" class="text-sm text-blue-600 hover:underline dark:text-blue-400">View All</a>
                        </div>
                        
                        <div class="space-y-4">
                            <?php if (!empty($recent_tests)): ?>
                                <?php foreach ($recent_tests as $test): 
                                    $submission_date = date('M d, H:i', strtotime($test['submission_time']));
                                    $exam_badge_color = match($test['exam_name']) {
                                        'SSC' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                        'Railway' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                        'Courts' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                                        'Banking' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                        default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                    };
                                ?>
                                <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg dark:hover:bg-gray-700">
                                    <div>
                                        <h4 class="font-medium dark:text-white"><?= htmlspecialchars($test['test_name']) ?></h4>
                                        <div class="flex items-center gap-2 mt-1">
                                            <?php if ($test['exam_name']): ?>
                                            <span class="exam-badge <?= $exam_badge_color ?>">
                                                <?= htmlspecialchars($test['exam_name']) ?>
                                            </span>
                                            <?php endif; ?>
                                            <span class="text-sm text-gray-500 dark:text-gray-400"><?= $submission_date ?></span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-medium dark:text-white"><?= $test['typing_speed_wpm'] ?> WPM</div>
                                        <div class="text-sm <?= $test['accuracy'] < 80 ? 'text-red-600' : 'text-green-600' ?> dark:text-gray-300">
                                            <?= $test['accuracy'] ?>%
                                        </div>
                                    </div>
                                    <a href="my-result.php?result_id=<?= $test['result_id'] ?>" class="ml-2 text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                        <i data-lucide="chevron-right" class="w-5 h-5"></i>
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                    <i data-lucide="file-text" class="w-8 h-8 mx-auto mb-2"></i>
                                    <p>No test attempts yet</p>
                                    <a href="skills-test.php" class="text-blue-600 hover:underline dark:text-blue-400">Take your first test</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Recommended Tests -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                        <h3 class="text-lg font-semibold mb-4 dark:text-white">Recommended For You</h3>
                        
                        <div class="space-y-4">
                            <?php if (!empty($recommended_tests)): ?>
                                <?php foreach ($recommended_tests as $test): 
                                    $exam_badge_color = match($test['exam_name']) {
                                        'SSC' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                        'Railway' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                        'Courts' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                                        'Banking' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                        default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                    };
                                ?>
                                <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg dark:hover:bg-gray-700">
                                    <div>
                                        <h4 class="font-medium dark:text-white"><?= htmlspecialchars($test['test_name']) ?></h4>
                                        <?php if ($test['exam_name']): ?>
                                        <span class="exam-badge <?= $exam_badge_color ?> mt-1">
                                            <?= htmlspecialchars($test['exam_name']) ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <a href="dictation.php?test_id=<?= $test['test_id'] ?>" class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
                                        Take Test
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                    <i data-lucide="check-circle" class="w-8 h-8 mx-auto mb-2"></i>
                                    <p>No recommendations available</p>
                                    <p class="text-sm">You've attempted all available tests</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Progress Charts -->
                <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                    <h3 class="text-lg font-semibold mb-4 dark:text-white">Your Progress</h3>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-medium mb-2 dark:text-white">Typing Speed (WPM)</h4>
                            <div class="h-64 bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                                <!-- Speed chart will be loaded here -->
                                <canvas id="speedChart"></canvas>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-medium mb-2 dark:text-white">Accuracy (%)</h4>
                            <div class="h-64 bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                                <!-- Accuracy chart will be loaded here -->
                                <canvas id="accuracyChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        <?php if (!empty($recent_tests)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Prepare data for charts
            const testNames = <?= json_encode(array_column($recent_tests, 'test_name')) ?>;
            const speeds = <?= json_encode(array_column($recent_tests, 'typing_speed_wpm')) ?>;
            const accuracies = <?= json_encode(array_column($recent_tests, 'accuracy')) ?>;
            const dates = <?= json_encode(array_map(function($t) { 
                return date('M d', strtotime($t['submission_time'])); 
            }, $recent_tests)) ?>;
            
            // Speed Chart
            const speedCtx = document.getElementById('speedChart').getContext('2d');
            new Chart(speedCtx, {
                type: 'line',
                data: {
                    labels: dates.reverse(),
                    datasets: [{
                        label: 'Typing Speed (WPM)',
                        data: speeds.reverse(),
                        borderColor: 'rgba(59, 130, 246, 0.8)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                        pointRadius: 4,
                        pointHoverRadius: 6
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
                                text: 'Words Per Minute'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Test Date'
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
            
            // Accuracy Chart
            const accuracyCtx = document.getElementById('accuracyChart').getContext('2d');
            new Chart(accuracyCtx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [{
                        label: 'Accuracy (%)',
                        data: accuracies.reverse(),
                        borderColor: 'rgba(16, 185, 129, 0.8)',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: 'rgba(16, 185, 129, 1)',
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: false,
                            min: Math.max(0, Math.min(...accuracies) - 10),
                            max: 100,
                            title: {
                                display: true,
                                text: 'Accuracy Percentage'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Test Date'
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
        });
        <?php endif; ?>
    </script>
</body>
</html>
