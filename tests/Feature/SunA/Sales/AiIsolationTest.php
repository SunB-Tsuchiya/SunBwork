<?php

namespace Tests\Feature\SunA\Sales;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * 売上レポート（SunA/Sales）のデータを、AI が参照しないことの保証（SUNASALES_PLAN1.md §10）。
 * ソースコードの静的な検査: AI 関連のコードが売上レポートのコード・テーブル・DB接続を参照したら失敗する。
 * また、売上レポートのコードが AI や外部への送信を行わないことも検査する。
 * （DB を使わない。違反を足した人が、このテストの失敗で気づけるようにするためのもの）
 */
class AiIsolationTest extends TestCase
{
    /** 売上レポートを示す文字列（コード・テーブル・接続） */
    private const SALES_MARKERS = [
        'SunA\\Sales',
        'SunA/Sales',
        'suna_sl_',
        "connection('sales')",
        'connection("sales")',
        "'sales'",
        'App\\Models\\Sales\\',
    ];

    /** AI 機能に該当するソースファイル（アプリ内チャット・AI設定・要約ジョブ・履歴統合・Bot画面） */
    private function aiFiles(): array
    {
        $base = base_path();
        $files = [];

        foreach ([
            'app/Http/Controllers/Bot',
            'app/Jobs',
            'app/Services',
            'app/Models',
            'app/Http/Controllers/Admin',
            'app/Http/Controllers/SuperAdmin',
            'config',
            'resources/js/Pages/Bot',
            'resources/js/Pages/Chat',
        ] as $dir) {
            if (! is_dir("{$base}/{$dir}")) {
                continue;
            }
            foreach ((new Finder())->files()->in("{$base}/{$dir}")->name(['*.php', '*.vue', '*.js']) as $file) {
                $path = str_replace($base . '/', '', $file->getPathname());
                // AI に関係するファイル名だけを対象にする（Bot ディレクトリ・Bot画面は全て対象）
                $isAi = preg_match('#(^|/)(Bot|Chat)(/|$)#', $path)
                    || preg_match('#/(Ai[A-Z][A-Za-z]*|Summarize[A-Za-z]*|Ai[A-Za-z]*Setting[A-Za-z]*)\.(php|vue|js)$#', $path)
                    || preg_match('#^config/ai[_a-z]*\.php$#', $path);
                if ($isAi) {
                    $files[] = $path;
                }
            }
        }

        return array_values(array_unique($files));
    }

    public function test_ai_related_code_exists_so_that_the_guard_is_meaningful()
    {
        $files = $this->aiFiles();

        $this->assertGreaterThanOrEqual(8, count($files), 'AI 関連ファイルの検出が少なすぎる（検査対象の指定が古くなっていないか確認）');
        $this->assertContains('app/Http/Controllers/Bot/BotController.php', $files);
        $this->assertContains('app/Jobs/SummarizeConversationJob.php', $files);
    }

    public function test_ai_related_code_never_references_sales_report_code_tables_or_connection()
    {
        foreach ($this->aiFiles() as $path) {
            $source = file_get_contents(base_path($path));
            foreach (self::SALES_MARKERS as $marker) {
                $this->assertStringNotContainsString(
                    $marker,
                    $source,
                    "AI 関連のコード {$path} が売上関連（{$marker}）を参照している。売上レポートのデータは AI から参照しない設計（SUNASALES_PLAN1.md §10）"
                );
            }
        }
    }

    public function test_sales_report_code_never_calls_ai_or_external_services()
    {
        $base = base_path();
        $forbidden = ['AiConversation', 'AiMessage', 'AiSummary', 'BotController', 'openai', 'OpenAI', 'anthropic', 'Anthropic', 'Http::', 'Guzzle', 'curl_exec', 'file_get_contents(\'http'];

        $files = [];
        foreach (['app/Models/SunA/Sales', 'app/Services/SunA/Sales', 'app/Http/Controllers/SunA/Sales', 'app/Support/SunA/Sales', 'resources/js/Pages/SunA/Sales', 'resources/js/Components/SunA/Sales'] as $dir) {
            if (is_dir("{$base}/{$dir}")) {
                foreach ((new Finder())->files()->in("{$base}/{$dir}") as $file) {
                    $files[] = $file->getPathname();
                }
            }
        }
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $source = file_get_contents($file);
            foreach ($forbidden as $word) {
                $this->assertStringNotContainsString($word, $source, str_replace($base . '/', '', $file) . " が {$word} を使っている（売上レポートは AI・外部サービスへデータを送らない）");
            }
        }
    }

    public function test_ai_config_and_prompts_do_not_mention_sales_report()
    {
        // AI のプリセット・プロンプト設定に、売上レポートの文言が入っていない
        $source = file_get_contents(base_path('config/ai_presets.php'));
        $this->assertStringNotContainsString('売上レポート', $source);
        $this->assertStringNotContainsString('suna_sl', $source);
    }
}
