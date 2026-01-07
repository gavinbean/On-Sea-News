    </main>
    
    <footer class="main-footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= h(SITE_NAME) ?>. All rights reserved.</p>
            <p><a href="<?= baseUrl('/terms.php') ?>">Terms and Conditions</a></p>
        </div>
    </footer>
    
    <!-- Google Ads Bottom Banner -->
    <div class="google-ads-banner">
        <!-- Desktop: Leaderboard (728x90) -->
        <ins class="adsbygoogle"
             style="display:block"
             data-ad-client="ca-pub-XXXXXXXXXXXXXX"
             data-ad-slot="XXXXXXXXXX"
             data-ad-format="auto"
             data-full-width-responsive="true"></ins>
    </div>
    
    <script>
        // Push ad to Google AdSense
        (adsbygoogle = window.adsbygoogle || []).push({});
    </script>
    
    <script src="<?= baseUrl('/js/main.js') ?>"></script>
    <?php if (isset($additionalScripts)): ?>
        <?php foreach ($additionalScripts as $script): ?>
            <script src="<?= h($script) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>

