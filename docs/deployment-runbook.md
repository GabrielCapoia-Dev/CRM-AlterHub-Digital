# Runbook de deploy e recuperacao

## Preparacao

1. Publique as imagens `app` e `nginx` e configure `APP_IMAGE` e `NGINX_IMAGE` com referencias por digest (`repo@sha256:...`).
2. Configure `.env` fora do Git: `APP_KEY`, banco, Redis, URL, `APP_FORCE_HTTPS=true`, cookies seguros e dominio.
3. Valide sem iniciar servicos: `docker compose -f docker-compose.prod.yml config --quiet`.
4. Confirme que MySQL e Redis nao possuem portas publicadas e que somente o proxy/Nginx esta exposto.

## Backup obrigatorio

Crie um diretorio protegido e gere um dump consistente antes da migration:

```bash
mkdir -p backups
docker compose -f docker-compose.prod.yml exec -T db sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers --set-gtid-purged=OFF "$MYSQL_DATABASE"' > "backups/alterhub-$(date +%Y%m%d-%H%M%S).sql"
```

Verifique se o arquivo nao esta vazio, calcule SHA-256 e teste a restauracao em um banco isolado. Nao prossiga sem esse teste.

## Release unico

1. Ative manutencao e drene worker: `docker compose -f docker-compose.prod.yml run --rm app php artisan down --retry=60`.
2. Execute uma unica vez: `docker compose -f docker-compose.prod.yml run --rm app php artisan migrate --force`.
3. Sincronize roles/permissoes: `docker compose -f docker-compose.prod.yml run --rm app php artisan db:seed --class=Database\\Seeders\\EssentialSeeder --force`.
4. Gere caches no volume compartilhado: `docker compose -f docker-compose.prod.yml run --rm app php artisan optimize`.
5. Suba a nova imagem: `docker compose -f docker-compose.prod.yml up -d app worker scheduler nginx`.
6. Reabra: `docker compose -f docker-compose.prod.yml exec app php artisan up`.

Nao execute Composer, npm, seed demonstrativo ou geracao de chave no servidor/boot.

## Smoke e aceite

- `docker compose -f docker-compose.prod.yml ps` deve mostrar healthchecks saudaveis.
- Acesse `/up`, login, CSS/JS, Kanban, lista de produtos e vendas.
- Execute `php artisan estoque:auditar` e arquive o relatorio.
- Confirme worker processando uma exportacao pequena e scheduler ativo.
- Reinicie os containers e confirme ausencia de migration, seed ou movimento duplicado.

## Rollback

Volte `APP_IMAGE`/`NGINX_IMAGE` ao digest anterior e recrie os servicos. Nao reverta migrations nem apague movimentos do razao. Se uma migration ja recebeu dados novos, produza migration corretiva progressiva.

## Restauracao de desastre

Pare app, worker e scheduler. Em um banco vazio e isolado, valide o dump antes da restauracao definitiva:

```bash
docker compose -f docker-compose.prod.yml exec -T db sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < backups/arquivo-validado.sql
```

Suba primeiro app sem trafego, execute auditoria de estoque e somente entao libere Nginx.
