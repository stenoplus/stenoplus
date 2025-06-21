<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Database Connection
require 'backend/config.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_student'])) {
        // Add new student
        $full_name = $conn->real_escape_string($_POST['full_name']);
        $email = $conn->real_escape_string($_POST['email']);
        $mobile = $conn->real_escape_string($_POST['mobile']);
        $password = password_hash('default123', PASSWORD_DEFAULT); // Default password
        $student_id = 'SP' . strtoupper(substr(md5(uniqid()), 0, 4));
        
        $query = "INSERT INTO users (full_name, email, mobile, password, student_id) 
                  VALUES ('$full_name', '$email', '$mobile', '$password', '$student_id')";
        
        if ($conn->query($query)) {
            $_SESSION['success'] = "Student added successfully!";
        } else {
            $_SESSION['error'] = "Error adding student: " . $conn->error;
        }
        
        header("Location: manage-students.php");
        exit();
    } elseif (isset($_POST['update_student'])) {
        // Update student
        $user_id = intval($_POST['user_id']);
        $full_name = $conn->real_escape_string($_POST['full_name']);
        $email = $conn->real_escape_string($_POST['email']);
        $mobile = $conn->real_escape_string($_POST['mobile']);
        $student_id = $conn->real_escape_string($_POST['student_id']);
        
        $query = "UPDATE users SET 
                  full_name = '$full_name',
                  email = '$email',
                  mobile = '$mobile',
                  student_id = '$student_id'
                  WHERE user_id = $user_id";
        
        if ($conn->query($query)) {
            $_SESSION['success'] = "Student updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating student: " . $conn->error;
        }
        
        header("Location: manage-students.php");
        exit();
    }
}

// Handle delete action
if (isset($_GET['delete'])) {
    $user_id = intval($_GET['delete']);
    
    $query = "DELETE FROM users WHERE user_id = $user_id AND role = 'student'";
    
    if ($conn->query($query)) {
        $_SESSION['success'] = "Student deleted successfully!";
    } else {
        $_SESSION['error'] = "Error deleting student: " . $conn->error;
    }
    
    header("Location: manage-students.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - StenoPlus</title>
    <!-- Favicon -->
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap">
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

        /* Popup Form */
        .popup-form {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            visibility: hidden;
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
            z-index: 1000;
        }
        .popup-form.active {
            visibility: visible;
            opacity: 1;
        }
        .popup-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            width: 400px;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
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
    <?php include 'sidebar.php';?>
    
    <!-- Main Content -->
    <main class="md:ml-64 w-full lg:p-6 md:p-0">
        <!-- Top Bar -->
        <header class="flex justify-between items-center bg-white shadow p-4 rounded-none lg:rounded-lg dark:bg-gray-800">
            <!-- Hamburger Menu (Only for Mobile) -->
            <button id="openSidebar" class="md:hidden">
                <i data-lucide="menu"></i>
            </button>

            <h2 class="text-xl font-semibold dark:text-white">Students</h2>
            
            <div class="flex items-center space-x-4">
                <i data-lucide="bell" class="cursor-pointer dark:text-white"></i>
                <i data-lucide="moon" id="darkModeToggle" class="cursor-pointer dark:text-white"></i>

                <!-- Profile Dropdown -->
                <div class="relative">
                    <img src="assets/images/student.png" alt="Profile" class="w-10 h-10 rounded-full cursor-pointer" id="profileBtn">
                    <div id="profileDropdown" class="hidden absolute right-0 bg-white shadow-lg rounded-md w-40 mt-2 dark:bg-gray-700">
                        <p class="p-2 text-sm dark:text-white"><?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?></p>
                        <p class="p-2 text-xs text-gray-500 dark:text-gray-300">Role: Admin</p>
                        <hr class="dark:border-gray-600">
                        <li class="flex items-center space-x-2 p-2 text-sm hover:bg-gray-200 dark:hover:bg-gray-600">
                            <i data-lucide="user" class="mr-0 w-4 h-4"></i> 
                            <a href="#" class="dark:text-white">View Profile</a>
                        </li>
                        <li class="flex items-center space-x-2 p-2 hover:bg-red-600 hover:text-white rounded-b text-sm">
                            <i data-lucide="log-out" class="mr-0 w-4 h-4"></i> 
                            <a href="backend/authentication/logout.php" class="dark:text-white">Logout</a>
                        </li>
                    </div>
                </div>
            </div>
        </header>

        <!-- Manage Students Content -->
        <section class="mt-6 p-6 lg:p-0">
            <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold dark:text-white">Manage Students</h3>
                    <button id="openPopup" class="bg-[#D2171E] text-white px-4 py-2 rounded">Add New Student</button>
                </div>
                
                <!-- Display success/error messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo $_SESSION['success']; ?></span>
                        <span class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                            <svg class="fill-current h-6 w-6 text-green-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Close</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/></svg>
                        </span>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo $_SESSION['error']; ?></span>
                        <span class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                            <svg class="fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><title>Close</title><path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/></svg>
                        </span>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
                <div class="w-full overflow-x-auto">
                    <table class="w-full mt-4 border-collapse border border-gray-300 min-w-max dark:border-gray-600">
                        <thead>
                            <tr class="bg-gray-200 dark:bg-gray-700">
                                <th class="border border-gray-300 px-4 py-2 dark:border-gray-600">ID</th>
                                <th class="border border-gray-300 px-4 py-2 dark:border-gray-600">Name</th>
                                <th class="border border-gray-300 px-4 py-2 dark:border-gray-600">Email</th>
                                <th class="border border-gray-300 px-4 py-2 dark:border-gray-600">Phone</th>
                                <th class="border border-gray-300 px-4 py-2 dark:border-gray-600">Student ID</th>
                                <th class="border border-gray-300 px-4 py-2 dark:border-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Fetch all students from the database (excluding admin)
                            $query = "SELECT user_id, full_name, email, mobile, student_id FROM users WHERE role = 'student' ORDER BY user_id ASC";
                            $result = $conn->query($query);
                        
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<tr class='dark:border-gray-600'>";
                                    echo "<td class='border border-gray-300 px-4 py-2 text-center dark:border-gray-600 dark:text-white'>" . $row['user_id'] . "</td>";
                                    echo "<td class='border border-gray-300 px-4 py-2 text-center dark:border-gray-600 dark:text-white'>" . htmlspecialchars($row['full_name']) . "</td>";
                                    echo "<td class='border border-gray-300 px-4 py-2 text-center dark:border-gray-600 dark:text-white'>" . htmlspecialchars($row['email']) . "</td>";
                                    echo "<td class='border border-gray-300 px-4 py-2 text-center dark:border-gray-600 dark:text-white'>" . htmlspecialchars($row['mobile']) . "</td>";
                                    echo "<td class='border border-gray-300 px-4 py-2 text-center dark:border-gray-600 dark:text-white'>" . htmlspecialchars($row['student_id']) . "</td>";
                                    echo "<td class='border border-gray-300 px-4 py-2 text-center flex justify-center gap-2 dark:border-gray-600'>";
                                    // Edit Button
                                    echo "<button onclick='openEditModal(" . $row['user_id'] . ", \"" . htmlspecialchars($row['full_name']) . "\", \"" . htmlspecialchars($row['email']) . "\", \"" . htmlspecialchars($row['mobile']) . "\", \"" . htmlspecialchars($row['student_id']) . "\")' class='bg-[#D2171E] text-white px-2 py-1 rounded'><i data-lucide='edit' class='w-4 h-4'></i></button>";
                                    // Delete Button
                                    echo "<button class='bg-[#D2171E] text-white px-2 py-1 rounded' onclick='confirmDelete(" . $row['user_id'] . ")'><i data-lucide='trash-2' class='w-4 h-4'></i></button>";
                                    echo "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6' class='border border-gray-300 px-4 py-2 text-center dark:border-gray-600 dark:text-white'>No students found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <!-- Add Student Popup Form -->
    <div id="popupForm" class="popup-form">
        <div class="popup-content bg-white p-6 rounded-lg dark:bg-gray-700">
            <h3 class="text-lg font-semibold mb-4 dark:text-white">Add New Student</h3>
            <form action="manage-students.php" method="POST">
                <div class="mb-4">
                    <label class="block mb-2 dark:text-white">Full Name:</label>
                    <input type="text" name="full_name" placeholder="Enter full name" required 
                           class="w-full p-2 border rounded focus:outline-none bg-gray-100 dark:bg-gray-800 dark:text-white dark:border-gray-600">
                </div>
                
                <div class="mb-4">
                    <label class="block mb-2 dark:text-white">Email:</label>
                    <input type="email" name="email" placeholder="Enter email" required 
                           class="w-full p-2 border rounded focus:outline-none bg-gray-100 dark:bg-gray-800 dark:text-white dark:border-gray-600">
                </div>
                
                <div class="mb-4">
                    <label class="block mb-2 dark:text-white">Mobile:</label>
                    <input type="text" name="mobile" placeholder="Enter mobile number" required 
                           class="w-full p-2 border rounded focus:outline-none bg-gray-100 dark:bg-gray-800 dark:text-white dark:border-gray-600">
                </div>
                
                <div class="flex justify-end">
                    <button type="button" id="closePopup" class="bg-gray-400 text-white px-4 py-2 rounded mr-2">Cancel</button>
                    <button type="submit" name="add_student" class="bg-[#D2171E] text-white px-4 py-2 rounded">Add Student</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Student Modal -->
    <div id="editModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex justify-center items-center w-full h-full p-4">
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 w-full sm:w-1/2 md:w-1/3 max-w-lg mx-2 dark:bg-gray-700">
            <h2 class="text-xl font-semibold mb-4 dark:text-white">Edit Student</h2>
            <form action="manage-students.php" method="POST">
                <input type="hidden" id="edit_user_id" name="user_id">
                
                <div class="mb-4">
                    <label for="edit_full_name" class="block text-sm font-medium text-gray-700 dark:text-white">Full Name:</label>
                    <input type="text" id="edit_full_name" name="full_name" 
                           class="w-full px-3 py-2 border border-gray-300 rounded mt-1 focus:outline-none bg-gray-100 dark:bg-gray-800 dark:text-white dark:border-gray-600" required>
                </div>
                
                <div class="mb-4">
                    <label for="edit_email" class="block text-sm font-medium text-gray-700 dark:text-white">Email:</label>
                    <input type="email" id="edit_email" name="email" 
                           class="w-full px-3 py-2 border border-gray-300 rounded mt-1 focus:outline-none bg-gray-100 dark:bg-gray-800 dark:text-white dark:border-gray-600" required>
                </div>
                
                <div class="mb-4">
                    <label for="edit_mobile" class="block text-sm font-medium text-gray-700 dark:text-white">Mobile:</label>
                    <input type="text" id="edit_mobile" name="mobile" 
                           class="w-full px-3 py-2 border border-gray-300 rounded mt-1 focus:outline-none bg-gray-100 dark:bg-gray-800 dark:text-white dark:border-gray-600" required>
                </div>
                
                <div class="mb-4">
                    <label for="edit_student_id" class="block text-sm font-medium text-gray-700 dark:text-white">Student ID:</label>
                    <input type="text" id="edit_student_id" name="student_id" 
                           class="w-full px-3 py-2 border border-gray-300 rounded mt-1 focus:outline-none bg-gray-100 dark:bg-gray-800 dark:text-white dark:border-gray-600" required>
                </div>

                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" onclick="closeEditModal()" class="bg-gray-400 text-white px-4 py-2 rounded">Cancel</button>
                    <button type="submit" name="update_student" class="bg-red-600 text-white px-4 py-2 rounded">Update</button>
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

        // Popup Form Toggle
        document.getElementById("openPopup").addEventListener("click", function () {
            document.getElementById("popupForm").classList.add("active");
        });
        
        document.getElementById("closePopup").addEventListener("click", function () {
            document.getElementById("popupForm").classList.remove("active");
        });

        // Function to open the edit modal with student data
        function openEditModal(user_id, full_name, email, mobile, student_id) {
            document.getElementById("edit_user_id").value = user_id;
            document.getElementById("edit_full_name").value = full_name;
            document.getElementById("edit_email").value = email;
            document.getElementById("edit_mobile").value = mobile;
            document.getElementById("edit_student_id").value = student_id;
            document.getElementById("editModal").classList.remove("hidden");

            // Add event listener for Escape key
            document.addEventListener("keydown", handleEscapeKey);
        }

        function closeEditModal() {
            document.getElementById("editModal").classList.add("hidden");
            document.removeEventListener("keydown", handleEscapeKey);
        }

        function handleEscapeKey(event) {
            if (event.key === "Escape") {
                closeEditModal();
            }
        }

        // Confirm Delete Alert
        function confirmDelete(userId) {
            Swal.fire({
                title: "Are you sure?",
                text: "You won't be able to revert this!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#3085d6",
                confirmButtonText: "Yes, delete it!"
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'manage-students.php?delete=' + userId;
                }
            });
        }

        // Close popup when clicking outside
        window.addEventListener('click', function(event) {
            const popupForm = document.getElementById('popupForm');
            if (event.target === popupForm) {
                popupForm.classList.remove('active');
            }
        });
    </script>
</body>
</html>
