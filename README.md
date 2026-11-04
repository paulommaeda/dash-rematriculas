# Dashboard de Rematrículas Journey

Plugin WordPress do Colégio Journey, desenvolvido por Paulo Maeda. Painel de acompanhamento de rematrículas, dados financeiros, conferências por setor, integração com Fluent Forms e arquivamento reversível de desistências.

**Versão: 4.4.0** · [Baixar ZIP para instalar](https://raw.githubusercontent.com/paulommaeda/dash-rematriculas/main/dist/dashboard-rematriculas-4.4.0.zip)

## Instalação

1. Faça backup do banco e do plugin atual.
2. Em **Plugins → Adicionar plugin → Enviar plugin**, envie o ZIP de `dist`. Se a versão anterior estiver em outra pasta, desative-a antes de ativar esta.
3. Use o shortcode `[dash_rematriculas]` na página do painel, ou abra **Dash Rematrículas** no WordPress.

O ZIP contém a pasta `dashboard-rematriculas`, preservando o caminho do plugin 4.0. O ZIP automático de código-fonte gerado pelo GitHub não é o pacote de instalação.

Quem já instalou a versão 4.0 mantém os dados e credenciais. Em uma instalação nova ou atualização direta da versão 3.9, configure a senha do painel e a chave da API em **Dash Rematrículas → Configurações**. A versão pública não contém senhas ou chaves fixas. Também é possível definir `DRJ_DASHBOARD_PASSWORD` e `DRJ_API_SECRET` no `wp-config.php`.

## Financeiro separado e restrito

O acompanhamento usa `[dash_rematriculas]`. O financeiro tem uma página independente com `[dash_financeiro]` e o menu próprio **Financeiro Journey** no WordPress. A aba compartilhada e o acesso pelo parâmetro da página de acompanhamento foram removidos.

Em **Dash Rematrículas → Configurações → Acesso exclusivo ao financeiro**:

- Administradores já têm acesso financeiro.
- Selecione as outras contas WordPress que podem consultar o financeiro.
- Se desejar acesso sem conta WordPress, habilite a senha exclusiva e defina uma senha diferente da senha de acompanhamento. Essa opção começa desativada.

A sessão e o token do acompanhamento não autorizam consultas financeiras. O acesso comum também não recebe os campos financeiros no HTML, no JSON da lista nem nas respostas de edição de aluno. A gravação de valores exige permissão financeira. Notas, conferências e arquivamento continuam disponíveis no acompanhamento. Contas com acesso financeiro mantêm os campos de edição individual ao acessar o acompanhamento.

Remover uma conta autorizada revoga seu acesso nas próximas consultas. Trocar a senha financeira invalida suas sessões anteriores. Desativar o acesso por senha também revoga essas sessões, inclusive se a opção for reativada depois. A sessão financeira usa cookie próprio com validade de oito horas e pode ser encerrada sem sair do acompanhamento. Também é possível definir `DRJ_FINANCE_PASSWORD` no `wp-config.php` para administrar a senha fora do painel.

## Valor base por curso e condicional único

Em **Dash Rematrículas → Configurações → Valor base por curso e desconto condicional**, cadastre os valores de referência e clique em **Salvar e aplicar a todos**.

- O valor base é único por curso e é usado por todos os alunos daquele curso, incluindo novos cadastros.
- O desconto condicional é um valor único para todos os cursos e alunos.
- Mudar a turma faz o aluno assumir o valor base do curso de destino.
- Os dois campos aparecem somente para consulta no dashboard. O salvamento individual ignora tentativas de sobrescrevê-los, mesmo por requisição direta.
- Incondicional, indicação, irmãos, vencimento e parcelas continuam individuais e sujeitos à permissão financeira.

Os dois painéis usam a mesma configuração central, inclusive nos totais por contrato. Valores individuais antigos desses dois campos não criam exceções à regra central. Os valores iniciais seguem as referências originais: R$ 1.350,00 para Fundamental I, R$ 1.290,00 para Fundamental II e R$ 1.520,00 para Médio, com condicional de R$ 103,00. Revise e salve o cadastro com os valores da instituição. Zero é aceito, inclusive para o condicional.

O primeiro cadastro reinicia as quatro conferências. Depois disso, alterar a base reinicia as conferências apenas dos alunos do curso atingido; alterar o condicional reinicia as de todos. Salvar sem mudanças preserva as conferências. Somente administradores podem alterar os valores gerais. A separação de acesso financeiro permanece em vigor.

## Valores e percentual de bolsa

Os totais por curso, turma e geral apresentam bruto, **desconto incondicional**, indicação, irmãos, desconto condicional e líquido em colunas separadas.

| Indicador | Cálculo |
| --- | --- |
| Bolsas | Somente descontos incondicionais |
| % de bolsa | Desconto incondicional ÷ bruto × 100 |
| Líquido | Bruto − incondicional − indicação − irmãos − condicional |
| Por parcela | Soma de uma parcela de cada aluno do filtro |
| Total dos contratos | Cada valor multiplicado pelas parcelas do respectivo aluno |

Indicação, irmãos e desconto condicional são deduzidos do líquido e ficam fora do percentual de bolsa. A visão inicial considera rematriculados e alunos novos; os filtros permitem incluir todos em acompanhamento ou somente pendentes e selecionar curso e turma. Arquivados ficam fora dos cálculos.

Os cursos são identificados pelas turmas: Fundamental I, Fundamental II e Ensino Médio, com grupos separados para turmas não reconhecidas. Os números são projeções que usam a base central por curso, o condicional geral e os demais descontos cadastrados por aluno. No total por contrato, os mesmos valores e descontos são aplicados às parcelas informadas; a base atual não contém cronograma de descontos diferentes por vencimento. O botão **Atualizar dados** consulta os valores mais recentes.

## Atualizações pelo painel

Depois de instalar a versão 4.1.0 uma vez, novas versões publicadas aqui serão reconhecidas pelo atualizador do WordPress.

- **Atualizar manualmente:** use **Plugins → Plugins instalados** ou **Painel → Atualizações**.
- **Instalar automaticamente:** ative **Instalar automaticamente novas versões deste plugin** em **Dash Rematrículas → Configurações** e salve. A opção nativa **Ativar atualizações automáticas** na lista de plugins controla a mesma configuração.
- **Consultar imediatamente:** clique em **Verificar atualizações agora** nas configurações.

O plugin consulta `update.json` por HTTPS, com cache de seis horas, e valida o SHA-256 do ZIP antes de entregá-lo ao instalador nativo. Falhas de rede ou de integridade não substituem os arquivos instalados. As consultas enviam somente a solicitação pública de versão, sem nomes, RAs ou dados financeiros. Atualizações automáticas dependem do agendamento e das permissões normais da hospedagem.

## Publicar uma versão nova

1. Altere o cabeçalho `Version` e a constante `DRJ_VERSION` no arquivo principal. A versão do banco só muda quando a estrutura exigir migração.
2. Atualize `CHANGELOG.md` e execute os testes relevantes.
3. Execute `python3 scripts/build_release.py`.
4. Faça commit e envie juntos o código, `update.json` e o novo ZIP em `dist` para `main`.

Não substitua ZIPs de versões já publicadas: gere um novo número de versão. O manifesto e o pacote devem entrar no mesmo commit, e os ZIPs anteriores devem continuar disponíveis para quem já recebeu uma oferta de atualização.

## Dados e desistências

A tabela existente `{prefixo}lista_alunos_v4` é preservada. Arquivados ficam fora da lista padrão, das métricas e dos gráficos ativos. É possível consultar o motivo e o histórico e restaurar o aluno com os dados anteriores. A API e a sincronização não reativam arquivados automaticamente.

Requisitos: WordPress 6.0+, PHP 7.4+, MySQL/MariaDB e `mbstring`. O arquivamento com histórico exige InnoDB. Consulte [as instruções completas](dashboard-rematriculas/LEIA-ME.md).

## Estrutura

| Caminho | Conteúdo |
| --- | --- |
| `dashboard-rematriculas/` | Plugin completo, incluindo atualizador e templates |
| `dist/` | ZIPs instaláveis por versão |
| `update.json` | Versão estável, URL e checksum do pacote |
| `scripts/build_release.py` | Geração determinística do ZIP e do manifesto |
| `tests/` | Testes do atualizador, cálculos, valores centrais e isolamento financeiro |

O repositório contém apenas código e documentação. Configurações particulares da escola são armazenadas no WordPress e não devem ser incluídas nos commits.
