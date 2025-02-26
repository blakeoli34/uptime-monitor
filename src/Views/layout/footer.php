</div>
    </main>

    <footer class="footer">
        <div class="content has-text-centered">
            <p>
                <strong><?= $config['app']['name'] ?></strong> by Jointly Studios. &copy; <?php echo date('Y'); ?>. All rights reserved.
            </p>
        </div>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // Get all "navbar-burger" elements
        const $navbarBurgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);

        // Add a click event on each of them
        $navbarBurgers.forEach(el => {
            el.addEventListener('click', () => {
                const target = el.dataset.target;
                const $target = document.getElementById(target);

                el.classList.toggle('is-active');
                $target.classList.toggle('is-active');
            });
        });

        // Auto-hide notifications after 5 seconds
        const notifications = document.querySelectorAll('.notification:not(.is-permanent)');
        notifications.forEach(notification => {
            setTimeout(() => {
                notification.style.display = 'none';
            }, 5000);
        });
    });
    </script>
</body>
</html>