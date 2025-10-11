<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Create a fake request context and call ChatController::indexRooms
// create request and set a user (if available) on it
$request = Illuminate\Http\Request::create('/chat/rooms', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
$user = null;
try {
    $user = App\Models\User::find(9);
    if ($user) {
        // attach user to request
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
    }
} catch (\Throwable $_) {
}

$controller = new App\Http\Controllers\Chat\ChatController();
$response = $controller->indexRooms($request);
if ($response instanceof Illuminate\Http\JsonResponse) {
    echo json_encode($response->getData(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo "Not JSON response\n";
}
