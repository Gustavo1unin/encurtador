<?php
// Script para migrar links.json para SQLite (links.db)
$jsonFile = __DIR__ . '/links.json';
$dbFile = __DIR__ . '/links.db';

if (!file_exists($jsonFile)) {
    echo "links.json not found. Nothing to migrate.\n";
    exit;
}

$json = file_get_contents($jsonFile);
$data = json_decode($json, true);
if (!is_array($data)) {
    echo "links.json is invalid or empty.\n";
    exit;
}

$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE IF NOT EXISTS links (
    `key` TEXT PRIMARY KEY,
    target TEXT NOT NULL,
    created TEXT,
    hits INTEGER DEFAULT 0
)");

$stmt = $pdo->prepare('INSERT OR REPLACE INTO links(`key`, target, created, hits) VALUES (:k, :t, :c, :h)');
$count = 0;
foreach ($data as $k => $item) {
    $stmt->execute([
        ':k' => $k,
        ':t' => $item['target'] ?? '',
        ':c' => $item['created'] ?? date('Y-m-d H:i:s'),
        ':h' => isset($item['hits']) ? (int)$item['hits'] : 0,
    ]);
    $count++;
}

echo "Migrated {$count} links to {$dbFile}\n";
