#!/bin/bash
# Roda uma única vez, na primeira inicialização do volume do MySQL.
# Reproduz o passo "Ambiente de desenvolvimento > 1. Banco de dados" do README:
# um usuário de aplicação (SELECT/INSERT/UPDATE/DELETE) e um de migrations (ALL).
set -euo pipefail

: "${DB_DATABASE:?DB_DATABASE não definido}"
: "${DB_USERNAME:?DB_USERNAME não definido}"
: "${DB_PASSWORD:?DB_PASSWORD não definido}"
: "${DB_MIGRATE_USERNAME:?DB_MIGRATE_USERNAME não definido}"
: "${DB_MIGRATE_PASSWORD:?DB_MIGRATE_PASSWORD não definido}"

mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS \`${DB_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

    CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'%' IDENTIFIED BY '${DB_PASSWORD}';
    GRANT SELECT, INSERT, UPDATE, DELETE ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'%';

    CREATE USER IF NOT EXISTS '${DB_MIGRATE_USERNAME}'@'%' IDENTIFIED BY '${DB_MIGRATE_PASSWORD}';
    GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_MIGRATE_USERNAME}'@'%';

    FLUSH PRIVILEGES;
EOSQL
