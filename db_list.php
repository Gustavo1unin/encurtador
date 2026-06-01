<?php
// Script para listar links no banco SQLite
$db = __DIR__ . '/links.db';
if (!file_exists($db)) {
    echo "links.db not found\n";
    exit(1);
}
$pdo = new PDO('sqlite:' . $db);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$stmt = $pdo->query('SELECT `key`, target, created, hits FROM links');
foreach ($stmt as $r) {
    echo $r['key'] . "\t" . $r['target'] . "\t" . ($r['created'] ?? '-') . "\t" . ($r['hits'] ?? 0) . "\n";
}
