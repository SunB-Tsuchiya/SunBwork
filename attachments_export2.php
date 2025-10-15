<?php
require __DIR__ . '/vendor/autoload.php';
 = require __DIR__ . '/bootstrap/app.php';
 = ->make(Illuminate\Contracts\Console\Kernel::class);
->bootstrap();
 = \DB::table('attachments')->select('id','path','original_name','mime_type','size','user_id','message_id','diary_id','event_id','owner_type','owner_id','created_at')->get();
 = fopen('attachments_export.csv','w');
fputcsv(, ['id','path','original_name','mime_type','size','user_id','message_id','diary_id','event_id','owner_type','owner_id','created_at']);
foreach ( as ) {
    fputcsv(, [(string)->id, ->path, ->original_name, ->mime_type, (string)->size, (string)->user_id, (string)->message_id, (string)->diary_id, (string)->event_id, ->owner_type, (string)->owner_id, ->created_at]);
}
fclose();
echo attachments_export.csv
