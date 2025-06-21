// Top Navbar and Mobile Menu
document.getElementById("close-topbar").addEventListener("click", function () {
  document.getElementById("top-navbar").style.display = "none";
});

document.getElementById("menu-btn").addEventListener("click", function () {
  let menu = document.getElementById("mobile-menu");
  menu.classList.toggle("hidden");
});

lucide.createIcons();

// Sticky Main Navbar
document.getElementById("close-topbar").addEventListener("click", function () {
  document.getElementById("top-navbar").style.display = "none"; // Hide the top navbar
  document.querySelector("nav").style.top = "0"; // Move main navbar to the top
  document.body.style.paddingTop = "60px"; // Adjust body padding
});