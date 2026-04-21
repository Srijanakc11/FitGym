<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/dynamic_content.php';

echo "Database connection: " . ($conn ? "OK" : "FAILED") . "\n";
if ($conn) {
    $res = $conn->query("SELECT COUNT(*) as cnt FROM classes_admin");
    $row = $res ? $res->fetch_assoc() : null;
    echo "Total classes in classes_admin: " . ($row ? $row['cnt'] : "ERROR") . "\n";
    
    $res = $conn->query("SELECT id, slug, name, active FROM classes_admin LIMIT 10");
    if ($res) {
        echo "Sample classes (active column):\n";
        while ($r = $res->fetch_assoc()) {
            echo "- ID: {$r['id']}, Slug: {$r['slug']}, Name: {$r['name']}, Active: {$r['active']}\n";
        }
    } else {
        echo "Error fetching sample classes: " . $conn->error . "\n";
    }

    $res = $conn->query("SHOW COLUMNS FROM classes_admin LIKE 'is_active'");
    echo "is_active column exists: " . ($res && $res->num_rows > 0 ? "YES" : "NO") . "\n";
}
