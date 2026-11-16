# Histórico de versões

## 4.6.0

- Página administrativa de logs com origem, ID/protocolo, RA, versão, primeira/última leitura, contadores, resultado e motivo; filtros, paginação e histórico por requerimento.
- Registro das leituras e decisões do ERP, API REST e Fluent Forms, além de falhas de consulta e requisições recusadas, sem guardar credenciais ou corpos de formulários.
- Identidade única por coligada + ID do ERP, compartilhada entre consulta e recebimento REST; versões antigas não são reaplicadas.
- API simples aceita id_requerimento, sistema_origem ou Idempotency-Key; envios antigos continuam protegidos pelo RA.
- Bloqueio de reuso do mesmo requerimento para outro RA, serialização de reenvios simultâneos e reserva persistente para retomadas após falha.
- Respostas incluem referência log_id; releituras aumentam contadores e mantêm o mesmo registro, preservando a primeira confirmação.
- Histórico detalhado com retenção de 90 dias, preservando permanentemente a identidade e o controle de duplicidade.
- Migração 4.6.0 adiciona duas tabelas de integração e mantém alunos, valores, conferências e arquivamentos.

## 4.5.0

- API REST autenticada para receber rematrículas por RA, individualmente ou em lotes, com suporte ao contrato de requerimentos do ERP 0.19.23.
- Confirmação compartilhada com Fluent Forms, preservando a primeira data, valores, conferências, tipo do aluno e arquivamento; RAs ausentes ou duplicados não são associados automaticamente.
- Configuração administrativa para consultar o ERP com conta de leitura e Senha de aplicativo cifrada, coligada autorizada, caminho do RA e início da campanha.
- Sincronização manual e automática em páginas, com janela de atualização fixa, retomada, sobreposição e reconciliação diária.
- Documentação e especificação OpenAPI, sem credenciais reais ou dados pessoais no pacote.

## 4.4.1

- Valores e resumo por aluno novamente disponíveis no dashboard de rematrículas para a validação comercial.
- Acesso ao acompanhamento permite conferir e ajustar os descontos individuais sem liberar o dashboard financeiro consolidado.
- Base por curso e condicional único continuam somente para consulta e protegidos contra sobrescrita individual.
- Dashboard financeiro, consultas agregadas, contas autorizadas e senha exclusiva mantêm o controle de acesso separado, sem aba compartilhada.

## 4.4.0

- Cadastro central de valor base por curso e desconto condicional único para todos os alunos.
- Aplicação das mesmas referências em acompanhamento, financeiro, novos alunos e troca de turma.
- Valor base e condicional somente para consulta no dashboard, com bloqueio de sobrescrita pelo salvamento individual.
- Demais descontos, vencimento e parcelas continuam individuais.
- Reinício das conferências apenas dos alunos afetados por alterações de referência; primeiro cadastro confirma a regra central para todos.
- Valores zero respeitados e validação administrativa do cadastro.

## 4.3.0

- Financeiro separado do acompanhamento, com menu próprio e shortcode exclusivo.
- Remoção da aba compartilhada e do acesso pelo parâmetro da página de acompanhamento.
- Autorização de contas WordPress e opção de senha financeira exclusiva, sem usar a sessão comum.
- Sessões e tokens independentes; revogação por retirada de conta, troca de senha ou desativação do acesso por senha.
- Dados financeiros removidos do HTML, JSON e respostas de cadastro para quem só possui acesso de acompanhamento.
- Gravação de valores protegida por permissão financeira.
- Colunas separadas de desconto incondicional, indicação, irmãos e desconto condicional.
- Percentual de bolsa calculado exclusivamente por incondicional ÷ bruto × 100; indicação e irmãos continuam deduzidos do líquido.

## 4.2.0

- Novo dashboard financeiro com bruto, líquido e bolsas por turma, por curso e no total geral.
- Alternância entre valores por parcela e total dos contratos, respeitando o parcelamento de cada aluno.
- Bolsas incondicionais, de indicação e de irmãos separadas do desconto condicional.
- Filtros por situação da matrícula, curso e turma, gráficos e atualização dos dados sem recarregar a página.
- Acesso pelo submenu Financeiro, pela aba do painel existente e pelo shortcode `[dash_financeiro]`.
- Cálculos em centavos e exclusão das desistências arquivadas.

## 4.1.0

- Atualizações integradas ao painel nativo do WordPress a partir deste repositório.
- Opção de instalação automática, compartilhada com a configuração nativa de plugins.
- Botão para consultar atualizações imediatamente.
- Download de pacotes versionados com verificação SHA-256 e cache das consultas.
- Remoção das credenciais fixas do código público. Configurações existentes na versão 4.0 são preservadas.
- Versão do banco separada da versão do plugin para evitar migrações desnecessárias.

## 4.0.0

- Separação do plugin em módulos, templates, JavaScript e CSS.
- Arquivamento reversível de desistências com motivo e histórico.
- Visões de acompanhamento, arquivados e todos os registros.
- Salvamento automático com fila por aluno e reenvio em caso de falha.
- Validação de acesso e tokens nas ações de edição.
- Preservação do tipo de aluno novo após a troca do RA.
