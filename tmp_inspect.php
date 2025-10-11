<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Foundation\Console\Kernel::class);
$kernel->bootstrap();

$request = Illuminate\Http\Request::create('/bot/chat','POST',['message' => '続けてください', 'conversation_id' => 5]);
$ctrl = new App\Http\Controllers\Bot\BotController();
$res = $ctrl->chat($request);
// if response is a JsonResponse or Response, try to extract data
if (is_object($res) && method_exists($res, 'getData')) {
    $data = $res->getData();
} elseif (is_array($res)) {
    $data = $res;
} else {
    $data = $res;
}
echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
