# Dashboard de Rematrículas Journey · 4.3.0

## Instalação e atualização

1. Faça backup do banco e da pasta da versão atual.
2. Desative o plugin antigo. Se o WordPress oferecer a substituição ao enviar este ZIP, confirme a substituição. Se ele estiver instalado com outra pasta ou diretamente em `plugins`, mantenha-o desativado ao ativar a versão nova.
3. Em **Plugins → Adicionar plugin → Enviar plugin**, envie o arquivo ZIP completo e ative **Dashboard de Rematrículas Journey**.
4. Continue usando o shortcode `[dash_rematriculas]` na mesma página. O menu **Dash Rematrículas** continua disponível.

Instale a pasta completa: o arquivo PHP principal agora carrega os módulos que estão em `includes`. A atualização usa a mesma tabela `{prefixo}lista_alunos_v4` e as opções de webhook existentes. Não há exclusão de registros na atualização, desativação ou remoção deste pacote.

Requisitos: WordPress 6.0+, PHP 7.4+ com as extensões usuais do WordPress e `mbstring`, MySQL/MariaDB. A tabela deve ter as colunas originais `id`, `ra`, `nome_aluno` e `turma`. Em uma instalação nova, ela é criada automaticamente. O usuário do banco precisa poder criar tabelas, adicionar colunas e índices. O arquivamento com histórico exige InnoDB; tabelas legadas com outro mecanismo precisam de conversão pelo administrador. O plugin verifica esse requisito antes de alterar o arquivamento e não converte o mecanismo automaticamente.

## Ocultar uma desistência

- Na linha do aluno, clique em **Arquivar**, ou clique no nome para abrir os detalhes.
- Informe o motivo da desistência e clique em **Confirmar desistência e arquivar**.
- O registro sai da aba padrão **Em acompanhamento**, dos totais ativos, da conversão e dos gráficos. Ele deixa de reservar vaga no gráfico por turma.
- Consulte-o em **Desistências arquivadas** ou **Todos os registros**. Na aba de arquivados, os indicadores ativos ficam zerados; o total de registros exibidos aparece acima da tabela.
- Use **Restaurar** para devolver o aluno ao acompanhamento. O status anterior, os valores, as observações e as conferências são mantidos.

O arquivamento e a restauração ficam registrados em uma tabela de histórico separada, com data, motivo e autor. As últimas 50 movimentações aparecem nos detalhes. Restaurações não apagam o histórico anterior. No acesso pela senha compartilhada, o autor é registrado como **Acesso pela senha do painel**; o acesso pela conta WordPress registra o usuário.

Campos financeiros e cadastro ficam bloqueados enquanto o aluno estiver arquivado. É necessário restaurá-lo para editar. Antes de arquivar, salve eventuais alterações no cadastro. Uma nova sincronização ou um reenvio da API não desarquiva o aluno.

## Funcionalidades preservadas

- Recepção de novos alunos pela ação AJAX `drj_receber_aluno_novo`, com os campos `secret`, `ra`, `nome` e `turma` em `wp-admin/admin-ajax.php`.
- Conferências de Comercial, Secretaria, Financeiro e TI e seus webhooks personalizáveis para Teams/Power Automate.
- Valores base de 2027: R$ 1.350,00 no Fundamental I, R$ 1.290,00 no Fundamental II e R$ 1.520,00 no Médio. A referência de capacidade continua em 30 alunos por turma.
- Descontos, vencimento, parcelas, observações, destaque de atenção e edição de RA/turma.
- Alterações financeiras reiniciam as quatro conferências, conforme a regra da versão anterior.

## Estabilidade e eficiência

- PHP, templates, JavaScript e CSS separados. O arquivo principal só inicia o plugin.
- Migração por versão, com verificação de falhas: sem consultas à estrutura a cada visita após a migração concluída e sem substituir descontos zero já cadastrados.
- A visão padrão consulta somente alunos ativos; arquivados têm índice próprio e são carregados ao escolher as outras abas.
- Salvamento com pequeno atraso para agrupar a digitação, fila por aluno, retorno visual, tratamento de erro e tentativa manual de reenvio. A fila organiza as operações feitas na mesma página; ela não implementa bloqueio de edição entre usuários diferentes.
- Filtros atualizam indicadores e gráficos juntos. Busca ignora acentos e funciona com nomes ou RAs contendo caracteres especiais.
- Gráficos renderizados localmente, sem depender do carregamento de uma biblioteca em CDN. Turmas acima da capacidade continuam visíveis.
- Aluno novo continua classificado como novo após substituir o RA provisório pelo oficial. Registros que já perderam o prefixo antes desta atualização não podem ter sua origem deduzida automaticamente.

## Acesso e configurações

Credenciais já configuradas na versão 4.0 são preservadas e armazenadas como hashes. Instalações novas e atualizações diretas da 3.9 devem definir a senha e a chave da API nas configurações do WordPress. O código público não inclui credenciais padrão. Por segurança, as sessões antigas precisam acessar novamente. O cookie de acesso passa a ser assinado, com validade de 24 horas, `HttpOnly` e `SameSite=Lax`. Todas as ações de edição exigem acesso válido e token da sessão. Administradores também podem acessar pela conta WordPress.

Em **Dash Rematrículas → Configurações** é possível trocar a senha do painel e a chave da API. Deixar vazio mantém o valor atual. A troca da senha invalida as sessões do painel. Se trocar a chave da API, atualize também a integração no site de requerimentos. É possível definir as constantes `DRJ_DASHBOARD_PASSWORD` e `DRJ_API_SECRET` no `wp-config.php`; quando definidas, elas têm prioridade sobre as configurações.

A sincronização do Fluent Forms roda em lotes de até 200 envios por requisição e preserva a primeira confirmação já registrada. É possível informar o ID do formulário e o nome interno do campo do RA nas configurações. ID 0 e campo vazio mantêm o comportamento de busca compatível com a versão anterior. Para evitar coincidências entre valores de outros campos e RAs, preencha essas configurações quando souber os nomes do formulário.

As páginas que contêm dados do painel emitem instruções para não usar cache. Exclua também a página do shortcode de caches externos/CDN que desconsiderem essas instruções.

## Organização do código

| Local | Responsabilidade |
| --- | --- |
| `dashboard-rematriculas.php` | Inicialização, hooks e shortcode |
| `includes/class-drj-database.php` | Estrutura e migrações do banco |
| `includes/class-drj-auth.php` | Acesso ao acompanhamento |
| `includes/class-drj-finance-auth.php` | Permissões, senha e sessão exclusivas do financeiro |
| `includes/class-drj-finance.php` | Cálculo e renderização financeira |
| `includes/class-drj-students.php` | Alunos, valores, arquivamento e histórico |
| `includes/class-drj-ajax.php` | Recepção e validação das ações |
| `includes/class-drj-integrations.php` | Fluent Forms e webhooks |
| `includes/class-drj-dashboard.php` | Consulta e renderização do painel |
| `includes/class-drj-admin.php` | Menu e configurações |
| `templates/` | HTML do painel, linhas, login e configurações |
| `assets/js/` e `assets/css/` | Comportamento e aparência |

O pacote não acessa nem altera seu servidor durante a preparação. A migração acontece apenas quando a nova versão é ativada no WordPress.

## Atualizações pelo WordPress

A versão 4.1.0 integra o repositório `paulommaeda/dash-rematriculas` ao atualizador nativo. Após instalar esta versão, use **Plugins** ou **Painel → Atualizações**. Para instalação automática, marque a opção correspondente nas configurações e salve; a configuração é compartilhada com o botão nativo da lista de plugins. **Verificar atualizações agora** consulta a versão sem esperar o cache de seis horas.

O atualizador aceita somente o pacote versionado do repositório e confere sua integridade SHA-256 antes da instalação. A preferência automática não é ativada sem escolha do administrador e respeita as políticas do WordPress e da hospedagem. Os módulos do atualizador ficam em `includes/class-drj-updater.php`.

## Financeiro separado

O painel de acompanhamento continua em `[dash_rematriculas]`. O financeiro usa exclusivamente `[dash_financeiro]` em outra página ou o menu **Financeiro Journey** no WordPress. A aba compartilhada e o atalho pelo parâmetro do acompanhamento foram removidos.

Administradores têm acesso financeiro por padrão. Autorize outras contas em **Dash Rematrículas → Configurações → Acesso exclusivo ao financeiro**. A senha exclusiva é opcional e vem desativada; para usá-la, habilite a opção e defina uma senha diferente da senha de acompanhamento. A constante `DRJ_FINANCE_PASSWORD` permite configurar essa senha pelo `wp-config.php`.

A sessão comum não abre o financeiro nem autoriza consultas ou alterações de valores. Usuários sem acesso financeiro não recebem campos de valores no HTML, no JSON do acompanhamento ou nas respostas de edição de aluno. O acompanhamento de matrícula, conferências, anotações e arquivamento continua funcionando. Usuários autorizados mantêm a edição individual de valores no acompanhamento.

A sessão financeira por senha dura oito horas, usa cookie e token próprios e pode ser encerrada separadamente. Remover uma conta autorizada bloqueia suas próximas consultas. Trocar a senha ou desativar o acesso por senha revoga as sessões financeiras em andamento. Reativar a opção exige nova autenticação.

## Valores financeiros

As tabelas por turma e curso e os totais gerais apresentam bruto, **desconto incondicional**, indicação, irmãos, desconto condicional e líquido, cada um em sua coluna.

- Bolsas: somente descontos incondicionais.
- % de bolsa: desconto incondicional ÷ valor bruto × 100, calculado sobre os totais do grupo filtrado.
- Líquido: bruto − incondicional − indicação − irmãos − condicional.
- Por parcela: soma de uma parcela de cada aluno selecionado.
- Total dos contratos: cada valor multiplicado pelo número de parcelas do respectivo aluno.

Indicação, irmãos e desconto condicional reduzem o líquido e não entram no percentual de bolsa. Os cursos são identificados pelas turmas: Fundamental I (1º ao 5º ano), Fundamental II (6º ao 9º ano) e Médio (séries). Turmas sem identificação ficam em grupos separados.

A visão inicial inclui rematriculados e alunos novos. Os filtros permitem considerar todos em acompanhamento ou somente pendentes, além de limitar curso e turma. Arquivados não entram no cálculo. Os valores são projeções baseadas nos dados de 2027 já cadastrados, mantendo as referências de preço por turma quando ainda não foi informado um valor base. No total dos contratos, o desconto cadastrado é aplicado a todas as parcelas; não há cronograma por vencimento na base atual.

O botão **Atualizar dados** consulta os últimos valores salvos. A tela financeira é somente de consulta. Os cálculos em centavos conciliam os totais por turma, curso e geral. Descontos superiores ao bruto são sinalizados e o líquido negativo é mantido para revisão.
