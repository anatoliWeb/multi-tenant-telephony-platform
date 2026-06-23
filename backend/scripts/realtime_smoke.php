<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

event(new App\Events\SystemNotificationEvent(
    type: 'info',
    title: 'Smoke',
    message: 'Realtime bridge validated',
    createdAt: now()->toIso8601String(),
));

echo "OK\n";

