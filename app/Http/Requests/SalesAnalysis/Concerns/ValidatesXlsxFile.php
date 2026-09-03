<?php

namespace App\Http\Requests\SalesAnalysis\Concerns;

use Illuminate\Validation\Validator;

trait ValidatesXlsxFile
{
    protected function xlsxFileRules(): array
    {
        // mimetypesルールはOS/ブラウザのfinfo判定に依存し、xlsx(zip内包)を
        // application/octet-stream 等と誤判定する環境があるため使わない。
        // 拡張子チェック＋validateXlsxMagicBytes()のZIPシグネチャ検証で安全性を担保する。
        return [
            'required',
            'file',
            'extensions:xlsx',
            'max:10240', // 暫定10MB（PLAN 3.4）
        ];
    }

    /** xlsxはZIP形式。先頭シグネチャで簡易検証する（拡張子偽装対策） */
    protected function validateXlsxMagicBytes(Validator $validator): void
    {
        $file = $this->file('file');
        if (! $file || ! $file->isValid()) {
            return;
        }

        $handle = fopen($file->getRealPath(), 'rb');
        $magic = $handle ? fread($handle, 4) : '';
        if ($handle) {
            fclose($handle);
        }

        if (! in_array($magic, ["PK\x03\x04", "PK\x05\x06"], true)) {
            $validator->errors()->add('file', 'ファイル形式が不正です（xlsxとして認識できません）。');
        }
    }
}
