#!/usr/bin/env bash
set -euo pipefail

ARCH="${1:-amd64}"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "${TMP_DIR}"' EXIT

case "${ARCH}" in
  amd64) FILENAME="cloudflared-linux-amd64" ;;
  arm64) FILENAME="cloudflared-linux-arm64" ;;
  arm) FILENAME="cloudflared-linux-arm" ;;
  *)
    echo "Unsupported architecture: ${ARCH}" >&2
    echo "Supported values: amd64, arm64, arm" >&2
    exit 1
    ;;
esac

URL="https://github.com/cloudflare/cloudflared/releases/latest/download/${FILENAME}"

curl -fsSL "${URL}" -o "${TMP_DIR}/cloudflared"
chmod +x "${TMP_DIR}/cloudflared"
"${TMP_DIR}/cloudflared" --version

sudo install -m 755 "${TMP_DIR}/cloudflared" /usr/local/bin/cloudflared

cat <<'EOF'
cloudflared binary is installed at /usr/local/bin/cloudflared

Next:
  1. Create a remotely-managed tunnel in the Cloudflare dashboard.
  2. Copy the tunnel token.
  3. Run: sudo cloudflared service install <TUNNEL_TOKEN>
EOF
