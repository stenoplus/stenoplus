<?php
session_start();

// Check if user is logged in and is a student
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
//     header("Location: login.php");
//     exit();
// }


// Database Connection
require 'backend/config.php';

$user_id = $_SESSION['user_id'];

// Get all tests attempted by the student with performance metrics
$tests_query = "SELECT 
                t.test_id, 
                t.test_name, 
                t.transcript_duration,
                e.exam_name,
                c.category_name,
                r.typing_speed_wpm, 
                r.accuracy,
                r.error_percentage,
                r.time_taken,
                r.backspace_count,
                r.submission_time,
                r.result_id
                FROM test_results r
                JOIN tests t ON r.test_id = t.test_id
                LEFT JOIN exams e ON t.exam_id = e.exam_id
                LEFT JOIN categories c ON t.category_id = c.category_id
                WHERE r.user_id = ?
                ORDER BY r.submission_time DESC";

$stmt = $conn->prepare($tests_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$attempted_tests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate overall statistics
$total_tests = count($attempted_tests);
$total_attempts = $total_tests; // Since we're showing one attempt per test
$avg_speed = $total_tests > 0 ? round(array_sum(array_column($attempted_tests, 'typing_speed_wpm')) / $total_tests, 1) : 0;
$avg_accuracy = $total_tests > 0 ? round(array_sum(array_column($attempted_tests, 'accuracy')) / $total_tests, 1) : 0;

// Function to format time
function formatTime($seconds) {
    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;
    return sprintf("%02d:%02d", $minutes, $seconds);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Performance - StenoPlus</title>
    <!-- Favicon -->
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
            scrollbar-width: thin;
            scrollbar-color: #696969 #002147;
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
        .dark .logo {
            background-color:#F9F9F9 !important;
        }
        
        /* Performance highlight classes */
        .highlight-good { background-color: #dcfce7; }
        .highlight-average { background-color: #fef9c3; }
        .highlight-poor { background-color: #fee2e2; }
        .dark .highlight-good { background-color: #14532d; }
        .dark .highlight-average { background-color: #713f12; }
        .dark .highlight-poor { background-color: #7f1d1d; }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #888; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #555; }
        
        /* Exam filter badge */
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

    <!-- Sidebar (Desktop & Mobile) -->
    <?php include 'student-sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="md:ml-64 w-full lg:p-6 md:p-0">
        <!-- Top Bar -->
        <header class="flex justify-between items-center bg-white shadow p-4 rounded-none lg:rounded-lg dark:bg-gray-800">
            <!-- Hamburger Menu (Only for Mobile) -->
            <button id="openSidebar" class="md:hidden">
                <i data-lucide="menu"></i>
            </button>

            <h2 class="text-xl font-semibold dark:text-white">My Performance</h2>
            
            <div class="flex items-center space-x-4">
                <i data-lucide="bell" class="cursor-pointer dark:text-white"></i>
                <i data-lucide="moon" id="darkModeToggle" class="cursor-pointer dark:text-white"></i>
                
                <!-- Profile Dropdown -->
                <?php require 'profile-dropdown.php'; ?>
            </div>
        </header>

        <!-- Performance Dashboard -->
        <section class="mt-6 p-6 lg:p-0">
            <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                <h3 class="text-lg font-semibold mb-6 dark:text-white">My Test Performance</h3>
                
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200 dark:bg-blue-900/20">
                        <div class="text-sm font-medium text-blue-800 dark:text-blue-200">Tests Attempted</div>
                        <div class="text-2xl font-bold dark:text-white"><?= $total_tests ?></div>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg border border-green-200 dark:bg-green-900/20">
                        <div class="text-sm font-medium text-green-800 dark:text-green-200">Total Attempts</div>
                        <div class="text-2xl font-bold dark:text-white"><?= $total_attempts ?></div>
                    </div>
                    <div class="bg-purple-50 p-4 rounded-lg border border-purple-200 dark:bg-purple-900/20">
                        <div class="text-sm font-medium text-purple-800 dark:text-purple-200">Avg. Speed</div>
                        <div class="text-2xl font-bold dark:text-white"><?= $avg_speed ?> WPM</div>
                    </div>
                    <div class="bg-orange-50 p-4 rounded-lg border border-orange-200 dark:bg-orange-900/20">
                        <div class="text-sm font-medium text-orange-800 dark:text-orange-200">Avg. Accuracy</div>
                        <div class="text-2xl font-bold dark:text-white"><?= $avg_accuracy ?>%</div>
                    </div>
                </div>

                <!-- Performance Charts -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Speed Progress Chart -->
                    <div class="bg-gray-50 p-4 rounded-lg dark:bg-gray-700">
                        <h4 class="font-semibold mb-3 text-gray-800 dark:text-white">My Typing Speed Progress</h4>
                        <canvas id="speedProgressChart" height="250"></canvas>
                    </div>
                    
                    <!-- Accuracy Progress Chart -->
                    <div class="bg-gray-50 p-4 rounded-lg dark:bg-gray-700">
                        <h4 class="font-semibold mb-3 text-gray-800 dark:text-white">My Accuracy Progress</h4>
                        <canvas id="accuracyProgressChart" height="250"></canvas>
                    </div>
                </div>

                <!-- Attempted Tests Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white dark:bg-gray-800">
                        <thead>
                            <tr class="bg-gray-200 dark:bg-gray-700">
                                <th class="border border-gray-300 px-4 py-2 text-left dark:border-gray-600">Test Name</th>
                                <th class="border border-gray-300 px-4 py-2 text-left dark:border-gray-600">Exam</th>
                                <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Duration</th>
                                <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Speed (WPM)</th>
                                <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Accuracy</th>
                                <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Errors</th>
                                <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Time Taken</th>
                                <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Submitted On</th>
                                <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attempted_tests as $test): 
                                $duration_formatted = formatTime($test['transcript_duration'] * 60);
                                $time_taken_formatted = formatTime($test['time_taken']);
                                $submission_date = date('d M Y, H:i', strtotime($test['submission_time']));
                                $exam_badge_color = match($test['exam_name']) {
                                    'SSC' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                    'Railway' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                    'Courts' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                                    'Banking' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                    default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                };
                                
                                $speed_class = $test['typing_speed_wpm'] < 30 ? 'highlight-poor' : 
                                             ($test['typing_speed_wpm'] < 50 ? 'highlight-average' : 'highlight-good');
                                $accuracy_class = $test['accuracy'] < 80 ? 'highlight-poor' : 
                                                ($test['accuracy'] < 90 ? 'highlight-average' : 'highlight-good');
                            ?>
                            <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-700 dark:border-gray-600">
                                <td class="border border-gray-300 px-4 py-2 dark:border-gray-600"><?= htmlspecialchars($test['test_name']) ?></td>
                                <td class="border border-gray-300 px-4 py-2 dark:border-gray-600">
                                    <?php if ($test['exam_name']): ?>
                                    <span class="exam-badge <?= $exam_badge_color ?>">
                                        <?= htmlspecialchars($test['exam_name']) ?>
                                    </span>
                                    <?php else: ?>
                                    N/A
                                    <?php endif; ?>
                                </td>
                                <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600"><?= $duration_formatted ?></td>
                                <td class="border border-gray-300 px-4 py-2 text-center <?= $speed_class ?> dark:border-gray-600">
                                    <?= $test['typing_speed_wpm'] ?>
                                </td>
                                <td class="border border-gray-300 px-4 py-2 text-center <?= $accuracy_class ?> dark:border-gray-600">
                                    <?= $test['accuracy'] ?>%
                                </td>
                                <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600"><?= $test['error_percentage'] ?>%</td>
                                <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600"><?= $time_taken_formatted ?></td>
                                <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600"><?= $submission_date ?></td>
                                <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">
                                    <a href="my-result.php?result_id=<?= $test['result_id'] ?>" 
                                       class="text-blue-600 hover:underline dark:text-blue-400"
                                       title="View detailed result">
                                        <i data-lucide="file-text" class="w-4 h-4 inline"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($attempted_tests)): ?>
                            <tr>
                                <td colspan="9" class="border border-gray-300 px-4 py-4 text-center dark:border-gray-600">
                                    You haven't attempted any tests yet. Start practicing to see your performance here!
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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

        // Initialize Charts if we have data
        <?php if (!empty($attempted_tests)): ?>
        function initializeCharts() {
            // Prepare data for charts
            const testNames = <?= json_encode(array_column($attempted_tests, 'test_name')) ?>;
            const speeds = <?= json_encode(array_column($attempted_tests, 'typing_speed_wpm')) ?>;
            const accuracies = <?= json_encode(array_column($attempted_tests, 'accuracy')) ?>;
            const submissionDates = <?= json_encode(array_map(function($t) { 
                return date('M d', strtotime($t['submission_time'])); 
            }, $attempted_tests)) ?>;

            // Speed Progress Chart
            const speedCtx = document.getElementById('speedProgressChart').getContext('2d');
            document.getElementById('speedProgressChart').parentElement.style.position = 'relative';
            document.getElementById('speedProgressChart').parentElement.style.height = '250px';
            document.getElementById('speedProgressChart').parentElement.style.width = '100%';
            
            new Chart(speedCtx, {
                type: 'line',
                data: {
                    labels: submissionDates,
                    datasets: [{
                        label: 'Typing Speed (WPM)',
                        data: speeds,
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        borderColor: 'rgba(99, 102, 241, 0.8)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: 'rgba(99, 102, 241, 1)',
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
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                title: function(context) {
                                    return testNames[context[0].dataIndex];
                                }
                            }
                        }
                    }
                }
            });

            // Accuracy Progress Chart
            const accuracyCtx = document.getElementById('accuracyProgressChart').getContext('2d');
            document.getElementById('accuracyProgressChart').parentElement.style.position = 'relative';
            document.getElementById('accuracyProgressChart').parentElement.style.height = '250px';
            document.getElementById('accuracyProgressChart').parentElement.style.width = '100%';
            
            new Chart(accuracyCtx, {
                type: 'line',
                data: {
                    labels: submissionDates,
                    datasets: [{
                        label: 'Accuracy (%)',
                        data: accuracies,
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderColor: 'rgba(16, 185, 129, 0.8)',
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
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                title: function(context) {
                                    return testNames[context[0].dataIndex];
                                }
                            }
                        }
                    }
                }
            });
        }

        // Initialize charts when page loads
        document.addEventListener('DOMContentLoaded', initializeCharts);

        // Also initialize charts when navigating back to the page
        if (document.readyState === 'complete') {
            initializeCharts();
        } else {
            window.addEventListener('load', initializeCharts);
        }
        <?php endif; ?>
    </script>
</body>
</html>