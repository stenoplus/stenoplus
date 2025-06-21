<?php
session_start();
require 'backend/config.php';

// Validate session and user role
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: login.php?redirect=dictation.php?test_id=".($_GET['test_id'] ?? ''));
    exit();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$test_id = isset($_GET['test_id']) ? intval($_GET['test_id']) : 0;
if ($test_id <= 0) {
    die("Invalid test ID");
}

// Fetch test details with all required fields
$test = [];
$stmt = $conn->prepare("SELECT t.test_id, t.test_name, t.dictation_duration, t.language_code, t.dictation_file,
                               t.word_count, t.test_mode, e.exam_name, e.exam_logo, c.category_name 
                        FROM tests t
                        JOIN exams e ON t.exam_id = e.exam_id
                        JOIN categories c ON t.category_id = c.category_id
                        WHERE t.test_id = ?");
$stmt->bind_param("i", $test_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $test = $result->fetch_assoc();
    
    // Calculate default WPM with validation
    if (!empty($test['word_count']) && !empty($test['dictation_duration']) && $test['dictation_duration'] > 0) {
        $test['default_wpm'] = round($test['word_count'] / ($test['dictation_duration'] / 60), 1);
    } else {
        // Set safe defaults if data is missing
        $test['default_wpm'] = 100; // Default average WPM
        if (empty($test['dictation_duration'])) {
            $test['dictation_duration'] = 600; // Default 10 minutes
        }
    }
} else {
    die("Test not found");
}

// Fetch user details
$user = [];
$stmt = $conn->prepare("SELECT full_name, student_id, profile_picture, has_active_subscription 
                        FROM users 
                        WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
}

// Format duration function
function formatDuration($seconds) {
    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;
    return sprintf("%02d:%02d", $minutes, $seconds);
}

$dictation_duration_display = formatDuration($test['dictation_duration']);

// Language code to name mapping
$languageNames = [
    'en' => 'English',
    'hi' => 'Hindi',
    'mr' => 'Marathi',
    // ... other languages ...
];

$test_language = $languageNames[$test['language_code']] ?? ucfirst($test['language_code']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($test['exam_name']); ?> Dictation - StenoPlus.in</title>
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon" />
    <script src="https://cdn.tailwindcss.com"></script>
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
        }
        
        .audio-player {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        
        .test-title {
            color: var(--secondary);
        }
        
        .btn-primary {
            background: var(--primary);
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .test-header {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .timer {
            font-family: 'Courier New', monospace;
            color: var(--primary);
        }
        
        .tooltip {
            font-family: 'Oswald', sans-serif;
            font-size: 9px;
        }
        
        @media (max-width: 768px) {
            .mobile-drawer {
                width: 85%;
            }
        }

        /* Mobile Drawer */
        .mobile-drawer {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 85%;
            background: white;
            z-index: 50;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            transform: translateX(-100%);
            transition: transform 0.3s ease-out;
        }
        
        .mobile-drawer.open {
            transform: translateX(0);
        }
        
        .drawer-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 40;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        
        .drawer-overlay.show {
            opacity: 1;
            pointer-events: auto;
        }

        /* Volume controls */
        .volume-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .volume-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            user-select: none;
        }
        .volume-btn:hover {
            background: #e0e0e0;
        }
        #volumeBar {
            background-color: var(--secondary); /* Using secondary color (002147) */
        }
    </style>
</head>
<body class="text-gray-800">

        <!-- Full-Screen Start Overlay -->
        <div id="fullscreenOverlay" class="fixed inset-0 flex items-center justify-center p-4 z-50 bg-white md:bg-gray-100">
            <div class="w-full max-w-4xl bg-white rounded-xl shadow-lg overflow-hidden md:flex">
                <!-- Left Column - Test Info -->
                <div class="p-8 md:w-1/2 flex flex-col justify-center">
                    <div class="text-center mb-6">
                        <img src="<?php echo htmlspecialchars($test['exam_logo'] ?? 'assets/images/ssc-logo.png'); ?>" 
                            alt="Exam Logo" 
                            class="w-20 h-20 mx-auto mb-4 rounded-lg">
                        <h1 class="test-title text-2xl font-bold mb-2"><?php echo htmlspecialchars($test['test_name']); ?></h1>
                        <p class="text-gray-600"><?php echo htmlspecialchars($test['exam_name']); ?> - <?php echo htmlspecialchars($test['category_name']); ?> - <?php echo $test_language; ?></p>
                        <p class="mt-3 text-sm text-gray-500">
                            <i class="fas fa-clock mr-2"></i>Dictation Duration: <?php echo $dictation_duration_display; ?>
                        </p>
                    </div>
                    
                    <button id="startTestBtn" class="btn-primary text-white font-bold py-3 px-8 rounded-lg text-lg w-full mt-auto">
                        <i class="fas fa-play mr-2"></i> Start Dictation
                    </button>
                </div>
                
                <!-- Right Column - Instructions -->
                <div class="bg-gray-50 p-8 md:w-1/2">
                    <h3 class="font-medium text-lg mb-4 text-gray-700">
                        <i class="fas fa-info-circle mr-2 text-gray-500"></i>Dictation Instructions
                    </h3>
                    <ul class="space-y-3 text-gray-600">
                        <li class="flex items-start">
                            <span class="inline-flex items-center justify-center w-6 h-6 mr-3 mt-0.5 border rounded border-gray-300 text-gray-500 bg-white">
                                <i class="fas fa-headphones-alt text-xs"></i>
                            </span>
                            <span class="flex-1">The dictation will play only once - listen carefully</span>
                        </li>
                        <li class="flex items-start">
                            <span class="inline-flex items-center justify-center w-6 h-6 mr-3 mt-0.5 border rounded border-gray-300 text-green-500 bg-white">
                                <i class="fas fa-check text-xs"></i>
                            </span>
                            <span class="flex-1">Pause, restart, and seeking are disabled to simulate real exam conditions</span>
                        </li>
                        <li class="flex items-start">
                            <span class="inline-flex items-center justify-center w-6 h-6 mr-3 mt-0.5 border rounded border-gray-300 text-gray-500 bg-white">
                                <i class="fas fa-volume-up text-xs"></i>
                            </span>
                            <span class="flex-1">You can adjust volume using the + and - buttons (10 steps)</span>
                        </li>
                        <li class="flex items-start">
                            <span class="inline-flex items-center justify-center w-6 h-6 mr-3 mt-0.5 border rounded border-gray-300 text-gray-500 bg-white">
                                <i class="fas fa-random text-xs"></i>
                            </span>
                            <span class="flex-1">Fluctuation level adds realistic speed variations (±5% to ±15%)</span>
                        </li>
                        <li class="flex items-start">
                            <span class="inline-flex items-center justify-center w-6 h-6 mr-3 mt-0.5 border rounded border-gray-300 text-gray-500 bg-white">
                                <i class="fas fa-stopwatch text-xs"></i>
                            </span>
                            <span class="flex-1">Timer will start automatically when dictation begins</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

    <!-- Main Test Container -->
    <div id="testContainer" class="hidden">
        <!-- Mobile Header -->
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
                        <span id="timerMobile"><?php echo $dictation_duration_display; ?></span>
                    </p>
                </div>
                
                <div class="w-6"></div> <!-- Spacer -->
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
                            <span id="timerDesktop"><?php echo $dictation_duration_display; ?></span>
                        </div>
                    </header>
                    
                    <!-- Audio Player Section -->
                    <div class="audio-player p-6 mb-6">
                        <audio id="dictationAudio" controls class="w-full mb-4" controlsList="nodownload noplaybackrate nofullscreen">
                            <source src="<?php echo htmlspecialchars($test['dictation_file']); ?>" type="audio/mpeg">
                            Your browser does not support the audio element.
                        </audio>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <!-- Speed Control -->
                            <div>
                                <label class="block font-semibold text-gray-700 mb-1">Dictation Speed
                                    <span class="ml-1 cursor-pointer info-icon" data-tooltip="speedTooltip">
                                        <i class="fas fa-info-circle text-gray-400"></i>
                                    </span>
                                </label>
                                <select id="speedSelector" class="w-full p-2 border rounded-md">
                                    <option value="0.83">50 WPM</option>
                                    <option value="1.00">60 WPM</option>
                                    <option value="1.17">70 WPM</option>
                                    <option value="1.33">80 WPM</option>
                                    <option value="1.50" selected>90 WPM</option>
                                    <option value="1.67">100 WPM</option>
                                    <option value="1.83">110 WPM</option>
                                    <option value="2.00">120 WPM</option>
                                    <option value="2.17">130 WPM</option>
                                    <option value="2.33">140 WPM</option>
                                    <option value="2.50">150 WPM</option>
                                </select>
                                <div id="speedTooltip" class="tooltip hidden mt-2 bg-white text-gray-800 p-2 border rounded-md shadow-lg">
                                    Controls playback speed in words per minute (WPM)
                                </div>
                            </div>

                             <!-- Fluctuation Control -->
                            <div>
                                <label class="block font-semibold text-gray-700 mb-1">Fluctuation Level
                                    <span class="ml-1 cursor-pointer info-icon" data-tooltip="fluctuationTooltip">
                                        <i class="fas fa-info-circle text-gray-400"></i>
                                    </span>
                                </label>
                                <select id="fluctuationSelector" class="w-full p-2 border rounded-md">
                                    <option value="0">Off</option>
                                    <option value="0.05">Low (±5%)</option>
                                    <option value="0.10">Medium (±10%)</option>
                                    <option value="0.15">High (±15%)</option>
                                </select>
                                <div id="fluctuationTooltip" class="tooltip hidden mt-2 bg-white text-gray-800 p-2 border rounded-md shadow-lg">
                                    Adds realistic speed variations to the dictation (±5% to ±15%)
                                </div>
                            </div>
                            
                            <!-- Volume Control -->
                            <div>
                                <label class="block font-semibold text-gray-700 mb-1">Volume Control
                                    <span class="ml-1 cursor-pointer info-icon" data-tooltip="volumeTooltip">
                                        <i class="fas fa-info-circle text-gray-400"></i>
                                    </span>
                                </label>
                                <div class="volume-controls">
                                    <button id="volumeDown" class="volume-btn" title="Decrease Volume">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <div class="flex-1 bg-gray-200 h-2 rounded-full">
                                        <div id="volumeBar" class="h-2 rounded-full" style="width: 100%"></div>
                                    </div>
                                    <button id="volumeUp" class="volume-btn" title="Increase Volume">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <div id="volumeTooltip" class="tooltip hidden mt-2 bg-white text-gray-800 p-2 border rounded-md shadow-lg">
                                    Adjust volume in 10% increments using + and - buttons
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-between items-center">
                            <div class="flex space-x-2">
                                <button id="playBtn" class="btn-primary text-white font-bold py-2 px-4 rounded-md">
                                    <i class="fas fa-play mr-2"></i> Play
                                </button>
                            </div>
                            
                            <a href="transcription.php?test_id=<?php echo $test_id; ?>" class="btn-primary text-white font-bold py-2 px-6 rounded-md">
                                <i class="fas fa-keyboard mr-2"></i> Transcribe Now
                            </a>
                        </div>
                    </div>
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
                </div>
                
                <!-- Test Info -->
                <div class="bg-gray-50 p-4 rounded-lg mb-4">
                    <h4 class="font-medium mb-2">Test Information</h4>
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
                            <span class="font-medium">Duration:</span> <?php echo $dictation_duration_display; ?>
                        </p>
                    </div>
                </div>
                
                <!-- Actions -->
                <button onclick="toggleFullscreen()" class="w-full flex items-center justify-center py-2 px-4 bg-gray-100 hover:bg-gray-200 rounded-lg mb-2">
                    <i class="fas fa-expand mr-2"></i> Fullscreen Mode
                </button>
            </div>
        </div>
    </div>

    <!-- Drawer Overlay -->
    <div class="drawer-overlay fixed inset-0 bg-black bg-opacity-50 z-40 hidden" id="drawerOverlay" onclick="toggleDrawer()"></div>

    <script>
    // Timer variables
    let testStartTime;
    let audioPlayed = false;
    let fluctuationInterval;
    
    // Start Test Button Click Handler
    document.getElementById("startTestBtn").addEventListener("click", function() {
        testStartTime = new Date();
        
        // Enter fullscreen
        let elem = document.documentElement;
        if (elem.requestFullscreen) {
            elem.requestFullscreen().catch(err => {
                console.log(`Fullscreen error: ${err.message}`);
            });
        }

        document.getElementById("fullscreenOverlay").style.display = "none";
        document.getElementById("testContainer").classList.remove("hidden");
        
        // Start the dictation timer (will be properly started when play is clicked)
    });

    // Audio Player Controls
    document.addEventListener("DOMContentLoaded", function() {
        const audio = document.getElementById("dictationAudio");
        const playBtn = document.getElementById("playBtn");
        const speedSelector = document.getElementById("speedSelector");
        const volumeUpBtn = document.getElementById("volumeUp");
        const volumeDownBtn = document.getElementById("volumeDown");
        const volumeBar = document.getElementById("volumeBar");
        const fluctuationSelector = document.getElementById("fluctuationSelector");
        
        // Get default values from PHP
        const defaultDuration = <?php echo $test['dictation_duration']; ?>;
        const defaultWpm = <?php echo $test['default_wpm']; ?>;
        const isExamMode = <?php echo $test['test_mode']; ?> === 1;
        let currentDuration = defaultDuration;
        
        // Initialize volume to 100%
        audio.volume = 1.0;
        updateVolumeBar();
        
        // Update timer display function
        function updateTimerDisplay(seconds) {
            const minutes = Math.floor(seconds / 60);
            const secs = seconds % 60;
            const timeString = `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
            
            // Update both timers
            document.getElementById("timerDesktop").textContent = timeString;
            if (document.getElementById("timerMobile")) {
                document.getElementById("timerMobile").textContent = timeString;
            }
        }
        
        // Calculate adjusted duration based on selected WPM (only in Test Mode)
        function calculateAdjustedDuration(selectedSpeed) {
            if (isExamMode) {
                // In Exam Mode, always use the original duration
                return defaultDuration;
            }
            
            // In Test Mode, calculate based on WPM
            const selectedWpm = defaultWpm * selectedSpeed;
            return Math.round((defaultWpm / selectedWpm) * defaultDuration);
        }
        
        // Volume control (10 steps)
        volumeUpBtn.addEventListener("click", () => {
            audio.volume = Math.min(1.0, audio.volume + 0.1);
            updateVolumeBar();
        });
        
        volumeDownBtn.addEventListener("click", () => {
            audio.volume = Math.max(0.0, audio.volume - 0.1);
            updateVolumeBar();
        });
        
        function updateVolumeBar() {
            volumeBar.style.width = `${audio.volume * 100}%`;
        }
        
        // Update duration when speed changes
        speedSelector.addEventListener("change", (e) => {
            const newSpeed = parseFloat(e.target.value);
            
            // Only update duration in Test Mode
            if (!isExamMode) {
                currentDuration = calculateAdjustedDuration(newSpeed);
                updateTimerDisplay(currentDuration);
            }
            
            // Always update playback rate
            audio.playbackRate = newSpeed;
            
            // Restart fluctuation with new base speed
            if (fluctuationInterval) {
                clearInterval(fluctuationInterval);
                setupFluctuation();
            }
            
            // Disable speed change in Exam Mode after playback starts
            if (isExamMode && audioPlayed) {
                speedSelector.disabled = true;
            }
        });
        
        // Fluctuation control
        function setupFluctuation() {
            const fluctuation = parseFloat(fluctuationSelector.value);
            
            if (fluctuationInterval) {
                clearInterval(fluctuationInterval);
            }
            
            if (fluctuation > 0 && !audio.paused) {
                const baseSpeed = parseFloat(speedSelector.value);
                
                fluctuationInterval = setInterval(() => {
                    const variation = (Math.random() * 2 - 1) * fluctuation;
                    audio.playbackRate = baseSpeed + (baseSpeed * variation);
                }, 5000); // Change every 5 seconds
            }
        }
        
        fluctuationSelector.addEventListener("change", () => {
            if (!audio.paused) {
                setupFluctuation();
            }
        });
        
        // Play button - only allow play once
        playBtn.addEventListener("click", () => {
            if (!audioPlayed) {
                audio.play();
                audioPlayed = true;
                playBtn.disabled = true;
                playBtn.classList.remove("btn-primary");
                playBtn.classList.add("bg-gray-400", "cursor-not-allowed");
                
                // Apply initial speed setting
                const initialSpeed = parseFloat(speedSelector.value);
                audio.playbackRate = initialSpeed;
                currentDuration = calculateAdjustedDuration(initialSpeed);
                
                // Start the timer with adjusted duration
                startDictationTimer(currentDuration);
                
                // In Exam Mode, disable speed selection after playback starts
                if (isExamMode) {
                    speedSelector.disabled = true;
                    document.getElementById("speedSelector").classList.add("bg-gray-100", "cursor-not-allowed");
                }
                
                // Start fluctuation if enabled
                setupFluctuation();
            }
        });
        
        // Prevent all audio controls except volume
        audio.addEventListener("play", function() {
            // Disable speed and fluctuation changes after playback starts in Exam Mode
            if (isExamMode) {
                speedSelector.disabled = true;
                fluctuationSelector.disabled = true;
            }
            
            // Setup initial fluctuation
            setupFluctuation();
        });
        
        // Disable right-click and keyboard shortcuts
        audio.addEventListener("contextmenu", (e) => e.preventDefault());
        document.addEventListener("keydown", (e) => {
            // Prevent space bar, media keys, etc.
            if (e.code === "Space" || e.code.includes("Media") || e.code === "ArrowLeft" || e.code === "ArrowRight") {
                e.preventDefault();
            }
        });
        
        // Hide native controls (except for fallback)
        audio.controls = false;
        
        // Disable seeking
        audio.addEventListener("seeking", function(e) {
            e.preventDefault();
            audio.currentTime = 0;
        });
        
        // Disable pausing
        audio.addEventListener("pause", function(e) {
            if (!audio.ended) {
                e.preventDefault();
                audio.play();
            }
        });
        
        // When audio ends, show transcription button more prominently
        audio.addEventListener("ended", function() {
            document.querySelector('a[href*="transcription.php"]').classList.add("animate-pulse");
        });
        
        // Tooltip functionality
        const infoIcons = document.querySelectorAll(".info-icon");
        infoIcons.forEach(icon => {
            const tooltipId = icon.getAttribute("data-tooltip");
            const tooltip = document.getElementById(tooltipId);
            
            icon.addEventListener("mouseenter", () => tooltip.classList.remove("hidden"));
            icon.addEventListener("mouseleave", () => tooltip.classList.add("hidden"));
        });
        
        // Initialize with default values
        updateTimerDisplay(currentDuration);
    });

    // Dictation Timer Function
    function startDictationTimer(duration) {
        let remainingTime = duration;
        const displayDesktop = document.getElementById("timerDesktop");
        const displayMobile = document.getElementById("timerMobile");
        
        window.dictationTimerInterval = setInterval(function() {
            const minutes = Math.floor(remainingTime / 60);
            const seconds = remainingTime % 60;
            const timeString = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            // Update both timers
            displayDesktop.textContent = timeString;
            if (displayMobile) {
                displayMobile.textContent = timeString;
            }
            
            if (--remainingTime < 0) {
                clearInterval(window.dictationTimerInterval);
                // Auto-redirect when time ends
                window.location.href = "transcription.php?test_id=<?php echo $test_id; ?>";
            }
        }, 1000);
    }

    // Fixed toggleDrawer function
    function toggleDrawer() {
        const drawer = document.getElementById("mobileDrawer");
        const overlay = document.getElementById("drawerOverlay");
        
        drawer.classList.toggle("open");
        overlay.classList.toggle("hidden");
        overlay.classList.toggle("show");
        
        // Prevent scrolling when drawer is open
        document.body.style.overflow = drawer.classList.contains("open") ? "hidden" : "";
    }

    function toggleFullscreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen();
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        }
    }
</script>
</body>
</html>
