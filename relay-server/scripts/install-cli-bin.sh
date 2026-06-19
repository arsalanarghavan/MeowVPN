#!/usr/bin/env bash
# Install /usr/local/bin/svp-relay wrapper (callable from install.sh and update.sh).
set -euo pipefail

INSTALL_DIR="${1:?install dir required}"
NODE_BIN="${2:-$(command -v node || echo /usr/bin/node)}"
BIN="/usr/local/bin/svp-relay"

mkdir -p "$(dirname "$BIN")"
# Remove old symlink first — otherwise cat follows it and overwrites dist/cli/svp-relay.js
rm -f "$BIN"
cat >"$BIN" <<EOF
#!/usr/bin/env bash
set -euo pipefail
exec ${NODE_BIN} "${INSTALL_DIR}/dist/cli/svp-relay.js" "\$@"
EOF
chmod 755 "$BIN"
echo "CLI: $BIN -> ${NODE_BIN} ${INSTALL_DIR}/dist/cli/svp-relay.js"
