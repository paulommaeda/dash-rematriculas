# Integração de rematrículas · 4.5.0

O dashboard pode receber envios de qualquer sistema por POST ou consultar a API de requerimentos do ERP Educacional 0.19.23+. As duas entradas usam a mesma confirmação por RA do Fluent Forms.

## 1. Enviar para o dashboard

Em **Dash Rematrículas → Configurações → Acesso e integração**, cadastre a chave da API. Se já houver uma chave, pode continuar usando-a. A constante `DRJ_API_SECRET`, quando definida no `wp-config.php`, tem prioridade. Trocar a chave também exige atualizar a integração de novos alunos que já a utiliza.

Copie o endereço exibido em **API de rematrículas e consulta ao ERP**. Na instalação padrão:

```text
POST https://SEU-DASHBOARD/wp-json/drj/v1/rematriculas
Content-Type: application/json
X-DRJ-Key: SUA_CHAVE_DE_INTEGRACAO
```

A chave é exigida no cabeçalho, mesmo se houver uma sessão WordPress aberta. Não é aceita na URL ou no JSON. A rota exige HTTPS. Em sites sem links permanentes, o WordPress pode exibir `https://SEU-DASHBOARD/?rest_route=/drj/v1/rematriculas`; use a URL apresentada nas configurações.

### Envio simples

```json
{
  "ra": "000123",
  "data_rematricula": "2026-11-13T13:00:00Z"
}
```

| Campo | Regra |
| --- | --- |
| `ra` | Obrigatório, texto com até 100 bytes. Preserve zeros iniciais. Associação exata, inclusive maiúsculas/minúsculas. |
| `data_rematricula` | Opcional. ISO 8601 com fuso, como `2026-11-13T10:00:00-03:00` ou UTC com `Z`. Ausente/vazio: horário de recebimento. |
| `status` | Opcional: `pendente`, `aprovado` ou `rejeitado`. Ausente, pendente e aprovado confirmam o recebimento; rejeitado é ignorado. |

Exemplo para o integrador, substituindo os valores ilustrativos:

```bash
curl --request POST \
  --header 'Content-Type: application/json' \
  --header 'X-DRJ-Key: SUA_CHAVE_DE_INTEGRACAO' \
  --data '{"ra":"000123","data_rematricula":"2026-11-13T13:00:00Z"}' \
  'https://SEU-DASHBOARD/wp-json/drj/v1/rematriculas'
```

Resposta HTTP 200, considerando WordPress no fuso de São Paulo:

```json
{
  "ra": "000123",
  "situacao": "confirmado",
  "alterado": true,
  "data_rematricula": "2026-11-13 10:00:00"
}
```

`data_rematricula` na resposta e no banco usa o fuso configurado no WordPress. Não é um horário UTC sem identificação.

| `situacao` | Efeito |
| --- | --- |
| `confirmado` | Aluno existente e ativo passa a `status_rematricula=1`, com a data do requerimento. |
| `ja_confirmado` | Reenvio aceito; mantém a primeira confirmação e a data anterior. |
| `arquivado` | Nenhuma alteração. O aluno continua arquivado. |
| `rejeitado` | Nenhuma alteração. Não desfaz confirmações anteriores. |
| `sem_ra` | Objeto do ERP sem RA disponível; não foi associado a um aluno. |
| `anterior_ao_periodo` | Objeto do ERP anterior à campanha configurada; não importado. |

**Mesma regra do Fluent Forms:** a integração confirma alunos que já estão no acompanhamento. Não cria cadastros para RAs desconhecidos, não altera o tipo do aluno e não procura correspondências por nome ou CPF. Não altera nome, turma, preços, descontos, parcelas, conferências ou anotações. Não restaura desistências. O financeiro consolidado mantém a autorização exclusiva.

RAs desconhecidos recebem HTTP 404. Cadastre o aluno no acompanhamento e reenvie. RAs duplicados recebem HTTP 409; corrija a duplicidade antes de reenviar. O cadastro de alunos novos continua usando a integração anterior `drj_receber_aluno_novo`.

### Lotes e contrato do ERP

Envie até 50 itens e no máximo 1 MiB por chamada:

```json
{
  "items": [
    {"ra":"000123","data_rematricula":"2026-11-13T13:00:00Z"},
    {"ra":"000456","data_rematricula":"2026-11-13T13:05:00Z"}
  ]
}
```

O retorno contém `resultados` na ordem enviada, com `indice` iniciado em zero, `sucesso` e o resultado de cada item. Falhas incluem `codigo`, `mensagem` e `http_status`. O HTTP é 200 quando não há erros ou **207** quando algum item falha. O lote não é uma transação única: sucessos permanecem salvos. Trate os resultados individualmente e reenvie as falhas após a correção; repetir sucessos é seguro.

A rota também aceita diretamente o item do ERP, o envelope de detalhe `{"schema_version":"1.0","item":{...}}` ou a coleção `{"schema_version":"1.0","items":[...]}`. Para objetos do ERP:

- Configure a **coligada autorizada** nas configurações do dashboard. Cada item precisa informar a mesma `codcoligada` como texto.
- `idrequerimento` e `versao` devem ser textos com inteiros positivos, conforme o contrato 0.19.23.
- O RA de `resultado.ra` tem prioridade após a aprovação. Se não existir, o dashboard lê o caminho configurado, por exemplo `dados.campos.ra_aluno`.
- A data vem de `criado_em`. O início da campanha, se configurado, é aplicado no fuso do WordPress.
- Pendentes e aprovados com RA confirmam o **recebimento do requerimento**, como o envio do Fluent Forms. Isso não significa aprovação no ERP nem conferência dos setores.
- Rejeitados são ignorados. Alterações de versão não sobrescrevem a primeira confirmação. O dashboard não mantém um espelho dos requerimentos nem dos seus dados pessoais.

Não é necessário transmitir documentos, dados médicos ou anexos. Se o sistema enviar campos adicionais, eles não são armazenados. A resposta não contém o cadastro completo nem dados financeiros.

### Erros

| HTTP | Significado |
| --- | --- |
| 400 | JSON inválido |
| 401 | Chave ausente, incorreta ou revogada |
| 403 | Conexão sem HTTPS |
| 404 | RA não cadastrado; método/rota inexistente |
| 409 | RA duplicado, cadastro alterado durante a confirmação ou coligada do ERP não configurada |
| 413 | Corpo maior que 1 MiB |
| 415 | Content-Type diferente de JSON |
| 422 | RA, data, status, coligada, contrato ou lote inválidos |
| 503 | Banco pendente de atualização ou falha ao consultar/gravar; tente novamente |

Erros individuais usam o formato REST do WordPress: `code`, `message`, `data.status`. As respostas da rota recebem `Cache-Control: private, no-store, max-age=0`. Não há rota GET para ler alunos ou finanças com essa chave.

Especificação para importar em ferramentas de integração: [openapi-rematriculas.json](openapi-rematriculas.json).

## 2. Buscar automaticamente no ERP

Esta opção funciona com os endpoints de leitura descritos no guia do ERP 0.19.23; não exige que o ERP implemente um webhook.

1. No ERP, crie uma conta exclusiva com **Integração: leitura de requerimentos**, autorize a coligada e gere uma **Senha de aplicativo**.
2. No dashboard, em **Configurações → API de rematrículas e consulta ao ERP**, informe coligada, URL completa terminada em `/erp-educacional/v1/integracao/requerimentos`, usuário e senha de aplicativo.
3. Se o RA estiver em um campo do formulário antes da aprovação, informe o caminho exato, como `dados.campos.ra_aluno`. Sem ele e sem `resultado.ra`, o item será sinalizado como `sem_ra`.
4. Selecione o início da campanha para evitar confirmações por envios de anos anteriores.
5. Salve. Use **Sincronizar ERP agora** para consultar imediatamente. Opcionalmente habilite a consulta automática a cada 15 minutos.

O botão percorre páginas de 20 itens e mostra o progresso. Fechar a página interrompe somente a sequência manual; o próximo clique retoma a rodada. Com o automático habilitado, as continuações também são agendadas. O WP-Cron depende das visitas e da configuração de cron da hospedagem; 15 minutos é a frequência prevista, não uma garantia de execução no horário exato.

A primeira rodada é completa. As seguintes reiniciam o cursor em zero, filtram por `atualizado_desde` e fixam `atualizado_ate` no início da rodada, com sobreposição de dois minutos. O marco só avança após a última página. Isso permite encontrar alterações em requerimentos antigos. Falhas de rede, contrato ou gravação preservam a possibilidade de repetir a rodada. As confirmações são idempotentes por aluno, mesmo se Fluent Forms e ERP receberem o mesmo RA ao mesmo tempo.

Uma vez ao dia, a próxima rodada faz nova leitura completa para revisitar RAs antes ausentes. **Refazer leitura completa** antecipa essa reconciliação após corrigir ou cadastrar alunos. O resumo informa quantos foram confirmados, já estavam confirmados, estão arquivados, não foram encontrados, estão sem RA, têm RA duplicado, foram rejeitados ou são anteriores ao período. Até dez identificadores de requerimentos com pendência aparecem para conferência; corpos completos não são gravados.

As consultas usam HTTP Basic com Senha de aplicativo sobre HTTPS, validação de certificado, proteção de destinos do WordPress e nenhum redirecionamento. Não fazem POST/PATCH no ERP, não baixam arquivos nem disparam e-mails. A senha é cifrada no banco com as chaves do WordPress. Deixe o campo vazio para mantê-la; mudar o destino ou o usuário exige informar a senha novamente. Se trocar as chaves/salts do WordPress, cadastre a senha novamente. Para revogar, remova a senha no dashboard ou revogue-a na conta do ERP.

Somente administradores podem configurar ou executar a sincronização manual. A chave de recebimento do dashboard e a Senha de aplicativo do ERP são credenciais distintas.
