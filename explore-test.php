<?php
session_start();

// ✅ Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: backend/authentication/login.php"); // Redirect to login page
    exit();
}

// ✅ Retrieve user details
$user_name = $_SESSION['user_name'];
$student_id = $_SESSION['student_id'];

// ✅ Database Connection
require 'backend/config.php';

// ✅ Fetch test data with JOINs
$sql = "SELECT 
            t.test_id,
            t.test_name,
            e.exam_name,
            c.category_name
        FROM tests t
        JOIN exams e ON t.exam_id = e.exam_id
        JOIN categories c ON t.category_id = c.category_id";

$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skills Test - StenoPlus</title>
        <!-- Favicon -->
        <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #F3F4F6; }
        /* .sidebar { transition: transform 0.3s ease-in-out; } */
        .sidebar {
        transition: transform 0.3s ease-in-out;
        height: 100vh; /* Ensures sidebar takes full viewport height */
        overflow-y: auto; /* Enables vertical scrolling */
        scrollbar-width: thin; /* Makes scrollbar less intrusive */
        scrollbar-color: #696969 #002147; /* Custom scrollbar color */
    }
        .sidebar-hidden { transform: translateX(-100%); }

        body {
    font-family: 'Poppins', sans-serif;
    transition: background-color 0.3s, color 0.3s;
}

.dark body {
    background-color: #111827; /* Dark gray background */
    color: #F3F4F6; /* Light text */
}

.dark .sidebar {
    background-color: #1F2937; /* Darker sidebar */
}

.dark .bg-white {
    background-color: #1E293B !important; /* Dark cards */
}

.dark .text-gray-500 {
    color: #9CA3AF !important; /* Lighter text */
}

.dark header {
    background-color: #1F2937 !important; /* Darker header */
}

.dark .logo {
    background-color:#F9F9F9 !important;
}

    </style>
    <script>
    tailwind.config = {
        darkMode: 'class'  // Enables dark mode toggle via class
    };
</script>
</head>
<body class="flex">

    <!-- Sidebar (Desktop & Mobile) -->
    <?php include 'student-sidebar.php'; ?>

    <!-- Main Content -->
    <main class="md:ml-64 w-full lg:p-6 md:p-0">
        <!-- Top Bar -->
        <header class="flex justify-between items-center bg-white shadow p-4 rounded-none lg:rounded-lg">
            <!-- Hamburger Menu (Only for Mobile) -->
            <button id="openSidebar" class="md:hidden">
                <i data-lucide="menu"></i>
            </button>

            <h2 class="text-xl font-semibold">Skills Test</h2>
            
            <div class="flex items-center space-x-4">
                <i data-lucide="bell" class="cursor-pointer"></i>
                <i data-lucide="moon" id="darkModeToggle" class="cursor-pointer"></i>

                <!-- Profile Dropdown -->
                <?php require 'profile-dropdown.php'; ?>
            </div>
        </header>

        <!-- Dashboard Content -->
        <section class="mt-6 p-6 lg:p-0">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <?php while ($row = mysqli_fetch_assoc($result)): ?>
        <div class="relative bg-white p-4 rounded-md shadow text-center">
            <span class="absolute top-2 right-2 bg-green-500 text-white text-xs px-2 py-1 rounded-md">Free</span>
            <h3 class="text-lg font-semibold mb-4"><?= htmlspecialchars($row['test_name']) ?></h3>
            <p class="text-gray-500"><?= htmlspecialchars($row['exam_name']) ?> - <?= htmlspecialchars($row['category_name']) ?></p>
            <ul class="text-left my-4">
                <li class="flex items-center mb-2">
                    <span class="mr-2 text-red-600"><i data-lucide="globe"></i></span> Language: English
                </li>
                <li class="flex items-center mb-2">
                    <span class="mr-2 text-red-600"><i data-lucide="file-text"></i></span> Total Words: 1000
                </li>
                <li class="flex items-center mb-2">
                    <span class="mr-2 text-red-600"><i data-lucide="clock"></i></span> Speeds: All Speeds
                </li>
            </ul>
            <a href="dictation.php?test_id=<?= $row['test_id'] ?>" class="mt-4 inline-block bg-[#002147] text-white px-4 py-2 rounded-md dark:bg-[#D2171E]">Take Test</a>
        </div>
    <?php endwhile; ?>
</div>
</section>
     </main>

    <script>
    lucide.createIcons();

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
</script>

</body>
</html>
