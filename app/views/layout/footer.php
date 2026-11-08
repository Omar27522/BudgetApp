<?php
// views/layout/footer.php
?>
    </main>

    <script>
    (function() {
        var toggle = document.getElementById('mobile-header-toggle');
        if (!toggle) return;
        function toggleOverview() {
            if (window.innerWidth <= 768) {
                var isOpen = document.body.classList.toggle('mobile-overview-open');
                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }
        }
        toggle.addEventListener('click', toggleOverview);
        toggle.addEventListener('keydown', function(e) {
            if (window.innerWidth <= 768 && (e.key === 'Enter' || e.key === ' ')) {
                e.preventDefault();
                toggleOverview();
            }
        });
    })();
    </script>
</body>
</html>

