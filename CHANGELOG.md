# Histórico de versões

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
