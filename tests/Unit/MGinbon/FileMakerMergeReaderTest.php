<?php

use App\Services\MGinbon\FileMakerMergeReader;

it('reads a cp932 FileMaker merge file including multiline values', function () {
    $path = tempnam(sys_get_temp_dir(), 'mginbon_merge_');
    $utf8 = "\"みくにコード\",\"学校名\",\"備考\"\r\n\"101\",\"学校A\",\"1行目\r\n2行目\"\r\n";
    file_put_contents($path, mb_convert_encoding($utf8, 'SJIS-win', 'UTF-8'));

    try {
        $result = (new FileMakerMergeReader)->read($path);
        $rows = iterator_to_array($result['rows']);

        expect($result['headers'])->toBe(['みくにコード', '学校名', '備考'])
            ->and($rows[2]['みくにコード'])->toBe('101')
            ->and($rows[2]['学校名'])->toBe('学校A')
            ->and($rows[2]['備考'])->toBe("1行目\n2行目");
    } finally {
        @unlink($path);
    }
});

it('rejects rows whose field count differs from the header', function () {
    $path = tempnam(sys_get_temp_dir(), 'mginbon_merge_');
    file_put_contents($path, "\"a\",\"b\"\n\"1\"\n");

    try {
        $result = (new FileMakerMergeReader)->read($path);
        iterator_to_array($result['rows']);
    } finally {
        @unlink($path);
    }
})->throws(RuntimeException::class, '列数が不正');
