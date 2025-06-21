<?php
// students-analytics.php
session_start();

// Check if user is logged in and is admin
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
//     header("Location: login.php");
//     exit();
// }

// Database Connection
require 'backend/config.php';

// Set timezone to India
date_default_timezone_set('Asia/Kolkata');

// Helper function to convert to 12-hour format
function to12Hour($hour) {
    return date("h:i A", strtotime(sprintf("%02d:00", $hour)));
}

// Helper function to calculate age from date of birth
function calculateAge($dob) {
    return date_diff(date_create($dob), date_create('today'))->y;
}

// 1. User Growth Data (Last 30 days)
$growth_query = "SELECT 
                DATE(created_at) as date, 
                COUNT(*) as new_users,
                SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) as male,
                SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) as female,
                SUM(CASE WHEN gender = 'other' THEN 1 ELSE 0 END) as other,
                SUM(has_active_subscription) as paid_users
                FROM users
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC";
$growth_data = $conn->query($growth_query)->fetch_all(MYSQLI_ASSOC);

// 2. Age Groups Calculation
$age_query = "SELECT 
              FLOOR(DATEDIFF(CURDATE(), date_of_birth)/365) as age,
              COUNT(*) as count
              FROM users
              WHERE date_of_birth IS NOT NULL
              GROUP BY FLOOR(DATEDIFF(CURDATE(), date_of_birth)/365)
              ORDER BY age";
$age_data = $conn->query($age_query)->fetch_all(MYSQLI_ASSOC);

// 3. Subscription vs Registration
$subs_query = "SELECT 
               DATE(created_at) as signup_date,
               MIN(DATE(updated_at)) as paid_date
               FROM users
               WHERE has_active_subscription = 1
               GROUP BY DATE(created_at)
               ORDER BY signup_date";
$subs_data = $conn->query($subs_query)->fetch_all(MYSQLI_ASSOC);

// 4. Geographic Data
$top_cities = $conn->query("SELECT city, COUNT(*) as count FROM users WHERE city IS NOT NULL GROUP BY city ORDER BY count DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
$top_pincodes = $conn->query("SELECT pin_code, COUNT(*) as count FROM users WHERE pin_code IS NOT NULL GROUP BY pin_code ORDER BY count DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

// 5. Paid Users Count
$paid_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE has_active_subscription = 1")->fetch_assoc();
$paid_users_month = $conn->query("SELECT COUNT(*) as count FROM users WHERE has_active_subscription = 1 AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetch_assoc();

// 6. User Activity Times
$active_times = $conn->query("SELECT HOUR(logout_time) as hour, COUNT(*) as count FROM user_sessions WHERE login_time IS NOT NULL GROUP BY HOUR(logout_time) ORDER BY hour")->fetch_all(MYSQLI_ASSOC);
$current_active = $conn->query("SELECT COUNT(*) as count FROM user_sessions WHERE logout_time >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)")->fetch_assoc();

// 7. Session Duration Data
$avg_session = $conn->query("SELECT AVG(session_duration) as avg_duration FROM user_sessions")->fetch_assoc();
$top_active_users = $conn->query("
    SELECT u.user_id, u.full_name, u.email, 
           COUNT(s.session_id) as session_count,
           SEC_TO_TIME(SUM(s.session_duration)) as total_time,
           MAX(s.logout_time) as last_active
    FROM users u
    LEFT JOIN user_sessions s ON u.user_id = s.user_id
    GROUP BY u.user_id
    ORDER BY SUM(s.session_duration) DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// 8. Incomplete Profiles
$incomplete_profiles = $conn->query("
    SELECT user_id, full_name, email, 
           (CASE WHEN profile_picture IS NULL THEN 1 ELSE 0 END) as missing_pic,
           (CASE WHEN address IS NULL THEN 1 ELSE 0 END) as missing_address,
           (CASE WHEN date_of_birth IS NULL THEN 1 ELSE 0 END) as missing_dob,
           (CASE WHEN target_exam IS NULL THEN 1 ELSE 0 END) as missing_exam
    FROM users
    WHERE role = 'student'
    HAVING missing_pic = 1 OR missing_address = 1 OR missing_dob = 1 OR missing_exam = 1
")->fetch_all(MYSQLI_ASSOC);

// 9. Upcoming Birthdays
$today_bday = $conn->query("
    SELECT user_id, full_name, date_of_birth 
    FROM users 
    WHERE DATE_FORMAT(date_of_birth, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')
")->fetch_all(MYSQLI_ASSOC);

$this_week_bday = $conn->query("
    SELECT user_id, full_name, date_of_birth 
    FROM users 
    WHERE DATE_FORMAT(date_of_birth, '%m-%d') BETWEEN DATE_FORMAT(CURDATE(), '%m-%d') 
    AND DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 7 DAY), '%m-%d')
    AND DATE_FORMAT(date_of_birth, '%m-%d') != DATE_FORMAT(CURDATE(), '%m-%d')
")->fetch_all(MYSQLI_ASSOC);

// 10. Password Reset Trends
$reset_trends = $conn->query("
    SELECT DATE(reset_token_expiry) as date, COUNT(*) as count 
    FROM users 
    WHERE reset_token IS NOT NULL
    GROUP BY DATE(reset_token_expiry)
    ORDER BY date DESC
    LIMIT 30
")->fetch_all(MYSQLI_ASSOC);

// 11. Target Exam Distribution
$exam_query = "SELECT 
              target_exam, 
              COUNT(*) as count 
              FROM users 
              WHERE target_exam IS NOT NULL
              GROUP BY target_exam
              ORDER BY count DESC";
$exam_data = $conn->query($exam_query)->fetch_all(MYSQLI_ASSOC);

// Prepare data for charts
$growth_labels = [];
$growth_values = [];
$paid_values = [];
foreach ($growth_data as $row) {
    $growth_labels[] = date('M j', strtotime($row['date']));
    $growth_values[] = $row['new_users'];
    $paid_values[] = $row['paid_users'];
}

$age_labels = ['<18', '18-24', '25-30', '31-40', '41-50', '51+'];
$age_values = [0, 0, 0, 0, 0, 0];
foreach ($age_data as $row) {
    $age = $row['age'];
    if ($age < 18) $age_values[0] += $row['count'];
    elseif ($age <= 24) $age_values[1] += $row['count'];
    elseif ($age <= 30) $age_values[2] += $row['count'];
    elseif ($age <= 40) $age_values[3] += $row['count'];
    elseif ($age <= 50) $age_values[4] += $row['count'];
    else $age_values[5] += $row['count'];
}

$time_labels = [];
$time_values = [];
for ($i = 0; $i < 24; $i++) {
    $time_labels[] = to12Hour($i);
    $time_values[$i] = 0;
}
foreach ($active_times as $row) {
    $time_values[$row['hour']] = $row['count'];
}

$city_labels = [];
$city_values = [];
foreach ($top_cities as $row) {
    $city_labels[] = $row['city'] ?: 'Unknown';
    $city_values[] = $row['count'];
}

$pincode_labels = [];
$pincode_values = [];
foreach ($top_pincodes as $row) {
    $pincode_labels[] = $row['pin_code'] ?: 'Unknown';
    $pincode_values[] = $row['count'];
}

$reset_labels = [];
$reset_values = [];
foreach ($reset_trends as $row) {
    $reset_labels[] = date('M j', strtotime($row['date']));
    $reset_values[] = $row['count'];
}


$exam_labels = [];
$exam_values = [];
foreach ($exam_data as $row) {
    $exam_labels[] = $row['target_exam'] ?: 'Not Specified';
    $exam_values[] = $row['count'];
}

// Gender Distribution
foreach ($growth_data as $row) {
    $growth_labels[] = date('M j', strtotime($row['date']));
    $growth_values[] = $row['new_users'];
    $gender_male[] = $row['male'];
    $gender_female[] = $row['female'];
    $gender_other[] = $row['other'];
}

// Average Session Duration
$avg_session = $conn->query("SELECT AVG(session_duration) as avg_duration FROM user_sessions")->fetch_assoc();

// Calculate hours and minutes
$avg_duration = $avg_session['avg_duration'] ?? 0;
$hours = floor($avg_duration / 3600);
$minutes = floor(($avg_duration % 3600) / 60);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students Analytics - StenoPlus Admin</title>
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
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .stats-card {
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .badge {
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

            <h2 class="text-xl font-semibold dark:text-white">Students Analytics</h2>
            
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
                <!-- Summary Cards Row 1 -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Total Students Card -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800 stats-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Total Students</p>
                                <?php 
                                $total_students = array_sum($growth_values);
                                $growth_30days = $total_students > 0 ? 
                                    (array_sum(array_slice($growth_values, -7))) / $total_students * 100 : 0;
                                ?>
                                <h3 class="text-2xl font-bold mt-1 dark:text-white"><?= $total_students ?></h3>
                                <p class="text-sm mt-1 <?= $growth_30days >= 0 ? 'text-green-600' : 'text-red-600' ?> dark:text-gray-300">
                                    <i data-lucide="<?= $growth_30days >= 0 ? 'trending-up' : 'trending-down' ?>" class="w-4 h-4 inline"></i>
                                    <?= abs(round($growth_30days)) ?>% last 7 days
                                </p>
                            </div>
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300">
                                <i data-lucide="users" class="w-6 h-6"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Paid Users Card -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800 stats-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Paid Subscribers</p>
                                <h3 class="text-2xl font-bold mt-1 dark:text-white"><?= $paid_users['count'] ?></h3>
                                <p class="text-sm mt-1 text-green-600 dark:text-green-300">
                                    <?= $paid_users_month['count'] ?> this month
                                </p>
                            </div>
                            <div class="p-3 rounded-full bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-300">
                                <i data-lucide="dollar-sign" class="w-6 h-6"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Currently Active Users -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800 stats-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Currently Active</p>
                                <h3 class="text-2xl font-bold mt-1 dark:text-white"><?= $current_active['count'] ?></h3>
                                <p class="text-sm mt-1 text-gray-500 dark:text-gray-400">
                                    Last 15 minutes
                                </p>
                            </div>
                            <div class="p-3 rounded-full bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-300">
                                <i data-lucide="activity" class="w-6 h-6"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Incomplete Profiles -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800 stats-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Incomplete Profiles</p>
                                <h3 class="text-2xl font-bold mt-1 dark:text-white"><?= count($incomplete_profiles) ?></h3>
                                <p class="text-sm mt-1 text-red-600 dark:text-red-300">
                                    <?= round((count($incomplete_profiles) / max(1, $total_students)) * 100) ?>% of total
                                </p>
                            </div>
                            <div class="p-3 rounded-full bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-300">
                                <i data-lucide="alert-circle" class="w-6 h-6"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Cards Row 2 -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Birthdays Today -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800 stats-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Birthdays Today</p>
                                <h3 class="text-2xl font-bold mt-1 dark:text-white"><?= count($today_bday) ?></h3>
                                <p class="text-sm mt-1 text-pink-600 dark:text-pink-300">
                                    <?= date('M j') ?>
                                </p>
                            </div>
                            <div class="p-3 rounded-full bg-pink-100 text-pink-600 dark:bg-pink-900/30 dark:text-pink-300">
                                <i data-lucide="gift" class="w-6 h-6"></i>
                            </div>
                        </div>
                    </div>
                    
                    
                    <!-- Password Resets -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800 stats-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Password Resets (30d)</p>
                                <h3 class="text-2xl font-bold mt-1 dark:text-white"><?= array_sum($reset_values) ?></h3>
                                <p class="text-sm mt-1 text-blue-600 dark:text-blue-300">
                                    <?= round(array_sum($reset_values) / 30, 1) ?>/day avg
                                </p>
                            </div>
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300">
                                <i data-lucide="key" class="w-6 h-6"></i>
                            </div>
                        </div>
                    </div>


                    <!-- Gender Distribution -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800 stats-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Gender Distribution</p>
                                <h3 class="text-2xl font-bold mt-1 dark:text-white">
                                    <?= array_sum($gender_male) ?>M / <?= array_sum($gender_female) ?>F
                                </h3>
                                <p class="text-sm mt-1 text-blue-600 dark:text-blue-300">
                                     <?= array_sum($gender_other) ?> Other
                                </p>
                            </div>
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300">
                                <i data-lucide="venus" class="w-6 h-6"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Average Time Spent -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800 stats-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">Average Time Spent</p>
                                <h3 class="text-2xl font-bold mt-1 dark:text-white">
                                     <?= $hours ?>h <?= $minutes ?>m
                                </h3>
                                <p class="text-sm mt-1 text-blue-600 dark:text-blue-300">
                                     Per session
                                </p>
                            </div>
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300">
                                <i data-lucide="clock" class="w-6 h-6"></i>
                            </div>
                        </div>
                    </div>
                    
                </div>

                <!-- Main Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- User Growth vs Paid Conversion -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                        <h3 class="text-lg font-semibold mb-4 dark:text-white">User Growth vs Paid Conversion (30 Days)</h3>
                        <div class="chart-container h-64 bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                            <canvas id="growthChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Age Group Distribution -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                        <h3 class="text-lg font-semibold mb-4 dark:text-white">Age Group Distribution</h3>
                        <div class="chart-container h-64 bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                            <canvas id="ageChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Secondary Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- User Activity by Hour -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                        <h3 class="text-lg font-semibold mb-4 dark:text-white">User Activity by Hour (IST)</h3>
                        <div class="chart-container h-64 bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                            <canvas id="activityChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Top Cities -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                        <h3 class="text-lg font-semibold mb-4 dark:text-white">Top 10 Cities</h3>
                        <div class="chart-container h-64 bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                            <canvas id="cityChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Third Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Password Reset Trends -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                        <h3 class="text-lg font-semibold mb-4 dark:text-white">Password Reset Requests (30 Days)</h3>
                        <div class="chart-container h-64 bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                            <canvas id="resetChart"></canvas>
                        </div>
                    </div>
                    
                </div>

                <!-- Fourth Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Top PIN Codes -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                        <h3 class="text-lg font-semibold mb-4 dark:text-white">Top 10 PIN Codes</h3>
                        <div class="chart-container h-64 bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                            <canvas id="pincodeChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Target Exam Distribution -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                        <h3 class="text-lg font-semibold mb-4 dark:text-white">Target Exam Preferences</h3>
                        <div class="chart-container h-64 bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                            <canvas id="examChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Data Tables Section -->
                <div class="grid grid-cols-1 gap-6">
                    <!-- Most Active Users -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                        <h3 class="text-lg font-semibold mb-4 dark:text-white">Most Active Users</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Student</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Sessions</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Total Time</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Last Active</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                    <?php foreach ($top_active_users as $user): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <img src="<?= !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : 'assets/images/student.png' ?>" alt="" class="h-10 w-10 rounded-full">
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium dark:text-white"><?= htmlspecialchars($user['full_name']) ?></div>
                                                    <div class="text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($user['email']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm dark:text-gray-300">
                                            <?= $user['session_count'] ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm dark:text-gray-300">
                                            <?= $user['total_time'] ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm dark:text-gray-300">
                                            <?= date('M j, h:i A', strtotime($user['last_active'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="manage-students.php?user_id=<?= $user['user_id'] ?>" class="text-blue-600 hover:text-blue-900 dark:text-blue-400">View</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Incomplete Profiles -->
                    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                        <h3 class="text-lg font-semibold mb-4 dark:text-white">Incomplete Profiles (<?= count($incomplete_profiles) ?>)</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Student</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Missing</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-300">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                    <?php foreach ($incomplete_profiles as $user): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <?php if ($user['missing_pic']): ?>
                                                        <div class="bg-gray-200 rounded-full h-10 w-10 flex items-center justify-center dark:bg-gray-600">
                                                            <i data-lucide="user" class="text-gray-500 dark:text-gray-300"></i>
                                                        </div>
                                                    <?php else: ?>
                                                        <img class="h-10 w-10 rounded-full" src="uploads/student_profile/profile_<?= $user['user_id'] ?>_*" alt="">
                                                    <?php endif; ?>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium dark:text-white"><?= htmlspecialchars($user['full_name']) ?></div>
                                                    <div class="text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($user['email']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex flex-wrap gap-2">
                                                <?php if ($user['missing_pic']): ?>
                                                    <span class="badge bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">Profile Pic</span>
                                                <?php endif; ?>
                                                <?php if ($user['missing_address']): ?>
                                                    <span class="badge bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">Address</span>
                                                <?php endif; ?>
                                                <?php if ($user['missing_dob']): ?>
                                                    <span class="badge bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">DOB</span>
                                                <?php endif; ?>
                                                <?php if ($user['missing_exam']): ?>
                                                    <span class="badge bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300">Target Exam</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="manage-students.php?user_id=<?= $user['user_id'] ?>" class="text-blue-600 hover:text-blue-900 dark:text-blue-400">Complete Profile</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Upcoming Birthdays -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Today's Birthdays -->
                        <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                            <h3 class="text-lg font-semibold mb-4 dark:text-white">Birthdays Today (<?= count($today_bday) ?>)</h3>
                            <?php if (!empty($today_bday)): ?>
                                <div class="space-y-3">
                                    <?php foreach ($today_bday as $user): ?>
                                    <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg dark:hover:bg-gray-700">
                                        <div class="flex items-center">
                                            <div class="bg-pink-100 text-pink-600 rounded-full p-2 mr-3 dark:bg-pink-900/30 dark:text-pink-300">
                                                <i data-lucide="gift" class="w-5 h-5"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-medium dark:text-white"><?= htmlspecialchars($user['full_name']) ?></h4>
                                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                                    Turning <?= calculateAge($user['date_of_birth']) ?> years
                                                </p>
                                            </div>
                                        </div>
                                        <a href="manage-students.php?user_id=<?= $user['user_id'] ?>" class="text-sm text-blue-600 hover:underline dark:text-blue-400">View</a>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                    <i data-lucide="party-popper" class="w-8 h-8 mx-auto mb-2"></i>
                                    <p>No birthdays today</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- This Week's Birthdays -->
                        <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                            <h3 class="text-lg font-semibold mb-4 dark:text-white">Birthdays This Week (<?= count($this_week_bday) ?>)</h3>
                            <?php if (!empty($this_week_bday)): ?>
                                <div class="space-y-3">
                                    <?php foreach ($this_week_bday as $user): 
                                        $bday_this_year = date('Y') . date('-m-d', strtotime($user['date_of_birth']));
                                        $day_name = date('D', strtotime($bday_this_year));
                                    ?>
                                    <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg dark:hover:bg-gray-700">
                                        <div class="flex items-center">
                                            <div class="bg-green-100 text-green-600 rounded-full p-2 mr-3 dark:bg-green-900/30 dark:text-green-300">
                                                <i data-lucide="calendar" class="w-5 h-5"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-medium dark:text-white"><?= htmlspecialchars($user['full_name']) ?></h4>
                                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?= $day_name ?>, <?= date('M j', strtotime($bday_this_year)) ?>
                                                </p>
                                            </div>
                                        </div>
                                        <a href="manage-students.php?user_id=<?= $user['user_id'] ?>" class="text-sm text-blue-600 hover:underline dark:text-blue-400">View</a>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                                    <i data-lucide="calendar" class="w-8 h-8 mx-auto mb-2"></i>
                                    <p>No birthdays this week</p>
                                </div>
                            <?php endif; ?>
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

        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // User Growth Chart
            const growthCtx = document.getElementById('growthChart').getContext('2d');
            new Chart(growthCtx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($growth_labels) ?>,
                    datasets: [
                        {
                            label: 'New Signups',
                            data: <?= json_encode($growth_values) ?>,
                            backgroundColor: 'rgba(59, 130, 246, 0.7)',
                            borderColor: 'rgba(59, 130, 246, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Paid Conversions',
                            data: <?= json_encode($paid_values) ?>,
                            backgroundColor: 'rgba(16, 185, 129, 0.7)',
                            borderColor: 'rgba(16, 185, 129, 1)',
                            borderWidth: 1
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
                                text: 'Number of Users'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        }
                    }
                }
            });

            // Age Group Distribution Chart
            const ageCtx = document.getElementById('ageChart').getContext('2d');
            new Chart(ageCtx, {
                type: 'pie',
                data: {
                    labels: <?= json_encode($age_labels) ?>,
                    datasets: [{
                        data: <?= json_encode($age_values) ?>,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(255, 206, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(153, 102, 255, 0.7)',
                            'rgba(255, 159, 64, 0.7)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(153, 102, 255, 1)',
                            'rgba(255, 159, 64, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });

            // User Activity by Hour Chart
            const activityCtx = document.getElementById('activityChart').getContext('2d');
            new Chart(activityCtx, {
                type: 'line',
                data: {
                    labels: <?= json_encode(array_map('to12Hour', range(0, 23))) ?>,
                    datasets: [{
                        label: 'Active Users',
                        data: <?= json_encode($time_values) ?>,
                        borderColor: 'rgba(139, 92, 246, 0.8)',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true
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
                                text: 'Number of Active Users'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Time of Day (IST)'
                            }
                        }
                    }
                }
            });

            // Top Cities Chart
            const cityCtx = document.getElementById('cityChart').getContext('2d');
            new Chart(cityCtx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($city_labels) ?>,
                    datasets: [{
                        label: 'Students',
                        data: <?= json_encode($city_values) ?>,
                        backgroundColor: 'rgba(59, 130, 246, 0.7)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'City'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Number of Students'
                            }
                        }
                    }
                }
            });

            // Password Reset Trends Chart
            const resetCtx = document.getElementById('resetChart').getContext('2d');
            new Chart(resetCtx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($reset_labels) ?>,
                    datasets: [{
                        label: 'Password Reset Requests',
                        data: <?= json_encode($reset_values) ?>,
                        borderColor: 'rgba(239, 68, 68, 0.8)',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true
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
                                text: 'Reset Requests'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        }
                    }
                }
            });

            // PIN Code Distribution Chart
            const pincodeCtx = document.getElementById('pincodeChart').getContext('2d');
            new Chart(pincodeCtx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($pincode_labels) ?>,
                    datasets: [{
                        label: 'Students',
                        data: <?= json_encode($pincode_values) ?>,
                        backgroundColor: 'rgba(245, 158, 11, 0.7)',
                        borderColor: 'rgba(245, 158, 11, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'PIN Code'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Number of Students'
                            }
                        }
                    }
                }
            });

            // Exam Distribution Chart
            const examCtx = document.getElementById('examChart').getContext('2d');
            new Chart(examCtx, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($exam_labels) ?>,
                    datasets: [{
                        data: <?= json_encode($exam_values) ?>,
                        backgroundColor: [
                            'rgba(59, 130, 246, 0.7)',
                            'rgba(16, 185, 129, 0.7)',
                            'rgba(244, 63, 94, 0.7)',
                            'rgba(245, 158, 11, 0.7)',
                            'rgba(139, 92, 246, 0.7)',
                            'rgba(20, 184, 166, 0.7)',
                            'rgba(236, 72, 153, 0.7)'
                        ],
                        borderColor: [
                            'rgba(59, 130, 246, 1)',
                            'rgba(16, 185, 129, 1)',
                            'rgba(244, 63, 94, 1)',
                            'rgba(245, 158, 11, 1)',
                            'rgba(139, 92, 246, 1)',
                            'rgba(20, 184, 166, 1)',
                            'rgba(236, 72, 153, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>