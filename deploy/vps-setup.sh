#!/usr/bin/env bash
# =============================================================================
#  Preparação da VPS (Ubuntu 22.04/24.04 — Hostinger KVM). Executado via:
#    make setup-vps DOMAIN=... ACME_EMAIL=...     (na sua máquina)
#  Idempotente: pode rodar de novo sem estragar nada.
# =============================================================================
set -euo pipefail
: "${VPS_DIR:?}" "${GIT_REPO:?}" "${GIT_BRANCH:?}" "${DOMAIN:?}" "${ACME_EMAIL:?}"
export DEBIAN_FRONTEND=noninteractive
SUDO=""; [ "$(id -u)" -eq 0 ] || SUDO="sudo"

echo "==> Pacotes básicos"
$SUDO apt-get update -qq
$SUDO apt-get install -y -qq git make curl openssl ufw fail2ban unattended-upgrades >/dev/null

echo "==> Docker"
if ! command -v docker >/dev/null; then
  curl -fsSL https://get.docker.com | $SUDO sh >/dev/null
fi
$SUDO systemctl enable --now docker >/dev/null
[ "$(id -u)" -eq 0 ] || $SUDO usermod -aG docker "$USER"

echo "==> Firewall (SSH, HTTP, HTTPS)"
$SUDO ufw allow OpenSSH >/dev/null
$SUDO ufw allow 80/tcp >/dev/null
$SUDO ufw allow 443/tcp >/dev/null
$SUDO ufw allow 443/udp >/dev/null
$SUDO ufw --force enable >/dev/null
$SUDO systemctl enable --now fail2ban >/dev/null

echo "==> Swap (se a VPS não tiver)"
if [ "$(swapon --show | wc -l)" -eq 0 ]; then
  $SUDO fallocate -l 2G /swapfile && $SUDO chmod 600 /swapfile && $SUDO mkswap /swapfile >/dev/null && $SUDO swapon /swapfile
  echo '/swapfile none swap sw 0 0' | $SUDO tee -a /etc/fstab >/dev/null
fi

echo "==> Código em $VPS_DIR"
$SUDO mkdir -p "$VPS_DIR" && $SUDO chown "$(id -u):$(id -g)" "$VPS_DIR"
if [ ! -d "$VPS_DIR/.git" ]; then
  git clone --quiet --branch "$GIT_BRANCH" "$GIT_REPO" "$VPS_DIR"
else
  git -C "$VPS_DIR" pull --ff-only --quiet
fi
cd "$VPS_DIR"
mkdir -p backups && chmod 700 backups

echo "==> .env"
if [ ! -f .env ]; then
  make --no-print-directory env
fi
sed -i "s|^DOMAIN=.*|DOMAIN=$DOMAIN|; s|^ACME_EMAIL=.*|ACME_EMAIL=$ACME_EMAIL|; s|^APP_URL=.*|APP_URL=https://$DOMAIN|" .env
chmod 600 .env

IP=$(curl -fsS --max-time 5 https://api.ipify.org || echo "?")
DNS=$(getent hosts "$DOMAIN" | awk '{print $1}' | head -1 || true)
echo
echo "Pronto. IP desta VPS: $IP | $DOMAIN resolve para: ${DNS:-(não resolve)}"
[ "$IP" = "$DNS" ] || echo "ATENÇÃO: crie/ajuste o registro DNS tipo A de $DOMAIN para $IP antes do deploy (o HTTPS depende disso)."
echo "Próximos passos (na sua máquina):  make deploy   e depois   make ssh  ->  make seed"
