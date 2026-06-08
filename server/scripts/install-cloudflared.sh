#!/usr/bin/env bash
set -euo pipefail

DNF_FLAGS=(
  -y
  --setopt=install_weak_deps=False
  --setopt=tsflags=nodocs
  --setopt=keepcache=0
)

sudo mkdir -p /etc/yum.repos.d /etc/cloudflared
curl -fsSL https://pkg.cloudflare.com/cloudflared.repo | sudo tee /etc/yum.repos.d/cloudflared.repo >/dev/null
sudo dnf install "${DNF_FLAGS[@]}" cloudflared

cloudflared --version

cat <<'EOF'
cloudflared is installed.

Next:
  1. Create a remotely-managed tunnel in the Cloudflare dashboard.
  2. Copy the tunnel token.
  3. Run: sudo cloudflared service install <TUNNEL_TOKEN>
EOF
