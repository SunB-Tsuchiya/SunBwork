<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
// Use HTTP kernel to handle request
$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

// login as user id 31
$user = \App\Models\User::find(31);
if ($user) {
    Auth::loginUsingId($user->id);
}

echo $response->getContent();
// create request to /calendar
$request = Request::create('/calendar', 'GET');
// set cookies/session if needed
$response = $httpKernel->handle($request);
// output the content
echo $response->getContent();

$httpKernel->terminate($request, $response);
