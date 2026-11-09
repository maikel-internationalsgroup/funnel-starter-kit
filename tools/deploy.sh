#!/usr/bin/env bash
# Deploy the funnel to the IT partner's server over SFTP.
#
#   ./tools/deploy.sh            deploy
#   ./tools/deploy.sh --dry-run  show what would be uploaded/deleted
#
# Needs: lftp (brew install lftp / apt install lftp), composer, .env.deploy
# The production .env is NOT uploaded: put it on the server once, by hand.

set -euo pipefail
cd "$(dirname "$0")/.."

DRY_RUN=""
[[ "${1:-}" == "--dry-run" ]] && DRY_RUN="--dry-run"

if [[ ! -f .env.deploy ]]; then
  echo "✗ .env.deploy missing — copy .env.deploy.example and fill in the SFTP details." >&2
  exit 1
fi
set -a; source .env.deploy; set +a

command -v lftp >/dev/null || { echo "✗ lftp is not installed" >&2; exit 1; }

echo "→ Checking config and translations"
php tools/funnel.php check

echo "→ Installing production dependencies"
composer install --no-dev --optimize-autoloader --no-interaction --quiet

AUTH="set sftp:auto-confirm yes;"
if [[ -n "${SFTP_KEY:-}" ]]; then
  AUTH+=" set sftp:connect-program 'ssh -a -x -i ${SFTP_KEY/#\~/$HOME}';"
  LOGIN="${SFTP_USER},"
else
  LOGIN="${SFTP_USER},${SFTP_PASS}"
fi

echo "→ Uploading to ${SFTP_HOST}:${SFTP_REMOTE_DIR} ${DRY_RUN}"
lftp -p "${SFTP_PORT:-22}" -u "$LOGIN" "sftp://${SFTP_HOST}" <<EOF
${AUTH}
set net:max-retries 2
mirror --reverse --delete --verbose --parallel=4 ${DRY_RUN} \
  --exclude-glob .git/ \
  --exclude-glob .github/ \
  --exclude-glob .env \
  --exclude-glob .env.* \
  --exclude-glob storage/logs/ \
  --exclude-glob storage/submissions/ \
  --exclude-glob storage/ratelimit/ \
  --exclude-glob dist/ \
  --exclude-glob docs/ \
  --exclude-glob tools/ \
  --exclude-glob node_modules/ \
  --exclude-glob '*.md' \
  . ${SFTP_REMOTE_DIR}
bye
EOF

echo "✓ Deployed. Tag this release:  git tag v\$(php -r 'echo json_decode(file_get_contents(\"config/funnel.json\"))->funnel->version;') && git push --tags"
