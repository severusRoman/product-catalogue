</main>

<footer class="site-footer">
    <div class="container site-footer__inner">
        <p><strong><?= e((string) config('app.name', 'Shelfwise')) ?></strong> is a student project for CSE 472 Web and Internet Programming, Southeast University.</p>
        <nav aria-label="Footer">
            <a href="<?= e(url('index.php')) ?>">Home</a>
            <a href="<?= e(url('catalogue.php')) ?>">Catalogue</a>
            <?php if (current_user() === null): ?>
                <a href="<?= e(url('login.php')) ?>">Log in</a>
            <?php else: ?>
                <a href="<?= e(url('dashboard.php')) ?>">My products</a>
            <?php endif; ?>
        </nav>
    </div>
</footer>
</body>
</html>
