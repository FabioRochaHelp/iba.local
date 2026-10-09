# Deploy com Docker em VPS (Hostinger)

Este guia coloca o sistema no ar numa VPS com **Docker**, **HTTPS automático (Let's Encrypt)** e **backup diário**. Você opera tudo pela sua máquina com `make`.

```
Internet ──443/80──▶ web (Caddy: HTTPS + SPA Vue) ──FastCGI──▶ api (PHP 8.3-FPM) ──▶ db (MySQL 8)
                                                                scheduler (limpeza diária + backup)
```

- **Rede:** só as portas 80 e 443 ficam abertas. O MySQL fica numa rede interna, sem acesso pela internet.
- **Banco:** o usuário da aplicação só tem `SELECT/INSERT/UPDATE/DELETE`. As migrations usam um usuário separado.
- **Dados:** os volumes `db_data` (banco), `backups`, `app_logs` e `caddy_data` (certificados) sobrevivem a deploys e reinícios.

---

## Rodar o Docker na sua máquina (local)

Esta é a mesma estrutura de produção, só que em HTTP, sem certificado, em `http://localhost:8880`.

```bash
make local                                   # cria o .env local, faz build, sobe e roda as migrations
make seed                                    # banco vazio: cria o admin e mostra a senha temporária
make local-db FILE=~/backups/dump.sql.gz     # ou: carrega um dump com seus dados (mesmos logins/senhas)
```

| Comando | O que faz |
|---|---|
| `make local` | Sobe, ou atualiza depois de mudar o código (rebuild + migrations) |
| `make logs` · `make logs S=api` | Logs |
| `make reset-password EMAIL=...` | Nova senha temporária, só no banco do Docker |
| `make local-down` | Para tudo, mantendo os dados |
| `make local-reset` | Apaga containers, banco e volumes locais |
| `make local LOCAL_PORT=9000` | Usa outra porta, se a 8880 estiver ocupada |

> O `.env` local fica na raiz do projeto (está no `.gitignore`) e é marcado com `SITE_ADDRESS=:80`. Os alvos locais se recusam a usar um `.env` de produção.
> O Docker usa um banco **próprio**, separado do MySQL da máquina (`iba_db`) usado pelo `npm run dev` / `composer serve`.

---

## Pré-requisitos

| Onde | O quê |
|---|---|
| **Hostinger** | VPS KVM com **Ubuntu 22.04 ou 24.04**. KVM 1 (1 vCPU / 4 GB) é suficiente. |
| **DNS** | Registro **tipo A** do domínio (ex.: `gestao.irmaosdabola.com.br`) apontando para o **IP da VPS**. No hPanel: *Domínios → DNS*. |
| **Firewall do hPanel** | Se estiver ativo (*VPS → Firewall*), libere as portas **22, 80 e 443**. |
| **Sua máquina** | `make`, `ssh` e `scp`. Acesso SSH à VPS por chave; a senha de root também funciona. |
| **GitHub** | O código precisa estar no repositório (`git push`), porque a VPS baixa de lá. |

---

## 1ª instalação

**1. Configure o acesso à VPS**, na raiz do projeto, na sua máquina:
```bash
cp deploy.env.example deploy.env      # fica só na sua máquina (está no .gitignore)
nano deploy.env                       # VPS_HOST=IP da VPS, VPS_USER=root
```

**2. Prepare a VPS.** O script instala Docker, firewall (ufw), fail2ban e swap, clona o repositório em `/opt/iba` e cria o `.env` com senhas aleatórias:
```bash
make setup-vps DOMAIN=gestao.irmaosdabola.com.br ACME_EMAIL=seu@email.com
```
No final, ele avisa se o DNS do domínio ainda não aponta para a VPS. Sem isso o HTTPS não é emitido.

**3. Revise o `.env` da VPS**, se quiser. Os campos mais importantes são `ADMIN_EMAIL` e `AUTO_INVOICES`:
```bash
make ssh
nano .env          # as senhas do banco já foram geradas; não precisa mexer nelas
exit
```

**4. Publique.** O comando faz build das imagens, sobe os containers, roda as migrations e testa o `https://DOMÍNIO/api/health`:
```bash
make deploy
```

**5. Escolha uma origem para os dados:**

- **Opção A — levar os dados que você já tem no computador** (atletas, mensalidades, usuários):
  ```bash
  # gere um dump do banco local (ex.: o arquivo em ~/backups/iba-local-para-vps-*.sql.gz)
  make push-db FILE=~/backups/iba-local-para-vps-AAAAMMDD-HHMM.sql.gz
  ```
  O comando pede confirmação (`SIM`), restaura o dump e aplica migrations pendentes. Os logins e senhas continuam os mesmos do seu ambiente local.

- **Opção B — começar do zero**, com o primeiro admin e uma senha temporária mostrada na tela:
  ```bash
  make ssh
  make seed
  ```

**6.** Acesse `https://gestao.irmaosdabola.com.br`. Para conferir a configuração de produção:
```bash
make remote-check
```

---

## Dia a dia (na sua máquina)

| Comando | O que faz |
|---|---|
| `make deploy` | Publica a última versão do GitHub (`git push` antes!) |
| `make remote-ps` | Situação dos containers |
| `make remote-logs` / `make remote-logs S=api` | Logs ao vivo (Ctrl+C sai) |
| `make remote-backup` | Faz backup agora e baixa para `./backups` na sua máquina |
| `make remote-check` | Checagem de segurança e configuração |
| `make ssh` | Terminal na VPS, já em `/opt/iba` |

## Na VPS (depois de `make ssh`)

| Comando | O que faz |
|---|---|
| `make ps` · `make logs S=web` | Situação e logs |
| `make backup` | Backup agora (em `./backups` e no volume) |
| `make restore FILE=backups/iba-....sql.gz` | Restaura um backup (pede confirmação) |
| `make reset-password EMAIL=admin@...` | Gera nova senha temporária (ex.: admin esqueceu a senha) |
| `make import-planilha FILE=planilha.xlsx DRY=1` | Simula a carga da planilha CONTROLE MENSALIDADE; sem `DRY=1`, grava |
| `make migrate` · `make shell` · `make db-shell` | Manutenção |
| `make down` · `make up` | Parar e subir (os dados ficam nos volumes) |

---

## Rotinas automáticas (container `scheduler`)

| Quando | O quê |
|---|---|
| Todo dia, 03:00 | Limpeza de sessões expiradas, tentativas de login antigas e logs com mais de 90 dias |
| Todo dia, 03:30 | **Backup do banco** (gzip), mantendo `BACKUP_KEEP_DAYS` dias (padrão: 14) |
| Dia 1º, 06:00 | Gera as mensalidades do mês, **só se** `AUTO_INVOICES=true` no `.env` |

> **Guarde backups fora da VPS.** Rode `make remote-backup` de vez em quando, ou ative o backup semanal da própria Hostinger em *VPS → Backups*.

---

## Atualizar a VPS / mudar configurações

- **Nova versão do sistema:** `git push` e depois `make deploy`. As migrations rodam sozinhas.
- **Mudou algo no `.env` da VPS:** `make ssh` e depois `make up`. Os containers são recriados com a nova configuração.
- **Trocar de domínio:** ajuste `DOMAIN` e `APP_URL` no `.env` e rode `make up`. O certificado novo é emitido automaticamente.

## Problemas comuns

| Sintoma | Causa provável / solução |
|---|---|
| `make health` avisa que não respondeu | O DNS ainda está propagando ou as portas 80/443 estão bloqueadas no firewall do hPanel. Veja `make remote-logs S=web` |
| Login volta para a tela de login | `APP_URL` diferente do endereço acessado (com/sem `www`). Liste os dois separados por vírgula |
| "Origem da requisição não permitida" | Mesmo caso acima: ajuste `APP_URL` |
| Erro "Código XXXX" numa tela | `make remote-logs S=api` e procure o código |
| Esqueceu a senha do admin | `make ssh` e depois `make reset-password EMAIL=admin@...` |

### Observação sobre o IP dos usuários
O bloqueio de força bruta conta as tentativas por e-mail e por IP. Em IPv4, o Docker repassa o IP real do visitante. Acessos por **IPv6** passam pelo proxy do Docker e chegam com o IP interno, então nesses casos o bloqueio passa a valer só por e-mail.
