#!/usr/bin/env bash
set -euo pipefail

# 安全: 実行前に現在のブランチとステータスを表示
echo "Branch: $(git rev-parse --abbrev-ref HEAD)"
echo "Top of working tree changes (porcelain):"
git status --porcelain

# 作業用バックアップディレクトリ
BACKUP_DIR="/tmp/patch_backups_$(date +%s)"
mkdir -p "$BACKUP_DIR"
echo "Backup dir: $BACKUP_DIR"

# 1) ワークツリー内の .bak ファイル一覧（ステージ済/未ステージ 両方）
echo
echo "Found .bak files (if any):"
git status --porcelain | awk '{print substr($0,4)}' | grep -E '\.bak($|/|[0-9])' || true

# 2) ステージされている .bak があればステージ解除
echo
echo "Unstaging any .bak files..."
git status --porcelain | awk '/\.bak/ {print substr($0,4)}' | xargs -r -I{} git restore --staged -- {} || true

# 3) ワークツリー内の .bak を退避（ワークツリーから取り除く）
echo
echo "Moving .bak files to backup dir..."
git status --porcelain | awk '/\.bak/ {print substr($0,4)}' | while read -r f; do
  if [ -e "$f" ]; then
    mkdir -p "$(dirname "$BACKUP_DIR/$f")"
    mv "$f" "$BACKUP_DIR/$f"
    echo "Moved $f -> $BACKUP_DIR/$f"
  fi
done || true

# 4) 誤置換パターンが残っていないか検査し、見つかれば自動修正（Create.vue 用）
TARGET="resources/js/Pages/Coordinator/ProjectJobs/Create.vue"
if [ -f "$TARGET" ]; then
  echo
  echo "Checking $TARGET for incorrect fetch('/api/clients/') pattern..."
  if grep -n "fetch(`/api/clients/`" "$TARGET" >/dev/null 2>&1 || grep -n "fetch('/api/clients/'," "$TARGET" >/dev/null 2>&1; then
    echo "Problematic pattern found. Backing up original to $BACKUP_DIR/$(basename "$TARGET").orig"
    cp "$TARGET" "$BACKUP_DIR/$(basename "$TARGET").orig"

    # 修正：fetch(`/api/clients/`,  -> fetch(`/api/clients/${clientSearch.value.id}`, 
    # 注意: awk プログラムはシェル展開を避けるため単一引用で囲う
    awk '{
      gsub("fetch(`/api/clients/,", "fetch(`/api/clients/${clientSearch.value.id},");
      gsub("fetch\\('/api/clients/',", "fetch(`/api/clients/${clientSearch.value.id},");
      print
    }' "$TARGET" > "$TARGET.tmp" && mv "$TARGET.tmp" "$TARGET"

    echo "Applied automated replacement in $TARGET (backup at $BACKUP_DIR/$(basename "$TARGET").orig)."
  else
    echo "No problematic /api/clients/ fetch pattern detected in $TARGET."
  fi
else
  echo "$TARGET not present; skipping pattern check."
fi

# 5) 変更差分の一覧（ワーキングとステージ済）
echo
echo "==== Working tree diff ===="
git --no-pager diff -- \
  resources/js/Components/LookupModal.vue \
  resources/js/Pages/Coordinator/ProjectJobs/Create.vue \
  resources/js/Pages/Coordinator/ProjectSchedules/Index.vue \
  resources/js/Pages/JobBox/Index.vue || true

echo
echo "==== Staged diff ===="
git --no-pager diff --staged -- \
  resources/js/Components/LookupModal.vue \
  resources/js/Pages/Coordinator/ProjectJobs/Create.vue \
  resources/js/Pages/Coordinator/ProjectSchedules/Index.vue \
  resources/js/Pages/JobBox/Index.vue || true

# 6) 変更をステージ（ユーザー確認前はステージしないオプションにする）
if [ "${1-}" = "--yes" ] || [ "${2-}" = "--yes" ]; then
  echo
  echo "Staging all changes..."
  git add -A
  # 自動コミット
  git commit -m "fix(fetch): add Accept header & same-origin credentials; guard res.json() with res.ok and add fallbacks"
  echo "Committed. To push, run: git push -u origin HEAD"
else
  echo
  echo "No commit performed. To auto-stage+commit, re-run with --yes."
  echo "Example: bash scripts/cleanup_fetch_patch.sh --yes"
fi

echo
echo "Script finished. Backups are in: $BACKUP_DIR"