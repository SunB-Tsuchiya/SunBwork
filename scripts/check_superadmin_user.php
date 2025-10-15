<?php
require __DIR__ . "/../vendor/autoload.php";
$app = require __DIR__ . "/../bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$email = env('SUPERADMIN_EMAIL');
if (!$email) {
    echo "NO_EMAIL\n";
    exit(0);
}
$u = \App\Models\User::where('email', $email)->first();
if ($u) {
    echo "FOUND:" . $u->id . ",role=" . ($u->user_role ?? '(null)') . "\n";
} else {
    echo "NOT_FOUND\n";
}
