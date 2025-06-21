<?php
// student_result.php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database Connection
require 'backend/config.php';

// Get result_id from URL
$result_id = isset($_GET['result_id']) ? intval($_GET['result_id']) : 0;

if ($result_id <= 0) {
    header("Location: performance-reports.php");
    exit();
}

// Get detailed result data
$result_query = "SELECT 
                r.*, 
                u.full_name, 
                u.student_id,
                t.test_name,
                t.transcript_duration,
                t.dictation_duration,
                e.exam_name,
                c.category_name
                FROM test_results r
                JOIN users u ON r.user_id = u.user_id
                JOIN tests t ON r.test_id = t.test_id
                LEFT JOIN exams e ON t.exam_id = e.exam_id
                LEFT JOIN categories c ON t.category_id = c.category_id
                WHERE r.result_id = ?";

$stmt = $conn->prepare($result_query);
$stmt->bind_param("i", $result_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$result) {
    header("Location: performance-reports.php");
    exit();
}

// Format dates and times
$submission_time = date('d M Y, h:i A', strtotime($result['submission_time']));
$time_taken_formatted = formatTime($result['time_taken']);
$transcript_duration_formatted = formatTime($result['transcript_duration'] * 60);
$dictation_duration_formatted = formatTime($result['dictation_duration'] * 60);

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
    <title>Student Result - StenoPlus</title>
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
        
        /* Highlight classes for text comparison */
        .highlight-correct { background-color: #bbf7d0; }
        .highlight-error { background-color: #fecaca; }
        .highlight-warning { background-color: #fef08a; }
        .dark .highlight-correct { background-color: #14532d; }
        .dark .highlight-error { background-color: #7f1d1d; }
        .dark .highlight-warning { background-color: #713f12; }
        
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

            <h2 class="text-xl font-semibold dark:text-white">Student Test Result</h2>
            
            <div class="flex items-center space-x-4">
                <a href="performance-reports.php" class="text-blue-600 hover:underline dark:text-blue-400">
                    <i data-lucide="arrow-left" class="w-5 h-5 inline"></i> Back to Reports
                </a>
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

        <!-- Student Result Content -->
        <section class="mt-6 p-6 lg:p-0">
            <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                <!-- Student and Test Information -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200 dark:bg-blue-900/20">
                        <div class="text-sm font-medium text-blue-800 dark:text-blue-200">Student</div>
                        <div class="text-xl font-bold dark:text-white"><?= htmlspecialchars($result['full_name']) ?></div>
                        <div class="text-sm dark:text-white">ID: <?= $result['student_id'] ?></div>
                    </div>
                    <div class="bg-purple-50 p-4 rounded-lg border border-purple-200 dark:bg-purple-900/20">
                        <div class="text-sm font-medium text-purple-800 dark:text-purple-200">Test</div>
                        <div class="text-xl font-bold dark:text-white"><?= htmlspecialchars($result['test_name']) ?></div>
                        <div class="text-sm dark:text-white">
                            <?= $result['exam_name'] ? htmlspecialchars($result['exam_name']) : 'N/A' ?>
                            <?= $result['category_name'] ? ' • ' . htmlspecialchars($result['category_name']) : '' ?>
                        </div>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg border border-green-200 dark:bg-green-900/20">
                        <div class="text-sm font-medium text-green-800 dark:text-green-200">Submission</div>
                        <div class="text-xl font-bold dark:text-white"><?= $submission_time ?></div>
                        <div class="text-sm dark:text-white">
                            Transcript: <?= $transcript_duration_formatted ?><br>
                            Dictation: <?= $dictation_duration_formatted ?>
                        </div>
                    </div>
                </div>

                <!-- Performance Metrics -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-blue-100 p-4 rounded-lg border border-blue-200 dark:bg-blue-900/30">
                        <div class="text-sm font-medium text-blue-800 dark:text-blue-200">Typing Speed</div>
                        <div class="text-2xl font-bold dark:text-white"><?= $result['typing_speed_wpm'] ?> WPM</div>
                    </div>
                    <div class="bg-green-100 p-4 rounded-lg border border-green-200 dark:bg-green-900/30">
                        <div class="text-sm font-medium text-green-800 dark:text-green-200">Accuracy</div>
                        <div class="text-2xl font-bold dark:text-white"><?= $result['accuracy'] ?>%</div>
                    </div>
                    <div class="bg-red-100 p-4 rounded-lg border border-red-200 dark:bg-red-900/30">
                        <div class="text-sm font-medium text-red-800 dark:text-red-200">Error Rate</div>
                        <div class="text-2xl font-bold dark:text-white"><?= $result['error_percentage'] ?>%</div>
                    </div>
                    <div class="bg-yellow-100 p-4 rounded-lg border border-yellow-200 dark:bg-yellow-900/30">
                        <div class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Time Taken</div>
                        <div class="text-2xl font-bold dark:text-white"><?= $time_taken_formatted ?></div>
                    </div>
                </div>

                <!-- Error Analysis -->
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                    <div class="bg-red-50 p-3 rounded border border-red-200 dark:bg-red-900/20">
                        <div class="font-bold">Full Mistakes</div>
                        <div class="text-xl"><?= $result['full_mistakes'] ?></div>
                        <div class="text-xs text-gray-600 dark:text-gray-300">(1.0 mark each)</div>
                    </div>
                    <div class="bg-orange-50 p-3 rounded border border-orange-200 dark:bg-orange-900/20">
                        <div class="font-bold">Half Mistakes</div>
                        <div class="text-xl"><?= $result['half_mistakes'] ?></div>
                        <div class="text-xs text-gray-600 dark:text-gray-300">(0.5 mark each)</div>
                    </div>
                    <div class="bg-red-50 p-3 rounded border border-red-200 dark:bg-red-900/20">
                        <div class="font-bold">Omissions</div>
                        <div class="text-xl"><?= $result['omissions'] ?></div>
                    </div>
                    <div class="bg-red-50 p-3 rounded border border-red-200 dark:bg-red-900/20">
                        <div class="font-bold">Additions</div>
                        <div class="text-xl"><?= $result['additions'] ?></div>
                    </div>
                    <div class="bg-yellow-50 p-3 rounded border border-yellow-200 dark:bg-yellow-900/20">
                        <div class="font-bold">Spelling</div>
                        <div class="text-xl"><?= $result['spelling_mistakes'] ?></div>
                    </div>
                    <div class="bg-blue-50 p-3 rounded border border-blue-200 dark:bg-blue-900/20">
                        <div class="font-bold">Capitalization</div>
                        <div class="text-xl"><?= $result['capitalization_mistakes'] ?></div>
                    </div>
                </div>

                <!-- Text Comparison -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div class="p-4 bg-gray-50 rounded-lg border border-gray-200 dark:bg-gray-700 dark:border-gray-600">
                        <h3 class="font-bold text-lg mb-2 dark:text-white">Original Text</h3>
                        <div class="p-3 border rounded-lg bg-white leading-relaxed whitespace-pre-wrap dark:bg-gray-800 dark:text-white">
                            <?= nl2br(htmlspecialchars($result['original_text'])) ?>
                        </div>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg border border-gray-200 dark:bg-gray-700 dark:border-gray-600">
                        <h3 class="font-bold text-lg mb-2 dark:text-white">Student's Transcription</h3>
                        <div class="p-3 border rounded-lg bg-white leading-relaxed whitespace-pre-wrap dark:bg-gray-800">
                            <?php 
                            // Parse the JSON result details for highlighted output
                            $result_details = json_decode($result['result_details'], true);
                            echo $result_details['output'] ?? nl2br(htmlspecialchars($result['typed_text']));
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Legend -->
                <div class="mb-6">
                    <h3 class="font-bold text-lg mb-2 dark:text-white">Error Legend</h3>
                    <div class="flex flex-wrap gap-2">
                        <div class="flex items-center gap-1 px-3 py-1 bg-green-100 rounded-full dark:bg-green-900/30">
                            <span class="w-3 h-3 bg-green-600 rounded-full"></span>
                            <span class="dark:text-white">Correct</span>
                        </div>
                        <div class="flex items-center gap-1 px-3 py-1 bg-red-100 rounded-full dark:bg-red-900/30">
                            <span class="w-3 h-3 bg-red-400 rounded-full"></span>
                            <span class="dark:text-white">Full Mistakes</span>
                        </div>
                        <div class="flex items-center gap-1 px-3 py-1 bg-yellow-100 rounded-full dark:bg-yellow-900/30">
                            <span class="w-3 h-3 bg-yellow-400 rounded-full"></span>
                            <span class="dark:text-white">Half Mistakes</span>
                        </div>
                        <div class="flex items-center gap-1 px-3 py-1 bg-blue-100 rounded-full dark:bg-blue-900/30">
                            <span class="w-3 h-3 bg-blue-400 rounded-full"></span>
                            <span class="dark:text-white">Capitalization</span>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex flex-wrap gap-3">
                    <button onclick="window.print()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 flex items-center gap-2">
                        <i data-lucide="printer" class="w-4 h-4"></i> Print Result
                    </button>
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
    </script>
</body>
</html>