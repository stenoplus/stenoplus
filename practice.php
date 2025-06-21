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
    <title>Practice - StenoPlus</title>
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

            <h2 class="text-xl font-semibold">Practice</h2>
            
            <div class="flex items-center space-x-4">
                <i data-lucide="bell" class="cursor-pointer"></i>
                <i data-lucide="moon" id="darkModeToggle" class="cursor-pointer"></i>

                <!-- Profile Dropdown -->
                <div class="relative">
                    <img src="assets/images/student.png" alt="Profile" class="w-10 h-10 rounded-full cursor-pointer" id="profileBtn">
                    <div id="profileDropdown" class="hidden absolute right-0 bg-white shadow-lg rounded-md w-40 mt-2">
                        <p class="p-2 text-sm"><?php echo htmlspecialchars($user_name); ?></p>
                        <p class="p-2 text-xs text-gray-500">Student ID: <?php echo htmlspecialchars($student_id); ?></p>
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

        <!-- Dashboard Content -->
        <section class="mt-6 p-6 lg:p-0">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-4 rounded-md shadow">
                    <h3 class="text-lg font-semibold">Quick Progress Summary</h3>
                    <p class="text-gray-500">Speed: 80 WPM, Accuracy: 95%</p>
                </div>
                <div class="bg-white p-4 rounded-md shadow">
                    <h3 class="text-lg font-semibold">Recent Test History</h3>
                    <p class="text-gray-500">Last Test: 92% Accuracy</p>
                </div>
                <div class="bg-white p-4 rounded-md shadow">
                    <h3 class="text-lg font-semibold">Announcements & Updates</h3>
                    <p class="text-gray-500">Next mock test on March 15th.</p>
                </div>
            </div>
        </section>
    </main>

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
</script>

</body>
</html>
