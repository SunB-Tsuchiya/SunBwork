# セッション Cookie (laravel_session) による API 401 問題：概要と修正ポイント

## 概要（短く）
SPA（Inertia/Vite）からの API 呼び出しが 401（Unauthenticated.）になる問題を調査し、原因は「API ルートがセッションを開始しないステートレスなミドルウェア経路で実行されていること」であると判明しました。
ローカルで確認した挙動：
- ブラウザでログイン後に発行された `laravel_session` クッキー（暗号化済み）を使い、コンテナ内で `/debug/whoami`（web ミドルウェア経由）を呼ぶと認証される（auth()->check() == true）。
- 同じ暗号化済み cookie を `curl` で `/api/user`（デフォルトは API ミドルウェア）に投げると 401 が返る。
- API ルートを `web` ミドルウェア経由に切り替えると `/api/user` は 200 でユーザー情報を返す。

## 原因の要点
1. Laravel の `api` ミドルウェアグループはデフォルトでステートレス（セッションを開始しない）。
2. Sanctum の SPA 認証（first-party cookie）を使うにはセッション開始（StartSession）と CSRF チェックが必要。API がセッションを開始しないと `auth:sanctum` はセッションのユーザーを拾えず 401 を返す。
3. `EncryptCookies` 自体は正しく動作しており、cookie の暗号化/復号はできている（問題はミドルウェア経路）。

---

## 修正方針（短く）
選択肢：
- 簡易で確実：SPA（ブラウザ）から呼ぶエンドポイントだけを `web` ミドルウェアで実行する（今回採用）
- 代替（構成寄り）：`EnsureFrontendRequestsAreStateful` を API ミドルウェアに正しく適用し、`SANCTUM_STATEFUL_DOMAINS` を SPA オリジンで完全網羅する
- 一時回避：個別エンドポイントで `StartSession` を明示的に挿入する（デバッグ向け）

推奨は "SPA 用エンドポイントを `web` ミドルウェアで運用"（観点：最小変更、理解しやすい、確実）。

---

## AI 向け詳細手順（かなり詳しく）
目的：リポジトリ上で再現した問題を恒久的に直し、チームに分かりやすく PR を出せるようにする。

1) 事前確認
- `.env` の `SESSION_DRIVER` が `file` などであることを確認する。
- `config/session.php` の `cookie` 名、`files` パス、`encrypt` が期待どおりか確認。
- `config/sanctum.php` の `stateful` と `guard`（通常は `web`）を確認する。

2) 修正手順（コード編集）
- 変更対象ファイル: `routes/api.php`
- 変更内容（安全な最小セット）：SPA が利用するルートに対して `web` ミドルウェアを適用する。
  例：

```php
// 変更前
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// 変更後
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['web','auth:sanctum']);
```

- 同じ考えを SPA が使う他のエンドポイント（チャット未読/既読等）にも適用する。

3) サーバー側ミドルウェアの補足確認
- `bootstrap/app.php` に `%middleware->encryptCookies(...)%` が登録されていることを確認。必要に応じて `EnsureFrontendRequestsAreStateful` を API 側に追加することは可能だが、ミドルウェアの順序と cache の反映に注意する。

4) キャッシュクリアと検証
- 変更後、必ず以下を実行してキャッシュをクリアして反映する（コンテナ環境内で実行）：

```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear
```

- ブラウザでログインし、該当エンドポイントを呼ぶ（またはブラウザで発行された `laravel_session` を使い、コンテナ内で curl で呼ぶ）
- 期待結果：`/api/user` が 200 を返し、JSON でユーザーが返る

5) 追加の注意点
- `web` ミドルウェアを API に適用すると、StartSession・VerifyCsrfToken 等が有効になるため、パフォーマンスと副作用（セッションファイルの増加、cookie 書き換え等）に注意すること。
- API を外部クライアント（mobile、third-party）にも公開している場合、そちらは引き続き API トークンや bearer token を使うルートに分離しておくこと。
- 可能なら PR に検証手順（Smoke test）と revert 手順を明記しておく。

---

## 私（人間）向け説明（わかりやすく）
問題の本質：
- あなたのブラウザはログインするとサーバーから `laravel_session` というクッキーを受け取ります。このクッキーがあるとサーバーは誰がリクエストを送ったか分かります。
- でもサーバー側の "道（ミドルウェア）" によってはそのクッキーを読み取ってセッションを復元しないものがあります。API 用の道（Laravel の `api` グループ）はそのままだとセッションを読みません。だからブラウザがクッキーを送ってもサーバーはそのリクエストを "誰か分からない" と扱い 401 を返します。

今回やったこと：
- ブラウザが使う API（ユーザー情報を返す `/api/user` など）だけ、セッションを読み取る道（`web` ミドルウェア）に通すようにしました。これでブラウザが一度ログインすれば、その後の API 呼び出しで認証が通るようになります。

簡単に言うと：
- 問題はクッキーを読むか読まないかの "道" の差です。道を変えたら直りました。

---

## テスト項目（簡単）
1. SPA でログインする
2. ブラウザの Network タブで `/api/user` を呼ぶ（fetch/axios）
3. 200 とユーザー JSON が返ることを確認

---

## ロールバック / 削除手順
- もし元に戻す必要がある場合、`routes/api.php` の該当ルートを元の `->middleware('auth:sanctum')` に戻し、キャッシュクリアを実行してください。

---

## 参考（短く）
- Laravel Sanctum SPA ドキュメント: https://laravel.com/docs/sanctum
- セッションミドルウェア: `Illuminate\Session\Middleware\StartSession`

---

このファイルは AI 向けの詳しい手順と、技術に慣れていない方にも分かる説明の両方を含みます。必要なら私が PR を作って draft にしておきます。
