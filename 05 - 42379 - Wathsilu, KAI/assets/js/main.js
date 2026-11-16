// ========================================================
// N Magazine - Simple Client-Side Helpers
// ========================================================

document.addEventListener("DOMContentLoaded", function () {
  // Confirmation for delete actions
  const deleteButtons = document.querySelectorAll(".confirm-delete");
  deleteButtons.forEach(function (button) {
    button.addEventListener("click", function (e) {
      const message = this.getAttribute("data-message") || "Are you sure you want to delete this item? This action cannot be undone.";
      if (!confirm(message)) {
        e.preventDefault();
      }
    });
  });

  // Auto-dismiss alert banners after 5 seconds if desired
  const alerts = document.querySelectorAll(".alert-auto-dismiss");
  alerts.forEach(function (alert) {
    setTimeout(function () {
      alert.style.transition = "opacity 0.5s ease";
      alert.style.opacity = "0";
      setTimeout(function () {
        alert.remove();
      }, 500);
    }, 5000);
  });

  // Floating Scroll-to-Top "Go Up" button
  const scrollBtn = document.getElementById("scrollToTopBtn");
  if (scrollBtn) {
    window.addEventListener("scroll", function () {
      if (window.scrollY > 200) {
        scrollBtn.classList.add("show");
      } else {
        scrollBtn.classList.remove("show");
      }
    });

    scrollBtn.addEventListener("click", function () {
      window.scrollTo({
        top: 0,
        behavior: "smooth"
      });
    });
  }

  // Ambient Campus Hero Video Background (Zero Controls, Seamless Infinite Loop)
  const heroVideo = document.getElementById("heroVideo") || document.querySelector(".hero-video-bg");
  if (heroVideo) {
    heroVideo.muted = true;
    heroVideo.defaultMuted = true;
    heroVideo.playsInline = true;

    const playHeroVideo = function () {
      const promise = heroVideo.play();
      if (promise !== undefined) {
        promise.catch(function () {
          const unlock = function () {
            heroVideo.muted = true;
            heroVideo.play().catch(function () {});
            ["click", "touchstart", "scroll", "keydown"].forEach(function (evt) {
              document.removeEventListener(evt, unlock);
            });
          };
          ["click", "touchstart", "scroll", "keydown"].forEach(function (evt) {
            document.addEventListener(evt, unlock, { once: true, passive: true });
          });
        });
      }
    };

    playHeroVideo();
    heroVideo.addEventListener("loadedmetadata", playHeroVideo);
    heroVideo.addEventListener("canplay", playHeroVideo);
    heroVideo.addEventListener("ended", function () {
      heroVideo.currentTime = 0;
      heroVideo.play().catch(function () {});
    });
  }
});

// Helper to replay minimalist preloader for testing if needed
window.replayPreloader = function () {
  var p = document.getElementById("sitePreloader");
  if (!p) return;
  p.classList.remove("fade-out");
  p.style.display = "flex";
  var fill = p.querySelector(".preloader-fill");
  if (fill) {
    fill.style.animation = "none";
    fill.offsetHeight; /* trigger reflow */
    fill.style.animation = "";
  }
  setTimeout(function () {
    p.classList.add("fade-out");
    setTimeout(function () { p.style.display = "none"; }, 400);
  }, 1300);
};



