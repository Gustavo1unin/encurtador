<?php
$dbFile = __DIR__ . '/links.db';

function getDb(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $pdo = new PDO('sqlite:' . __DIR__ . '/links.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE IF NOT EXISTS links (
        `key` TEXT PRIMARY KEY,
        target TEXT NOT NULL,
        created TEXT,
        hits INTEGER DEFAULT 0
    )");
    return $pdo;
}

function loadLinks(): array
{
    $pdo = getDb();
    $stmt = $pdo->query('SELECT `key`, target, created, hits FROM links ORDER BY created DESC');
    $rows = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rows[$r['key']] = $r;
    }
    return $rows;
}

function normalizeUrl(string $url): string
{
    $url = trim($url);
    if ($url === '') return '';
    if (!preg_match('#^https?://#i', $url)) $url = 'http://' . $url;
    return $url;
}

function validateCustomKey(string $key): bool
{
    return preg_match('/^[A-Za-z0-9_-]{3,20}$/', $key) === 1;
}

function generateSlug(int $length = 6): string
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $pdo = getDb();
    do {
        $slug = '';
        for ($i = 0; $i < $length; $i++) {
            $slug .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $stmt = $pdo->prepare('SELECT 1 FROM links WHERE `key` = :k');
        $stmt->execute([':k' => $slug]);
        $exists = (bool)$stmt->fetchColumn();
    } while ($exists);
    return $slug;
}

function getBaseUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = dirname($_SERVER['SCRIPT_NAME']);
    $path = rtrim($path, '/\\');
    return sprintf('%s://%s%s/encurtador.php', $scheme, $host, $path !== '' ? $path : '');
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$messages = [];
$shortUrl = null;

// fluxo de redirecionamento
if (isset($_GET['r'])) {
    $key = trim((string)$_GET['r']);
    if ($key !== '') {
        $pdo = getDb();
        $stmt = $pdo->prepare('SELECT target FROM links WHERE `key` = :k');
        $stmt->execute([':k' => $key]);
        $target = $stmt->fetchColumn();
        if ($target !== false) {
            $pdo->prepare('UPDATE links SET hits = hits + 1 WHERE `key` = :k')->execute([':k' => $key]);
            header('Location: ' . $target);
            exit;
        }
    }
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>404 - Link não encontrado</title></head><body><h1>404 - Link não encontrado</h1><p>O link solicitado não existe.</p></body></html>';
    exit;
}

// envio do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = normalizeUrl((string)($_POST['url'] ?? ''));
    $custom = trim((string)($_POST['custom'] ?? ''));

    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        $messages[] = ['type' => 'error', 'text' => 'Informe uma URL válida. Por exemplo: https://example.com'];
    }

    if ($custom !== '' && !validateCustomKey($custom)) {
        $messages[] = ['type' => 'error', 'text' => 'Chave personalizada inválida. Use 3-20 caracteres: letras, números, - ou _.'];
    }

    if ($custom !== '') {
        $stmt = getDb()->prepare('SELECT 1 FROM links WHERE `key` = :k');
        $stmt->execute([':k' => $custom]);
        if ($stmt->fetchColumn()) {
            $messages[] = ['type' => 'error', 'text' => 'Chave personalizada já existe. Escolha outra.'];
        }
    }

    if (empty($messages)) {
        $key = $custom !== '' ? $custom : generateSlug();
        $pdo = getDb();
        $stmt = $pdo->prepare('INSERT OR REPLACE INTO links(`key`, target, created, hits) VALUES (:k, :t, :c, :h)');
        $stmt->execute([
            ':k' => $key,
            ':t' => $url,
            ':c' => date('Y-m-d H:i:s'),
            ':h' => 0,
        ]);
        $shortUrl = getBaseUrl() . '?r=' . rawurlencode($key);
        $messages[] = ['type' => 'success', 'text' => 'Link encurtado com sucesso. Copie abaixo:'];
    }
}

// load links for listing
$links = loadLinks();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encurtador de Links</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="page">
    <div class="card">
        <h1>Encurtador de Links</h1>
        <p class="small">Cole a URL e gere um link curto. Você pode usar uma chave personalizada ou deixar o sistema criar uma automaticamente.</p>

        <?php foreach ($messages as $message): ?>
            <div class="message <?= $message['type'] ?>"><?= escape($message['text']) ?></div>
        <?php endforeach; ?>

        <form method="post" action="<?= escape($_SERVER['SCRIPT_NAME']) ?>">
            <label for="url">URL original</label>
            <input type="text" id="url" name="url" placeholder="https://exemplo.com/pagina" value="<?= escape($_POST['url'] ?? '') ?>" required>
            <label for="custom">Chave personalizada (opcional)</label>
            <input type="text" id="custom" name="custom" placeholder="meu-link" value="<?= escape($_POST['custom'] ?? '') ?>">
            <div class="hint">Use 3-20 caracteres, apenas letras, números, traço (-) e sublinhado (_).</div>
            <button type="submit">Gerar link curto</button>
        </form>

        <?php if ($shortUrl !== null): ?>
            <div class="short-url">
                <strong>Link curto:</strong>
                <div><a href="<?= escape($shortUrl) ?>" target="_blank" rel="noopener noreferrer"><?= escape($shortUrl) ?></a></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($links)): ?>
            <h2 style="margin-top: 32px;">Links criados</h2>
            <table>
                <thead>
                    <tr>
                        <th>Chave</th>
                        <th>Destino</th>
                        <th>Criado</th>
                        <th>Cliques</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($links, true) as $key => $item): ?>
                        <tr>
                            <td><a href="<?= escape($shortUrl = getBaseUrl() . '?r=' . rawurlencode($key)) ?>" target="_blank" rel="noopener noreferrer"><?= escape($key) ?></a></td>
                            <td><a href="<?= escape($item['target']) ?>" target="_blank" rel="noopener noreferrer"><?= escape($item['target']) ?></a></td>
                            <td><?= escape($item['created'] ?? '-') ?></td>
                            <td><?= (int)($item['hits'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
<script src="assets/app.js"></script>
</html>
