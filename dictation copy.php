<?php
require 'backend/config.php'; // ✅ Use your DB connection

$audioSrc = '';
if (isset($_GET['test_id'])) {
    $test_id = intval($_GET['test_id']);

    $query = "SELECT dictation_file FROM tests WHERE test_id = $test_id LIMIT 1";
    $result = mysqli_query($conn, $query);

    if ($row = mysqli_fetch_assoc($result)) {
        $audioSrc = $row['dictation_file']; // Store the path from DB
    }
}
$test_id = isset($_GET['test_id']) ? intval($_GET['test_id']) : 0; // Ensure test_id is available
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Steno Dictation Player</title>
    <!-- Favicon -->
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon" />
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@200..700&display=swap" rel="stylesheet">
    <style>
        select:focus {
            outline: none;
            border-color: #d1d5db;
            box-shadow: none;
        }

        body {
            font-family: 'Poppins', sans-serif;
        }

        .tooltip {
            font-family: 'Oswald', sans-serif;
            font-size: 9px;
        }
    </style>
</head>

<body class="flex items-center justify-center min-h-screen bg-gray-100">
    <div class="bg-white p-6 rounded-lg shadow-lg w-80">
        <div class="flex justify-center">
            <img src="assets/images/stenoplus-logo.png" alt="Stenoshala Logo" class="w-auto h-12 mb-5">
        </div>
        <audio id="dictationAudio" controls class="w-full mb-4" controlsList="nodownload noplaybackrate">
            <source src="<?= htmlspecialchars($audioSrc) ?>" type="audio/mpeg">
            Your browser does not support the audio element.
        </audio>

        <div class="flex justify-between items-center mb-4">
            <button id="playBtn" class="w-full bg-[#002147] text-white font-bold py-2 rounded-md hover:bg-[#003066]">
                Play Dictation
            </button>
        </div>

        <!-- Dictation Speed with Tooltip -->
        <div class="relative">
            <label class="block font-semibold text-gray-700 mb-1">Dictation Speed
                <span class="ml-1 cursor-pointer info-icon" data-tooltip="speedTooltip">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline" viewBox="0 0 20 20"
                        fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-9-3a1 1 0 112 0v1a1 1 0 11-2 0V7zm1 2a1 1 0 00-1 1v3a1 1 0 002 0v-3a1 1 0 00-1-1z"
                            clip-rule="evenodd" />
                    </svg>
                </span>
            </label>
            <div id="speedTooltip"
                class="tooltip hidden absolute bg-white text-gray-800 p-2 border rounded-md shadow-lg w-auto text-sm mt-0 ml-[20%] z-50">
                <strong>Dictation Speed (WPM):</strong> Controls how fast the audio is played.
                <ul class="list-disc pl-4">
                    <li>50 WPM: Slow</li>
                    <li>100 WPM: Normal</li>
                    <li>150+ WPM: Fast</li>
                </ul>
            </div>
        </div>

        <select id="speedSelector" class="w-full p-2 border rounded-md mb-4">
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
            <option value="2.67">160 WPM</option>
        </select>

        <!-- Fluctuation Level with Tooltip -->
        <div class="relative">
            <label class="block font-semibold text-gray-700 mb-1">Fluctuation Level
                <span class="ml-1 cursor-pointer info-icon" data-tooltip="fluctuationTooltip">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline" viewBox="0 0 20 20"
                        fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-9-3a1 1 0 112 0v1a1 1 0 11-2 0V7zm1 2a1 1 0 00-1 1v3a1 1 0 002 0v-3a1 1 0 00-1-1z"
                            clip-rule="evenodd" />
                    </svg>
                </span>
            </label>
            <div id="fluctuationTooltip"
                class="tooltip hidden absolute bg-white text-gray-800 p-2 border rounded-md shadow-lg w-auto text-sm mt-0 ml-[20%] z-50">
                Fluctuation Level introduces random speed variations to simulate real dictation pace.
                <ul class="list-disc pl-4">
                    <li><strong>Low (±5%)</strong>: Small variations</li>
                    <li><strong>Medium (±10%)</strong>: Noticeable fluctuations</li>
                    <li><strong>High (±15%)</strong>: More unpredictable</li>
                </ul>
            </div>
        </div>

        <select id="fluctuationSelector" class="w-full p-2 border rounded-md mb-4">
            <option value="0">Off</option>
            <option value="0.05">Low (±5%)</option>
            <option value="0.10">Medium (±10%)</option>
            <option value="0.15">High (±15%)</option>
        </select>

        <a href="transcription.php?test_id=<?php echo $test_id; ?>">
        <button id="transcribeBtn" class="w-full bg-red-600 text-white font-bold py-2 rounded-md hover:bg-red-700">
            Transcribe Now
        </button>
        </a> 
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const tooltips = document.querySelectorAll(".tooltip");
            const infoIcons = document.querySelectorAll(".info-icon");

            function isTouchDevice() {
                return "ontouchstart" in window || navigator.maxTouchPoints > 0;
            }

            infoIcons.forEach(icon => {
                const tooltipId = icon.getAttribute("data-tooltip");
                const tooltip = document.getElementById(tooltipId);

                if (!tooltip) return;

                if (!isTouchDevice()) {
                    // Desktop: Show tooltip on hover
                    icon.addEventListener("mouseenter", () => tooltip.classList.remove("hidden"));
                    icon.addEventListener("mouseleave", () => tooltip.classList.add("hidden"));
                } else {
                    // Mobile: Toggle tooltip on click
                    icon.addEventListener("click", (event) => {
                        event.stopPropagation();
                        // Hide all other tooltips
                        tooltips.forEach(t => {
                            if (t !== tooltip) t.classList.add("hidden");
                        });
                        // Toggle this one
                        tooltip.classList.toggle("hidden");
                    });
                }
            });

            // Hide tooltip when clicking anywhere else
            document.addEventListener("click", (event) => {
                if (!event.target.classList.contains("info-icon")) {
                    tooltips.forEach(tooltip => tooltip.classList.add("hidden"));
                }
            });
        });

        // Audio Player Script
        document.addEventListener("DOMContentLoaded", function () {
            const audio = document.getElementById("dictationAudio");
            const playBtn = document.getElementById("playBtn");
            const speedSelector = document.getElementById("speedSelector");

            playBtn.addEventListener("click", () => {
                if (audio.paused) {
                    audio.play();
                    playBtn.textContent = "Pause Dictation";
                } else {
                    audio.pause();
                    playBtn.textContent = "Play Dictation";
                }
            });

            // Prevent right-click on the audio element
            audio.addEventListener("contextmenu", (event) => event.preventDefault());

            // Change playback speed based on selection
            speedSelector.addEventListener("change", (event) => {
                audio.playbackRate = event.target.value;
            });

            // Disable keyboard shortcuts (Spacebar & Media Keys)
            document.addEventListener("keydown", (event) => {
                if (event.code === "Space" || event.code.includes("Media")) {
                    event.preventDefault();
                }
            });
        });
    </script>
</body>

</html>
