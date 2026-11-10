<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// サンエー印刷: 総務・経理・営業が、それぞれ別のカレンダーと予定を持つ（SUNASALES_PLAN1.md §14）。
// いまのクラークのカレンダーは「会社単位で共有」なので、どのカレンダーかを示す列 calendar_kind を足す。
//   - 空文字（既定）= 従来のカレンダー（サン・ブレーンはこのまま。挙動は変わらない）
//   - 'soumu' / 'keiri' / 'sales' = サンエー印刷の総務・経理・営業のカレンダー
// 移行: 一般会社（company_type='general'）の既存の行だけ、空文字を 'soumu' に埋める
//       （サンエー印刷は未使用で、予定が無い想定。万一あれば、総務のものとする）。
// 唯一の「追加以外」の変更: clerk_calendar_colors の unique(company_id, color_key) を
//       unique(company_id, calendar_kind, color_key) に差し替える（サン・ブレーンの行はすべて空文字なので、制約の内容は同じ）。
return new class extends Migration
{
    private const TABLES = [
        'clerk_events',
        'clerk_week_posts',
        'clerk_schedule_rules',
        'clerk_calendar_reminders',
        'clerk_calendar_colors',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('calendar_kind', 20)->default('');
            });
        }

        // 色の名前: カレンダーごとに持てるよう、一意制約を差し替える。
        // company_id の外部キーを支える索引が途切れないよう、新しい制約を先に作ってから、古い制約を外す
        Schema::table('clerk_calendar_colors', function (Blueprint $table) {
            $table->unique(['company_id', 'calendar_kind', 'color_key'], 'clerk_colors_company_kind_key_unique');
        });
        Schema::table('clerk_calendar_colors', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'color_key']);
        });

        // 一覧の絞り込み用（予定は、既存の期間の索引があるため追加しない）
        foreach (['clerk_week_posts', 'clerk_schedule_rules', 'clerk_calendar_reminders'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->index(['company_id', 'calendar_kind'], $name . '_company_kind_index');
            });
        }

        // 移行: 一般会社（サンエー印刷）の既存の行は、総務のカレンダーのものとする（テストできるよう、部品に切り出している）
        \App\Support\ClerkCalendarKindBackfill::run();
    }

    public function down(): void
    {
        foreach (['clerk_week_posts', 'clerk_schedule_rules', 'clerk_calendar_reminders'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropIndex($name . '_company_kind_index');
            });
        }

        // 一意制約を元に戻す（カレンダーごとの色が複数ある場合は、戻せない。その場合は、先に整理する）
        Schema::table('clerk_calendar_colors', function (Blueprint $table) {
            $table->unique(['company_id', 'color_key']);
        });
        Schema::table('clerk_calendar_colors', function (Blueprint $table) {
            $table->dropUnique('clerk_colors_company_kind_key_unique');
        });

        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('calendar_kind');
            });
        }
    }
};
