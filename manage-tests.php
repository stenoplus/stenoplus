<?php
session_start();

// ✅ Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Redirect to login page
    exit();
}

// ✅ Retrieve user details
$user_name = $_SESSION['user_name'];
$student_id = $_SESSION['student_id'];

// ✅ Database Connection
require 'backend/config.php';

// Function to format duration from seconds to MM:SS
function formatDuration($seconds) {
    $minutes = floor($seconds / 60);
    $remainingSeconds = $seconds % 60;
    return sprintf("%02d:%02d", $minutes, $remainingSeconds);
}

// Fetch exams
$examOptions = "";
$sql = "SELECT exam_id, exam_name FROM exams ORDER BY exam_name ASC";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $examOptions .= "<option value='{$row['exam_id']}'>{$row['exam_name']}</option>";
    }
}

// Fetch categories
$categoryOptions = "";
$sql = "SELECT category_id, category_name FROM categories WHERE status = 'active' ORDER BY category_name ASC";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categoryOptions .= "<option value='{$row['category_id']}'>{$row['category_name']}</option>";
    }
}

// Fetch tests data from the database
$query = "SELECT t.*, e.exam_name, c.category_name 
          FROM tests t
          JOIN exams e ON t.exam_id = e.exam_id
          JOIN categories c ON t.category_id = c.category_id
          ORDER BY t.test_id DESC";

$result = mysqli_query($conn, $query);

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tests - StenoPlus</title>
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
            scrollbar-width: thin;
            scrollbar-color: #696969 #002147;
        }
        .sidebar-hidden { transform: translateX(-100%); }
        body {
            font-family: 'Poppins', sans-serif;
            transition: background-color 0.3s, color 0.3s;
        }
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
        .toggle-checkbox:checked {
            right: 0;
            border-color: #D2171E;
            background-color: #D2171E;
        }
        .toggle-checkbox:checked + .toggle-label {
            background-color: #002147;
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
    <?php include 'sidebar.php'; ?>
    <!-- Main Content -->
    <main class="md:ml-64 w-full lg:p-6 md:p-0">
        <!-- Top Bar -->
        <header class="flex justify-between items-center bg-white shadow p-4 rounded-none lg:rounded-lg dark:bg-gray-800">
            <!-- Hamburger Menu (Only for Mobile) -->
            <button id="openSidebar" class="md:hidden">
                <i data-lucide="menu"></i>
            </button>

            <h2 class="text-xl font-semibold">Tests</h2>
            
            <div class="flex items-center space-x-4">
                <i data-lucide="bell" class="cursor-pointer"></i>
                <i data-lucide="moon" id="darkModeToggle" class="cursor-pointer"></i>

                <!-- Profile Dropdown -->
                <div class="relative">
                    <img src="assets/images/student.png" alt="Profile" class="w-10 h-10 rounded-full cursor-pointer" id="profileBtn">
                    <div id="profileDropdown" class="hidden absolute right-0 bg-white shadow-lg rounded-md w-40 mt-2 dark:bg-gray-700">
                        <p class="p-2 text-sm"><?php echo htmlspecialchars($user_name); ?></p>
                        <p class="p-2 text-xs text-gray-500">Role: <?php echo htmlspecialchars($student_id); ?></p>
                        <hr>
                        <li class="flex items-center space-x-2 p-2 text-sm hover:bg-gray-200 dark:hover:bg-gray-600">
                            <i data-lucide="user" class="mr-0 w-4 h-4"></i> 
                            <a href="#">View Profile</a>
                        </li>
                        <li class="flex items-center space-x-2 p-2 hover:bg-red-600 hover:text-white rounded-b text-sm">
                            <i data-lucide="log-out" class="mr-0 w-4 h-4"></i> 
                            <a href="backend/authentication/logout.php">Logout</a>
                        </li>
                    </div>
                </div>
            </div>
        </header>

        <!-- Manage Tests Content -->
        <section class="mt-6 p-6 lg:p-0">
            <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                <h3 class="text-lg font-semibold mb-4">Manage Tests</h3>
                <button id="openTestPopup" class="bg-[#D2171E] text-white px-4 py-2 rounded">Add New Test</button>
                <div class="w-full overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full mt-4 border-collapse border border-gray-300 min-w-max dark:border-gray-600">
                            <thead>
                                <tr class="bg-gray-200 dark:bg-gray-700">
                                    <th class="border border-gray-300 px-2 py-2">ID</th>
                                    <th class="border border-gray-300 px-2 py-2">Name</th>
                                    <th class="border border-gray-300 px-2 py-2">Exam</th>
                                    <th class="border border-gray-300 px-2 py-2">Category</th>
                                    <th class="border border-gray-300 px-2 py-2">Dictation Duration</th>
                                    <th class="border border-gray-300 px-2 py-2">Transcript Duration</th>
                                    <th class="border border-gray-300 px-2 py-2">Word Count</th>
                                    <th class="border border-gray-300 px-2 py-2">Mode</th>
                                    <th class="border border-gray-300 px-2 py-2">Dictation</th>
                                    <th class="border border-gray-300 px-2 py-2">Transcript</th>
                                    <th class="border border-gray-300 px-2 py-2">Language</th>
                                    <th class="border border-gray-300 px-2 py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                                <tr class="dark:border-gray-600">
                                    <td class="border border-gray-300 px-2 py-2 text-center"><?= $row['test_id'] ?></td>
                                    <td class="border border-gray-300 px-2 py-2 text-center"><?= htmlspecialchars($row['test_name']) ?></td>
                                    <td class="border border-gray-300 px-2 py-2 text-center"><?= htmlspecialchars($row['exam_name']) ?></td>
                                    <td class="border border-gray-300 px-2 py-2 text-center"><?= htmlspecialchars($row['category_name']) ?></td>
                                    <td class="border border-gray-300 px-2 py-2 text-center">
                                        <?= formatDuration($row['dictation_duration']) ?> (MM:SS)
                                    </td>
                                    <td class="border border-gray-300 px-2 py-2 text-center">
                                        <?= formatDuration($row['transcript_duration']) ?> (MM:SS)
                                    </td>
                                    <td class="border border-gray-300 px-2 py-2 text-center"><?= $row['word_count'] ?></td>
                                    <td class="border border-gray-300 px-2 py-2 text-center">
                                        <button class="toggle-mode px-3 py-1 rounded-full text-xs <?= $row['test_mode'] ? 'bg-green-500 text-white' : 'bg-red-500 text-white' ?>" 
                                                data-id="<?= $row['test_id']; ?>" 
                                                data-mode="<?= $row['test_mode']; ?>">
                                            <?= $row['test_mode'] ? 'Exam Mode' : 'Test Mode' ?>
                                        </button>
                                    </td>
                                    <td class="border border-gray-300 px-2 py-2 text-center">
                                        <div class="flex items-center justify-center h-full">
                                            <button id="audioControl<?= $row['test_id'] ?>" class="w-8 h-8 flex items-center justify-center bg-[#D2171E] text-white rounded-full shadow-lg hover:bg-[#002147] transition duration-300 dark:hover:bg-gray-600 dark:bg-gray-500">
                                                <svg id="playIcon<?= $row['test_id'] ?>" class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M8 5v14l11-7z"></path>
                                                </svg>
                                                <svg id="pauseIcon<?= $row['test_id'] ?>" class="w-4 h-4 hidden" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M6 5h4v14H6zM14 5h4v14h-4z"></path>
                                                </svg>
                                            </button>
                                            <audio id="audioPlayer<?= $row['test_id'] ?>" src="<?= $row['dictation_file'] ?>"></audio>
                                        </div>
                                    </td>
                                    <td class="border border-gray-300 px-2 py-2 text-center"> 
                                        <div class="flex items-center justify-center h-full">
                                            <?php
                                            $transcriptPath = $row['transcript_file'];
                                            if (!empty($row['transcript_file']) && file_exists($transcriptPath)) {
                                                $transcriptContent = file_get_contents($transcriptPath);
                                            } else {
                                                $transcriptContent = "Transcript not available";
                                            }                                    
                                            ?>
                                            <textarea name="transcript" id="transcript<?= $row['test_id'] ?>" cols="25" rows="1"
                                                readonly class="border border-gray-300 px-2 py-2 text-left resize-y focus:outline-none dark:bg-gray-800 dark:text-white"
                                                placeholder="<?= htmlspecialchars($row['transcript_file']) ?>" required><?= htmlspecialchars($transcriptContent) ?></textarea>
                                        </div>
                                    </td>
                                    <td class="border border-gray-300 px-2 py-2 text-center"><?= strtoupper($row['language_code']) ?></td>
                                    <td class="border border-gray-300 px-2 py-2 text-center">
                                        <button class="edit-test edit-btn bg-[#D2171E] text-white px-2 py-1 w-8 h-8 rounded-full" 
                                                data-id="<?= $row['test_id']; ?>" 
                                                data-name="<?= htmlspecialchars($row['test_name']); ?>" 
                                                data-exam="<?= $row['exam_id']; ?>" 
                                                data-category="<?= $row['category_id']; ?>" 
                                                data-transcript_duration="<?= $row['transcript_duration']; ?>"
                                                data-dictation-duration="<?= $row['dictation_duration']; ?>"
                                                data-language="<?= $row['language_code']; ?>"
                                                data-word-count="<?= $row['word_count']; ?>"
                                                data-test-mode="<?= $row['test_mode']; ?>">
                                            <i data-lucide="edit" class="mr-0 w-4 h-4"></i>
                                        </button>
                                        <button class="delete-test bg-[#D2171E] text-white px-2 py-1 w-8 h-8 rounded-full" data-id="<?= $row['test_id']; ?>">
                                            <i data-lucide="trash-2" class="mr-0 w-4 h-4"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Popup Form -->
    <div id="testPopup" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg w-full max-w-2xl">
            <h2 class="text-lg font-semibold mb-4">Add/Edit Test</h2>
            <form method="POST" enctype="multipart/form-data" action="backend/test_submit.php" id="testForm" class="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-[80vh] overflow-y-auto p-2">
                <input type="hidden" name="testId" id="testId" value="">
                <div>
                    <label class="block mb-2">Test Name</label>
                    <input type="text" id="testName" name="testName" required placeholder="Enter Test Name" class="w-full p-2 border rounded focus:outline-none bg-gray-100 text-gray-600 placeholder-gray-400 placeholder:text-gray-600 dark:bg-gray-800 dark:text-white dark:border-gray-100 dark:placeholder-gray-400 dark:placeholder:text-gray-500 dark:placeholder:opacity-50">
                </div>
                <div>
                    <label class="block mb-2">Exam</label>
                    <select id="exam" name="exam" class="w-full p-2 border rounded focus:outline-none bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-white dark:border-gray-100">
                        <option value="">Select Exam</option>
                        <?php echo $examOptions; ?>
                    </select>
                </div>
                <div>
                    <label class="block mb-2">Category</label>
                    <select id="category" name="category" class="w-full p-2 border rounded focus:outline-none bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-white dark:border-gray-100">
                        <option value="">Select Category</option>
                        <?php echo $categoryOptions; ?>
                    </select>
                </div>
                <div>
                    <label class="block mb-2">Language</label>
                    <select id="language" name="language" class="w-full p-2 border rounded focus:outline-none bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-white dark:border-gray-100">
                        <option value="en">English</option>
                        <option value="hi">Hindi</option>
                    </select>
                </div>
                <div>
                    <label class="block mb-2">Dictation Duration (in seconds)</label>
                    <input type="number" id="dictationDuration" name="dictationDuration" min="1" class="w-full p-2 border rounded focus:outline-none bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-white dark:border-gray-100" placeholder="Enter dictation duration" required>
                </div>
                <div>
                    <label class="block mb-2">Transcript Duration (in seconds)</label>
                    <input type="number" id="transcriptDuration" name="transcriptDuration" min="1" class="w-full p-2 border rounded focus:outline-none bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-white dark:border-gray-100" placeholder="Enter transcript duration" required>
                </div>
                <div>
                    <label class="block mb-2">Word Count</label>
                    <input type="number" id="wordCount" name="wordCount" min="0" class="w-full p-2 border rounded focus:outline-none bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-white dark:border-gray-100" placeholder="Enter word count" value="0">
                </div>
                <div>
                    <label class="block mb-2">Test Mode</label>
                    <div class="flex items-center">
                        <div class="relative inline-block w-12 mr-2 align-middle select-none">
                            <input type="checkbox" name="testMode" id="testMode" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer"/>
                            <label for="testMode" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                        </div>
                        <span id="modeText" class="text-gray-700 dark:text-gray-300">Test Mode</span>
                    </div>
                </div>
                <div>
                    <label class="block mb-2 flex items-center">
                        Dictation
                        <span class="ml-2 text-blue-500 cursor-pointer relative group z-10">
                            <i data-lucide="info" class="w-4 h-4"></i>
                            <span class="absolute left-0 mt-2 w-48 bg-gray-700 text-white text-xs rounded p-1 hidden group-hover:block">
                                Supported: .mp3, .wav<br>
                                Convert text to audio: <a href="https://voicemaker.in/" target="_blank" class="underline">VoiceMaker.in</a>
                            </span>
                        </span>
                    </label>
                    <div class="relative flex items-center border border-gray-200 dark:border-gray-100 rounded p-2 cursor-pointer bg-gray-100 dark:bg-gray-800">
                        <img src="assets/images/upload.png" alt="Upload" class="w-5 h-5 mr-2 dark:hidden">
                        <img src="assets/images/upload-white.png" alt="Upload" class="w-5 h-5 mr-2 hidden dark:block">
                        <span class="text-gray-600 dark:text-gray-300">Upload Dictation</span>
                        <input type="file" id="dictation" name="dictation" class="absolute inset-0 opacity-0 cursor-pointer" accept="audio/mp3,audio/wav">
                    </div>
                </div>
                <div>
                    <label class="block mb-2 flex items-center">
                        Transcript
                        <span class="ml-2 text-blue-500 cursor-pointer relative group z-10">
                            <i data-lucide="info" class="w-4 h-4"></i>
                            <span class="absolute left-0 mt-2 w-48 bg-gray-700 text-white text-xs rounded p-1 hidden group-hover:block">
                                Supported: .txt only
                            </span>
                        </span>
                    </label>
                    <div class="relative flex items-center border border-gray-200 dark:border-gray-100 rounded p-2 cursor-pointer bg-gray-100 dark:bg-gray-800">
                        <img src="assets/images/upload.png" alt="Upload" class="w-5 h-5 mr-2 dark:hidden">
                        <img src="assets/images/upload-white.png" alt="Upload" class="w-5 h-5 mr-2 hidden dark:block">
                        <span class="text-gray-600 dark:text-gray-300">Upload Transcript</span>
                        <input type="file" id="transcript" name="transcript" class="absolute inset-0 opacity-0 cursor-pointer" accept=".txt">
                    </div>
                </div>
                <div class="md:col-span-2 flex justify-end gap-2 mt-4">
                    <button type="button" id="closeTestPopup" class="bg-gray-500 text-white px-4 py-2 rounded">Cancel</button>
                    <button type="submit" class="bg-[#D2171E] text-white px-4 py-2 rounded">Submit</button>
                </div>
            </form>
        </div>
    </div>
                                    
    <script>
    lucide.createIcons();

    // Profile Dropdown Toggle
    document.getElementById("profileBtn").addEventListener("click", function () {
        document.getElementById("profileDropdown").classList.toggle("hidden");
    });

    // Function to toggle dark mode
    function toggleDarkMode() {
        const isDark = document.documentElement.classList.toggle("dark");
        localStorage.setItem("darkMode", isDark ? "enabled" : "disabled");
    }

    // Apply dark mode on page load if enabled
    if (localStorage.getItem("darkMode") === "enabled") {
        document.documentElement.classList.add("dark");
    }

    // Dark mode button event listener
    document.getElementById("darkModeToggle").addEventListener("click", toggleDarkMode);

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

    // Close Sidebar when clicking outside
    document.addEventListener("click", function (event) {
        if (!sidebar.contains(event.target) && !openSidebar.contains(event.target) && !closeSidebar.contains(event.target)) {
            sidebar.classList.add("sidebar-hidden");
        }
    });

    // Audio Play/Pause Functionality
    document.querySelectorAll('[id^=audioControl]').forEach(button => {
        button.addEventListener('click', function () {
            let testId = this.id.replace('audioControl', '');
            let audio = document.getElementById('audioPlayer' + testId);
            let playIcon = document.getElementById('playIcon' + testId);
            let pauseIcon = document.getElementById('pauseIcon' + testId);

            if (audio.paused) {
                audio.play();
                playIcon.classList.add('hidden');
                pauseIcon.classList.remove('hidden');
            } else {
                audio.pause();
                playIcon.classList.remove('hidden');
                pauseIcon.classList.add('hidden');
            }
        });
    });

    // Modal Management Functions
    function openModal() {
        document.getElementById("testPopup").classList.remove("hidden");
        document.addEventListener('keydown', handleKeyDown);
    }

    function closeModal() {
        document.getElementById("testPopup").classList.add("hidden");
        document.removeEventListener('keydown', handleKeyDown);
    }

    function handleKeyDown(event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    }

    // Close modal when clicking outside content
    document.getElementById("testPopup").addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // Open Add Modal
    document.getElementById("openTestPopup")?.addEventListener("click", function() {
        document.getElementById("testForm").reset();
        document.getElementById("testId").value = "";
        document.getElementById("testMode").checked = false;
        document.getElementById("modeText").textContent = "Test Mode";
        openModal();
    });

    // Open Edit Modal
    document.querySelectorAll(".edit-test").forEach(button => {
        button.addEventListener("click", function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const exam = this.dataset.exam;
            const category = this.dataset.category;
            const transcriptDuration = this.dataset.transcript_duration;
            const dictationDuration = this.dataset.dictationDuration;
            const language = this.dataset.language;
            const wordCount = this.dataset.wordCount;
            const testMode = this.dataset.testMode;

            // Fill the form with data
            document.getElementById("testId").value = id;
            document.getElementById("testName").value = name;
            document.getElementById("exam").value = exam;
            document.getElementById("category").value = category;
            document.getElementById("transcriptDuration").value = transcriptDuration;
            document.getElementById("dictationDuration").value = dictationDuration;
            document.getElementById("language").value = language;
            document.getElementById("wordCount").value = wordCount;
            document.getElementById("testMode").checked = testMode === '1';
            document.getElementById("modeText").textContent = testMode === '1' ? 'Exam Mode' : 'Test Mode';

            openModal();
        });
    });

    // Close Modal via button
    document.getElementById("closeTestPopup").addEventListener("click", closeModal);

    // Test Mode Toggle in Popup
    document.getElementById('testMode').addEventListener('change', function() {
        document.getElementById('modeText').textContent = this.checked ? 'Exam Mode' : 'Test Mode';
    });

    document.querySelectorAll('.toggle-mode').forEach(button => {
    button.addEventListener('click', async function() {
        const testId = this.dataset.id;
        const currentMode = this.dataset.mode;
        const newMode = currentMode === '1' ? '0' : '1';
        const button = this;

        try {
            const response = await fetch('backend/toggle_test_mode.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `test_id=${testId}&mode=${newMode}`
            });

            // First check if response is OK
            if (!response.ok) {
                const error = await response.text();
                throw new Error(`Server error: ${error}`);
            }

            // Then try to parse JSON
            const data = await response.json();
            
            if (data.status !== 'success') {
                throw new Error(data.message || 'Toggle failed');
            }

            // Update UI
            button.dataset.mode = newMode;
            button.textContent = data.mode_text;
            button.className = `toggle-mode px-3 py-1 rounded-full text-xs ${
                newMode === '1' ? 'bg-green-500' : 'bg-red-500'
            } text-white`;

        } catch (error) {
            console.error('Toggle Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Toggle Failed',
                html: `
                    <div class="text-left">
                        <p>${error.message}</p>
                        ${error.message.includes('<') ? 
                         '<p class="text-sm mt-2">Server returned HTML instead of JSON</p>' : ''}
                    </div>
                `,
                timer: 5000
            });
        }
    });
});

    </script>

    <!-- SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Delete Test -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.delete-test').forEach(button => {
            button.addEventListener('click', function () {
                const testId = this.getAttribute('data-id');

                Swal.fire({
                    title: 'Are you sure?',
                    text: "This test and all its files will be permanently deleted!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#D2171E',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch('backend/delete_test.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `test_id=${testId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire('Deleted!', 'Test and all files have been deleted.', 'success').then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Error', data.message || 'Failed to delete.', 'error');
                            }
                        })
                        .catch(err => {
                            Swal.fire('Error', 'Something went wrong.', 'error');
                        });
                    }
                });
            });
        });
    });
    </script>
</body>
</html>
