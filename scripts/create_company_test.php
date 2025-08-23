<?php

require __DIR__ . "/../vendor/autoload.php";

use Illuminate\Support\Str;
use App\Models\Company;

// bootstrap the framework so Eloquent works
$app = require __DIR__ . "/../bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$name = 'テスト会社 ' . time();
$base = Str::slug($name);
$code = $base;
$i = 1;
while (Company::where('code', $code)->exists()) {
    $code = $base . '-' . ($i++);
}
Company::create(['name' => $name, 'code' => $code]);
echo "created {$code}\n";
