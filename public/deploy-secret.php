<?php

$envPath = dirname(__DIR__) . '/.env';

if (!file_exists($envPath)) {
    die('Missing .env file');
}

$envContent = file_get_contents($envPath);

if (preg_match('/^DEPLOY_SECRET\s*=/m', $envContent)) {
    preg_match('/^DEPLOY_SECRET\s*=\s*(.*)$/m', $envContent, $m);

    echo "<h3>DEPLOY_SECRET už existuje</h3>";
    echo "<pre>{$m[1]}</pre>";
    echo "<p><strong>⚠️ Tento súbor po použití zmaž.</strong></p>";
    exit;
}

if (!preg_match('/^APP_KEY\s*=\s*(.*)$/m', $envContent, $m)) {
    die('APP_KEY not found');
}

$appKeyRaw = trim($m[1]);

if (str_starts_with($appKeyRaw, 'base64:')) {
    $appKey = base64_decode(substr($appKeyRaw, 7));
} else {
    $appKey = $appKeyRaw;
}

$deploySecret = hash_hmac(
    'sha256',
    'github-webhook-deploy',
    $appKey
);

$replacement = "APP_KEY={$appKeyRaw}\nDEPLOY_SECRET={$deploySecret}";
$newEnv = preg_replace(
    '/^APP_KEY\s*=\s*.*$/m',
    $replacement,
    $envContent,
    1
);

if ($newEnv === null || $newEnv === $envContent) {
    $newEnv .= "\nDEPLOY_SECRET={$deploySecret}\n";
}

// 6️⃣ Zapíš späť do .env
file_put_contents($envPath, $newEnv);

// 7️⃣ Výpis pre užívateľa
echo "<h2>DEPLOY_SECRET vygenerovaný a uložený</h2>";
echo "<pre style='word-break:break-all;'>$deploySecret</pre>";

echo "<p>Webhook secret je teraz uložený v <code>.env</code>.</p>";
echo "<p><strong>⚠️ Tento súbor po použití zmaž.</strong></p>";
