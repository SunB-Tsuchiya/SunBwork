#!/bin/bash

# deploy-sakura-local.sh
# さくらレンタルサーバー向けデプロイ - ローカル側自動化スクリプト
# 
# 機能:
#   ① git status 確認
#   ② routes/web.php 変更があれば Ziggy 再生成
#   ③ さくら用ビルド (VITE_APP_BASE_PATH を /members に切替)
#   ④ コミット
#   ⑤ VITE_APP_BASE_PATH を空に戻す + ローカルビルド
# 
# 使用法:
#   bash scripts/deploy-sakura-local.sh [--skip-ziggy] [--no-git-check]
#
# オプション:
#   --skip-ziggy     : Ziggy 再生成をスキップ
#   --no-git-check   : git status チェックをスキップ
#   --commit-msg MSG : コミットメッセージを指定（デフォルト: feat/build: さくらデプロイ用ビルド）

set -e

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$PROJECT_ROOT/.env"
COMMIT_MSG="feat/build: さくらデプロイ用ビルド"
SKIP_ZIGGY=0
NO_GIT_CHECK=0

# オプション解析
while [[ $# -gt 0 ]]; do
  case "$1" in
    --skip-ziggy)
      SKIP_ZIGGY=1
      shift
      ;;
    --no-git-check)
      NO_GIT_CHECK=1
      shift
      ;;
    --commit-msg)
      COMMIT_MSG="$2"
      shift 2
      ;;
    *)
      echo "❌ 不明なオプション: $1"
      exit 1
      ;;
  esac
done

echo "🚀 さくらレンタルサーバーデプロイ - ローカル側自動化"
echo ""

# ① git status 確認
if [[ $NO_GIT_CHECK -eq 0 ]]; then
  echo "① git status を確認中..."
  # public/build と tmp/ は除外（デプロイスクリプト側で扱う）
  git_status=$(cd "$PROJECT_ROOT" && git status --short | grep -v "public/build" | grep -v "^?? scripts/" || true)
  
  if [[ -n "$git_status" ]]; then
    echo "⚠️  以下のファイルがコミットされていません (app/, database/, resources/ など):"
    echo "$git_status"
    echo ""
    echo "💡 ヒント: これらのコア部分は先にコミットしてください"
    echo "   git add app/ database/ resources/"
    echo "   git commit -m \"feat: 説明\""
    echo ""
    read -p "コア部分がすべてコミット済みですか？ (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
      echo "❌ キャンセルしました。先にコミットしてください。"
      exit 1
    fi
  else
    echo "✅ コア部分（app/database/resources）の未コミットファイルはありません"
  fi
fi

echo ""

# ② routes/web.php 変更があれば Ziggy 再生成
if [[ $SKIP_ZIGGY -eq 0 ]]; then
  echo "② Ziggy を再生成中..."
  echo "  Ziggy を再生成します..."
  docker compose exec laravel bash -lc "php artisan ziggy:generate resources/js/ziggy.js" || {
    echo "⚠️  Ziggy 生成に失敗しましたが、続行します..."
  }
  echo "✅ Ziggy 再生成完了"
fi

echo ""

# ③ さくら用ビルド (VITE_APP_BASE_PATH を /members に切替)
echo "③ さくら用ビルドを実行中..."
echo "   VITE_APP_BASE_PATH を /members に切替..."

# .env のバックアップ
cp "$ENV_FILE" "$ENV_FILE.backup.local"

# VITE_APP_BASE_PATH を /members に設定
sed -i.tmp 's/^VITE_APP_BASE_PATH=$/VITE_APP_BASE_PATH=\/members/' "$ENV_FILE"
rm -f "$ENV_FILE.tmp"

echo "   npm run build を実行..."
cd "$PROJECT_ROOT" && npm run build

echo "✅ さくら用ビルド完了"

echo ""

# ④ コミット
echo "④ コミットを作成中..."
echo "   ステージング: public/build/ + resources/js/ziggy.js + 変更ファイル"

cd "$PROJECT_ROOT"
git add public/build/ resources/js/ziggy.js 2>/dev/null || true

# Controller/Model/Migration/routes に変更がないかチェック
uncommitted=$(git status --short | grep -E "^\s*M\s+(app/|database/|routes/)" || true)
if [[ -n "$uncommitted" ]]; then
  echo "⚠️  以下のコア部分が未ステージングです:"
  echo "$uncommitted"
  read -p "ステージングして続行しますか？ (y/N): " -n 1 -r
  echo
  if [[ $REPLY =~ ^[Yy]$ ]]; then
    git add app/ database/ routes/ 2>/dev/null || true
  fi
fi

git commit -m "$COMMIT_MSG" || {
  echo "⚠️  コミット失敗。既にコミット済みの可能性があります。"
  echo "   .env 復元後、続行します..."
}

echo "✅ コミット完了"

echo ""

# ⑤ .env をローカル用に戻す
echo "⑤ ローカル用に .env を復元中..."
echo "   VITE_APP_BASE_PATH を空に戻す..."

sed -i.tmp 's/^VITE_APP_BASE_PATH=\/members$/VITE_APP_BASE_PATH=/' "$ENV_FILE"
rm -f "$ENV_FILE.tmp"

# バックアップ削除
rm -f "$ENV_FILE.backup.local"

echo "   npm run build を実行（ローカル用）..."
npm run build

echo "✅ ローカル環境復元完了"

echo ""
echo "=========================================="
echo "✅ ローカル側のデプロイ準備が完了しました"
echo "=========================================="
echo ""
echo "【次のステップ（あなたの操作）】"
echo ""
echo "1️⃣  リモートにプッシュ:"
echo "   git push origin main"
echo ""
echo "2️⃣  さくらサーバーでデプロイを実行:"
echo "   bash scripts/deploy-sakura-remote.sh <sakura-host>"
echo ""
echo "   または手動で実行:"
echo "   ssh <sakura-user>@<sakura-host> 'cd ~/SunBWork && bash scripts/deploy-sakura-remote.sh'"
echo ""
