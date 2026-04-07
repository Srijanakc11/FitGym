<?php
session_start();
require_once __DIR__ . '/dynamic_content.php';
require_once __DIR__ . '/payment_helpers.php';

fitgym_bootstrap_booking_payment_columns();

if (!function_exists('fitgym_khalti_callback_payment_status')) {
    function fitgym_khalti_callback_payment_status(string $value): string
    {
        $normalized = strtolower(trim($value));
        return match ($normalized) {
            'completed' => 'paid',
            'pending', 'initiated' => $normalized,
            'refunded' => 'refunded',
            'expired' => 'expired',
            'user canceled', 'cancelled', 'canceled' => 'cancelled',
            default => 'failed',
        };
    }
}

if (!function_exists('fitgym_khalti_callback_page_state')) {
    function fitgym_khalti_callback_page_state(string $paymentStatus): array
    {
        return match ($paymentStatus) {
            'paid' => [
                'variant' => 'success',
                'title' => 'Payment Verified',
                'message' => 'Your Khalti payment was confirmed and the booking is now locked in.',
            ],
            'pending', 'initiated' => [
                'variant' => 'pending',
                'title' => 'Payment Still Processing',
                'message' => 'Khalti has not marked this transaction as complete yet. Your slot is temporarily held while the payment remains active.',
            ],
            'refunded' => [
                'variant' => 'warning',
                'title' => 'Payment Refunded',
                'message' => 'This transaction was refunded, so the booking is no longer active.',
            ],
            'cancelled', 'expired' => [
                'variant' => 'danger',
                'title' => 'Payment Not Completed',
                'message' => 'The Khalti checkout was cancelled or expired before payment completed.',
            ],
            default => [
                'variant' => 'danger',
                'title' => 'Payment Verification Failed',
                'message' => 'We could not verify the Khalti transaction for this booking.',
            ],
        };
    }
}

$pidx = trim((string)($_GET['pidx'] ?? ''));
$purchaseOrderId = trim((string)($_GET['purchase_order_id'] ?? ''));
$callbackStatus = trim((string)($_GET['status'] ?? ''));
$callbackTransactionId = trim((string)($_GET['transaction_id'] ?? $_GET['txnId'] ?? $_GET['tidx'] ?? ''));

$booking = fitgym_get_booking_by_payment_reference($purchaseOrderId, $pidx);
$classRow = null;
$paymentSnapshot = [
    'provider_label' => 'Khalti',
    'method_label' => 'Khalti',
    'status_label' => 'Verification required',
    'status_detail' => 'The payment response could not be matched to a booking.',
    'order_id' => $purchaseOrderId,
    'pidx' => $pidx,
    'transaction_id' => $callbackTransactionId,
];
$pageState = fitgym_khalti_callback_page_state('failed');
$pageNote = '';
$classPriceLabel = '';

if ($booking) {
    $classRow = fitgym_get_class_by_slug((string)($booking['class_slug'] ?? ''));
    $lookupResponse = $pidx !== '' ? fitgym_khalti_lookup_payment($pidx) : [
        'ok' => false,
        'body' => [],
        'error' => 'Missing Khalti payment identifier.',
    ];
    $lookupBody = is_array($lookupResponse['body'] ?? null) ? $lookupResponse['body'] : [];
    if (!empty($lookupResponse['ok'])) {
        $resolvedPaymentStatus = fitgym_khalti_callback_payment_status((string)($lookupBody['status'] ?? $callbackStatus));
    } else {
        $fallbackStatus = fitgym_khalti_callback_payment_status($callbackStatus);
        $resolvedPaymentStatus = in_array($fallbackStatus, ['cancelled', 'expired', 'pending', 'initiated'], true)
            ? $fallbackStatus
            : 'failed';
    }
    $verificationTimestamp = date('Y-m-d H:i:s');

    $paymentFields = [
        'payment_provider' => 'khalti',
        'payment_method' => 'khalti',
        'payment_status' => $resolvedPaymentStatus,
        'payment_pidx' => $pidx,
        'payment_transaction_id' => trim((string)($lookupBody['transaction_id'] ?? $callbackTransactionId)),
        'payment_verified_at' => $verificationTimestamp,
        'payment_response_json' => json_encode([
            'callback' => $_GET,
            'lookup' => $lookupBody,
        ], JSON_UNESCAPED_SLASHES),
    ];

    if ($resolvedPaymentStatus === 'paid') {
        $paymentFields['payment_completed_at'] = $verificationTimestamp;
        $paymentFields['status'] = 'Confirmed';
        $_SESSION['booking_success_flash'] = [
            'class_slug' => (string)($booking['class_slug'] ?? ''),
            'message' => 'Your Khalti payment was verified and the class booking was confirmed automatically.',
        ];
        $_SESSION['client_profile_flash'] = 'Your Khalti payment was verified and the booking was confirmed automatically.';
    } elseif (in_array($resolvedPaymentStatus, ['cancelled', 'expired', 'refunded', 'failed'], true)) {
        $paymentFields['status'] = 'Cancelled';
    } else {
        $paymentFields['status'] = 'Pending';
    }

    fitgym_update_booking_payment_fields((int)$booking['id'], $paymentFields);
    $booking = array_merge($booking, $paymentFields);
    if ($resolvedPaymentStatus === 'paid') {
        fitgym_send_booking_payment_success_email($booking);
    }
    $paymentSnapshot = fitgym_booking_payment_snapshot($booking);
    $pageState = fitgym_khalti_callback_page_state((string)$paymentSnapshot['status']);
    $classPriceLabel = fitgym_price_label_from_rupees((int)round(((int)($booking['payment_amount_paisa'] ?? 0)) / 100));

    if (empty($lookupResponse['ok'])) {
        $pageNote = trim((string)($lookupResponse['error'] ?? ''));
        if ($pageNote === '' && $lookupBody !== []) {
            foreach ($lookupBody as $value) {
                if (is_array($value) && isset($value[0]) && is_string($value[0]) && trim($value[0]) !== '') {
                    $pageNote = trim($value[0]);
                    break;
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Khalti Payment Status | FitGym</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="<?= fitgym_esc(fitgym_asset_url('/pictures/favicon.png')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/header.css')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/footer.css')) ?>">
    <link rel="stylesheet" href="<?= fitgym_esc(fitgym_asset_url('/css/payment.css')) ?>">
</head>
<body>
<?php include "header.php"; ?>

<main class="payment-page">
    <section class="payment-hero payment-hero-khalti">
        <div class="hero-inner">
            <span class="hero-pill">Khalti Sandbox</span>
            <h1><?= fitgym_esc($pageState['title']) ?></h1>
            <p><?= fitgym_esc($pageState['message']) ?></p>
        </div>
    </section>

    <section class="payment-shell">
        <div class="payment-card <?= fitgym_esc($pageState['variant']) ?>">
            <div class="provider">
                <div class="provider-logo provider-logo-text">Khalti</div>
            </div>

            <?php if ($booking): ?>
                <div class="status-banner <?= fitgym_esc($pageState['variant']) ?>">
                    <strong><?= fitgym_esc($paymentSnapshot['status_label']) ?></strong>
                    <p><?= fitgym_esc($paymentSnapshot['status_detail']) ?></p>
                </div>

                <div class="summary">
                    <h3>Booking Summary</h3>
                    <div class="summary-row">
                        <span>Class</span>
                        <strong><?= fitgym_esc((string)($booking['class_name'] ?? '')) ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Trainer</span>
                        <strong><?= fitgym_esc((string)($booking['trainer_name'] ?? 'TBA')) ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Date</span>
                        <strong><?= fitgym_esc((string)($booking['preferred_date'] ?? '')) ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Time Slot</span>
                        <strong><?= fitgym_esc((string)($booking['time_slot'] ?? '')) ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Seats</span>
                        <strong><?= fitgym_esc((string)($booking['participants'] ?? '1')) ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Booking Status</span>
                        <strong><?= fitgym_esc((string)($booking['status'] ?? 'Pending')) ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Payment Method</span>
                        <strong><?= fitgym_esc($paymentSnapshot['method_label']) ?></strong>
                    </div>
                    <div class="summary-row total">
                        <span>Total</span>
                        <strong><?= fitgym_esc($classPriceLabel !== '' ? $classPriceLabel : 'NPR 0') ?></strong>
                    </div>
                </div>

                <div class="payment-reference-grid">
                    <div class="reference-card">
                        <span>Order ID</span>
                        <strong><?= fitgym_esc($paymentSnapshot['order_id'] !== '' ? $paymentSnapshot['order_id'] : '-') ?></strong>
                    </div>
                    <div class="reference-card">
                        <span>PIDX</span>
                        <strong><?= fitgym_esc($paymentSnapshot['pidx'] !== '' ? $paymentSnapshot['pidx'] : '-') ?></strong>
                    </div>
                    <div class="reference-card">
                        <span>Transaction ID</span>
                        <strong><?= fitgym_esc($paymentSnapshot['transaction_id'] !== '' ? $paymentSnapshot['transaction_id'] : '-') ?></strong>
                    </div>
                    <div class="reference-card">
                        <span>Location</span>
                        <strong><?= fitgym_esc((string)($classRow['location'] ?? 'Main Studio')) ?></strong>
                    </div>
                </div>

                <?php if ($pageNote !== ''): ?>
                    <div class="payment-note">
                        <strong>Verification note</strong>
                        <p><?= fitgym_esc($pageNote) ?></p>
                    </div>
                <?php endif; ?>

                <div class="actions">
                    <?php if (($paymentSnapshot['status'] ?? '') === 'paid'): ?>
                        <a class="primary-btn" href="<?= fitgym_esc(fitgym_url('/php/client/dashboard.php')) ?>">Open Profile</a>
                        <a class="secondary-btn" href="<?= fitgym_esc(fitgym_url('/php/classes.php')) ?>">Browse More Classes</a>
                    <?php else: ?>
                        <a class="primary-btn" href="<?= fitgym_esc(fitgym_url('/php/book_class.php')) ?>?class=<?= fitgym_esc((string)($booking['class_slug'] ?? '')) ?>">Try Booking Again</a>
                        <a class="secondary-btn" href="<?= fitgym_esc(fitgym_url('/php/client/dashboard.php')) ?>">Open Profile</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="status-banner danger">
                    <strong>Booking not found</strong>
                    <p>We could not match the Khalti response to any booking in FitGym.</p>
                </div>
                <div class="payment-note">
                    <strong>Payment reference</strong>
                    <p>Order ID: <?= fitgym_esc($purchaseOrderId !== '' ? $purchaseOrderId : '-') ?></p>
                    <p>PIDX: <?= fitgym_esc($pidx !== '' ? $pidx : '-') ?></p>
                </div>
                <div class="actions">
                    <a class="primary-btn" href="<?= fitgym_esc(fitgym_url('/php/classes.php')) ?>">Browse Classes</a>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include "footer.php"; ?>
</body>
</html>
