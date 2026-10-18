<?php

namespace App\Support\SunA\GeneralAffairs;

use Illuminate\Support\Str;
use League\CommonMark\Extension\Table\TableExtension;

/**
 * 総務向けガイド（resources/docs/suna/general_affairs/*.md）を画面用の HTML にする。
 * リポジトリ管理の文書だが、HTML は取り除いて描画する。
 */
class MarkdownGuide
{
    /** @return array{html: string, updatedAt: ?string} */
    public static function render(string $relativePath): array
    {
        $path = resource_path('docs/suna/general_affairs/' . $relativePath);
        $exists = is_file($path);
        $markdown = $exists ? file_get_contents($path) : 'ガイドの文書が見つかりません。';

        return [
            'html' => Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false], [new TableExtension()]),
            'updatedAt' => $exists ? date('Y-m-d H:i', filemtime($path)) : null,
        ];
    }
}
