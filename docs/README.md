# docs/

A planilha original de cadastro (PDF/CSV/XLSX) **não é versionada**: ela contém dados pessoais de
atletas menores de idade (nome, data de nascimento e telefone dos responsáveis) e este repositório é público.

Guarde as planilhas somente localmente ou em armazenamento privado. O `.gitignore` bloqueia
`docs/*.pdf`, `docs/*.csv`, `docs/*.xls` e `docs/*.xlsx`.

Formato esperado pelo importador (`backend/scripts/import_planilha.php`), com cabeçalho na primeira linha:

`Nome do atleta` · `Data de nascimento` · `Nome do responsável` · `Telefone de contato do responsável` ·
`Posição que joga o atleta` · `Possui alguma condição de saúde?` · `Escolha o Plano de treinamento` ·
`Pagamento Uniforme` · `Mensalidade <Mês>` · `Recebi de Patrocinio`
