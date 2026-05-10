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
### sshの指示は可能ならワンライナーで表示。

```
【あなたの操作が必要です】

1. git push origin main

2. さくら SSH でデプロイ:　できればワインラインで。
   cd ~/SunBWork
   git pull
   php artisan migrate          ← マイグレーションがない場合は省略可
   php artisan config:clear
   php artisan cache:clear
```

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
php artisan migrate
php artisan config:clear
```

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

*このドキュメントは `/home/tchirosb/SunBWork/CLAUDE.md` のデプロイセクションをもとに作成。*
*最新情報は CLAUDE.md を正とすること。*
