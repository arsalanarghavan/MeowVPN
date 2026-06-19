#!/usr/bin/env bash
# Terminal outbound proxy for this project (user: 127.0.0.1:10808).
# Usage: source "$(dirname "${BASH_SOURCE[0]}")/proxy-env.sh"
#    or: . scripts/proxy-env.sh

export HTTP_PROXY=http://127.0.0.1:10808
export HTTPS_PROXY=http://127.0.0.1:10808
export http_proxy=http://127.0.0.1:10808
export https_proxy=http://127.0.0.1:10808
# Tools that honor ALL_PROXY (curl, some CLIs); socks5h resolves DNS via proxy.
export ALL_PROXY=socks5h://127.0.0.1:10808
export all_proxy=socks5h://127.0.0.1:10808
export NO_PROXY=127.0.0.1,localhost,::1
export no_proxy=127.0.0.1,localhost,::1
