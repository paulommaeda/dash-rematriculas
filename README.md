# Dashboard de Rematrículas Journey

Plugin WordPress do Colégio Journey, desenvolvido por Paulo Maeda. Painel de acompanhamento de rematrículas, dados financeiros, conferências por setor, integração com Fluent Forms e arquivamento reversível de desistências.

**Versão: 4.2.0** · [Baixar ZIP para instalar](https://raw.githubusercontent.com/paulommaeda/dash-rematriculas/main/dist/dashboard-rematriculas-4.2.0.zip)

## Instalação

1. Faça backup do banco e do plugin atual.
2. Em **Plugins → Adicionar plugin → Enviar plugin**, envie o ZIP de `dist`. Se a versão anterior estiver em outra pasta, desative-a antes de ativar esta.
3. Use o shortcode `[dash_rematriculas]` na página do painel, ou abra **Dash Rematrículas** no WordPress.

O ZIP contém a pasta `dashboard-rematriculas`, preservando o caminho do plugin 4.0. O ZIP automático de código-fonte gerado pelo GitHub não é o pacote de instalação.

Quem já instalou a versão 4.0 mantém os dados e credenciais. Em uma instalação nova ou atualização direta da versão 3.9, configure a senha do painel e a chave da API em **Dash Rematrículas → Configurações**. A versão pública não contém senhas ou chaves fixas. Também é possível definir `DRJ_DASHBOARD_PASSWORD` e `DRJ_API_SECRET` no `wp-config.php`.

## Dashboard financeiro

Abra **Dash Rematrículas → Financeiro**, ou use a aba **Financeiro** na página que já contém `[dash_rematriculas]`. Para uma página dedicada, use `[dash_financeiro]`, com o mesmo acesso restrito.

O painel apresenta bruto, bolsas, desconto condicional e líquido no total geral e em tabelas por curso e turma. Os cursos são identificados pelas turmas: Fundamental I, Fundamental II e Ensino Médio. Turmas não reconhecidas ficam separadas.

- **Por parcela:** soma de uma parcela de cada aluno do filtro.
- **Total dos contratos:** cada valor é multiplicado pelo número de parcelas do respectivo aluno.
- **Bolsas:** soma de desconto incondicional, indicação e irmãos.
- **Líquido:** bruto menos bolsas e desconto condicional.

A visão inicial considera rematriculados e alunos novos. Os filtros permitem incluir todos em acompanhamento ou somente pendentes, além de selecionar curso e turma. Desistências arquivadas ficam fora dos cálculos. Os gráficos e os totais acompanham os filtros. O botão **Atualizar dados** consulta os últimos valores cadastrados sem alterar registros.

Os números são projeções baseadas nos valores de 2027 já cadastrados no plugin. O total por contrato aplica os mesmos valores e descontos a todas as parcelas informadas; não há cronograma de descontos diferentes por vencimento na base atual.

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
| `tests/` | Testes do atualizador e dos cálculos financeiros com WordPress carregado |

O repositório contém apenas código e documentação. Configurações particulares da escola são armazenadas no WordPress e não devem ser incluídas nos commits.
