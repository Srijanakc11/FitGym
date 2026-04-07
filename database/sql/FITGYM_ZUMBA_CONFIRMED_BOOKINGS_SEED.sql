INSERT INTO accounts (
    role,
    name,
    email,
    login_code,
    password_hash,
    phone,
    gender,
    qualification_status,
    active,
    legacy_source,
    legacy_id
)
SELECT
    'client',
    seed.name,
    seed.email,
    seed.email,
    '$2y$10$B.s6wZbN2WquVRsIu3T7zu1XKo1v6fUzt2gDWR0/gj6oUwPOWOB76',
    seed.phone,
    seed.gender,
    'verified',
    1,
    NULL,
    NULL
FROM (
    SELECT 'Aayush Gurung' AS name, 'aayush.zumba@fitgym.local' AS email, '9800001001' AS phone, 'male' AS gender
    UNION ALL
    SELECT 'Nisha Rai', 'nisha.zumba@fitgym.local', '9800001002', 'female'
    UNION ALL
    SELECT 'Prerna Shrestha', 'prerna.zumba@fitgym.local', '9800001003', 'female'
) AS seed
WHERE NOT EXISTS (
    SELECT 1
    FROM accounts existing
    WHERE existing.email = seed.email
);

INSERT INTO bookings (
    user_id,
    class_id,
    class_slug,
    class_name,
    trainer_name,
    trainer_type,
    full_name,
    email,
    contact_number,
    preferred_date,
    time_slot,
    participants,
    payment_method,
    payment_provider,
    payment_status,
    payment_order_id,
    payment_pidx,
    payment_transaction_id,
    payment_amount_paisa,
    payment_requested_at,
    payment_completed_at,
    payment_verified_at,
    payment_response_json,
    status
)
SELECT
    NULL,
    NULL,
    class_meta.class_slug,
    class_meta.class_name,
    class_meta.trainer_name,
    'regular',
    seed.name,
    seed.email,
    seed.phone,
    DATE('2026-04-03'),
    '6:00-7:00 AM',
    1,
    'khalti',
    'khalti',
    'paid',
    seed.payment_order_id,
    seed.payment_pidx,
    seed.payment_transaction_id,
    200000,
    '2026-04-01 08:30:00',
    '2026-04-01 08:32:00',
    '2026-04-01 08:33:00',
    '{"seeded":true,"provider":"khalti","status":"Completed","source":"FITGYM_ZUMBA_CONFIRMED_BOOKINGS_SEED"}',
    'Confirmed'
FROM (
    SELECT
        'Aayush Gurung' AS name,
        'aayush.zumba@fitgym.local' AS email,
        '9800001001' AS phone,
        'FG-SEED-ZUMBA-001' AS payment_order_id,
        'PSEEDZUMBA001' AS payment_pidx,
        'TXNSEEDZUMBA001' AS payment_transaction_id
    UNION ALL
    SELECT
        'Nisha Rai',
        'nisha.zumba@fitgym.local',
        '9800001002',
        'FG-SEED-ZUMBA-002',
        'PSEEDZUMBA002',
        'TXNSEEDZUMBA002'
    UNION ALL
    SELECT
        'Prerna Shrestha',
        'prerna.zumba@fitgym.local',
        '9800001003',
        'FG-SEED-ZUMBA-003',
        'PSEEDZUMBA003',
        'TXNSEEDZUMBA003'
) AS seed
CROSS JOIN (
    SELECT
        c.slug AS class_slug,
        c.name AS class_name,
        COALESCE(a.name, 'Hari Tamang') AS trainer_name
    FROM classes_admin c
    LEFT JOIN accounts a
        ON a.id = c.trainer_account_id
       AND a.role = 'trainer'
    WHERE c.slug = 'zumba'
    LIMIT 1
) AS class_meta
WHERE NOT EXISTS (
    SELECT 1
    FROM bookings existing
    WHERE existing.email = seed.email
      AND existing.class_slug = 'zumba'
      AND existing.preferred_date = DATE('2026-04-03')
      AND existing.time_slot = '6:00-7:00 AM'
);
