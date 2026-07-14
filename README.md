# CRM AlterHub Digital

CRM e operacao comercial em Laravel 12 + Filament 5, com oportunidades, vendas multi-item, estoque reservado/fisico, remessas parciais, devolucoes, producao por BOM, regras fiscais por UF/NCM, logistica e exportacoes XLSX em fila.

## Requisitos

- Docker Desktop com Compose v2; ou PHP 8.4, Composer 2, Node 22, MySQL 8.4 e Redis 7.4.
- Nenhuma credencial padrao e distribuida. Copie `.env.example` para `.env`, gere `APP_KEY` e defina senhas exclusivas.

## Ambiente local com Docker

```bash
cp .env.example .env
php artisan key:generate --show
docker compose config --quiet
docker compose build
docker compose up -d db redis app worker scheduler nginx
docker compose run --rm app php artisan migrate --force
docker compose run --rm app php artisan db:seed --class=Database\\Seeders\\EssentialSeeder --force
```

Informe a chave gerada em `APP_KEY`. O painel local fica em `http://localhost:8080` por padrao. MySQL e Nginx local escutam apenas em loopback; o Compose de producao nao publica MySQL.

Para criar o primeiro administrador, preencha temporariamente `BOOTSTRAP_ADMIN_NAME`, `BOOTSTRAP_ADMIN_EMAIL` e uma senha forte em `BOOTSTRAP_ADMIN_PASSWORD`, execute o seed essencial uma vez e remova os tres valores. Dados de demonstracao sao opt-in e bloqueados em producao:

```bash
docker compose run --rm app php artisan db:seed --class=Database\\Seeders\\DemoSeeder
```

## Qualidade

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test
npm ci
npm audit --audit-level=high
npm run build
composer audit --locked
```

O CI repete PHPUnit em SQLite e MySQL e inclui um teste concorrente com dois processos disputando a ultima unidade disponivel.

## Operacao e entrega

- Auditoria de estoque: `php artisan estoque:auditar`; use `--corrigir` somente depois de revisar e guardar o relatorio.
- Exportacoes grandes usam fila Redis e chunks configuraveis por `CRM_EXPORT_*`.
- O container nao instala dependencias, nao gera chave, nao migra, nao cria cache e nao executa seed no boot.
- Leia [docs/deployment-runbook.md](docs/deployment-runbook.md) antes de uma entrega e [docs/data-migration.md](docs/data-migration.md) antes da primeira migracao desta versao.

Nunca execute `migrate:fresh`, `migrate:rollback` ou restaure um dump no banco de producao. Depois de novas movimentacoes, rollback significa voltar a imagem da aplicacao e corrigir o banco de forma progressiva.
