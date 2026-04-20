<?php
/**
 * MASTER FOOTER - TIX EVENT
 */
$role = $_SESSION['role'] ?? 'user';
?>

    </main> <!-- Closing <main> -->

    <?php if ($role !== 'user'): ?>
        </div> <!-- Closing .app-wrapper -->
    <?php endif; ?>

    <?php if ($role === 'user'): ?>
        <footer class="py-4 mt-auto border-top border-secondary-subtle">
            <div class="container text-center">
                <small class="text-muted">&copy; <?= date('Y') ?> TIX EVENT - Premium Experience.</small>
            </div>
        </footer>
    <?php endif; ?>

    <!-- JS Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Sidebar Toggle Logic
        const toggleBtn = document.getElementById('toggle-sidebar');
        if (toggleBtn) {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            const icon = document.getElementById('toggle-icon');
            const brandFull = document.querySelector('.brand-full');
            const brandShort = document.querySelector('.brand-short');
            
            const mobileToggle = document.getElementById('mobile-sidebar-toggle');
            const overlay = document.getElementById('sidebar-overlay');
            
            // Check saved state
            const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
            if (isCollapsed && window.innerWidth > 768) {
                applyState(true);
            }

            toggleBtn.addEventListener('click', () => {
                const nowCollapsed = !sidebar.classList.contains('collapsed');
                applyState(nowCollapsed);
                localStorage.setItem('sidebar-collapsed', nowCollapsed);
            });

            // Mobile Toggle
            if (mobileToggle) {
                mobileToggle.addEventListener('click', () => {
                    sidebar.classList.toggle('mobile-show');
                    overlay.classList.toggle('show');
                });
            }

            if (overlay) {
                overlay.addEventListener('click', () => {
                    sidebar.classList.remove('mobile-show');
                    overlay.classList.remove('show');
                });
            }

            function applyState(collapsed) {
                if (collapsed) {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('expanded');
                } else {
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('expanded');
                }
            }
        }
        
        // Remove preload class after a short delay to allow elements to render without transition
        window.addEventListener('load', () => {
            setTimeout(() => {
                document.body.classList.remove('preload');
            }, 100);
        });
    </script>
</body>
</html>
