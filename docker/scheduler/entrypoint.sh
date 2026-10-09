#!/bin/sh
# Agendador: monta o crontab e roda o crond em primeiro plano.
# AUTO_INVOICES=true acrescenta a geração automática das mensalidades (dia 1º, 06h).
set -eu
CRONTAB_FILE=/tmp/iba-crontab
cp /etc/iba/crontab "$CRONTAB_FILE"
if [ "${AUTO_INVOICES:-false}" = "true" ]; then
  echo "0 6 1 * * php /app/scripts/generate_invoices.php" >> "$CRONTAB_FILE"
fi
echo "[iba] tarefas agendadas:"; grep -v '^#' "$CRONTAB_FILE"
exec supercronic -passthrough-logs "$CRONTAB_FILE"
