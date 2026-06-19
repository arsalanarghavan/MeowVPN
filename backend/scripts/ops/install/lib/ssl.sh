#!/usr/bin/env bash
set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/common.sh"
source "$(dirname "${BASH_SOURCE[0]}")/system.sh"

cert_paths_certbot() {
  local domain="$1"
  echo "/etc/letsencrypt/live/${domain}/fullchain.pem|/etc/letsencrypt/live/${domain}/privkey.pem"
}

cert_paths_acme() {
  local domain="$1"
  local home="${HOME:-/root}"
  echo "${home}/.acme.sh/${domain}_ecc/fullchain.cer|${home}/.acme.sh/${domain}_ecc/${domain}.key"
}

ensure_certbot() {
  if command -v certbot >/dev/null 2>&1; then
    return 0
  fi
  log "Installing certbot..."
  apt_install certbot python3-certbot-nginx
}

ensure_acme() {
  local home="${HOME:-/root}"
  if [[ -x "${home}/.acme.sh/acme.sh" ]]; then
    return 0
  fi
  log "Installing acme.sh..."
  apt_install curl socat
  retry 3 10 -- curl -fsSL https://get.acme.sh | sh -s email="${SSL_EMAIL:-admin@localhost}"
}

issue_ssl_cert() {
  local domain="$1"
  if ! is_valid_hostname "$domain"; then
    warn "Skipping SSL for $domain (IP or invalid hostname — use HTTP)"
    return 0
  fi
  mkdir -p "$CERTBOT_WEBROOT"
  case "${SSL_METHOD:-certbot}" in
    acme)
      ensure_acme
      local home="${HOME:-/root}"
      "${home}/.acme.sh/acme.sh" --issue -d "$domain" --nginx --force 2>/dev/null \
        || "${home}/.acme.sh/acme.sh" --issue -d "$domain" -w "$CERTBOT_WEBROOT" --standalone --force
      ;;
    *)
      ensure_certbot
      certbot certonly --nginx -d "$domain" --non-interactive --agree-tos \
        ${SSL_EMAIL:+--email "$SSL_EMAIL"} --keep-until-expiring \
        || certbot certonly --webroot -w "$CERTBOT_WEBROOT" -d "$domain" --non-interactive --agree-tos \
        ${SSL_EMAIL:+--email "$SSL_EMAIL"} --keep-until-expiring
      ;;
  esac
  log "SSL issued for $domain"
}

get_ssl_cert_key() {
  local domain="$1"
  local pair
  if [[ "${SSL_METHOD:-certbot}" == "acme" ]]; then
    pair="$(cert_paths_acme "$domain")"
  else
    pair="$(cert_paths_certbot "$domain")"
  fi
  SSL_CERT="${pair%%|*}"
  SSL_KEY="${pair##*|}"
}

print_ssl_renew_hint() {
  if [[ "${SSL_METHOD:-certbot}" == "acme" ]]; then
    echo "Renew: ~/.acme.sh/acme.sh --renew-all (add to cron)"
  else
    echo "Renew: certbot renew (systemd timer or cron)"
  fi
}
