<?php
require_once __DIR__ . '/partials/header.php';

$message = '';
$error = '';

if (!function_exists('admin_setting_value')) {
    function admin_setting_value(array $settingsMap, string $key, string $default = ''): string
    {
        return isset($settingsMap[$key]) ? (string)$settingsMap[$key] : $default;
    }
}

if ($conn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_group';

    if ($action === 'delete') {
        $settingKey = trim((string)($_POST['setting_key'] ?? ''));
        if ($settingKey !== '') {
            $stmt = $conn->prepare("DELETE FROM settings WHERE setting_key = ?");
            if ($stmt) {
                $stmt->bind_param('s', $settingKey);
                $stmt->execute();
                $stmt->close();
                $message = 'Setting deleted.';
            }
        }
    } elseif ($action === 'save_custom') {
        $settingKey = trim((string)($_POST['setting_key'] ?? ''));
        $settingValue = trim((string)($_POST['setting_value'] ?? ''));

        if ($settingKey === '') {
            $error = 'Setting key is required.';
        } else {
            $stmt = $conn->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
            if ($stmt) {
                $stmt->bind_param('ss', $settingKey, $settingValue);
                $stmt->execute();
                $stmt->close();
                $message = 'Custom setting saved.';
            }
        }
    } elseif ($action === 'save_group') {
        $allowedKeys = [
            'site_name',
            'header_brand_title',
            'footer_tagline',
            'contact_address',
            'contact_phone',
            'contact_email',
            'contact_hours',
            'footer_map_embed_url',
            'home_hero_cta_label',
            'home_hero_cta_url',
            'home_featured_title',
            'classes_recommend_cta_label',
            'classes_recommend_cta_url',
            'default_class_location',
            'default_class_time',
            'class_images_json',
            'class_descriptions_json',
            'class_locations_json',
            'class_times_json',
            'khalti_environment',
            'khalti_secret_key',
            'khalti_public_key',
            'mail_smtp_host',
            'mail_smtp_port',
            'mail_smtp_secure',
            'mail_smtp_username',
            'mail_smtp_password',
            'mail_from_email',
            'mail_from_name',
            'mail_reply_to_email',
            'mail_admin_notification_email',
            'mail_test_recipient',
        ];

        $stmt = $conn->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
        if ($stmt) {
            foreach ($allowedKeys as $key) {
                if (!array_key_exists($key, $_POST)) {
                    continue;
                }

                $value = trim((string)$_POST[$key]);
                if (str_ends_with($key, '_json') && $value !== '') {
                    json_decode($value, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $error = "Invalid JSON provided for {$key}.";
                        break;
                    }
                }

                $stmt->bind_param('ss', $key, $value);
                $stmt->execute();
            }
            $stmt->close();

            if ($error === '') {
                $message = 'Settings updated successfully.';
            }
        }
    }
}

$settingsRows = $conn ? $conn->query("SELECT * FROM settings ORDER BY setting_key ASC") : false;
$settingsMap = [];
$settingsList = [];
if ($settingsRows) {
    while ($row = $settingsRows->fetch_assoc()) {
        $settingsMap[(string)$row['setting_key']] = (string)$row['setting_value'];
        $settingsList[] = $row;
    }
}
?>

<div class="page-header-row">
    <h2>Settings & Configuration</h2>
    <div class="page-actions">
        <button type="button" class="btn-primary panel-toggle" data-panel="advancedSettingsPanel" aria-expanded="false">Advanced Key/Value</button>
    </div>
</div>

<?php if ($message !== ''): ?>
    <div class="alert"><?= esc($message) ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert" style="background:#ffe9e9;color:#7a1212;border-color:#f0b7b7;"><?= esc($error) ?></div>
<?php endif; ?>

<div class="section-stack">
    <div class="card">
        <div class="card-head">
            <h3>Brand Settings</h3>
            <p class="admin-note">Control the main site name and footer tagline.</p>
        </div>
        <form method="POST" class="form-grid">
            <input type="hidden" name="action" value="save_group">
            <label>Site Name
                <input name="site_name" value="<?= esc(admin_setting_value($settingsMap, 'site_name', 'FitGym')) ?>">
            </label>
            <label>Header Brand Title
                <input name="header_brand_title" value="<?= esc(admin_setting_value($settingsMap, 'header_brand_title', admin_setting_value($settingsMap, 'site_name', 'FitGym'))) ?>">
            </label>
            <label class="full-span">Footer Tagline
                <input name="footer_tagline" value="<?= esc(admin_setting_value($settingsMap, 'footer_tagline', 'Your trusted partner for strength, wellness, and transformation.')) ?>">
            </label>
            <div class="full-span">
                <button class="btn-primary" type="submit">Save Brand Settings</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-head">
            <h3>Contact & Footer</h3>
            <p class="admin-note">Update business contact details used across contact and footer sections.</p>
        </div>
        <form method="POST" class="form-grid">
            <input type="hidden" name="action" value="save_group">
            <label>Address
                <input name="contact_address" value="<?= esc(admin_setting_value($settingsMap, 'contact_address', 'Lalitpur, Nepal')) ?>">
            </label>
            <label>Phone
                <input name="contact_phone" value="<?= esc(admin_setting_value($settingsMap, 'contact_phone', '+977-9845673217')) ?>">
            </label>
            <label>Email
                <input name="contact_email" value="<?= esc(admin_setting_value($settingsMap, 'contact_email', 'info@fitgymcenter.com')) ?>">
            </label>
            <label>Business Hours
                <input name="contact_hours" value="<?= esc(admin_setting_value($settingsMap, 'contact_hours', '5 AM-10 PM')) ?>">
            </label>
            <label class="full-span">Google Map Embed URL
                <textarea name="footer_map_embed_url" rows="3"><?= esc(admin_setting_value($settingsMap, 'footer_map_embed_url', 'https://www.google.com/maps?q=Lalitpur,+Nepal&output=embed')) ?></textarea>
            </label>
            <div class="full-span">
                <button class="btn-primary" type="submit">Save Contact Settings</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-head">
            <h3>Homepage & CTA</h3>
            <p class="admin-note">Control the main call-to-action buttons and homepage section label.</p>
        </div>
        <form method="POST" class="form-grid">
            <input type="hidden" name="action" value="save_group">
            <label>Hero Button Label
                <input name="home_hero_cta_label" value="<?= esc(admin_setting_value($settingsMap, 'home_hero_cta_label', 'Explore Classes')) ?>">
            </label>
            <label>Hero Button URL
                <input name="home_hero_cta_url" value="<?= esc(admin_setting_value($settingsMap, 'home_hero_cta_url', fitgym_url('/php/classes.php'))) ?>">
            </label>
            <label>Recommended Section Title
                <input name="home_featured_title" value="<?= esc(admin_setting_value($settingsMap, 'home_featured_title', 'Most Recommended Classes')) ?>">
            </label>
            <label>Classes CTA Label
                <input name="classes_recommend_cta_label" value="<?= esc(admin_setting_value($settingsMap, 'classes_recommend_cta_label', 'Get Recommendations')) ?>">
            </label>
            <label class="full-span">Classes CTA URL
                <input name="classes_recommend_cta_url" value="<?= esc(admin_setting_value($settingsMap, 'classes_recommend_cta_url', fitgym_url('/php/recommend.php'))) ?>">
            </label>
            <div class="full-span">
                <button class="btn-primary" type="submit">Save Homepage Settings</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-head">
            <h3>Payment Settings</h3>
            <p class="admin-note">Khalti web checkout now replaces the old demo online flow. Use your sandbox live secret key from test-admin.khalti.com.</p>
        </div>
        <form method="POST" class="form-grid">
            <input type="hidden" name="action" value="save_group">
            <label>Khalti Environment
                <select name="khalti_environment">
                    <?php $khaltiEnvironment = admin_setting_value($settingsMap, 'khalti_environment', 'sandbox'); ?>
                    <option value="sandbox" <?= $khaltiEnvironment === 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
                    <option value="production" <?= $khaltiEnvironment === 'production' ? 'selected' : '' ?>>Production</option>
                </select>
            </label>
            <label class="full-span">Khalti Secret Key
                <input name="khalti_secret_key" type="password" autocomplete="new-password" value="<?= esc(admin_setting_value($settingsMap, 'khalti_secret_key')) ?>">
            </label>
            <label class="full-span">Khalti Public Key
                <input name="khalti_public_key" type="text" value="<?= esc(admin_setting_value($settingsMap, 'khalti_public_key')) ?>">
            </label>
            <div class="full-span">
                <button class="btn-primary" type="submit">Save Payment Settings</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-head">
            <h3>Email Settings</h3>
            <p class="admin-note">PHPMailer uses SMTP. For Gmail, keep the host as smtp.gmail.com and add your Gmail App Password to activate delivery.</p>
        </div>
        <form method="POST" class="form-grid">
            <input type="hidden" name="action" value="save_group">
            <label>SMTP Host
                <input name="mail_smtp_host" value="<?= esc(admin_setting_value($settingsMap, 'mail_smtp_host', 'smtp.gmail.com')) ?>">
            </label>
            <label>SMTP Port
                <input name="mail_smtp_port" value="<?= esc(admin_setting_value($settingsMap, 'mail_smtp_port', '587')) ?>">
            </label>
            <label>Encryption
                <?php $mailSecure = admin_setting_value($settingsMap, 'mail_smtp_secure', 'tls'); ?>
                <select name="mail_smtp_secure">
                    <option value="tls" <?= $mailSecure === 'tls' ? 'selected' : '' ?>>TLS</option>
                    <option value="ssl" <?= $mailSecure === 'ssl' ? 'selected' : '' ?>>SSL</option>
                </select>
            </label>
            <label>SMTP Username
                <input name="mail_smtp_username" value="<?= esc(admin_setting_value($settingsMap, 'mail_smtp_username', 'srijanaseru@gmail.com')) ?>">
            </label>
            <label class="full-span">SMTP Password / App Password
                <input name="mail_smtp_password" type="password" autocomplete="new-password" value="<?= esc(admin_setting_value($settingsMap, 'mail_smtp_password')) ?>">
            </label>
            <label>From Email
                <input name="mail_from_email" value="<?= esc(admin_setting_value($settingsMap, 'mail_from_email', 'srijanaseru@gmail.com')) ?>">
            </label>
            <label>From Name
                <input name="mail_from_name" value="<?= esc(admin_setting_value($settingsMap, 'mail_from_name', admin_setting_value($settingsMap, 'site_name', 'FitGym'))) ?>">
            </label>
            <label>Reply-To Email
                <input name="mail_reply_to_email" value="<?= esc(admin_setting_value($settingsMap, 'mail_reply_to_email', 'srijanaseru@gmail.com')) ?>">
            </label>
            <label>Admin Copy Email
                <input name="mail_admin_notification_email" value="<?= esc(admin_setting_value($settingsMap, 'mail_admin_notification_email', 'srijanaseru@gmail.com')) ?>">
            </label>
            <label class="full-span">Test Recipient Override
                <input name="mail_test_recipient" value="<?= esc(admin_setting_value($settingsMap, 'mail_test_recipient', '')) ?>">
            </label>
            <div class="full-span">
                <button class="btn-primary" type="submit">Save Email Settings</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-head">
            <h3>Class Defaults</h3>
            <p class="admin-note">Used when class-specific content is missing in the database-driven class pages.</p>
        </div>
        <form method="POST" class="form-grid">
            <input type="hidden" name="action" value="save_group">
            <label>Default Class Location
                <input name="default_class_location" value="<?= esc(admin_setting_value($settingsMap, 'default_class_location', 'Main Studio')) ?>">
            </label>
            <label>Default Class Time
                <input name="default_class_time" value="<?= esc(admin_setting_value($settingsMap, 'default_class_time', 'Schedule on request')) ?>">
            </label>
            <label class="full-span">Class Images JSON
                <textarea name="class_images_json" rows="5"><?= esc(admin_setting_value($settingsMap, 'class_images_json')) ?></textarea>
            </label>
            <label class="full-span">Class Descriptions JSON
                <textarea name="class_descriptions_json" rows="5"><?= esc(admin_setting_value($settingsMap, 'class_descriptions_json')) ?></textarea>
            </label>
            <label class="full-span">Class Locations JSON
                <textarea name="class_locations_json" rows="4"><?= esc(admin_setting_value($settingsMap, 'class_locations_json')) ?></textarea>
            </label>
            <label class="full-span">Class Times JSON
                <textarea name="class_times_json" rows="4"><?= esc(admin_setting_value($settingsMap, 'class_times_json')) ?></textarea>
            </label>
            <div class="full-span">
                <button class="btn-primary" type="submit">Save Class Settings</button>
            </div>
        </form>
    </div>

    <div id="advancedSettingsPanel" class="card collapsible-panel">
        <div class="card-head">
            <h3>Advanced Key / Value Editor</h3>
            <p class="admin-note">Use this only for custom settings not covered by the grouped forms above.</p>
        </div>
        <form method="POST" class="form-grid">
            <input type="hidden" name="action" value="save_custom">
            <label>Setting Key
                <input name="setting_key" required>
            </label>
            <label class="full-span">Setting Value
                <textarea name="setting_value" rows="4" required></textarea>
            </label>
            <div class="full-span">
                <button class="btn-primary" type="submit">Save Custom Setting</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-head">
            <h3>Saved Settings</h3>
            <p class="admin-note">Current settings stored in the database.</p>
        </div>

        <?php if (empty($settingsList)): ?>
            <div class="empty-state">No settings have been saved yet.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Key</th>
                            <th>Value</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($settingsList as $row): ?>
                            <?php
                            $settingKey = (string)$row['setting_key'];
                            $displayValue = (string)$row['setting_value'];
                            if (in_array($settingKey, ['khalti_secret_key', 'khalti_public_key', 'mail_smtp_password'], true) && $displayValue !== '') {
                                $displayValue = str_repeat('*', max(8, strlen($displayValue) - 4)) . substr($displayValue, -4);
                            }
                            ?>
                            <tr>
                                <td><strong><?= esc($settingKey) ?></strong></td>
                                <td><?= nl2br(esc($displayValue)) ?></td>
                                <td class="actions-cell">
                                    <form method="POST" onsubmit="return confirm('Delete this setting?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="setting_key" value="<?= esc($settingKey) ?>">
                                        <button class="btn-danger" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.panel-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            const panel = document.getElementById(button.dataset.panel || '');
            if (!panel) return;
            const open = panel.classList.toggle('is-open');
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });
});
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
