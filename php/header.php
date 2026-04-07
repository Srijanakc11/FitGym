<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/dynamic_content.php';

if (!function_exists('fitgym_header_is_active')) {
    function fitgym_header_is_active(array $matches, string $currentPage): bool
    {
        foreach ($matches as $match) {
            if ($match === $currentPage) {
                return true;
            }
        }

        return false;
    }
}

$currentPage = basename((string)($_SERVER['PHP_SELF'] ?? ''));
$siteName = fitgym_setting('site_name', 'FitGym');
$brandTitle = fitgym_setting('header_brand_title', $siteName);
$currentRole = (string)($_SESSION['auth_role'] ?? '');
$currentUserName = trim((string)($_SESSION['auth_name'] ?? $_SESSION['user_name'] ?? 'Member'));

$logoPath = fitgym_asset_url('/pictures/favicon.png');
$navLinks = [
    [
        'label' => 'Home',
        'url' => fitgym_url('/index.php'),
        'matches' => ['index.php'],
    ],
    [
        'label' => 'Classes',
        'url' => fitgym_url('/php/classes.php'),
        'matches' => ['classes.php', 'class_inside.php', 'book_class.php'],
    ],
    [
        'label' => 'Recommendations',
        'url' => fitgym_url('/php/recommend.php'),
        'matches' => ['recommend.php'],
    ],
    [
        'label' => 'About',
        'url' => fitgym_url('/php/about.php'),
        'matches' => ['about.php'],
    ],
];

$accountPrimaryLabel = 'Login';
$accountPrimaryUrl = fitgym_url('/php/login.php');
$accountSecondaryLabel = 'Sign Up';
$accountSecondaryUrl = fitgym_url('/php/signup.php');

if ($currentRole === 'admin') {
    $accountPrimaryLabel = $currentUserName !== '' ? $currentUserName : 'Admin';
    $accountPrimaryUrl = fitgym_url('/php/admin/index.php');
    $accountSecondaryLabel = 'Logout';
    $accountSecondaryUrl = fitgym_url('/php/logout.php');
} elseif ($currentRole === 'trainer') {
    $accountPrimaryLabel = $currentUserName !== '' ? $currentUserName : 'Trainer';
    $accountPrimaryUrl = fitgym_url('/php/trainer/dashboard.php');
    $accountSecondaryLabel = 'Logout';
    $accountSecondaryUrl = fitgym_url('/php/logout.php');
} elseif ($currentRole === 'client') {
    $accountPrimaryLabel = $currentUserName !== '' ? $currentUserName : 'Profile';
    $accountPrimaryUrl = fitgym_url('/php/client/dashboard.php');
    $accountSecondaryLabel = 'Logout';
    $accountSecondaryUrl = fitgym_url('/php/logout.php');
}
?>

<header class="fg-site-header">
    <div class="fg-nav-shell">
        <a class="fg-brand" href="<?= fitgym_esc(fitgym_url('/index.php')) ?>" aria-label="<?= fitgym_esc($siteName) ?>">
            <img src="<?= fitgym_esc($logoPath) ?>" alt="" class="fg-brand-mark" aria-hidden="true">
            <span class="fg-brand-copy">
                <strong><?= fitgym_esc($brandTitle) ?></strong>
            </span>
        </a>

        <button
            class="fg-nav-toggle"
            type="button"
            aria-expanded="false"
            aria-controls="fgNavPanel"
            aria-label="Toggle navigation"
        >
            <span></span>
            <span></span>
            <span></span>
        </button>

        <div class="fg-nav-panel" id="fgNavPanel">
            <nav class="fg-primary-nav" aria-label="Primary">
                <?php foreach ($navLinks as $link): ?>
                    <a
                        href="<?= fitgym_esc((string)$link['url']) ?>"
                        class="fg-nav-link <?= fitgym_header_is_active((array)($link['matches'] ?? []), $currentPage) ? 'is-active' : '' ?>"
                    >
                        <?= fitgym_esc((string)$link['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="fg-account-actions">
                <a class="fg-account-link is-primary" href="<?= fitgym_esc($accountPrimaryUrl) ?>">
                    <?= fitgym_esc($accountPrimaryLabel) ?>
                </a>
                <?php if ($accountSecondaryLabel !== '' && $accountSecondaryUrl !== ''): ?>
                    <a class="fg-account-link is-secondary" href="<?= fitgym_esc($accountSecondaryUrl) ?>">
                        <?= fitgym_esc($accountSecondaryLabel) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<script>
(() => {
    const header = document.querySelector('.fg-site-header');
    if (!header) return;

    const toggle = header.querySelector('.fg-nav-toggle');
    const panel = header.querySelector('.fg-nav-panel');
    if (!toggle || !panel) return;

    const closePanel = () => {
        panel.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
    };

    toggle.addEventListener('click', () => {
        const nextState = !panel.classList.contains('is-open');
        panel.classList.toggle('is-open', nextState);
        toggle.setAttribute('aria-expanded', nextState ? 'true' : 'false');
    });

    panel.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 900) {
                closePanel();
            }
        });
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 900) {
            closePanel();
        }
    });
})();
</script>
