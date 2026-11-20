<!-- Minimalist White Site Preloader -->
<div id="sitePreloader" class="site-preloader">
    <div class="preloader-inner">
        <img src="assets/images/logo_nav.png" alt="N Magazine" class="preloader-logo">
        <div class="preloader-bar">
            <div class="preloader-fill">
                <div class="preloader-wave"></div>
            </div>
        </div>
    </div>
</div>
<script>
(function() {
    var p = document.getElementById('sitePreloader');
    if (!p) return;
    var dismissed = false;
    if (window.location.search.indexOf('preview=1') !== -1) {
        return; // Stay open indefinitely for visual inspection
    }
    function dismiss() {
        if (dismissed) return;
        dismissed = true;
        p.classList.add('fade-out');
        setTimeout(function() {
            p.style.display = 'none';
        }, 450);
    }
    if (document.readyState === 'complete') {
        setTimeout(dismiss, 1000);
    } else {
        window.addEventListener('load', function() {
            setTimeout(dismiss, 800);
        });
        setTimeout(dismiss, 2200);
    }
})();
</script>
