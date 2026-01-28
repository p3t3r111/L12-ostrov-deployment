<?php

// ===== KONFIGURÁCIA =====
$APP_DIR = __DIR__.'/../';      // Laravel root
$BRANCH  = 'main';         // branch na deploy
$SECRET  = getenv('DEPLOY_SECRET');

// ===== OVERENIE WEBHOOKU =====
$payload   = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

$expected = 'sha256=' . hash_hmac('sha256', $payload, $SECRET);

if (!$SECRET || !hash_equals($expected, $signature)) {
    http_response_code(403);
    exit('Invalid signature');
}
$APP_DIR = realpath(__DIR__ . '/../');
// ===== DEPLOY PRÍKAZY =====
$commands = [
    "echo 'Deploying to $APP_DIR'",
    'git pull',
    'composer install --no-dev --optimize-autoloader',
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
        echo "Deploy failed on: $command\n\n";
        echo implode("\n", $out);
        exit;
    }
}

$output[] = "Deploy hotový";

echo implode("\n", $output);