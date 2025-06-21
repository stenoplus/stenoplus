document.addEventListener("DOMContentLoaded", function () {
    // Initial icon creation
    lucide.createIcons();

    // Password toggle functionality
    document.querySelectorAll(".toggle-password").forEach(button => {
        button.addEventListener("click", function () {
            const input = document.getElementById(this.dataset.target);
            const isPassword = input.type === "password";
            
            // Toggle input type
            input.type = isPassword ? "text" : "password";

            // Clear existing icon
            const iconContainer = this.querySelector('.lucide-icon-container');
            iconContainer.innerHTML = '';
            
            // Create new icon
            const icon = document.createElement('i');
            icon.className = 'w-5 h-5 text-gray-600';
            icon.setAttribute('data-lucide', isPassword ? 'eye' : 'eye-off');
            iconContainer.appendChild(icon);
            
            // Recreate icons
            lucide.createIcons();
        });
    });

    // Form switching logic (keep existing)
    document.getElementById("showSignUp").addEventListener("click", function () {
        document.getElementById("loginForm").classList.add("hidden");
        document.getElementById("signUpForm").classList.remove("hidden");
        document.getElementById("forgotPasswordForm").classList.add("hidden");
        document.getElementById("formTitle").innerText = "Sign Up for Steno Plus";
    });

    document.getElementById("showLogin").addEventListener("click", function () {
        document.getElementById("loginForm").classList.remove("hidden");
        document.getElementById("signUpForm").classList.add("hidden");
        document.getElementById("forgotPasswordForm").classList.add("hidden");
        document.getElementById("formTitle").innerText = "Login to Steno Plus";
    });

    document.getElementById("showForgotPassword").addEventListener("click", function () {
        document.getElementById("loginForm").classList.add("hidden");
        document.getElementById("signUpForm").classList.add("hidden");
        document.getElementById("forgotPasswordForm").classList.remove("hidden");
        document.getElementById("formTitle").innerText = "Reset Your Password";
    });

    document.getElementById("showLoginFromForgot").addEventListener("click", function () {
        document.getElementById("loginForm").classList.remove("hidden");
        document.getElementById("signUpForm").classList.add("hidden");
        document.getElementById("forgotPasswordForm").classList.add("hidden");
        document.getElementById("formTitle").innerText = "Login to Steno Plus";
    });
});