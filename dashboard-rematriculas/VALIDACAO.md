# Validação local · versão 4.3.0

Ambiente isolado com dados sintéticos: WordPress 7.1.4, PHP 8.3.6 e MariaDB 10.11.14.

- 24 verificações de banco e regras do acompanhamento, incluindo migração, preservação de dados e arquivamento.
- 25 verificações do atualizador nativo e da integridade dos pacotes.
- 37 verificações de cálculo financeiro, percentual sobre o incondicional, cursos, turmas e acesso ao shortcode separado.
- 25 verificações das permissões financeiras: administradores, contas autorizadas, bloqueio de sessão comum, tokens distintos, proteção dos campos e configurações de senha.
- 39 verificações HTTP dos fluxos de matrícula, acesso e consultas.
- 29 verificações HTTP de isolamento financeiro: senha comum recusada, login por conta autorizada, revogação de conta e sessão, logout independente, desativação e troca de senha, bloqueio de consulta e edição sem permissão.
- 5 verificações DOM do acompanhamento sem financeiro: ausência de valores e resumo financeiro, edição de observações e respostas sem dados financeiros.
- 15 verificações DOM do acompanhamento autorizado, incluindo salvamento automático e reenvio.
- 22 verificações DOM financeiras: colunas separadas, percentual incondicional, filtros, contratos, tratamento de erros e consultas fora de ordem.

Total: **221 verificações funcionais aprovadas**. A sintaxe PHP e JavaScript também foi validada.

Os testes do atualizador simulam as respostas de rede. O manifesto e o pacote público são conferidos após a publicação. A interface foi validada funcionalmente em DOM; não houve validação visual em navegador gráfico nem instalação no servidor de produção.
