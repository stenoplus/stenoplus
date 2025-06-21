<?php
session_start();

// ✅ Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Redirect to login page
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
    <title>Manage Categories - StenoPlus</title>
    <!-- Favicon -->
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap">
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        /* Toast Notification */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            display: none;
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
    <?php include 'sidebar.php';?>
    <!-- Main Content -->
    <main class="md:ml-64 w-full lg:p-6 md:p-0">
        <!-- Top Bar -->
        <header class="flex justify-between items-center bg-white shadow p-4 rounded-none lg:rounded-lg">
            <!-- Hamburger Menu (Only for Mobile) -->
            <button id="openSidebar" class="md:hidden">
                <i data-lucide="menu"></i>
            </button>

            <h2 class="text-xl font-semibold">Categories</h2>
            
            <div class="flex items-center space-x-4">
                <i data-lucide="bell" class="cursor-pointer"></i>
                <i data-lucide="moon" id="darkModeToggle" class="cursor-pointer"></i>

                <!-- Profile Dropdown -->
                <div class="relative">
                    <img src="assets/images/student.png" alt="Profile" class="w-10 h-10 rounded-full cursor-pointer" id="profileBtn">
                    <div id="profileDropdown" class="hidden absolute right-0 bg-white shadow-lg rounded-md w-40 mt-2">
                        <p class="p-2 text-sm"><?php echo htmlspecialchars($user_name); ?></p>
                        <p class="p-2 text-xs text-gray-500">Role: <?php echo htmlspecialchars($student_id); ?></p>
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

  <!-- Manage Categories Content -->
  <section class="mt-6 p-6 lg:p-0">
            <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-lg font-semibold mb-4">Manage Categories</h3>
                <button id="openPopup" class="bg-[#D2171E] text-white px-4 py-2 rounded">Add New Category</button>
                <div class="w-full overflow-x-auto">
                <table class="w-full mt-4 border-collapse border border-gray-300 min-w-max">
                    <thead>
                        <tr class="bg-gray-200 dark:bg-gray-700">
                            <th class="border border-gray-300 px-4 py-2">ID</th>
                            <th class="border border-gray-300 px-4 py-2">Category</th>
                            <th class="border border-gray-300 px-4 py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Fetch all categories from the database
                        $query = "SELECT category_id, category_name FROM categories ORDER BY created_at ASC";
                        $result = $conn->query($query);
                    
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td class='border border-gray-300 px-4 py-2 text-center'>" . $row['category_id'] . "</td>";
                                echo "<td class='border border-gray-300 px-4 py-2 text-center'>" . htmlspecialchars($row['category_name']) . "</td>";
                                echo "<td class='border border-gray-300 px-4 py-2 text-center flex justify-center gap-2'>";
                                // Update Button inside PHP echo statement
                                echo "<button onclick='openEditModal(" . $row['category_id'] . ", \"" . htmlspecialchars($row['category_name']) . "\")' class='bg-[#D2171E] text-white px-2 py-1 rounded'><i data-lucide='edit' class='w-4 h-4'></i></button>";
                                // Delete Button inside PHP echo statement
                                echo "<button class='bg-[#D2171E] text-white px-2 py-1 rounded' onclick='confirmDelete(" . $row['category_id'] . ")'><i data-lucide='trash-2' class='w-4 h-4'></i></button>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='3' class='border border-gray-300 px-4 py-2 text-center'>No categories found</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
                </div>
            </div>
        </section>
    </main>

     <!-- Popup Form for Adding New Category -->
     <div id="popupForm" class="popup-form">
        <div class="popup-content bg-white p-6 rounded-lg">
            <h3 class="text-lg font-semibold mb-4">Create New Category</h3>
            <form action="backend/category_submit.php" method="POST">
                <label class="block mb-2">Category Name:</label>
                <input type="text" name="category_name" placeholder="Enter Category Name" required class="w-full p-2 border rounded mb-4 focus:outline-none bg-gray-100 dark:bg-gray-800 dark:text-white">
                <div class="flex justify-end">
                    <button type="button" id="closePopup" class="bg-gray-400 text-white px-4 py-2 rounded mr-2">Cancel</button>
                    <button type="submit" name="submit" id="categoryForm" class="bg-[#D2171E] text-white px-4 py-2 rounded">Submit</button>
                </div>
            </form>
        </div>
    </div>

<!-- Edit Category Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex justify-center items-center w-full h-full p-4">
    <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 w-full sm:w-1/2 md:w-1/3 max-w-lg mx-2">
        <h2 class="text-xl font-semibold mb-4 dark:text-white">Edit Category</h2>
        <form action="backend/category_submit.php" method="POST">
            <input type="hidden" id="edit_category_id" name="category_id">
            
            <label for="edit_category_name" class="block text-sm font-medium text-gray-700 dark:text-white">Category Name:</label>
            <input type="text" id="edit_category_name" name="category_name" class="w-full px-3 py-2 border border-gray-300 rounded mt-1 focus:outline-none bg-gray-100 dark:bg-gray-800 dark:text-white" required>

            <div class="flex justify-end gap-2 mt-4">
                <button type="button" onclick="closeEditModal()" class="bg-gray-400 text-white px-4 py-2 rounded">Cancel</button>
                <button type="submit" name="update" class="bg-red-600 text-white px-4 py-2 rounded">Update</button>
            </div>
        </form>
    </div>
</div>
    
     <!-- Toast Notification -->
     <div id="toast" class="toast">Category added successfully!</div>

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

    // Popup Form for Adding New Category
       // Popup Form Toggle
       document.getElementById("openPopup").addEventListener("click", function () {
            document.getElementById("popupForm").classList.add("active");
        });
        document.getElementById("closePopup").addEventListener("click", function () {
            document.getElementById("popupForm").classList.remove("active");
        });

        // Handle form submission
        document.getElementById("categoryForm").addEventListener("submit", function (e) {
            e.preventDefault();
            document.getElementById("popupForm").classList.remove("active");
            
            // Show toast
            let toast = document.getElementById("toast");
            toast.style.display = "block";
            setTimeout(() => {
                toast.style.display = "none";
            }, 1000);
        });

        // Function to open the edit modal
        function openEditModal(category_id, name) {
    document.getElementById("edit_category_id").value = category_id;
    document.getElementById("edit_category_name").value = name;
    document.getElementById("editModal").classList.remove("hidden");

    // Add event listener for Escape key
    document.addEventListener("keydown", handleEscapeKey);
}

function closeEditModal() {
    document.getElementById("editModal").classList.add("hidden");

    // Remove event listener after modal is closed
    document.removeEventListener("keydown", handleEscapeKey);
}

function handleEscapeKey(event) {
    if (event.key === "Escape") {
        closeEditModal();
    }
}


// Show Success Alert on Update
function showUpdateAlert() {
    Swal.fire({
        position: 'bottom-start',
        icon: 'success',
        title: 'Category updated successfully!',
        showConfirmButton: false,
        timer: 2000
    });
    return true;
}

// Confirm Delete Alert
function confirmDelete(categoryId) {
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
            window.location.href = 'backend/category_submit.php?delete=' + categoryId;
            
            setTimeout(() => {
                Swal.fire({
                    position: 'bottom-start',
                    icon: 'success',
                    title: 'Category deleted successfully!',
                    showConfirmButton: false,
                    timer: 2000
                });
            }, 500);
        }
    });
}
</script>

</body>
</html>
