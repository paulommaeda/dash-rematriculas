# Validação local · versão 4.4.0

Ambiente isolado com dados sintéticos: WordPress 7.1.4, PHP 8.3.6 e MariaDB 10.11.14.

- 24 verificações de banco e regras do acompanhamento, incluindo migração, preservação de dados e arquivamento.
- 25 verificações do atualizador nativo e da integridade dos pacotes.
- 37 verificações de cálculo financeiro, percentual sobre o incondicional, cursos, turmas e acesso ao shortcode separado.
- 25 verificações das permissões financeiras: administradores, contas autorizadas, bloqueio de sessão comum, tokens distintos, proteção dos campos e configurações de senha.
- 39 verificações HTTP dos fluxos de matrícula, acesso e consultas.
- 29 verificações HTTP de isolamento financeiro: senha comum recusada, login por conta autorizada, revogação de conta e sessão, logout independente, desativação e troca de senha, bloqueio de consulta e edição sem permissão.
- 5 verificações DOM do acompanhamento sem financeiro: ausência de valores e resumo financeiro, edição de observações e respostas sem dados financeiros.
- 18 verificações DOM do acompanhamento autorizado, incluindo salvamento, reenvio, campos centrais somente para consulta e restauração dos valores oficiais após manipulação local.
- 22 verificações DOM financeiras: colunas separadas, percentual incondicional, filtros, contratos, tratamento de erros e consultas fora de ordem.

- 21 verificações dos valores centrais: referência por curso, condicional único, igualdade entre alunos, troca de turma, valores zero, validação, permissão administrativa e reinício de conferências por curso ou geral.
- 18 verificações HTTP do cadastro central real: formulário, nonce, permissões, gravação, aplicação em alunos existentes e novos, recusa de sobrescrita individual, troca de curso e condicional zero.

Total: **263 verificações funcionais aprovadas**. A sintaxe PHP e JavaScript também foi validada.

Os testes do atualizador simulam as respostas de rede. O manifesto e o pacote público são conferidos após a publicação. A interface foi validada funcionalmente em DOM; não houve validação visual em navegador gráfico nem instalação no servidor de produção.
