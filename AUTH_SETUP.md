# 認証設定 (Authentication Setup)

このプロジェクトは **Laravel Fortify** を使用して認証機能を実装しています。

## 使用している認証システム

- **Laravel Fortify**: 認証のバックエンド処理
- **Laravel Jetstream**: チーム機能とフロントエンド
- **Inertia.js + Vue.js**: フロントエンド

## 重要な設定ファイル

### 1. Fortifyサービスプロバイダー
**ファイル**: `app/Providers/FortifyServiceProvider.php`
- 登録画面のカスタマイズ
- 会社・部署・役職データの提供
- バリデーションルールの設定

```php
// 登録画面のビューカスタマイズ
Fortify::registerView(function () {
    $companies = \App\Models\Company::with([
        'departments' => function ($query) {
            $query->where('active', 1)
                ->with(['assignments' => function ($roleQuery) {
                    $roleQuery->where('active', 1)->orderBy('sort_order');
                }])
                ->orderBy('sort_order');
        }
    ])->where('active', 1)->get();
    
    return \Inertia\Inertia::render('Auth/Register', [
        'companies' => $companies->toArray()
    ]);
});
```

### 2. ユーザー作成アクション
**ファイル**: `app/Actions/Fortify/CreateNewUser.php`
- 新規ユーザー登録時の処理
- バリデーションルール
- チーム作成とアサイン

**対応フィールド**:
- `company_id`: 会社ID
- `department_id`: 部署ID  
- `role_id`: 役職ID
- `user_role`: 権限レベル (admin, leader, user)

### 3. フロントエンド登録画面
**ファイル**: `resources/js/Pages/Auth/Register.vue`
- 会社・部署・役職の連動ドロップダウン
- リアクティブなフォーム処理
- フォームバリデーション

## データベース関連

### モデル関係
- `Company` → `Department` (hasMany)
- `Department` → `Assignment` (hasMany)
- `User` → `Company`, `Department`, `Role` (belongsTo)

### 主要テーブル
- `companies`: 会社マスター
- `departments`: 部署マスター
- `assignments`: 役職マスター  
- `users`: ユーザー情報

## ルーティング

### Fortifyが自動生成するルート
- `GET /register`: 登録画面表示
- `POST /register`: 登録処理
- `GET /login`: ログイン画面表示
- `POST /login`: ログイン処理

### カスタムルート
**ファイル**: `routes/auth.php`
- Fortifyのルートに加えて、追加の認証関連ルートがここに定義される
- **注意**: registerルートはFortifyが自動処理するため、ここには定義しない

## 設定変更時の注意点

### 登録画面を変更したい場合
1. **フロントエンド**: `resources/js/Pages/Auth/Register.vue`
2. **データ提供**: `app/Providers/FortifyServiceProvider.php` の `registerView`
3. **バリデーション**: `app/Actions/Fortify/CreateNewUser.php`

### 新しいフィールドを追加したい場合
1. マイグレーションでDBフィールド追加
2. `CreateNewUser.php` のバリデーションと保存処理を更新
3. `Register.vue` のフォームフィールドを追加
4. 必要に応じて `FortifyServiceProvider.php` のデータ提供を更新

### デバッグ方法
- **フロントエンド**: ブラウザのコンソールログを確認
- **バックエンド**: `storage/logs/laravel.log` を確認
- **データ構造**: `/debug/register-data` エンドポイントでJSONデータを確認

## よくある問題と解決方法

### 役職ドロップダウンが表示されない
1. `FortifyServiceProvider.php` でEager Loadingが正しく設定されているか確認
2. データベースにroles データが存在するか確認
3. ブラウザコンソールでJavaScriptエラーがないか確認

### 登録処理でエラーが発生する
1. `CreateNewUser.php` のバリデーションルールを確認
2. データベースの外部キー制約を確認
3. Laravelログでエラー詳細を確認

## 削除済みファイル

以下のファイルは重複を避けるため削除済み:
- `app/Http/Controllers/Auth/RegisteredUserController.php` (Fortify使用のため不要)

## 参考リンク

- [Laravel Fortify Documentation](https://laravel.com/docs/fortify)
- [Laravel Jetstream Documentation](https://jetstream.laravel.com/)
- [Inertia.js Documentation](https://inertiajs.com/)
