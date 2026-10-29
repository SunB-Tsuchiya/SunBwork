<?php

namespace App\Services\SalesAnalysis;

use App\Models\Sales\SalesExpenseNote;
use App\Models\User;

/**
 * 経費メモ（Phase24-5）。科目・支払先・月にコメントを残し、翌年度も見られるようにする。
 * 取込の版とは切り離し、年度・部署・科目・支払先の名前で紐づける。名前は名寄せ後の正式名で保存し、
 * 読み出し時にも名寄せを通す（名寄せをあとから変更しても、メモは正式名の行に表示される）。
 */
class SalesExpenseNoteService
{
    private ?int $companyId = null;

    private ?SalesExpenseAliasService $aliasService = null;

    public function forCompany(int $companyId): self
    {
        $this->companyId = $companyId;
        $this->aliasService = null;

        return $this;
    }

    private function requireCompanyId(): int
    {
        if ($this->companyId === null) {
            throw new \LogicException('SalesExpenseNoteService: forCompany()が呼ばれる前に実行されました。');
        }

        return $this->companyId;
    }

    private function aliases(): SalesExpenseAliasService
    {
        return $this->aliasService ??= (new SalesExpenseAliasService())->forCompany($this->requireCompanyId());
    }

    /** 書いた本人とSuperAdminだけが、編集・削除できる */
    public static function canModify(SalesExpenseNote $note, User $user): bool
    {
        return $user->isSuperAdmin() || (int) $note->created_by === (int) $user->id;
    }

    /**
     * 部署シート用: 指定年度と前年度のメモ（名寄せ後の名前に読み替え済み）。
     *
     * @return array{notes: array<int, array>, previous_notes: array<int, array>}
     */
    public function forDepartment(string $departmentKey, int $fiscalYear, User $viewer): array
    {
        $rows = SalesExpenseNote::where('company_id', $this->requireCompanyId())
            ->where('department_key', $departmentKey)
            ->whereIn('fiscal_year', [$fiscalYear, $fiscalYear - 1])
            ->orderBy('id')
            ->get();

        $presented = $this->present($rows, $viewer);

        return [
            'notes' => array_values(array_filter($presented, fn ($n) => $n['fiscal_year'] === $fiscalYear)),
            'previous_notes' => array_values(array_filter($presented, fn ($n) => $n['fiscal_year'] === $fiscalYear - 1)),
        ];
    }

    /**
     * 支払先カルテ用: その支払先（正式名・別名のどちらでも）に付いたメモを、全年度・全部署分、新しい年度順に返す。
     *
     * @return array<int, array>
     */
    public function forVendor(string $vendorName, User $viewer): array
    {
        $canonical = $this->aliases()->canonicalVendor($vendorName);
        $rows = SalesExpenseNote::where('company_id', $this->requireCompanyId())
            ->whereIn('vendor_name', $this->aliases()->vendorNamesFor($canonical))
            ->orderByDesc('fiscal_year')
            ->orderBy('id')
            ->get();

        return $this->present($rows, $viewer);
    }

    /** 画面に出す形へ。科目名・支払先名を正式名に読み替え、投稿者名と編集可否を付ける */
    private function present($rows, User $viewer): array
    {
        $names = User::whereIn('id', $rows->pluck('created_by')->unique()->all())->pluck('name', 'id');

        return $rows->map(fn (SalesExpenseNote $n) => [
            'id' => $n->id,
            'fiscal_year' => $n->fiscal_year,
            'department_key' => $n->department_key,
            'category' => $n->category === null ? null : $this->aliases()->canonicalCategory($n->department_key, $n->category),
            'vendor_name' => $n->vendor_name === null ? null : $this->aliases()->canonicalVendor($n->vendor_name),
            'calendar_month' => $n->calendar_month,
            'body' => $n->body,
            'author_name' => $names[$n->created_by] ?? '（不明）',
            'created_at' => $n->created_at?->toIso8601String(),
            'updated_at' => $n->updated_at?->toIso8601String(),
            'is_edited' => $n->updated_by !== null,
            'can_edit' => self::canModify($n, $viewer),
        ])->values()->all();
    }

    /** @param  array{fiscal_year:int, department_key:string, category:?string, vendor_name:?string, calendar_month:?int, body:string}  $data */
    public function create(array $data, User $author): SalesExpenseNote
    {
        $category = $data['category'] ?? null;
        $vendor = $data['vendor_name'] ?? null;

        return SalesExpenseNote::create([
            'company_id' => $this->requireCompanyId(),
            'fiscal_year' => $data['fiscal_year'],
            'department_key' => $data['department_key'],
            'category' => $category === null ? null : $this->aliases()->canonicalCategory($data['department_key'], $category),
            'vendor_name' => $vendor === null ? null : $this->aliases()->canonicalVendor($vendor),
            'calendar_month' => $data['calendar_month'] ?? null,
            'body' => trim($data['body']),
            'created_by' => $author->id,
        ]);
    }

    /** 会社内のメモを探す（他社のメモは見つからない扱い） */
    public function find(int $id): ?SalesExpenseNote
    {
        return SalesExpenseNote::where('company_id', $this->requireCompanyId())->find($id);
    }

    /** 本文と月だけを変更できる（付け先の科目・支払先は変えない） */
    public function update(SalesExpenseNote $note, string $body, ?int $calendarMonth, User $editor): SalesExpenseNote
    {
        $note->update(['body' => trim($body), 'calendar_month' => $calendarMonth, 'updated_by' => $editor->id]);

        return $note;
    }

    public function presentOne(SalesExpenseNote $note, User $viewer): array
    {
        return $this->present(collect([$note]), $viewer)[0];
    }
}
