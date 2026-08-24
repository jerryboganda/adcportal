<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = App\Models\Service::first();
if ($service) {
    $slots = timeSlot($service->id, now()->format('d-m-Y'));
    echo 'slots via AvailabilityService: '.count($slots).PHP_EOL;
    echo json_encode(array_slice($slots, 0, 2)).PHP_EOL;
    // second call should hit cache (0 queries)
    $t = microtime(true);
    $slots2 = timeSlot($service->id, now()->format('d-m-Y'));
    echo 'cached call: '.count($slots2).' slots in '.round((microtime(true) - $t) * 1000, 2).'ms'.PHP_EOL;
} else {
    echo "no services seeded\n";
}
