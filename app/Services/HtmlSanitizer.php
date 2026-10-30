<?php

namespace App\Services;

class HtmlSanitizer
{
    protected $purifier;

    public function __construct()
    {
        $config = \HTMLPurifier_Config::createDefault();
        $settings = config('htmlpurifier.settings', []);
        foreach ($settings as $k => $v) {
            $config->set($k, $v);
        }

        // ensure cache path exists
        $cachePath = $settings['Cache.SerializerPath'] ?? storage_path('framework/htmlpurifier');
        if (!is_dir($cachePath)) {
            @mkdir($cachePath, 0755, true);
        }

        $this->purifier = new \HTMLPurifier($config);
    }

    /**
     * Purify arbitrary HTML string according to configured whitelist.
     * Returns null for empty/blank input.
     */
    public function purify(?string $html): ?string
    {
        if (empty($html)) return null;
        return $this->purifier->purify($html);
    }

    /**
     * お知らせ・リマインダー用。文字色・背景色などの基本装飾だけを許可する。
     * 装飾が無い（空の段落だけの）内容は null を返す。
     */
    public function purifyRich(?string $html): ?string
    {
        if ($html === null || trim($html) === '') return null;

        $config = \HTMLPurifier_Config::createDefault();
        $settings = config('htmlpurifier.rich', []);
        foreach ($settings as $k => $v) {
            $config->set($k, $v);
        }
        $cachePath = $settings['Cache.SerializerPath'] ?? storage_path('framework/htmlpurifier');
        if (!is_dir($cachePath)) {
            @mkdir($cachePath, 0755, true);
        }

        $clean = trim((new \HTMLPurifier($config))->purify($html));

        return trim(strip_tags($clean)) === '' ? null : $clean;
    }
}
