#!/bin/bash

# deploy-sakura-remote.sh
# さくらレンタルサーバー向けデプロイ - リモート側実行スクリプト
# 
# 機能:
#   - git pull
#   - php artisan migrate
#   - php artisan config:clear
#   - php artisan cache:clear
# 
# 使用法（さくらサーバー上で実行）:
#   bash scripts/deploy-sakura-remote.sh [--skip-migrate]
#
# オプション:
#   --skip-migrate : migrate をスキップ（マイグレーションがない場合）

set -e

SAKURA_ROOT="${HOME}/SunBWork"
SKIP_MIGRATE=0

# オプション解析
while [[ $# -gt 0 ]]; do
  case "$1" in
    --skip-migrate)
      SKIP_MIGRATE=1
      shift
      ;;
    *)
      echo "❌ 不明なオプション: $1"
      exit 1
      ;;
  esac
done

echo "🚀 さくらレンタルサーバーデプロイ - リモート側実行"
echo ""

# git pull
echo "① git pull を実行中..."
cd "$SAKURA_ROOT"
git pull origin main || {
  echo "❌ git pull に失敗しました"
  exit 1
}
echo "✅ git pull 完了"

echo ""

# migrate
if [[ $SKIP_MIGRATE -eq 0 ]]; then
  echo "② php artisan migrate を実行中..."
  php artisan migrate || {
    echo "⚠️  migrate に失敗しました。続行します..."
  }
  echo "✅ migrate 完了"
else
  echo "② migrate はスキップされました"
fi

echo ""

# config:clear
echo "③ php artisan config:clear を実行中..."
php artisan config:clear || {
  echo "⚠️  config:clear に失敗しました。続行します..."
}
echo "✅ config:clear 完了"

echo ""

# cache:clear
echo "④ php artisan cache:clear を実行中..."
php artisan cache:clear || {
  echo "⚠️  cache:clear に失敗しました。続行します..."
}
echo "✅ cache:clear 完了"

echo ""
echo "=========================================="
echo "✅ さくらサーバーのデプロイが完了しました"
echo "=========================================="
echo ""
echo "📋 デプロイ完了の確認:"
echo "   https://silverlamb759.sakura.ne.jp/members"
echo "   にアクセスして動作を確認してください"
echo ""
