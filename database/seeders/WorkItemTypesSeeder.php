<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 組版会社向け作業種別マスター
 *
 * グループ構成:
 *   dtp    … 組版・DTP（InDesign などのオペレーション）
 *   design … デザイン・制作（レイアウト・画像処理・原稿整理）
 *   proof  … 校正・確認（校正・赤字照合・確認）
 *   mgmt   … 進行管理・事務（書類作成・データ整理・打合せ）
 *   common … 共通（その他）
 *
 * ── 既存 slug ルール ──────────────────────────────────────────────────────
 * slug='other' は WorkloadAnalyzer 等から参照されるため変更禁止。
 * その他の既存 slug も ID を保持するために updateOrInsert で更新する。
 * 新規追加の slug は末尾に記載。
 */
class WorkItemTypesSeeder extends Seeder
{
    public function run(): void
    {
        $companyId    = DB::table('companies')->where('code', 'SUNBRAIN')->value('id');
        $departmentId = DB::table('departments')->where('code', 'INFO')->value('id');

        $types = [
            // ── 組版・DTP ──────────────────────────────────────────────────
            [
                'name'          => '組版（新規作成）',
                'slug'          => 'creation',
                'group'         => 'dtp',
                'description'   => '原稿・指定紙をもとにした新規組版作業（InDesign 等）',
                'coefficient'   => 1.25,
                'sort_order'    => 10,
            ],
            [
                'name'          => '組版（修正・赤字入力）',
                'slug'          => 'revision',
                'group'         => 'dtp',
                'description'   => '校正紙・赤字をもとにした修正作業',
                'coefficient'   => 1.0,
                'sort_order'    => 11,
            ],
            [
                'name'          => '赤字照合',
                'slug'          => 'proofreading against corrections',
                'group'         => 'dtp',
                'description'   => '修正前後の版面と赤字の照合確認',
                'coefficient'   => 0.75,
                'sort_order'    => 12,
            ],
            [
                'name'          => 'データ変換・整備',
                'slug'          => 'data_conversion',
                'group'         => 'dtp',
                'description'   => 'PDF 書き出し・データ変換・ファイル整備',
                'coefficient'   => 0.8,
                'sort_order'    => 13,
            ],

            // ── デザイン・制作 ────────────────────────────────────────────
            [
                'name'          => 'デザイン（新規）',
                'slug'          => 'design_new',
                'group'         => 'design',
                'description'   => '新規デザイン・レイアウト制作',
                'coefficient'   => 1.5,
                'sort_order'    => 20,
            ],
            [
                'name'          => 'デザイン（修正）',
                'slug'          => 'design_revision',
                'group'         => 'design',
                'description'   => 'デザイン・レイアウトの修正対応',
                'coefficient'   => 1.0,
                'sort_order'    => 21,
            ],
            [
                'name'          => '画像処理・レタッチ',
                'slug'          => 'image_processing',
                'group'         => 'design',
                'description'   => 'Photoshop 等による写真・画像の加工・切り抜き・補正',
                'coefficient'   => 1.0,
                'sort_order'    => 22,
            ],
            [
                'name'          => '原稿整理・編集',
                'slug'          => 'editing',
                'group'         => 'design',
                'description'   => '原稿の整理・テキスト編集・体裁整形',
                'coefficient'   => 0.9,
                'sort_order'    => 23,
            ],

            // ── 校正・確認 ────────────────────────────────────────────────
            [
                'name'          => '校正',
                'slug'          => 'proofreading',
                'group'         => 'proof',
                'description'   => '初校〜再校等の校正作業',
                'coefficient'   => 1.0,
                'sort_order'    => 30,
            ],
            [
                'name'          => '確認・照合',
                'slug'          => 'confirmation',
                'group'         => 'proof',
                'description'   => '版面・データの最終確認・照合',
                'coefficient'   => 0.75,
                'sort_order'    => 31,
            ],

            // ── 進行管理・事務 ────────────────────────────────────────────
            [
                'name'          => '書類作成',
                'slug'          => 'document_creation',
                'group'         => 'mgmt',
                'description'   => '見積書・スケジュール表・仕様書等の作成',
                'coefficient'   => 1.0,
                'sort_order'    => 40,
            ],
            [
                'name'          => 'データ整理・管理',
                'slug'          => 'data_management',
                'group'         => 'mgmt',
                'description'   => '入稿データの整理・保管・バージョン管理',
                'coefficient'   => 0.75,
                'sort_order'    => 41,
            ],
            [
                'name'          => '打合せ・確認対応',
                'slug'          => 'meeting',
                'group'         => 'mgmt',
                'description'   => '社内外の打合せ・電話・メール対応等',
                'coefficient'   => 1.0,
                'sort_order'    => 42,
            ],

            // ── 営業 ──────────────────────────────────────────────────────
            [
                'name'          => '架電・電話対応',
                'slug'          => 'sales_call',
                'group'         => 'sales',
                'description'   => '顧客・取引先への架電、受電対応',
                'coefficient'   => 1.0,
                'sort_order'    => 50,
            ],
            [
                'name'          => '顧客訪問・営業活動',
                'slug'          => 'sales_visit',
                'group'         => 'sales',
                'description'   => '得意先訪問、展示会等の営業活動',
                'coefficient'   => 1.0,
                'sort_order'    => 51,
            ],
            [
                'name'          => '見積・提案書作成',
                'slug'          => 'sales_estimate',
                'group'         => 'sales',
                'description'   => '見積書・提案書・プレゼン資料の作成',
                'coefficient'   => 1.0,
                'sort_order'    => 52,
            ],
            [
                'name'          => '受注・発注処理',
                'slug'          => 'sales_order',
                'group'         => 'sales',
                'description'   => '受注登録、外注発注、納期調整等',
                'coefficient'   => 1.0,
                'sort_order'    => 53,
            ],
            [
                'name'          => '伝票・請求書処理',
                'slug'          => 'sales_invoice',
                'group'         => 'sales',
                'description'   => '納品伝票・請求書・支払処理等',
                'coefficient'   => 0.75,
                'sort_order'    => 54,
            ],
            [
                'name'          => '社内報告・情報共有',
                'slug'          => 'sales_report',
                'group'         => 'sales',
                'description'   => '案件進捗報告、社内共有、日報等',
                'coefficient'   => 0.75,
                'sort_order'    => 55,
            ],

            // ── 共通 ──────────────────────────────────────────────────────
            [
                'name'          => 'その他',
                'slug'          => 'other',   // 変更禁止: WorkloadAnalyzer 等から参照
                'group'         => 'common',
                'description'   => 'その他の作業',
                'coefficient'   => 1.0,
                'sort_order'    => 99,
            ],
        ];

        foreach ($types as $t) {
            $t['company_id']    = $companyId;
            $t['department_id'] = $departmentId;
            DB::table('work_item_types')->updateOrInsert(['slug' => $t['slug']], $t);
        }
    }
}
