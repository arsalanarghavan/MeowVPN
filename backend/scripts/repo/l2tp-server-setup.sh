#!/usr/bin/env bash
#
# L2TP/IPsec server bootstrap for SimpleVPBot
# -------------------------------------------
# One-shot installer: strongswan + xl2tpd + ppp + NAT + sudo user for the bot.
#
# Supported: Ubuntu 20.04/22.04/24.04, Debian 11/12.
# Usage (as root):
#     curl -fsSL .../l2tp-server-setup.sh | bash
#   or:
#     bash l2tp-server-setup.sh
#
# Non-interactive env overrides (all optional):
#     SVP_SSH_USER       default: svpbot
#     SVP_SSH_PUBKEY     REQUIRED if you want the bot to connect. Paste full "ssh-ed25519 AAAA... wp" line.
#     SVP_L2TP_PSK       default: random 32 hex chars
#     SVP_POOL_CIDR      default: 10.10.10.0/24 (server=10.10.10.1, pool=10.10.10.100-200)
#     SVP_DNS1/DNS2      default: 1.1.1.1 / 8.8.8.8
#     SVP_WAN_IF         default: auto-detected via default route
#     SVP_ENABLE_ACCT    default: 1 (per-user traffic accounting via iptables)
#
# What this does:
#   1. installs packages
#   2. generates or reuses PSK
#   3. writes /etc/ipsec.conf, /etc/ipsec.secrets
#   4. writes /etc/xl2tpd/xl2tpd.conf, /etc/ppp/options.xl2tpd, empty /etc/ppp/chap-secrets
#   5. sysctl ip_forward=1, disable redirects
#   6. iptables MASQUERADE + UDP 500/4500/1701, ESP allow; persists with netfilter-persistent
#   7. creates svpbot user + authorized_keys + /etc/sudoers.d/svpbot
#   8. (optional) per-user accounting scripts in /etc/ppp/ip-up.d and ip-down.d
#   9. enables & starts strongswan + xl2tpd
#  10. prints the credentials you must paste into the plugin admin.
#
# Safe to re-run: files are backed up with .bak once.

set -euo pipefail

if [[ $EUID -ne 0 ]]; then
    echo "[!] Must run as root. Re-run with sudo." >&2
    exit 1
fi

. /etc/os-release 2>/dev/null || { echo "[!] Cannot read /etc/os-release"; exit 1; }
case "${ID:-}${ID_LIKE:-}" in
    *debian*|ubuntu*|*ubuntu*)
        ;;
    *)
        echo "[!] This script supports Debian/Ubuntu only. Detected: ${PRETTY_NAME:-unknown}" >&2
        exit 1
        ;;
esac

SVP_SSH_USER="${SVP_SSH_USER:-svpbot}"
SVP_SSH_PUBKEY="${SVP_SSH_PUBKEY:-}"
SVP_POOL_CIDR="${SVP_POOL_CIDR:-10.10.10.0/24}"
SVP_DNS1="${SVP_DNS1:-1.1.1.1}"
SVP_DNS2="${SVP_DNS2:-8.8.8.8}"
SVP_ENABLE_ACCT="${SVP_ENABLE_ACCT:-1}"

SVP_WAN_IF="${SVP_WAN_IF:-$(ip -4 route get 1.1.1.1 2>/dev/null | awk '/dev/ {for (i=1;i<=NF;i++) if ($i=="dev") {print $(i+1); exit}}')}"
if [[ -z "${SVP_WAN_IF}" ]]; then
    SVP_WAN_IF="$(ip -o -4 route show default | awk '{print $5; exit}')"
fi
if [[ -z "${SVP_WAN_IF}" ]]; then
    echo "[!] Could not detect WAN interface. Set SVP_WAN_IF=eth0 and re-run." >&2
    exit 1
fi

IFS=. read -r A B C _ <<<"${SVP_POOL_CIDR%/*}"
SVP_POOL_BASE="${A}.${B}.${C}"
SVP_LOCAL_IP="${SVP_POOL_BASE}.1"
SVP_POOL_RANGE="${SVP_POOL_BASE}.100-${SVP_POOL_BASE}.200"

if [[ -z "${SVP_L2TP_PSK:-}" ]]; then
    if [[ -r /etc/ipsec.secrets ]] && grep -qE '^\s*: PSK ' /etc/ipsec.secrets; then
        SVP_L2TP_PSK="$(awk -F'"' '/: PSK /{print $2; exit}' /etc/ipsec.secrets)"
    else
        SVP_L2TP_PSK="$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n' | cut -c1-32)"
    fi
fi

backup_once() {
    local f="$1"
    [[ -f "$f" && ! -f "${f}.bak" ]] && cp -a "$f" "${f}.bak" || true
}

echo "[*] Installing packages..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y --no-install-recommends \
    strongswan xl2tpd ppp iptables iptables-persistent netfilter-persistent \
    sudo openssh-server ca-certificates curl iproute2

echo "[*] Writing /etc/ipsec.conf ..."
backup_once /etc/ipsec.conf
cat >/etc/ipsec.conf <<'EOF'
config setup
    charondebug="ike 1, knl 1, cfg 0"
    uniqueids=no

conn L2TP-PSK
    authby=secret
    auto=add
    keyexchange=ikev1
    ikelifetime=8h
    keylife=1h
    type=transport
    left=%defaultroute
    leftprotoport=17/1701
    right=%any
    rightprotoport=17/%any
    rekey=no
    fragmentation=yes
    forceencaps=yes
    dpddelay=30
    dpdtimeout=120
    dpdaction=clear
    ike=aes256-sha1-modp1024,aes128-sha1-modp1024,3des-sha1-modp1024!
    esp=aes256-sha1,aes128-sha1,3des-sha1!
EOF

echo "[*] Writing /etc/ipsec.secrets ..."
backup_once /etc/ipsec.secrets
cat >/etc/ipsec.secrets <<EOF
%any  %any  : PSK "${SVP_L2TP_PSK}"
EOF
chmod 600 /etc/ipsec.secrets

echo "[*] Writing /etc/xl2tpd/xl2tpd.conf ..."
mkdir -p /etc/xl2tpd
backup_once /etc/xl2tpd/xl2tpd.conf
cat >/etc/xl2tpd/xl2tpd.conf <<EOF
[global]
port = 1701
ipsec saref = no
force userspace = yes

[lns default]
ip range = ${SVP_POOL_RANGE}
local ip = ${SVP_LOCAL_IP}
require chap = yes
refuse pap = yes
require authentication = yes
name = L2TP-VPN
ppp debug = no
pppoptfile = /etc/ppp/options.xl2tpd
length bit = yes
EOF

echo "[*] Writing /etc/ppp/options.xl2tpd ..."
mkdir -p /etc/ppp
backup_once /etc/ppp/options.xl2tpd
cat >/etc/ppp/options.xl2tpd <<EOF
require-mschap-v2
refuse-pap
refuse-chap
refuse-mschap
nobsdcomp
novj
novjccomp
noccp
auth
idle 1800
mtu 1400
mru 1400
hide-password
lock
connect-delay 5000
lcp-echo-interval 30
lcp-echo-failure 4
ms-dns ${SVP_DNS1}
ms-dns ${SVP_DNS2}
proxyarp
EOF

echo "[*] Ensuring /etc/ppp/chap-secrets exists ..."
if [[ ! -f /etc/ppp/chap-secrets ]]; then
    cat >/etc/ppp/chap-secrets <<'EOF'
# client     server   secret         IP addresses
# (managed by SimpleVPBot via SSH; one line per user)
EOF
fi
chmod 600 /etc/ppp/chap-secrets

echo "[*] Applying sysctl ..."
backup_once /etc/sysctl.d/99-svpt-l2tp.conf
cat >/etc/sysctl.d/99-svpt-l2tp.conf <<'EOF'
net.ipv4.ip_forward=1
net.ipv4.conf.all.accept_redirects=0
net.ipv4.conf.all.send_redirects=0
net.ipv4.conf.default.accept_redirects=0
net.ipv4.conf.default.send_redirects=0
net.ipv4.conf.all.rp_filter=2
net.ipv4.conf.default.rp_filter=2
EOF
sysctl --system >/dev/null

echo "[*] Configuring firewall (iptables) on ${SVP_WAN_IF} ..."
iptables -t nat -C POSTROUTING -s "${SVP_POOL_CIDR}" -o "${SVP_WAN_IF}" -j MASQUERADE 2>/dev/null || \
    iptables -t nat -A POSTROUTING -s "${SVP_POOL_CIDR}" -o "${SVP_WAN_IF}" -j MASQUERADE

iptables -C INPUT -p udp --dport 500 -j ACCEPT 2>/dev/null || iptables -A INPUT -p udp --dport 500 -j ACCEPT
iptables -C INPUT -p udp --dport 4500 -j ACCEPT 2>/dev/null || iptables -A INPUT -p udp --dport 4500 -j ACCEPT
iptables -C INPUT -p udp --dport 1701 -j ACCEPT 2>/dev/null || iptables -A INPUT -p udp --dport 1701 -j ACCEPT
iptables -C INPUT -p esp -j ACCEPT 2>/dev/null || iptables -A INPUT -p esp -j ACCEPT
iptables -C FORWARD -s "${SVP_POOL_CIDR}" -j ACCEPT 2>/dev/null || iptables -A FORWARD -s "${SVP_POOL_CIDR}" -j ACCEPT
iptables -C FORWARD -d "${SVP_POOL_CIDR}" -j ACCEPT 2>/dev/null || iptables -A FORWARD -d "${SVP_POOL_CIDR}" -j ACCEPT

mkdir -p /etc/iptables
iptables-save >/etc/iptables/rules.v4
systemctl enable --now netfilter-persistent >/dev/null 2>&1 || true

if [[ "${SVP_ENABLE_ACCT}" == "1" ]]; then
    echo "[*] Installing per-user accounting hooks ..."
    mkdir -p /var/log/svpt /etc/ppp/ip-up.d /etc/ppp/ip-down.d
    touch /var/log/svpt/acct.tsv
    chmod 640 /var/log/svpt/acct.tsv

    cat >/etc/ppp/ip-up.d/svpt-acct <<'UP'
#!/bin/sh
# args: iface tty speed local-ip remote-ip ipparam
[ -z "$PEERNAME" ] && exit 0
/sbin/iptables -C FORWARD -s "$5" -m comment --comment "svpt:$PEERNAME" -j ACCEPT 2>/dev/null \
    || /sbin/iptables -I FORWARD 1 -s "$5" -m comment --comment "svpt:$PEERNAME" -j ACCEPT
/sbin/iptables -C FORWARD -d "$5" -m comment --comment "svpt:$PEERNAME" -j ACCEPT 2>/dev/null \
    || /sbin/iptables -I FORWARD 1 -d "$5" -m comment --comment "svpt:$PEERNAME" -j ACCEPT
exit 0
UP

    cat >/etc/ppp/ip-down.d/svpt-acct <<'DOWN'
#!/bin/sh
# args: iface tty speed local-ip remote-ip ipparam
[ -z "$PEERNAME" ] && exit 0
LOG=/var/log/svpt/acct.tsv
# Sum bytes from both rules matching this user's comment
BYTES=$(/sbin/iptables -nvxL FORWARD 2>/dev/null | awk -v u="svpt:$PEERNAME" '$0 ~ u {s += $2} END {print s+0}')
if [ -n "$BYTES" ] && [ "$BYTES" -gt 0 ]; then
    printf '%s\t%s\n' "$PEERNAME" "$BYTES" >> "$LOG"
fi
/sbin/iptables -D FORWARD -s "$5" -m comment --comment "svpt:$PEERNAME" -j ACCEPT 2>/dev/null || true
/sbin/iptables -D FORWARD -d "$5" -m comment --comment "svpt:$PEERNAME" -j ACCEPT 2>/dev/null || true
exit 0
DOWN

    chmod 755 /etc/ppp/ip-up.d/svpt-acct /etc/ppp/ip-down.d/svpt-acct
fi

echo "[*] Creating SSH user '${SVP_SSH_USER}' ..."
if ! id -u "${SVP_SSH_USER}" >/dev/null 2>&1; then
    adduser --disabled-password --gecos "" "${SVP_SSH_USER}"
fi
install -d -m 700 -o "${SVP_SSH_USER}" -g "${SVP_SSH_USER}" "/home/${SVP_SSH_USER}/.ssh"
AUTH="/home/${SVP_SSH_USER}/.ssh/authorized_keys"
touch "${AUTH}"
chown "${SVP_SSH_USER}:${SVP_SSH_USER}" "${AUTH}"
chmod 600 "${AUTH}"
if [[ -n "${SVP_SSH_PUBKEY}" ]]; then
    grep -qxF "${SVP_SSH_PUBKEY}" "${AUTH}" 2>/dev/null || echo "${SVP_SSH_PUBKEY}" >>"${AUTH}"
fi

echo "[*] Writing /etc/sudoers.d/svpbot (limited commands) ..."
cat >/etc/sudoers.d/svpbot <<EOF
# Allow bot SSH user to manage L2TP users only.
${SVP_SSH_USER} ALL=(root) NOPASSWD: /usr/bin/tee -a /etc/ppp/chap-secrets, /usr/bin/sed -i /^* /etc/ppp/chap-secrets, /bin/systemctl reload xl2tpd, /bin/cat /var/log/svpt/acct.tsv, /bin/cat /etc/ppp/chap-secrets
Defaults:${SVP_SSH_USER} !requiretty
EOF
chmod 440 /etc/sudoers.d/svpbot
visudo -cf /etc/sudoers.d/svpbot >/dev/null

echo "[*] Enabling services ..."
systemctl enable --now strongswan-starter 2>/dev/null || systemctl enable --now strongswan 2>/dev/null || true
systemctl enable --now xl2tpd
systemctl restart strongswan-starter 2>/dev/null || systemctl restart strongswan 2>/dev/null || true
systemctl restart xl2tpd

# Detect public IP for admin print.
PUBIP="$(curl -fsSL --max-time 5 https://api.ipify.org || hostname -I | awk '{print $1}')"

cat <<MSG

================================================================
  L2TP/IPsec server ready.
================================================================
  Public IP        : ${PUBIP}
  L2TP WAN iface   : ${SVP_WAN_IF}
  IPsec PSK        : ${SVP_L2TP_PSK}
  DNS pushed       : ${SVP_DNS1}, ${SVP_DNS2}
  Client pool      : ${SVP_POOL_RANGE}
  chap-secrets     : /etc/ppp/chap-secrets (managed by plugin)
  SSH user         : ${SVP_SSH_USER}
  Accounting       : $([[ "${SVP_ENABLE_ACCT}" == "1" ]] && echo "ENABLED (/var/log/svpt/acct.tsv)" || echo "disabled")

Next step — in WordPress → SimpleVPBot → «سرورهای L2TP» add a server with:

  SSH host             : ${PUBIP}
  SSH port             : 22
  SSH user             : ${SVP_SSH_USER}
  SSH auth             : key (paste the private key that matches the public key you pushed above)
  L2TP host (نمایش)    : ${PUBIP}
  L2TP PSK             : ${SVP_L2TP_PSK}
  chap path            : /etc/ppp/chap-secrets
  Reload command       : sudo /bin/systemctl reload xl2tpd
  Usage command (opt.) : sudo /bin/cat /var/log/svpt/acct.tsv | awk '\$1==\"{username}\"{s+=\$2}END{print s+0}'

If you did NOT paste a public key via SVP_SSH_PUBKEY, do it now:
  echo 'ssh-ed25519 AAAA... wp' >> /home/${SVP_SSH_USER}/.ssh/authorized_keys

Test from WordPress host:
  ssh -i ~/.ssh/svpbot_ed25519 ${SVP_SSH_USER}@${PUBIP} "sudo /bin/systemctl is-active xl2tpd"
================================================================
MSG
