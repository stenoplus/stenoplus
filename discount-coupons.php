<?php
// manage-coupons.php
session_start();

// Check if user is logged in and is admin
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
//     header("Location: login.php");
//     exit();
// }

// Database Connection
require 'backend/config.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_coupon'])) {
        // Add new coupon
        $title = $conn->real_escape_string($_POST['title']);
        $description = $conn->real_escape_string($_POST['description']);
        $code = $conn->real_escape_string($_POST['code']);
        $discount = intval($_POST['discount']);
        $active = isset($_POST['active']) ? 1 : 0;
        
        $query = "INSERT INTO promotions (title, description, code, discount, active) 
                  VALUES ('$title', '$description', '$code', $discount, $active)";
        $conn->query($query);
    } elseif (isset($_POST['update_coupon'])) {
        // Update existing coupon
        $id = intval($_POST['id']);
        $title = $conn->real_escape_string($_POST['title']);
        $description = $conn->real_escape_string($_POST['description']);
        $code = $conn->real_escape_string($_POST['code']);
        $discount = intval($_POST['discount']);
        $active = isset($_POST['active']) ? 1 : 0;
        
        $query = "UPDATE promotions SET 
                  title='$title', 
                  description='$description', 
                  code='$code', 
                  discount=$discount, 
                  active=$active 
                  WHERE id=$id";
        $conn->query($query);
    } elseif (isset($_POST['delete_coupon'])) {
        // Delete coupon
        $id = intval($_POST['id']);
        $query = "DELETE FROM promotions WHERE id=$id";
        $conn->query($query);
    }
}

// Handle search
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$where_clause = $search ? "WHERE (title LIKE '%$search%' OR code LIKE '%$search%' OR description LIKE '%$search%')" : '';

// Get all coupons with search filter
$coupons_query = "SELECT * FROM promotions $where_clause ORDER BY created_at DESC";
$coupons = $conn->query($coupons_query)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Discount Coupons - StenoPlus</title>
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
        }
        .sidebar-hidden { transform: translateX(-100%); }
        
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
        
        /* Brand colors */
        .brand-primary { background-color: #002147; }
        .brand-primary-text { color: #002147; }
        .brand-primary-border { border-color: #002147; }
        .brand-primary-hover:hover { background-color: #003366; }
        
        .brand-secondary { background-color: #D2171E; }
        .brand-secondary-text { color: #D2171E; }
        .brand-secondary-border { border-color: #D2171E; }
        .brand-secondary-hover:hover { background-color: #B8141A; }
        
        /* Custom styles */
        .coupon-card {
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 33, 71, 0.1);
        }
        .coupon-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 33, 71, 0.15);
        }
        .coupon-code-badge {
            background: linear-gradient(135deg, #002147 0%, #003366 100%);
            color: white;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .discount-badge {
            background: linear-gradient(135deg, #D2171E 0%, #B8141A 100%);
            color: white;
            font-weight: 600;
        }
        
        /* Mobile optimizations */
        @media (max-width: 640px) {
            .coupon-grid {
                grid-template-columns: 1fr;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            .action-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }
            .action-buttons button {
                width: 100%;
            }
        }
    </style>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#002147',
                        secondary: '#D2171E',
                    }
                }
            }
        };
    </script>
</head>
<body class="flex">
    <!-- Admin Sidebar -->
    <?php include 'sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="md:ml-64 w-full lg:p-6 md:p-0">
        <!-- Top Bar -->
        <header class="flex justify-between items-center bg-white shadow p-4 rounded-none lg:rounded-lg dark:bg-gray-800">
            <!-- Hamburger Menu (Only for Mobile) -->
            <button id="openSidebar" class="md:hidden">
                <i data-lucide="menu"></i>
            </button>

            <h2 class="text-xl font-semibold dark:text-white">Manage Coupons</h2>
            
            <div class="flex items-center space-x-4">
                <i data-lucide="bell" class="cursor-pointer dark:text-white"></i>
                <i data-lucide="moon" id="darkModeToggle" class="cursor-pointer dark:text-white"></i>
                
                <!-- Profile Dropdown -->
                <div class="relative">
                    <img src="assets/images/admin.png" alt="Profile" class="w-10 h-10 rounded-full cursor-pointer" id="profileBtn">
                    <div id="profileDropdown" class="hidden absolute right-0 bg-white shadow-lg rounded-md w-40 mt-2 dark:bg-gray-700">
                        <p class="p-2 text-sm dark:text-white"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></p>
                        <p class="p-2 text-xs text-gray-500 dark:text-gray-300">Role: Admin</p>
                        <hr class="dark:border-gray-600">
                        <li class="flex items-center space-x-2 p-2 text-sm hover:bg-gray-200 dark:hover:bg-gray-600">
                            <i data-lucide="user" class="mr-0 w-4 h-4"></i> 
                            <a href="admin_profile.php" class="dark:text-white">Admin Profile</a>
                        </li>
                        <li class="flex items-center space-x-2 p-2 hover:bg-red-600 hover:text-white rounded-b text-sm">
                            <i data-lucide="log-out" class="mr-0 w-4 h-4"></i> 
                            <a href="backend/authentication/logout.php" class="dark:text-white">Logout</a>
                        </li>
                    </div>
                </div>
            </div>
        </header>

        <!-- Discount Coupon Content -->
        <section class="mt-6 p-4 lg:p-0">
            <div class="space-y-6">
                <!-- Header with Stats -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 p-2 lg:p-0">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Discount Coupon Management</h1>
                        <p class="text-gray-600 dark:text-gray-300">Create and manage discount coupons for your platform</p>
                    </div>
                    <div class="flex items-center gap-3 bg-white dark:bg-gray-800 p-3 rounded-lg shadow w-full md:w-auto">
                        <div class="text-center px-2 md:px-4 py-2">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Coupons</p>
                            <h3 class="text-xl font-bold brand-primary-text"><?= count($coupons) ?></h3>
                        </div>
                        <div class="h-10 w-px bg-gray-200 dark:bg-gray-600"></div>
                        <div class="text-center px-2 md:px-4 py-2">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Active</p>
                            <h3 class="text-xl font-bold text-green-600 dark:text-green-400">
                                <?= array_reduce($coupons, function($carry, $item) { return $carry + ($item['active'] ? 1 : 0); }, 0) ?>
                            </h3>
                        </div>
                    </div>
                </div>

                <!-- Search and Add Coupon Section -->
                <div class="flex flex-col md:flex-row gap-4 p-2 lg:p-0">
                    <!-- Search Form -->
                    <form method="GET" class="flex-1">
                        <div class="relative w-full">
                            <input type="text" name="search" placeholder="Search discount coupons..." 
                                   value="<?= htmlspecialchars($search) ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <button type="submit" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <i data-lucide="search" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </form>
                    
                    <!-- Add New Button (Mobile) -->
                    <button onclick="document.getElementById('addCouponForm').classList.toggle('hidden')"
                            class="md:hidden px-4 py-2 bg-primary text-white rounded-lg flex items-center justify-center gap-2">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span>Add Coupon</span>
                    </button>
                </div>

                <!-- Add New Discount Coupon Form (Hidden on mobile by default) -->
                <div id="addCouponForm" class="hidden md:block bg-white p-4 md:p-6 rounded-xl shadow-lg coupon-card dark:bg-gray-800 border-l-4 brand-primary-border">
                    <h3 class="text-lg font-semibold mb-4 flex items-center gap-2 dark:text-white">
                        <i data-lucide="plus-circle" class="w-5 h-5 brand-primary-text"></i>
                        <span>Create New Discount Coupon</span>
                    </h3>
                    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 form-grid">
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
                            <input type="text" id="title" name="title" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                        <div>
                            <label for="code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Code</label>
                            <input type="text" id="code" name="code" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                   placeholder="e.g. SUMMER20">
                        </div>
                        <div>
                            <label for="discount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Discount (%)</label>
                            <div class="relative">
                                <input type="number" id="discount" name="discount" min="1" max="100" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <span class="absolute right-3 top-2 text-gray-400">%</span>
                            </div>
                        </div>
                        <div class="flex items-end gap-3 action-buttons">
                            <div class="flex items-center h-full">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input id="active" name="active" type="checkbox" checked class="sr-only peer">
                                    <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-secondary"></div>
                                    <span class="ms-3 text-sm font-medium text-gray-700 dark:text-gray-300">Active</span>
                                </label>
                            </div>
                            <button type="submit" name="add_coupon" 
                                    class="ml-auto px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition-colors flex items-center gap-2">
                                <i data-lucide="tag" class="w-4 h-4"></i>
                                Create Coupon
                            </button>
                        </div>
                        <div class="md:col-span-2 lg:col-span-4">
                            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                            <textarea id="description" name="description" rows="2" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                      placeholder="Enter a description for this discount coupon..."></textarea>
                        </div>
                    </form>
                </div>

                <!-- Discount Coupons List -->
                <div class="bg-white p-4 md:p-6 rounded-xl shadow-lg dark:bg-gray-800">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 md:mb-6 gap-4">
                        <div>
                            <h3 class="text-lg font-semibold dark:text-white flex items-center gap-2">
                                <i data-lucide="tags" class="w-5 h-5 brand-primary-text"></i>
                                <span>Current Discount Coupons</span>
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                <?= count($coupons) ?> coupons found • 
                                <?= array_reduce($coupons, function($carry, $item) { return $carry + ($item['active'] ? 1 : 0); }, 0) ?> active
                            </p>
                        </div>
                        <?php if ($search): ?>
                            <a href="manage-coupons.php" class="text-sm text-primary hover:underline dark:text-primary-300 flex items-center gap-1">
                                <i data-lucide="x" class="w-4 h-4"></i>
                                Clear search
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($coupons)): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 coupon-grid">
                            <?php foreach ($coupons as $coupon): 
                                $created_date = date('M d, Y', strtotime($coupon['created_at']));
                                $is_active = $coupon['active'] == 1;
                                $days_old = floor((time() - strtotime($coupon['created_at'])) / (60 * 60 * 24));
                            ?>
                            <div class="border border-gray-200 rounded-xl p-4 md:p-5 dark:border-gray-700 coupon-card <?= $is_active ? 'bg-white dark:bg-gray-800' : 'bg-gray-50 dark:bg-gray-700/50' ?>">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <span class="inline-block px-2 py-1 rounded-full text-xs font-medium mb-2 <?= $is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' ?>">
                                            <?= $is_active ? 'Active' : 'Inactive' ?>
                                        </span>
                                        <h4 class="font-semibold text-lg dark:text-white"><?= htmlspecialchars($coupon['title']) ?></h4>
                                    </div>
                                    <div class="flex gap-2">
                                        <!-- Edit Button -->
                                        <button onclick="openEditModal(
                                            <?= $coupon['id'] ?>, 
                                            '<?= htmlspecialchars($coupon['title'], ENT_QUOTES) ?>', 
                                            '<?= htmlspecialchars($coupon['description'], ENT_QUOTES) ?>', 
                                            '<?= htmlspecialchars($coupon['code'], ENT_QUOTES) ?>', 
                                            <?= $coupon['discount'] ?>, 
                                            <?= $coupon['active'] ?>
                                        )" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300">
                                            <i data-lucide="edit" class="w-4 h-4"></i>
                                        </button>
                                        
                                        <!-- Delete Button -->
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this discount coupon?');">
                                            <input type="hidden" name="id" value="<?= $coupon['id'] ?>">
                                            <button type="submit" name="delete_coupon" class="p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/30 text-red-600 dark:text-red-400">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 dark:text-gray-300 text-sm mb-4"><?= htmlspecialchars($coupon['description']) ?></p>
                                
                                <div class="flex items-center justify-between mt-4 flex-wrap gap-2">
                                    <div>
                                        <span class="inline-block px-2 py-1 rounded-md text-xs md:text-sm font-semibold coupon-code-badge">
                                            <?= htmlspecialchars($coupon['code']) ?>
                                        </span>
                                    </div>
                                    <div>
                                        <span class="inline-block px-2 py-1 rounded-md text-xs md:text-sm font-semibold discount-badge">
                                            <?= $coupon['discount'] ?>% OFF
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="flex justify-between items-center mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Created <?= $created_date ?></span>
                                    <?php if ($days_old < 7): ?>
                                        <span class="text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded-full dark:bg-blue-900/30 dark:text-blue-300">New</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12 border-2 border-dashed border-gray-300 rounded-xl dark:border-gray-700">
                            <i data-lucide="tags" class="w-10 h-10 mx-auto text-gray-400 mb-3"></i>
                            <h4 class="text-lg font-medium text-gray-700 dark:text-gray-300">
                                <?= $search ? 'No matching coupons found' : 'No discount coupons found' ?>
                            </h4>
                            <p class="text-gray-500 dark:text-gray-400 mt-1">
                                <?= $search ? 'Try a different search term' : 'Create your first discount coupon to get started' ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Edit Modal -->
        <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
            <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md mx-4 dark:bg-gray-800">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold dark:text-white flex items-center gap-2">
                        <i data-lucide="edit" class="w-5 h-5 brand-primary-text"></i>
                        <span>Edit Discount Coupon</span>
                    </h3>
                    <button onclick="closeEditModal()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                
                <form id="editForm" method="POST">
                    <input type="hidden" name="id" id="editId">
                    <div class="space-y-4">
                        <div>
                            <label for="editTitle" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
                            <input type="text" id="editTitle" name="title" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                        <div>
                            <label for="editDescription" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                            <textarea id="editDescription" name="description" rows="3" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"></textarea>
                        </div>
                        <div>
                            <label for="editCode" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Code</label>
                            <input type="text" id="editCode" name="code" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        </div>
                        <div>
                            <label for="editDiscount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Discount (%)</label>
                            <div class="relative">
                                <input type="number" id="editDiscount" name="discount" min="1" max="100" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                <span class="absolute right-3 top-2 text-gray-400">%</span>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <label class="inline-flex items-center cursor-pointer">
                                <input id="editActive" name="active" type="checkbox" class="sr-only peer">
                                <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-secondary"></div>
                                <span class="ms-3 text-sm font-medium text-gray-700 dark:text-gray-300">Active</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end space-x-3 action-buttons">
                        <button type="button" onclick="closeEditModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:hover:bg-gray-600">
                            Cancel
                        </button>
                        <button type="submit" name="update_coupon" 
                                class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 flex items-center gap-2">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        lucide.createIcons();

        // Dark Mode Toggle
        function toggleDarkMode() {
            const isDark = document.documentElement.classList.toggle("dark");
            localStorage.setItem("darkMode", isDark ? "enabled" : "disabled");
        }
        document.getElementById("darkModeToggle").addEventListener("click", toggleDarkMode);
        if (localStorage.getItem("darkMode") === "enabled") {
            document.documentElement.classList.add("dark");
        }

        // Profile Dropdown Toggle
        document.getElementById("profileBtn").addEventListener("click", function () {
            document.getElementById("profileDropdown").classList.toggle("hidden");
        });

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
        document.addEventListener("click", function (event) {
            if (!sidebar.contains(event.target) && !openSidebar.contains(event.target) && !closeSidebar.contains(event.target)) {
                sidebar.classList.add("sidebar-hidden");
            }
        });

        // Edit Modal Functions
        function openEditModal(id, title, description, code, discount, active) {
            document.getElementById('editId').value = id;
            document.getElementById('editTitle').value = title;
            document.getElementById('editDescription').value = description;
            document.getElementById('editCode').value = code;
            document.getElementById('editDiscount').value = discount;
            document.getElementById('editActive').checked = active == 1;
            
            document.getElementById('editModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // Prevent scrolling when modal is open
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
            document.body.style.overflow = ''; // Re-enable scrolling
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeEditModal();
            }
        }

        // Toggle add coupon form on mobile
        document.addEventListener('DOMContentLoaded', function() {
            // Show add form if coming from "Add Coupon" button
            if (window.location.hash === '#add-coupon') {
                document.getElementById('addCouponForm').classList.remove('hidden');
            }
            
            // Auto-focus search input if there's a search term
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput && searchInput.value) {
                searchInput.focus();
            }
        });
    </script>
</body>
</html>