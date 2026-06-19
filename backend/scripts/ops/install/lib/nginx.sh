#!/usr/bin/env bash
set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/common.sh"
source "$(dirname "${BASH_SOURCE[0]}")/ssl.sh"

core_proxy_locations() {
  cat <<'NGINX'
    location / {
        proxy_pass http://meowvpn_core_upstream;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
NGINX
}

dashboard_proxy_locations() {
  cat <<'NGINX'
    location / {
        proxy_pass http://meowvpn_dashboard_upstream;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
NGINX
}

telegram_proxy_locations() {
  cat <<'NGINX'
    location /api/v1/webhook/ {
        rewrite ^/api/v1/webhook/(.*)$ /webhook/$1 break;
        proxy_pass http://meowvpn_telegram_upstream;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
    location /webhook/ {
        proxy_pass http://meowvpn_telegram_upstream;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
    location /health {
        proxy_pass http://meowvpn_telegram_upstream;
        proxy_set_header Host $host;
    }
NGINX
}

bale_proxy_locations() {
  cat <<'NGINX'
    location /api/v1/webhook/ {
        rewrite ^/api/v1/webhook/(.*)$ /webhook/$1 break;
        proxy_pass http://meowvpn_bale_upstream;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
    location /webhook/ {
        proxy_pass http://meowvpn_bale_upstream;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
    location /health {
        proxy_pass http://meowvpn_bale_upstream;
        proxy_set_header Host $host;
    }
NGINX
}

relay_proxy_locations() {
  cat <<'NGINX'
    location /internal/ {
        return 404;
    }
    location /health {
        return 404;
    }
    location ~ ^/webhook/ {
        proxy_pass http://meowvpn_relay_upstream;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 120s;
    }
    location ~ ^/bot {
        proxy_pass http://meowvpn_relay_upstream;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 120s;
    }
    location / {
        return 404;
    }
NGINX
}

render_http_block() {
  local use_ssl="$1"
  local locations="$2"
  if [[ "$use_ssl" == "1" ]]; then
    echo "    location / { return 301 https://\$host\$request_uri; }"
  else
    echo "$locations"
  fi
}

render_ssl_server() {
  local server_name="$1"
  local use_ssl="$2"
  local locations="$3"
  if [[ "$use_ssl" != "1" ]]; then
    echo ""
    return
  fi
  get_ssl_cert_key "$server_name"
  cat <<NGINX
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name ${server_name};

    ssl_certificate     ${SSL_CERT};
    ssl_certificate_key ${SSL_KEY};
    ssl_protocols       TLSv1.2 TLSv1.3;

    client_max_body_size 32m;

${locations}
}
NGINX
}

render_nginx_site() {
  local role="$1"
  local server_name="$2"
  local out_name="meowvpn-${role}-${server_name}.conf"
  local tpl="$TEMPLATE_DIR/${role}.conf.tpl"
  [[ -f "$tpl" ]] || die "Missing template: $tpl"

  local use_ssl=0
  if host_use_ssl "$server_name"; then
    use_ssl=1
    issue_ssl_cert "$server_name"
  fi

  local locations=""
  case "$role" in
    core) locations="$(core_proxy_locations)" ;;
    dashboard) locations="$(dashboard_proxy_locations)" ;;
    telegram-bot) locations="$(telegram_proxy_locations)" ;;
    bale-bot) locations="$(bale_proxy_locations)" ;;
    relay) locations="$(relay_proxy_locations)"; tpl="$TEMPLATE_DIR/relay.conf.tpl" ;;
    *) die "Unknown nginx role: $role" ;;
  esac
  [[ "$role" != "relay" ]] && tpl="$TEMPLATE_DIR/${role}.conf.tpl"

  local http_block ssl_block
  http_block="$(render_http_block "$use_ssl" "$locations")"
  ssl_block="$(render_ssl_server "$server_name" "$use_ssl" "$locations")"

  local body
  body="$(cat "$tpl")"
  body="${body//\{\{SERVER_NAME\}\}/$server_name}"
  body="${body//\{\{HTTP_REDIRECT_OR_PROXY\}\}/$http_block}"
  body="${body//\{\{SSL_SERVER_BLOCK\}\}/$ssl_block}"

  mkdir -p "$NGINX_SITES_AVAILABLE" "$NGINX_SITES_ENABLED" "$CERTBOT_WEBROOT"
  echo "$body" >"$NGINX_SITES_AVAILABLE/$out_name"
  ln -sf "$NGINX_SITES_AVAILABLE/$out_name" "$NGINX_SITES_ENABLED/$out_name"
  log "nginx site: $out_name"
}

ensure_nginx() {
  apt_install nginx 2>/dev/null || true
  if ! command -v nginx >/dev/null 2>&1; then
    die "nginx installation failed"
  fi
  nginx -t
  systemctl enable nginx
  if systemctl is-active nginx >/dev/null 2>&1; then
    systemctl reload nginx 2>/dev/null || systemctl restart nginx
  else
    systemctl start nginx 2>/dev/null || systemctl restart nginx
  fi
}

install_nginx_core() { render_nginx_site "core" "$CORE_HOST"; ensure_nginx; }
install_nginx_dashboard() { render_nginx_site "dashboard" "$DASHBOARD_HOST"; ensure_nginx; }
install_nginx_telegram() { render_nginx_site "telegram-bot" "$TELEGRAM_HOST"; ensure_nginx; }
install_nginx_bale() { render_nginx_site "bale-bot" "$BALE_HOST"; ensure_nginx; }
install_nginx_relay() { render_nginx_site "relay" "$RELAY_HOST"; ensure_nginx; }
