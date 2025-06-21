// Testimonial Carousel
let currentIndex = 0;
let autoScroll = true;
let cardsPerView = 1;
const container = document.getElementById("testimonial-container");
const testimonials = document.querySelectorAll(".testimonial-card");
let resizeTimer;

// Update cards per view based on screen size
function updateCardsPerView() {
  const width = window.innerWidth;
  if (width >= 1280) cardsPerView = 4;
  else if (width >= 1024) cardsPerView = 3;
  else if (width >= 768) cardsPerView = 2;
  else cardsPerView = 1;
}

// Handle window resize
window.addEventListener("resize", () => {
  clearTimeout(resizeTimer);
  resizeTimer = setTimeout(() => {
    updateCardsPerView();
    showSlide(currentIndex);
  }, 250);
});

// Show current slide
function showSlide(index) {
  const maxIndex = Math.ceil(testimonials.length / cardsPerView) - 1;
  currentIndex = Math.max(0, Math.min(index, maxIndex));
  const offset = -currentIndex * (100 / cardsPerView);
  container.style.transform = `translateX(${offset}%)`;
}

// Navigation controls
document.getElementById("prev-btn").addEventListener("click", () => {
  showSlide(currentIndex - 1);
});

document.getElementById("next-btn").addEventListener("click", () => {
  showSlide(currentIndex + 1);
});

// Auto-scroll logic
let scrollInterval = setInterval(() => {
  if (autoScroll) {
    const maxIndex = Math.ceil(testimonials.length / cardsPerView);
    showSlide((currentIndex + 1) % maxIndex);
  }
}, 5000);

// Hover control
container.addEventListener("mouseenter", () => {
  autoScroll = false;
  clearInterval(scrollInterval);
});

container.addEventListener("mouseleave", () => {
  autoScroll = true;
  scrollInterval = setInterval(() => {
    const maxIndex = Math.ceil(testimonials.length / cardsPerView);
    showSlide((currentIndex + 1) % maxIndex);
  }, 5000);
});

// Initial setup
updateCardsPerView();
showSlide(0);
// End Testimonial Carousel