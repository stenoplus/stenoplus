<?php
session_start();
require 'backend/config.php';

// Authentication & Authorization
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new user
    if (isset($_POST['add_user'])) {
        $full_name = $conn->real_escape_string($_POST['full_name']);
        $email = $conn->real_escape_string($_POST['email']);
        $mobile = $conn->real_escape_string($_POST['mobile']);
        $role = $conn->real_escape_string($_POST['role']);
        $password = isset($_POST['auto_generate']) ? 
            password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT) :
            password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        $student_id = ($role === 'student') ? 'SP' . strtoupper(substr(md5(uniqid()), 0, 4)) : '';
        
        $query = "INSERT INTO users (full_name, email, mobile, password, role, student_id, is_active) 
                  VALUES ('$full_name', '$email', '$mobile', '$password', '$role', '$student_id', 1)";
        
        if ($conn->query($query)) {
            $_SESSION['success'] = "User added successfully!";
            if (isset($_POST['auto_generate'])) {
                $_SESSION['generated_password'] = "Temporary password: " . $_POST['password'];
            }
        } else {
            $_SESSION['error'] = "Error adding user: " . $conn->error;
        }
    }
    // Update user
    elseif (isset($_POST['update_user'])) {
        $user_id = intval($_POST['user_id']);
        $full_name = $conn->real_escape_string($_POST['full_name']);
        $email = $conn->real_escape_string($_POST['email']);
        $mobile = $conn->real_escape_string($_POST['mobile']);
        $role = $conn->real_escape_string($_POST['role']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Handle password update if provided
        $password_update = '';
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $password_update = ", password = '$password'";
        }
        
        $query = "UPDATE users SET 
                  full_name = '$full_name',
                  email = '$email',
                  mobile = '$mobile',
                  role = '$role',
                  is_active = $is_active
                  $password_update
                  WHERE user_id = $user_id";
        
        if ($conn->query($query)) {
            $_SESSION['success'] = "User updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating user: " . $conn->error;
        }
    }
    // Bulk actions
    elseif (isset($_POST['bulk_action'])) {
        $action = $_POST['bulk_action'];
        $user_ids = $_POST['user_ids'] ?? [];
        
        if (!empty($user_ids)) {
            $ids = implode(',', array_map('intval', $user_ids));
            
            switch ($action) {
                case 'activate':
                    $conn->query("UPDATE users SET is_active = 1 WHERE user_id IN ($ids)");
                    $_SESSION['success'] = "Selected users activated!";
                    break;
                case 'deactivate':
                    $conn->query("UPDATE users SET is_active = 0 WHERE user_id IN ($ids)");
                    $_SESSION['success'] = "Selected users deactivated!";
                    break;
                case 'delete':
                    $conn->query("DELETE FROM users WHERE user_id IN ($ids)");
                    $_SESSION['success'] = "Selected users deleted!";
                    break;
            }
        }
    }
    
    header("Location: manage-users.php");
    exit();
}

// Handle single delete
if (isset($_GET['delete'])) {
    $user_id = intval($_GET['delete']);
    $conn->query("DELETE FROM users WHERE user_id = $user_id");
    $_SESSION['success'] = "User deleted successfully!";
    header("Location: manage-users.php");
    exit();
}

// Fetch all users with filters
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search_term = $_GET['search'] ?? '';

$query = "SELECT * FROM users WHERE 1=1";
if ($role_filter) $query .= " AND role = '" . $conn->real_escape_string($role_filter) . "'";
if ($status_filter === 'active') $query .= " AND is_active = 1";
if ($status_filter === 'inactive') $query .= " AND is_active = 0";
if ($search_term) {
    $query .= " AND (full_name LIKE '%" . $conn->real_escape_string($search_term) . "%' OR 
                email LIKE '%" . $conn->real_escape_string($search_term) . "%' OR 
                mobile LIKE '%" . $conn->real_escape_string($search_term) . "%')";
}
$query .= " ORDER BY created_at DESC";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - StenoPlus</title>
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #F3F4F6; }
        .sidebar { transition: transform 0.3s ease-in-out; height: 100vh; overflow-y: auto; }
        .sidebar-hidden { transform: translateX(-100%); }
        .dark body { background-color: #111827; color: #F3F4F6; }
        .dark .sidebar { background-color: #1F2937; }
        .dark .bg-white { background-color: #1E293B !important; }
        .popup-form {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(0, 0, 0, 0.5); display: flex; justify-content: center; 
            align-items: center; visibility: hidden; opacity: 0; transition: opacity 0.3s; z-index: 1000;
        }
        .popup-form.active { visibility: visible; opacity: 1; }
        .popup-content { background: white; padding: 20px; border-radius: 8px; width: 400px; }
        .dark .popup-content { background: #1F2937; }
        .password-toggle { cursor: pointer; }
    </style>
</head>
<body class="flex dark:bg-gray-900">
    <?php include 'sidebar.php'; ?>
    
    <main class="md:ml-64 w-full lg:p-6 md:p-0">
        <header class="flex justify-between items-center bg-white shadow p-4 rounded-none lg:rounded-lg dark:bg-gray-800">
            <button id="openSidebar" class="md:hidden dark:text-white">
                <i data-lucide="menu"></i>
            </button>
            <h2 class="text-xl font-semibold dark:text-white">User Management</h2>
            <div class="flex items-center space-x-4">
                <i data-lucide="bell" class="cursor-pointer dark:text-white"></i>
                <i data-lucide="moon" id="darkModeToggle" class="cursor-pointer dark:text-white"></i>
                <div class="relative">
                    <img src="assets/images/admin.png" alt="Profile" class="w-10 h-10 rounded-full cursor-pointer" id="profileBtn">
                    <div id="profileDropdown" class="hidden absolute right-0 bg-white shadow-lg rounded-md w-40 mt-2 dark:bg-gray-700">
                        <p class="p-2 text-sm dark:text-white"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?></p>
                        <p class="p-2 text-xs text-gray-500 dark:text-gray-300">Role: Admin</p>
                        <hr class="dark:border-gray-600">
                        <li class="flex items-center space-x-2 p-2 text-sm hover:bg-gray-200 dark:hover:bg-gray-600">
                            <i data-lucide="user" class="w-4 h-4"></i> 
                            <a href="#" class="dark:text-white">Profile</a>
                        </li>
                        <li class="flex items-center space-x-2 p-2 hover:bg-red-600 hover:text-white rounded-b text-sm">
                            <i data-lucide="log-out" class="w-4 h-4"></i> 
                            <a href="backend/authentication/logout.php" class="dark:text-white">Logout</a>
                        </li>
                    </div>
                </div>
            </div>
        </header>

        <section class="mt-6 p-6 lg:p-0">
            <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                <!-- Filters and Actions -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                    <h3 class="text-lg font-semibold dark:text-white">Manage Users</h3>
                    <div class="flex flex-col md:flex-row gap-2 w-full md:w-auto">
                        <button id="openPopup" class="bg-[#D2171E] text-white px-4 py-2 rounded whitespace-nowrap">
                            <i data-lucide="plus" class="inline mr-1 w-4 h-4"></i> Add User
                        </button>
                        <div class="flex gap-2">
                            <select id="roleFilter" class="border rounded px-3 py-2 dark:bg-gray-700 dark:text-white">
                                <option value="">All Roles</option>
                                <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                                <option value="instructor" <?= $role_filter === 'instructor' ? 'selected' : '' ?>>Instructor</option>
                                <option value="student" <?= $role_filter === 'student' ? 'selected' : '' ?>>Student</option>
                            </select>
                            <select id="statusFilter" class="border rounded px-3 py-2 dark:bg-gray-700 dark:text-white">
                                <option value="">All Status</option>
                                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Search and Bulk Actions -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                    <div class="relative w-full md:w-64">
                        <input type="text" id="searchInput" placeholder="Search users..." value="<?= htmlspecialchars($search_term) ?>" 
                               class="w-full pl-10 pr-4 py-2 border rounded dark:bg-gray-700 dark:text-white">
                        <i data-lucide="search" class="absolute left-3 top-2.5 text-gray-400"></i>
                    </div>
                    
                    <form method="post" class="flex items-center gap-2" id="bulkForm">
                        <select name="bulk_action" class="border rounded px-3 py-2 dark:bg-gray-700 dark:text-white">
                            <option value="">Bulk Actions</option>
                            <option value="activate">Activate</option>
                            <option value="deactivate">Deactivate</option>
                            <option value="delete">Delete</option>
                        </select>
                        <button type="submit" class="bg-gray-200 px-4 py-2 rounded dark:bg-gray-700 dark:text-white">
                            Apply
                        </button>
                    </form>
                </div>

                <!-- Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 dark:bg-green-900 dark:border-green-700 dark:text-green-100">
                        <?= $_SESSION['success'] ?>
                        <?php unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 dark:bg-red-900 dark:border-red-700 dark:text-red-100">
                        <?= $_SESSION['error'] ?>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['generated_password'])): ?>
                    <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-4 dark:bg-blue-900 dark:border-blue-700 dark:text-blue-100">
                        <?= $_SESSION['generated_password'] ?>
                        <?php unset($_SESSION['generated_password']); ?>
                    </div>
                <?php endif; ?>

                <!-- Users Table -->
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse border border-gray-300 dark:border-gray-600">
                        <thead>
                            <tr class="bg-gray-200 dark:bg-gray-700">
                                <th class="p-2 border dark:border-gray-600"><input type="checkbox" id="selectAll"></th>
                                <th class="p-2 border dark:border-gray-600">ID</th>
                                <th class="p-2 border dark:border-gray-600">Name</th>
                                <th class="p-2 border dark:border-gray-600">Email</th>
                                <th class="p-2 border dark:border-gray-600">Phone</th>
                                <th class="p-2 border dark:border-gray-600">Role</th>
                                <th class="p-2 border dark:border-gray-600">Status</th>
                                <th class="p-2 border dark:border-gray-600">Joined</th>
                                <th class="p-2 border dark:border-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr class="dark:border-gray-600 <?= !$row['is_active'] ? 'bg-gray-100 dark:bg-gray-900' : '' ?>">
                                        <td class="p-2 border text-center dark:border-gray-600">
                                            <input type="checkbox" name="user_ids[]" value="<?= $row['user_id'] ?>" class="user-checkbox">
                                        </td>
                                        <td class="p-2 border text-center dark:border-gray-600 dark:text-white"><?= $row['user_id'] ?></td>
                                        <td class="p-2 border dark:border-gray-600 dark:text-white">
                                            <div class="flex items-center gap-2">
                                                <?php if (!empty($row['profile_picture'])): ?>
                                                    <img src="<?= htmlspecialchars($row['profile_picture']) ?>" alt="Profile" class="w-8 h-8 rounded-full">
                                                <?php else: ?>
                                                    <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center dark:bg-gray-600">
                                                        <i data-lucide="user" class="w-4 h-4 text-gray-500 dark:text-gray-300"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <?= htmlspecialchars($row['full_name']) ?>
                                            </div>
                                        </td>
                                        <td class="p-2 border dark:border-gray-600 dark:text-white"><?= htmlspecialchars($row['email']) ?></td>
                                        <td class="p-2 border dark:border-gray-600 dark:text-white"><?= htmlspecialchars($row['mobile']) ?></td>
                                        <td class="p-2 border text-center dark:border-gray-600">
                                            <span class="px-2 py-1 rounded-full text-xs 
                                                <?= $row['role'] === 'admin' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 
                                                   ($row['role'] === 'instructor' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 
                                                   'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200') ?>">
                                                <?= ucfirst($row['role']) ?>
                                            </span>
                                        </td>
                                        <td class="p-2 border text-center dark:border-gray-600">
                                            <span class="px-2 py-1 rounded-full text-xs 
                                                <?= $row['is_active'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' ?>">
                                                <?= $row['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td class="p-2 border text-center dark:border-gray-600 dark:text-white">
                                            <?= date('M d, Y', strtotime($row['created_at'])) ?>
                                        </td>
                                        <td class="p-2 border text-center dark:border-gray-600">
                                            <div class="flex justify-center gap-1">
                                                <button onclick="openEditModal(
                                                    <?= $row['user_id'] ?>, 
                                                    '<?= htmlspecialchars($row['full_name']) ?>', 
                                                    '<?= htmlspecialchars($row['email']) ?>', 
                                                    '<?= htmlspecialchars($row['mobile']) ?>', 
                                                    '<?= $row['role'] ?>', 
                                                    <?= $row['is_active'] ? 'true' : 'false' ?>
                                                )" class="bg-blue-500 text-white p-1 rounded">
                                                    <i data-lucide="edit" class="w-4 h-4"></i>
                                                </button>
                                                <button onclick="confirmDelete(<?= $row['user_id'] ?>)" class="bg-red-500 text-white p-1 rounded">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="p-4 border text-center dark:border-gray-600 dark:text-white">No users found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <!-- Add User Modal -->
    <div id="popupForm" class="popup-form">
        <div class="popup-content dark:bg-gray-700">
            <h3 class="text-lg font-semibold mb-4 dark:text-white">Add New User</h3>
            <form action="manage-users.php" method="POST">
                <div class="mb-4">
                    <label class="block mb-1 dark:text-white">Full Name</label>
                    <input type="text" name="full_name" required class="w-full p-2 border rounded dark:bg-gray-800 dark:text-white dark:border-gray-600">
                </div>
                <div class="mb-4">
                    <label class="block mb-1 dark:text-white">Email</label>
                    <input type="email" name="email" required class="w-full p-2 border rounded dark:bg-gray-800 dark:text-white dark:border-gray-600">
                </div>
                <div class="mb-4">
                    <label class="block mb-1 dark:text-white">Phone</label>
                    <input type="text" name="mobile" required class="w-full p-2 border rounded dark:bg-gray-800 dark:text-white dark:border-gray-600">
                </div>
                <div class="mb-4">
                    <label class="block mb-1 dark:text-white">Role</label>
                    <select name="role" required class="w-full p-2 border rounded dark:bg-gray-800 dark:text-white dark:border-gray-600">
                        <option value="student">Student</option>
                        <option value="instructor">Instructor</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block mb-1 dark:text-white">Password</label>
                    <div class="relative">
                        <input type="password" name="password" id="passwordField" class="w-full p-2 border rounded dark:bg-gray-800 dark:text-white dark:border-gray-600">
                        <i data-lucide="eye" class="absolute right-3 top-2.5 password-toggle" onclick="togglePassword()"></i>
                    </div>
                    <div class="mt-2 flex items-center">
                        <input type="checkbox" name="auto_generate" id="autoGenerate" class="mr-2" checked>
                        <label for="autoGenerate" class="text-sm dark:text-white">Auto-generate password</label>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" id="closePopup" class="bg-gray-400 text-white px-4 py-2 rounded">Cancel</button>
                    <button type="submit" name="add_user" class="bg-[#D2171E] text-white px-4 py-2 rounded">Add User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex justify-center items-center w-full h-full p-4">
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 w-full sm:w-1/2 md:w-1/3 max-w-lg mx-2 dark:bg-gray-700">
            <h2 class="text-xl font-semibold mb-4 dark:text-white">Edit User</h2>
            <form action="manage-users.php" method="POST">
                <input type="hidden" id="edit_user_id" name="user_id">
                
                <div class="mb-4">
                    <label class="block mb-1 dark:text-white">Full Name</label>
                    <input type="text" id="edit_full_name" name="full_name" required 
                           class="w-full p-2 border rounded dark:bg-gray-800 dark:text-white dark:border-gray-600">
                </div>
                <div class="mb-4">
                    <label class="block mb-1 dark:text-white">Email</label>
                    <input type="email" id="edit_email" name="email" required 
                           class="w-full p-2 border rounded dark:bg-gray-800 dark:text-white dark:border-gray-600">
                </div>
                <div class="mb-4">
                    <label class="block mb-1 dark:text-white">Phone</label>
                    <input type="text" id="edit_mobile" name="mobile" required 
                           class="w-full p-2 border rounded dark:bg-gray-800 dark:text-white dark:border-gray-600">
                </div>
                <div class="mb-4">
                    <label class="block mb-1 dark:text-white">Role</label>
                    <select id="edit_role" name="role" required 
                            class="w-full p-2 border rounded dark:bg-gray-800 dark:text-white dark:border-gray-600">
                        <option value="student">Student</option>
                        <option value="instructor">Instructor</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block mb-1 dark:text-white">Status</label>
                    <div class="flex items-center">
                        <input type="checkbox" id="edit_is_active" name="is_active" class="mr-2">
                        <label for="edit_is_active" class="dark:text-white">Active User</label>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block mb-1 dark:text-white">New Password (leave blank to keep current)</label>
                    <div class="relative">
                        <input type="password" name="password" id="editPasswordField" 
                               class="w-full p-2 border rounded dark:bg-gray-800 dark:text-white dark:border-gray-600">
                        <i data-lucide="eye" class="absolute right-3 top-2.5 password-toggle" onclick="togglePassword('editPasswordField')"></i>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeEditModal()" class="bg-gray-400 text-white px-4 py-2 rounded">Cancel</button>
                    <button type="submit" name="update_user" class="bg-[#D2171E] text-white px-4 py-2 rounded">Update</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        // Toggle password visibility
        function togglePassword(fieldId = 'passwordField') {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling;
            if (field.type === 'password') {
                field.type = 'text';
                icon.setAttribute('data-lucide', 'eye-off');
            } else {
                field.type = 'password';
                icon.setAttribute('data-lucide', 'eye');
            }
            lucide.createIcons();
        }
        
        // Auto-generate password toggle
        document.getElementById('autoGenerate').addEventListener('change', function() {
            const passwordField = document.getElementById('passwordField');
            if (this.checked) {
                const randomPassword = Math.random().toString(36).slice(-8);
                passwordField.value = randomPassword;
                passwordField.type = 'text';
            } else {
                passwordField.value = '';
                passwordField.type = 'password';
            }
        });
        
        // Initialize auto-generated password
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('autoGenerate').checked) {
                const randomPassword = Math.random().toString(36).slice(-8);
                document.getElementById('passwordField').value = randomPassword;
            }
        });
        
        // Modal controls
        document.getElementById('openPopup').addEventListener('click', function() {
            document.getElementById('popupForm').classList.add('active');
        });
        
        document.getElementById('closePopup').addEventListener('click', function() {
            document.getElementById('popupForm').classList.remove('active');
        });
        
        // Edit modal functions
        function openEditModal(user_id, full_name, email, mobile, role, is_active) {
            document.getElementById('edit_user_id').value = user_id;
            document.getElementById('edit_full_name').value = full_name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_mobile').value = mobile;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_is_active').checked = is_active;
            document.getElementById('editModal').classList.remove('hidden');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
        
        // Bulk actions
        document.getElementById('selectAll').addEventListener('change', function() {
            document.querySelectorAll('.user-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
        
        // Filters
        document.getElementById('roleFilter').addEventListener('change', function() {
            updateFilters();
        });
        
        document.getElementById('statusFilter').addEventListener('change', function() {
            updateFilters();
        });
        
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                updateFilters();
            }
        });
        
        function updateFilters() {
            const params = new URLSearchParams();
            const role = document.getElementById('roleFilter').value;
            const status = document.getElementById('statusFilter').value;
            const search = document.getElementById('searchInput').value;
            
            if (role) params.set('role', role);
            if (status) params.set('status', status);
            if (search) params.set('search', search);
            
            window.location.href = 'manage-users.php?' + params.toString();
        }
        
        // Delete confirmation
        function confirmDelete(userId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#D2171E',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'manage-users.php?delete=' + userId;
                }
            });
        }
        
        // Dark mode toggle
        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', document.documentElement.classList.contains('dark') ? 'enabled' : 'disabled');
        }
        
        document.getElementById('darkModeToggle').addEventListener('click', toggleDarkMode);
        
        // Apply dark mode preference on load
        if (localStorage.getItem('darkMode') === 'enabled') {
            document.documentElement.classList.add('dark');
        }
        
        // Profile dropdown
        document.getElementById('profileBtn').addEventListener('click', function() {
            document.getElementById('profileDropdown').classList.toggle('hidden');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('#profileBtn') && !event.target.closest('#profileDropdown')) {
                document.getElementById('profileDropdown').classList.add('hidden');
            }
        });
        
        // Mobile sidebar toggle
        document.getElementById('openSidebar').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('sidebar-hidden');
        });
    </script>
</body>
</html>
