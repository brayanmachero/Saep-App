<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$svc = app(\App\Services\KizeoService::class);

echo "--- Testing getSimpleList ---\n";
try {
    $items = $svc->getSimpleList('427266', true);
    echo "Items type: " . gettype($items) . "\n";
    echo "Items count: " . count($items) . "\n";
    if (count($items) > 0) {
        $first = $items[array_key_first($items)];
        echo "First item type: " . gettype($first) . "\n";
        echo "First item: " . json_encode($first, JSON_UNESCAPED_UNICODE) . "\n";
        echo "Second item: " . json_encode($items[array_key_first($items) + 1] ?? 'N/A', JSON_UNESCAPED_UNICODE) . "\n";
    }
} catch (\Exception $e) {
    echo "ERROR getSimpleList: " . $e->getMessage() . "\n";
}

echo "\n--- Testing getPersonalVigente ---\n";
try {
    $personal = $svc->getPersonalVigente(true);
    echo "Personal count: " . count($personal) . "\n";
    if (count($personal) > 0) {
        echo "First: " . json_encode($personal[0], JSON_UNESCAPED_UNICODE) . "\n";
    }
} catch (\Exception $e) {
    echo "ERROR getPersonalVigente: " . $e->getMessage() . "\n";
}
