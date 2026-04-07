<?php
require_once __DIR__ . '/dynamic_content.php';

if (!function_exists('fitgym_bootstrap_booking_payment_columns')) {
    function fitgym_bootstrap_booking_payment_columns(): void
    {
        static $bootstrapped = false;
        global $conn;

        if ($bootstrapped || !isset($conn) || !($conn instanceof mysqli)) {
            return;
        }

        $columnDefinitions = [
            'payment_provider' => "ALTER TABLE bookings ADD COLUMN payment_provider VARCHAR(30) NOT NULL DEFAULT 'cash' AFTER payment_method",
            'payment_status' => "ALTER TABLE bookings ADD COLUMN payment_status VARCHAR(30) NOT NULL DEFAULT 'unpaid' AFTER payment_provider",
            'payment_order_id' => "ALTER TABLE bookings ADD COLUMN payment_order_id VARCHAR(120) NULL AFTER payment_status",
            'payment_pidx' => "ALTER TABLE bookings ADD COLUMN payment_pidx VARCHAR(120) NULL AFTER payment_order_id",
            'payment_transaction_id' => "ALTER TABLE bookings ADD COLUMN payment_transaction_id VARCHAR(120) NULL AFTER payment_pidx",
            'payment_amount_paisa' => "ALTER TABLE bookings ADD COLUMN payment_amount_paisa INT NULL AFTER payment_transaction_id",
            'payment_requested_at' => "ALTER TABLE bookings ADD COLUMN payment_requested_at DATETIME NULL AFTER payment_amount_paisa",
            'payment_completed_at' => "ALTER TABLE bookings ADD COLUMN payment_completed_at DATETIME NULL AFTER payment_requested_at",
            'payment_verified_at' => "ALTER TABLE bookings ADD COLUMN payment_verified_at DATETIME NULL AFTER payment_completed_at",
            'payment_response_json' => "ALTER TABLE bookings ADD COLUMN payment_response_json LONGTEXT NULL AFTER payment_verified_at",
        ];

        foreach ($columnDefinitions as $column => $sql) {
            if (!fitgym_table_has_column('bookings', $column)) {
                $conn->query($sql);
            }
        }

        fitgym_reset_table_column_cache();
        $expiryDays = function_exists('fitgym_booking_expiry_days') ? max(1, fitgym_booking_expiry_days()) : 30;

        if (fitgym_table_has_column('bookings', 'payment_method') && fitgym_table_has_column('bookings', 'payment_provider')) {
            $conn->query(
                "UPDATE bookings
                 SET payment_provider = 'khalti'
                 WHERE COALESCE(payment_method, 'cash') = 'khalti'
                   AND COALESCE(payment_provider, 'cash') <> 'khalti'"
            );
            $conn->query(
                "UPDATE bookings
                 SET payment_provider = 'cash'
                 WHERE COALESCE(payment_method, 'cash') = 'cash'
                   AND COALESCE(payment_provider, '') = ''"
            );
        }

        if (fitgym_table_has_column('bookings', 'payment_method')) {
            $conn->query("UPDATE bookings SET payment_method = 'khalti' WHERE payment_method = 'online'");
        }

        if (fitgym_table_has_column('bookings', 'payment_status')) {
            $conn->query(
                "UPDATE bookings
                 SET payment_status = 'cancelled'
                 WHERE COALESCE(status, 'Pending') = 'Cancelled'
                   AND COALESCE(payment_status, '') NOT IN ('cancelled', 'paid', 'refunded')"
            );
            $conn->query(
                "UPDATE bookings
                 SET payment_status = 'paid'
                 WHERE COALESCE(payment_method, 'cash') = 'khalti'
                   AND COALESCE(payment_status, 'unpaid') = 'unpaid'
                   AND payment_pidx IS NULL
                   AND payment_transaction_id IS NULL
                   AND COALESCE(status, 'Pending') <> 'Cancelled'"
            );
            $conn->query(
                "UPDATE bookings
                 SET payment_status = 'unpaid'
                 WHERE COALESCE(payment_method, 'cash') = 'cash'
                   AND (payment_status IS NULL OR payment_status = '')"
            );
        }

        if (fitgym_table_has_column('bookings', 'status') && fitgym_table_has_column('bookings', 'payment_provider') && fitgym_table_has_column('bookings', 'payment_status')) {
            $conn->query(
                "UPDATE bookings
                 SET status = 'Confirmed'
                 WHERE COALESCE(payment_provider, 'cash') = 'khalti'
                   AND COALESCE(payment_status, 'unpaid') = 'paid'
                   AND preferred_date >= DATE_SUB(CURDATE(), INTERVAL {$expiryDays} DAY)
                   AND COALESCE(status, 'Pending') NOT IN ('Confirmed', 'Expired')"
            );
            $conn->query(
                "UPDATE bookings
                 SET status = 'Pending'
                 WHERE COALESCE(payment_provider, 'cash') = 'khalti'
                   AND COALESCE(payment_status, 'unpaid') IN ('initiated', 'pending')
                   AND preferred_date >= DATE_SUB(CURDATE(), INTERVAL {$expiryDays} DAY)
                   AND COALESCE(status, 'Pending') = 'Confirmed'"
            );
            $conn->query(
                "UPDATE bookings
                 SET status = 'Cancelled'
                 WHERE COALESCE(payment_provider, 'cash') = 'khalti'
                   AND COALESCE(payment_status, 'unpaid') IN ('cancelled', 'expired', 'failed', 'refunded')
                   AND COALESCE(status, 'Pending') <> 'Cancelled'"
            );
        }

        if (fitgym_table_has_column('bookings', 'payment_amount_paisa')) {
            $conn->query(
                "UPDATE bookings
                 SET payment_amount_paisa = 200000
                 WHERE payment_amount_paisa IS NULL"
            );
        }

        if (function_exists('fitgym_sync_booking_expiry_statuses')) {
            fitgym_sync_booking_expiry_statuses();
        }

        $bootstrapped = true;
    }
}

if (!function_exists('fitgym_setting_secret')) {
    function fitgym_setting_secret(string $envKey, string $settingKey): string
    {
        $envValue = trim((string)getenv($envKey));
        if ($envValue !== '') {
            return $envValue;
        }

        return trim(fitgym_setting($settingKey, ''));
    }
}

if (!function_exists('fitgym_khalti_environment')) {
    function fitgym_khalti_environment(): string
    {
        $environment = strtolower(trim(fitgym_setting('khalti_environment', 'sandbox')));
        return $environment === 'production' ? 'production' : 'sandbox';
    }
}

if (!function_exists('fitgym_khalti_secret_key')) {
    function fitgym_khalti_secret_key(): string
    {
        return fitgym_setting_secret('FITGYM_KHALTI_SECRET_KEY', 'khalti_secret_key');
    }
}

if (!function_exists('fitgym_khalti_public_key')) {
    function fitgym_khalti_public_key(): string
    {
        return fitgym_setting_secret('FITGYM_KHALTI_PUBLIC_KEY', 'khalti_public_key');
    }
}

if (!function_exists('fitgym_khalti_is_configured')) {
    function fitgym_khalti_is_configured(): bool
    {
        return fitgym_khalti_secret_key() !== '';
    }
}

if (!function_exists('fitgym_khalti_api_base')) {
    function fitgym_khalti_api_base(): string
    {
        return fitgym_khalti_environment() === 'production'
            ? 'https://khalti.com/api/v2'
            : 'https://dev.khalti.com/api/v2';
    }
}

if (!function_exists('fitgym_parse_price_rupees')) {
    function fitgym_parse_price_rupees($value, int $fallback = 2000): int
    {
        if (is_int($value) || is_float($value)) {
            $numeric = (int)$value;
            return max(10, $numeric);
        }

        $text = trim((string)$value);
        if ($text === '') {
            return max(10, $fallback);
        }

        if (preg_match('/(\d[\d,]*)/', $text, $matches)) {
            $numeric = (int)str_replace(',', '', $matches[1]);
            if ($numeric > 0) {
                return $numeric;
            }
        }

        return max(10, $fallback);
    }
}

if (!function_exists('fitgym_price_label_from_rupees')) {
    function fitgym_price_label_from_rupees(int $rupees): string
    {
        return 'NPR ' . number_format($rupees);
    }
}

if (!function_exists('fitgym_generate_payment_order_id')) {
    function fitgym_generate_payment_order_id(string $prefix = 'FG'): string
    {
        try {
            $random = strtoupper(bin2hex(random_bytes(4)));
        } catch (Throwable $throwable) {
            $random = strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8));
        }

        return strtoupper($prefix) . '-' . date('YmdHis') . '-' . $random;
    }
}

if (!function_exists('fitgym_khalti_request')) {
    function fitgym_khalti_request(string $endpoint, array $payload): array
    {
        $secretKey = fitgym_khalti_secret_key();
        if ($secretKey === '') {
            return [
                'ok' => false,
                'http_code' => 0,
                'error' => 'Khalti sandbox key is not configured.',
                'body' => null,
            ];
        }

        if (!function_exists('curl_init')) {
            return [
                'ok' => false,
                'http_code' => 0,
                'error' => 'The cURL extension is required for Khalti payments.',
                'body' => null,
            ];
        }

        $endpoint = '/' . ltrim($endpoint, '/');
        $url = rtrim(fitgym_khalti_api_base(), '/') . $endpoint;
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: key ' . $secretKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $jsonPayload,
        ]);

        $rawResponse = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if (!is_string($rawResponse)) {
            return [
                'ok' => false,
                'http_code' => $httpCode,
                'error' => $curlError !== '' ? $curlError : 'No response returned by Khalti.',
                'body' => null,
            ];
        }

        $decoded = json_decode($rawResponse, true);
        if (!is_array($decoded)) {
            $decoded = ['raw' => $rawResponse];
        }

        return [
            'ok' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'error' => $curlError,
            'body' => $decoded,
        ];
    }
}

if (!function_exists('fitgym_khalti_initiate_payment')) {
    function fitgym_khalti_initiate_payment(array $booking): array
    {
        $amountPaisa = (int)($booking['payment_amount_paisa'] ?? 0);
        $payload = [
            'return_url' => fitgym_absolute_url('/php/payment_khalti_callback.php'),
            'website_url' => fitgym_absolute_url('/'),
            'amount' => $amountPaisa,
            'purchase_order_id' => (string)($booking['payment_order_id'] ?? ''),
            'purchase_order_name' => (string)($booking['class_name'] ?? 'FitGym Class'),
            'customer_info' => [
                'name' => (string)($booking['full_name'] ?? 'FitGym Member'),
                'email' => (string)($booking['email'] ?? ''),
                'phone' => (string)($booking['contact_number'] ?? ''),
            ],
            'amount_breakdown' => [
                [
                    'label' => 'Class Fee',
                    'amount' => $amountPaisa,
                ],
            ],
            'product_details' => [
                [
                    'identity' => (string)($booking['class_slug'] ?? $booking['payment_order_id'] ?? 'fitgym-class'),
                    'name' => (string)($booking['class_name'] ?? 'FitGym Class'),
                    'total_price' => $amountPaisa,
                    'quantity' => 1,
                    'unit_price' => $amountPaisa,
                ],
            ],
            'merchant_booking_id' => (string)($booking['id'] ?? ''),
        ];

        return fitgym_khalti_request('/epayment/initiate/', $payload);
    }
}

if (!function_exists('fitgym_khalti_lookup_payment')) {
    function fitgym_khalti_lookup_payment(string $pidx): array
    {
        return fitgym_khalti_request('/epayment/lookup/', ['pidx' => $pidx]);
    }
}

if (!function_exists('fitgym_update_booking_payment_fields')) {
    function fitgym_update_booking_payment_fields(int $bookingId, array $fields): bool
    {
        global $conn;

        if (!isset($conn) || !($conn instanceof mysqli) || $bookingId <= 0 || $fields === []) {
            return false;
        }

        $setParts = [];
        $types = '';
        $values = [];
        foreach ($fields as $column => $value) {
            $setParts[] = "{$column} = ?";
            if (is_int($value)) {
                $types .= 'i';
            } else {
                $types .= 's';
            }
            $values[] = $value;
        }

        $types .= 'i';
        $values[] = $bookingId;
        $sql = "UPDATE bookings SET " . implode(', ', $setParts) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param($types, ...$values);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('fitgym_get_booking_by_payment_reference')) {
    function fitgym_get_booking_by_payment_reference(string $purchaseOrderId = '', string $pidx = ''): ?array
    {
        global $conn;

        if (!isset($conn) || !($conn instanceof mysqli)) {
            return null;
        }

        fitgym_bootstrap_booking_payment_columns();

        if ($purchaseOrderId !== '' && fitgym_table_has_column('bookings', 'payment_order_id')) {
            $stmt = $conn->prepare("SELECT * FROM bookings WHERE payment_order_id = ? ORDER BY id DESC LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $purchaseOrderId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    return $row;
                }
            }
        }

        if ($pidx !== '' && fitgym_table_has_column('bookings', 'payment_pidx')) {
            $stmt = $conn->prepare("SELECT * FROM bookings WHERE payment_pidx = ? ORDER BY id DESC LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $pidx);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    return $row;
                }
            }
        }

        return null;
    }
}

if (!function_exists('fitgym_booking_payment_snapshot')) {
    function fitgym_booking_payment_snapshot(array $booking): array
    {
        $provider = strtolower(trim((string)($booking['payment_provider'] ?? $booking['payment_method'] ?? 'cash')));
        if ($provider === 'online') {
            $provider = 'khalti';
        }

        $bookingStatus = strtolower(trim((string)($booking['status'] ?? 'pending')));
        $status = strtolower(trim((string)($booking['payment_status'] ?? '')));
        if ($status === '') {
            if ($bookingStatus === 'cancelled') {
                $status = 'cancelled';
            } elseif ($provider === 'khalti') {
                $status = 'paid';
            } else {
                $status = 'unpaid';
            }
        }

        $providerLabel = $provider === 'khalti' ? 'Khalti' : 'Cash';
        $methodLabel = $provider === 'khalti' ? 'Khalti' : 'Cash at gym';
        $label = 'Payment pending';
        $detail = 'Payment has not been completed yet.';
        $badgeClass = 'warning';

        if ($provider === 'cash') {
            if ($bookingStatus === 'expired') {
                $label = 'Booking expired';
                $detail = 'This class session is more than 30 days old and is now archived.';
                $badgeClass = 'info';
            } elseif ($status === 'cancelled') {
                $label = 'No payment due';
                $detail = 'This booking was cancelled, so no cash payment is required.';
                $badgeClass = 'danger';
            } else {
                $label = 'Pay at gym';
                $detail = 'Please pay at the front desk before your class starts.';
                $badgeClass = 'warning';
            }
        } elseif ($status === 'paid') {
            $label = 'Paid with Khalti';
            $detail = 'Your Khalti payment was verified successfully.';
            $badgeClass = 'success';
        } elseif (in_array($status, ['initiated', 'pending'], true)) {
            $label = 'Awaiting Khalti confirmation';
            $detail = 'The booking is holding your slot while Khalti payment confirmation is pending.';
            $badgeClass = 'warning';
        } elseif ($status === 'refunded') {
            $label = 'Refunded';
            $detail = 'This Khalti payment was refunded.';
            $badgeClass = 'info';
        } elseif (in_array($status, ['failed', 'cancelled', 'expired'], true)) {
            $label = 'Payment not completed';
            $detail = 'The Khalti checkout did not complete successfully.';
            $badgeClass = 'danger';
        }

        return [
            'provider' => $provider,
            'provider_label' => $providerLabel,
            'method_label' => $methodLabel,
            'status' => $status,
            'status_label' => $label,
            'status_detail' => $detail,
            'badge_class' => $badgeClass,
            'order_id' => trim((string)($booking['payment_order_id'] ?? '')),
            'pidx' => trim((string)($booking['payment_pidx'] ?? '')),
            'transaction_id' => trim((string)($booking['payment_transaction_id'] ?? '')),
        ];
    }
}
