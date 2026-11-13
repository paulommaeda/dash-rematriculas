# Validação local · versão 4.5.0

Ambiente isolado com dados sintéticos: WordPress 7.1.4, PHP 8.4.24 e MariaDB 11.8.6.

- 72 verificações da API e da integração: autenticação, HTTPS, RA textual e exato, datas e fuso, reenvios, arquivamento, cadastro ausente/duplicado, lotes parciais, ausência de acesso financeiro, coligada, contrato ERP, prioridade do RA oficial, senha cifrada, permissões administrativas, paginação, janela fixa, retomada, reconciliação diária, agendamento e compatibilidade com Fluent Forms.
- 23 verificações HTTP em WordPress nativo: rota e cabeçalhos reais, ausência de cache, reenvio, arquivados, lote parcial, isolamento financeiro, sessões, nonces e gravação do formulário administrativo.
- 9 verificações DOM do fluxo de sincronização: bloqueio de cliques repetidos, continuação dos lotes, progresso, mensagens de erro, resposta inválida da hospedagem, tempo esgotado e nova tentativa.
- 37 verificações de cálculo financeiro: bolsas sobre incondicional, cursos, turmas, contratos e shortcode separado.
- 25 verificações das permissões financeiras: sessões independentes, valores comerciais por aluno e revogação de acesso.
- 21 verificações dos valores centrais: base por curso, condicional único, zeros, permissões e reinício das conferências.
- 25 verificações do atualizador nativo, manifesto e integridade dos pacotes.

Total: **212 verificações funcionais aprovadas**. Sintaxe PHP e JavaScript validada. Schemas e exemplos do OpenAPI conferidos com JSON Schema 2020-12. O HTML administrativo real também foi conferido para garantir formulários independentes de integração, preços e configurações.

Os testes da consulta ao ERP e do atualizador simulam as respostas de rede. Os testes HTTP usam um servidor local; a sinalização de HTTPS é controlada no ambiente de testes, sem simular uma conexão com o ERP da escola. Nenhum acesso de produção foi configurado e nenhum dado real de aluno foi usado. Não houve instalação no WordPress do colégio nem validação visual em navegador gráfico.

O banco mantém a versão 4.0.0: não há alteração de estrutura nesta versão. Após a publicação, o manifesto e o ZIP público são conferidos por versão, conteúdo e SHA-256.

Para repetir os testes PHP do repositório, aponte `DRJ_WP_BOOTSTRAP` para o bootstrap de um WordPress descartável com o plugin ativo, defina `DRJ_TEST_ENV=1` e execute os arquivos de `tests/` com PHP CLI. Os testes de valores centrais e acesso existentes utilizam os alunos sintéticos 1 (6º ano), 2 (Médio) e 3 (Fundamental I); não execute sobre um banco real. A interface usa `DRJ_TEST_ENV=1 node tests/erp-ui.cjs` com `jsdom` disponível.
