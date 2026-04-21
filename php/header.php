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

// SweetAlert2 for notifications
if ($currentRole === 'client') {
    echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
}

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

// --- Profile completion popup detection (clients only) ---
$_showProfilePopup = false;
if ($currentRole === 'client') {
    $popupAccountId = (int)($_SESSION['auth_id'] ?? 0);
    if ($popupAccountId > 0 && empty($_SESSION['profile_popup_dismissed'])) {
        // Load profile helpers if not already loaded
        if (!function_exists('fitgym_get_user_fitness_profile')) {
            require_once __DIR__ . '/auth_common.php';
        }
        $popupProfile = fitgym_get_user_fitness_profile($popupAccountId);
        if ($popupProfile === null || (int)($popupProfile['profile_completed'] ?? 0) !== 1) {
            $_showProfilePopup = true;
        }
    }
}

$notifications = [];
$unreadCount = 0;
$headerUserId = $userId ?? (int)($_SESSION['auth_id'] ?? $_SESSION['user_id'] ?? 0);
if ($currentRole === 'client' && $headerUserId > 0) {
    fitgym_generate_class_reminders($headerUserId);
    $notifications = fitgym_get_user_notifications($headerUserId);
    foreach ($notifications as $n) {
        if (!(int)$n['is_read']) $unreadCount++;
    }
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
                <?php if ($currentRole === 'client'): ?>
                    <div class="fg-notif-wrapper">
                        <button class="fg-notif-bell" id="fgNotifBell" aria-label="Notifications">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                            <?php if ($unreadCount > 0): ?>
                                <span class="fg-notif-dot"><?= $unreadCount ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="fg-notif-dropdown" id="fgNotifDropdown">
                            <div class="fg-notif-header">Notifications</div>
                            <div class="fg-notif-list">
                                <?php if (empty($notifications)): ?>
                                    <div class="fg-notif-empty">No new notifications</div>
                                <?php else: ?>
                                    <?php foreach ($notifications as $n): ?>
                                        <div class="fg-notif-item <?= !(int)$n['is_read'] ? 'is-unread' : '' ?>" 
                                             data-id="<?= $n['id'] ?>" 
                                             data-details='<?= fitgym_esc($n['details']) ?>'>
                                            <div class="fg-notif-msg"><?= fitgym_esc($n['message']) ?></div>
                                            <div class="fg-notif-time"><?= date('M j, g:i A', strtotime($n['created_at'])) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

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

    // Notification Dropdown Logic
    const bell = document.getElementById('fgNotifBell');
    const dropdown = document.getElementById('fgNotifDropdown');
    if (bell && dropdown) {
        bell.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('is-open');
        });
        document.addEventListener('click', () => dropdown.classList.remove('is-open'));
        dropdown.addEventListener('click', (e) => e.stopPropagation());

        dropdown.querySelectorAll('.fg-notif-item').forEach(item => {
            item.addEventListener('click', async () => {
                const details = JSON.parse(item.dataset.details);
                const notifId = item.dataset.id;

                Swal.fire({
                    title: details.title,
                    html: `
                        <div style="text-align: left; font-size: 0.95rem; line-height: 1.6;">
                            <p><strong>Date:</strong> ${details.date}</p>
                            <p><strong>Time:</strong> ${details.time}</p>
                            <p><strong>Trainer:</strong> ${details.trainer}</p>
                            <p><strong>Status:</strong> ${details.status}</p>
                            ${details.reason ? `<p><strong>Reason:</strong> ${details.reason}</p>` : ''}
                            <p><strong>Payment:</strong> ${details.payment}</p>
                        </div>
                    `,
                    icon: 'info',
                    confirmButtonColor: '#ff6c1a'
                });

                if (item.classList.contains('is-unread')) {
                    item.classList.remove('is-unread');
                    const dot = document.querySelector('.fg-notif-dot');
                    if (dot) {
                        const count = parseInt(dot.textContent) - 1;
                        if (count <= 0) dot.remove();
                        else dot.textContent = count;
                    }
                    
                    try {
                        await fetch('<?= fitgym_url('/php/mark_notif_read.php') ?>', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `id=${notifId}`
                        });
                    } catch (err) {}
                }
            });
        });
    }
})();
</script>

<?php if ($_showProfilePopup): ?>
<style>
/* ---- Profile Popup ---- */
.fg-profile-overlay {
  position: fixed; inset: 0; z-index: 9000;
  background: rgba(0,0,0,0.65);
  display: flex; align-items: center; justify-content: center;
  padding: 16px;
  animation: fg-fadeIn .25s ease;
}
@keyframes fg-fadeIn { from { opacity:0 } to { opacity:1 } }
.fg-profile-dialog {
  background: #fff;
  border-radius: 16px;
  max-width: 560px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
  padding: 36px 32px 28px;
  box-shadow: 0 20px 60px rgba(0,0,0,.25);
  position: relative;
  animation: fg-slideUp .3s ease;
}
@keyframes fg-slideUp { from { transform:translateY(24px); opacity:0 } to { transform:translateY(0); opacity:1 } }
.fg-profile-dialog h2 {
  margin: 0 0 4px;
  font-size: 1.45rem;
  font-weight: 700;
  color: #111;
}
.fg-profile-dialog .fg-popup-sub {
  color: #666;
  font-size: .93rem;
  margin: 0 0 20px;
}
.fg-profile-badge {
  display: inline-block;
  background: #fff4ee;
  color: #ff6c1a;
  font-size: .75rem;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  padding: 4px 10px;
  border-radius: 999px;
  margin-bottom: 10px;
}
.fg-profile-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px 18px;
}
@media (max-width: 480px) { .fg-profile-grid { grid-template-columns: 1fr; } }
.fg-profile-grid .fg-full { grid-column: 1 / -1; }
.fg-popup-label {
  display: flex;
  flex-direction: column;
  font-size: .88rem;
  font-weight: 600;
  color: #333;
  gap: 5px;
}
.fg-popup-label input,
.fg-popup-label select {
  padding: 9px 12px;
  border: 1.5px solid #ddd;
  border-radius: 8px;
  font-size: .93rem;
  background: #fafafa;
  transition: border-color .2s;
  color: #111;
}
.fg-popup-label input:focus,
.fg-popup-label select:focus {
  outline: none;
  border-color: #ff6c1a;
  background: #fff;
  box-shadow: 0 0 0 3px rgba(255,108,26,.1);
}
.fg-popup-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 22px;
  gap: 12px;
}
.fg-popup-skip {
  font-size: .88rem;
  color: #999;
  cursor: pointer;
  background: none;
  border: none;
  padding: 0;
  text-decoration: underline;
}
.fg-popup-skip:hover { color: #666; }
.fg-popup-submit {
  background: #ff6c1a;
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 11px 28px;
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
  transition: background .2s;
}
.fg-popup-submit:hover { background: #e85f14; }
.fg-popup-submit:disabled { opacity: .6; cursor: not-allowed; }
.fg-popup-error {
  margin-top: 10px;
  color: #c0392b;
  font-size: .87rem;
  display: none;
}
.fg-popup-progress {
  font-size:.82rem; color:#aaa; margin-top:8px; text-align:right;
}
</style>

<div class="fg-profile-overlay" id="fgProfileOverlay" role="dialog" aria-modal="true" aria-labelledby="fgProfileTitle">
  <div class="fg-profile-dialog">
    <span class="fg-profile-badge">One-time setup</span>
    <h2 id="fgProfileTitle">Complete Your Fitness Profile</h2>
    <p class="fg-popup-sub">Help us recommend the best classes for you. Takes less than a minute.</p>

    <form id="fgProfileForm">
      <div class="fg-profile-grid">
        <label class="fg-popup-label">
          Age
          <input type="number" name="age" min="14" max="100" placeholder="e.g. 25" required>
        </label>
        <label class="fg-popup-label">
          Gender
          <select name="gender" required>
            <option value="">Select…</option>
            <option value="female">Female</option>
            <option value="male">Male</option>
          </select>
        </label>
        <label class="fg-popup-label">
          Height (cm)
          <input type="number" name="height_cm" min="100" max="250" placeholder="e.g. 165" step="0.1" required>
        </label>
        <label class="fg-popup-label">
          Weight (kg)
          <input type="number" name="weight_kg" min="20" max="300" placeholder="e.g. 68" step="0.1" required>
        </label>
        <label class="fg-popup-label">
          Activity Level
          <select name="activity" required>
            <option value="">Select…</option>
            <option value="sedentary">Sedentary</option>
            <option value="light">Light</option>
            <option value="moderate">Moderate</option>
            <option value="active">Active</option>
            <option value="very_active">Very Active</option>
          </select>
        </label>
        <label class="fg-popup-label">
          Your Goal
          <select name="goal" required>
            <option value="">Select…</option>
            <option value="fat_loss">Fat Loss</option>
            <option value="muscle_gain">Muscle Gain</option>
            <option value="maintenance">Maintenance</option>
            <option value="endurance">Endurance</option>
            <option value="mobility">Mobility</option>
            <option value="flexibility">Flexibility</option>
            <option value="stress_relief">Stress Relief</option>
          </select>
        </label>
        <label class="fg-popup-label">
          Fitness Level
          <select name="fitness_level" required>
            <option value="">Select…</option>
            <option value="beginner">Beginner</option>
            <option value="intermediate">Intermediate</option>
            <option value="advanced">Advanced</option>
          </select>
        </label>
        <label class="fg-popup-label">
          Training Days / Week
          <input type="number" name="training_days_per_week" min="1" max="7" placeholder="e.g. 4" required>
        </label>
        <label class="fg-popup-label">
          Joint Pain / Need Low Impact?
          <select name="joint_pain" required>
            <option value="">Select…</option>
            <option value="no">No</option>
            <option value="yes">Yes</option>
          </select>
        </label>
        <label class="fg-popup-label">
          Preferred Session Duration (min, optional)
          <input type="number" name="duration_preference" min="15" max="120" step="5" placeholder="e.g. 45">
        </label>
      </div>

      <p class="fg-popup-error" id="fgPopupError"></p>

      <div class="fg-popup-actions">
        <button type="button" class="fg-popup-skip" id="fgPopupSkip">Skip for now</button>
        <button type="submit" class="fg-popup-submit" id="fgPopupSubmit">Save &amp; Get Recommendations</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  const overlay  = document.getElementById('fgProfileOverlay');
  const form     = document.getElementById('fgProfileForm');
  const errorEl  = document.getElementById('fgPopupError');
  const submitBtn = document.getElementById('fgPopupSubmit');
  const skipBtn  = document.getElementById('fgPopupSkip');
  if (!overlay || !form) return;

  function closePopup() {
    overlay.style.opacity = '0';
    overlay.style.transition = 'opacity .2s';
    setTimeout(() => overlay.remove(), 220);
  }

  skipBtn.addEventListener('click', closePopup);

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    errorEl.style.display = 'none';
    submitBtn.disabled = true;
    submitBtn.textContent = 'Saving…';

    const formData = new FormData(form);

    try {
      const res = await fetch('<?= fitgym_esc(fitgym_url('/php/profile_popup_handler.php')) ?>', {
        method: 'POST',
        body: formData,
      });
      const json = await res.json();

      if (json.ok) {
        submitBtn.textContent = 'Saved! ✓';
        submitBtn.style.background = '#27ae60';
        setTimeout(() => {
          closePopup();
          // Reload dashboard to show recommendations
          if (window.location.pathname.includes('/client/dashboard')) {
            window.location.reload();
          }
        }, 700);
      } else {
        const msg = json.errors ? json.errors.join(' ') : (json.error || 'Something went wrong.');
        errorEl.textContent = msg;
        errorEl.style.display = 'block';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save & Get Recommendations';
      }
    } catch (err) {
      errorEl.textContent = 'Network error. Please try again.';
      errorEl.style.display = 'block';
      submitBtn.disabled = false;
      submitBtn.textContent = 'Save & Get Recommendations';
    }
  });
})();
</script>
<?php endif; ?>
