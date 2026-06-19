<?php

namespace Database\Seeders;

use App\Models\DemoPage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoPageSeeder extends Seeder
{
    public function run(): void
    {
        // 初回実行者: SuperAdmin の最初のユーザー
        $superAdmin = User::where('user_role', 'superadmin')->orderBy('id')->first();
        if (! $superAdmin) {
            $this->command->warn('SuperAdmin ユーザーが見つかりません。Seeder をスキップします。');
            return;
        }

        // 既存の n-demo ページを .env の認証情報で登録
        $password = config('nsystem.guest.password');
        $email    = config('nsystem.guest.email')    ?? 'guest@n-demo.local';

        if (! is_string($password) || $password === '') {
            throw new \RuntimeException('NSYSTEM_GUEST_PASSWORD が設定されていません。');
        }

        $page = DemoPage::updateOrCreate(
            ['slug' => 'n-demo'],
            [
                'name'        => '入試データ デモ（NDB SAMPLE）',
                'description' => '中学入試問題のDB化デモページ。学校一覧・問題閲覧・全文検索を体験できる。',
                'password'    => Hash::make($password),
                'is_active'   => true,
                'expires_at'  => null,
                'created_by'  => $superAdmin->id,
            ]
        );

        // 許可メールアドレスを登録（重複スキップ）
        $page->emails()->firstOrCreate(
            ['email' => $email],
            ['label' => '初期ゲストアカウント']
        );

        $this->command->info("DemoPage 登録完了: [{$page->slug}] {$page->name}");
        $this->command->info("許可メール: {$email}");
    }
}
