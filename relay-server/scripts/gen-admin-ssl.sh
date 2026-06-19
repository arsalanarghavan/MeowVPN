#!/usr/bin/env bash
set -euo pipefail
DIR="${1:-/etc/svp-relay/ssl}"
mkdir -p "$DIR"
if [[ -f "$DIR/admin-ip.crt" && -f "$DIR/admin-ip.key" ]]; then
  echo "Admin SSL already exists: $DIR/admin-ip.crt"
  exit 0
fi
openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
  -keyout "$DIR/admin-ip.key" \
  -out "$DIR/admin-ip.crt" \
  -subj "/CN=svp-relay-admin/O=SimpleVPBot"
chmod 600 "$DIR/admin-ip.key"
chmod 644 "$DIR/admin-ip.crt"
echo "Created $DIR/admin-ip.crt"
