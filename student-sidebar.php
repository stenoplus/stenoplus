<!-- Sidebar (Desktop & Mobile) -->
<aside id="sidebar" class="w-64 bg-[#002147] text-white h-screen p-4 fixed sidebar sidebar-hidden md:translate-x-0 z-50">
    <!-- Close Button (Only for Mobile) -->
    <button id="closeSidebar" class="absolute top-4 right-4 text-white md:hidden">
        <i data-lucide="x"></i>
    </button>

    <img src="assets/images/stenoplus-logo.png" alt="StenoPlus Logo" class="h-12 mx-auto mb-4 bg-white p-1 rounded logo">
    <ul class="space-y-3 h-full pb-20">
        <li class="flex items-center space-x-2 p-2 rounded <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-[#D2171E]' : 'hover:bg-gray-700'; ?>">
            <i data-lucide="layout-dashboard"></i> 
            <a href="dashboard.php" class="w-full">Dashboard</a>
        </li>
        <li class="flex items-center space-x-2 p-2 rounded <?php echo strpos($_SERVER['REQUEST_URI'], 'leaderboard.php') !== false ? 'bg-[#D2171E]' : 'hover:bg-gray-700'; ?>">
            <i data-lucide="award"></i> 
            <a href="leaderboard.php">Leaderboard</a>
        </li>
        <li class="flex items-center space-x-2 p-2 rounded <?php echo basename($_SERVER['PHP_SELF']) == 'skills-test.php' ? 'bg-[#D2171E]' : 'hover:bg-gray-700'; ?>">
            <i data-lucide="clipboard-list"></i> 
            <a href="skills-test.php">Skills Test</a>
        </li>
        <li class="flex items-center space-x-2 p-2 hover:bg-gray-700 rounded <?php echo basename($_SERVER['PHP_SELF']) == 'my-performance.php' ? 'bg-[#D2171E]' : ''; ?>"> 
            <i data-lucide="bar-chart"></i> 
            <a href="my-performance.php">My Performance</a>
        </li>
        <li class="flex items-center space-x-2 p-2 hover:bg-gray-700 rounded">
            <i data-lucide="file-text"></i> 
            <a href="#">Study Materials</a>
        </li>
        <li class="flex items-center space-x-2 p-2 hover:bg-gray-700 rounded <?php echo basename($_SERVER['PHP_SELF']) == 'pricing.php' ? 'bg-[#D2171E]' : ''; ?>">
            <i data-lucide="credit-card"></i> 
            <a href="pricing.php">Pricing Plans</a>
        </li>
        <li class="flex items-center space-x-2 p-2 hover:bg-gray-700 rounded <?php echo basename($_SERVER['PHP_SELF']) == 'my-profile.php' ? 'bg-[#D2171E]' : ''; ?>">
            <i data-lucide="user"></i> 
            <a href="my-profile.php">My Profile</a>
        </li>
        <li class="flex items-center space-x-2 p-2 hover:bg-gray-700 rounded">
            <i data-lucide="help-circle"></i> 
            <a href="#">Help & Support</a>
        </li>
        <li class="flex items-center space-x-2 p-2 hover:bg-gray-700 rounded">
            <i data-lucide="settings"></i> 
            <a href="#">Settings</a>
        </li>
        <li class="flex items-center space-x-2 p-2 hover:bg-red-600 rounded">
            <i data-lucide="log-out"></i> 
            <a href="backend/authentication/logout.php">Logout</a>
        </li>
    </ul>
</aside>
