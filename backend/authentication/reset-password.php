<?php
require '../config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $token = $_POST['token'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    if ($newPassword !== $confirmPassword) {
        echo "<script>
                window.onload = function() {
                    Toastify({
                        text: '❌ Passwords do not match!',
                        duration: 3000,
                        gravity: 'top',
                        position: 'right',
                        backgroundColor: '#dc3545',
                        close: true
                    }).showToast();
                    setTimeout(() => window.history.back(), 3000);
                };
              </script>";
        exit();
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

    // Validate token
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $userId = $row['user_id'];

        // Update password & clear token
        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = ?");
        $stmt->bind_param("si", $hashedPassword, $userId);
        $stmt->execute();

        echo "<script>
                window.onload = function() {
                    Toastify({
                        text: '✅ Password has been reset successfully!',
                        duration: 3000,
                        gravity: 'top',
                        position: 'right',
                        backgroundColor: '#28a745',
                        close: true
                    }).showToast();
                    setTimeout(() => window.location.href='../../login.php', 3000);
                };
              </script>";
    } else {
        echo "<script>
                window.onload = function() {
                    Toastify({
                        text: '❌ Invalid or expired token!',
                        duration: 3000,
                        gravity: 'top',
                        position: 'right',
                        backgroundColor: '#dc3545',
                        close: true
                    }).showToast();
                    setTimeout(() => window.history.back(), 3000);
                };
              </script>";
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - StenoPlus</title>
    <!-- Favicon -->
    <link rel="icon" href="../../assets/images/favicon.ico" type="image/x-icon" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen bg-gray-100">
    <div class="bg-white p-6 sm:p-8 rounded-lg shadow-lg max-w-md w-full">
        <div class="flex justify-center mb-4">
            <img src="../../assets/images/stenoplus-logo.png" alt="StenoPlus Logo" class="h-12">
        </div>
        <h2 class="text-2xl font-semibold text-[#002147] text-center">Reset Your Password</h2>
        <p class="text-gray-600 text-sm text-center mb-4">Enter your new password below</p>
        
        <form id="resetForm" action="reset-password.php" method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>">

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-medium mb-1">New Password</label>
                <div class="relative">
                    <input type="password" id="new_password" name="new_password" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-[#D2171E] focus:border-[#D2171E]" placeholder="Enter New Password" required>
                    <span class="absolute right-3 top-3 cursor-pointer text-gray-500" onclick="togglePassword('new_password')">👁️</span>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-medium mb-1">Confirm Password</label>
                <div class="relative">
                    <input type="password" id="confirm_password" name="confirm_password" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-[#D2171E] focus:border-[#D2171E]" placeholder="Confirm Password" required>
                    <span class="absolute right-3 top-3 cursor-pointer text-gray-500" onclick="togglePassword('confirm_password')">👁️</span>
                </div>
            </div>

            <button type="submit" class="w-full bg-[#D2171E] hover:bg-red-700 text-white font-medium py-3 rounded-lg transition duration-200">Reset Password</button>
        </form>
    </div>

    <script>
        function togglePassword(id) {
            let input = document.getElementById(id);
            input.type = input.type === "password" ? "text" : "password";
        }

        function showToast(message, type) {
            Toastify({
                text: message,
                duration: 3000,
                close: true,
                gravity: "top",
                position: "right",
                backgroundColor: type === "success" ? "#28a745" : "#dc3545",
                stopOnFocus: true,
            }).showToast();
        }

        document.getElementById("resetForm").addEventListener("submit", function(event) {
            let newPassword = document.getElementById("new_password").value;
            let confirmPassword = document.getElementById("confirm_password").value;

            if (newPassword !== confirmPassword) {
                showToast("❌ Passwords do not match!", "error");
                event.preventDefault();
            }
        });
    </script>
</body>
</html>

