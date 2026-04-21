<?php
$notifications = [];
$unreadCount = 0;
if (isset($conn) && $conn instanceof mysqli) {
    if (!function_exists('fitgym_get_user_notifications')) {
        require_once __DIR__ . '/../../dynamic_content.php';
    }
    $trainerId = (int)($_SESSION['auth_id'] ?? $_SESSION['trainer_id'] ?? 0);
    if ($trainerId > 0) {
        $notifications = fitgym_get_user_notifications($trainerId);
        foreach ($notifications as $n) {
            if (!(int)$n['is_read']) $unreadCount++;
        }
    }
}
?>
<div class="fg-notif-wrapper" style="margin-left: auto;">
    <button class="fg-notif-bell" id="fgNotifBell" aria-label="Notifications">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:24px;height:24px;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
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
                         data-id="<?= $n['id'] ?>">
                        <div class="fg-notif-msg"><?= trainer_esc($n['message']) ?></div>
                        <div class="fg-notif-time"><?= date('M j, g:i A', strtotime($n['created_at'])) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(() => {
    const bellBtn = document.getElementById('fgNotifBell');
    const dropdown = document.getElementById('fgNotifDropdown');
    const notifItems = document.querySelectorAll('.fg-notif-item');

    if (!bellBtn || !dropdown) return;

    bellBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdown.classList.toggle('is-open');
    });

    document.addEventListener('click', (e) => {
        if (!dropdown.contains(e.target) && !bellBtn.contains(e.target)) {
            dropdown.classList.remove('is-open');
        }
    });

    notifItems.forEach(item => {
        item.addEventListener('click', () => {
            if (!item.classList.contains('is-unread')) return;

            const notifId = item.getAttribute('data-id');
            const data = new FormData();
            data.append('id', notifId);

            fetch('<?= trainer_esc(fitgym_url('/php/mark_notif_read.php')) ?>', {
                method: 'POST',
                body: data
            }).then(r => r.json()).then(res => {
                if (res.ok) {
                    item.classList.remove('is-unread');
                    const dot = bellBtn.querySelector('.fg-notif-dot');
                    if (dot) {
                        let count = parseInt(dot.textContent, 10);
                        if (!isNaN(count) && count > 1) {
                            dot.textContent = count - 1;
                        } else {
                            dot.remove();
                        }
                    }
                }
            }).catch(e => console.error(e));
        });
    });
})();
</script>
