#!/bin/bash
# Executado só na PRIMEIRA criação do volume do MySQL.
# Cria os dois usuários do sistema com privilégios mínimos:
#   - aplicação:  SELECT/INSERT/UPDATE/DELETE  (o sistema no dia a dia)
#   - migrations: ALL PRIVILEGES no banco       (só database/migrate.php)
set -euo pipefail

mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'%' IDENTIFIED BY '${DB_PASSWORD}';
GRANT SELECT, INSERT, UPDATE, DELETE ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'%';

CREATE USER IF NOT EXISTS '${DB_MIGRATE_USERNAME}'@'%' IDENTIFIED BY '${DB_MIGRATE_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_MIGRATE_USERNAME}'@'%';

FLUSH PRIVILEGES;
SQL
echo "[iba] usuários do banco criados."
