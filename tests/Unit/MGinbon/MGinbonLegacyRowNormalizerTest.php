<?php

use App\Services\MGinbon\MGinbonLegacyRowNormalizer;

it('normalizes subjects dates actors and measurements without resolving people', function () {
    $row = [
        'みくにコード' => '101',
        '日能研コード' => '1721',
        '学校名' => '学校A',
        '科目' => '国語・算数・社会・理科',
        '媒体分類' => '問題',
        '入稿日_国語' => '2026/2/3',
        '初校OP_国語' => '担当候補',
        '外注scan数_国語' => '02',
        '外注作図数_国語' => null,
        '社内scan_国語' => '0',
        '社内作図数_国語' => '3',
    ];

    $result = (new MGinbonLegacyRowNormalizer)->normalize($row);
    $japanese = $result['data']['subjects']['japanese'];

    expect($result['data']['unit_type'])->toBe('exam')
        ->and($japanese['active'])->toBeTrue()
        ->and($japanese['dates']['manuscript_received_on'])->toBe('2026-02-03')
        ->and($japanese['actors']['initial_operation']['resolution'])->toBe('unresolved')
        ->and(array_column($japanese['measurements'], 'quantity'))->toBe([2, 0, 0, 3])
        ->and($result['warnings'])->toContain('non_padded_date:入稿日_国語');
});

it('keeps invalid dates out of candidates and marks them for review', function () {
    $result = (new MGinbonLegacyRowNormalizer)->normalize([
        '学校名' => '冊子共通',
        '媒体分類' => '解答',
        '科目' => null,
        '三校出_国語' => '2026/02/31',
    ]);

    expect($result['data']['unit_type'])->toBe('book_component')
        ->and($result['data']['subjects']['japanese']['dates'])->not->toHaveKey('third_shared_on')
        ->and($result['warnings'])->toContain('invalid_date:三校出_国語');
});
