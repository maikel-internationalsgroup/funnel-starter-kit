#!/usr/bin/env bash
# Create a new private funnel repository from this starter kit and clone it.
#
#   ./tools/new-funnel.sh <repo-name> "<Funnel Name>" <url> <languages> [GTM-ID]
#   ./tools/new-funnel.sh spring-campaign "Spring Campaign" https://spring.yourcompany.nl nl,en GTM-ABC1234
#
# Needs: GitHub CLI (gh auth login), and the starter kit marked as a template
# repository (GitHub → Settings → General → "Template repository").

set -euo pipefail

OWNER="${FUNNEL_GITHUB_OWNER:-maikel-internationalsgroup}"
TEMPLATE="${FUNNEL_TEMPLATE:-maikel-internationalsgroup/funnel-starter-kit}"

REPO="${1:?repo name required}"
NAME="${2:?funnel name required}"
URL="${3:?url required}"
LANGS="${4:-nl,en}"
GTM="${5:-}"

command -v gh >/dev/null || { echo "✗ GitHub CLI (gh) is not installed" >&2; exit 1; }

echo "→ Creating private repo ${OWNER}/${REPO} from ${TEMPLATE}"
gh repo create "${OWNER}/${REPO}" --private --template "${TEMPLATE}" --clone
cd "${REPO}"

# Template repos are created asynchronously; wait until the files are there.
for _ in {1..10}; do
  [[ -f config/funnel.json ]] && break
  sleep 2; git pull --quiet origin main 2>/dev/null || true
done

ARGS=(--name="${NAME}" --slug="${REPO}" --url="${URL}" --languages="${LANGS}")
[[ -n "${GTM}" ]] && ARGS+=(--gtm="${GTM}")

php tools/funnel.php init "${ARGS[@]}"
php tools/funnel.php key
php tools/funnel.php check || true

git add -A
git commit -m "Initialise funnel: ${NAME}" --quiet
git push --quiet

echo
echo "✓ ${OWNER}/${REPO} is ready in ./${REPO}"
echo "  Next: send docs/it-partner-request.md to the IT partner, then run: php tools/funnel.php serve"
