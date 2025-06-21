// Function to create slide groups
function createSlideGroups() {
    container.innerHTML = "";
    const totalSlides = Math.ceil(testimonials.length / cardsPerView);
  
    for (let i = 0; i < totalSlides; i++) {
      const slideGroup = document.createElement("div");
      slideGroup.className = "slide-group grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 p-2";
  
      const startIndex = i * cardsPerView;
      const endIndex = startIndex + cardsPerView;
      testimonials.slice(startIndex, endIndex).forEach((testimonial) => {
        slideGroup.innerHTML += createTestimonialCard(testimonial);
      });
  
      container.appendChild(slideGroup);
    }
  }
  
  // Function to update cards per view
  function updateCardsPerView() {
    const width = window.innerWidth;
    if (width >= 1280) cardsPerView = 4;
    else if (width >= 1024) cardsPerView = 3;
    else if (width >= 768) cardsPerView = 2;
    else cardsPerView = 1;
  
    createSlideGroups();
    initializeDots();
    showSlide(currentIndex);
  }
  
  // Function to initialize dots
  function initializeDots() {
    dotsContainer.innerHTML = "";
    const totalSlides = Math.ceil(testimonials.length / cardsPerView);
  
    for (let i = 0; i < totalSlides; i++) {
      const dot = document.createElement("span");
      dot.className = "dot w-3 h-3 rounded-full bg-[#002147] cursor-pointer";
      dot.addEventListener("click", () => showSlide(i));
      dotsContainer.appendChild(dot);
    }
  }
  
  // Function to show current slide
  function showSlide(index) {
    const totalSlides = Math.ceil(testimonials.length / cardsPerView);
    currentIndex = (index + totalSlides) % totalSlides;
  
    const offset = -currentIndex * 100;
    container.style.transform = `translateX(${offset}%)`;
  
    document.querySelectorAll(".dot").forEach((dot, i) => {
      dot.classList.toggle("active-dot", i === currentIndex);
    });
  }
  
  // Navigation controls
  document.getElementById("prev-btn").addEventListener("click", () => showSlide(currentIndex - 1));
  document.getElementById("next-btn").addEventListener("click", () => showSlide(currentIndex + 1));
  
  // Auto-scroll with hover control
  let scrollInterval = setInterval(() => {
    if (autoScroll) showSlide(currentIndex + 1);
  }, 5000);
  
  container.addEventListener("mouseenter", () => {
    autoScroll = false;
    clearInterval(scrollInterval);
  });
  
  container.addEventListener("mouseleave", () => {
    autoScroll = true;
    scrollInterval = setInterval(() => {
      if (autoScroll) showSlide(currentIndex + 1);
    }, 5000);
  });
  
  // Handle window resize
  window.addEventListener("resize", () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(updateCardsPerView, 250);
  });
  
  // Initial setup
  updateCardsPerView();
  showSlide(0);  