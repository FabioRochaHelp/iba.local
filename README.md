# Irmãos da Bola Academy — Sistema de Gestão

Backend **PHP 8.1+ (MVC + SOLID, sem framework)** · Frontend **Vue 3 + Vite + PrimeVue 4** · **MySQL 8 / MariaDB 10.4+**

Feito para rodar em **hospedagem compartilhada** (Apache + PHP + MySQL, sem Node e sem SSH no servidor).

```
backend/    API REST (JSON). Front controller em public/index.php
frontend/   SPA Vue 3 (tema IBA em src/theme)
deploy/     build.sh (gera o pacote) + README.md (guia de publicação no cPanel)
docs/       planilha original de referência
```

## Módulos

| Módulo | Admin | Professor |
|---|---|---|
| Atletas e responsáveis (importação da planilha CSV/XLSX) | total | consulta (sem valores/CPF) |
| Planos, mensalidades, pagamentos/estornos, inadimplência + WhatsApp | total | — |
| Uniformes (pedidos, pagamento, entrega) e patrocínios | total | — |
| Turmas e chamada (mobile), relatórios de frequência | total | só as próprias turmas |
| Evolução do atleta: avaliações (1–5 por critério), medidas físicas, observações, metas | total | registra só para atletas das suas turmas |
| Usuários (senha temporária) e auditoria | total | — |

**Portal (somente leitura):** o perfil **Atleta** vê a própria evolução (avaliações e observações marcadas como visíveis, medidas e metas), a frequência e as turmas e horários. O perfil **Responsável** vê o mesmo para cada filho vinculado. Os dois são negados em todo o resto.

## Publicar em produção

```bash
bash deploy/build.sh      # testes + auditoria + build → dist/iba-deploy-*.zip
```
Depois siga **[deploy/README.md](deploy/README.md)**, o passo a passo no cPanel (banco, upload, `.env`, cron e verificação).

## Ambiente de desenvolvimento

### 1. Banco de dados
```sql
CREATE DATABASE iba CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'iba_app'@'localhost' IDENTIFIED BY 'troque-esta-senha';
GRANT SELECT, INSERT, UPDATE, DELETE ON iba.* TO 'iba_app'@'localhost';
CREATE USER 'iba_migrate'@'localhost' IDENTIFIED BY 'troque-esta-senha-2';
GRANT ALL PRIVILEGES ON iba.* TO 'iba_migrate'@'localhost';
```

### 2. Backend
```bash
cd backend
composer install
cp .env.example .env              # preencha DB_* e DB_MIGRATE_*
php database/migrate.php --seed   # tabelas + admin (senha temporária impressa)
composer serve                    # API em http://localhost:8765
```

### 3. Frontend
```bash
cd frontend
npm install
npm run dev                       # http://localhost:5173 (proxy /api → :8765)
```

### Scripts úteis (backend)
| Comando | Função |
|---|---|
| `php scripts/import_planilha.php arquivo.xlsx [--dry-run] [--mes=AAAA-MM]` | Importa a planilha de atletas (repetir não duplica) |
| `php scripts/generate_invoices.php [--mes=AAAA-MM]` | Gera as mensalidades do mês (repetir não duplica) |
| `php scripts/maintenance.php` | Limpeza diária (sessões, tentativas de login, logs) |
| `php scripts/check_install.php` | Verifica se a instalação está pronta para produção |
| `php database/migrate.php [--seed\|--status\|--dump]` | Migrations e seed; `--dump` gera `schema.sql` |

### Qualidade
```bash
cd backend && vendor/bin/phpunit && vendor/bin/phpstan analyse
cd frontend && npm run lint       # inclui vue/no-v-html = error
```

## Segurança (resumo)
- **SQL Injection:** PDO com prepared statements reais. Colunas de ordenação e filtros só por lista permitida.
- **XSS:** a API só devolve JSON (com escape HEX). O Vue escapa por padrão, `v-html` é proibido e a CSP é `script-src 'self'`. Entradas passam por `strip_tags`.
- **CSRF:** token de sessão no header `X-CSRF-Token`, verificação de Origin/Referer, cookie `SameSite=Strict` e escritas só em JSON.
- **Sessão:** cookie HttpOnly/Secure guardado no MySQL (ID em hash). Expira por inatividade (30 min) e de forma absoluta (8 h), e é regenerada no login.
- **Força bruta e abuso:** bloqueio progressivo após 5 falhas de login e rate limit por IP.
- **Autorização:**
  - rotas negadas por padrão;
  - o professor só acessa as próprias turmas, com checagem por registro;
  - dados financeiros e pessoais são filtrados por perfil.
- **Auditoria:** registra logins, pagamentos, estornos, alterações e exclusões com usuário, IP e data.
- **Deploy:**
  - `app/` fica fora do `public_html`;
  - `.htaccess` bloqueia arquivos sensíveis e força HTTPS;
  - cabeçalhos de segurança (HSTS, CSP, X-Frame-Options...);
  - usuário do banco da aplicação sem privilégio de DDL.
