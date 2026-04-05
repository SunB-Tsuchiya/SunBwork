# さくらレンタルサーバー デプロイガイド

このディレクトリの3つのスクリプトで、さくらへのデプロイを自動化しています。

## スクリプト一覧

| スクリプト | 実行環境 | 役割 |
| --- | --- | --- |
| `deploy-sakura-local.sh` | ローカル | ビルド・コミット・.env 復元 |
| `deploy-sakura-remote.sh` | さくらサーバー | git pull・migrate・キャッシュクリア |
| `deploy-sakura-all-in-one.sh` | ローカル | 上記をワンショットで実行 |

---

## 使用シーン別ガイド

### 【重要】事前準備：コア部分をコミット

**スクリプトはビルド部分（`public/build/` と `resources/js/ziggy.js`）のみを自動でコミットします。**
**Controller / Model / Migration などの変更ファイルは事前に手動でコミット必須です。**

コミット例：

```bash
# コア部分の変更をコミット
git add app/ database/ resources/
git commit -m "feat: 新しい機能の実装"

# DEPLOY_GUIDE.md など新規ファイルもコミット
git add scripts/
git commit -m "docs: デプロイガイド追加"

# その後、デプロイスクリプトを実行
bash scripts/deploy-sakura-all-in-one.sh silverlamb759 silverlamb759.sakura.ne.jp
```

---

### シーン1: 標準的なデプロイ（全自動）

**最もシンプル。ローカル側とリモート側をワンコマンドで自動実行する方法です。**

```bash
bash scripts/deploy-sakura-all-in-one.sh <sakura-user> <sakura-host> [オプション]
```

#### 例：

```bash
# 基本構文
bash scripts/deploy-sakura-all-in-one.sh silverlamb759 silverlamb759.sakura.ne.jp

# Ziggy 再生成をスキップ（routes/web.php に変更がない場合）
bash scripts/deploy-sakura-all-in-one.sh silverlamb759 silverlamb759.sakura.ne.jp --skip-ziggy

# migrate をスキップ（マイグレーションがない場合）
bash scripts/deploy-sakura-all-in-one.sh silverlamb759 silverlamb759.sakura.ne.jp --skip-migrate

# 両方スキップ
bash scripts/deploy-sakura-all-in-one.sh silverlamb759 silverlamb759.sakura.ne.jp --skip-ziggy --skip-migrate

# git push のみ、リモートは手動（CI で自動デプロイする場合など）
bash scripts/deploy-sakura-all-in-one.sh silverlamb759 silverlamb759.sakura.ne.jp --no-push
```

#### このコマンドの流れ：

1. **PHASE 1**: ローカルでビルド・コミット・.env 復元
2. **PHASE 2**: `git push origin main`
3. **PHASE 3**: SSH でさくらに接続し、`git pull・migrate・config:clear・cache:clear` を実行

---

### シーン2: 段階的実行（手動確認あり）

**各段階を个別に実行したい場合（デバッグ・動作確認の際に便利）**

#### ステップ1: ローカル側のビルド・コミット

```bash
bash scripts/deploy-sakura-local.sh
```

オプション：

```bash
# Ziggy 再生成をスキップ
bash scripts/deploy-sakura-local.sh --skip-ziggy

# git status チェックをスキップ（未コミットファイルを含める）
bash scripts/deploy-sakura-local.sh --no-git-check

# カスタムコミットメッセージ
bash scripts/deploy-sakura-local.sh --commit-msg "chore: カスタムメッセージ"
```

このスクリプト実行後：

- ✅ public/build/ が更新され、コミット済み
- ✅ .env が ローカル用（空）に復元済み
- ✅ npm run build でローカルビルドも完了

**次のステップに進むか確認してから:**

```bash
git push origin main
```

#### ステップ2: さくらサーバーでデプロイ実行

さくらに SSH 接続:

```bash
ssh silverlamb759@silverlamb759.sakura.ne.jp
cd ~/SunBWork
bash scripts/deploy-sakura-remote.sh
```

オプション：

```bash
# migrate をスキップ（マイグレーションがない場合）
bash scripts/deploy-sakura-remote.sh --skip-migrate
```

---

### シーン3: IP ホワイトリスト / 特段の理由で SSH が使えない場合

**ローカルまでのみ自動化：**

```bash
bash scripts/deploy-sakura-local.sh
git push origin main
```

その後、さくらでは手動で:

```bash
ssh <sakura-user>@<sakura-host>
cd ~/SunBWork
git pull origin main
php artisan migrate
php artisan config:clear
php artisan cache:clear
```

または、さくらのさらに安全な制御パネルから自動デプロイフックを設定する等の方法を検討してください。

---

## スクリプトの動作詳細

### `deploy-sakura-local.sh` の実行フロー

1. **git status 確認**  
   未コミットファイル（public/build 除外）をチェック。あれば確認

2. **Ziggy 再生成**（`--skip-ziggy` で省略可）  
   `routes/web.php` に変更があれば `php artisan ziggy:generate` を実行

3. **さくら用ビルド**  
   - `.env` をバックアップ
   - `VITE_APP_BASE_PATH` を `/members` に切替
   - `npm run build` で本番用アセット生成

4. **コミット**  
   `public/build/` と `resources/js/ziggy.js` をステージング＆コミット

5. **ローカル用に復元**  
   - `VITE_APP_BASE_PATH` を空に戻す
   - `npm run build` でローカル用アセット生成

### `deploy-sakura-remote.sh` の実行フロー

1. **git pull origin main**
2. **php artisan migrate**（`--skip-migrate` で省略可）
3. **php artisan config:clear**
4. **php artisan cache:clear**

### `deploy-sakura-all-in-one.sh` の実行フロー

上記3スクリプトを統合：

1. ローカルスクリプト実行
2. git push
3. SSH でリモートスクリプト実行

途中で確認待機があり、キャンセル可能です。

---

## よくある質問 / トラブルシューティング

### Q: public/build が root 所有になって次回のビルドが失敗する

Docker コンテナ内でビルドすると発生します。手動で修正:

```bash
sudo chown -R $USER:$USER public/build/
sudo chmod -R 755 public/build/assets
```

スクリプトは OS 権限で実行されるため、自動修正はできません。

### Q: SSH パスワード認証が求められる

スクリプトは自動的に SSH リモートコマンドを実行しますが、以下のいずれかで対応：

- SSH 鍵認証を設定
- `--no-push` で全自動を外し、手動で SSH 実行
- ローカルスクリプトのみ使用

### Q: git push 時に `Permission denied (publickey)` エラー

SSH 鍵認証へのセットアップが必要です。GitHub、またはさくらのキー管理を確認。

### Q: さくらサーバーで migrate エラーが出た

既知の問題として、テーブルが既に存在する等の理由で migrate が失敗することがあります。

その場合、さくら上で手動で確認:

```bash
cd ~/SunBWork
php artisan tinker
# 以下を実行:
# DB::table('migrations')->where('batch', '<確認>', '<値>')->delete();
```

詳細は `CLAUDE.md` の「さくらレンタルサーバー デプロイ設定」セクションを参照。

### Q: コミットメッセージをカスタマイズしたい

```bash
# ローカルスクリプト
bash scripts/deploy-sakura-local.sh --commit-msg "fix: 重大なバグ修正"

# 全自動スクリプト
bash scripts/deploy-sakura-all-in-one.sh silverlamb759 silverlamb759.sakura.ne.jp --commit-msg "fix: 重大なバグ修正"
```

---

## 参考資料

- [CLAUDE.md - さくらレンタルサーバー デプロイ設定](../CLAUDE.md#さくらレンタルサーバー-デプロイ設定)
- [.github/copilot-instructions.md](../.github/copilot-instructions.md)

---

## 更新履歴

- **2026-04-05**: 3つのデプロイスクリプトを作成・リリース
  - `deploy-sakura-local.sh` - ローカル側自動化
  - `deploy-sakura-remote.sh` - さくら側実行
  - `deploy-sakura-all-in-one.sh` - 統合自動化
