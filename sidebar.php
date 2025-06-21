<!-- Sidebar -->
    <aside id="sidebar" class="w-64 bg-[#002147] text-white h-screen p-4 fixed sidebar sidebar-hidden md:translate-x-0 z-50">
        <button id="closeSidebar" class="absolute top-4 right-4 text-white md:hidden">
            <i data-lucide="x"></i>
        </button>
        
        <img src="assets/images/stenoplus-logo.png" alt="StenoPlus Logo" class="h-12 mx-auto mb-4 bg-white p-1 rounded logo">
        <ul class="space-y-3 h-full pb-20">
    <li class="flex items-center space-x-2 p-2 rounded <?php echo basename($_SERVER['PHP_SELF']) == 'admin.php' ? 'bg-[#D2171E]' : 'hover:bg-gray-700'; ?>">
        <i data-lucide="layout-dashboard"></i> 
        <a href="admin.php" class="w-full">Dashboard</a>
    </li>
    <li class="flex items-center space-x-2 p-2 rounded <?php echo basename($_SERVER['PHP_SELF']) == 'students-analytics.php' ? 'bg-[#D2171E]' : 'hover:bg-gray-700'; ?>">
        <i data-lucide="trending-up"></i> 
        <a href="students-analytics.php" class="w-full">Students Analytics</a>
    </li>
    <li class="flex items-center space-x-2 p-2 rounded <?php echo basename($_SERVER['PHP_SELF']) == 'manage-students.php' ? 'bg-[#D2171E]' : 'hover:bg-gray-700'; ?>">
        <i data-lucide="users"></i> 
        <a href="manage-students.php" class="w-full">Manage Students</a>
    </li>
    <li class="flex items-center space-x-2 p-2 rounded <?php echo basename($_SERVER['PHP_SELF']) == 'manage-tests.php' ? 'bg-[#D2171E]' : 'hover:bg-gray-700'; ?>">
        <i data-lucide="clipboard-list"></i> 
        <a href="manage-tests.php" class="w-full">Manage Tests</a>
    </li>
    <li class="flex items-center space-x-2 p-2 rounded <?php echo basename($_SERVER['PHP_SELF']) == 'manage-categories.php' ? 'bg-[#D2171E]' : 'hover:bg-gray-700'; ?>">
        <i data-lucide="layers"></i> 
        <a href="manage-categories.php" class="w-full">Manage Categories</a>
    </li>
    <li class="flex items-center space-x-2 p-2 rounded <?php echo basename($_SERVER['PHP_SELF']) == 'manage-exams.php' ? 'bg-[#D2171E]' : 'hover:bg-gray-700'; ?>">
        <i data-lucide="book-open"></i> 
        <a href="manage-exams.php" class="w-full">Manage Exams</a>
    </li>
    <li class="flex items-center space-x-2 p-2 rounded <?php echo basename($_SERVER['PHP_SELF']) == 'performance-reports.php' ? 'bg-[#D2171E]' : 'hover:bg-gray-700'; ?>">
        <i data-lucide="bar-chart"></i> 
        <a href="performance-reports.php" class="w-full">Performance Reports</a>
    </li>
    <li class="flex items-center space-x-2 p-2 rounded <?php echo basename($_SERVER['PHP_SELF']) == 'packages.php' ? 'bg-[#D2171E]' : 'hover:bg-gray-700'; ?>">
        <i data-lucide="package"></i> 
        <a href="packages.php" class="w-full">Packages</a>
    </li>
    <li class="flex items-center space-x-2 p-2 rounded <?php echo basename($_SERVER['PHP_SELF']) == 'discount-coupons.php' ? 'bg-[#D2171E]' : 'hover:bg-gray-700'; ?>">
        <i data-lucide="ticket-percent"></i> 
        <a href="discount-coupons.php" class="w-full">Discount Coupons</a>
    </li>
    <li class="flex items-center space-x-2 p-2 rounded <?php echo basename($_SERVER['PHP_SELF']) == 'system-settings.php' ? 'bg-[#D2171E]' : 'hover:bg-gray-700'; ?>">
        <i data-lucide="settings"></i> 
        <a href="system-settings.php" class="w-full">Settings</a>
    </li>
    <li class="flex items-center space-x-2 p-2 hover:bg-red-600 rounded">
        <i data-lucide="log-out"></i> 
        <a href="backend/authentication/logout.php">Logout</a>
    </li>
</ul>
    </aside>
