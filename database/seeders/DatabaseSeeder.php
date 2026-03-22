<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * ============================================================
     * 【デプロイ手順】
     * 本番（さくらレンタルサーバー）へのデプロイ時は以下を実行:
     *   php artisan migrate
     *   php artisan db:seed
     *
     * サンプルデータ（z_ プレフィックス）は開発環境専用です。
     * 本番デプロイ時は $sampleData = false のままにしてください。
     * ============================================================
     */
    public function run(): void
    {
        // ────────────────────────────────────────────────────────
        // 本番・開発共通マスターデータ（必ず実行）
        // 全 Seeder は updateOrInsert / firstOrCreate で冪等性あり
        // ────────────────────────────────────────────────────────
        $this->call([
            // Superadmin 会社・ユーザー・チーム（依存関係の起点）
            CreateSuperadminCompanySeeder::class,
            CreateSuperadminSeeder::class,
            CreateSuperadminTeamSeeder::class,

            // 本体企業・部署・役職・チーム・勤務形態
            CompanySeeder::class,
            DepartmentSeeder::class,
            AssignmentSeeder::class,
            TeamSeeder::class,
            WorktypeSeeder::class,

            // AI プリセット（config/ai_presets.php から読み込み）
            AiPresetsSeeder::class,

            // 作業種別・サイズ・ステージ・難易度マスター
            WorkItemTypesSeeder::class,
            SizesSeeder::class,
            StagesSeeder::class,
            DifficultiesSeeder::class,

            // ステータスマスター（status_id カラム用 key 付き）
            StatusesSeeder::class,
            StatusesTableSeeder::class,

            // 作業項目プリセット
            WorkItemPresetsSeeder::class,

            // 「その他」クライアント・案件（EventController が参照する共通レコード）
            OtherClientProjectSeeder::class,
        ]);

        // ────────────────────────────────────────────────────────
        // 開発環境専用サンプルデータ
        // 本番デプロイ時は false のままにすること
        // ────────────────────────────────────────────────────────
        $sampleData = false; // 本番: false / 開発環境でサンプルデータが必要なら true に変更

        if ($sampleData) {
            $this->call([
                z_SampleAdminUserSeeder::class,  // Admin サンプルユーザー (ito@test.com)
                z_SampleUsers22Seeder::class,    // 22名 サンプルユーザー
                z_SampleDiariesSeeder::class,    // サンプル日報
                z_ClientSeeder::class,           // サンプル得意先（朝日デザイン等）
                z_ProjectJobsSeeder::class,      // サンプル案件（クライアントに紐付く）
            ]);
        }
    }
}
