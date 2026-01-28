<?php

// ================== KONFIGURÁCIA ==================
$APP_DIR = realpath(__DIR__ . '/../'); // Laravel root
$SECRET  = getenv('DEPLOY_SECRET');

if (!$APP_DIR) {
    http_response_code(500);
    exit('Invalid APP_DIR');
}

if (!$SECRET) {
    http_response_code(500);
    exit('DEPLOY_SECRET not set');
}

// ================== OVERENIE SIGNATÚRY ==================
$payload   = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

$expected = 'sha256=' . hash_hmac('sha256', $payload, $SECRET);

if (!hash_equals($expected, $signature)) {
    http_response_code(403);
    exit('Invalid signature');
}

// ================== ULOŽ ZIP ==================
$zipPath = $APP_DIR . '/deploy.zip';

if (file_put_contents($zipPath, $payload) === false) {
    http_response_code(500);
    exit('Failed to save deploy.zip');
}

// ================== ROZBAL ZIP ==================
$unzipCmd = 'unzip -o ' . escapeshellarg($zipPath) . ' -d ' . escapeshellarg($APP_DIR);
exec($unzipCmd . ' 2>&1', $out, $code);

if ($code !== 0) {
    http_response_code(500);
    echo "Unzip failed\n";
    echo implode("\n", $out);
    exit;
}

// ================== ZMAŽ ZIP ==================
@unlink($zipPath);

// ================== CHMOD FUNKCIA ==================
function chmodRecursive(string $path, int $perm): void
{
    if (!file_exists($path)) {
        return;
    }

    if (is_file($path) || is_link($path)) {
        @chmod($path, $perm);
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        @chmod($item->getPathname(), $perm);
    }

    @chmod($path, $perm);
}

// ================== LARAVEL PRÁVA ==================
chmodRecursive($APP_DIR . '/storage', 0775);
chmodRecursive($APP_DIR . '/bootstrap/cache', 0775);

// ================== POST-DEPLOY PRÍKAZY ==================
$commands = [
    'php artisan optimize:clear',
];

$output = [];

foreach ($commands as $command) {
    $out = [];
    $code = 0;

    exec(
        'cd ' . escapeshellarg($APP_DIR) . ' && ' . $command . ' 2>&1',
        $out,
        $code
    );

    $output[] = ">> $command";
    $output   = array_merge($output, $out);

    if ($code !== 0) {
        http_response_code(500);
        echo "Post-deploy failed on: $command\n\n";
        echo implode("\n", $out);
        exit;
    }
}

// ================== HOTOVO ==================
echo ":white_check_mark: Deploy hotový\n";
echo implode("\n", $output);
