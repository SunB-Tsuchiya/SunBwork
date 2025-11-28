<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Models\Attachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

function ensureUsers($n = 3)
{
    $count = User::count();
    if ($count >= $n) return;
    for ($i = $count; $i < $n; $i++) {
        User::create([
            'name' => 'testuser_' . $i,
            'email' => 'testuser_' . $i . '@example.local',
            'password' => bcrypt('secret'),
        ]);
    }
}

ensureUsers(3);
$users = User::take(3)->get();
$user1 = $users[0];
$user2 = $users[1];
$user3 = $users[2];

echo "=== Multi-recipient case ===\n";
// message from user1 to user2 and user3
$message = Message::create([
    'from_user_id' => $user1->id,
    'subject' => 'MULTI ' . time(),
    'body' => 'multi body',
    'status' => 'sent',
    'sent_at' => now(),
]);
MessageRecipient::create(['message_id' => $message->id, 'user_id' => $user2->id, 'type' => 'to']);
MessageRecipient::create(['message_id' => $message->id, 'user_id' => $user3->id, 'type' => 'to']);

// create attachment and link
$ts = time();
$relpath = 'attachments/multi_' . $ts . '.txt';
$fullpath = storage_path('app/public/' . $relpath);
@mkdir(dirname($fullpath), 0755, true);
file_put_contents($fullpath, "multi $ts");
$att = Attachment::create(['original_name' => 'multi.txt', 'path' => $relpath, 'mime_type' => 'text/plain', 'size' => filesize($fullpath), 'status' => 'ready']);
DB::table('attachmentables')->insert(['attachment_id' => $att->id, 'attachable_type' => Message::class, 'attachable_id' => $message->id]);

// simulate user2 trash -> permanent delete
MessageRecipient::where('message_id', $message->id)->where('user_id', $user2->id)->update(['deleted_at' => now()]);
$request = Illuminate\Http\Request::create('/messages/' . $message->id, 'DELETE');
$request->setUserResolver(function () use ($user2) {
    return $user2;
});
$controller = app()->make(App\Http\Controllers\MessageController::class);
$res = $controller->destroy($request, $message);

echo "Controller response: " . json_encode($res->getData()) . "\n";
// checks
echo "Message exists: " . (Message::where('id', $message->id)->exists() ? 'yes' : 'no') . "\n";
echo "Recipient rows: " . DB::table('message_recipients')->where('message_id', $message->id)->count() . "\n";
echo "Attachment row exists: " . (Attachment::where('id', $att->id)->exists() ? 'yes' : 'no') . "\n";
echo "Attachment file exists: " . (file_exists($fullpath) ? 'yes' : 'no') . "\n";
echo "attachmentables count: " . DB::table('attachmentables')->where('attachment_id', $att->id)->count() . "\n";

echo "\n=== Shared-attachment case ===\n";
// create shared attachment
$relpath2 = 'attachments/shared_' . ($ts + 1) . '.txt';
$fullpath2 = storage_path('app/public/' . $relpath2);
@mkdir(dirname($fullpath2), 0755, true);
file_put_contents($fullpath2, "shared $ts");
$att2 = Attachment::create(['original_name' => 'shared.txt', 'path' => $relpath2, 'mime_type' => 'text/plain', 'size' => filesize($fullpath2), 'status' => 'ready']);

// create msgA (only actingUser recipient) and msgB (other recipient)
$msgA = Message::create(['from_user_id' => $user1->id, 'subject' => 'A' . time(), 'body' => 'A', 'status' => 'sent', 'sent_at' => now()]);
MessageRecipient::create(['message_id' => $msgA->id, 'user_id' => $user2->id, 'type' => 'to', 'deleted_at' => now()]);
DB::table('attachmentables')->insert(['attachment_id' => $att2->id, 'attachable_type' => Message::class, 'attachable_id' => $msgA->id]);

$msgB = Message::create(['from_user_id' => $user1->id, 'subject' => 'B' . time(), 'body' => 'B', 'status' => 'sent', 'sent_at' => now()]);
MessageRecipient::create(['message_id' => $msgB->id, 'user_id' => $user3->id, 'type' => 'to']);
DB::table('attachmentables')->insert(['attachment_id' => $att2->id, 'attachable_type' => Message::class, 'attachable_id' => $msgB->id]);

// Now run destroy as user2 for msgA
$request2 = Illuminate\Http\Request::create('/messages/' . $msgA->id, 'DELETE');
$request2->setUserResolver(function () use ($user2) {
    return $user2;
});
$res2 = $controller->destroy($request2, $msgA);

echo "Controller response (shared case): " . json_encode($res2->getData()) . "\n";

// checks
echo "msgA exists: " . (Message::where('id', $msgA->id)->exists() ? 'yes' : 'no') . "\n";
echo "msgB exists: " . (Message::where('id', $msgB->id)->exists() ? 'yes' : 'no') . "\n";
echo "attachment row exists (att2): " . (Attachment::where('id', $att2->id)->exists() ? 'yes' : 'no') . "\n";
echo "attachment file exists (att2): " . (file_exists($fullpath2) ? 'yes' : 'no') . "\n";
echo "attachmentables count (att2): " . DB::table('attachmentables')->where('attachment_id', $att2->id)->count() . "\n";

echo "\nDone.\n";
