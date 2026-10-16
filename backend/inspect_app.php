<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$a = \App\Models\App::withoutTenantScope()->where('ulid', '01M4WVCV6T93ASWMD0XE1ECVKA')->first();
if (!$a) {
    echo "App not found\n";
    exit;
}

echo "App Name: " . $a->name . "\n";
echo "Platform: " . $a->platform?->value . "\n";
echo "Package: " . $a->package_name . "\n";
echo "Setup Step: " . $a->setup_step?->value . "\n";

if ($a->connection) {
    echo "Connection Store URL: " . $a->connection->store_url . "\n";
    echo "Connection Platform: " . $a->connection->platform?->value . "\n";
    echo "Connection Status: " . $a->connection->status?->value . "\n";
} else {
    echo "No connection record\n";
}

$published = $a->configs()->where('state', 'published')->first();
$draft = $a->configs()->where('state', 'draft')->first();

echo "Published Config: " . ($published ? json_encode($published->config_payload, JSON_PRETTY_PRINT) : 'None') . "\n";
echo "Draft Config: " . ($draft ? json_encode($draft->config_payload, JSON_PRETTY_PRINT) : 'None') . "\n";
