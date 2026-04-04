# Design System do Projeto

## 1. Objetivo

Este documento define o design system do projeto com base na analise da pasta `deploy` e adaptado para a stack real do repositorio:

- Laravel 12+
- Filament v5
- Livewire v4
- Tailwind CSS v4

O objetivo nao e apenas documentar estetica. O objetivo e padronizar como o produto deve ser construido no Filament, mantendo consistencia visual, clareza operacional e alta reutilizacao.

## 2. Fontes analisadas

As referencias reais usadas para este documento foram:

- `C:\Users\gabri\Documents\2026\deploy\deploy\assets\design_system_gestao.html`
- `C:\Users\gabri\Documents\2026\deploy\deploy\assets\design_system_unibiotech.html`
- `C:\Users\gabri\Documents\2026\deploy\deploy\assets\dashboard.html`
- `C:\Users\gabri\Documents\2026\deploy\deploy\assets\login.html`
- `C:\Users\gabri\Documents\2026\deploy\deploy\assets\crm.html`
- `C:\Users\gabri\Documents\2026\deploy\deploy\assets\clientes-lista.html`
- `C:\Users\gabri\Documents\2026\deploy\deploy\assets\fornecedores-lista.html`

## 3. Decisao de direcao visual

O projeto tem duas camadas visuais complementares:

1. `design_system_gestao.html` e a base principal do produto.
2. `design_system_unibiotech.html` e a camada de marca.

Na pratica:

- O sistema de gestao define shell, tabelas, formularios, CRUD, dashboard, CRM e componentes operacionais.
- A marca Unibiotech define personalidade, paleta institucional, wordmark e atmosfera visual.
- O produto final deve parecer um sistema administrativo premium e tecnico, nao um site institucional.

## 4. Posicionamento visual

O sistema deve comunicar:

- confianca
- precisao
- controle operacional
- contexto cientifico e corporativo
- sobriedade com refinamento

Evitar:

- interfaces genericas de SaaS
- excesso de cor em areas de trabalho
- cards decorativos demais em telas transacionais
- gradientes e animacoes em excesso fora de login, hero ou marketing interno

## 5. Principios de implementacao

Seguindo os principios da skill `filamentphp-senior-frontend`:

- Filament first: usar componentes nativos do Filament antes de qualquer workaround.
- Semantica primeiro: cor, espacamento e componentes devem comunicar estado e hierarquia, nao decoracao.
- Reutilizacao obrigatoria: se um padrao aparece mais de duas vezes, extrair para schema, componente Blade, helper ou classe dedicada.
- UX operacional: telas de trabalho devem priorizar leitura rapida, filtros claros, feedback imediato e baixo atrito.
- Zero ruido: customizacoes visuais devem apoiar a tarefa, nao competir com ela.

## 6. Arquitetura visual do produto

### 6.1 Shell da aplicacao

Padrao oficial:

- sidebar escura fixa
- header claro e sticky
- conteudo principal em superficie clara
- plano de fundo com neutros suaves e glow muito discreto

Especificacao:

- Sidebar expandida: `260px`
- Sidebar recolhida: `72px`
- Header: `64px`
- Conteudo: fundo neutro claro com variacao sutil entre `neutral-50` e `surface-secondary`

Uso em Filament:

- aplicar o tema no `Panel`
- manter sidebar escura
- manter topbar clara
- evitar backgrounds chapados com contraste agressivo no conteudo principal

### 6.2 Padrao de pagina

Toda pagina deve seguir esta ordem:

1. breadcrumb
2. titulo principal
3. subtitulo curto com contexto
4. acoes primarias e secundarias
5. filtros ou widgets
6. superficie principal de trabalho

### 6.3 Densidade visual

O produto e administrativo. Portanto:

- usar espacamento respiravel, mas sem desperdicio vertical
- priorizar blocos entre 12px e 24px de espacamento interno
- usar cards e containers para agrupar contexto, nao para criar excesso de moldura

## 7. Tokens oficiais

## 7.1 Cores principais

| Papel | Token sugerido | Hex | Uso |
|---|---|---|---|
| Primary 950 | `--color-primary-950` | `#0A1A4A` | sidebar, hero login, areas de alta densidade |
| Primary 900 | `--color-primary-900` | `#0F2261` | gradientes escuros, profundidade |
| Primary 800 | `--color-primary-800` | `#17368D` | botao principal, links, foco, destaque de navegacao |
| Primary 600 | `--color-primary-600` | `#2A58C0` | hover, estados intermediarios |
| Secondary 300 | `--color-info-300` | `#9CC7E7` | highlight suave, chips, foco secundario |
| Secondary 400 | `--color-info-400` | `#93C0E7` | apoio visual e acento frio |
| Accent 500 | `--color-accent-500` | `#00C9A7` | acento de produto, CTA secundaria de alto destaque |

## 7.2 Neutros

| Papel | Hex | Uso |
|---|---|---|
| Neutral 900 | `#0F1729` | texto forte em fundo claro |
| Neutral 800 | `#1A2340` | headings secundarios |
| Neutral 700 | `#2D3756` | texto principal |
| Neutral 600 | `#47516E` | texto de apoio |
| Neutral 500 | `#6B7694` | legenda, ajuda, hint |
| Neutral 300 | `#B4BACE` | borda suave |
| Neutral 200 | `#D4D8E6` | borda padrao |
| Neutral 100 | `#E8EBF2` | fundo de linha, hover muito suave |
| Neutral 50 | `#F4F5F9` | fundo base do app |
| Neutral 0 | `#FEFEFE` | superficie principal |

## 7.3 Cores semanticas

| Papel | Hex | Regra |
|---|---|---|
| Success 500 | `#00C97B` | sucesso, confirmacao, status positivos |
| Warning 500 | `#F5A623` | atencao, alerta, estado pendente |
| Error 500 | `#E53E6B` | erro, risco, perda, exclusao |
| Info 500 | `#3A6DD6` | informacao, funil, estado neutro ativo |

Regra importante:

- `accent` nao substitui `success`
- `secondary sky` nao substitui `primary`
- cor semantica sempre vence cor decorativa

## 7.4 Tipografia

Familias:

- Sans principal: `Plus Jakarta Sans`
- Mono: `JetBrains Mono`

Escala recomendada:

- Display: 48px a 56px
- H1: 32px a 40px
- H2: 24px a 32px
- H3: 20px a 24px
- Body: 16px
- Body small: 14px
- Caption: 12px
- Overline: 11px uppercase

Regra:

- titulos sempre com peso 700 ou 800
- labels e captions com peso 600 quando precisarem de contraste
- texto corrido sempre em 14px ou 16px

## 7.5 Espacamento

Base oficial: grid de 4px

Escala padrao:

- 4
- 8
- 12
- 16
- 20
- 24
- 32
- 40
- 48
- 64

## 7.6 Raios e sombras

Raios:

- `sm`: 4px
- `md`: 8px
- `lg`: 12px
- `xl`: 16px
- `2xl`: 24px
- `full`: 9999px

Sombras:

- `shadow-sm`: inputs, containers leves
- `shadow-md`: cards com hover
- `shadow-lg`: widgets em destaque
- `shadow-xl`: modais, drawers, overlays

## 8. Componentes oficiais

### 8.1 Botoes

Tipos oficiais:

- `primary`: navy forte para acao principal
- `ghost`: fundo claro, borda suave, acao secundaria
- `sky`: CTA secundaria importante
- `danger`: acao destrutiva

Regras:

- uma unica acao primaria por area principal
- acoes destrutivas sempre com confirmacao
- hover deve alterar sombra ou borda, nao apenas cor

Em Filament:

- `color('primary')` para acao principal
- `color('gray')` ou padrao neutro para secundarias
- `color('danger')` com `requiresConfirmation()` para destrutivas

### 8.2 Badges e tags

Tipos:

- ativo / ok
- pendente
- informativo
- erro
- estagios do CRM

Regra:

- badge comunica estado
- chip comunica categoria, relacao ou entidade
- nao usar badge para texto decorativo

### 8.3 Cards

Cards sao permitidos em:

- KPIs
- quick actions
- modulos do dashboard
- agrupamento de conteudo resumido

Cards nao devem ser o container universal da aplicacao.

### 8.4 Tabelas

Tabela e componente central do sistema.

Padrao:

- header leve
- linhas sobre superficie branca
- hover suave em `primary-50`
- toolbar acima da tabela
- paginacao visivel
- acoes no fim da linha

Em Filament:

- usar `Table` como padrao default para CRUD
- definir colunas pesquisaveis e ordenaveis com intencao
- usar filtros em toolbar, nao escondidos sem necessidade

### 8.5 Formularios

Padrao:

- labels descritivas
- helper text quando houver ambiguidade
- placeholders reais
- campos agrupados por secao semantica
- formularios pequenos em modal ou slideover
- formularios longos em pagina dedicada

Em Filament:

- usar `Section::make()` e `columns(2)`
- evitar formularios gigantes em uma unica coluna
- usar `slideOver()` para edicao contextual
- usar `CreateAction` e `EditAction` nativos quando possivel

### 8.6 Modais e drawers

Regra de uso:

- modal para confirmacao e formulacao curta
- drawer/slideover para edicao contextual e inspeccao rapida
- pagina dedicada para processos longos, com multiplas secoes ou dependencia de navegacao

### 8.7 Widgets

Widgets oficiais:

- KPI cards
- graficos simples
- atividade recente
- quick actions

Em Filament:

- `StatsOverviewWidget` para KPIs
- `ChartWidget` para distribuicao e tendencia
- `TableWidget` para atividade recente

### 8.8 CRM Kanban

O CRM usa um padrao proprio, mas ainda dentro do sistema:

- colunas com cor por etapa
- cards leves com valor, responsavel, tags e data
- filtro visivel acima do board
- edicao em slideover
- badge de status sincronizado com etapa

Em Filament:

- implementar como `Page` customizada
- manter actions, filtros e slideovers integrados ao painel
- nao transformar o CRM em uma colecao de widgets soltos

## 9. Padroes por superficie

### 9.1 Dashboard

Deve conter:

- saudacao e contexto
- KPIs no topo
- graficos de distribuicao e volume
- atalhos rapidos
- atividade recente
- modulos principais do sistema

### 9.2 CRUDs operacionais

Padrao oficial:

- tabela como primeira superficie
- busca + filtros + CTA de criar
- editar via modal ou slideover
- view page opcional para entidades com muito detalhe

### 9.3 Login

O login e a superficie mais expressiva visualmente.

Pode usar:

- gradiente institucional
- glow controlado
- palavra de marca
- microanimacoes leves

Nao pode:

- sacrificar contraste
- usar campos sem estado de erro claro
- usar CTA ambigua

## 10. Acessibilidade

Obrigatorio:

- contraste suficiente entre texto e fundo
- labels sempre presentes
- estados de foco visiveis
- confirmacao em acoes destrutivas
- feedback claro em sucesso e erro
- textos de ajuda em campos complexos

No Filament:

- usar notifications nativas
- usar `helperText()`
- usar `placeholder()` com exemplos reais
- respeitar policies ao inves de esconder interface de forma ad hoc

## 11. Motion

Motion permitido:

- fade in leve
- hover com pequena elevacao
- ripple sutil em botoes
- transicoes curtas em modais e drawers

Motion proibido em telas transacionais:

- animacao constante chamando mais atencao que o dado
- gradiente animado em areas de tabela ou formulario
- microinteracoes longas

Padrao:

- `100ms` rapido
- `200ms` normal
- `300ms` lento

## 12. Regras de implementacao no Filament

### 12.1 Resource

- CRUD de clientes, fornecedores, produtos e insumos deve nascer como `Resource`
- manter `form()` e `table()` como fonte primaria da interface
- logica de negocio fora de closures de UI

### 12.2 Form

- agrupar por contexto: identificacao, contato, endereco, regras comerciais, observacoes
- usar colunas duplas por default quando a leitura permitir
- usar pagina dedicada quando o formulario for realmente longo

### 12.3 Table

- densidade media
- hover suave
- chips de status sem ruido
- acoes por linha objetivas
- bulk action apenas quando houver caso real

### 12.4 Actions

- criar, editar, aprovar, excluir e sincronizar devem ser actions nativas
- usar `requiresConfirmation()` em exclusoes, suspensoes e mudancas irreversiveis

### 12.5 Theme

Recomendacao de mapeamento para Tailwind v4:

```css
@import "tailwindcss";

@theme {
  --color-primary-950: #0A1A4A;
  --color-primary-900: #0F2261;
  --color-primary-800: #17368D;
  --color-primary-600: #2A58C0;
  --color-info-400: #93C0E7;
  --color-info-300: #9CC7E7;
  --color-success-500: #00C97B;
  --color-warning-500: #F5A623;
  --color-danger-500: #E53E6B;
  --color-gray-900: #0F1729;
  --color-gray-700: #2D3756;
  --color-gray-500: #6B7694;
  --color-gray-200: #D4D8E6;
  --color-gray-50: #F4F5F9;
  --font-sans: "Plus Jakarta Sans", system-ui, sans-serif;
  --font-mono: "JetBrains Mono", monospace;
  --radius-xl: 1rem;
}
```

Regra:

- tokens do produto devem viver no tema
- nao espalhar hex hardcoded por resource, page ou widget

## 13. O que evitar

- usar verde accent como cor padrao de sucesso em tudo
- usar azul sky como CTA principal
- montar CRUD com pagina custom quando `Resource` resolve
- exagerar glassmorphism fora de login ou showcase
- usar card dentro de card dentro de card
- criar componentes visualmente unicos para cada modulo
- usar JS custom quando Filament, Livewire ou Alpine resolvem

## 14. Definicao final

Este projeto deve seguir o estilo:

- shell escuro e institucional
- superficies claras e operacionais
- tipografia precisa e contemporanea
- uso forte de tabela, formulario, widget e status
- visual premium, mas pragmatico
- marca presente, sem invadir a produtividade

Em resumo:

- `design_system_gestao` e a base do produto
- `design_system_unibiotech` e a camada de identidade
- Filament v5 e o meio oficial de implementacao

