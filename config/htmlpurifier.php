<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HTMLPurifier settings
    |--------------------------------------------------------------------------
    |
    | Centralized settings for server-side HTML sanitization. Adjust the
    | whitelist or formatting options here. This file is intentionally small
    | so operators can tune allowed tags/attributes without editing code.
    |
    */
    'settings' => [
        // allow a conservative set of formatting tags and attributes
        'HTML.Allowed' => 'p,b,strong,i,em,u,a[href|title|target|rel],ul,ol,li,br,img[src|alt|width|height],h1,h2,h3,blockquote,pre,code',
        // auto paragraph and remove empty nodes to keep markup tidy
        'AutoFormat.AutoParagraph' => true,
        'AutoFormat.RemoveEmpty' => true,
        // cache serializer path (ensure writable)
        'Cache.SerializerPath' => storage_path('framework/htmlpurifier'),
    ],

    // お知らせ・リマインダー用（文字色・背景色などの基本装飾を許可）
    'rich' => [
        'HTML.Allowed' => 'p,br,strong,b,em,i,u,s,strike,span[style],ul,ol,li',
        'CSS.AllowedProperties' => 'color,background-color',
        'AutoFormat.RemoveEmpty' => true,
        'Cache.SerializerPath' => storage_path('framework/htmlpurifier'),
    ],
];
