#!/usr/bin/env bash
# ----------------------------------------------------------------------
#  Gera o pacote para hospedagem compartilhada em dist/:
#
#    dist/public_html/        -> conteúdo do public_html (SPA + /api)
#    dist/app/                -> código PHP, FORA do public_html (~/app)
#    dist/iba-deploy-*.zip    -> tudo compactado para upload
#
#  Uso:  bash deploy/build.sh
#  Requisitos locais: PHP 8.1+, Composer 2, Node 20+
# ----------------------------------------------------------------------
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/dist"
STAMP="$(date +%Y%m%d-%H%M)"

echo "==> Limpando $OUT"
rm -rf "$OUT"
mkdir -p "$OUT/public_html/api" "$OUT/app"

echo "==> Qualidade e segurança (backend)"
( cd "$ROOT/backend" && vendor/bin/phpunit --testsuite Unit --no-progress && vendor/bin/phpstan analyse --no-progress --memory-limit=512M && composer audit --no-interaction )

echo "==> Frontend: dependências, auditoria, lint e build"
( cd "$ROOT/frontend" && npm ci --no-audit --no-fund && npm audit --omit=dev --audit-level=high && npx eslint src && npm run build )
cp -r "$ROOT/frontend/dist/." "$OUT/public_html/"

echo "==> Backend: cópia sem arquivos de desenvolvimento"
rsync -a \
  --exclude 'vendor/' --exclude 'tests/' --exclude '.env' --exclude '.phpunit.cache/' \
  --exclude 'phpunit.xml' --exclude 'phpstan.neon' --exclude 'storage/logs/*.log' --exclude '.gitignore' \
  "$ROOT/backend/" "$OUT/app/"

echo "==> Backend: dependências de produção (sem dev)"
( cd "$OUT/app" && composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --quiet )

echo "==> Front controller da API em public_html/api"
mv "$OUT/app/public/index.php" "$OUT/public_html/api/index.php"
mv "$OUT/app/public/.htaccess" "$OUT/public_html/api/.htaccess"
rmdir "$OUT/app/public"

echo "==> Proteções e arquivos auxiliares"
printf '# Nada nesta pasta deve ser servido pela web\nRequire all denied\n' > "$OUT/app/.htaccess"
cp "$ROOT/deploy/env.production.example" "$OUT/app/.env.example"
( cd "$OUT/app" && php database/migrate.php --dump >/dev/null )   # database/schema.sql para phpMyAdmin
mkdir -p "$OUT/app/storage/logs" && touch "$OUT/app/storage/logs/.gitkeep"
find "$OUT" -type d -exec chmod 755 {} +
find "$OUT" -type f -exec chmod 644 {} +

echo "==> Compactando"
( cd "$OUT" && zip -qr "iba-deploy-$STAMP.zip" public_html app )

echo
echo "Pacote pronto: dist/iba-deploy-$STAMP.zip"
echo "Siga deploy/README.md para publicar."
