<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Retrieve user details
$user_name = $_SESSION['user_name'];
$student_id = $_SESSION['student_id'];

// Database Connection
require 'backend/config.php';

// Check for success messages
$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Exams - StenoPlus</title>
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap">
    <script src="https://unpkg.com/lucide@latest"></script>
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
        
        .dark body { background-color: #111827; color: #F3F4F6; }
        .dark .sidebar { background-color: #1F2937; }
        .dark .bg-white { background-color: #1E293B !important; }
        .dark .text-gray-500 { color: #9CA3AF !important; }
        .dark header { background-color: #1F2937 !important; }
        .dark .logo { background-color:#F9F9F9 !important; }
        
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
        .popup-form.active { visibility: visible; opacity: 1; }
        .popup-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
        }
        
        /* Table Responsiveness */
        @media (max-width: 640px) {
            table { display: block; overflow-x: auto; white-space: nowrap; }
        }
    </style>
    <script>
        tailwind.config = { darkMode: 'class' };
    </script>
</head>
<body class="flex">

    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="md:ml-64 w-full lg:p-6 md:p-0">
        <!-- Top Bar -->
        <header class="flex justify-between items-center bg-white shadow p-4 rounded-none lg:rounded-lg dark:bg-gray-800">
            <button id="openSidebar" class="md:hidden">
                <i data-lucide="menu"></i>
            </button>
            <h2 class="text-xl font-semibold dark:text-white">Exams</h2>
            <div class="flex items-center space-x-4">
                <i data-lucide="bell" class="cursor-pointer dark:text-white"></i>
                <i data-lucide="moon" id="darkModeToggle" class="cursor-pointer dark:text-white"></i>
                <div class="relative">
                    <img src="assets/images/student.png" alt="Profile" class="w-10 h-10 rounded-full cursor-pointer" id="profileBtn">
                    <div id="profileDropdown" class="hidden absolute right-0 bg-white shadow-lg rounded-md w-40 mt-2 dark:bg-gray-700">
                        <p class="p-2 text-sm dark:text-white"><?php echo htmlspecialchars($user_name); ?></p>
                        <p class="p-2 text-xs text-gray-500 dark:text-gray-300">Role: <?php echo htmlspecialchars($student_id); ?></p>
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

        <!-- Manage Exams Content -->
        <section class="mt-6 p-6 lg:p-0">
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-lg font-semibold mb-4">Manage Exams</h3>
                <button id="openPopup" class="bg-[#D2171E] text-white px-4 py-2 rounded">Add New Exam</button>
                <div class="w-full overflow-x-auto">
                    <table class="w-full mt-4 border-collapse border border-gray-300 min-w-max dark:border-gray-600">
                        <thead>
                            <tr class="bg-gray-200 dark:bg-gray-700">
                                <th class="border border-gray-300 px-4 py-2 dark:border-gray-600">ID</th>
                                <th class="border border-gray-300 px-4 py-2 dark:border-gray-600">Exam</th>
                                <th class="border border-gray-300 px-4 py-2 dark:border-gray-600">Logo</th>
                                <th class="border border-gray-300 px-4 py-2 dark:border-gray-600">Actions</th>
                            </tr>
                        </thead>
                      <!-- In the table body section -->
                    <tbody>
                        <?php
                        $query = "SELECT exam_id, exam_name, exam_logo FROM exams ORDER BY created_at ASC";
                        $result = $conn->query($query);
                        
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr class='hover:bg-gray-100 dark:hover:bg-gray-700'>";
                                echo "<td class='border border-gray-300 px-4 py-2 text-center dark:border-gray-600 dark:text-white'>" . $row['exam_id'] . "</td>";
                                echo "<td class='border border-gray-300 px-4 py-2 text-center dark:border-gray-600 dark:text-white'>" . htmlspecialchars($row['exam_name']) . "</td>";
                                echo "<td class='border border-gray-300 px-4 py-2 text-center dark:border-gray-600'>";
                                
                                // Check if logo exists and display it
                                if (!empty($row['exam_logo'])) {
                                    // Get the base filename without any path (in case full path was stored)
                                    $logo_filename = basename(htmlspecialchars($row['exam_logo']));
                                    $logo_relative_path = 'uploads/exam-logos/' . $logo_filename;
                                    $logo_absolute_path = $_SERVER['DOCUMENT_ROOT'] . '/stenoplus.in/' . $logo_relative_path;
                                    
                                    if (file_exists($logo_absolute_path)) {
                                        echo "<img src='" . $logo_relative_path . "' alt='Exam Logo' class='h-10 mx-auto'>";
                                    } else {
                                        // Debug output (remove in production)
                                        echo "<span class='text-gray-500 dark:text-gray-400'>Logo missing (File: " . $logo_filename . " at " . $logo_absolute_path . ")</span>";
                                    }
                                } else {
                                    echo "<span class='text-gray-500 dark:text-gray-400'>No logo</span>";
                                }
                                echo "</td>";
                                echo "<td class='border border-gray-300 px-4 py-2 text-center dark:border-gray-600'>";
                                echo "<div class='flex justify-center gap-2'>";
                                echo "<button onclick='openEditModal(" . $row['exam_id'] . ", \"" . htmlspecialchars($row['exam_name']) . "\", \"" . htmlspecialchars($row['exam_logo']) . "\")' class='bg-blue-600 text-white px-2 py-1 rounded hover:bg-blue-700 transition'>";
                                echo "<i data-lucide='edit' class='w-4 h-4'></i></button>";
                                echo "<button onclick='confirmDelete(" . $row['exam_id'] . ")' class='bg-red-600 text-white px-2 py-1 rounded hover:bg-red-700 transition'>";
                                echo "<i data-lucide='trash-2' class='w-4 h-4'></i></button>";
                                echo "</div></td></tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' class='border border-gray-300 px-4 py-2 text-center dark:border-gray-600 dark:text-white'>No exams found</td></tr>";
                        }
                        ?>
                    </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <!-- Add Exam Popup -->
    <div id="popupForm" class="popup-form">
        <div class="popup-content">
            <h3 class="text-lg font-semibold mb-4 dark:text-white">Create New Exam</h3>
            <form action="backend/exam_submit.php" method="POST" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="block mb-2 dark:text-white">Exam Name:</label>
                    <input type="text" name="exam_name" placeholder="Enter Exam Name" required 
                        class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-[#D2171E] bg-gray-100 dark:bg-gray-700 dark:text-white dark:border-gray-600">
                </div>
                <div class="mb-4">
                    <label class="block mb-2 dark:text-white">Exam Logo:</label>
                    <input type="file" name="exam_logo" class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-[#D2171E] bg-gray-100 dark:bg-gray-700 dark:text-white dark:border-gray-600" accept="image/*">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" id="closePopup" class="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500 transition">Cancel</button>
                    <button type="submit" name="submit" class="bg-[#D2171E] text-white px-4 py-2 rounded hover:bg-[#b3151b] transition">Submit</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Exam Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content dark:bg-gray-800">
            <h2 class="text-xl font-semibold mb-4 dark:text-white">Edit Exam</h2>
            <form action="backend/exam_submit.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" id="edit_exam_id" name="exam_id">
                
                <div class="mb-4">
                    <label class="block mb-2 dark:text-white">Exam Name:</label>
                    <input type="text" id="edit_exam_name" name="exam_name" required
                        class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-[#D2171E] bg-gray-100 dark:bg-gray-700 dark:text-white dark:border-gray-600">
                </div>
                
                <div class="mb-4">
                    <div id="currentLogoContainer" class="mb-2 hidden"></div>
                    <label class="block mb-2 dark:text-white">Change Logo:</label>
                    <input type="file" id="edit_exam_logo" name="exam_logo"
                        class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-[#D2171E] bg-gray-100 dark:bg-gray-700 dark:text-white dark:border-gray-600" accept="image/*">
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeEditModal()" class="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500 transition">Cancel</button>
                    <button type="submit" name="update" class="bg-[#D2171E] text-white px-4 py-2 rounded hover:bg-[#b3151b] transition">Update</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Show success/error messages
        <?php if ($success): ?>
            Swal.fire({
                position: 'bottom-start',
                icon: 'success',
                title: '<?php echo htmlspecialchars($success); ?>',
                showConfirmButton: false,
                timer: 2000
            });
        <?php endif; ?>
        
        <?php if ($error): ?>
            Swal.fire({
                position: 'bottom-start',
                icon: 'error',
                title: '<?php echo htmlspecialchars($error); ?>',
                showConfirmButton: false,
                timer: 2000
            });
        <?php endif; ?>

        // Profile Dropdown Toggle
        document.getElementById("profileBtn").addEventListener("click", function() {
            document.getElementById("profileDropdown").classList.toggle("hidden");
        });

        // Dark Mode Toggle
        function toggleDarkMode() {
            const isDark = document.documentElement.classList.toggle("dark");
            localStorage.setItem("darkMode", isDark ? "enabled" : "disabled");
        }
        
        if (localStorage.getItem("darkMode") === "enabled") {
            document.documentElement.classList.add("dark");
        }
        
        document.getElementById("darkModeToggle").addEventListener("click", toggleDarkMode);

        // Sidebar Toggle for Mobile
        const sidebar = document.getElementById("sidebar");
        const openSidebar = document.getElementById("openSidebar");
        
        openSidebar.addEventListener("click", function() {
            sidebar.classList.remove("sidebar-hidden");
        });

        // Close sidebar when clicking outside
        document.addEventListener("click", function(event) {
            if (!sidebar.contains(event.target) && event.target !== openSidebar) {
                sidebar.classList.add("sidebar-hidden");
            }
        });

        // Popup Form Handling
        document.getElementById("openPopup").addEventListener("click", function() {
            document.getElementById("popupForm").classList.add("active");
        });
        
        document.getElementById("closePopup").addEventListener("click", function() {
            document.getElementById("popupForm").classList.remove("active");
        });

        // Edit Modal Functions
        function openEditModal(exam_id, name, logo) {
            document.getElementById("edit_exam_id").value = exam_id;
            document.getElementById("edit_exam_name").value = name;
            
            const logoContainer = document.getElementById("currentLogoContainer");
            logoContainer.innerHTML = '';
            
            if (logo) {
                const img = document.createElement("img");
                img.src = 'uploads/exam_logo/' + logo;
                img.alt = 'Current Logo';
                img.className = 'h-10 mx-auto';
                logoContainer.appendChild(img);
            } else {
                logoContainer.innerHTML = '<p class="text-gray-500 dark:text-gray-400">No logo uploaded</p>';
            }
            
            document.getElementById("editModal").style.display = "flex";
        }

        function closeEditModal() {
            document.getElementById("editModal").style.display = "none";
        }

        // Confirm Delete
        function confirmDelete(examId) {
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
                    window.location.href = 'backend/exam_submit.php?delete=' + examId;
                }
            });
        }

        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            const popupForm = document.getElementById('popupForm');
            if (event.target === popupForm) {
                popupForm.classList.remove('active');
            }
            
            const editModal = document.getElementById('editModal');
            if (event.target === editModal) {
                closeEditModal();
            }
        });
    </script>
</body>
</html>