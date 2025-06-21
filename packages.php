<?php
// packages.php
session_start();

// Check if user is logged in and is admin
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
//     header("Location: login.php");
//     exit();
// }

// Database Connection
require 'backend/config.php';

// Create packages table if not exists
$conn->query("
    CREATE TABLE IF NOT EXISTS `packages` (
        `package_id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `subtitle` VARCHAR(255) DEFAULT NULL,
        `short_description` TEXT DEFAULT NULL,
        `long_description` TEXT DEFAULT NULL,
        `key_features` TEXT DEFAULT NULL, -- JSON encoded array
        `regular_price` DECIMAL(10,2) DEFAULT 0,
        `offer_price` DECIMAL(10,2) DEFAULT 0,
        `validity_period` INT DEFAULT 1,
        `validity_unit` ENUM('month','year') DEFAULT 'month',
        `is_free` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

// Create package_tests table for many-to-many relationship
$conn->query("
    CREATE TABLE IF NOT EXISTS `package_tests` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `package_id` INT NOT NULL,
        `test_id` INT NOT NULL,
        `is_free` TINYINT(1) DEFAULT 0,
        `sort_order` INT DEFAULT 0,
        FOREIGN KEY (`package_id`) REFERENCES `packages`(`package_id`) ON DELETE CASCADE,
        FOREIGN KEY (`test_id`) REFERENCES `tests`(`test_id`) ON DELETE CASCADE,
        UNIQUE KEY `package_test_unique` (`package_id`,`test_id`)
    )
");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_package'])) {
        // Add new package
        $title = $conn->real_escape_string($_POST['title']);
        $subtitle = $conn->real_escape_string($_POST['subtitle']);
        $short_desc = $conn->real_escape_string($_POST['short_description']);
        $long_desc = $conn->real_escape_string($_POST['long_description']);
        $key_features = json_encode(array_filter(array_map('trim', explode("\n", $_POST['key_features']))));
        $regular_price = floatval($_POST['regular_price']);
        $offer_price = floatval($_POST['offer_price']);
        $validity = intval($_POST['validity_period']);
        $validity_unit = in_array($_POST['validity_unit'], ['month','year']) ? $_POST['validity_unit'] : 'month';
        $is_free = isset($_POST['is_free']) ? 1 : 0;
        
        $query = "INSERT INTO packages (title, subtitle, short_description, long_description, key_features, 
                  regular_price, offer_price, validity_period, validity_unit, is_free)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssssddiss", $title, $subtitle, $short_desc, $long_desc, $key_features, 
                         $regular_price, $offer_price, $validity, $validity_unit, $is_free);
        $stmt->execute();
        $package_id = $stmt->insert_id;
        $stmt->close();
        
        // Handle test imports if any
if (isset($_POST['tests']) && is_array($_POST['tests'])) {
    // Get all valid test IDs from database
    $valid_test_ids = [];
    $result = $conn->query("SELECT test_id FROM tests");
    while ($row = $result->fetch_assoc()) {
        $valid_test_ids[] = $row['test_id'];
    }

    foreach ($_POST['tests'] as $test_id) {
        $test_id = (int)$test_id;
        
        // Only proceed if test exists in database
        if (!in_array($test_id, $valid_test_ids)) {
            error_log("Invalid test_id: $test_id - skipping");
            continue;
        }

        $is_free_test = ($is_free || (isset($_POST['default_free_test']) && $_POST['default_free_test'] == $test_id)) ? 1 : 0;
        
        // Use prepared statement
        $stmt = $conn->prepare("INSERT INTO package_tests (package_id, test_id, is_free) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $package_id, $test_id, $is_free_test);
        
        if (!$stmt->execute()) {
            error_log("Error adding test to package: " . $stmt->error);
        }
        $stmt->close();
    }
}
    } elseif (isset($_POST['update_package'])) {
        // Update existing package
        $id = intval($_POST['id']);
        $title = $conn->real_escape_string($_POST['title']);
        $subtitle = $conn->real_escape_string($_POST['subtitle']);
        $short_desc = $conn->real_escape_string($_POST['short_description']);
        $long_desc = $conn->real_escape_string($_POST['long_description']);
        $key_features = json_encode(array_filter(array_map('trim', explode("\n", $_POST['key_features']))));
        $regular_price = floatval($_POST['regular_price']);
        $offer_price = floatval($_POST['offer_price']);
        $validity = intval($_POST['validity_period']);
        $validity_unit = in_array($_POST['validity_unit'], ['month','year']) ? $_POST['validity_unit'] : 'month';
        $is_free = isset($_POST['is_free']) ? 1 : 0;
        
        $query = "UPDATE packages SET 
                  title=?, subtitle=?, short_description=?, long_description=?, key_features=?,
                  regular_price=?, offer_price=?, validity_period=?, validity_unit=?, is_free=?
                  WHERE package_id=?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssssddissi", $title, $subtitle, $short_desc, $long_desc, $key_features,
                         $regular_price, $offer_price, $validity, $validity_unit, $is_free, $id);
        $stmt->execute();
        $stmt->close();
        
        // Update all tests to free if package is free
        if ($is_free) {
            $conn->query("UPDATE package_tests SET is_free=1 WHERE package_id=$id");
        }
    } elseif (isset($_POST['delete_package'])) {
        // Delete package (cascade will delete package_tests)
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM packages WHERE package_id=$id");
    } elseif (isset($_POST['update_test_status'])) {
        // Update individual test status
        $package_id = intval($_POST['package_id']);
        $test_id = intval($_POST['test_id']);
        $is_free = isset($_POST['is_free']) ? 1 : 0;
        $conn->query("UPDATE package_tests SET is_free=$is_free 
                      WHERE package_id=$package_id AND test_id=$test_id");
    } elseif (isset($_POST['add_tests_to_package'])) {
        // Add tests to existing package
        $package_id = intval($_POST['package_id']);
        $is_package_free = $conn->query("SELECT is_free FROM packages WHERE package_id=$package_id")->fetch_assoc()['is_free'];
        
        foreach ($_POST['tests'] as $test_id) {
            // Check if test already exists in package
            $exists = $conn->query("SELECT 1 FROM package_tests WHERE package_id=$package_id AND test_id=$test_id")->num_rows;
            if (!$exists) {
                $is_free_test = $is_package_free ? 1 : 0;
                $conn->query("INSERT INTO package_tests (package_id, test_id, is_free) 
                              VALUES ($package_id, $test_id, $is_free_test)");
            }
        }
    } elseif (isset($_POST['remove_test_from_package'])) {
        // Remove test from package
        $package_id = intval($_POST['package_id']);
        $test_id = intval($_POST['test_id']);
        $conn->query("DELETE FROM package_tests WHERE package_id=$package_id AND test_id=$test_id");
    }
}

// Get all packages
$packages_query = "SELECT * FROM packages ORDER BY created_at DESC";
$packages = $conn->query($packages_query)->fetch_all(MYSQLI_ASSOC);

// Get all tests for dropdowns
$tests_query = "SELECT t.*, e.exam_name, c.category_name 
                FROM tests t
                JOIN exams e ON t.exam_id = e.exam_id
                JOIN categories c ON t.category_id = c.category_id
                ORDER BY t.test_name";
$all_tests = $conn->query($tests_query)->fetch_all(MYSQLI_ASSOC);

// Get all exams for category filtering
$exams_query = "SELECT * FROM exams ORDER BY exam_name";
$exams = $conn->query($exams_query)->fetch_all(MYSQLI_ASSOC);

// Get all categories
$categories_query = "SELECT * FROM categories WHERE status='active' ORDER BY category_name";
$categories = $conn->query($categories_query)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Packages - StenoPlus</title>
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
        .package-card {
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 33, 71, 0.1);
        }
        .package-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 33, 71, 0.15);
        }
        .price-badge {
            background: linear-gradient(135deg, #002147 0%, #003366 100%);
            color: white;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .free-badge {
            background: linear-gradient(135deg, #D2171E 0%, #B8141A 100%);
            color: white;
            font-weight: 600;
        }
        
        /* Mobile optimizations */
        @media (max-width: 640px) {
            .package-grid {
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
            .test-list-item {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        
        /* Toggle switch */
        .toggle-checkbox:checked {
            right: 0;
            border-color: #D2171E;
            background-color: #D2171E;
        }
        .toggle-checkbox:checked + .toggle-label {
            background-color: rgba(210, 23, 30, 0.2);
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
            <!-- [Header content same as before] -->
            <h2 class="text-xl font-semibold dark:text-white">Package Management</h2>
            <!-- [Rest of header remains the same] -->
        </header>

        <!-- Package Content -->
        <section class="mt-6 p-4 lg:p-0">
            <div class="space-y-6">
                <!-- Header with Stats -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 p-2 lg:p-0">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Test Packages</h1>
                        <p class="text-gray-600 dark:text-gray-300">Create and manage test packages with flexible pricing</p>
                    </div>
                    <div class="flex items-center gap-3 bg-white dark:bg-gray-800 p-3 rounded-lg shadow w-full md:w-auto">
                        <div class="text-center px-2 md:px-4 py-2">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Packages</p>
                            <h3 class="text-xl font-bold brand-primary-text"><?= count($packages) ?></h3>
                        </div>
                        <div class="h-10 w-px bg-gray-200 dark:bg-gray-600"></div>
                        <div class="text-center px-2 md:px-4 py-2">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Free</p>
                            <h3 class="text-xl font-bold text-green-600 dark:text-green-400">
                                <?= array_reduce($packages, function($carry, $item) { return $carry + ($item['is_free'] ? 1 : 0); }, 0) ?>
                            </h3>
                        </div>
                    </div>
                </div>

                <!-- Add New Package Button -->
                <button onclick="openAddModal()"
                        class="w-full md:w-auto px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition-colors flex items-center justify-center gap-2">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Create New Package</span>
                </button>

                <!-- Packages List -->
                <div class="bg-white p-4 md:p-6 rounded-xl shadow-lg dark:bg-gray-800">
                    <?php if (!empty($packages)): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 package-grid">
                            <?php foreach ($packages as $package): 
                                $key_features = json_decode($package['key_features'], true) ?: [];
                                $tests_count = $conn->query("SELECT COUNT(*) FROM package_tests WHERE package_id={$package['package_id']}")->fetch_row()[0];
                            ?>
                            <div class="border border-gray-200 rounded-xl p-4 md:p-5 dark:border-gray-700 package-card <?= $package['is_free'] ? 'bg-white dark:bg-gray-800' : 'bg-gray-50 dark:bg-gray-700/50' ?>">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <span class="inline-block px-2 py-1 rounded-full text-xs font-medium mb-2 <?= $package['is_free'] ? 'free-badge' : 'price-badge' ?>">
                                            <?= $package['is_free'] ? 'FREE' : '₹'.number_format($package['offer_price'], 0) ?>
                                        </span>
                                        <h4 class="font-semibold text-lg dark:text-white"><?= htmlspecialchars($package['title']) ?></h4>
                                        <?php if ($package['subtitle']): ?>
                                            <p class="text-gray-600 dark:text-gray-300 text-sm"><?= htmlspecialchars($package['subtitle']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex gap-2">
                                        <!-- Edit Button -->
                                        <button onclick="openEditModal(
                                            <?= $package['package_id'] ?>, 
                                            '<?= htmlspecialchars($package['title'], ENT_QUOTES) ?>', 
                                            '<?= htmlspecialchars($package['subtitle'], ENT_QUOTES) ?>', 
                                            '<?= htmlspecialchars($package['short_description'], ENT_QUOTES) ?>', 
                                            '<?= htmlspecialchars($package['long_description'], ENT_QUOTES) ?>', 
                                            `<?= str_replace("\n", "\\n", htmlspecialchars(implode("\n", $key_features))) ?>`, 
                                            <?= $package['regular_price'] ?>, 
                                            <?= $package['offer_price'] ?>, 
                                            <?= $package['validity_period'] ?>, 
                                            '<?= $package['validity_unit'] ?>', 
                                            <?= $package['is_free'] ?>
                                        )" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300">
                                            <i data-lucide="edit" class="w-4 h-4"></i>
                                        </button>
                                        
                                        <!-- Delete Button -->
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this package?');">
                                            <input type="hidden" name="id" value="<?= $package['package_id'] ?>">
                                            <button type="submit" name="delete_package" class="p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/30 text-red-600 dark:text-red-400">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                
                                <p class="text-gray-600 dark:text-gray-300 text-sm mb-3"><?= htmlspecialchars($package['short_description']) ?></p>
                                
                                <?php if (!empty($key_features)): ?>
                                    <ul class="text-sm text-gray-600 dark:text-gray-300 mb-4 space-y-1">
                                        <?php foreach ($key_features as $feature): ?>
                                            <li class="flex items-start gap-2">
                                                <i data-lucide="check" class="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0"></i>
                                                <span><?= htmlspecialchars($feature) ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                
                                <div class="flex justify-between items-center mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <div>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            <?= $tests_count ?> tests • 
                                            Valid for <?= $package['validity_period'] ?> <?= $package['validity_unit'] ?><?= $package['validity_period'] > 1 ? 's' : '' ?>
                                        </span>
                                    </div>
                                    <a href="#" onclick="event.preventDefault(); openManageTestsModal(<?= $package['package_id'] ?>);" 
                                   class="text-xs text-primary hover:underline dark:text-primary-300">
                                    Manage Tests
                                </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12 border-2 border-dashed border-gray-300 rounded-xl dark:border-gray-700">
                            <i data-lucide="package" class="w-10 h-10 mx-auto text-gray-400 mb-3"></i>
                            <h4 class="text-lg font-medium text-gray-700 dark:text-gray-300">No packages found</h4>
                            <p class="text-gray-500 dark:text-gray-400 mt-1">Create your first package to get started</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Add/Edit Package Modal -->
        <div id="packageModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
            <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-3xl mx-4 max-h-[90vh] overflow-y-auto dark:bg-gray-800">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold dark:text-white flex items-center gap-2">
                        <i data-lucide="package" class="w-5 h-5 brand-primary-text"></i>
                        <span id="modalTitle">Create New Package</span>
                    </h3>
                    <button onclick="closePackageModal()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                
                <form id="packageForm" method="POST">
                    <input type="hidden" name="id" id="packageId">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title*</label>
                                <input type="text" id="title" name="title" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                            <div>
                                <label for="subtitle" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Subtitle</label>
                                <input type="text" id="subtitle" name="subtitle" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                        </div>
                        
                        <div>
                            <label for="short_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Short Description*</label>
                            <textarea id="short_description" name="short_description" rows="2" required 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"></textarea>
                        </div>
                        
                        <div>
                            <label for="long_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Long Description</label>
                            <textarea id="long_description" name="long_description" rows="3" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"></textarea>
                        </div>
                        
                        <div>
                            <label for="key_features" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Key Features (one per line)</label>
                            <textarea id="key_features" name="key_features" rows="4" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                      placeholder="Feature 1&#10;Feature 2&#10;Feature 3"></textarea>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="regular_price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Regular Price (₹)*</label>
                                <input type="number" id="regular_price" name="regular_price" min="0" step="0.01" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                            <div>
                                <label for="offer_price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Offer Price (₹)*</label>
                                <input type="number" id="offer_price" name="offer_price" min="0" step="0.01" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            </div>
                            <div class="flex items-end gap-2">
                                <div class="flex-1">
                                    <label for="validity_period" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Validity Period*</label>
                                    <div class="flex">
                                        <input type="number" id="validity_period" name="validity_period" min="1" required 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-l-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        <select name="validity_unit" class="border border-gray-300 rounded-r-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            <option value="month">Month(s)</option>
                                            <option value="year">Year(s)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center">
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="is_free" name="is_free" class="sr-only peer">
                                <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-secondary"></div>
                                <span class="ms-3 text-sm font-medium text-gray-700 dark:text-gray-300">Free Package</span>
                            </label>
                        </div>
                        
                        <!-- Test Import Section -->
                        <div id="testImportSection" class="border border-gray-200 rounded-lg p-4 dark:border-gray-700">
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Add Tests to Package</h4>
                            
                            <!-- Test Import Options -->
                            <div class="flex flex-col md:flex-row gap-3 mb-4">
                                <button type="button" onclick="showImportOption('all')" 
                                        class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm dark:bg-gray-700 dark:hover:bg-gray-600 flex items-center gap-2">
                                    <i data-lucide="list" class="w-4 h-4"></i>
                                    Import All Tests
                                </button>
                                <button type="button" onclick="showImportOption('category')" 
                                        class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm dark:bg-gray-700 dark:hover:bg-gray-600 flex items-center gap-2">
                                    <i data-lucide="folder" class="w-4 h-4"></i>
                                    Import by Category
                                </button>
                                <button type="button" onclick="showImportOption('single')" 
                                        class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm dark:bg-gray-700 dark:hover:bg-gray-600 flex items-center gap-2">
                                    <i data-lucide="plus" class="w-4 h-4"></i>
                                    Add Single Test
                                </button>
                            </div>
                            
                            <!-- Test Selection Areas -->
                            <div id="allTestsOption" class="hidden mb-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">All available tests</span>
                                    <button type="button" onclick="selectAllTests()" class="text-xs text-primary hover:underline dark:text-primary-300">
                                        Select All
                                    </button>
                                </div>
                                <div class="max-h-60 overflow-y-auto border border-gray-200 rounded-lg p-2 dark:border-gray-700">
                                    <?php foreach ($all_tests as $test): ?>
                                    <div class="flex items-center gap-2 p-2 hover:bg-gray-50 dark:hover:bg-gray-700 rounded">
                                        <input type="checkbox" name="tests[]" value="<?= $test['test_id'] ?>" 
                                               class="rounded border-gray-300 text-primary focus:ring-primary dark:border-gray-600 dark:bg-gray-700">
                                        <span class="text-sm dark:text-gray-300">
                                            <?= htmlspecialchars($test['test_name']) ?> (<?= htmlspecialchars($test['exam_name']) ?>)
                                        </span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div id="categoryTestsOption" class="hidden mb-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Select Exam</label>
                                        <select id="examFilter" onchange="filterCategories()" 
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            <option value="">All Exams</option>
                                            <?php foreach ($exams as $exam): ?>
                                            <option value="<?= $exam['exam_id'] ?>"><?= htmlspecialchars($exam['exam_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Select Category</label>
                                        <select id="categoryFilter" onchange="filterTestsByCategory()" 
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            <option value="">All Categories</option>
                                            <?php foreach ($categories as $category): ?>
                                            <option value="<?= $category['category_id'] ?>" data-exam="<?= $category['exam_id'] ?? '' ?>">
                                                <?= htmlspecialchars($category['category_name']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="mt-3 max-h-60 overflow-y-auto border border-gray-200 rounded-lg p-2 dark:border-gray-700">
                                    <div id="categoryTestsList" class="space-y-2">
                                        <!-- Tests will be loaded here by JavaScript -->
                                        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">Select an exam and category to view tests</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="singleTestOption" class="hidden">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Select Test</label>
                                <select name="tests[]" class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    <option value="">Select a test</option>
                                    <?php foreach ($all_tests as $test): ?>
                                    <option value="<?= $test['test_id'] ?>">
                                        <?= htmlspecialchars($test['test_name']) ?> (<?= htmlspecialchars($test['exam_name']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div id="selectedTestsInfo" class="hidden mt-3 p-3 bg-gray-50 rounded-lg dark:bg-gray-700">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium dark:text-gray-300">Selected Tests:</span>
                                    <span id="selectedTestsCount" class="text-xs bg-primary text-white px-2 py-1 rounded-full">0</span>
                                </div>
                                <div id="selectedTestsList" class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                                    <!-- Selected tests will be listed here -->
                                </div>
                                <input type="hidden" id="default_free_test" name="default_free_test" value="">
                            </div>
                        </div>
                        
                        <div class="mt-6 flex justify-end space-x-3 action-buttons">
                            <button type="button" onclick="closePackageModal()" 
                                    class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:hover:bg-gray-600">
                                Cancel
                            </button>
                            <button type="submit" name="add_package" id="submitButton"
                                    class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 flex items-center gap-2">
                                <i data-lucide="save" class="w-4 h-4"></i>
                                Create Package
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Manage Tests Modal -->
        <div id="manageTestsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
            <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-3xl mx-4 max-h-[90vh] overflow-y-auto dark:bg-gray-800">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold dark:text-white flex items-center gap-2">
                        <i data-lucide="list" class="w-5 h-5 brand-primary-text"></i>
                        <span id="manageTestsTitle">Manage Package Tests</span>
                    </h3>
                    <button onclick="closeManageTestsModal()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                
                <div id="packageTestsInfo" class="mb-4 p-3 bg-gray-50 rounded-lg dark:bg-gray-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="font-medium dark:text-gray-300" id="packageNameDisplay"></h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400" id="packagePriceDisplay"></p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium dark:text-gray-300">Status:</span>
                            <span id="packageStatusBadge" class="px-2 py-1 rounded-full text-xs"></span>
                        </div>
                    </div>
                </div>
                
                <!-- Add More Tests Section -->
                <div class="border border-gray-200 rounded-lg p-4 mb-6 dark:border-gray-700">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Add More Tests</h4>
                    
                    <div class="flex flex-col md:flex-row gap-3 mb-4">
                        <button type="button" onclick="showAddTestsOption('all')" 
                                class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm dark:bg-gray-700 dark:hover:bg-gray-600 flex items-center gap-2">
                            <i data-lucide="list" class="w-4 h-4"></i>
                            Import All Tests
                        </button>
                        <button type="button" onclick="showAddTestsOption('category')" 
                                class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm dark:bg-gray-700 dark:hover:bg-gray-600 flex items-center gap-2">
                            <i data-lucide="folder" class="w-4 h-4"></i>
                            Import by Category
                        </button>
                        <button type="button" onclick="showAddTestsOption('single')" 
                                class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm dark:bg-gray-700 dark:hover:bg-gray-600 flex items-center gap-2">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            Add Single Test
                        </button>
                    </div>
                    
                    <div id="addAllTestsOption" class="hidden mb-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm text-gray-600 dark:text-gray-400">All available tests</span>
                            <button type="button" onclick="selectAllAvailableTests()" class="text-xs text-primary hover:underline dark:text-primary-300">
                                Select All
                            </button>
                        </div>
                        <div class="max-h-60 overflow-y-auto border border-gray-200 rounded-lg p-2 dark:border-gray-700">
                            <div id="allAvailableTestsList">
                                <!-- Tests will be loaded here by JavaScript -->
                            </div>
                        </div>
                    </div>
                    
                    <div id="addCategoryTestsOption" class="hidden mb-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Select Exam</label>
                                <select id="addExamFilter" onchange="filterAddCategories()" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    <option value="">All Exams</option>
                                    <?php foreach ($exams as $exam): ?>
                                    <option value="<?= $exam['exam_id'] ?>"><?= htmlspecialchars($exam['exam_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Select Category</label>
                                <select id="addCategoryFilter" onchange="filterAddTestsByCategory()" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['category_id'] ?>" data-exam="<?= $category['exam_id'] ?? '' ?>">
                                        <?= htmlspecialchars($category['category_name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mt-3 max-h-60 overflow-y-auto border border-gray-200 rounded-lg p-2 dark:border-gray-700">
                            <div id="addCategoryTestsList" class="space-y-2">
                                <!-- Tests will be loaded here by JavaScript -->
                                <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">Select an exam and category to view tests</p>
                            </div>
                        </div>
                    </div>
                    
                    <div id="addSingleTestOption" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Select Test</label>
                        <select id="singleTestSelect" class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                            <option value="">Select a test</option>
                            <?php foreach ($all_tests as $test): ?>
                            <option value="<?= $test['test_id'] ?>">
                                <?= htmlspecialchars($test['test_name']) ?> (<?= htmlspecialchars($test['exam_name']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mt-3 flex justify-end">
                        <button type="button" onclick="addSelectedTests()" 
                                class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 flex items-center gap-2">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            Add Selected Tests
                        </button>
                    </div>
                </div>
                
                <!-- Current Package Tests -->
                <div>
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Current Package Tests</h4>
                    <div id="currentTestsList" class="space-y-2">
                        <!-- Tests will be loaded here by JavaScript -->
                        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">Loading tests...</p>
                    </div>
                </div>
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

        // Package Modal Functions
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Create New Package';
            document.getElementById('submitButton').name = 'add_package';
            document.getElementById('submitButton').innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Create Package';
            document.getElementById('packageForm').reset();
            document.getElementById('packageId').value = '';
            document.getElementById('testImportSection').classList.remove('hidden');
            document.getElementById('packageModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function openEditModal(id, title, subtitle, short_desc, long_desc, key_features, regular_price, offer_price, validity, unit, is_free) {
            document.getElementById('modalTitle').textContent = 'Edit Package';
            document.getElementById('submitButton').name = 'update_package';
            document.getElementById('submitButton').innerHTML = '<i data-lucide="save" class="w-4 h-4"></i> Update Package';
            document.getElementById('packageId').value = id;
            document.getElementById('title').value = title;
            document.getElementById('subtitle').value = subtitle;
            document.getElementById('short_description').value = short_desc;
            document.getElementById('long_description').value = long_desc;
            document.getElementById('key_features').value = key_features;
            document.getElementById('regular_price').value = regular_price;
            document.getElementById('offer_price').value = offer_price;
            document.getElementById('validity_period').value = validity;
            document.querySelector('select[name="validity_unit"]').value = unit;
            document.getElementById('is_free').checked = is_free;
            document.getElementById('testImportSection').classList.add('hidden');
            document.getElementById('packageModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closePackageModal() {
            document.getElementById('packageModal').classList.add('hidden');
            document.body.style.overflow = '';
        }

        // Test Import Options
        function showImportOption(option) {
            document.getElementById('allTestsOption').classList.add('hidden');
            document.getElementById('categoryTestsOption').classList.add('hidden');
            document.getElementById('singleTestOption').classList.add('hidden');
            
            document.getElementById(option + 'TestsOption').classList.remove('hidden');
            
            if (option === 'category') {
                filterCategories();
            }
        }

        function selectAllTests() {
            const checkboxes = document.querySelectorAll('#allTestsOption input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            updateSelectedTestsInfo();
        }

        function filterCategories() {
            const examId = document.getElementById('examFilter').value;
            const categoryOptions = document.querySelectorAll('#categoryFilter option');
            
            categoryOptions.forEach(option => {
                if (option.value === '') return;
                if (!examId || option.dataset.exam === examId) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
            
            // Reset category selection
            document.getElementById('categoryFilter').value = '';
            document.getElementById('categoryTestsList').innerHTML = '<p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">Select a category to view tests</p>';
        }

        function filterTestsByCategory() {
            const categoryId = document.getElementById('categoryFilter').value;
            if (!categoryId) {
                document.getElementById('categoryTestsList').innerHTML = '<p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">Select a category to view tests</p>';
                return;
            }
            
            // In a real app, you would fetch this from the server via AJAX
            const tests = <?= json_encode($all_tests) ?>;
            const filteredTests = tests.filter(test => test.category_id == categoryId);
            
            let html = '';
            if (filteredTests.length) {
                filteredTests.forEach(test => {
                    html += `
                    <div class="flex items-center gap-2 p-2 hover:bg-gray-50 dark:hover:bg-gray-700 rounded">
                        <input type="checkbox" name="tests[]" value="${test.test_id}" 
                               class="rounded border-gray-300 text-primary focus:ring-primary dark:border-gray-600 dark:bg-gray-700"
                               onchange="updateSelectedTestsInfo()">
                        <span class="text-sm dark:text-gray-300">
                            ${escapeHtml(test.test_name)} (${escapeHtml(test.exam_name)})
                        </span>
                    </div>
                    `;
                });
            } else {
                html = '<p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No tests found in this category</p>';
            }
            
            document.getElementById('categoryTestsList').innerHTML = html;
        }

        function updateSelectedTestsInfo() {
            const checkboxes = document.querySelectorAll('input[name="tests[]"]:checked');
            const count = checkboxes.length;
            document.getElementById('selectedTestsCount').textContent = count;
            
            let html = '';
            if (count > 0) {
                html = '<ul class="space-y-1">';
                checkboxes.forEach(checkbox => {
                    const testName = checkbox.nextElementSibling.textContent.trim();
                    html += `<li class="flex items-center gap-2">
                        <span>${testName}</span>
                        ${count > 1 ? `<button type="button" onclick="setAsDefaultFreeTest(${checkbox.value})" class="text-xs text-primary hover:underline dark:text-primary-300">
                            Make Free
                        </button>` : ''}
                    </li>`;
                });
                html += '</ul>';
                
                if (count === 1) {
                    document.getElementById('default_free_test').value = checkboxes[0].value;
                }
            }
            
            document.getElementById('selectedTestsList').innerHTML = html;
            document.getElementById('selectedTestsInfo').classList.toggle('hidden', count === 0);
        }

        function setAsDefaultFreeTest(testId) {
            document.getElementById('default_free_test').value = testId;
            alert('This test will be free when the package is paid. You can change individual test pricing later.');
        }

        // Manage Tests Modal Functions
        let currentPackageId = null;

        function openManageTestsModal(packageId) {
            currentPackageId = packageId;
            fetchPackageTests(packageId);
            document.getElementById('manageTestsModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeManageTestsModal() {
            document.getElementById('manageTestsModal').classList.add('hidden');
            document.body.style.overflow = '';
            currentPackageId = null;
        }

        function fetchPackageTests(packageId) {
            // Fetch package info
            fetch(`backend/get_package.php?id=${packageId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('packageNameDisplay').textContent = data.title;
                    document.getElementById('packagePriceDisplay').textContent = 
                        data.is_free ? 'FREE' : `₹${data.offer_price} (Regular: ₹${data.regular_price})`;
                    
                    const statusBadge = document.getElementById('packageStatusBadge');
                    statusBadge.textContent = data.is_free ? 'FREE' : 'PAID';
                    statusBadge.className = data.is_free ? 
                        'px-2 py-1 rounded-full text-xs bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' :
                        'px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
                });
            
            // Fetch package tests
            fetch(`backend/get_package_tests.php?package_id=${packageId}`)
                .then(response => response.json())
                .then(tests => {
                    let html = '';
                    if (tests.length) {
                        tests.forEach((test, index) => {
                            html += `
                            <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg dark:border-gray-700 test-list-item">
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-medium dark:text-gray-300">${index + 1}.</span>
                                    <div>
                                        <h5 class="text-sm font-medium dark:text-gray-300">${escapeHtml(test.test_name)}</h5>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">${escapeHtml(test.exam_name)} • ${escapeHtml(test.category_name)}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <form method="POST" class="flex items-center">
                                        <input type="hidden" name="package_id" value="${packageId}">
                                        <input type="hidden" name="test_id" value="${test.test_id}">
                                        <label class="inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="is_free" ${test.is_free ? 'checked' : ''} 
                                                   class="sr-only peer" onchange="this.form.submit()">
                                            <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-secondary"></div>
                                            <span class="ms-3 text-xs font-medium text-gray-700 dark:text-gray-300">${test.is_free ? 'Free' : 'Paid'}</span>
                                        </label>
                                        <input type="hidden" name="update_test_status">
                                    </form>
                                    <form method="POST" onsubmit="return confirm('Remove this test from package?');">
                                        <input type="hidden" name="package_id" value="${packageId}">
                                        <input type="hidden" name="test_id" value="${test.test_id}">
                                        <button type="submit" name="remove_test_from_package" class="text-red-600 hover:text-red-900 dark:text-red-400">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            `;
                        });
                    } else {
                        html = '<p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No tests added to this package yet</p>';
                    }
                    document.getElementById('currentTestsList').innerHTML = html;
                    lucide.createIcons();
                });
        }

        function showAddTestsOption(option) {
            document.getElementById('addAllTestsOption').classList.add('hidden');
            document.getElementById('addCategoryTestsOption').classList.add('hidden');
            document.getElementById('addSingleTestOption').classList.add('hidden');
            
            document.getElementById('add' + option.charAt(0).toUpperCase() + option.slice(1) + 'TestsOption').classList.remove('hidden');
            
            if (option === 'category') {
                filterAddCategories();
            } else if (option === 'all') {
                loadAllAvailableTests();
            }
        }

        function filterAddCategories() {
            const examId = document.getElementById('addExamFilter').value;
            const categoryOptions = document.querySelectorAll('#addCategoryFilter option');
            
            categoryOptions.forEach(option => {
                if (option.value === '') return;
                if (!examId || option.dataset.exam === examId) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
            
            // Reset category selection
            document.getElementById('addCategoryFilter').value = '';
            document.getElementById('addCategoryTestsList').innerHTML = '<p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">Select a category to view tests</p>';
        }

        function filterAddTestsByCategory() {
            const categoryId = document.getElementById('addCategoryFilter').value;
            if (!categoryId) {
                document.getElementById('addCategoryTestsList').innerHTML = '<p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">Select a category to view tests</p>';
                return;
            }
            
            // Fetch tests for this category that aren't already in the package
            fetch(`backend/get_tests_by_category.php?category_id=${categoryId}&package_id=${currentPackageId}`)
                .then(response => response.json())
                .then(tests => {
                    let html = '';
                    if (tests.length) {
                        tests.forEach(test => {
                            html += `
                            <div class="flex items-center gap-2 p-2 hover:bg-gray-50 dark:hover:bg-gray-700 rounded">
                                <input type="checkbox" value="${test.test_id}" 
                                       class="test-to-add rounded border-gray-300 text-primary focus:ring-primary dark:border-gray-600 dark:bg-gray-700">
                                <span class="text-sm dark:text-gray-300">
                                    ${escapeHtml(test.test_name)} (${escapeHtml(test.exam_name)})
                                </span>
                            </div>
                            `;
                        });
                    } else {
                        html = '<p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No tests found in this category or all tests are already in package</p>';
                    }
                    
                    document.getElementById('addCategoryTestsList').innerHTML = html;
                });
        }

        function loadAllAvailableTests() {
            // Fetch tests that aren't already in the package
            fetch(`backend/get_available_tests.php?package_id=${currentPackageId}`)
                .then(response => response.json())
                .then(tests => {
                    let html = '';
                    if (tests.length) {
                        tests.forEach(test => {
                            html += `
                            <div class="flex items-center gap-2 p-2 hover:bg-gray-50 dark:hover:bg-gray-700 rounded">
                                <input type="checkbox" value="${test.test_id}" 
                                       class="test-to-add rounded border-gray-300 text-primary focus:ring-primary dark:border-gray-600 dark:bg-gray-700">
                                <span class="text-sm dark:text-gray-300">
                                    ${escapeHtml(test.test_name)} (${escapeHtml(test.exam_name)} • ${escapeHtml(test.category_name)})
                                </span>
                            </div>
                            `;
                        });
                    } else {
                        html = '<p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">All tests are already in this package</p>';
                    }
                    
                    document.getElementById('allAvailableTestsList').innerHTML = html;
                });
        }

        function selectAllAvailableTests() {
            const checkboxes = document.querySelectorAll('#allAvailableTestsList .test-to-add');
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
        }

        function addSelectedTests() {
            const selectedTests = [];
            
            // Check which option is active
            if (!document.getElementById('addAllTestsOption').classList.contains('hidden')) {
                document.querySelectorAll('#allAvailableTestsList .test-to-add:checked').forEach(checkbox => {
                    selectedTests.push(checkbox.value);
                });
            } else if (!document.getElementById('addCategoryTestsOption').classList.contains('hidden')) {
                document.querySelectorAll('#addCategoryTestsList .test-to-add:checked').forEach(checkbox => {
                    selectedTests.push(checkbox.value);
                });
            } else {
                const testId = document.getElementById('singleTestSelect').value;
                if (testId) selectedTests.push(testId);
            }
            
            if (selectedTests.length === 0) {
                alert('Please select at least one test to add');
                return;
            }
            
            // Submit form to add tests
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const packageIdInput = document.createElement('input');
            packageIdInput.type = 'hidden';
            packageIdInput.name = 'package_id';
            packageIdInput.value = currentPackageId;
            form.appendChild(packageIdInput);
            
            selectedTests.forEach(testId => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'tests[]';
                input.value = testId;
                form.appendChild(input);
            });
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'add_tests_to_package';
            form.appendChild(actionInput);
            
            document.body.appendChild(form);
            form.submit();
        }

        // Helper function to escape HTML
        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const packageModal = document.getElementById('packageModal');
            if (event.target === packageModal) {
                closePackageModal();
            }
            
            const manageTestsModal = document.getElementById('manageTestsModal');
            if (event.target === manageTestsModal) {
                closeManageTestsModal();
            }
        };
    </script>
</body>
</html>
