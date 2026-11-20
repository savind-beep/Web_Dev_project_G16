</main>

<!-- Floating Scroll-To-Top "Go Up" Button -->
<button id="scrollToTopBtn" class="btn-scroll-top" title="Go Up" aria-label="Scroll to top">
    <i class="bi bi-arrow-up-short"></i>
</button>

<!-- Thin Minimalist Footer -->
<footer class="nsbm-footer-thin">
    <div class="container">
        <div class="footer-thin-content">
            <!-- Brand & Copyright -->
            <div class="footer-thin-brand">
                <a href="index.php" class="d-inline-flex align-items-center">
                    <img src="assets/images/logo_nav.png" alt="N Magazine" height="22" class="footer-logo">
                </a>
                <span class="footer-divider">&bull;</span>
                <span class="footer-copy">&copy; <?php echo date("Y"); ?> NSBM NewsDesk &bull; Group 16</span>
            </div>

            <!-- Quick Links Inline -->
            <nav class="footer-thin-links" aria-label="Footer Navigation">
                <a href="index.php">Home</a>
                <a href="index.php?category=academic">Academic</a>
                <a href="index.php?category=events">Events</a>
                <a href="index.php?category=sports">Sports</a>
                <a href="mindscape.php">Mindscape</a>
                <a href="submit_article.php">Submit News</a>
            </nav>

            <!-- Social Links -->
            <div class="footer-thin-socials">
                <a href="https://www.facebook.com/nsbm.lk" target="_blank" rel="noopener noreferrer" title="Facebook"><i class="bi bi-facebook"></i></a>
                <a href="https://www.instagram.com/nsbmgreenuniversity/" target="_blank" rel="noopener noreferrer" title="Instagram"><i class="bi bi-instagram"></i></a>
                <a href="https://www.tiktok.com/@nsbmgreenuniversity" target="_blank" rel="noopener noreferrer" title="TikTok"><i class="bi bi-tiktok"></i></a>
                <a href="https://www.youtube.com/@NSBMGreenUniversityTown/" target="_blank" rel="noopener noreferrer" title="YouTube"><i class="bi bi-youtube"></i></a>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 JS Bundle (with Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JavaScript -->
<script src="assets/js/main.js"></script>
<!-- Dark Mode Toggle Script -->
<script>
(function () {
    const html = document.documentElement;
    const btn  = document.getElementById('darkModeToggle');
    const icon = document.getElementById('darkModeIcon');

    // Apply stored theme immediately (no flash)
    const stored = localStorage.getItem('nsbm-theme') || 'light';
    html.setAttribute('data-theme', stored);
    updateIcon(stored);

    if (btn) {
        btn.addEventListener('click', function () {
            const current = html.getAttribute('data-theme');
            const next    = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('nsbm-theme', next);
            updateIcon(next);
        });
    }

    function updateIcon(theme) {
        if (!icon) return;
        if (theme === 'dark') {
            icon.className = 'bi bi-sun-fill';
            icon.style.color = '#00ff88';
        } else {
            icon.className = 'bi bi-moon-stars-fill';
            icon.style.color = '';
        }
    }
})();
</script>
</body>
</html>
