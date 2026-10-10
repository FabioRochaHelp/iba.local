#!/bin/sh
# Backup do banco (gzip) em /backups, apagando os mais antigos que BACKUP_KEEP_DAYS.
set -eu
FILE="/backups/iba-$(date +%Y%m%d-%H%M).sql.gz"
MYSQL_PWD="${DB_MIGRATE_PASSWORD}" mariadb-dump --single-transaction --routines --no-tablespaces --hex-blob --skip-ssl \
  -h "${DB_HOST}" -P "${DB_PORT:-3306}" -u "${DB_MIGRATE_USERNAME}" "${DB_DATABASE}" | gzip > "${FILE}.tmp"
mv "${FILE}.tmp" "${FILE}"
chmod 600 "${FILE}"
find /backups -name 'iba-*.sql.gz' -mtime +"${BACKUP_KEEP_DAYS:-14}" -delete
echo "[$(date '+%F %T')] backup: ${FILE} ($(du -h "${FILE}" | cut -f1))"
