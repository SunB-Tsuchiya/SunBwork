<?php

namespace App\Console\Commands;

use App\Models\Portfolio\PortfolioItem;
use App\Models\Portfolio\PortfolioItemPage;
use App\Services\Portfolio\PdfRasterizer;
use App\Services\Portfolio\PortfolioStorage;
use Illuminate\Console\Command;

/**
 * 保存済みの PDF 作品のページ画像を、原本の PDF から作り直す。
 * 2026-11-18 の CMYK PDF ネガポジ反転の修正前に取り込んだ作品を直すためのもの（原本 PDF は保存してあるので再変換できる）。
 * 1 作品ずつ「全ページを先にメモリ上で変換 → 成功したら書き込む」ので、途中で失敗しても元の画像は壊れない。
 * 画像の作品は原本を保存していないため、対象外（本人が再アップロードする）。
 */
class RegeneratePortfolioPdfPages extends Command
{
    protected $signature = 'portfolio:regenerate-pdf-pages {--item= : 作品 ID を 1 件指定する} {--dry-run : 変更せず対象だけ表示する}';

    protected $description = 'ポートフォリオの PDF 作品のページ画像を、原本 PDF から作り直す';

    public function handle(PdfRasterizer $rasterizer): int
    {
        $query = PortfolioItem::where('type', 'pdf')->where('status', 'ready')->whereNotNull('file_path');
        if ($this->option('item')) {
            $query->whereKey((int) $this->option('item'));
        }
        $items = $query->orderBy('id')->get();
        $dry = (bool) $this->option('dry-run');
        $disk = PortfolioStorage::disk();
        $ok = 0;
        $ng = 0;

        foreach ($items as $item) {
            $label = sprintf('#%d user=%d "%s" (%dページ)', $item->id, $item->user_id, $item->title, $item->page_count);
            if (! $disk->exists($item->file_path)) {
                $this->warn($label . ' … 原本 PDF が見つからないため飛ばしました');
                $ng++;
                continue;
            }
            if ($dry) {
                $this->line($label . ' … 対象');
                continue;
            }

            try {
                $abs = $disk->path($item->file_path);
                $base = preg_replace('/\.pdf$/', '', $item->file_path);

                // 先に全ページを変換する（途中で失敗したら何も書き込まない）
                $rendered = [];
                for ($n = 1; $n <= $item->page_count; $n++) {
                    $rendered[$n] = $rasterizer->renderPage($abs, $n - 1);
                }

                foreach ($rendered as $n => $out) {
                    $path = sprintf('%s_p%03d.jpg', $base, $n);
                    $disk->put($path, $out['blob']);
                    PortfolioItemPage::updateOrCreate(
                        ['item_id' => $item->id, 'page_no' => $n],
                        ['path' => $path, 'width' => $out['width'], 'height' => $out['height']],
                    );
                }

                $thumbPath = $base . '_t.jpg';
                $disk->put($thumbPath, $rasterizer->thumbnail($rendered[1]['blob']));

                $total = $disk->size($item->file_path) + $disk->size($thumbPath);
                foreach ($item->pages()->get() as $p) {
                    $total += $disk->size($p->path);
                }
                $item->update([
                    'thumb_path' => $thumbPath,
                    'width' => $rendered[1]['width'],
                    'height' => $rendered[1]['height'],
                    'size_bytes' => $total,
                ]);
                $this->info($label . ' … 再変換しました');
                $ok++;
            } catch (\Throwable $e) {
                report($e);
                $this->error($label . ' … 失敗: ' . $e->getMessage());
                $ng++;
            }
        }

        $this->line(sprintf('完了: 成功 %d 件 / 失敗・スキップ %d 件%s', $ok, $ng, $dry ? '（dry-run：変更なし。対象 ' . $items->count() . ' 件）' : ''));

        return $ng > 0 ? self::FAILURE : self::SUCCESS;
    }
}
