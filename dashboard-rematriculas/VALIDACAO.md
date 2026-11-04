# Validação local · versão 4.1.0

Ambiente isolado, sem acesso ao banco ou ao servidor do colégio: WordPress 7.1.4, PHP 8.3.6 e MariaDB 10.11.14, com dados sintéticos.

- Sintaxe validada nos 13 arquivos PHP do plugin, no teste PHP do atualizador e no JavaScript.
- 24 verificações de banco e regras do backend: migração de tabela legada, preservação de colunas extras e descontos zero, arquivamento, restauração, histórico, rollback em falha, recusa de tabela sem transações, sincronização e identificação de aluno novo após troca de RA.
- 28 verificações HTTP em WordPress: acesso por senha, token vinculado à sessão, validações, proteção de arquivados, integração da API, consulta de desistências, restauração e invalidação da sessão após sair.
- 15 verificações de JavaScript em DOM: filtros, gráficos, nomes e RAs com caracteres especiais, agrupamento de gravações, cálculo, erro de rede e reenvio, conferências e modal.
- 25 verificações do atualizador: integração com o verificador nativo do WordPress, cache, oferta de versão nova, recusa de downgrade, origem do pacote, conferência SHA-256, recusa de arquivo alterado, recuperação após falha de rede, opção automática e preservação de credenciais existentes.

Total: **92 verificações funcionais aprovadas**.

Os testes do atualizador simularam as respostas de rede e validaram a entrega do ZIP ao instalador nativo. A publicação do manifesto e do pacote deve ser verificada no repositório depois do envio.

A interface recebeu estilos responsivos e ajustes de acessibilidade. A execução de um navegador gráfico não esteve disponível neste ambiente; a validação da interface foi funcional em DOM. Não houve instalação no site de produção. Tema, cache e integrações externas devem ser conferidos depois da instalação na sua hospedagem.
