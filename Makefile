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

# Produção usa arquivo e .env próprios (o docker-compose.yml/.env são do ambiente de desenvolvimento)
ENV_FILE := .env.prod
# Atrás de reverse proxy (PROXY_MODE=true): só a porta HTTP local é publicada.
PROXY_MODE := $(shell sed -n 's/^PROXY_MODE=//p' $(ENV_FILE) 2>/dev/null | tail -1)
COMPOSE  := docker compose -f docker-compose.prod.yml $(if $(filter true,$(PROXY_MODE)),-f docker-compose.proxy.yml,) --env-file $(ENV_FILE)
SSH      = ssh -p $(VPS_PORT) $(VPS_USER)@$(VPS_HOST)
# O .env.prod não é incluído no make (senhas com $ ou # quebrariam); só lemos o domínio.
DOMAIN_FROM_ENV := $(shell sed -n 's/^DOMAIN=//p' .env.prod 2>/dev/null)

# Mensagem clara quando falta o .env.prod (ex.: VPS configurada antes da troca .env -> .env.prod)
define require_env
	@if [ ! -f $(ENV_FILE) ]; then
	  if [ -f .env ] && grep -q '^MYSQL_ROOT_PASSWORD=' .env; then
	    echo "Falta o $(ENV_FILE). O seu .env tem a configuração de produção (versão anterior):"; echo "  mv .env $(ENV_FILE)"
	  else echo "Falta o $(ENV_FILE). Crie com: make env  (ou make local para teste local)"; fi
	  exit 1
	fi
endef

define require_vps
	@if [ -z "$(VPS_HOST)" ]; then echo "Defina VPS_HOST em deploy.env (copie de deploy.env.example)."; exit 1; fi
endef

.PHONY: help
help: ## Lista os comandos
	@awk 'BEGIN{FS=":.*## "} /^## /{printf "\n\033[1m%s\033[0m\n", substr($$0,4)} /^[a-zA-Z_-]+:.*## /{printf "  \033[33m%-18s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

## Containers (rodar na VPS, ou localmente para testar)
.PHONY: env build up down restart ps logs release
env: ## Cria o .env.prod a partir do modelo, com senhas aleatórias
	@if [ -f $(ENV_FILE) ]; then echo "$(ENV_FILE) já existe — nada a fazer."; exit 0; fi
	cp .env.prod.example $(ENV_FILE) && chmod 600 $(ENV_FILE)
	for v in MYSQL_ROOT_PASSWORD DB_PASSWORD DB_MIGRATE_PASSWORD; do
	  sed -i "s|^$$v=$$|$$v=$$(openssl rand -hex 24)|" $(ENV_FILE)
	done
	echo "$(ENV_FILE) criado com senhas aleatórias. Ajuste DOMAIN, ACME_EMAIL, APP_URL e ADMIN_EMAIL."

build: ## Constrói as imagens
	$(require_env)
	$(COMPOSE) build --pull

up: ## Sobe tudo em segundo plano
	$(require_env)
	$(COMPOSE) up -d --remove-orphans

down: ## Para tudo (os dados ficam nos volumes)
	$(require_env)
	$(COMPOSE) down

restart: ## Reinicia os containers
	$(require_env)
	$(COMPOSE) restart

ps: ## Situação dos containers
	$(require_env)
	$(COMPOSE) ps

logs: ## Logs (make logs S=api para um serviço)
	$(require_env)
	$(COMPOSE) logs -f --tail=200 $(S)

release: ## Build + sobe + migrations + checagem (usado pelo deploy)
	@test -f $(ENV_FILE) || { echo "Falta o $(ENV_FILE) (rode: make env)"; exit 1; }
	$(COMPOSE) build --pull
	$(COMPOSE) up -d --remove-orphans
	$(MAKE) --no-print-directory wait-db migrate
	$(COMPOSE) restart api scheduler
	docker image prune -f >/dev/null
	$(MAKE) --no-print-directory health

## Docker local com a imagem de PRODUÇÃO (dev com hot-reload: docker compose up -d)
LOCAL_PORT ?= 8880
.PHONY: local local-env local-db local-down local-reset
local-env: ## Cria o .env.prod local (HTTP em localhost:LOCAL_PORT, sem Let's Encrypt)
	@if [ -f $(ENV_FILE) ]; then
	  grep -q '^SITE_ADDRESS=:80' $(ENV_FILE) || { echo "Já existe um $(ENV_FILE) que não é local (produção?). Renomeie-o antes."; exit 1; }
	  echo "$(ENV_FILE) local já existe."; exit 0
	fi
	$(MAKE) --no-print-directory env >/dev/null
	sed -i 's|^DOMAIN=.*|DOMAIN=localhost|; s|^ACME_EMAIL=.*|ACME_EMAIL=dev@localhost|; s|^APP_URL=.*|APP_URL=http://localhost:$(LOCAL_PORT)|; s|^HTTP_PORT=.*|HTTP_PORT=$(LOCAL_PORT)|; s|^HTTPS_PORT=.*|HTTPS_PORT=$(shell echo $$(( $(LOCAL_PORT) + 1 )))|' $(ENV_FILE)
	printf '\n# --- MODO LOCAL: HTTP sem certificado ---\nSITE_ADDRESS=:80\nSESSION_SECURE=false\n' >> $(ENV_FILE)
	echo "$(ENV_FILE) local criado (http://localhost:$(LOCAL_PORT))."

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
	$(require_env)
	@echo -n "Aguardando o MySQL"; for i in $$(seq 1 60); do
	  [ "$$($(COMPOSE) ps db --format '{{.Health}}')" = "healthy" ] && { echo " ok"; exit 0; }; echo -n "."; sleep 2; done
	echo " tempo esgotado"; exit 1

migrate: ## Aplica migrations pendentes
	$(require_env)
	$(COMPOSE) exec -T api php database/migrate.php

seed: ## Migrations + dados iniciais + 1º admin (mostra a senha temporária)
	$(require_env)
	$(COMPOSE) exec -T api php database/migrate.php --seed

check: ## Verifica se a instalação está pronta para produção
	$(require_env)
	$(COMPOSE) exec -T api php scripts/check_install.php

health: ## Testa o sistema (porta local e https://DOMAIN)
	@port=$$(sed -n 's/^HTTP_PORT=//p' $(ENV_FILE) | tail -1); d="$(DOMAIN_FROM_ENV)"
	for i in $$(seq 1 15); do curl -fsS --max-time 3 "http://127.0.0.1:$${port:-80}/api/health" >/dev/null 2>&1 && break; sleep 2; done
	if curl -fsS --max-time 3 "http://127.0.0.1:$${port:-80}/api/health" >/dev/null 2>&1; then echo "OK: containers respondendo em http://127.0.0.1:$${port:-80}"
	else echo "ERRO: containers não respondem em 127.0.0.1:$${port:-80}. Veja: make logs S=web / make logs S=api"; exit 1; fi
	if curl -fsS --max-time 8 "https://$$d/api/health" >/dev/null 2>&1; then echo "OK: https://$$d no ar"
	else echo "Aviso: https://$$d ainda não responde (DNS, certificado ou reverse proxy do painel ainda não configurado)."; fi

proxy-mode: ## Ajusta o .env.prod para rodar atrás de reverse proxy (PORT=8880)
	$(require_env)
	@port=$${PORT:-8880}
	sed -i -E '/^#? ?(PROXY_MODE|SITE_ADDRESS|HTTP_BIND|HTTP_PORT|HTTPS_BIND|HTTPS_PORT|TRUSTED_PROXIES)=/d; /^# --- (PROXY|MODO LOCAL).*---$$/d' $(ENV_FILE)
	sed -i 's/^SESSION_SECURE=false/SESSION_SECURE=true/' $(ENV_FILE)
	printf '\n# --- PROXY (make proxy-mode) ---\nPROXY_MODE=true\nSITE_ADDRESS=:80\nHTTP_BIND=127.0.0.1\nHTTP_PORT=%s\nTRUSTED_PROXIES=private_ranges\n' "$$port" >> $(ENV_FILE)
	echo "Modo proxy: o sistema responde em http://127.0.0.1:$$port"
	echo "No painel, crie o reverse proxy:  https://$(DOMAIN_FROM_ENV)  ->  http://127.0.0.1:$$port"
	echo "Aplique com: make up   (e confira com: make health)"

shell: ## Terminal no container da API
	$(require_env)
	$(COMPOSE) exec api sh

db-shell: ## Console MySQL (usuário da aplicação)
	$(require_env)
	$(COMPOSE) exec db sh -c 'mysql -u"$$DB_USERNAME" -p"$$DB_PASSWORD" "$$DB_DATABASE"'

backup: ## Backup agora (fica no volume e com cópia em ./backups)
	$(require_env)
	mkdir -p backups && chmod 700 backups
	out=$$($(COMPOSE) exec -T scheduler iba-backup) && echo "$$out"
	f=$$(echo "$$out" | sed -n 's|.*backup: /backups/\([^ ]*\).*|\1|p')
	$(COMPOSE) cp "scheduler:/backups/$$f" "backups/$$f" && chmod 600 "backups/$$f" && echo "cópia local: backups/$$f"

restore: ## Restaura um backup: make restore FILE=backups/iba-....sql.gz
	$(require_env)
	@test -f "$(FILE)" || { echo "Informe FILE=caminho/do/backup.sql.gz"; exit 1; }
	read -p "Isto SUBSTITUI os dados atuais pelo backup $(FILE). Digite SIM para continuar: " ok; [ "$$ok" = "SIM" ] || exit 1
	gunzip -c "$(FILE)" | $(COMPOSE) exec -T db sh -c 'mysql -uroot -p"$$MYSQL_ROOT_PASSWORD" "$$DB_DATABASE"'
	$(MAKE) --no-print-directory migrate
	echo "Restaurado."

reset-password: ## Nova senha temporária: make reset-password EMAIL=admin@...
	$(require_env)
	@test -n "$(EMAIL)" || { echo "Informe EMAIL=..."; exit 1; }
	$(COMPOSE) exec -T api php scripts/reset_password.php "$(EMAIL)"

import-planilha: ## Carga da planilha: make import-planilha FILE=... [DRY=1]
	$(require_env)
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
