# さくらレンタルサーバー デプロイ手順書（AI Agent 向け）

> この手順書は **AI Agent が迷わず安全にデプロイできること** を目的としています。
> 各ステップを上から順に実行し、スキップしないでください。

---

## 0. 前提確認

| 項目 | 値 |
|---|---|
| ローカル作業ディレクトリ | `/home/tchirosb/SunBWork` |
| ローカル .env の `VITE_APP_BASE_PATH` | **空（`VITE_APP_BASE_PATH=`）** |
| さくら本番 .env の `VITE_APP_BASE_PATH` | `/members` |
| node バージョン | v24（ホスト npm を使用） |
| npm バージョン | v11 |

> ⚠️ **さくら SSH のリポジトリディレクトリ**
> さくら SSH でのデプロイは **`cd ~/SunBWork`** が正しい。
> `~/www/members` はシンボリックリンクが置かれた公開ディレクトリであり、`git pull` や `php artisan` を実行する場所ではない。

---

## 1. 作業前チェック — 未コミット漏れの確認

```bash
# public/build は無視して未コミットを確認
git status --short | grep -v "public/build"
```

**確認すること:**
- Controller / Model / Migration / routes ファイルが漏れていないか
- **`??` で表示される未追跡ファイルも確認する** — `z_instructions/` の新規ファイルは必ずコミット対象に含めること
- `routes/web.php` を変更した場合は **Ziggy 再生成が必要**（→ ステップ2へ）
- 変更なければステップ3へ

---

## 2. routes/web.php を変更した場合のみ — Ziggy 再生成

```bash
docker compose exec laravel bash -lc "php artisan ziggy:generate resources/js/ziggy.js"
```

> Ziggy を再生成しないと `route()` が 404 を返す。忘れずに。

---

## 3. さくら用ビルド

### ⚠️ VITE_APP_BASE_PATH の切り替えは **必ずこの順序**で行う

```bash
# ① .env を さくら用 に切り替え（/members を設定）
sed -i 's/^VITE_APP_BASE_PATH=$/VITE_APP_BASE_PATH=\/members/' /home/tchirosb/SunBWork/.env

# ② さくら用ビルド実行
npm run build

# ③ ビルド成功を確認してからコミットへ進む
```

> **注意:** `sed -i` はWSL/Linux環境。さくらサーバ上では BSD版のため `-i ''` が必要だが、
> ここはローカルでの操作なので `-i`（引数なし）で正しい。

---

## 4. コミット

```bash
# 変更したファイルを明示的に指定してステージング
git add <変更したController/Model/Migration/routesファイル> \
        public/build/ \
        resources/js/ziggy.js   # routes/web.php を変更した場合のみ

# z_instructions/ に新規ファイルがあれば必ず追加
git add z_instructions/   # 新規ドキュメントがあれば

git commit -m "feat/fix/build: <変更内容の説明>"
```

---

## 5. ローカル用ビルドへ戻す（コミット直後に必ず実行）

### ⚠️ これを忘れると次回の開発ビルドが `/members` ベースパスになり、ローカル環境が壊れる

```bash
# .env をローカル用に戻す（/members を空にする）
sed -i 's/^VITE_APP_BASE_PATH=\/members$/VITE_APP_BASE_PATH=/' /home/tchirosb/SunBWork/.env

# ローカル用ビルドを実行
npm run build
```

> **このビルドはコミットしない。** ローカル専用のビルドです。

---

## 6. ユーザーへ伝えるメッセージ（コピペ用）

**SSH 接続先:** `silverlamb759@silverlamb759.sakura.ne.jp`

> ⚠️ **`php artisan migrate` / `php artisan db:seed` はワンライナー SSH で実行すると対話確認を求められて失敗する。**
> 必ず `--force` を付けること。シーダーは `--force` が不要だが migrate は必須。

### マイグレーションあり（テーブル追加・カラム変更がある場合）

```bash
git push origin main
```

```bash
ssh silverlamb759@silverlamb759.sakura.ne.jp "cd ~/SunBWork && git pull && php artisan migrate --force && php artisan config:clear && php artisan cache:clear"
```

### マイグレーションなし（JS/CSS/PHP のみ変更の場合）

```bash
git push origin main
```

```bash
ssh silverlamb759@silverlamb759.sakura.ne.jp "cd ~/SunBWork && git pull && php artisan config:clear && php artisan cache:clear"
```

### ChangelogSeeder も反映する場合

```bash
ssh silverlamb759@silverlamb759.sakura.ne.jp "cd ~/SunBWork && git pull && php artisan migrate --force && php artisan config:clear && php artisan cache:clear && php artisan db:seed --class=ChangelogSeeder"
```

### LabelMasterSeeder など任意のシーダーも反映する場合

```bash
ssh silverlamb759@silverlamb759.sakura.ne.jp "cd ~/SunBWork && git pull && php artisan migrate --force && php artisan config:clear && php artisan cache:clear && php artisan db:seed --class=LabelMasterSeeder"
```

> **ワンライナーが失敗する場合の代替手順:** SSH でログインしてから実行する
> ```bash
> ssh silverlamb759@silverlamb759.sakura.ne.jp
> cd ~/SunBWork
> git pull
> php artisan migrate --force
> php artisan db:seed --class=LabelMasterSeeder
> php artisan config:clear && php artisan cache:clear
> ```

---

## ⚠️ public/ 直下の静的ファイルに関する注意

`~/www/members/` は `~/SunBWork/public/` の**シンボリックリンクではなく実体ディレクトリ**。
`build/` と `storage/` のみがシンボリックリンクとなっている。

**影響するファイル（変更時は手動コピーが必要）:**
- `favicon.ico`
- `favicon.svg`
- `apple-touch-icon.png`
- `robots.txt`
- `public/` 直下に置く任意の静的ファイル

**コピーコマンド（さくら SSH で実行）:**
```bash
cp ~/SunBWork/public/favicon.ico ~/www/members/favicon.ico
cp ~/SunBWork/public/favicon.svg ~/www/members/favicon.svg
cp ~/SunBWork/public/apple-touch-icon.png ~/www/members/apple-touch-icon.png
```

> `public/build/` 配下のアセットはシンボリックリンク経由で自動反映されるため不要。

---

## ステップ全体のフロー図

```
[ステップ1] git status 確認
     ↓ routes/web.php 変更あり?
     Yes → [ステップ2] Ziggy 再生成
     No  ↓
[ステップ3] VITE=空 → VITE=/members に sed で変更 → npm run build
     ↓ ビルド成功?
     No → エラーを調査・修正してからやり直し（.envをローカル用に戻すのを忘れずに）
     Yes ↓
[ステップ4] git add + git commit
     ↓
[ステップ5] VITE=/members → VITE=空 に sed で変更 → npm run build（コミットしない）
     ↓
[ステップ6] ユーザーへ push & さくら側デプロイ手順を伝える
```

---

## よくあるミスと対処

### ミス1: ステップ5を忘れた（.env が /members のまま）

**症状:** 次の `npm run build` で生成されるアセットのパスが `/members/build/assets/...` になり、
ローカル (`http://localhost:8000`) で 404 になる。

**対処:**
```bash
# .env の現状確認
grep VITE_APP_BASE_PATH /home/tchirosb/SunBWork/.env

# /members になっていたらローカル用に戻す
sed -i 's/^VITE_APP_BASE_PATH=\/members$/VITE_APP_BASE_PATH=/' /home/tchirosb/SunBWork/.env
npm run build
```

---

### ミス2: ビルドが EACCES Permission denied で失敗する

**原因:** Docker コンテナ内でビルドした場合、`public/build/assets/` が root 所有になる。

**対処:**
```bash
sudo chown -R $USER:$USER public/build/
sudo chmod -R 755 public/build/assets
npm run build
```

---

### ミス3: routes/web.php を変更したのに Ziggy 再生成を忘れた

**症状:** `route('xxx.yyy')` が undefined になる、または古いURLを返す。

**対処:**
```bash
docker compose exec laravel bash -lc "php artisan ziggy:generate resources/js/ziggy.js"
# ziggy.js をステージングし直してコミット・ビルドをやり直す
git add resources/js/ziggy.js
git commit -m "fix: Ziggy 再生成"
# → ステップ3からやり直し
```

---

### ミス4: さくらで php artisan migrate を忘れた

**症状:** ローカルは正常に動くが、さくら本番でページが壊れる（500 ではなく props が空になるなど）。

**対処:** さくら SSH でマイグレーションを実行:
```bash
cd ~/SunBWork
php artisan migrate --force
php artisan config:clear
```

### ミス5（追加）: SSH ワンライナーで migrate が対話確認を求めて失敗する

**症状:** `ssh ... "... && php artisan migrate && ..."` を実行すると、本番環境保護の確認入力を求めて止まる。

**対処:** `--force` を付ける（ワンライナー時は必須）:
```bash
# NG
ssh host "cd ~/SunBWork && php artisan migrate && ..."

# OK
ssh host "cd ~/SunBWork && php artisan migrate --force && ..."
```
または SSH でログインしてから対話的に実行する。

---

### ミス5: ナビゲーションで window.location.href をハードコードした

**症状:** ローカルは動くが、さくら本番（`/members` ベースパス）で 404 になる。

**対処:** 必ず Ziggy の `route()` を使う:
```js
// NG
window.location.href = `/events/${id}`;

// OK
window.location.href = route('events.show', { event: id });
router.get(route('events.show', { event: id })); // Inertia 遷移が望ましい
```

---

### ミス6: CSRF トークンをクッキーから取得している

**症状:** さくら本番で 419 エラー（`XSRF-TOKEN` クッキーが発行されない）。

**対処:** meta tag から取得する:
```js
// NG
const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
headers: { 'X-XSRF-TOKEN': match ? decodeURIComponent(match[1]) : '' }

// OK
const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }
```

---

### ミス7: `~/www/members/storage/` シンボリックリンクが存在しない

**症状:** `Storage::disk('public')` で保存した画像（prepress/jobticker 等）の URL が
`https://sun-brain.co.jp/members/storage/...` の形式になるが、404 エラーになる。

**原因:** `php artisan storage:link` は `~/SunBWork/public/storage/` を作成するが、
さくらの公開ディレクトリは `~/www/members/` なのでそこには届かない。

**対処:** さくら SSH で以下を一度だけ実行:
```bash
ln -s ~/SunBWork/storage/app/public ~/www/members/storage
```

---

## さくら本番 .env の重要設定（参考）

```env
APP_URL=https://sun-brain.co.jp/members
ASSET_URL=https://sun-brain.co.jp/members
VITE_APP_BASE_PATH=/members
APP_DEBUG=false
```

---

## ローカル .env の重要設定（参考）

```env
APP_URL=http://localhost:8000
VITE_APP_BASE_PATH=          # 空にする（行自体は残す）
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax
```

---

## さくらサーバー構成（参考）

| パス | 役割 |
|---|---|
| `~/SunBWork/` | Laravel ルート |
| `~/www/members/` | 公開ディレクトリ（`index.php` のパスが通常と異なる） |
| `~/www/members/build/` | `~/SunBWork/public/build/` へのシンボリックリンク |
| `~/www/members/storage/` | `~/SunBWork/storage/app/public/` へのシンボリックリンク（要手動作成） |

---

## 初回セットアップ（さくら SSH で一度だけ実行）

通常の `php artisan storage:link` は `~/SunBWork/public/storage/` を作成するが、
さくらの公開ディレクトリは `~/www/members/` であるため、別途シンボリックリンクが必要。

```bash
# storage シンボリックリンク（初回のみ）
ln -s ~/SunBWork/storage/app/public ~/www/members/storage

# 確認
ls -la ~/www/members/
# → storage -> /home/ユーザー名/SunBWork/storage/app/public
```

**これがないと `Storage::disk('public')` で保存した画像（prepress/jobticker 等）が
`/members/storage/...` の URL で 404 になる。**

---

## デプロイ後の確認ポイント

1. ブラウザで `https://sun-brain.co.jp/members` にアクセスしてログイン可能か
2. JS / CSS が 404 になっていないか（DevTools → Network）
3. `php artisan migrate` が必要だった場合、関連機能が正常に動作するか
4. ローカル環境の `.env` が `VITE_APP_BASE_PATH=`（空）に戻っているか確認

---

---

## NSystem データのデプロイ（JSON・Excel）

NSystem の問題データと Mコードリスト Excel は `.gitignore` 管理外のため  
**git push では本番に届かない**。ローカルでデータを更新した後は別途転送が必要。

詳細手順は `z_instructions/NSYSTEM_GUIDE.md` のセクション10を参照。

**ショートカット手順（全部まとめて実行する場合）:**

```bash
# 1. JSON 転送（36MB・1352件）
rsync -az /home/w229/SunBwork/storage/app/private/n_import/ \
  silverlamb759@silverlamb759.sakura.ne.jp:~/SunBWork/storage/app/private/n_import/

# 2. Excel 転送（5ファイル）
scp /home/w229/SunBwork/z_NDBSystem/Nコードリスト*.xlsx \
  silverlamb759@silverlamb759.sakura.ne.jp:~/SunBWork/z_NDBSystem/

# 3. phpspreadsheet 確認（PHP 8.2 のため --ignore-platform-reqs が必要）
ssh silverlamb759@silverlamb759.sakura.ne.jp \
  "cd ~/SunBWork && composer install --no-interaction --ignore-platform-reqs 2>&1 | tail -3"

# 4. import 実行
ssh silverlamb759@silverlamb759.sakura.ne.jp \
  "cd ~/SunBWork && php artisan n-system:import --force 2>&1"
```

> ⚠️ JSON は `storage/app/private/n_import/`（`private` が必須）。`storage/app/n_import/` では動かない。

---

*このドキュメントは `/home/tchirosb/SunBWork/CLAUDE.md` のデプロイセクションをもとに作成。*
*最新情報は CLAUDE.md を正とすること。*
