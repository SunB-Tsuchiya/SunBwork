<?php

namespace App\Services\SunA\Sales;

use App\Models\SunA\Sales\AuditLog;
use App\Models\SunA\Sales\Fact;
use App\Models\SunA\Sales\Import;
use App\Models\SunA\Sales\ImportChange;
use App\Models\SunA\Sales\ImportSnapshot;
use Illuminate\Support\Facades\DB;

/**
 * 取込の取り消し（SUNASALES_PLAN1.md §16）。**最新の取込だけ**、取り込む前の状態に完全に戻す。
 *  - 追加された行 = 変更ログの「追加」の記録（得意先・年・月）で特定して削除する（import_id では特定しない。更新された行も import_id が書き換わるため）
 *  - 変更された行 = 取込の確定時に保存した「変更前の姿」に戻す
 *  - 削除された行 = 保存した「削除前の姿」を、同じ id で復元する
 * 変更前の姿が保存されていない取込（この機能より前の取込）は、変更ログが「追加」だけのときに限り取り消せる。
 * 会社のコンテキスト（SunaCompanyContext）が必要。監査ログには件数だけを残す。
 */
class ImportUndoService
{
    /** @return array{can_undo:bool, reason:?string} */
    public function eligibility(Import $import): array
    {
        if ($import->status !== 'completed') {
            return ['can_undo' => false, 'reason' => $import->status === 'undone' ? '取消済みです。' : 'この取込は取り消せません。'];
        }
        $latestId = Import::query()->where('status', 'completed')->max('id');
        if ((int) $latestId !== (int) $import->id) {
            return ['can_undo' => false, 'reason' => 'より新しい取込があります。新しい取込から順に取り消してください。'];
        }
        $needsSnapshot = ImportChange::query()->where('import_id', $import->id)->whereIn('kind', ['changed', 'removed'])->exists();
        if ($needsSnapshot && ! ImportSnapshot::query()->where('import_id', $import->id)->exists()) {
            return ['can_undo' => false, 'reason' => '変更前の内容が保存されていない取込（この機能より前の取込）のため、取り消せません。'];
        }

        return ['can_undo' => true, 'reason' => null];
    }

    /**
     * 取り消しを実行する。条件を満たさないとき、または想定と違うデータのときは、何も変更せず例外にする。
     *
     * @return array{added_removed:int, restored_changed:int, restored_deleted:int}
     */
    public function undo(int $importId, int $userId): array
    {
        $companyId = SunaCompanyContext::requireId();

        return DB::connection('sales')->transaction(function () use ($importId, $userId, $companyId) {
            // 同時に取り消しや新しい取込が走らないよう、取込の行をロックして、最新であることを再確認する
            $import = Import::query()->whereKey($importId)->lockForUpdate()->first();
            if ($import === null) {
                throw new SunaSalesImportException('取込が見つかりません。');
            }
            $check = $this->eligibility($import);
            if (! $check['can_undo']) {
                throw new SunaSalesImportException($check['reason']);
            }

            // 1) 追加された行を削除（変更ログの「追加」で特定）
            $added = ImportChange::query()->where('import_id', $import->id)->where('kind', 'added')->where('field', 'amount')
                ->get(['client_key', 'sales_year', 'sales_month']);
            $addedSet = [];
            foreach ($added as $a) {
                $addedSet[$a->client_key . '|' . $a->sales_year . '|' . $a->sales_month] = true;
            }
            $deleteIds = [];
            foreach (array_chunk(array_unique($added->pluck('client_key')->all()), 300) as $keys) {
                foreach (Fact::query()->whereIn('client_key', $keys)->get(['id', 'client_key', 'sales_year', 'sales_month']) as $f) {
                    if (isset($addedSet[$f->client_key . '|' . $f->sales_year . '|' . $f->sales_month])) {
                        $deleteIds[] = $f->id;
                    }
                }
            }
            if (count($deleteIds) !== count($addedSet)) {
                // 追加した行が既に無い・増えている = 想定と違う状態。壊さないために中止する
                throw new SunaSalesImportException('追加された行の数が、取込の記録と一致しません。取り消しを中止しました。');
            }
            foreach (array_chunk($deleteIds, 500) as $ids) {
                Fact::query()->whereIn('id', $ids)->delete();
            }

            // 2) 変更された行を元に戻す
            $changed = 0;
            foreach (ImportSnapshot::query()->where('import_id', $import->id)->where('kind', 'updated')->get() as $snap) {
                $changed += Fact::query()->whereKey($snap->fact_id)->update([
                    'client_name' => $snap->client_name, 'department_key' => $snap->department_key, 'section_key' => $snap->section_key,
                    'amount' => $snap->amount, 'order_count' => $snap->order_count,
                    'import_id' => $snap->fact_import_id, 'updated_at' => $snap->fact_updated_at,
                ]);
            }

            // 3) 削除された行を復元（同じ id・同じ作成日時で）
            $restore = [];
            foreach (ImportSnapshot::query()->where('import_id', $import->id)->where('kind', 'deleted')->get() as $snap) {
                $restore[] = [
                    'id' => $snap->fact_id, 'client_key' => $snap->client_key, 'client_name' => $snap->client_name,
                    'sales_year' => $snap->sales_year, 'sales_month' => $snap->sales_month,
                    'department_key' => $snap->department_key, 'section_key' => $snap->section_key,
                    'amount' => $snap->amount, 'order_count' => $snap->order_count,
                    'import_id' => $snap->fact_import_id, 'created_at' => $snap->fact_created_at, 'updated_at' => $snap->fact_updated_at,
                ];
            }
            Fact::insertScoped($restore);

            $result = ['added_removed' => count($deleteIds), 'restored_changed' => $changed, 'restored_deleted' => count($restore)];

            // 取消済みにして、保存した変更前の姿は削除する（取り消した取込のぶん）
            ImportSnapshot::query()->where('import_id', $import->id)->delete();
            $import->update(['status' => 'undone', 'undone_at' => now(), 'undone_by' => $userId]);

            AuditLog::create([
                'user_id' => $userId,
                'action' => 'import_undo',
                'target_type' => 'suna_sl_imports',
                'target_id' => $import->id,
                // 件数だけ（得意先名・金額・ファイル内容は入れない）
                'context' => $result,
            ]);

            return $result;
        });
    }
}
