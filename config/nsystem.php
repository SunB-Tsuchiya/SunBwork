<?php

return [
    /*
    |--------------------------------------------------------------------------
    | NSystem デモ用ゲスト認証設定
    |--------------------------------------------------------------------------
    | .env に NSYSTEM_GUEST_EMAIL / NSYSTEM_GUEST_PASSWORD を設定すること。
    | このシステムを削除するときはこのファイルごと削除する。
    */
    'guest' => [
        'email'    => env('NSYSTEM_GUEST_EMAIL', 'guest@n-demo.local'),
        'password' => env('NSYSTEM_GUEST_PASSWORD'),
        'name'     => env('NSYSTEM_GUEST_NAME', 'デモゲスト'),
    ],
];
