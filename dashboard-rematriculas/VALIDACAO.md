# Validação local · versão 4.2.0

Ambiente isolado, sem acesso ao banco ou ao servidor do colégio: WordPress 7.1.4, PHP 8.3.6 e MariaDB 10.11.14, com dados sintéticos.

- Sintaxe validada nos 15 arquivos PHP do plugin, nos testes PHP de financeiro e atualizador e nos dois arquivos JavaScript.
- 24 verificações de banco e regras do backend: migração de tabela legada, preservação de colunas extras e descontos zero, arquivamento, restauração, histórico, rollback em falha, recusa de tabela sem transações, sincronização e identificação de aluno novo após troca de RA.
- 36 verificações HTTP em WordPress: acesso por senha, token vinculado à sessão, validações, proteção de arquivados, integração da API, consulta de desistências, restauração, invalidação da sessão após sair, acesso ao financeiro, filtros e navegação entre painéis.
- 15 verificações de JavaScript em DOM: filtros, gráficos, nomes e RAs com caracteres especiais, agrupamento de gravações, cálculo, erro de rede e reenvio, conferências e modal.
- 25 verificações do atualizador: integração com o verificador nativo do WordPress, cache, oferta de versão nova, recusa de downgrade, origem do pacote, conferência SHA-256, recusa de arquivo alterado, recuperação após falha de rede, opção automática e preservação de credenciais existentes.

- 33 verificações de cálculo e renderização financeira: bruto, bolsas, condicionais e líquido em centavos; cursos e turmas; confirmação e pendências; arquivados; parcelamentos diferentes; conciliação de totais; valores negativos; turmas sem identificação e acesso por shortcode.
- 19 verificações da interface financeira em DOM: filtros, cartões, tabelas, gráficos, base por parcela e contrato, consulta vazia, erro de rede, recuperação, escape de texto e cancelamento de consulta anterior.

Total: **152 verificações funcionais aprovadas**.

Os testes do atualizador simularam as respostas de rede e validaram a entrega do ZIP ao instalador nativo. A publicação do manifesto e do pacote deve ser verificada no repositório depois do envio.

A interface recebeu estilos responsivos e ajustes de acessibilidade. A execução de um navegador gráfico não esteve disponível neste ambiente; a validação da interface foi funcional em DOM. Não houve instalação no site de produção. Tema, cache e integrações externas devem ser conferidos depois da instalação na sua hospedagem.
