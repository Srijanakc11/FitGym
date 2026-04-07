<?php
require_once __DIR__ . '/dynamic_content.php';

$siteName = fitgym_setting('site_name', 'FitGym Center PVT LTD');
$footerTagline = fitgym_setting('footer_tagline', 'Your trusted partner for strength, wellness, and transformation.');
$footerAddress = fitgym_setting('contact_address', 'Lalitpur, Nepal');
$footerPhone = fitgym_setting('contact_phone', '+977-9845673217');
$footerEmail = fitgym_setting('contact_email', 'info@fitgymcenter.com');
$mapEmbedUrl = fitgym_setting('footer_map_embed_url', 'https://www.google.com/maps?q=Lalitpur,+Nepal&output=embed');
$copyrightText = fitgym_setting('footer_copyright_text', 'Copyright ' . date('Y') . ' ' . $siteName . ' | All Rights Reserved');

$quickLinks = [
    ['label' => 'Home', 'url' => fitgym_url('/index.php')],
    ['label' => 'About Us', 'url' => fitgym_url('/php/about.php')],
    ['label' => 'Classes', 'url' => fitgym_url('/php/classes.php')],
    ['label' => 'Contact', 'url' => fitgym_url('/php/contact.php')],
];
?>

<link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/footer.css')) ?>">

<footer class="footer">
    <div class="footer-container">
        <div class="footer-section">
            <h3><?= fitgym_esc($siteName) ?></h3>
            <p><?= fitgym_esc($footerTagline) ?></p>

            <ul class="contact-info">
                <li><strong>Address:</strong> <?= fitgym_esc($footerAddress) ?></li>
                <li><strong>Phone:</strong> <?= fitgym_esc($footerPhone) ?></li>
                <li><strong>Email:</strong> <?= fitgym_esc($footerEmail) ?></li>
            </ul>
        </div>

        <div class="footer-section">
            <h4>Quick Links</h4>
            <ul class="quick-links">
                <?php foreach ($quickLinks as $link): ?>
                    <?php $label = trim((string)($link['label'] ?? '')); ?>
                    <?php $url = trim((string)($link['url'] ?? '#')); ?>
                    <?php if ($label !== ''): ?>
                        <li><a href="<?= fitgym_esc($url) ?>"><?= fitgym_esc($label) ?></a></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="footer-section map-section">
            <h4>Find Us</h4>
            <iframe src="<?= fitgym_esc($mapEmbedUrl) ?>" allowfullscreen="" loading="lazy"></iframe>
        </div>
    </div>

    <div class="footer-bottom">
        <p><?= fitgym_esc($copyrightText) ?></p>
    </div>
</footer>
