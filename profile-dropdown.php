<?php
// profile-dropdown.php
if (!isset($_SESSION['user_id'])) {
    return; // Exit if no user session
}

require 'backend/config.php';

// Fetch user data including profile picture
$user_id = $_SESSION['user_id'];
$query = "SELECT profile_picture, student_id FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<div class="relative">
    <img src="<?= !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : 'assets/images/student.png' ?>" 
         alt="Profile" class="w-10 h-10 rounded-full cursor-pointer" id="profileBtn">
    <div id="profileDropdown" class="hidden absolute right-0 bg-white shadow-lg rounded-md w-40 mt-2 dark:bg-gray-700 z-50">
        <p class="p-2 text-sm dark:text-white"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></p>
        <p class="p-2 text-xs text-gray-500 dark:text-gray-300">Student ID: <?= htmlspecialchars($user['student_id'] ?? '') ?></p>
        <hr class="dark:border-gray-600">
        <li class="flex items-center space-x-2 p-2 text-sm hover:bg-gray-200 dark:hover:bg-gray-600">
            <i data-lucide="user" class="mr-0 w-4 h-4"></i> 
            <a href="my-profile.php" class="dark:text-white">My Profile</a>
        </li>
        <li class="flex items-center space-x-2 p-2 hover:bg-red-600 hover:text-white rounded-b text-sm">
            <i data-lucide="log-out" class="mr-0 w-4 h-4"></i> 
            <a href="backend/authentication/logout.php" class="dark:text-white">Logout</a>
        </li>
    </div>
</div>

<script>
// Add dropdown toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const profileBtn = document.getElementById('profileBtn');
    const dropdown = document.getElementById('profileDropdown');
    
    if (profileBtn && dropdown) {
        profileBtn.addEventListener('click', function() {
            dropdown.classList.toggle('hidden');
        });
        
        // Close when clicking outside
        document.addEventListener('click', function(event) {
            if (!profileBtn.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });
    }
});
</script>