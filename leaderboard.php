<?php
// leaderboard.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database Connection
require 'backend/config.php';

// Get all exams for filter dropdown
$exams_query = "SELECT exam_id, exam_name FROM exams ORDER BY exam_name";
$exams_result = $conn->query($exams_query);
$all_exams = $exams_result->fetch_all(MYSQLI_ASSOC);

// Get all tests for filter dropdown
$tests_query = "SELECT test_id, test_name FROM tests ORDER BY test_name";
$tests_result = $conn->query($tests_query);
$all_tests = $tests_result->fetch_all(MYSQLI_ASSOC);

// Check filters
$exam_filter = isset($_GET['exam_filter']) ? intval($_GET['exam_filter']) : 0;
$test_filter = isset($_GET['test_filter']) ? intval($_GET['test_filter']) : 0;
$time_filter = isset($_GET['time_filter']) ? $_GET['time_filter'] : 'all';

// Get overall leaderboard data
$overall_query = "SELECT 
                u.user_id,
                u.student_id,
                u.full_name,
                COUNT(r.result_id) as tests_taken,
                AVG(r.typing_speed_wpm) as avg_speed,
                AVG(r.accuracy) as avg_accuracy,
                MAX(r.typing_speed_wpm) as max_speed,
                MAX(r.accuracy) as max_accuracy
                FROM users u
                JOIN test_results r ON u.user_id = r.user_id
                WHERE u.role = 'student'";

// Apply filters if set
if ($exam_filter > 0) {
    $overall_query .= " AND r.test_id IN (SELECT test_id FROM tests WHERE exam_id = $exam_filter)";
}
if ($test_filter > 0) {
    $overall_query .= " AND r.test_id = $test_filter";
}
if ($time_filter === 'month') {
    $overall_query .= " AND r.submission_time >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
} elseif ($time_filter === 'week') {
    $overall_query .= " AND r.submission_time >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
}

$overall_query .= " GROUP BY u.user_id 
                    ORDER BY avg_speed DESC, avg_accuracy DESC 
                    LIMIT 50";

$overall_leaderboard = $conn->query($overall_query)->fetch_all(MYSQLI_ASSOC);

// Get test-wise leaderboard if test is selected
$test_leaderboard = [];
$selected_test = null;
if ($test_filter > 0) {
    $test_query = "SELECT test_name FROM tests WHERE test_id = ?";
    $stmt = $conn->prepare($test_query);
    $stmt->bind_param("i", $test_filter);
    $stmt->execute();
    $selected_test = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $test_leaderboard_query = "SELECT 
                            r.result_id,
                            u.user_id,
                            u.student_id,
                            u.full_name,
                            r.typing_speed_wpm as speed,
                            r.accuracy,
                            r.time_taken,
                            r.submission_time
                            FROM test_results r
                            JOIN users u ON r.user_id = u.user_id
                            WHERE r.test_id = ?
                            ORDER BY r.typing_speed_wpm DESC, r.accuracy DESC
                            LIMIT 50";
    
    $stmt = $conn->prepare($test_leaderboard_query);
    $stmt->bind_param("i", $test_filter);
    $stmt->execute();
    $test_leaderboard = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

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
    <title>Leaderboard - StenoPlus</title>
    <!-- Favicon -->
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
        
        /* Leaderboard specific styles */
        .rank-1 { background-color: #fef3c7; }
        .rank-2 { background-color: #e5e7eb; }
        .rank-3 { background-color: #fcd34d; }
        .dark .rank-1 { background-color: #92400e; }
        .dark .rank-2 { background-color: #374151; }
        .dark .rank-3 { background-color: #b45309; }
        
        .medal-gold { color: #f59e0b; }
        .medal-silver { color: #9ca3af; }
        .medal-bronze { color: #b45309; }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #888; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #555; }
    </style>
    <script>
        tailwind.config = {
            darkMode: 'class'
        };
    </script>
</head>
<body class="flex">

    <!-- Sidebar -->
    <?php include 'student-sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="md:ml-64 w-full lg:p-6 md:p-0">
        <!-- Top Bar -->
        <header class="flex justify-between items-center bg-white shadow p-4 rounded-none lg:rounded-lg dark:bg-gray-800">
            <!-- Hamburger Menu (Only for Mobile) -->
            <button id="openSidebar" class="md:hidden">
                <i data-lucide="menu"></i>
            </button>

            <h2 class="text-xl font-semibold dark:text-white">Leaderboard</h2>
            
            <div class="flex items-center space-x-4">
                <i data-lucide="bell" class="cursor-pointer dark:text-white"></i>
                <i data-lucide="moon" id="darkModeToggle" class="cursor-pointer dark:text-white"></i>
                
                <!-- Profile Dropdown -->
                <?php require 'profile-dropdown.php'; ?>
            </div>
        </header>

        <!-- Leaderboard Content -->
        <section class="mt-6 p-6 lg:p-0">
            <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                <!-- Filters -->
                <div class="mb-6">
                    <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label for="exam_filter" class="block text-sm font-medium mb-1 dark:text-white">Filter by Exam</label>
                            <select name="exam_filter" id="exam_filter" class="w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:text-white dark:border-gray-600">
                                <option value="0">All Exams</option>
                                <?php foreach ($all_exams as $exam): ?>
                                <option value="<?= $exam['exam_id'] ?>" <?= $exam_filter == $exam['exam_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($exam['exam_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="test_filter" class="block text-sm font-medium mb-1 dark:text-white">Filter by Test</label>
                            <select name="test_filter" id="test_filter" class="w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:text-white dark:border-gray-600">
                                <option value="0">All Tests</option>
                                <?php foreach ($all_tests as $test): ?>
                                <option value="<?= $test['test_id'] ?>" <?= $test_filter == $test['test_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($test['test_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="time_filter" class="block text-sm font-medium mb-1 dark:text-white">Time Period</label>
                            <select name="time_filter" id="time_filter" class="w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:text-white dark:border-gray-600">
                                <option value="all" <?= $time_filter === 'all' ? 'selected' : '' ?>>All Time</option>
                                <option value="month" <?= $time_filter === 'month' ? 'selected' : '' ?>>Last Month</option>
                                <option value="week" <?= $time_filter === 'week' ? 'selected' : '' ?>>Last Week</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 w-full">
                                Apply Filters
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Overall Leaderboard -->
                <div class="mb-8">
                    <h3 class="text-xl font-bold mb-4 dark:text-white">Overall Performance Leaderboard</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white dark:bg-gray-800">
                            <thead>
                                <tr class="bg-gray-200 dark:bg-gray-700">
                                    <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Rank</th>
                                    <th class="border border-gray-300 px-4 py-2 text-left dark:border-gray-600">Student</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Tests Taken</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Avg. Speed</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Avg. Accuracy</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Best Speed</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Best Accuracy</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($overall_leaderboard as $index => $student): 
                                    $rank = $index + 1;
                                    $rank_class = '';
                                    if ($rank === 1) $rank_class = 'rank-1';
                                    elseif ($rank === 2) $rank_class = 'rank-2';
                                    elseif ($rank === 3) $rank_class = 'rank-3';
                                ?>
                                <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-700 dark:border-gray-600 <?= $rank_class ?>">
                                    <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">
                                        <?php if ($rank === 1): ?>
                                            <i data-lucide="award" class="w-5 h-5 mx-auto medal-gold"></i>
                                        <?php elseif ($rank === 2): ?>
                                            <i data-lucide="award" class="w-5 h-5 mx-auto medal-silver"></i>
                                        <?php elseif ($rank === 3): ?>
                                            <i data-lucide="award" class="w-5 h-5 mx-auto medal-bronze"></i>
                                        <?php else: ?>
                                            <?= $rank ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-2 dark:border-gray-600">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center dark:bg-blue-900/30">
                                                <span class="text-blue-800 dark:text-blue-200 font-medium">
                                                    <?= substr($student['full_name'], 0, 1) ?>
                                                </span>
                                            </div>
                                            <div class="ml-4">
                                                <div class="font-medium dark:text-white"><?= htmlspecialchars($student['full_name']) ?></div>
                                                <div class="text-sm text-gray-500 dark:text-gray-300">ID: <?= $student['student_id'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600"><?= $student['tests_taken'] ?></td>
                                    <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600"><?= round($student['avg_speed'], 1) ?> WPM</td>
                                    <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600"><?= round($student['avg_accuracy'], 1) ?>%</td>
                                    <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600"><?= round($student['max_speed'], 1) ?> WPM</td>
                                    <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600"><?= round($student['max_accuracy'], 1) ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Test-wise Leaderboard -->
                <?php if ($test_filter > 0): ?>
                <div class="mb-8">
                    <h3 class="text-xl font-bold mb-4 dark:text-white">
                        Test-wise Leaderboard: <?= htmlspecialchars($selected_test['test_name']) ?>
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white dark:bg-gray-800">
                            <thead>
                                <tr class="bg-gray-200 dark:bg-gray-700">
                                    <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Rank</th>
                                    <th class="border border-gray-300 px-4 py-2 text-left dark:border-gray-600">Student</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Speed (WPM)</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Accuracy</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Time Taken</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Submitted On</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($test_leaderboard as $index => $result): 
                                    $rank = $index + 1;
                                    $rank_class = '';
                                    if ($rank === 1) $rank_class = 'rank-1';
                                    elseif ($rank === 2) $rank_class = 'rank-2';
                                    elseif ($rank === 3) $rank_class = 'rank-3';
                                ?>
                                <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-700 dark:border-gray-600 <?= $rank_class ?>">
                                    <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">
                                        <?php if ($rank === 1): ?>
                                            <i data-lucide="award" class="w-5 h-5 mx-auto medal-gold"></i>
                                        <?php elseif ($rank === 2): ?>
                                            <i data-lucide="award" class="w-5 h-5 mx-auto medal-silver"></i>
                                        <?php elseif ($rank === 3): ?>
                                            <i data-lucide="award" class="w-5 h-5 mx-auto medal-bronze"></i>
                                        <?php else: ?>
                                            <?= $rank ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-2 dark:border-gray-600">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center dark:bg-blue-900/30">
                                                <span class="text-blue-800 dark:text-blue-200 font-medium">
                                                    <?= substr($result['full_name'], 0, 1) ?>
                                                </span>
                                            </div>
                                            <div class="ml-4">
                                                <div class="font-medium dark:text-white"><?= htmlspecialchars($result['full_name']) ?></div>
                                                <div class="text-sm text-gray-500 dark:text-gray-300">ID: <?= $result['student_id'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600"><?= $result['speed'] ?></td>
                                    <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600"><?= $result['accuracy'] ?>%</td>
                                    <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600"><?= formatTime($result['time_taken']) ?></td>
                                    <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">
                                        <?= date('d M Y, H:i', strtotime($result['submission_time'])) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
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

        // Update test dropdown based on exam selection
        document.getElementById('exam_filter').addEventListener('change', function() {
            const examId = this.value;
            const testDropdown = document.getElementById('test_filter');
            
            if (examId === '0') {
                // Reset to all tests if no exam selected
                for (let i = 1; i < testDropdown.options.length; i++) {
                    testDropdown.options[i].style.display = '';
                }
            } else {
                // Hide tests that don't belong to selected exam
                // In a real implementation, you would fetch tests via AJAX
                // This is a simplified version that assumes all tests are loaded
                for (let i = 1; i < testDropdown.options.length; i++) {
                    // In practice, you would need to know which tests belong to which exam
                    // For now, we'll just show all tests
                    testDropdown.options[i].style.display = '';
                }
            }
        });
    </script>
</body>
</html>
