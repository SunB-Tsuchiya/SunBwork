# SunBWork ガイド改定 Claude向けプロンプトファイル
作成日: 2026-05-06

---

## このファイルの使い方

新しい Claude セッションを開始するとき、このファイルの内容をそのまま冒頭に貼り付けてください。
または「GUIDE_PROMPT.md を読んでガイド改定を続けてください」と指示してください。

---

## セッション開始時のプロンプト（コピーして使用）

```
あなたはこれからSunBWorkプロジェクトのガイド改定作業を行います。

まず以下のファイルを必ず順番に読んでください：
1. `/home/tchirosb/SunBWork/CLAUDE.md`（プロジェクト全体ルール・必読）
2. `/home/tchirosb/SunBWork/z_instructions/GUIDE_MANAGER.md`（作業管理書・フロー・進捗一覧）
3. `/home/tchirosb/SunBWork/z_instructions/GUIDE_PLAN.md`（各ガイドの詳細仕様・タブ構成・修正方針）

読み終えたら、以下を報告してください：
- 現在の進捗状況（未着手・進行中・完了の件数）
- 次に着手すべき推奨作業（理由も添えて）

作業は GUIDE_MANAGER.md に記載された「作業フロー（5ステップ）」と「安全ルール」に従って進めてください。
特に STEP 2（設計・方針の提示）でユーザーの「OK」を得るまで絶対に実装を始めないこと。

各GUIDE-xx作業の完了・進捗状況は必ず GUIDE_MANAGER.md に記録してください：
- 作業完了時: 進捗一覧のステータスを「✅ 完了」に更新し、作業ログに変更ファイルを記録
- 実装中の場合: ステータスを「🔨 実装中」に更新
```

---

## 設計サマリー（Claude向け補足）

### プロジェクト背景

- **業種:** 印刷・組版会社向け社内管理システム（Laravel 11 + Vue 3 + Inertia.js）
- **目的:** サイト構成が大きく変わったため、既存ガイドを現状の機能・タブメニューに合わせて全面改定する
- **対象ロール:** Admin / Leader / Coordinator / ProofAdmin / User の全5ロール
- **ProofAdmin ガイドは現在存在しない** → 新規作成が必要

### 最重要ルール（CLAUDE.md より）

1. 作業前に必ず関連コードを読む
2. 設計提示 → ユーザー確認 → 実装の順を守る
3. 質問は1つずつ
4. Vue/JSファイル変更後は `npm run build`（プロジェクトルートで実行）
5. Artisan は `docker compose exec laravel bash -lc "php artisan ..."`
6. さくら本番では `route()` 必須・ハードコードパス禁止

### 作業ID一覧と推奨順

| 順序 | ID | ロール | 内容 | ファイル種別 |
|------|-----|--------|------|-------------|
| 1 | GUIDE-U-01 | User | Userガイド改定 | Vue のみ |
| 2 | GUIDE-A-01 | Admin | Adminガイド改定 | Vue のみ |
| 3 | GUIDE-L-01 | Leader | Leaderガイド改定 | Vue のみ |
| 4 | GUIDE-P-01 | ProofAdmin | ProofAdminガイド新規作成 | PHP + routes + Vue |
| 5 | GUIDE-C-01 | Coordinator | Coordinatorガイド改定（最もボリューム大） | Vue のみ |

### 各ロールのカラー

| ロール | カラー | ヒーローバナー例 |
|--------|--------|----------------|
| Admin | red | `from-red-500 to-rose-400` |
| Leader | orange | `from-orange-500 to-amber-400` |
| Coordinator | green | `from-green-500 to-emerald-400` |
| ProofAdmin | pink | `from-pink-500 to-rose-400` |
| User | blue | `from-blue-500 to-sky-400` |

### タブ構成（実装済みのNavigation Tabsより）

**Admin タブ（AdminNavigationTabs.vue）:**
案件総覧 / 会社管理(権限要) / ユーザー管理(権限要) / 部署管理(権限要) / 日報管理(権限要) / クライアント管理(権限要) / 作業量分析(権限要) / 勤務形態設定(権限要) / 勤務時間管理(権限要) / Admin権限管理(代表者のみ) / Leader権限管理 / 会議設定

**Leader タブ（LeaderNavigationTabs.vue）:**
ユーザー管理(部署L) / 案件総覧(部署L・Admin以上) / チーム管理 / クライアント管理(権限要) / 日報管理(権限要) / 作業量分析(権限要) / 作業項目設定(権限要) / 勤務時間管理(権限要) / 派遣管理(権限要) / Leader権限管理 / 会議設定

**Coordinator タブ（CoordinatorNavigationTabs.vue）:**
クライアント管理 / 外注先管理 / 案件一覧 / ジョブ一覧 / 案件カレンダー / 進行表一覧 / 進行レポート / 設定

**ProofAdmin タブ（ProofCoordinatorNavigationTabs.vue）:**
校正依頼受信(バッジ付) / ジョブ管理 / 校正カレンダー / 校正員作業量 / 校正チーム管理 / 単発派遣管理

**User タブ（UserNavigationTabs.vue）:**
案件確認 / マイジョブBOX / 依頼されたジョブ / 日報一覧 / カレンダー / 校正状況 / 校正ジョブ(校正メンバーのみ) / 設定

### 既存ガイドページのパス

```
resources/js/Pages/Guide/Index.vue         ← ガイド一覧（ProofAdminカード追加が必要）
resources/js/Pages/Guide/Admin.vue         ← Admin ガイド（改定対象）
resources/js/Pages/Guide/Leader.vue        ← Leader ガイド（改定対象）
resources/js/Pages/Guide/Coordinator.vue   ← Coordinator ガイド（改定対象・最大）
resources/js/Pages/Guide/User.vue          ← User ガイド（改定対象）
resources/js/Pages/Guide/ProofCoordinator.vue  ← 存在しない（新規作成）
```

### GUIDE-P-01 で必要なバックエンド追加

```php
// app/Http/Controllers/GuideController.php に追加するメソッド
public function proofCoordinator()
{
    return Inertia::render('Guide/ProofCoordinator');
}

// routes/web.php に追加するルート（既存の guide グループ内）
Route::get('/proof-coordinator', [GuideController::class, 'proofCoordinator'])
    ->name('proof_coordinator');
// ※ Route::prefix('guide')->name('guide.') グループ内に記述
```

### ガイドページのデザインパターン（既存 User.vue より）

```vue
<AppLayout title="XXX向けガイド">
    <template #header>
        <div class="flex items-center gap-3">
            <Link :href="route('guide.index')" class="text-sm text-XXX-500 hover:underline">← ガイド一覧</Link>
            <span class="text-gray-300">/</span>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">XXX向けガイド</h2>
        </div>
    </template>

    <div class="space-y-6">
        <!-- ヒーローバナー -->
        <div class="overflow-hidden rounded-2xl bg-gradient-to-r from-XXX-500 to-YYY-400 p-8 text-white shadow">
            ...
        </div>

        <!-- もくじ -->
        <div class="rounded-xl border border-XXX-100 bg-XXX-50 p-5">
            <h3 class="mb-3 font-semibold text-XXX-700">📌 もくじ</h3>
            <ol class="space-y-1 text-sm text-XXX-600">
                <li><a href="#section1" class="hover:underline">1. ...</a></li>
                ...
            </ol>
        </div>

        <!-- 各セクション -->
        <div id="section1" class="scroll-mt-16 rounded-xl bg-white p-6 shadow-sm">
            <h2 class="mb-4 flex items-center gap-2 text-xl font-bold text-gray-800">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-XXX-100 text-sm text-XXX-600 font-bold">1</span>
                セクションタイトル
            </h2>
            ...
        </div>

        <!-- ガイド一覧に戻る -->
        <div class="flex justify-center pt-2 pb-4">
            <Link :href="route('guide.index')" class="...">← ガイド一覧に戻る</Link>
        </div>
    </div>
</AppLayout>
```

### ジョブステータスの正しい4段階（全ガイドで統一）

| ステータス | 条件 | バッジ色 |
|-----------|------|---------|
| 未読 | 割り当て済み・未読 | indigo |
| 確認済み | 確認済み（read_at あり） | green |
| セット済み | 予定表にセット（accepted=true） | blue |
| 完了 | 作業完了（completed=true） | yellow |

### よくある落とし穴

- さくら本番では `route()` 必須（ハードコードパス禁止）
- ガイドページは全ロールが閲覧可能なため、権限チェックはガイド一覧（Index.vue）の visible 条件で管理
- ProofAdmin ガイドの visible 条件: `['proof_coordinator', 'leader', 'admin', 'superadmin']`
- AppLayout のスロット: `#header` に「← ガイド一覧」リンクを配置する
- コメントアウトされているタブ（UserNavigationTabs.vue の校正カレンダー等）はガイドに記載しない
