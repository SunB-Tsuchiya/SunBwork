<?php

use App\Services\MGinbon\FileMakerMergeReader;
use App\Services\MGinbon\MGinbonImportPreviewService;

it('summarizes school, book component, and duplicate candidates without database writes', function () {
    $path = tempnam(sys_get_temp_dir(), 'mginbon_preview_');
    $content = <<<'CSV'
"みくにコード","日能研コード","学校名","媒体分類"
"101","1721","学校A","問題"
"101","1721","学校A","問題"
"","","表紙","解答"
CSV;
    file_put_contents($path, $content."\n");

    try {
        $summary = (new MGinbonImportPreviewService(new FileMakerMergeReader))->preview($path);

        expect($summary['columns'])->toBe(4)
            ->and($summary['records'])->toBe(3)
            ->and($summary['school_records'])->toBe(2)
            ->and($summary['book_component_records'])->toBe(1)
            ->and($summary['missing_both_codes'])->toBe(1)
            ->and($summary['duplicate_identity_candidates'])->toBe(1);
    } finally {
        @unlink($path);
    }
});
