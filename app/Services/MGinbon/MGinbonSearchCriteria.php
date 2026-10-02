<?php

namespace App\Services\MGinbon;

class MGinbonSearchCriteria
{
    public function __construct(private readonly MGinbonLedgerSearch $ledgerSearch) {}

    /** @return array{version: int, requests: array<int, array<string, mixed>>} */
    public function normalize(array|string|null $criteria): array
    {
        $json = is_string($criteria) ? $criteria : json_encode($criteria ?? [], JSON_UNESCAPED_UNICODE);

        return ['version' => 2, 'requests' => $this->ledgerSearch->decode($json ?: '[]')];
    }

    public function canonicalJson(array $criteria): string
    {
        return json_encode($this->normalize($criteria), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public function hash(array $criteria): string
    {
        return hash('sha256', $this->canonicalJson($criteria));
    }

    public function summary(array $criteria): string
    {
        $labels = [
            'school_name' => '学校名', 'media' => '媒体', 'category' => '分類',
            'mikuni_code' => 'Mコード', 'n_code' => 'Nコード', 'publication_status' => '掲載',
            'note' => '備考', 'milestone' => '工程日付', 'actor' => '担当者', 'anomaly' => '異常',
        ];
        $parts = [];
        foreach ($this->normalize($criteria)['requests'] as $request) {
            $conditions = array_map(fn ($condition) => ($labels[$condition['field']] ?? $condition['field']).':'.$condition['value'], $request['conditions']);
            $parts[] = ($request['omit'] ? '除外 ' : '').implode('・', $conditions);
        }

        return mb_substr(implode(' / ', $parts) ?: '条件なし', 0, 1000);
    }
}
