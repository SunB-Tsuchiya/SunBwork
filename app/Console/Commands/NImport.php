<?php

namespace App\Console\Commands;

use App\Models\NSystem\NAnswersDaimon;
use App\Models\NSystem\NQuestionsDaimon;
use App\Models\NSystem\NSchool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class NImport extends Command
{
    protected $signature = 'n:import {--force : 確認をスキップして実行}';

    protected $description = '入試データ（n_import/*.json）をDBにインポートする';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('n_schools / n_questions_daimon / n_answers_daimon を upsert します。続けますか？')) {
            $this->line('キャンセルしました。');
            return self::SUCCESS;
        }

        // --- 1. 学校マスタ ---
        $schoolsPath = Storage::disk('local')->path('n_import/schools_index.json');
        if (! file_exists($schoolsPath)) {
            $this->error("schools_index.json が見つかりません: {$schoolsPath}");
            return self::FAILURE;
        }

        $schools = json_decode(file_get_contents($schoolsPath), true);
        $schoolCount = 0;
        foreach ($schools as $s) {
            NSchool::updateOrCreate(
                ['code' => $s['code'], 'year' => $s['year']],
                ['name' => $s['name'], 'category' => $s['category'] ?? '']
            );
            $schoolCount++;
        }
        $this->info("学校マスタ: {$schoolCount} 件 upsert 完了");

        // school_id 解決用キャッシュ（code+year → id）
        $schoolMap = NSchool::all()->keyBy(fn($s) => "{$s->code}_{$s->year}")->map->id->toArray();

        // --- 2. 問題・解答 JSON ---
        $importDir = Storage::disk('local')->path('n_import');
        $files = glob("{$importDir}/*.json");

        $pattern = '/^([A-Za-z0-9]{4})(\d{4})__(Q|A)(Ko|Sa|Sh|Ri)\.json$/';

        $questionCount = 0;
        $answerCount   = 0;
        $skipCount     = 0;
        $errorCount    = 0;

        $bar = $this->output->createProgressBar(count($files));
        $bar->start();

        foreach ($files as $filepath) {
            $filename = basename($filepath);

            // schools_index.json はスキップ
            if ($filename === 'schools_index.json') {
                $bar->advance();
                continue;
            }

            if (! preg_match($pattern, $filename, $m)) {
                $bar->advance();
                continue;
            }

            [, $code, $year, $type, $subject] = $m;
            $key = "{$code}_{$year}";

            if (! isset($schoolMap[$key])) {
                $skipCount++;
                $bar->advance();
                continue;
            }

            $schoolId = $schoolMap[$key];
            $json = json_decode(file_get_contents($filepath), true);

            if (! is_array($json)) {
                $errorCount++;
                $bar->advance();
                continue;
            }

            foreach ($json as $item) {
                $bodyHtml = str_replace('src="images/', 'src="/n_images/', $item['body_html'] ?? '');
                $bodyText = $item['body_text'] ?? '';
                $daimonIndex = $item['daimon_index'] ?? 0;

                $attrs = [
                    'school_id'    => $schoolId,
                    'subject'      => $subject,
                    'daimon_index' => $daimonIndex,
                ];
                $values = [
                    'body_html' => $bodyHtml,
                    'body_text' => $bodyText,
                ];

                try {
                    if ($type === 'Q') {
                        NQuestionsDaimon::updateOrCreate($attrs, $values);
                        $questionCount++;
                    } else {
                        NAnswersDaimon::updateOrCreate($attrs, $values);
                        $answerCount++;
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("問題 upsert: {$questionCount} 件");
        $this->info("解答 upsert: {$answerCount} 件");
        if ($skipCount) {
            $this->warn("school_id 未解決スキップ: {$skipCount} ファイル");
        }
        if ($errorCount) {
            $this->error("エラー: {$errorCount} 件");
        }

        return self::SUCCESS;
    }
}
