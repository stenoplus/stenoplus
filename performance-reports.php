<?php
session_start();

// Check if user is logged in and is admin
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
//     header("Location: login.php");
//     exit();
// }

// Database Connection
require 'backend/config.php';

// Get all exams for filter dropdown
$exams_query = "SELECT exam_id, exam_name FROM exams ORDER BY exam_name";
$exams_result = $conn->query($exams_query);
$all_exams = $exams_result->fetch_all(MYSQLI_ASSOC);

// Check if exam filter is applied
$exam_filter = isset($_GET['exam_filter']) ? intval($_GET['exam_filter']) : 0;

// Get all tests with student participation counts and performance metrics
$tests_query = "SELECT 
                t.test_id, 
                t.test_name, 
                t.transcript_duration as duration,
                e.exam_name,
                COUNT(r.result_id) as student_count,
                AVG(r.typing_speed_wpm) as avg_speed, 
                AVG(r.accuracy) as avg_accuracy,
                MIN(r.submission_time) as first_attempt,
                MAX(r.submission_time) as last_attempt
                FROM tests t
                LEFT JOIN test_results r ON t.test_id = r.test_id
                LEFT JOIN exams e ON t.exam_id = e.exam_id
                WHERE (? = 0 OR t.exam_id = ?)
                GROUP BY t.test_id
                ORDER BY t.test_id DESC";

$stmt = $conn->prepare($tests_query);
$stmt->bind_param("ii", $exam_filter, $exam_filter);
$stmt->execute();
$tests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get detailed performance if test_id is selected
$selected_test = null;
$test_results = [];
if (isset($_GET['test_id'])) {
    $test_id = intval($_GET['test_id']);
    
    // Get test details with exam and category information
  $test_query = "SELECT t.*, e.exam_name, c.category_name, 
               t.transcript_duration as duration, t.dictation_duration
               FROM tests t
               LEFT JOIN exams e ON t.exam_id = e.exam_id
               LEFT JOIN categories c ON t.category_id = c.category_id
               WHERE t.test_id = ?";
    $stmt = $conn->prepare($test_query);
    $stmt->bind_param("i", $test_id);
    $stmt->execute();
    $selected_test = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Get student results for this test
    $performance_query = "SELECT 
                            r.result_id,
                            u.student_id, 
                            u.full_name, 
                            r.typing_speed_wpm, 
                            r.accuracy,
                            r.error_percentage,
                            r.time_taken,
                            r.backspace_count,
                            r.submission_time
                          FROM test_results r
                          JOIN users u ON r.user_id = u.user_id
                          WHERE r.test_id = ?
                          ORDER BY r.typing_speed_wpm DESC, r.accuracy DESC";
    $stmt = $conn->prepare($performance_query);
    $stmt->bind_param("i", $test_id);
    $stmt->execute();
    $test_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Calculate performance distribution for chart
    $speed_distribution = [0, 0, 0, 0]; // 0-20, 21-40, 41-60, 61+
    $accuracy_distribution = [0, 0, 0]; // <80%, 80-90%, >90%
    foreach ($test_results as $result) {
        // Speed distribution
        if ($result['typing_speed_wpm'] <= 20) $speed_distribution[0]++;
        elseif ($result['typing_speed_wpm'] <= 40) $speed_distribution[1]++;
        elseif ($result['typing_speed_wpm'] <= 60) $speed_distribution[2]++;
        else $speed_distribution[3]++;
        
        // Accuracy distribution
        if ($result['accuracy'] < 80) $accuracy_distribution[0]++;
        elseif ($result['accuracy'] <= 90) $accuracy_distribution[1]++;
        else $accuracy_distribution[2]++;
    }
}

// Calculate totals for dashboard
$total_tests = count($tests);
$total_attempts = array_sum(array_column($tests, 'student_count'));
$avg_speed = $total_attempts > 0 ? round(array_sum(array_column($tests, 'avg_speed')) / $total_tests, 1) : 0;
$avg_accuracy = $total_attempts > 0 ? round(array_sum(array_column($tests, 'avg_accuracy')) / $total_tests, 1) : 0;

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
    <title>Performance Reports - StenoPlus</title>
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
    <?php include 'sidebar.php';?>
    
    <!-- Main Content -->
    <main class="md:ml-64 w-full lg:p-6 md:p-0">
        <!-- Top Bar -->
        <header class="flex justify-between items-center bg-white shadow p-4 rounded-none lg:rounded-lg dark:bg-gray-800">
            <!-- Hamburger Menu (Only for Mobile) -->
            <button id="openSidebar" class="md:hidden">
                <i data-lucide="menu"></i>
            </button>

            <h2 class="text-xl font-semibold dark:text-white">Performance Reports</h2>
            
            <div class="flex items-center space-x-4">
                <i data-lucide="bell" class="cursor-pointer dark:text-white"></i>
                <i data-lucide="moon" id="darkModeToggle" class="cursor-pointer dark:text-white"></i>
                
                <!-- Profile Dropdown -->
                <div class="relative">
                    <img src="assets/images/student.png" alt="Profile" class="w-10 h-10 rounded-full cursor-pointer" id="profileBtn">
                    <div id="profileDropdown" class="hidden absolute right-0 bg-white shadow-lg rounded-md w-40 mt-2 dark:bg-gray-700">
                        <p class="p-2 text-sm dark:text-white"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></p>
                        <p class="p-2 text-xs text-gray-500 dark:text-gray-300">Role: Admin</p>
                        <hr class="dark:border-gray-600">
                        <li class="flex items-center space-x-2 p-2 text-sm hover:bg-gray-200 dark:hover:bg-gray-600">
                            <i data-lucide="user" class="mr-0 w-4 h-4"></i> 
                            <a href="#" class="dark:text-white">View Profile</a>
                        </li>
                        <li class="flex items-center space-x-2 p-2 hover:bg-red-600 hover:text-white rounded-b text-sm">
                            <i data-lucide="log-out" class="mr-0 w-4 h-4"></i> 
                            <a href="backend/authentication/logout.php" class="dark:text-white">Logout</a>
                        </li>
                    </div>
                </div>
            </div>
        </header>

        <!-- Performance Dashboard -->
        <section class="mt-6 p-6 lg:p-0">
            <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <h3 class="text-lg font-semibold dark:text-white">Test Performance Analytics</h3>
                    
                    <!-- Exam Filter Dropdown -->
                    <form method="get" class="mt-4 md:mt-0">
                        <input type="hidden" name="test_id" value="<?= $_GET['test_id'] ?? '' ?>">
                        <div class="flex items-center gap-2">
                            <label for="exam_filter" class="text-sm font-medium dark:text-white">Filter by Exam:</label>
                            <select name="exam_filter" id="exam_filter" onchange="this.form.submit()" 
                                    class="rounded-md border-gray-300 shadow-sm text-sm dark:bg-gray-700 dark:text-white dark:border-gray-600">
                                <option value="0" <?= $exam_filter == 0 ? 'selected' : '' ?>>All Exams</option>
                                <?php foreach ($all_exams as $exam): ?>
                                <option value="<?= $exam['exam_id'] ?>" <?= $exam_filter == $exam['exam_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($exam['exam_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($exam_filter > 0): ?>
                            <a href="performance-reports.php<?= isset($_GET['test_id']) ? '?test_id='.$_GET['test_id'] : '' ?>" 
                               class="text-sm text-red-600 hover:underline dark:text-red-400">
                                Clear
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200 dark:bg-blue-900/20">
                        <div class="text-sm font-medium text-blue-800 dark:text-blue-200">Total Tests</div>
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

                <!-- Tests List Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white dark:bg-gray-800">
                        <thead>
                            <tr class="bg-gray-200 dark:bg-gray-700">
                                <th class="border border-gray-300 px-4 py-2 text-left dark:border-gray-600">Test Name</th>
                                <th class="border border-gray-300 px-4 py-2 text-left dark:border-gray-600">Exam</th>
                                <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Duration</th>
                                <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Participants</th>
                                <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Avg. Speed</th>
                                <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Avg. Accuracy</th>
                                <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">First Attempt</th>
                                <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Last Attempt</th>
                                <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tests as $test): 
                                $duration_formatted = formatTime($test['duration'] * 60);
                                $first_attempt = $test['first_attempt'] ? date('d M Y', strtotime($test['first_attempt'])) : 'N/A';
                                $last_attempt = $test['last_attempt'] ? date('d M Y', strtotime($test['last_attempt'])) : 'N/A';
                                $exam_badge_color = match($test['exam_name']) {
                                    'SSC' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                    'Railway' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                    'Courts' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                                    'Banking' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                    default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                };
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
                                <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600"><?= $test['student_count'] ?></td>
                                <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600"><?= round($test['avg_speed'], 1) ?> WPM</td>
                                <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600"><?= round($test['avg_accuracy'], 1) ?>%</td>
                                <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600"><?= $first_attempt ?></td>
                                <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600"><?= $last_attempt ?></td>
                                <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">
                                    <a href="?test_id=<?= $test['test_id'] ?><?= $exam_filter > 0 ? '&exam_filter='.$exam_filter : '' ?>" 
                                       class="text-blue-600 hover:underline dark:text-blue-400">
                                        View Details
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Detailed Performance for Selected Test -->
                <?php if (!empty($selected_test)): ?>
                <div class="bg-white rounded-lg shadow p-6 mt-6 dark:bg-gray-800">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                        <div>
                            <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-2">
                                <?= htmlspecialchars($selected_test['test_name']) ?>
                            </h3>
                            <div class="flex flex-wrap gap-4 text-sm text-gray-600 dark:text-gray-300">
                                <?php if ($selected_test['exam_name']): ?>
                                <span>
                                    <strong>Exam:</strong> 
                                    <span class="exam-badge <?= match($selected_test['exam_name']) {
                                        'SSC' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                        'Railway' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                        'Courts' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                                        'Banking' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                        default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                    } ?>">
                                        <?= htmlspecialchars($selected_test['exam_name']) ?>
                                    </span>
                                </span>
                                <?php endif; ?>
                                <?php if ($selected_test['category_name']): ?>
                                <span><strong>Category:</strong> <?= htmlspecialchars($selected_test['category_name']) ?></span>
                                <?php endif; ?>
                                <span><strong>Duration:</strong> <?= formatTime($selected_test['duration'] * 60) ?></span>
                                <span><strong>Participants:</strong> <?= count($test_results) ?></span>
                            </div>
                        </div>
                        <div class="mt-4 md:mt-0 flex gap-2">
                            <a href="performance-reports.php<?= $exam_filter > 0 ? '?exam_filter='.$exam_filter : '' ?>" 
                               class="bg-gray-200 text-gray-800 px-4 py-2 rounded hover:bg-gray-300 dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600">
                                Back to All Tests
                            </a>
                            <button onclick="exportToCSV()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 flex items-center gap-2">
                                <i data-lucide="file-text" class="w-4 h-4"></i> Export
                            </button>
                        </div>
                    </div>

                    <!-- Performance Charts -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                        <!-- Speed Distribution Chart -->
                        <div class="bg-gray-50 p-4 rounded-lg dark:bg-gray-700">
                            <h4 class="font-semibold mb-3 text-gray-800 dark:text-white">Typing Speed Distribution</h4>
                            <canvas id="speedChart" height="250"></canvas>
                        </div>
                        
                        <!-- Accuracy Distribution Chart -->
                        <div class="bg-gray-50 p-4 rounded-lg dark:bg-gray-700">
                            <h4 class="font-semibold mb-3 text-gray-800 dark:text-white">Accuracy Distribution</h4>
                            <canvas id="accuracyChart" height="250"></canvas>
                        </div>
                    </div>

                    <!-- Performance Scatter Plot -->
                    <div class="bg-gray-50 p-4 rounded-lg mb-8 dark:bg-gray-700">
                        <h4 class="font-semibold mb-3 text-gray-800 dark:text-white">Accuracy vs Typing Speed</h4>
                        <div class="relative h-[400px]">
                            <canvas id="performanceScatter"></canvas>
                        </div>
                    </div>


                    <!-- Students Performance Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white dark:bg-gray-800">
                            <thead>
                                <tr class="bg-gray-200 dark:bg-gray-700">
                                    <th class="border border-gray-300 px-4 py-2 text-left dark:border-gray-600">Student ID</th>
                                    <th class="border border-gray-300 px-4 py-2 text-left dark:border-gray-600">Name</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Speed (WPM)</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Accuracy</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Errors</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Time Taken</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Backspaces</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Submitted On</th>
                                    <th class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($test_results as $result): 
                                    $speed_class = $result['typing_speed_wpm'] < 30 ? 'highlight-poor' : 
                                                 ($result['typing_speed_wpm'] < 50 ? 'highlight-average' : 'highlight-good');
                                    $accuracy_class = $result['accuracy'] < 80 ? 'highlight-poor' : 
                                                    ($result['accuracy'] < 90 ? 'highlight-average' : 'highlight-good');
                                    $time_taken_formatted = formatTime($result['time_taken']);
                                    $submission_date = date('d M Y, H:i', strtotime($result['submission_time']));
                                ?>
                                <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-700 dark:border-gray-600">
                                    <td class="border border-gray-300 px-4 py-2 dark:border-gray-600"><?= $result['student_id'] ?></td>
                                    <td class="border border-gray-300 px-4 py-2 dark:border-gray-600"><?= htmlspecialchars($result['full_name']) ?></td>
                                    <td class="border border-gray-300 px-4 py-2 text-center <?= $speed_class ?> dark:border-gray-600">
                                        <?= $result['typing_speed_wpm'] ?>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-2 text-center <?= $accuracy_class ?> dark:border-gray-600">
                                        <?= $result['accuracy'] ?>%
                                    </td>
                                    <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600"><?= $result['error_percentage'] ?>%</td>
                                    <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600"><?= $time_taken_formatted ?></td>
                                    <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600"><?= $result['backspace_count'] ?></td>
                                    <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600"><?= $submission_date ?></td>
                                    <td class="border border-gray-300 px-4 py-2 text-center dark:border-gray-600">
                                        <a href="student_result.php?result_id=<?= $result['result_id'] ?>" 
                                           class="text-blue-600 hover:underline dark:text-blue-400"
                                           title="View detailed result">
                                            <i data-lucide="file-text" class="w-4 h-4 inline"></i>
                                        </a>
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

    // Profile Dropdown Toggle
    document.getElementById("profileBtn").addEventListener("click", function () {
        document.getElementById("profileDropdown").classList.toggle("hidden");
    });

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

    // Chart instances
    let speedChart = null;
    let accuracyChart = null;
    let scatterChart = null;

    // Initialize Charts if we have data
    <?php if (!empty($selected_test) && !empty($test_results)): ?>
    function initializeCharts() {
        // Speed Distribution Chart (already fixed)
        const speedCtx = document.getElementById('speedChart').getContext('2d');
        if (speedChart) speedChart.destroy();
        document.getElementById('speedChart').parentElement.style.position = 'relative';
        document.getElementById('speedChart').parentElement.style.height = '250px';
        document.getElementById('speedChart').parentElement.style.width = '100%';
        speedChart = new Chart(speedCtx, {
            type: 'bar',
            data: {
                labels: ['0-20 WPM', '21-40 WPM', '41-60 WPM', '61+ WPM'],
                datasets: [{
                    label: 'Number of Students',
                    data: <?= json_encode($speed_distribution) ?>,
                    backgroundColor: [
                        'rgba(239, 68, 68, 0.7)',
                        'rgba(234, 179, 8, 0.7)',
                        'rgba(34, 197, 94, 0.7)',
                        'rgba(59, 130, 246, 0.7)'
                    ],
                    borderColor: [
                        'rgba(239, 68, 68, 1)',
                        'rgba(234, 179, 8, 1)',
                        'rgba(34, 197, 94, 1)',
                        'rgba(59, 130, 246, 1)'
                    ],
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
                        },
                        ticks: {
                            precision: 0
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Typing Speed Range (WPM)'
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

        // Accuracy Distribution Chart (fixed version)
        const accuracyCtx = document.getElementById('accuracyChart').getContext('2d');
        if (accuracyChart) accuracyChart.destroy();
        document.getElementById('accuracyChart').parentElement.style.position = 'relative';
        document.getElementById('accuracyChart').parentElement.style.height = '250px';
        document.getElementById('accuracyChart').parentElement.style.width = '100%';
        accuracyChart = new Chart(accuracyCtx, {
            type: 'doughnut',
            data: {
                labels: ['Below 80%', '80-90%', 'Above 90%'],
                datasets: [{
                    data: <?= json_encode($accuracy_distribution) ?>,
                    backgroundColor: [
                        'rgba(239, 68, 68, 0.7)',
                        'rgba(234, 179, 8, 0.7)',
                        'rgba(34, 197, 94, 0.7)'
                    ],
                    borderColor: [
                        'rgba(239, 68, 68, 1)',
                        'rgba(234, 179, 8, 1)',
                        'rgba(34, 197, 94, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 20
                        }
                    }
                },
                cutout: '60%'
            }
        });

        // Performance Scatter Plot (keep existing implementation)
        const scatterCtx = document.getElementById('performanceScatter').getContext('2d');
        if (scatterChart) scatterChart.destroy();
        scatterChart = new Chart(scatterCtx, {
            type: 'scatter',
            data: {
                datasets: [{
                    label: 'Students',
                    data: <?= json_encode(array_map(function($r) {
                        return [
                            'x' => $r['typing_speed_wpm'],
                            'y' => $r['accuracy'],
                            'student' => $r['full_name']
                        ];
                    }, $test_results)) ?>,
                    backgroundColor: 'rgba(99, 102, 241, 0.7)',
                    borderColor: 'rgba(99, 102, 241, 1)',
                    borderWidth: 1,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Typing Speed (WPM)'
                        },
                        min: 0,
                        max: Math.max(...<?= json_encode(array_column($test_results, 'typing_speed_wpm')) ?>) + 10
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Accuracy (%)'
                        },
                        min: 0,
                        max: 100
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const data = context.raw;
                                return `${data.student}: ${data.x} WPM, ${data.y}%`;
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

    // Export to CSV function (keep existing implementation)
    function exportToCSV() {
        const rows = [
            ['Student ID', 'Name', 'Typing Speed (WPM)', 'Accuracy (%)', 'Error (%)', 'Time Taken', 'Backspaces', 'Submitted On'],
            <?php foreach ($test_results as $result): ?>
            [
                '<?= $result['student_id'] ?>',
                '<?= addslashes($result['full_name']) ?>',
                <?= $result['typing_speed_wpm'] ?>,
                <?= $result['accuracy'] ?>,
                <?= $result['error_percentage'] ?>,
                '<?= formatTime($result['time_taken']) ?>',
                <?= $result['backspace_count'] ?>,
                '<?= date('d M Y, H:i', strtotime($result['submission_time'])) ?>'
            ],
            <?php endforeach; ?>
        ];

        let csvContent = "data:text/csv;charset=utf-8,";
        rows.forEach(row => {
            csvContent += row.join(",") + "\r\n";
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "performance_<?= preg_replace('/[^a-z0-9]/i', '_', strtolower($selected_test['test_name'])) ?>.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    <?php endif; ?>
</script>
</body>
</html>