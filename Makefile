# =============================================================================
#  Irmãos da Bola Academy — Docker + deploy em VPS (Hostinger)
#
#  Na sua máquina:  make setup-vps DOMAIN=... ACME_EMAIL=...   (1ª vez)
#                   make deploy                                 (cada versão)
#  Na VPS:          make up | logs | backup | migrate | ...
#  Lista completa:  make help
# =============================================================================
SHELL := /bin/bash
.DEFAULT_GOAL := help
.ONESHELL:

-include deploy.env
VPS_USER   ?= root
VPS_PORT   ?= 22
VPS_DIR    ?= /opt/iba
GIT_REPO   ?= https://github.com/FabioRochaHelp/iba.local.git
GIT_BRANCH ?= main

COMPOSE := docker compose
SSH      = ssh -p $(VPS_PORT) $(VPS_USER)@$(VPS_HOST)
# O .env não é incluído no make (senhas com $ ou # quebrariam); só lemos o domínio.
DOMAIN_FROM_ENV := $(shell sed -n 's/^DOMAIN=//p' .env 2>/dev/null)

define require_vps
	@if [ -z "$(VPS_HOST)" ]; then echo "Defina VPS_HOST em deploy.env (copie de deploy.env.example)."; exit 1; fi
endef

.PHONY: help
help: ## Lista os comandos
	@awk 'BEGIN{FS=":.*## "} /^## /{printf "\n\033[1m%s\033[0m\n", substr($$0,4)} /^[a-zA-Z_-]+:.*## /{printf "  \033[33m%-18s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

## Containers (rodar na VPS, ou localmente para testar)
.PHONY: env build up down restart ps logs release
env: ## Cria o .env a partir do modelo, com senhas aleatórias
	@if [ -f .env ]; then echo ".env já existe — nada a fazer."; exit 0; fi
	cp .env.docker.example .env && chmod 600 .env
	for v in MYSQL_ROOT_PASSWORD DB_PASSWORD DB_MIGRATE_PASSWORD; do
	  sed -i "s|^$$v=$$|$$v=$$(openssl rand -hex 24)|" .env
	done
	echo ".env criado com senhas aleatórias. Ajuste DOMAIN, ACME_EMAIL, APP_URL e ADMIN_EMAIL."

build: ## Constrói as imagens
	$(COMPOSE) build --pull

up: ## Sobe tudo em segundo plano
	$(COMPOSE) up -d --remove-orphans

down: ## Para tudo (os dados ficam nos volumes)
	$(COMPOSE) down

restart: ## Reinicia os containers
	$(COMPOSE) restart

ps: ## Situação dos containers
	$(COMPOSE) ps

logs: ## Logs (make logs S=api para um serviço)
	$(COMPOSE) logs -f --tail=200 $(S)

release: ## Build + sobe + migrations + checagem (usado pelo deploy)
	@test -f .env || { echo "Falta o .env (rode: make env)"; exit 1; }
	$(COMPOSE) build --pull
	$(COMPOSE) up -d --remove-orphans
	$(MAKE) --no-print-directory wait-db migrate
	$(COMPOSE) restart api scheduler
	docker image prune -f >/dev/null
	$(MAKE) --no-print-directory health

## Docker local (desenvolvimento/teste na sua máquina)
LOCAL_PORT ?= 8880
.PHONY: local local-env local-db local-down local-reset
local-env: ## Cria o .env local (HTTP em localhost:LOCAL_PORT, sem Let's Encrypt)
	@if [ -f .env ]; then
	  grep -q '^SITE_ADDRESS=:80' .env || { echo "Já existe um .env que não é local (produção?). Renomeie-o antes."; exit 1; }
	  echo ".env local já existe."; exit 0
	fi
	$(MAKE) --no-print-directory env >/dev/null
	sed -i 's|^DOMAIN=.*|DOMAIN=localhost|; s|^ACME_EMAIL=.*|ACME_EMAIL=dev@localhost|; s|^APP_URL=.*|APP_URL=http://localhost:$(LOCAL_PORT)|; s|^HTTP_PORT=.*|HTTP_PORT=$(LOCAL_PORT)|; s|^HTTPS_PORT=.*|HTTPS_PORT=$(shell echo $$(( $(LOCAL_PORT) + 1 )))|' .env
	printf '\n# --- MODO LOCAL: HTTP sem certificado ---\nSITE_ADDRESS=:80\nSESSION_SECURE=false\n' >> .env
	echo ".env local criado (http://localhost:$(LOCAL_PORT))."

local: local-env ## Sobe o sistema no Docker local: http://localhost:8880
	$(COMPOSE) build
	$(COMPOSE) up -d --remove-orphans
	$(MAKE) --no-print-directory wait-db migrate
	echo
	echo "Sistema no ar: http://localhost:$(LOCAL_PORT)"
	echo "Banco vazio? rode 'make seed' (cria admin) ou 'make local-db FILE=dump.sql.gz' (traz seus dados)."

local-db: ## Carrega um dump no Docker local: make local-db FILE=~/backups/....sql.gz
	@test -f "$(FILE)" || { echo "Informe FILE=caminho/do/dump.sql.gz"; exit 1; }
	gunzip -c "$(FILE)" | $(COMPOSE) exec -T db sh -c 'mysql -uroot -p"$$MYSQL_ROOT_PASSWORD" "$$DB_DATABASE"'
	$(MAKE) --no-print-directory migrate
	echo "Dados carregados. Os logins/senhas são os mesmos do dump."

local-down: ## Para o Docker local (mantém os dados)
	$(COMPOSE) down

local-reset: ## APAGA o Docker local (containers, banco e volumes)
	@read -p "Apagar containers, banco e volumes locais? Digite SIM: " ok; [ "$$ok" = "SIM" ] || exit 1
	$(COMPOSE) down -v

## Banco e aplicação
.PHONY: wait-db migrate seed check health shell db-shell backup restore reset-password import-planilha
wait-db:
	@echo -n "Aguardando o MySQL"; for i in $$(seq 1 60); do
	  [ "$$($(COMPOSE) ps db --format '{{.Health}}')" = "healthy" ] && { echo " ok"; exit 0; }; echo -n "."; sleep 2; done
	echo " tempo esgotado"; exit 1

migrate: ## Aplica migrations pendentes
	$(COMPOSE) exec -T api php database/migrate.php

seed: ## Migrations + dados iniciais + 1º admin (mostra a senha temporária)
	$(COMPOSE) exec -T api php database/migrate.php --seed

check: ## Verifica se a instalação está pronta para produção
	$(COMPOSE) exec -T api php scripts/check_install.php

health: ## Testa https://DOMAIN/api/health
	@d="$(DOMAIN_FROM_ENV)"; for i in $$(seq 1 20); do
	  if curl -fsS --max-time 5 "https://$$d/api/health" >/dev/null 2>&1; then echo "OK: https://$$d no ar"; exit 0; fi; sleep 3; done
	echo "Aviso: https://$$d/api/health não respondeu (DNS/certificado ainda propagando?). Veja: make logs S=web"

shell: ## Terminal no container da API
	$(COMPOSE) exec api sh

db-shell: ## Console MySQL (usuário da aplicação)
	$(COMPOSE) exec db sh -c 'mysql -u"$$DB_USERNAME" -p"$$DB_PASSWORD" "$$DB_DATABASE"'

backup: ## Backup agora (fica no volume e com cópia em ./backups)
	mkdir -p backups && chmod 700 backups
	out=$$($(COMPOSE) exec -T scheduler iba-backup) && echo "$$out"
	f=$$(echo "$$out" | sed -n 's|.*backup: /backups/\([^ ]*\).*|\1|p')
	$(COMPOSE) cp "scheduler:/backups/$$f" "backups/$$f" && chmod 600 "backups/$$f" && echo "cópia local: backups/$$f"

restore: ## Restaura um backup: make restore FILE=backups/iba-....sql.gz
	@test -f "$(FILE)" || { echo "Informe FILE=caminho/do/backup.sql.gz"; exit 1; }
	read -p "Isto SUBSTITUI os dados atuais pelo backup $(FILE). Digite SIM para continuar: " ok; [ "$$ok" = "SIM" ] || exit 1
	gunzip -c "$(FILE)" | $(COMPOSE) exec -T db sh -c 'mysql -uroot -p"$$MYSQL_ROOT_PASSWORD" "$$DB_DATABASE"'
	$(MAKE) --no-print-directory migrate
	echo "Restaurado."

reset-password: ## Nova senha temporária: make reset-password EMAIL=admin@...
	@test -n "$(EMAIL)" || { echo "Informe EMAIL=..."; exit 1; }
	$(COMPOSE) exec -T api php scripts/reset_password.php "$(EMAIL)"

import-planilha: ## Carga da planilha: make import-planilha FILE=... [DRY=1]
	@test -f "$(FILE)" || { echo "Informe FILE=caminho/da/planilha.xlsx"; exit 1; }
	$(COMPOSE) cp "$(FILE)" api:/tmp/planilha.xlsx
	$(COMPOSE) exec -T api php scripts/import_controle_mensalidade.php /tmp/planilha.xlsx $(if $(DRY),--dry-run,)
	$(COMPOSE) exec -T -u root api rm -f /tmp/planilha.xlsx

## Deploy (rodar na SUA máquina; usa deploy.env)
.PHONY: setup-vps deploy push-db ssh remote-logs remote-ps remote-backup remote-check
setup-vps: ## 1ª vez: instala Docker, firewall, clona o repo e cria o .env na VPS
	$(require_vps)
	@test -n "$(DOMAIN)" -a -n "$(ACME_EMAIL)" || { echo "Use: make setup-vps DOMAIN=gestao.seudominio.com.br ACME_EMAIL=voce@email.com"; exit 1; }
	$(SSH) "VPS_DIR='$(VPS_DIR)' GIT_REPO='$(GIT_REPO)' GIT_BRANCH='$(GIT_BRANCH)' DOMAIN='$(DOMAIN)' ACME_EMAIL='$(ACME_EMAIL)' bash -s" < deploy/vps-setup.sh

deploy: ## Publica a versão do GitHub (branch GIT_BRANCH) na VPS
	$(require_vps)
	@echo "Publicando $(GIT_BRANCH) em $(VPS_HOST):$(VPS_DIR)"
	$(SSH) "set -e; cd '$(VPS_DIR)'; git fetch --quiet origin; git checkout --quiet '$(GIT_BRANCH)'; git pull --ff-only --quiet origin '$(GIT_BRANCH)'; echo \"versão: \$$(git log -1 --format='%h %s')\"; make release"

push-db: ## Envia um dump local para a VPS e restaura: make push-db FILE=...sql.gz
	$(require_vps)
	@test -f "$(FILE)" || { echo "Informe FILE=caminho/do/dump.sql.gz (ex.: de ~/backups)"; exit 1; }
	scp -P $(VPS_PORT) "$(FILE)" $(VPS_USER)@$(VPS_HOST):$(VPS_DIR)/backups/upload.sql.gz
	$(SSH) -t "cd '$(VPS_DIR)' && make restore FILE=backups/upload.sql.gz && rm -f backups/upload.sql.gz"

ssh: ## Abre um terminal na VPS, já na pasta do projeto
	$(require_vps)
	$(SSH) -t "cd '$(VPS_DIR)' && exec bash -l"

remote-logs: ## Logs da VPS (S=serviço opcional)
	$(require_vps)
	$(SSH) -t "cd '$(VPS_DIR)' && make logs S='$(S)'"

remote-ps: ## Situação dos containers na VPS
	$(require_vps)
	$(SSH) "cd '$(VPS_DIR)' && make ps"

remote-backup: ## Backup na VPS e download para ./backups
	$(require_vps)
	$(SSH) "cd '$(VPS_DIR)' && make backup"
	mkdir -p backups && scp -P $(VPS_PORT) "$(VPS_USER)@$(VPS_HOST):$(VPS_DIR)/backups/$$($(SSH) "ls -1t '$(VPS_DIR)/backups' | head -1")" backups/

remote-check: ## Checagem de produção na VPS
	$(require_vps)
	$(SSH) "cd '$(VPS_DIR)' && make check"
