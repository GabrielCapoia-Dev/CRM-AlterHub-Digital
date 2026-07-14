# Migracao de dados e reconciliacao

As migrations desta entrega sao aditivas e preservam tabelas/linhas existentes. A migration central executa preflight antes do DDL e bloqueia baixa parcial ambigua ou movimento compartilhado por mais de uma linha.

## Classificacao inicial

- Produto com BOM existente: `fabricado`.
- Produto sem BOM: `revenda`.
- Origem inicial: `nacional`, sujeita a revisao administrativa.
- Quantidades da BOM permanecem na unidade-base do insumo.

## Vendas legadas

- Com movimentacao completa: migram como despachadas e ganham remessa legada, sem nova baixa.
- Sem movimentacao: recebem reserva quando houver saldo disponivel.
- Sem saldo ou inconsistentes: ficam em rascunho com historico de bloqueio para revisao.
- Baixa parcial interrompe a migration antes de alterar o esquema.

## Procedimento

1. Gere/teste o backup conforme o runbook.
2. Em copia recente do banco, rode `php artisan migrate --force` e guarde a saida.
3. Rode `php artisan estoque:auditar`; investigue cada divergencia.
4. Revise classificacao/origem de produtos e cadastre regras fiscais vigentes para todas as UFs atendidas.
5. Cadastre transportadoras e vinculos preferenciais de clientes.
6. Somente apos aprovacao do relatorio repita a mesma release em producao.

`estoque:auditar --corrigir` atualiza apenas contadores indexados a partir do razao/reservas; nao edita nem exclui movimentos. Guarde justificativa, usuario responsavel e relatorios antes/depois.
