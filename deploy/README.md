# Publicação na hospedagem compartilhada (cPanel)

Guia para colocar o sistema **Irmãos da Bola Academy** no ar em uma hospedagem compartilhada com cPanel, **sem precisar de SSH**.

Tempo estimado: 30 a 45 minutos na primeira vez.

---

## 0. O que você precisa

| Item | Onde conferir |
|---|---|
| Hospedagem com **PHP 8.1 ou superior** e **MySQL 5.7+/MariaDB 10.4+** | cPanel → *MultiPHP Manager* / *Select PHP Version* |
| Extensões PHP: `pdo_mysql`, `mbstring`, `json` (obrigatórias); `zip`, `simplexml` (para importar planilha .xlsx) | cPanel → *Select PHP Version* → *Extensions* |
| **Certificado SSL (HTTPS)** ativo no domínio ou subdomínio | cPanel → *SSL/TLS Status* (AutoSSL / Let's Encrypt) |
| Acesso ao **Gerenciador de Arquivos**, **Bancos de Dados MySQL**, **phpMyAdmin** e **Cron Jobs** | cPanel |

> Recomendação: publique em um **subdomínio próprio** (ex.: `gestao.irmaosdabola.com.br`), apontado para o `public_html` dele. Assim o sistema fica isolado do site institucional.

---

## 1. Gerar o pacote (no seu computador)

```bash
bash deploy/build.sh
```

O script roda os testes, a análise estática, a auditoria de dependências e o lint. Se algo falhar, **o pacote não é gerado**.

No fim, cria `dist/iba-deploy-AAAAMMDD-HHMM.zip`, com duas pastas:

```
public_html/   → tudo o que é público (telas + /api)
app/           → código PHP, configurações e dependências (NÃO público)
```

---

## 2. Criar o banco de dados

No cPanel → **Bancos de Dados MySQL**:

1. **Crie o banco**, por exemplo `iba`. O cPanel adiciona o prefixo da conta, ficando algo como `conta_iba`.
2. **Crie dois usuários**, cada um com uma senha forte (use o gerador do cPanel):
   - `conta_iba_app`: usado pelo sistema no dia a dia;
   - `conta_iba_mig`: usado só para criar e atualizar as tabelas.
3. **Adicione os usuários ao banco** com estes privilégios:
   - `conta_iba_app`: marque **somente** `SELECT`, `INSERT`, `UPDATE`, `DELETE`;
   - `conta_iba_mig`: marque **TODOS OS PRIVILÉGIOS**.

> Por que dois usuários? Se alguém conseguir explorar uma falha no sistema, o usuário do dia a dia não consegue apagar nem alterar a estrutura das tabelas.

---

## 3. Enviar os arquivos

No cPanel → **Gerenciador de Arquivos**:

1. Na **pasta inicial da conta** (`/home/conta/`, um nível **acima** do `public_html`), envie o `.zip` e extraia.
2. Mova o **conteúdo** de `public_html/` extraído para o `public_html` do domínio ou subdomínio.
   - Ative "Mostrar arquivos ocultos" para ver o `.htaccess`, que também precisa ir.
3. Deixe a pasta `app/` em `/home/conta/app`, **fora** do `public_html`.

Estrutura final:

```
/home/conta/
├── app/                 ← código e .env (não acessível pela web)
└── public_html/
    ├── index.html
    ├── .htaccess
    ├── assets/ img/ ...
    └── api/
        ├── index.php
        └── .htaccess
```

> Se o seu subdomínio usa outra pasta (ex.: `/home/conta/gestao`), coloque o conteúdo de `public_html/` nela e deixe `app/` em `/home/conta/app`. O `api/index.php` procura a pasta `app` dois níveis acima.

---

## 4. Configurar o `.env`

1. Em `/home/conta/app/`, **copie** `.env.example` para `.env`.
2. Edite o `.env` e preencha:
   - `APP_URL`: o endereço **https** do sistema, sem barra no final (ex.: `https://gestao.irmaosdabola.com.br`);
   - `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`: banco e usuário **app**;
   - `DB_MIGRATE_USERNAME`, `DB_MIGRATE_PASSWORD`: usuário **mig**;
   - `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`: o primeiro administrador. A senha precisa ter no mínimo 10 caracteres, com letras e números.
3. Confira que estão assim: `APP_ENV=production`, `APP_DEBUG=false` e `SESSION_SECURE=true`.
4. Mude a permissão do `.env` para **600**: botão direito → *Change Permissions*.

---

## 5. Criar as tabelas e o administrador (via Cron, sem SSH)

No cPanel → **Cron Jobs**, crie uma tarefa **temporária**:

- Horário: "Uma vez por minuto" (`* * * * *`)
- Comando:
  ```
  /usr/local/bin/php /home/conta/app/database/migrate.php --seed
  ```
  O caminho do PHP varia entre hospedagens. O cPanel costuma mostrá-lo na própria tela de Cron; outras opções comuns são `/usr/bin/php` e `/opt/cpanel/ea-php82/root/usr/bin/php`.

Espere 1 ou 2 minutos. O resultado chega no e-mail configurado no topo da tela de Cron. Depois:

1. **Apague essa tarefa do Cron.**
2. **Apague o valor de `ADMIN_PASSWORD` no `.env`**, deixando `ADMIN_PASSWORD=`.

> Alternativa sem Cron: importe `app/database/schema.sql` pelo **phpMyAdmin** para criar as tabelas. O administrador, porém, precisa do `--seed` pelo Cron, porque a senha é gravada com hash.

---

## 6. Agendar as tarefas automáticas

Em **Cron Jobs**, crie as tarefas **permanentes**:

| Quando | Comando | Para quê |
|---|---|---|
| `0 6 1 * *` (dia 1º, 6h) | `/usr/local/bin/php /home/conta/app/scripts/generate_invoices.php` | Gera as mensalidades do mês. Rodar de novo não duplica nada. |
| `0 3 * * *` (todo dia, 3h) | `/usr/local/bin/php /home/conta/app/scripts/maintenance.php` | Limpa sessões expiradas, tentativas de login antigas e logs com mais de 90 dias. |

> Prefere gerar as mensalidades manualmente pelo botão **"Gerar mensalidades"**? Então não crie a primeira tarefa.

---

## 7. Verificar a instalação

1. Crie no Cron, **uma única vez**, a tarefa:
   ```
   /usr/local/bin/php /home/conta/app/scripts/check_install.php
   ```
   O relatório chega por e-mail. Todos os itens devem estar `[OK]`; avisos (`[AVISO]`) são recomendações. Depois apague a tarefa.
2. Acesse `https://gestao.seudominio.com.br/api/health`. Deve aparecer `{"data":{"status":"ok",...}}`.
3. Acesse o sistema, entre com o administrador e **troque a senha** quando o sistema pedir.
4. Opcional: teste os cabeçalhos de segurança em <https://securityheaders.com>. O resultado esperado é nota **A**.
5. Teste que arquivos sensíveis **não** abrem. Todos devem dar erro 403 ou 404:
   - `https://seu-endereco/.env`
   - `https://seu-endereco/api/.htaccess`
   - `https://seu-endereco/api/composer.json`

---

## 8. Primeiros passos no sistema

1. **Usuários**: cadastre os professores. Cada um recebe uma senha temporária, que você entrega por um canal seguro.
   - Contas de **Atleta** e **Responsável** (portal, somente leitura) são criadas na mesma tela, escolhendo o atleta ou o responsável vinculado.
   - Cada atleta ou responsável tem no máximo uma conta.
2. **Planos** e **Uniformes → Itens e preços**: confira os valores.
3. **Turmas**: crie as turmas por dia e categoria, escolha o professor e adicione os atletas pelas "Sugestões pela idade".
4. **Atletas**: importe a planilha, se ainda não tiver feito. Veja a seção 9.

---

## 9. Importar a planilha de atletas (opcional)

1. Envie o arquivo `.csv` ou `.xlsx` para `/home/conta/app/storage/`.
2. **Simule** via Cron, uma vez; o relatório chega por e-mail:
   ```
   /usr/local/bin/php /home/conta/app/scripts/import_planilha.php /home/conta/app/storage/planilha.xlsx --dry-run
   ```
3. Se estiver tudo certo, **grave**, rodando o mesmo comando sem `--dry-run`. Atletas que já existem são ignorados, então repetir não duplica.
4. **Apague a planilha** da pasta `storage/`, porque ela contém dados pessoais.

---

## 10. Atualizar o sistema (novas versões)

1. Gere um novo pacote com `bash deploy/build.sh`.
2. **Faça backup**: cPanel → *Backup* → baixe o backup do banco.
3. Substitua os arquivos:
   - em `public_html/`, apague a pasta `assets/` antiga e envie a nova, junto com `index.html` e `api/`;
   - em `app/`, substitua tudo, **menos** o `.env` e a pasta `storage/`.
4. Se a nova versão tiver migrations novas, rode `migrate.php --seed` pelo Cron, como no passo 5.
   - Os seeds podem rodar de novo sem duplicar nada e criam dados-padrão novos, como os critérios de avaliação.
   - Com `ADMIN_PASSWORD` vazio, nenhum admin é criado.

---

## 11. Backup

- **Banco (diário):** crie no Cron o comando abaixo. Ele usa o usuário mig, que tem permissão de leitura completa.
  ```
  /usr/bin/mysqldump --single-transaction -u conta_iba_mig -p'SENHA' conta_iba | gzip > /home/conta/backups/iba-$(date +\%F).sql.gz
  ```
  - Crie antes a pasta `/home/conta/backups`, **fora** do `public_html`.
  - Apague backups antigos periodicamente.
- **Arquivos:** só o `.env` precisa de cópia, porque o resto é regenerado pelo build. Guarde uma cópia dele em local seguro.

---

## Problemas comuns

| Sintoma | Causa provável | Solução |
|---|---|---|
| "Aplicação não configurada" em `/api/health` | A pasta `app/` não está dois níveis acima de `public_html/api` | Reveja a estrutura do passo 3 |
| Erro 500 em tudo | Versão do PHP antiga ou extensão faltando | Rode o `check_install.php` (passo 7) |
| Login volta para a tela de login | `SESSION_SECURE=true` sem HTTPS, ou `APP_URL` diferente do endereço acessado | Ative o SSL e confira o `APP_URL` (com `https://` e sem `www` se você acessa sem `www`) |
| "Origem da requisição não permitida" | `APP_URL` diferente do endereço no navegador | Deixe o `APP_URL` idêntico ao endereço usado. Se o site abre com e sem `www`, liste os dois separados por vírgula |
| "Muitas tentativas de login" | Bloqueio contra força bruta | Aguarde o tempo indicado (máximo de 15 minutos) |
| Tela branca após atualizar | O navegador guardou o `index.html` antigo | Recarregue com Ctrl+F5 |
| Erro "Código XXXXX" em alguma tela | Erro interno registrado | Veja `app/storage/logs/app-AAAA-MM-DD.log` e procure o código |
