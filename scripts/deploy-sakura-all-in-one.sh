#!/bin/bash

# deploy-sakura-all-in-one.sh
# さくらレンタルサーバー向けデプロイ - 統合スクリプト
# 
# 機能:
#   1. ローカル側: ビルド～コミット～.env 復元
#   2. git push
#   3. リモート側: git pull～migrate～config:clear（SSH で実行）
# 
# 使用法:
#   bash scripts/deploy-sakura-all-in-one.sh <sakura-user> <sakura-host> [--skip-ziggy] [--skip-migrate] [--no-push]
#
# 引数:
#   sakura-user     : さくらサーバーの SSH ユーザー名
#   sakura-host     : さくらサーバーのホスト名
#
# オプション:
#   --skip-ziggy    : Ziggy 再生成をスキップ
#   --skip-migrate  : migrate をスキップ
#   --no-push       : git push をスキップ（ローカルまで）
#   --commit-msg MSG: コミットメッセージを指定

set -e

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SAKURA_USER="silverlamb759"
SAKURA_HOST="silverlamb759.sakura.ne.jp"
SKIP_ZIGGY=0
SKIP_MIGRATE=0
NO_PUSH=0
COMMIT_MSG="feat/build: さくらデプロイ用ビルド"

# 引数解析
if [[ $# -lt 2 ]]; then
  cat << EOF
❌ 引数が不足しています

使用法:
  bash scripts/deploy-sakura-all-in-one.sh <sakura-user> <sakura-host> [オプション]

例:
  bash scripts/deploy-sakura-all-in-one.sh w229 silverlamb759.sakura.ne.jp
  bash scripts/deploy-sakura-all-in-one.sh w229 silverlamb759.sakura.ne.jp --skip-ziggy --no-push

オプション:
  --skip-ziggy    : Ziggy 再生成をスキップ
  --skip-migrate  : migrate をスキップ
  --no-push       : git push をスキップ
  --commit-msg    : コミットメッセージを指定
EOF
  exit 1
fi

SAKURA_USER="$1"
SAKURA_HOST="$2"
shift 2

# オプション解析
while [[ $# -gt 0 ]]; do
  case "$1" in
    --skip-ziggy)
      SKIP_ZIGGY=1
      shift
      ;;
    --skip-migrate)
      SKIP_MIGRATE=1
      shift
      ;;
    --no-push)
      NO_PUSH=1
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

SAKURA_SSH="${SAKURA_USER}@${SAKURA_HOST}"

echo "🚀 さくらレンタルサーバーデプロイ - 統合スクリプト"
echo ""
echo "📌 設定情報:"
echo "   ローカル: $PROJECT_ROOT"
echo "   さくす: $SAKURA_SSH"
echo "   git repository: origin main"
echo ""

read -p "このまま続行しますか？ (y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
  echo "❌ キャンセルしました"
  exit 1
fi

echo ""
echo "========== PHASE 1: ローカル側のビルド・コミット =========="
echo ""

# ローカル側スクリプト実行（配列で安全に引数を渡す）
local_args=()
[[ $SKIP_ZIGGY -eq 1 ]] && local_args+=("--skip-ziggy")
local_args+=("--commit-msg" "$COMMIT_MSG")

bash "$PROJECT_ROOT/scripts/deploy-sakura-local.sh" "${local_args[@]}" || {
  echo "❌ ローカル側のビルドに失敗しました"
  exit 1
}

echo ""
echo "========== PHASE 2: git push =========="
echo ""

if [[ $NO_PUSH -eq 0 ]]; then
  echo "git push origin main を実行中..."
  cd "$PROJECT_ROOT"
  git push origin main || {
    echo "❌ git push に失敗しました"
    exit 1
  }
  echo "✅ git push 完了"
else
  echo "⏭️  git push はスキップされました"
fi

echo ""
echo "========== PHASE 3: リモート側のデプロイ（SSH 実行） =========="
echo ""

# リモート側スクリプト引数（配列で安全に渡す）
remote_args=""
[[ $SKIP_MIGRATE -eq 1 ]] && remote_args="--skip-migrate"

echo "ssh $SAKURA_SSH で以下を実行します:"
echo ""
echo "cd ~/SunBWork && bash scripts/deploy-sakura-remote.sh $remote_args"
echo ""

read -p "リモートコマンドを実行しますか？ (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
  ssh "$SAKURA_SSH" "cd ~/SunBWork && bash scripts/deploy-sakura-remote.sh $remote_args" || {
    echo "⚠️  リモートコマンドの実行に失敗しました。SSH でさくらに接続して手動で実行してください:"
    echo "   ssh $SAKURA_SSH"
    echo "   cd ~/SunBWork && bash scripts/deploy-sakura-remote.sh $remote_args"
    exit 1
  }
  echo "✅ リモートデプロイ完了"
else
  echo "⏭️  リモートコマンドは実行されませんでした。手動で実行してください:"
  echo "   ssh $SAKURA_SSH"
  echo "   cd ~/SunBWork && bash scripts/deploy-sakura-remote.sh $remote_args"
fi

echo ""
echo "=========================================="
echo "✅ 全デプロイプロセスが完了しました！"
echo "=========================================="
echo ""
echo "📋 デプロイ確認:"
echo "   https://silverlamb759.sakura.ne.jp/members"
echo "   にアクセスして動作を確認してください"
echo ""
