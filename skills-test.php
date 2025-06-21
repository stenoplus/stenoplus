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
        <section class="mt-6 p-6 lg:p-0 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-3 gap-6">
       <!-- Test Card - SSC Stenographer Grade C -->
<div class="bg-gradient-to-b from-red-50 to-white dark:from-gray-800 dark:to-gray-900 rounded-lg shadow-md dark:shadow-lg dark:shadow-red-900 ring-1 ring-transparent dark:ring-[#D2171E] p-6 w-64 text-center hover:shadow-xl dark:hover:shadow-2xl transition-all duration-300 ease-in-out transform hover:scale-105 w-full mx-auto">
    <!-- Logo -->
    <div class="flex justify-center mb-4">
        <div class="p-2 rounded-full shadow-md dark:shadow-lg bg-white dark:bg-gray-800">
            <img src="assets/images/ssc-logo.png" alt="SSC Logo" class="h-16 w-16 object-contain rounded-full">
        </div>
    </div>

    <!-- Title -->
    <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4 leading-snug">
        SSC Stenographer Grade C
    </h2>

    <!-- Test Count -->
    <div class="flex items-center justify-center bg-[#002147] text-white font-semibold rounded-md px-2 py-2 mb-4 text-sm dark:bg-[#D2171E] w-2/3 mx-auto">
        <i data-lucide="book-open" class="mr-2 w-4 h-4"></i>
        15 Tests
    </div>

    <!-- Explore Button -->
    <a href="explore-test.php">
        <button class="bg-red-500 hover:bg-[#D2171E] text-white font-bold py-2 px-4 rounded-md w-2/3 block mx-auto transition dark:bg-[#D2171E] dark:hover:bg-red-600">
            Explore
        </button>
    </a>
</div>

<!-- Test Card - SSC Stenographer Grade D -->
<div class="bg-gradient-to-b from-red-50 to-white dark:from-gray-800 dark:to-gray-900 rounded-lg shadow-md dark:shadow-lg dark:shadow-red-900 ring-1 ring-transparent dark:ring-[#D2171E] p-6 w-64 text-center hover:shadow-xl dark:hover:shadow-2xl transition-all duration-300 ease-in-out transform hover:scale-105 w-full mx-auto">
    <div class="flex justify-center mb-4">
        <div class="p-2 rounded-full shadow-md dark:shadow-lg bg-white dark:bg-gray-800">
            <img src="assets/images/ssc-logo.png" alt="SSC Logo" class="h-16 w-16 object-contain rounded-full">
        </div>
    </div>
    <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4 leading-snug">SSC Stenographer Grade D</h2>
    <div class="flex items-center justify-center bg-[#002147] text-white font-semibold rounded-md px-2 py-2 mb-4 text-sm dark:bg-[#D2171E] w-2/3 mx-auto">
        <i data-lucide="book-open" class="mr-2 w-4 h-4"></i> 10 Tests
    </div>
    <a href="explore-test.php">
        <button class="bg-red-500 hover:bg-[#D2171E] dark:bg-[#D2171E] dark:hover:bg-red-600 text-white font-bold py-2 px-4 rounded-md w-2/3 block mx-auto transition">Explore</button>
    </a>
</div>

<!-- Test Card - Supreme Court Stenographer -->
<div class="bg-gradient-to-b from-red-50 to-white dark:from-gray-800 dark:to-gray-900 rounded-lg shadow-md dark:shadow-lg dark:shadow-red-900 ring-1 ring-transparent dark:ring-[#D2171E] p-6 w-64 text-center hover:shadow-xl dark:hover:shadow-2xl transition-all duration-300 ease-in-out transform hover:scale-105 w-full mx-auto">
    <div class="flex justify-center mb-4">
        <div class="p-2 rounded-full shadow-md dark:shadow-lg bg-white dark:bg-gray-800">
            <img src="assets/images/supreme-court-logo.svg" alt="Supreme Court Logo" class="h-16 w-16 object-contain rounded-full">
        </div>
    </div>
    <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4 leading-snug">Supreme Court Stenographer</h2>
    <div class="flex items-center justify-center bg-[#002147] text-white font-semibold rounded-md px-2 py-2 mb-4 text-sm dark:bg-[#D2171E] w-2/3 mx-auto">
        <i data-lucide="book-open" class="mr-2 w-4 h-4"></i> 20 Tests
    </div>
    <a href="explore-test.php">
        <button class="bg-red-500 hover:bg-[#D2171E] dark:bg-[#D2171E] dark:hover:bg-red-600 text-white font-bold py-2 px-4 rounded-md w-2/3 block mx-auto transition">Explore</button>
    </a>
</div>

<!-- Test Card - Bihar Civil Court Stenographer -->
<div class="bg-gradient-to-b from-red-50 to-white dark:from-gray-800 dark:to-gray-900 rounded-lg shadow-md dark:shadow-lg dark:shadow-red-900 ring-1 ring-transparent dark:ring-[#D2171E] p-6 w-64 text-center hover:shadow-xl dark:hover:shadow-2xl transition-all duration-300 ease-in-out transform hover:scale-105 w-full mx-auto">
    <div class="flex justify-center mb-4">
        <div class="p-2 rounded-full shadow-md dark:shadow-lg bg-white dark:bg-gray-800">
            <img src="assets/images/patna-high-court-logo.png" alt="Bihar Court Logo" class="h-16 w-16 object-contain rounded-full">
        </div>
    </div>
    <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4 leading-snug">Bihar Civil Court Stenographer</h2>
    <div class="flex items-center justify-center bg-[#002147] text-white font-semibold rounded-md px-2 py-2 mb-4 text-sm dark:bg-[#D2171E] w-2/3 mx-auto">
        <i data-lucide="book-open" class="mr-2 w-4 h-4"></i> 10 Tests
    </div>
    <a href="explore-test.php">
        <button class="bg-red-500 hover:bg-[#D2171E] dark:bg-[#D2171E] dark:hover:bg-red-600 text-white font-bold py-2 px-4 rounded-md w-2/3 block mx-auto transition">Explore</button>
    </a>
</div>

<!-- Test Cards - Railway Stenographer -->
<div class="bg-gradient-to-b from-red-50 to-white dark:from-gray-800 dark:to-gray-900 rounded-lg shadow-md dark:shadow-lg dark:shadow-red-900 ring-1 ring-transparent dark:ring-[#D2171E] p-6 w-64 text-center hover:shadow-xl dark:hover:shadow-2xl transition-all duration-300 ease-in-out transform hover:scale-105 w-full mx-auto">
    <div class="flex justify-center mb-4">
        <div class="p-2 rounded-full shadow-md dark:shadow-lg bg-white dark:bg-gray-800">
            <img src="assets/images/indian-railways.svg" alt="Railway Logo" class="h-16 w-16 object-contain rounded-full">
        </div>
    </div>
    <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4 leading-snug">Railway Stenographer</h2>
    <div class="flex items-center justify-center bg-[#002147] text-white font-semibold rounded-md px-2 py-2 mb-4 text-sm dark:bg-[#D2171E] w-2/3 mx-auto">
        <i data-lucide="book-open" class="mr-2 w-4 h-4"></i> 20 Tests
    </div>
    <a href="explore-test.php">
        <button class="bg-red-500 hover:bg-[#D2171E] dark:bg-[#D2171E] dark:hover:bg-red-600 text-white font-bold py-2 px-4 rounded-md w-2/3 block mx-auto transition">Explore</button>
    </a>
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
