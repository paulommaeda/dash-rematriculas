# Validação local · versão 4.6.0

Ambiente isolado com dados sintéticos: WordPress 7.1.4, PHP 8.4.24 e MariaDB 11.8.6.

- 46 verificações de logs e duplicidade: migração sem perda de alunos, índice único, histórico, contadores, IDs externos, canais ERP/API compartilhados, versões antigas, vínculo por RA, correção de pendências, reserva após falha, dois processos simultâneos, filtros, paginação, fuso local, permissões e retenção sem liberar reenvios.
- 72 verificações da API e das integrações: autenticação, HTTPS, RA textual/exato, datas, reenvios, arquivados, cadastros ausentes/duplicados, lotes, isolamento financeiro, coligada, contrato ERP, credenciais cifradas, paginação, janela fixa, retomada, agendamento e Fluent Forms.
- 32 verificações HTTP no WordPress nativo: rotas e cabeçalhos reais, ausência de cache, reenvios, lotes, Idempotency-Key, bloqueio de outro RA, sessão administrativa, nonces, gravação das configurações e lista/histórico dos logs com bloqueio de visitantes e usuários comuns.
- 9 verificações DOM da sincronização: cliques repetidos, continuação dos lotes, progresso, mensagens de erro, resposta inválida da hospedagem, tempo esgotado e nova tentativa.
- 37 verificações de cálculo financeiro por turma, curso e geral, com percentual sobre incondicional e financeiro separado.
- 25 verificações das permissões financeiras e revogação de acesso.
- 21 verificações dos valores centrais e reinício das conferências.
- 25 verificações do atualizador nativo, manifesto e integridade dos pacotes.

Total: **267 verificações funcionais aprovadas**. Sintaxe PHP e JavaScript validada; schemas OpenAPI conferidos com JSON Schema 2020-12. O HTML administrativo real foi conferido para garantir formulários independentes e uma tela de log com decisões e histórico.

O teste de concorrência usa dois subprocessos PHP ligados ao mesmo MariaDB e requer a função proc_open. A consulta ao ERP e o atualizador usam respostas de rede simuladas. Os testes HTTP usam um servidor local com a sinalização de HTTPS controlada no ambiente de testes. Nenhum acesso de produção foi configurado e nenhum dado real de aluno foi usado. Não houve instalação no WordPress do colégio nem validação visual em navegador gráfico.

A migração do banco para 4.6.0 adiciona as tabelas drj_integracao_requerimentos e drj_integracao_eventos, respeitando o prefixo do WordPress e preservando alunos, valores, conferências e arquivamentos. O manifesto e o ZIP público são conferidos após publicar, por versão, conteúdo e SHA-256.

Para repetir os testes PHP, aponte DRJ_WP_BOOTSTRAP para o bootstrap de um WordPress descartável com o plugin ativo, defina DRJ_TEST_ENV=1 e execute os arquivos de tests/ com PHP CLI. integration-log-worker.php é chamado automaticamente pelo teste integration-log.php. Os testes de valores centrais e acesso utilizam alunos sintéticos 1 (6º ano), 2 (Médio) e 3 (Fundamental I); não execute sobre um banco real. A interface usa DRJ_TEST_ENV=1 node tests/erp-ui.cjs com jsdom disponível.
