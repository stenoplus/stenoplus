<?php
// my-result.php
require 'backend/config.php';
session_start();

date_default_timezone_set('Asia/Kolkata');

// Check if student is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get result_id from URL and verify it belongs to the current student
$result_id = isset($_GET['result_id']) ? intval($_GET['result_id']) : 0;
$user_id = $_SESSION['user_id'];

if ($result_id <= 0) {
    header("Location: my-performance.php");
    exit();
}

// Get detailed result data for this student only
$result_query = "SELECT 
                r.*, 
                u.full_name, 
                u.student_id,
                u.profile_picture,
                t.test_name,
                t.transcript_file,
                t.transcript_duration as test_duration,
                t.language_code,
                e.exam_name,
                c.category_name
                FROM test_results r
                JOIN users u ON r.user_id = u.user_id
                JOIN tests t ON r.test_id = t.test_id
                LEFT JOIN exams e ON t.exam_id = e.exam_id
                LEFT JOIN categories c ON t.category_id = c.category_id
                WHERE r.result_id = ? AND r.user_id = ?";

$stmt = $conn->prepare($result_query);
$stmt->bind_param("ii", $result_id, $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$result) {
    header("Location: my-performance.php");
    exit();
}

// Format dates and times
$submission_time = date('d/m/Y, h:i a', strtotime($result['submission_time']));
$time_taken_formatted = formatTime($result['time_taken']);
$test_duration_formatted = formatTime($result['test_duration'] / 60);

// Get result details
$result_details = json_decode($result['result_details'], true);
$error_analysis = $result_details['error_analysis'] ?? [];
$time_metrics = $result_details['time_metrics'] ?? [];

// Get original text if available
$original_text = '';
if (!empty($result['transcript_file']) && file_exists($result['transcript_file'])) {
    $original_text = file_get_contents($result['transcript_file']);
}

// Function to format time
function formatTime($seconds) {
    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;
    return sprintf("%02d:%02d", $minutes, $seconds);
}

// Get rank information
$rank_query = "SELECT 
                COUNT(*) as total_participants,
                SUM(CASE WHEN user_id = ? THEN 1 ELSE 0 END) as user_exists,
                (SELECT COUNT(*) FROM test_results WHERE test_id = ? AND (typing_speed_wpm > r.typing_speed_wpm OR 
                 (typing_speed_wpm = r.typing_speed_wpm AND accuracy > r.accuracy) OR
                 (typing_speed_wpm = r.typing_speed_wpm AND accuracy = r.accuracy AND time_taken < r.time_taken))) + 1 as user_rank
              FROM test_results r
              WHERE test_id = ?";

$stmt = $conn->prepare($rank_query);
$test_id = $result['test_id'];
$stmt->bind_param("iii", $user_id, $test_id, $test_id);
$stmt->execute();
$rank_result = $stmt->get_result()->fetch_assoc();
$stmt->close();

$total_participants = $rank_result['total_participants'] ?? 0;
$user_rank = $rank_result['user_rank'] ?? 0;

// Get topper information if not the topper
$topper_result = [];
if ($user_rank > 1 && $total_participants > 1) {
    $topper_query = "SELECT 
                    u.full_name, u.student_id, u.profile_picture,
                    r.typing_speed_wpm, r.accuracy, r.time_taken,
                    r.correct_words, r.wrong_words
                    FROM test_results r
                    JOIN users u ON r.user_id = u.user_id
                    WHERE r.test_id = ?
                    ORDER BY r.typing_speed_wpm DESC, r.accuracy DESC, r.time_taken ASC
                    LIMIT 1";
    
    $stmt = $conn->prepare($topper_query);
    $stmt->bind_param("i", $test_id);
    $stmt->execute();
    $topper_result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Prepare comparison data if topper exists
$comparison_data = [];
if (!empty($topper_result)) {
    $wpm_diff = $topper_result['typing_speed_wpm'] - $result['typing_speed_wpm'];
    $accuracy_diff = $topper_result['accuracy'] - $result['accuracy'];
    $time_diff = $result['time_taken'] - $topper_result['time_taken'];
    $correct_words_diff = $topper_result['correct_words'] - $result['correct_words'];
    $wrong_words_diff = $result['wrong_words'] - $topper_result['wrong_words'];
    
    $wpm_diff_percent = ($wpm_diff / $topper_result['typing_speed_wpm']) * 100;
    $accuracy_diff_percent = ($accuracy_diff / $topper_result['accuracy']) * 100;
    $time_diff_percent = ($time_diff / $topper_result['time_taken']) * 100;
    $correct_words_diff_percent = ($correct_words_diff / $topper_result['correct_words']) * 100;
    $wrong_words_diff_percent = ($wrong_words_diff / $topper_result['wrong_words']) * 100;
    
    $comparison_data = [
        'topper_name' => htmlspecialchars($topper_result['full_name']),
        'topper_student_id' => htmlspecialchars($topper_result['student_id']),
        'topper_profile_pic' => htmlspecialchars($topper_result['profile_picture']),
        'wpm_diff' => $wpm_diff,
        'wpm_diff_percent' => round($wpm_diff_percent, 2),
        'accuracy_diff' => $accuracy_diff,
        'accuracy_diff_percent' => round($accuracy_diff_percent, 2),
        'time_diff' => $time_diff,
        'time_diff_percent' => round($time_diff_percent, 2),
        'correct_words_diff' => $correct_words_diff,
        'correct_words_diff_percent' => round($correct_words_diff_percent, 2),
        'wrong_words_diff' => $wrong_words_diff,
        'wrong_words_diff_percent' => round($wrong_words_diff_percent, 2),
        'message' => 'Comparison with topper'
    ];
}

// Calculate correct and wrong words if not already in result
if (!isset($result['correct_words']) || !isset($result['wrong_words'])) {
    $result['correct_words'] = max(0, $result['typed_words'] - ($result['full_mistakes'] + ($result['half_mistakes'] * 2)));
    $result['wrong_words'] = $result['full_mistakes'] + ($result['half_mistakes'] * 2);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
        <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Result - <?php echo htmlspecialchars($result['test_name']); ?></title>
    <!-- Favicon -->
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <!-- Add Chart.js for data visualization -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
        }
        
        .result-container {
            background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .header-gradient {
            background: linear-gradient(135deg, #002147 0%, #003366 100%);
        }
        
        .metric-card {
            transition: all 0.3s ease;
            background: white;
        }
        
        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .text-display {
            background: white;
            border-left: 4px solid #059669;
        }
        
        .error-legend {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(5px);
        }
        
        @media print {
            body {
                background: white !important;
            }
            
            .no-print {
                display: none !important;
            }
            
            .result-container {
                box-shadow: none !important;
                background: white !important;
            }
            
            .break-after {
                page-break-after: always;
            }
        }
        
        /* Brand colors */
        .brand-blue { color: #002147; }
        .brand-red { color: #D2171E; }
        .bg-brand-blue { background-color: #002147; }
        .bg-brand-red { background-color: #D2171E; }
        
        /* Updated highlighting styles */
        .highlight-correct { background-color: #dcfce7; color: #166534; }
        .highlight-omission { background-color: #fef3c7; color: #92400e; }
        .highlight-addition { background-color: #f3e8ff; color: #6b21a8; }
        .highlight-spelling { background-color: #fee2e2; color: #991b1b; }
        .highlight-capitalization { background-color: #e0f2fe; color: #075985; }
        .highlight-punctuation { background-color: #ccfbf1; color: #0f766e; }
        .highlight-full { background-color: #fecaca; color: #7f1d1d; }
        
        /* Updated span classes to match the legend */
        span.bg-green-100 { background-color: #dcfce7 !important; color: #166534 !important; }
        span.bg-amber-100 { background-color: #fef3c7 !important; color: #92400e !important; }
        span.bg-purple-100 { background-color: #f3e8ff !important; color: #6b21a8 !important; }
        span.bg-red-100 { background-color: #fee2e2 !important; color: #991b1b !important; }
        span.bg-sky-100 { background-color: #e0f2fe !important; color: #075985 !important; }
        span.bg-cyan-100 { background-color: #ccfbf1 !important; color: #0f766e !important; }
        span.bg-red-200 { background-color: #fecaca !important; color: #7f1d1d !important; }

        /* Custom scrollbar for text areas */
        .text-content {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .text-content::-webkit-scrollbar {
            width: 6px;
        }
        
        .text-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .text-content::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }
        
        .text-content::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }

        /* Add chart container styles */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
    </style>
</head>
<body class="p-4 md:p-8">
    <div class="max-w-6xl mx-auto result-container rounded-xl overflow-hidden" id="result-container">
        <!-- Header Section -->
        <div class="header-gradient text-white p-6 md:p-8">
            <div class="flex flex-col md:flex-row md:justify-between md:items-start">
                <div class="w-full md:w-1/2 lg:w-3/4 mb-4 md:mb-0">
                    <h1 class="text-2xl md:text-3xl font-bold mb-2">Typing Test Result</h1>
                    <p class="text-blue-100">Detailed analysis of your typing performance</p>
                </div>
                <div class="w-full md:w-1/2 lg:w-1/4 text-left md:text-right">
                    <p class="text-sm text-blue-100">Submitted on</p>
                    <p class="font-medium"><?php echo $submission_time; ?></p>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="p-6 md:p-8">
            <!-- User Info -->
            <div class="mb-8">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-800">User Information</h2>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Profile Card -->
                    <div class="metric-card p-4 rounded-lg border border-gray-200 flex items-center gap-4">
                        <?php if (!empty($result['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($result['profile_picture']); ?>" 
                                 alt="Profile" class="w-12 h-12 rounded-full object-cover">
                        <?php else: ?>
                            <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                        <?php endif; ?>
                        <div>
                            <p class="text-sm text-gray-500">Student Name</p>
                            <p class="text-lg font-semibold text-gray-800">
                                <?php echo htmlspecialchars($result['full_name']); ?>
                            </p>
                            <p class="text-xs text-gray-500">
                             ID: <?php echo htmlspecialchars($result['student_id']); ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Student ID Card -->
                    <div class="metric-card p-4 rounded-lg border border-gray-200">
                        <p class="text-sm text-gray-500">Student ID</p>
                        <p class="text-lg font-semibold text-gray-800">
                            <?php echo htmlspecialchars($result['student_id']); ?>
                        </p>
                    </div>
                    
                    <!-- Rank Card with dynamic status -->
                    <div class="metric-card p-4 rounded-lg border border-gray-200">
                        <p class="text-sm text-gray-500">Your Rank</p>
                        <p class="text-lg font-semibold text-gray-800">
                            <?php if ($user_rank > 0): ?>
                                <?php if ($user_rank == 1): ?>
                                    <span class="text-yellow-600">🥇 #1 (Top Rank)</span>
                                <?php else: ?>
                                    #<?php echo $user_rank; ?> of <?php echo $total_participants; ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if ($total_participants == 0): ?>
                                    No participants yet
                                <?php else: ?>
                                    Not ranked yet
                                <?php endif; ?>
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($comparison_data['message'])): ?>
                            <p class="text-sm mt-1 <?php echo strpos($comparison_data['message'], 'topper') !== false ? 'text-gray-500' : 'text-green-600'; ?>">
                                <?php echo htmlspecialchars($comparison_data['message']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Comparison Section (only shown when there's valid comparison data) -->
                <?php if (!empty($comparison_data) && isset($comparison_data['wpm_diff'])): ?>
                <div class="mt-6 bg-blue-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Comparison with Topper</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Topper Profile -->
                        <div class="metric-card p-4 rounded-lg border border-blue-200 bg-white flex items-center gap-4">
                            <?php if (!empty($comparison_data['topper_profile_pic'])): ?>
                                <img src="<?php echo htmlspecialchars($comparison_data['topper_profile_pic']); ?>" 
                                     alt="Topper Profile" class="w-12 h-12 rounded-full object-cover">
                            <?php else: ?>
                                <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                    </svg>
                                </div>
                            <?php endif; ?>
                            <div>
                                <p class="text-sm text-gray-500">Topper Student</p>
                                <p class="text-lg font-semibold text-gray-800">
                                    <?php echo htmlspecialchars($comparison_data['topper_name']); ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    ID: <?php echo htmlspecialchars($comparison_data['topper_student_id']); ?>
                                </p>
                            </div>
                        </div>
                        
                        <!-- WPM Comparison -->
                        <div class="metric-card p-4 rounded-lg border border-blue-200 bg-white">
                            <p class="text-sm text-gray-500">Speed Difference</p>
                            <p class="text-lg font-semibold <?php echo $comparison_data['wpm_diff'] > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                <?php echo $comparison_data['wpm_diff'] > 0 ? '-' : '+'; ?>
                                <?php echo abs($comparison_data['wpm_diff']); ?> WPM
                                <span class="text-sm">(<?php echo abs($comparison_data['wpm_diff_percent']); ?>%)</span>
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                Your speed: <?php echo $result['typing_speed_wpm']; ?> WPM
                            </p>
                        </div>
                        
                        <!-- Accuracy Comparison -->
                        <div class="metric-card p-4 rounded-lg border border-blue-200 bg-white">
                            <p class="text-sm text-gray-500">Accuracy Difference</p>
                            <p class="text-lg font-semibold <?php echo $comparison_data['accuracy_diff'] > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                <?php echo $comparison_data['accuracy_diff'] > 0 ? '-' : '+'; ?>
                                <?php echo abs($comparison_data['accuracy_diff']); ?>%
                                <span class="text-sm">(<?php echo abs($comparison_data['accuracy_diff_percent']); ?>%)</span>
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                Your accuracy: <?php echo $result['accuracy']; ?>%
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Rank Comparison Section -->
            <?php if (!empty($topper_result) && (!isset($comparison_data['message']) || $comparison_data['message'] !== 'You are the topper!')): ?>
            <div class="mb-8">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-indigo-100 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-800">Your Performance vs Topper</h2>
                </div>

                <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                    <!-- Header -->
                    <div class="grid grid-cols-2 bg-gray-50 border-b border-gray-200">
                        <div class="p-4 text-center font-medium text-gray-700">
                            <span class="inline-block px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm">
                                Topper (Rank #1)
                            </span>
                        </div>
                        <div class="p-4 text-center font-medium text-gray-700">
                            <span class="inline-block px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">
                                You (Rank #<?php echo $user_rank; ?>)
                            </span>
                        </div>
                    </div>
                    
                    <!-- Profile Row -->
                    <div class="grid grid-cols-2 border-b border-gray-200">
                        <div class="p-4 flex items-center justify-center gap-4">
                            <?php if (!empty($topper_result['profile_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($topper_result['profile_picture']); ?>" 
                                     alt="Topper" class="w-12 h-12 rounded-full object-cover">
                            <?php else: ?>
                                <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                            <?php endif; ?>
                            <div class="text-left">
                                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($topper_result['full_name'] ?? 'N/A'); ?></p>
                                <p class="text-sm text-gray-500">ID: <?php echo htmlspecialchars($topper_result['student_id'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                        <div class="p-4 flex items-center justify-center gap-4">
                            <?php if (!empty($result['profile_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($result['profile_picture']); ?>" 
                                     alt="You" class="w-12 h-12 rounded-full object-cover">
                            <?php else: ?>
                                <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                            <?php endif; ?>
                            <div class="text-left">
                                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($result['full_name'] ?? 'You'); ?></p>
                                <p class="text-sm text-gray-500">ID: <?php echo htmlspecialchars($result['student_id'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Metrics Comparison -->
                    <div class="divide-y divide-gray-200">
                        <!-- WPM -->
                        <div class="grid grid-cols-2">
                            <div class="p-4 text-center">
                                <p class="text-2xl font-bold text-green-600"><?php echo $topper_result['typing_speed_wpm'] ?? 'N/A'; ?></p>
                                <p class="text-sm text-gray-500">Words Per Minute</p>
                            </div>
                            <div class="p-4 text-center border-l border-gray-200">
                                <p class="text-2xl font-bold <?php echo ($result['typing_speed_wpm'] >= ($topper_result['typing_speed_wpm'] * 0.9) ? 'text-green-600' : 'text-red-600'); ?>">
                                    <?php echo $result['typing_speed_wpm'] ?? 'N/A'; ?>
                                </p>
                                <?php if (isset($comparison_data['wpm_diff'])): ?>
                                    <p class="text-xs <?php echo $comparison_data['wpm_diff'] > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                        <?php echo $comparison_data['wpm_diff'] > 0 ? "↓ {$comparison_data['wpm_diff']} WPM slower" : "↑ " . abs($comparison_data['wpm_diff']) . " WPM faster"; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Accuracy -->
                        <div class="grid grid-cols-2">
                            <div class="p-4 text-center">
                                <p class="text-2xl font-bold text-green-600"><?php echo $topper_result['accuracy'] ?? 'N/A'; ?>%</p>
                                <p class="text-sm text-gray-500">Accuracy</p>
                            </div>
                            <div class="p-4 text-center border-l border-gray-200">
                                <p class="text-2xl font-bold <?php echo ($result['accuracy'] >= ($topper_result['accuracy'] * 0.95) ? 'text-green-600' : 'text-red-600'); ?>">
                                    <?php echo $result['accuracy'] ?? 'N/A'; ?>%
                                </p>
                                <?php if (isset($comparison_data['accuracy_diff'])): ?>
                                    <p class="text-xs <?php echo $comparison_data['accuracy_diff'] > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                        <?php echo $comparison_data['accuracy_diff'] > 0 ? "↓ {$comparison_data['accuracy_diff']}% lower" : "↑ " . abs($comparison_data['accuracy_diff']) . "% higher"; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Time Taken -->
                        <div class="grid grid-cols-2">
                            <div class="p-4 text-center">
                                <p class="text-2xl font-bold text-green-600"><?php echo formatTime($topper_result['time_taken'] ?? 0); ?></p>
                                <p class="text-sm text-gray-500">Time Taken</p>
                            </div>
                            <div class="p-4 text-center border-l border-gray-200">
                                <p class="text-2xl font-bold <?php echo ($result['time_taken'] <= ($topper_result['time_taken'] * 1.1) ? 'text-green-600' : 'text-red-600'); ?>">
                                    <?php echo formatTime($result['time_taken'] ?? 0); ?>
                                </p>
                                <?php if (isset($comparison_data['time_diff'])): ?>
                                    <p class="text-xs <?php echo $comparison_data['time_diff'] > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                        <?php echo $comparison_data['time_diff'] > 0 ? "↓ {$comparison_data['time_diff']}s slower" : "↑ " . abs($comparison_data['time_diff']) . "s faster"; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Test Info -->
            <div class="mb-8">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-teal-100 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-teal-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-800">Test Information</h2>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="metric-card p-4 rounded-lg border border-gray-200">
                        <p class="text-sm text-gray-500">Name</p>
                        <p class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($result['test_name']); ?></p>
                    </div>
                    <div class="metric-card p-4 rounded-lg border border-gray-200">
                        <p class="text-sm text-gray-500">Exam & Category</p>
                        <p class="text-lg font-semibold text-gray-800">
                            <?php echo htmlspecialchars($result['exam_name'] ?: 'General'); ?> / <?php echo htmlspecialchars($result['category_name'] ?: 'General'); ?>
                        </p>
                    </div>
                    <div class="metric-card p-4 rounded-lg border border-gray-200">
                        <p class="text-sm text-gray-500">Language</p>
                        <p class="text-lg font-semibold text-gray-800">
                            <?php 
                            $language_names = [
                                'en' => 'English',
                                'hi' => 'Hindi',
                                'mr' => 'Marathi',
                                // Add more language codes as needed
                            ];
                            echo $language_names[$result['language_code']] ?? 'English';
                            ?>
                        </p>
                    </div>
                    <div class="metric-card p-4 rounded-lg border border-gray-200">
                        <p class="text-sm text-gray-500">Duration</p>
                        <p class="text-lg font-semibold text-gray-800"><?php echo $test_duration_formatted; ?> Minutes</p>
                    </div>
                </div>
            </div>
            
            <!-- Performance Summary -->
            <div class="mb-8 break-after">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-800">Performance Summary</h2>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="metric-card p-4 rounded-lg border border-blue-200">
                        <p class="text-sm text-gray-500">Typing Speed</p>
                        <p class="text-2xl font-bold text-blue-600"><?php echo $result['typing_speed_wpm']; ?> <span class="text-sm font-normal">WPM</span></p>
                        <p class="text-xs text-gray-500 mt-1">Words per minute</p>
                    </div>
                    <div class="metric-card p-4 rounded-lg border border-green-200">
                        <p class="text-sm text-gray-500">Accuracy</p>
                        <p class="text-2xl font-bold text-green-600"><?php echo $result['accuracy']; ?>%</p>
                        <p class="text-xs text-gray-500 mt-1">Typing accuracy</p>
                    </div>
                    <div class="metric-card p-4 rounded-lg border border-purple-200">
                        <p class="text-sm text-gray-500">Time Taken</p>
                        <p class="text-2xl font-bold text-purple-600"><?php echo $time_taken_formatted; ?></p>
                        <p class="text-xs text-gray-500 mt-1">MM:SS format</p>
                    </div>
                    <div class="metric-card p-4 rounded-lg border border-amber-200">
                        <p class="text-sm text-gray-500">Backspaces</p>
                        <p class="text-2xl font-bold text-amber-600"><?php echo $result['backspace_count']; ?></p>
                        <p class="text-xs text-gray-500 mt-1">Total corrections</p>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="metric-card p-4 rounded-lg border border-blue-200">
                        <p class="text-sm text-gray-500">Total Words</p>
                        <p class="text-xl font-semibold text-blue-800"><?php echo $result['total_words']; ?></p>
                        <p class="text-xs text-gray-500">Original text</p>
                    </div>
                    <div class="metric-card p-4 rounded-lg border border-orange-200">
                        <p class="text-sm text-gray-500">Typed Words</p>
                        <p class="text-xl font-semibold text-orange-400"><?php echo $result['typed_words']; ?></p>
                        <p class="text-xs text-gray-500">Your input</p>
                    </div>
                    <div class="metric-card p-4 rounded-lg border border-green-200">
                        <p class="text-sm text-gray-500">Correct Words</p>
                        <p class="text-xl font-semibold text-green-600"><?php echo max(0, $result['typed_words'] - ($result['full_mistakes'] + ($result['half_mistakes'] * 2))); ?></p>
                        <p class="text-xs text-gray-500">Perfect matches</p>
                    </div>
                    <div class="metric-card p-4 rounded-lg border border-red-200">
                        <p class="text-sm text-gray-500">Wrong Words</p>
                        <p class="text-xl font-semibold text-red-600"><?php echo $result['full_mistakes'] + ($result['half_mistakes'] * 2); ?></p>
                        <p class="text-xs text-gray-500">Wrong matches</p>
                    </div>
                </div>
            </div>

                            <!-- Performance Charts -->
                <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Words Comparison Chart -->
                    <div class="bg-white p-4 rounded-lg border border-gray-200">
                        <h3 class="font-bold text-lg mb-4 text-gray-700">Words Comparison</h3>
                        <div class="chart-container">
                            <canvas id="wordsComparisonChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Speed vs Accuracy Chart -->
                    <div class="bg-white p-4 rounded-lg border border-gray-200">
                        <h3 class="font-bold text-lg mb-4 text-gray-700">Speed vs Accuracy</h3>
                        <div class="chart-container">
                            <canvas id="speedAccuracyChart"></canvas>
                        </div>
                    </div>
                </div>
            
            <!-- Error Analysis -->
            <div class="mb-8">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-red-100 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-800">Error Analysis</h2>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="metric-card p-4 rounded-lg border border-red-200">
                        <p class="text-sm text-gray-500">Full Mistakes</p>
                        <p class="text-xl font-bold text-red-600"><?php echo $result['full_mistakes']; ?></p>
                        <p class="text-xs text-gray-500">-1.0 mark each</p>
                    </div>
                    <div class="metric-card p-4 rounded-lg border border-orange-200">
                        <p class="text-sm text-gray-500">Half Mistakes</p>
                        <p class="text-xl font-bold text-orange-600"><?php echo $result['half_mistakes']; ?></p>
                        <p class="text-xs text-gray-500">-0.5 mark each</p>
                    </div>
                    <div class="metric-card p-4 rounded-lg border border-rose-200">
                        <p class="text-sm text-gray-500">Spelling Mistakes</p>
                        <p class="text-xl font-bold text-rose-600"><?php echo $result['spelling_mistakes']; ?></p>
                        <p class="text-xs text-gray-500">Wrong spelling</p>
                    </div>
                    <div class="metric-card p-4 rounded-lg border border-indigo-200">
                        <p class="text-sm text-gray-500">Error Percentage</p>
                        <p class="text-xl font-bold text-indigo-600"><?php echo $result['error_percentage']; ?>%</p>
                        <p class="text-xs text-gray-500">(Full + Half Mistakes)</p>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="metric-card p-4 rounded-lg border border-yellow-200">
                        <p class="text-sm text-gray-500">Omissions</p>
                        <p class="text-xl font-bold text-yellow-600"><?php echo $result['omissions']; ?></p>
                        <p class="text-xs text-gray-500">Missing words</p>
                    </div>
                    <div class="metric-card p-4 rounded-lg border border-purple-200">
                        <p class="text-sm text-gray-500">Additions</p>
                        <p class="text-xl font-bold text-purple-600"><?php echo $result['additions']; ?></p>
                        <p class="text-xs text-gray-500">Extra words</p>
                    </div>
                    <div class="metric-card p-4 rounded-lg border border-blue-200">
                        <p class="text-sm text-gray-500">Capitalization</p>
                        <p class="text-xl font-bold text-blue-600"><?php echo $result['capitalization_mistakes']; ?></p>
                        <p class="text-xs text-gray-500">Case errors</p>
                    </div>
                    <div class="metric-card p-4 rounded-lg border border-cyan-200">
                        <p class="text-sm text-gray-500">Punctuation</p>
                        <p class="text-xl font-bold text-cyan-600"><?php echo $result['punctuation_mistakes']; ?></p>
                        <p class="text-xs text-gray-500">Symbol errors</p>
                    </div>
                </div>
            </div>

            <!-- Error Analysis Charts -->
                <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Error Distribution Chart -->
                    <div class="bg-white p-4 rounded-lg border border-gray-200">
                        <h3 class="font-bold text-lg mb-4 text-gray-700">Error Distribution</h3>
                        <div class="chart-container">
                            <canvas id="errorDistributionChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Error Types Chart -->
                    <div class="bg-white p-4 rounded-lg border border-gray-200">
                        <h3 class="font-bold text-lg mb-4 text-gray-700">Error Types Breakdown</h3>
                        <div class="chart-container">
                            <canvas id="errorTypesChart"></canvas>
                        </div>
                    </div>
                </div>
            
            <!-- Text Comparison -->
            <div class="mb-8">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-indigo-100 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-800">Text Comparison</h2>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Original Text -->
                    <div class="text-display p-4 rounded-lg">
                        <h3 class="font-bold text-lg mb-3 text-gray-700 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            Original Text
                        </h3>
                        <div class="text-content px-3 py-2 bg-gray-50 rounded leading-snug whitespace-pre-wrap text-gray-700">
                            <?php
                            $clean_original = preg_replace('/^\s+|^(&nbsp;)+/u', '', $original_text);
                            echo '<div>' . htmlspecialchars($clean_original) . '</div>';
                            ?>
                        </div>
                    </div>

                    <!-- Your Transcription -->
                    <div class="text-display p-4 rounded-lg">
                        <h3 class="font-bold text-lg mb-3 text-gray-700 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Your Transcription
                        </h3>
                        <div class="text-content px-3 py-2 bg-gray-50 rounded leading-snug whitespace-pre-wrap">
                            <?php
                            $clean_output = preg_replace('/^\s+|^(&nbsp;)+/u', '', $result_details['output'] ?? $result['typed_text']);
                            echo '<div>' . $clean_output . '</div>';
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error Legend -->
            <div class="mb-8 error-legend p-4 rounded-lg border border-gray-200">
                <h3 class="font-bold text-lg mb-3 text-gray-700">Error Legend</h3>
                <div class="flex flex-wrap gap-3">
                    <div class="flex items-center gap-2 px-3 py-1 bg-green-50 rounded-full border border-green-100">
                        <span class="w-3 h-3 bg-green-500 rounded-full"></span>
                        <span class="text-sm font-medium text-green-700">Correct</span>
                    </div>
                    <div class="flex items-center gap-2 px-3 py-1 bg-red-50 rounded-full border border-red-100">
                        <span class="w-3 h-3 bg-red-500 rounded-full"></span>
                        <span class="text-sm font-medium text-red-700">Full Mistakes</span>
                    </div>
                    <div class="flex items-center gap-2 px-3 py-1 bg-orange-50 rounded-full border border-orange-100">
                        <span class="w-3 h-3 bg-orange-500 rounded-full"></span>
                        <span class="text-sm font-medium text-orange-700">Half Mistakes</span>
                    </div>
                    <div class="flex items-center gap-2 px-3 py-1 bg-rose-50 rounded-full border border-rose-100">
                        <span class="w-3 h-3 bg-rose-500 rounded-full"></span>
                        <span class="text-sm font-medium text-rose-700">Spelling</span>
                    </div>
                    <div class="flex items-center gap-2 px-3 py-1 bg-yellow-50 rounded-full border border-yellow-100">
                        <span class="w-3 h-3 bg-yellow-500 rounded-full"></span>
                        <span class="text-sm font-medium text-yellow-700">Omissions</span>
                    </div>
                    <div class="flex items-center gap-2 px-3 py-1 bg-purple-50 rounded-full border border-purple-100">
                        <span class="w-3 h-3 bg-purple-500 rounded-full"></span>
                        <span class="text-sm font-medium text-purple-700">Additions</span>
                    </div>
                    <div class="flex items-center gap-2 px-3 py-1 bg-blue-50 rounded-full border border-blue-100">
                        <span class="w-3 h-3 bg-blue-500 rounded-full"></span>
                        <span class="text-sm font-medium text-blue-700">Capitalization</span>
                    </div>
                    <div class="flex items-center gap-2 px-3 py-1 bg-cyan-50 rounded-full border border-cyan-100">
                        <span class="w-3 h-3 bg-cyan-500 rounded-full"></span>
                        <span class="text-sm font-medium text-cyan-700">Punctuation</span>
                    </div>
                </div>
            </div>
            
            <!-- Error Stats -->
            <div class="mb-8 error-legend p-4 rounded-lg border border-gray-200">
                <h3 class="font-bold text-lg mb-3 text-gray-700">Error Stats</h3>
                <div class="flex flex-wrap gap-3">
                    <div class="flex items-center gap-2 px-3 py-1 bg-red-50 rounded-lg border border-red-100 w-full">
                        <span class="w-3 h-3 bg-red-500 rounded-full"></span>
                        <span class="text-sm font-medium text-red-700">Full Mistakes:</span>
                        <span class="text-xs text-gray-600">Skipped Words, Wrong Words, Added Words, Omitted Sentences, Incorrect Word Order</span>
                    </div>
                    <div class="flex items-center gap-2 px-3 py-1 bg-orange-50 rounded-lg border border-orange-100 w-full">
                        <span class="w-3 h-3 bg-orange-500 rounded-full"></span>
                        <span class="text-sm font-medium text-orange-700">Half Mistakes:</span>
                        <span class="text-xs text-gray-600">Spelling Mistake, Capitalization Error, Punctuation Missing, Extra/Missing Space, Wrong Word Form</span>
                    </div>
                </div>
            </div>
            
            <!-- Improvement Tips -->
            <div class="mb-6 bg-blue-50 p-4 rounded-lg border border-blue-200">
                <h3 class="text-lg font-bold mb-2 text-gray-700">Tips for Improvement</h3>
                <ul class="list-disc pl-5 space-y-2 text-gray-700">
                    <?php if ($result['typing_speed_wpm'] < 30): ?>
                    <li>Practice typing drills daily to increase your speed</li>
                    <?php endif; ?>
                    <?php if ($result['accuracy'] < 80): ?>
                    <li>Focus on accuracy first, speed will improve with practice</li>
                    <?php endif; ?>
                    <?php if ($result['spelling_mistakes'] > 0): ?>
                    <li>Review common spelling patterns and practice difficult words</li>
                    <?php endif; ?>
                    <?php if ($result['capitalization_mistakes'] > 0): ?>
                    <li>Pay attention to proper nouns and sentence beginnings</li>
                    <?php endif; ?>
                    <?php if ($result['omissions'] > 0): ?>
                    <li>Read carefully to avoid missing words</li>
                    <?php endif; ?>
                    <li>Take regular practice tests to track your progress</li>
                </ul>
            </div>
            
             <!-- Action Buttons -->
             <div class="flex flex-wrap gap-4 no-print">
                <button onclick="generatePDF()" class="px-6 py-3 bg-brand-blue hover:bg-blue-900 text-white rounded-lg transition-colors flex items-center gap-2 shadow-md hover:shadow-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Download PDF Report
                </button>
                <button onclick="window.print()" class="px-6 py-3 bg-brand-red hover:bg-red-800 text-white rounded-lg transition-colors flex items-center gap-2 shadow-md hover:shadow-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Print Result
                </button>
                <a href="dictation.php?test_id=<?= $result['test_id'] ?>" class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors flex items-center gap-2 shadow-md hover:shadow-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Retake This Test
                </a>
                <a href="my-performance.php" class="px-6 py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors flex items-center gap-2 shadow-md hover:shadow-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 01-18 0z" />
                    </svg>
                    Back to My Performance
                </a>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="bg-gray-50 p-4 text-center text-sm text-gray-500 border-t border-gray-200 no-print">
            <div class="flex flex-col items-center">
                <div>© 2025 StenoPlus. All Rights Reserved.</div>
                <div class="mt-1">
                    Made with ❤ by <a href="https://codeclue.in" target="_blank" class="text-brand-blue hover:underline">Code Clue</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Generate PDF using jsPDF
        function generatePDF() {
            const { jsPDF } = window.jspdf;
            const element = document.getElementById('result-container');
            
            // Show loading state
            const originalHTML = element.innerHTML;
            element.innerHTML = `
                <div class="flex flex-col items-center justify-center p-8" style="height: 100%;">
                    <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-teal-600 mb-4"></div>
                    <p class="text-lg font-medium text-gray-700">Generating PDF report...</p>
                </div>
            `;
            
            // Use html2canvas with better configuration for PDF generation
            const options = {
                scale: 2,
                logging: false,
                useCORS: true,
                allowTaint: true,
                scrollX: 0,
                scrollY: 0,
                windowWidth: document.documentElement.scrollWidth,
                windowHeight: document.documentElement.scrollHeight
            };
            
            html2canvas(element, options).then(canvas => {
                // Restore original content
                element.innerHTML = originalHTML;
                
                const imgData = canvas.toDataURL('image/png', 1.0);
                const pdf = new jsPDF('p', 'mm', 'a4');
                const imgProps = pdf.getImageProperties(imgData);
                const pdfWidth = pdf.internal.pageSize.getWidth() - 20; // Add margins
                const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;
                
                // Add title page
                pdf.setFontSize(22);
                pdf.setTextColor(5, 150, 105); // Teal color
                pdf.text('Typing Test Result', 105, 30, { align: 'center' });
                
                pdf.setFontSize(16);
                pdf.setTextColor(31, 41, 55); // Gray-800
                pdf.text('Test Name: ' + "<?php echo htmlspecialchars($result['test_name']); ?>", 105, 40, { align: 'center' });
                
                pdf.setFontSize(14);
                pdf.text('Submitted on: ' + "<?php echo $submission_time; ?>", 105, 50, { align: 'center' });
                
                // Add some space
                pdf.addPage();
                
                // Add the captured content
                pdf.addImage(imgData, 'PNG', 10, 10, pdfWidth, pdfHeight, undefined, 'FAST');
                
                // Save the PDF
                pdf.save('typing-test-result-<?php echo htmlspecialchars(str_replace(' ', '-', strtolower($result['test_name']))); ?>-<?php echo date('Ymd-His'); ?>.pdf');
            }).catch(error => {
                console.error('Error generating PDF:', error);
                element.innerHTML = originalHTML;
                alert('Failed to generate PDF. Please try again.');
            });
        }
        
        // Print styling
        window.onbeforeprint = function() {
            document.body.classList.add('printing');
        };
        
        window.onafterprint = function() {
            document.body.classList.remove('printing');
        };

        // Initialize Charts when DOM is loaded (same as in result.php)
        document.addEventListener('DOMContentLoaded', function() {
            // Words Comparison Chart
            const wordsComparisonCtx = document.getElementById('wordsComparisonChart').getContext('2d');
            const wordsComparisonChart = new Chart(wordsComparisonCtx, {
                type: 'bar',
                data: {
                    labels: ['Total Words', 'Typed Words', 'Correct Words', 'Wrong Words'],
                    datasets: [{
                        label: 'Words Count',
                        data: [
                            <?php echo $result['total_words']; ?>,
                            <?php echo $result['typed_words']; ?>,
                            <?php echo $result['correct_words']; ?>,
                            <?php echo $result['wrong_words']; ?>
                        ],
                        backgroundColor: [
                            'rgba(59, 130, 246, 0.7)',
                            'rgba(249, 115, 22, 0.7)',
                            'rgba(22, 163, 74, 0.7)',
                            'rgba(220, 38, 38, 0.7)'
                        ],
                        borderColor: [
                            'rgba(59, 130, 246, 1)',
                            'rgba(249, 115, 22, 1)',
                            'rgba(22, 163, 74, 1)',
                            'rgba(220, 38, 38, 1)'
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
                                text: 'Number of Words'
                            }
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Words Comparison'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.raw;
                                }
                            }
                        }
                    }
                }
            });
            
            // Speed vs Accuracy Chart
            const speedAccuracyCtx = document.getElementById('speedAccuracyChart').getContext('2d');
            const speedAccuracyChart = new Chart(speedAccuracyCtx, {
                type: 'radar',
                data: {
                    labels: ['Typing Speed (WPM)', 'Accuracy (%)', 'Correct Words', 'Wrong Words'],
                    datasets: [{
                        label: 'Your Performance',
                        data: [
                            <?php echo $result['typing_speed_wpm']; ?>,
                            <?php echo $result['accuracy']; ?>,
                            <?php echo $result['correct_words']; ?>,
                            <?php echo $result['wrong_words']; ?>
                        ],
                        backgroundColor: 'rgba(79, 70, 229, 0.2)',
                        borderColor: 'rgba(79, 70, 229, 1)',
                        pointBackgroundColor: 'rgba(79, 70, 229, 1)',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: 'rgba(79, 70, 229, 1)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        r: {
                            angleLines: {
                                display: true
                            },
                            suggestedMin: 0,
                            suggestedMax: Math.max(
                                <?php echo $result['typing_speed_wpm']; ?>,
                                <?php echo $result['accuracy']; ?>,
                                <?php echo $result['correct_words']; ?>,
                                <?php echo $result['wrong_words']; ?>
                            ) * 1.2
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Performance Overview'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.raw;
                                }
                            }
                        }
                    }
                }
            });
            
            // Error Distribution Chart
            const errorDistributionCtx = document.getElementById('errorDistributionChart').getContext('2d');
            const errorDistributionChart = new Chart(errorDistributionCtx, {
                type: 'pie',
                data: {
                    labels: ['Correct Words', 'Full Mistakes', 'Half Mistakes'],
                    datasets: [{
                        data: [
                            <?php echo $result['correct_words']; ?>,
                            <?php echo $result['full_mistakes']; ?>,
                            <?php echo $result['half_mistakes']; ?>
                        ],
                        backgroundColor: [
                            'rgba(22, 163, 74, 0.7)',
                            'rgba(220, 38, 38, 0.7)',
                            'rgba(249, 115, 22, 0.7)'
                        ],
                        borderColor: [
                            'rgba(22, 163, 74, 1)',
                            'rgba(220, 38, 38, 1)',
                            'rgba(249, 115, 22, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Error Distribution'
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
            
            // Error Types Chart
            const errorTypesCtx = document.getElementById('errorTypesChart').getContext('2d');
            const errorTypesChart = new Chart(errorTypesCtx, {
                type: 'bar',
                data: {
                    labels: ['Spelling', 'Capitalization', 'Punctuation', 'Omissions', 'Additions'],
                    datasets: [{
                        label: 'Error Types',
                        data: [
                            <?php echo $result['spelling_mistakes']; ?>,
                            <?php echo $result['capitalization_mistakes']; ?>,
                            <?php echo $result['punctuation_mistakes']; ?>,
                            <?php echo $result['omissions']; ?>,
                            <?php echo $result['additions']; ?>
                        ],
                        backgroundColor: [
                            'rgba(244, 63, 94, 0.7)',
                            'rgba(14, 165, 233, 0.7)',
                            'rgba(20, 184, 166, 0.7)',
                            'rgba(234, 179, 8, 0.7)',
                            'rgba(168, 85, 247, 0.7)'
                        ],
                        borderColor: [
                            'rgba(244, 63, 94, 1)',
                            'rgba(14, 165, 233, 1)',
                            'rgba(20, 184, 166, 1)',
                            'rgba(234, 179, 8, 1)',
                            'rgba(168, 85, 247, 1)'
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
                                text: 'Number of Errors'
                            }
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Error Types Breakdown'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.raw;
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