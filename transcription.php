<?php
session_start();
require __DIR__ . '/backend/config.php';

// Validate session and user role
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: login.php?redirect=transcription.php?test_id=".($_GET['test_id'] ?? ''));
    exit();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$test_id = isset($_GET['test_id']) ? intval($_GET['test_id']) : 0;

// Verify database connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Fetch test details with subscription check
$test = [];
$stmt = $conn->prepare("SELECT t.test_id, t.test_name, t.transcript_duration, t.language_code,
                               t.dictation_file, t.transcript_file, t.is_paid_test, t.requires_active_subscription,
                               e.exam_name, e.exam_logo, c.category_name 
                        FROM tests t
                        JOIN exams e ON t.exam_id = e.exam_id
                        JOIN categories c ON t.category_id = c.category_id
                        WHERE t.test_id = ?");
$stmt->bind_param("i", $test_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $test = $result->fetch_assoc();
    
    // Check if test requires subscription and user has one
    if ($test['requires_active_subscription'] && !($_SESSION['has_active_subscription'] ?? false)) {
        die("This test requires an active subscription. Please upgrade your account.");
    }
    
    // Check if paid test and user has access
    if ($test['is_paid_test']) {
        $access_stmt = $conn->prepare("SELECT 1 FROM user_purchased_tests WHERE user_id = ? AND test_id = ?");
        $access_stmt->bind_param("ii", $_SESSION['user_id'], $test_id);
        $access_stmt->execute();
        if (!$access_stmt->get_result()->num_rows) {
            die("You need to purchase this test before attempting it.");
        }
    }
} else {
    die("Test not found");
}

// Fetch user details
$user = [];
$stmt = $conn->prepare("SELECT full_name, student_id, profile_picture, target_exam, has_active_subscription 
                        FROM users 
                        WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
} else {
    die("User not found");
}

// Calculate duration
$duration_min = floor($test['transcript_duration'] / 60);
$duration_sec = $test['transcript_duration'] % 60;
$duration_display = sprintf("%02d:%02d", $duration_min, $duration_sec);

// Language code to name mapping
$languageNames = [
    'en' => 'English',
    'hi' => 'Hindi',
    'mr' => 'Marathi',
    'ta' => 'Tamil',
    'te' => 'Telugu',
    'kn' => 'Kannada',
    'ml' => 'Malayalam',
    'bn' => 'Bengali',
    'gu' => 'Gujarati',
    'pa' => 'Punjabi'
];

$test_language = isset($languageNames[$test['language_code']]) ? 
                 $languageNames[$test['language_code']] : 
                 ucfirst($test['language_code']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($test['exam_name']); ?> - <?php echo htmlspecialchars($test['test_name']); ?> - StenoPlus.in</title>
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #D2171E;
            --primary-dark: #B3121A;
            --secondary: #002147;
            --light-bg: #F5F7FA;
        }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--light-bg);
            -webkit-tap-highlight-color: transparent;
        }
        
        #fullscreenOverlay {
            background: white;
        }
        
        .instruction-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
        }
        
        .test-title {
            color: var(--secondary);
        }
        
        .typing-area {
            min-height: 50vh;
            font-size: 18px;
            line-height: 1.6;
            border: 2px solid #E5E7EB;
            transition: all 0.3s ease;
        }
        
        .typing-area:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(210, 23, 30, 0.1);
        }
        
        .btn-primary {
            background: var(--primary);
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .test-header {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .timer {
            font-family: 'Courier New', monospace;
            color: var(--primary);
        }
        
        /* Drawer animations */
        .mobile-drawer {
            transform: translateX(-100%);
            transition: transform 0.3s ease-out;
        }
        
        .mobile-drawer.open {
            transform: translateX(0);
        }
        
        .drawer-overlay {
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        
        .drawer-overlay.show {
            opacity: 1;
            pointer-events: auto;
        }
        
        /* Error styles */
        .error-message {
            opacity: 0;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .error-message.show {
            opacity: 1;
            max-height: 100px;
        }
        
        .shake {
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }
        
        @media (max-width: 768px) {
            .typing-area {
                min-height: 60vh;
                font-size: 16px;
            }
            
            .mobile-drawer {
                width: 85%;
            }
        }
    </style>
</head>
<body class="text-gray-800">

 <!-- Full-Screen Start Overlay - Updated Layout with Icons -->
<div id="fullscreenOverlay" class="fixed inset-0 flex flex-col items-center justify-center p-6 text-center z-50 bg-white">
    <div class="max-w-md w-full">
        <div class="bg-white p-6 mb-6 rounded-lg shadow-md">
            <div class="mb-6">
                <img src="<?php echo htmlspecialchars($test['exam_logo'] ?? 'assets/images/ssc-logo.png'); ?>" 
                     alt="Exam Logo" 
                     class="w-20 h-20 mx-auto mb-4 rounded-lg border border-gray-200">
                <h1 class="test-title text-2xl font-bold mb-2"><?php echo htmlspecialchars($test['test_name']); ?></h1>
                <p class="text-gray-600"><?php echo htmlspecialchars($test['exam_name']); ?> - <?php echo htmlspecialchars($test['category_name']); ?> - <?php echo $test_language; ?></p>
                <p class="mt-3 text-sm text-gray-500">
                    <i class="fas fa-clock mr-2 text-gray-400"></i>Duration: <?php echo $duration_display; ?>
                </p>
            </div>
            
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-100">
                <h3 class="font-medium mb-3 text-gray-700 flex items-center justify-center">
                    <i class="fas fa-info-circle mr-2 text-gray-500"></i>Test Instructions
                </h3>
                <ul class="text-left space-y-3 text-gray-600">
                    <li class="flex items-start">
                        <span class="text-blue-500 mr-3 w-5 flex justify-center">
                            <i class="fas fa-keyboard text-sm mt-0.5"></i>
                        </span>
                        <span>Type what you hear in the audio</span>
                    </li>
                    <li class="flex items-start">
                        <span class="text-red-500 mr-3 w-5 flex justify-center">
                            <i class="fas fa-ban text-sm mt-0.5"></i>
                        </span>
                        <span>No copy/paste allowed</span>
                    </li>
                    <li class="flex items-start">
                        <span class="text-purple-500 mr-3 w-5 flex justify-center">
                            <i class="fas fa-expand text-sm mt-0.5"></i>
                        </span>
                        <span>Stay in fullscreen mode</span>
                    </li>
                    <li class="flex items-start">
                        <span class="text-green-500 mr-3 w-5 flex justify-center">
                            <i class="fas fa-hourglass-start text-sm mt-0.5"></i>
                        </span>
                        <span>Timer will start when you begin</span>
                    </li>
                </ul>
            </div>
        </div>
        
        <button id="startTestBtn" class="btn-primary text-white font-bold py-3 px-8 rounded-lg text-lg w-full shadow hover:shadow-md transition-all">
            <i class="fas fa-keyboard mr-2"></i> Transcribe Now
        </button>
    </div>
</div>

    <!-- Exit Warning Overlay -->
    <div id="exitWarningOverlay" class="fixed inset-0 flex flex-col items-center justify-center bg-black bg-opacity-90 text-white z-50 p-6 text-center hidden">
        <div class="max-w-md">
            <div class="bg-red-100 text-red-800 p-4 rounded-full inline-block mb-4">
                <i class="fas fa-exclamation-triangle text-2xl"></i>
            </div>
            <h2 class="text-2xl font-bold mb-3">Fullscreen Mode Exited!</h2>
            <p class="mb-6">
                Please return to fullscreen mode within <span class="font-bold" id="exitCountdown">60 seconds</span> 
                or you'll be redirected to the dashboard.
            </p>
            <button id="returnToTestBtn" class="btn-primary text-white font-bold py-3 px-6 rounded-lg w-full">
                <i class="fas fa-expand mr-2"></i> Return to Test
            </button>
        </div>
    </div>

    <!-- Main Test Container -->
    <div id="testContainer" class="hidden">
        <!-- Mobile Header (Sticky) -->
        <header class="test-header sticky top-0 z-40 md:hidden p-4 border-b">
            <div class="flex items-center justify-between">
                <button onclick="toggleDrawer()" class="text-gray-600">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                
                <div class="text-center">
                    <p class="text-sm font-semibold text-gray-600 truncate max-w-[180px] mx-auto">
                        <?php echo htmlspecialchars($test['test_name']); ?>
                    </p>
                    <p class="timer text-md font-bold">
                        <i class="fas fa-clock mr-1"></i>
                        <span id="timerMobile"><?php echo $duration_display; ?></span>
                    </p>
                </div>
                
                <div class="w-6"></div> <!-- Spacer for balance -->
            </div>
        </header>

        <div class="container mx-auto max-w-6xl px-4 py-6">
            <div class="flex flex-col md:flex-row gap-6">
                <!-- Left Section (Main Content) -->
                <div class="md:w-3/4 w-full">
                    <!-- Desktop Header -->
                    <header class="hidden md:flex items-center mb-6 p-4 bg-white rounded-lg shadow-sm">
                        <img src="<?php echo htmlspecialchars($test['exam_logo'] ?? 'assets/images/ssc-logo.png'); ?>" 
                             alt="Exam Logo" 
                             class="w-12 h-12 rounded-lg">
                        <div class="ml-4">
                            <h1 class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($test['test_name']); ?></h1>
                            <p class="text-sm text-gray-600">
                                <?php echo htmlspecialchars($test['exam_name']); ?> • <?php echo htmlspecialchars($test['category_name']); ?>
                            </p>
                        </div>
                        <div class="ml-auto timer text-lg font-bold bg-gray-100 px-4 py-2 rounded-lg">
                            <i class="fas fa-clock mr-2"></i>
                            <span id="timerDesktop"><?php echo $duration_display; ?></span>
                        </div>
                    </header>
                    
                    <!-- Typing Area -->
                    <form action="result.php" method="POST" id="testForm">
                        <input type="hidden" name="test_id" value="<?php echo $test_id; ?>">
                        <input type="hidden" name="time_taken" id="time_taken" value="0">
                        <input type="hidden" name="backspace_count" id="backspace_count" value="0">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                        <textarea name="typed_text" id="typingArea" 
                                  class="typing-area w-full p-4 rounded-lg bg-white shadow-sm" 
                                  placeholder="Start typing your transcription here..." 
                                  autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"></textarea>
                        
                        <!-- Error message container -->
                        <div id="errorDisplay" class="error-message mt-2 text-red-600 text-sm"></div>

                        <div class="mt-4 flex justify-between items-center">
                            <div class="text-sm text-gray-500">
                                <span id="wordCount">0</span> words
                            </div>
                            <button type="submit" class="btn-primary text-white font-bold py-3 px-6 rounded-lg">
                                <i class="fas fa-paper-plane mr-2"></i> Submit
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Right Section (Desktop Sidebar) -->
                <div class="hidden md:block md:w-1/4">
                    <div class="bg-white p-4 rounded-lg shadow-sm sticky top-6">
                        <!-- User Profile -->
                        <div class="flex flex-col items-center text-center border-b pb-4 mb-4">
                            <img src="<?php echo htmlspecialchars($user['profile_picture'] ?? 'assets/images/student.png'); ?>" 
                                 alt="Candidate Photo" 
                                 class="w-16 h-16 rounded-full object-cover border-2 border-white shadow-md">
                            <h3 class="mt-3 font-semibold"><?php echo htmlspecialchars($user['full_name']); ?></h3>
                            <p class="text-sm text-gray-600">ID: <?php echo htmlspecialchars($user['student_id']); ?></p>
                            
                            <?php if ($user['has_active_subscription']): ?>
                                <span class="mt-2 px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                                    <i class="fas fa-crown mr-1"></i> Premium Member
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Test Info -->
                        <div class="space-y-3">
                            <div>
                                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Test Info</h4>
                                <p class="text-sm mt-1">
                                    <i class="fas fa-book-open mr-2 text-gray-400"></i>
                                    <?php echo htmlspecialchars($test['exam_name']); ?>
                                </p>
                                <p class="text-sm mt-1">
                                    <i class="fas fa-tag mr-2 text-gray-400"></i>
                                    <?php echo htmlspecialchars($test['category_name']); ?>
                                </p>
                                 <p class="text-sm mt-1">
                                     <i class="fas fa-language mr-2 text-gray-400"></i>
                                     <?php echo htmlspecialchars($test_language); ?>
                                    </p>
                            </div>
                            
                            <!-- Quick Actions -->
                            <div class="pt-2">
                                <button onclick="toggleFullscreen()" class="w-full flex items-center justify-center text-sm py-2 px-3 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                                    <i class="fas fa-expand mr-2"></i> Fullscreen
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Drawer -->
    <div class="drawer-overlay fixed inset-0 bg-black bg-opacity-50 z-40" id="drawerOverlay" onclick="toggleDrawer()"></div>
    <div class="mobile-drawer fixed top-0 left-0 h-full bg-white z-50 shadow-xl w-85" id="mobileDrawer">
        <div class="h-full flex flex-col">
            <div class="p-4 border-b flex justify-between items-center">
                <h3 class="font-bold text-lg">Test Details</h3>
                <button onclick="toggleDrawer()" class="text-gray-500">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="p-4 overflow-y-auto flex-1">
                <!-- User Profile -->
                <div class="flex flex-col items-center text-center mb-6">
                    <img src="<?php echo htmlspecialchars($user['profile_picture'] ?? 'assets/images/student.png'); ?>" 
                         alt="Candidate Photo" 
                         class="w-20 h-20 rounded-full object-cover border-2 border-white shadow-md mb-3">
                    <h3 class="font-semibold"><?php echo htmlspecialchars($user['full_name']); ?></h3>
                    <p class="text-sm text-gray-600">ID: <?php echo htmlspecialchars($user['student_id']); ?></p>
                    
                    <?php if ($user['has_active_subscription']): ?>
                        <span class="mt-2 px-3 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                            <i class="fas fa-crown mr-1"></i> Premium Member
                        </span>
                    <?php endif; ?>
                </div>
                
                <!-- Test Info -->
                <div class="bg-gray-50 p-4 rounded-lg mb-4">
                    <h4 class="font-medium mb-2 flex items-center">
                        <i class="fas fa-info-circle text-gray-500 mr-2"></i>
                        Test Information
                    </h4>
                    <div class="space-y-2">
                        <p class="text-sm">
                            <span class="font-medium">Name:</span> <?php echo htmlspecialchars($test['test_name']); ?>
                        </p>
                        <p class="text-sm">
                            <span class="font-medium">Exam:</span> <?php echo htmlspecialchars($test['exam_name']); ?>
                        </p>
                        <p class="text-sm">
                            <span class="font-medium">Category:</span> <?php echo htmlspecialchars($test['category_name']); ?>
                        </p>
                        <p class="text-sm">
                            <span class="font-medium">Language:</span> <?php echo htmlspecialchars($test_language); ?>
                        </p>
                        <p class="text-sm">
                            <span class="font-medium">Duration:</span> <?php echo $duration_display; ?>
                        </p>
                    </div>
                </div>
                
                <!-- Actions -->
                <button onclick="toggleFullscreen()" class="w-full flex items-center justify-center py-2 px-4 bg-gray-100 hover:bg-gray-200 rounded-lg mb-2">
                    <i class="fas fa-expand mr-2"></i> Fullscreen Mode
                </button>
                
                <div class="mt-6 pt-4 border-t">
                    <p class="text-xs text-gray-500 text-center">
                        <i class="fas fa-shield-alt mr-1"></i>
                        Your test is being monitored
                    </p>
                </div>
            </div>
        </div>
    </div>
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <!-- Custom Script -->
    <script>
    // Timer variables
    let testStartTime;
    let backspaceCount = 0;
    const timerDuration = <?php echo $test['transcript_duration']; ?>;
    let fullscreenWarningShown = false;
    let fullscreenWarningTimer;
    let exitCountdownInterval;

    // Start Test Button Click Handler
    document.getElementById("startTestBtn").addEventListener("click", function() {
        testStartTime = new Date();
        
        // Enter fullscreen
        let elem = document.documentElement;
        if (elem.requestFullscreen) {
            elem.requestFullscreen().catch(err => {
                alert(`Fullscreen error: ${err.message}`);
            });
        } else if (elem.mozRequestFullScreen) {
            elem.mozRequestFullScreen();
        } else if (elem.webkitRequestFullscreen) {
            elem.webkitRequestFullscreen();
        } else if (elem.msRequestFullscreen) {
            elem.msRequestFullscreen();
        }

        document.getElementById("fullscreenOverlay").style.display = "none";
        document.getElementById("testContainer").classList.remove("hidden");
        
        // Start the countdown timer
        startCountdown();
    });

    // Timer Function
    function startCountdown() {
        let remainingTime = timerDuration;
        const displayDesktop = document.getElementById("timerDesktop");
        const displayMobile = document.getElementById("timerMobile");
        
        const timerInterval = setInterval(function() {
            const minutes = Math.floor(remainingTime / 60);
            const seconds = remainingTime % 60;
            const timeString = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            // Update both timers
            displayDesktop.textContent = timeString;
            if (displayMobile) {
                displayMobile.textContent = timeString;
            }
            
            if (--remainingTime < 0) {
                clearInterval(timerInterval);
                document.getElementById('testForm').submit();
            }
        }, 1000);
        
        window.testTimerInterval = timerInterval;
    }

    // Track backspace key presses
    document.getElementById('typingArea').addEventListener('keydown', function(e) {
        if (e.key === 'Backspace') {
            backspaceCount++;
            document.getElementById('backspace_count').value = backspaceCount;
        }
    });

    // Enhanced form validation
    document.getElementById('testForm').addEventListener('submit', function(e) {
        const typedText = document.getElementById('typingArea').value.trim();
        const errorDisplay = document.getElementById('errorDisplay');
        
        if (!typedText) {
            e.preventDefault();
            showInlineError('Please type something before submitting. Empty tests are not allowed.');
            return false;
        }
        
        const endTime = new Date();
        const timeTaken = Math.floor((endTime - testStartTime) / 1000);
        document.getElementById('time_taken').value = timeTaken;
        
        if (window.testTimerInterval) {
            clearInterval(window.testTimerInterval);
        }
        
        return true;
    });

    // Enhanced copy/paste prevention
    document.getElementById('typingArea').addEventListener('paste', function(e) {
        e.preventDefault();
        showInlineError('Pasting text is disabled. Please type manually.');
        triggerShakeAnimation();
    });

    document.getElementById('typingArea').addEventListener('copy', function(e) {
        e.preventDefault();
        showInlineError('Copying text is disabled during the test.');
        triggerShakeAnimation();
    });

    // Show inline error message
    function showInlineError(message) {
        const errorDisplay = document.getElementById('errorDisplay');
        errorDisplay.textContent = message;
        errorDisplay.classList.add('show');
        
        // Hide after 5 seconds
        setTimeout(() => {
            errorDisplay.classList.remove('show');
        }, 5000);
    }

    // Shake animation for visual feedback
    function triggerShakeAnimation() {
        const textarea = document.getElementById('typingArea');
        textarea.classList.add('shake');
        
        // Remove after animation completes
        setTimeout(() => {
            textarea.classList.remove('shake');
        }, 500);
    }

    // Fixed drawer toggle function
    function toggleDrawer() {
        const drawer = document.getElementById("mobileDrawer");
        const overlay = document.getElementById("drawerOverlay");
        
        drawer.classList.toggle("open");
        overlay.classList.toggle("show");
    }

    // Handle fullscreen changes
    document.addEventListener("fullscreenchange", handleFullscreenChange);
    document.addEventListener("mozfullscreenchange", handleFullscreenChange);
    document.addEventListener("webkitfullscreenchange", handleFullscreenChange);
    document.addEventListener("msfullscreenchange", handleFullscreenChange);

    function handleFullscreenChange() {
        if (!document.fullscreenElement && !document.mozFullScreenElement && 
            !document.webkitFullscreenElement && !document.msFullscreenElement) {
            // If test hasn't started yet, just show overlay as before
            if (!testStartTime) {
                document.getElementById("fullscreenOverlay").style.display = "flex";
                document.getElementById("testContainer").classList.add("hidden");
                return;
            }
            
            // Show warning if not already shown
            if (!fullscreenWarningShown) {
                showExitWarning();
            }
        } else {
            // If returning to fullscreen, hide any warning
            if (fullscreenWarningShown) {
                hideExitWarning();
            }
        }
    }

    function showExitWarning() {
        fullscreenWarningShown = true;
        const warningOverlay = document.getElementById('exitWarningOverlay');
        warningOverlay.style.display = 'flex';
        
        // Start 60 second countdown
        let secondsLeft = 60;
        document.getElementById('exitCountdown').textContent = `Time left: ${secondsLeft} seconds`;
        
        exitCountdownInterval = setInterval(function() {
            secondsLeft--;
            document.getElementById('exitCountdown').textContent = `Time left: ${secondsLeft} seconds`;
            
            if (secondsLeft <= 0) {
                clearInterval(exitCountdownInterval);
                // Automatically redirect without confirmation
                window.location.href = 'dashboard.php';
            }
        }, 1000);
        
        // Handle return to test button
        document.getElementById('returnToTestBtn').onclick = function() {
            hideExitWarning();
            
            // Re-enter fullscreen
            let elem = document.documentElement;
            if (elem.requestFullscreen) {
                elem.requestFullscreen();
            } else if (elem.mozRequestFullScreen) {
                elem.mozRequestFullScreen();
            } else if (elem.webkitRequestFullscreen) {
                elem.webkitRequestFullscreen();
            } else if (elem.msRequestFullscreen) {
                elem.msRequestFullscreen();
            }
        };
    }

    function hideExitWarning() {
        fullscreenWarningShown = false;
        clearInterval(exitCountdownInterval);
        document.getElementById('exitWarningOverlay').style.display = 'none';
    }

    // Word counter
    function updateWordCount() {
        const text = document.getElementById('typingArea').value.trim();
        const wordCount = text ? text.split(/\s+/).length : 0;
        document.getElementById('wordCount').textContent = wordCount;
    }
    
    // Toggle fullscreen
    function toggleFullscreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
                showInlineError('Fullscreen error: ' + err.message);
            });
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        }
        toggleDrawer();
    }
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        const typingArea = document.getElementById('typingArea');
        typingArea.addEventListener('input', updateWordCount);
        
        // Focus typing area when test starts
        document.getElementById('startTestBtn').addEventListener('click', function() {
            setTimeout(() => {
                typingArea.focus();
            }, 500);
        });
    });

    // Prevent ESC key from exiting fullscreen immediately
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && (document.fullscreenElement || document.mozFullScreenElement || 
            document.webkitFullscreenElement || document.msFullscreenElement)) {
            if (testStartTime) {
                e.preventDefault();
                if (!fullscreenWarningShown) {
                    showExitWarning();
                }
            }
        }
    });

    window.addEventListener("beforeunload", function (event) {
        // Clear the timer when leaving the page
        if (window.testTimerInterval) {
            clearInterval(window.testTimerInterval);
        }
        
        // Only show confirmation if test has started but not when we're redirecting due to fullscreen exit
        if (testStartTime && !fullscreenWarningShown) {
            event.preventDefault();
            event.returnValue = "Are you sure you want to leave? Your progress will be lost.";
        }
    });
</script>
</body>
</html>

</body>
</html>