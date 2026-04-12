<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$httpKernel->bootstrap();

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

Auth::loginUsingId(31);
$ctrl = new \App\Http\Controllers\CalendarController();
try {
    $resp = $ctrl->index();
    if (method_exists($resp, 'toResponse')) {
        $request = Request::create('/calendar','GET');
        $content = $resp->toResponse($request)->getContent();
        echo $content;
    } else {
        var_export($resp);
    }
} catch (\Throwable $e) {
    echo "ERROR: ";
    echo $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString();
}
