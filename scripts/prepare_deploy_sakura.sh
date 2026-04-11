#!/usr/bin/env bash
set -euo pipefail

# prepare_deploy_sakura.sh
# Usage:
#  - Dry run: ./scripts/prepare_deploy_sakura.sh
#  - With build: ./scripts/prepare_deploy_sakura.sh --build  # requires confirmation

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

echo "[prepare_deploy_sakura] working dir: $ROOT_DIR"

echo "Checking git status (excluding public/build)..."
if git status --porcelain | grep -v "public/build" | grep -q .; then
  echo "Uncommitted changes detected (excluding public/build):"
  git status --porcelain | grep -v "public/build"
  echo "Please commit or stash them before proceeding. Aborting."
  exit 1
fi

echo "If you've modified routes/web.php, regenerate Ziggy on the laravel container with:"
echo "  docker compose exec laravel bash -lc \"php artisan ziggy:generate resources/js/ziggy.js\""

if [ ! -f .env ]; then
  echo ".env not found in repository root. Aborting."
  exit 1
fi

if [ ! -f .env.deploy.bak ]; then
  cp .env .env.deploy.bak
  echo "Backed up .env to .env.deploy.bak"
else
  echo "Backup .env.deploy.bak already exists; leaving it in place."
fi

echo "Switching VITE_APP_BASE_PATH to /members for sakura build..."
sed -i 's/^VITE_APP_BASE_PATH=$/VITE_APP_BASE_PATH=\/members/' .env || true
echo ".env updated (backup: .env.deploy.bak)."

if [ "${1:-}" != "--build" ]; then
  echo "Dry run complete. To perform the build and commit, re-run this script with --build"
  echo "Example: ./scripts/prepare_deploy_sakura.sh --build"
  exit 0
fi

echo "--build flag provided. Proceeding to run build (this will install node modules and build assets)."

read -p "Are you sure you want to run the production build now? (y/N): " confirm
if [[ "$confirm" != "y" && "$confirm" != "Y" ]]; then
  echo "Build cancelled by user. Reverting .env to backup and exiting."
  cp .env.deploy.bak .env
  exit 1
fi

echo "Installing node dependencies (npm ci)..."
npm ci

echo "Running production build (npm run build)..."
npm run build

echo "Build finished. You should now commit changed files and public/build."
echo "Suggested commit:"
echo "  git add <changed Controller/Model/Migration/routes files> public/build/ resources/js/ziggy.js"
echo "  git commit -m \"chore(build): sakura production build\""

echo "After committing, revert .env to local and run local build (do NOT commit local build):"
echo "  sed -i 's/^VITE_APP_BASE_PATH=\/members$/VITE_APP_BASE_PATH=/' .env"
echo "  npm run build"

echo "Done."
