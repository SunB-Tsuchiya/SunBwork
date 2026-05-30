# ANNEX_PLAN1.md — 通知機能拡張 設計書 v1

作成日: 2026-05-30

---

## 概要

通知（Announcement）機能に以下3つの拡張を行う。

| # | 機能 | 対象 |
|---|---|---|
| A | 会社横断通知 | サンエー印刷（general タイプ）の Clerk のみ |
| B | 添付ファイル | 全 Clerk の通知作成・受信側表示 |
| C | 編集・削除 | 送信者本人（タイトル・本文・添付のみ。受信者は変更不可） |

---

## 機能 A: 会社横断通知

### 仕様

- `company_type === 'general'` の会社の Clerk は通知作成時に「送信先の会社」を選択できる
- 会社を選択後、既存の `target_type`（全員・社員のみ・個人指定）を適用する
- それ以外の Clerk は従来通り自社内のみ

### DB 変更

```sql
-- announcements テーブルに target_company_id を追加
ALTER TABLE announcements ADD COLUMN target_company_id BIGINT UNSIGNED NULL
  AFTER sender_id,
  ADD FOREIGN KEY (target_company_id) REFERENCES companies(id) ON DELETE SET NULL;
```

- null = 送信者の所属会社（従来と同じ動作）
- 値あり = 指定会社のユーザーが送信対象

### featureFlag

`HandleInertiaRequests.php` に追加:

```php
'crossCompanyAnnouncement' => $contextCompany?->company_type === 'general'
    && in_array($user->user_role, ['clerk', 'admin', 'superadmin']),
```

> 将来 general 会社が増えた際に制限が必要なら `companies.is_group_parent` フラグを追加して絞り込む。

### コントローラー変更 (`Clerk/AnnouncementController::store()`)

```php
$targetCompanyId = $request->input('target_company_id');
// featureFlag なしで直接 company_type チェックも可
$company = $targetCompanyId
    ? Company::findOrFail($targetCompanyId)
    : $request->user()->company;

// recipient 抽出を $company->id でスコープ
$recipientIds = match ($request->target_type) {
    'all'            => User::where('company_id', $company->id)->pluck('id')->toArray(),
    'employees_only' => User::where('company_id', $company->id)
                            ->whereIn('employment_type', ['regular', 'contract'])
                            ->pluck('id')->toArray(),
    'individual'     => $request->user_ids,
};
```

---

## 機能 B: 添付ファイル

### 仕組み

既存の `attachmentables` ポリモーフィックピボットを使用（Diary・Message 等と同じ仕組み）。

```php
// Announcement モデルに追加
public function attachments(): MorphToMany
{
    return $this->morphToMany(Attachment::class, 'attachable', 'attachmentables');
}
```

### store() / update() でのアップロード処理

```php
// AttachmentService::storeUploadedFile() を使用
if ($request->hasFile('attachments')) {
    foreach ($request->file('attachments') as $file) {
        $meta = $attachmentService->storeUploadedFile($file, $request->user(),
            Announcement::class, $announcement->id);
    }
}
```

### 削除時の添付クリーンアップ

```php
foreach ($announcement->attachments as $att) {
    $attachmentService->deleteAttachment($att);
}
$announcement->delete();
```

### 表示（本文の下に画像表示）

- 画像（`mime` が `image/*`）: `<img>` で表示
- その他（PDF 等）: ファイル名リンクで表示
- Prepress/Tickets/Show.vue の実装を参考にする

---

## 機能 C: 編集・削除

### 仕様

- 編集対象: タイトル・本文・添付ファイル（受信者は変更しない）
- 削除: 受信者レコード + 添付ファイル + 本体を削除
- 権限: 送信者本人のみ（`abort_if($announcement->sender_id !== $user->id, 403)`）

### ボタン配置（CONSOLIDATED_01 準拠）

`Clerk/Announcements/Show.vue` のカード内フッターに配置:

```vue
<!-- カード下部 border-t bg-gray-50 px-5 py-3 flex -->
<div class="flex items-center justify-between border-t bg-gray-50 px-5 py-3">
  <Link :href="route('clerk.announcements.index')" class="... ">← 一覧に戻る</Link>
  <div class="flex gap-2">
    <Link :href="route('clerk.announcements.edit', { announcement: announcement.id })"
      class="rounded bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
      編集
    </Link>
    <button @click="destroy"
      class="rounded bg-red-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-red-700">
      削除
    </button>
  </div>
</div>
```

---

## 変更ファイル一覧

### Phase 1: DB + Model + Route + featureFlag

| # | ファイル | 変更内容 |
|---|---|---|
| 1-1 | `database/migrations/xxxx_add_target_company_id_to_announcements.php` | 新規 |
| 1-2 | `app/Models/Announcement.php` | `target_company_id` fillable + `attachments()` 追加 |
| 1-3 | `routes/web.php` | clerk.announcements に edit/update/destroy 追加 |
| 1-4 | `app/Http/Middleware/HandleInertiaRequests.php` | `featureFlags.crossCompanyAnnouncement` 追加 |

### Phase 2: コントローラー

| # | ファイル | 変更内容 |
|---|---|---|
| 2-1 | `app/Http/Controllers/Clerk/AnnouncementController.php` | store(): company スコープ対応 + 添付処理 |
| 2-2 | 同上 | edit(): 編集フォーム表示 |
| 2-3 | 同上 | update(): 本文+添付のみ更新 |
| 2-4 | 同上 | destroy(): 添付クリーンアップ + 削除 |
| 2-5 | 同上 | create(): companies 一覧を渡す（cross-company 用） |
| 2-6 | 同上 | show(): 添付データを追加 |

### Phase 3: フロントエンド

| # | ファイル | 変更内容 |
|---|---|---|
| 3-1 | `resources/js/Pages/Clerk/Announcements/Create.vue` | 会社セレクタ（crossCompanyAnnouncement=true時）+ 添付アップロード |
| 3-2 | `resources/js/Pages/Clerk/Announcements/Show.vue` | 添付表示 + 編集・削除ボタン |
| 3-3 | `resources/js/Pages/Clerk/Announcements/Edit.vue` | 新規作成（本文・添付編集フォーム） |
| 3-4 | `resources/js/Pages/Announcements/Show.vue` | 受信者側: 本文下に添付表示 |

### Phase 4: ビルド・確認

| # | 内容 |
|---|---|
| 4-1 | `npm run build` |
| 4-2 | `php artisan db:migrate` |
| 4-3 | 動作確認（ブラウザ） |

---

## 実装上の注意

1. 添付ファイルのアップロードは `multipart/form-data` → `useForm()` の `files` オプションを使う
2. `target_company_id` が null の場合は従来通り `$user->company_id` でスコープ
3. 受信者側 `Announcements/Show.vue` は `AnnouncementRecipient` 経由でアクセスするため、添付データは `announcement` から eager load して渡す
4. 削除時は `confirm()` ダイアログを表示してから実行

---

## 将来の拡張メモ

- `companies.is_group_parent` フラグ: general 会社が複数になった場合の細粒度制御
- 添付ファイル数の上限: 現在は無制限（必要なら validation で `max:5` 等を追加）
- 編集履歴: 現在は最終版のみ保持
