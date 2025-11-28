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

// choose a user
$user = User::first();
if (! $user) {
    echo "No users in DB\n";
    exit(1);
}
Auth::login($user);

$ts = time();
// create message
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

// choose a user
$user = User::first();
if (! $user) {
    echo "No users in DB\n";
    exit(1);
}
Auth::login($user);

$ts = time();
// create message
$message = Message::create([
    'from_user_id' => $user->id,
    'subject' => 'TEST DELETE ' . $ts,
    'body' => 'test body',
    'status' => 'sent',
    'sent_at' => now(),
]);

// create recipient (trashed)
$mr = MessageRecipient::create([
    'message_id' => $message->id,
    'user_id' => $user->id,
    'type' => 'to',
    'deleted_at' => now(),
]);

// create attachment record
$filename = 'testfile_' . $ts . '.txt';
$relpath = 'attachments/' . $filename;
$fullpath = storage_path('app/public/' . $relpath);
@mkdir(dirname($fullpath), 0755, true);
file_put_contents($fullpath, "hello world $ts");
$size = filesize($fullpath);

$att = Attachment::create([
    'original_name' => 'testfile.txt',
    'path' => $relpath,
    'mime_type' => 'text/plain',
    'size' => $size,
    'status' => 'ready',
]);
// create pivot
DB::table('attachmentables')->insert([
    'attachment_id' => $att->id,
    'attachable_type' => Message::class,
    'attachable_id' => $message->id,
]);

// now call controller destroy
$request = Illuminate\Http\Request::create('/messages/'.$message->id, 'DELETE');
$request->setUserResolver(function() use ($user) { return $user; });
$controller = app()->make(App\Http\Controllers\MessageController::class);
$res = $controller->destroy($request, $message);

echo "Controller response: ".json_encode($res->getData())."\n";

// Check DB state
$existsMsg = Message::where('id', $message->id)->exists();
$recCount = DB::table('message_recipients')->where('message_id', $message->id)->count();
$attRow = Attachment::where('id', $att->id)->first();
$fileExists = file_exists($fullpath);

echo "Message exists after destroy: ".($existsMsg? 'yes':'no')."\n";
echo "Recipient rows for message: $recCount\n";
echo "Attachment row exists: ".($attRow? 'yes':'no')."\n";
echo "Attachment file exists on disk: ".($fileExists? 'yes':'no')."\n";

// show attachmentables count
$attachables = DB::table('attachmentables')->where('attachment_id', $att->id)->count();
echo "attachmentables count for attachment: $attachables\n";
    $fileExists = file_exists($fullpath);

    echo "Message exists after destroy: ".($existsMsg? 'yes':'no')."\n";
    echo "Recipient rows for message: $recCount\n";
    echo "Attachment row exists: ".($attRow? 'yes':'no')."\n";
    echo "Attachment file exists on disk: ".($fileExists? 'yes':'no')."\n";

    // show attachmentables count
    $attachables = DB::table('attachmentables')->where('attachment_id', $att->id)->count();
    echo "attachmentables count for attachment: $attachables\n";
