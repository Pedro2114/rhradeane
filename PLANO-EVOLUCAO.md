# Evolução aprovada — RH Radeane

Pedido de Pedro em 30/10/2026: desenvolver em blocos, testar, publicar e conferir cada entrega; manter a identidade visual do RH publicado. Apresentar opinião crítica e sugestões junto da execução.

## Bloco 1 — base operacional (publicado em 30/10/2026; migrações 001–009 conferidas no MySQL)

- Banco de talentos em `/banco-de-talentos`: PDF privado, autorização registrada com versão e cópia do aviso, pesquisa e acompanhamento do RH. Recebimento desabilitado até configuração institucional de privacidade.
- Checklist de documentos por pessoa, link imprevisível válido por até sete dias, cancelamento, recebimento na ficha e reenvio após pedido de correção. Sem acesso público ao prontuário.
- Modelos de jornadas fixas, sem horário fixo e grade semanal de hora-aula. Vínculo com vigência e cópia da grade para preservar histórico. Referência de hora-aula de 50 minutos. Dispensa de ponto permanece uma decisão separada do RH.
- Cronograma de tarefas: solicitante, responsável, prazo, dependência, entrega com observação/anexo privado e conferência pelo solicitante ou RH. Entregue e concluída são situações diferentes.
- Edital integral de premiações preservado, ciclos e metodologia, requisitos cumulativos, evidências, validação por aprovador diferente do preenchente, prazo de concessão e registro de entrega/revogação.
- Medições estruturadas de assiduidade, pontualidade, planejamento, satisfação, formações, metas, projetos e rematrículas. Nesta entrega, bases são informadas e conferidas pelo RH; não são notas automáticas.
- Apuração automática **preliminar** do ponto pela jornada vigente. Dias sem entrada são sinalizados para conferência. Férias/folgas/ausências aprovadas são separadas; justificativas documentais não excluem dias automaticamente. Feriados, tolerâncias contratuais e jornadas completas ainda dependem de evolução.
- Relatórios: marcações, apuração preliminar, indicadores, critérios/concessões, vigências de jornada, tarefas e talentos, além dos relatórios anteriores. Filtros de pessoa, setor e período, CSV e impressão/PDF.
- Registros iniciais das seis ferramentas da foto: organograma, RACI, descrições de cargos, remuneração variável, sucessão e Partnership/LTI. Organograma nesta entrega é cadastro textual; árvore interativa e vínculos formais pertencem ao bloco 3. Não há cálculo financeiro ou contratação automática.

## Bloco 2 — fontes institucionais e central de pendências

### Entrega 2A — publicada em 30/10/2026

Migração 010 aplicada no MySQL com cópia privada prévia; histórico 001–009 sem alerta de checksum. Formulários e relatório conferidos online, sem inserir registros fictícios. Fluxos de gravação, isolamento, anexos e revisão testados em SQLite temporário; não houve teste de gravação de ficha fictícia no MySQL real.

- Nova aba **Rotinas institucionais**: atendimentos familiares, projetos com marcos e participantes, formações/certificados e entregas de planejamento com qualidade e prazo.
- Anexos PDF/JPG/PNG privados por área, histórico de andamento/consulta, revisão concorrente, rascunho, encaminhamento e conferência por pessoa diferente do autor e responsável. Fontes validadas são imutáveis; arquivamento exige outra pessoa e motivo.
- Atendimentos restritos à direção e às pessoas explicitamente designadas por área. Administrador de RH não recebe acesso automático a relatos familiares. Acesso designado é à área inteira; a direção deve limitar a equipe. Não há filtragem por turma nesta entrega.
- Relatórios por área, período, pessoa e setor; CSV com proteção contra fórmulas. Relatórios de famílias omitem contexto, referência familiar, encaminhamento e retorno; detalhes só na ficha restrita. Lista principal tem busca, pessoa, setor e situação.
- Fila de pendências institucionais com prazo vencido e conferência. As filas de tarefas e documentos permanecem nas telas próprias; divergências de ponto ainda não integram esta central.
- Apuração por fonte validada: uma atividade por participante, comprovado/não comprovado, justificados explicitamente excluídos; planejamento considera prazo além da qualidade conferida. Fonte e revisão são identificadas. É uma prévia da fonte, ainda não grava nem consolida automaticamente `performance_metrics`.
- Ainda pendentes do bloco 2: pesquisas de satisfação, consolidação entre fontes com rastreabilidade, central unificada, calendário/feriados, justificativas parciais, tolerâncias e correções aprovadas de ponto. Não há mudança automática em prêmios, remuneração ou carreira.

- Ficha de atendimento à família: acesso restrito por função; data, responsável, assunto, elogio/preocupação/dúvida, professor relacionado, encaminhamento, evidência e retorno. Minimizar identificação de crianças e conteúdo sensível. Manifestação não determina nota ou penalidade automática; contexto e apuração são obrigatórios.
- Projetos: objetivo, responsável, participantes, marcos, prazos, entregas, anexos e resultado institucional. Participação registrada por pessoa.
- Formações e certificados: previsto, presença, conclusão e evidência.
- Planejamento: entregas previstas, vencimento e validação de correção/qualidade pela coordenação.
- Pesquisas de satisfação por ciclo, população, quantidade de respostas e critérios definidos antes da avaliação. Evitar exposição de respostas individuais e inferência indevida de anonimato em grupos pequenos.
- Integração dessas fontes com medições da premiação. Recalcular indicadores e sinalizar qualidade/incompletude da base, preservando aprovação humana.
- Central de pendências: documentos, entregas para conferir, divergências de ponto, evidências e prazos, com acesso ao registro de origem.
- Definir calendário/feriados, justificativas parciais, tolerâncias e correções aprovadas antes de ampliar apuração automática do ponto.

## Bloco 3 — estrutura e carreira

- Cargo/posição separado da pessoa. Árvore hierárquica com posições vagas, liderança, setor e ocupantes.
- Arrastar pessoa entre posições, exibindo nova função, liderança e vigência; gravar a alteração após aprovação identificada. Alternativa acessível por formulário, sem depender de arrastar.
- Atualizar cadastro a partir da ocupação aprovada, sem atribuir permissões administrativas automaticamente.
- Plano de carreira por cargo e nível: competências, formações, entregas e critérios de progressão; acompanhamento individual e aprovação da gestão. Progressão não concede salário ou promoção automaticamente.
- RACI vinculada a posições/rotinas. Sucessão e continuidade; planos de incentivos exigem deliberação e formalização próprias.

## Bloco 4 — jornada do colaborador

- Admissão e integração: checklist e acompanhamentos de 30, 60 e 90 dias.
- Conversas de acompanhamento, plano de desenvolvimento e próximas revisões.
- Cobertura/substituição por setor, férias e grade docente; conflitos e validação antes de confirmar.
- Portal individual com jornada vigente, tarefas, documentos e solicitações de correção.

## Condições transversais de cada bloco

- Identidade visual: preto, vermelho `#cf1034`, cinza, tipografia e marca do RH publicado; conferir computador e celular.
- Permissões por necessidade de acesso, segregação de aprovação e auditoria. Não misturar conteúdo familiar ou de saúde com tarefas comuns de marketing.
- Definir institucionalmente finalidades, bases legais, prazos de retenção, canal de direitos, atendimento a titulares, descarte/anônimização e cópias de segurança. Configurar não equivale a certificar conformidade com a LGPD.
- Testes isolados, revisão visual, cópia prévia, atualização controlada do código, migrações e conferência no MySQL antes de aceitar uso real. Não sobrescrever configurações, banco, documentos ou sessões.
- Regras do edital ainda precisam de parâmetros da gestão: assiduidade/pontualidade, índices satisfatórios, metas, formações exigidas, carteira/exclusões de rematrícula e tratamento de empates. O item 10 se chama “Não cumulação”, mas permite reconhecimento em mais de uma categoria; revisar o texto com a instituição.
