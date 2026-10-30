# Portal Radeane — Gestão de Pessoas em PHP

Versão 0.4 — primeiro bloco de desenvolvimento de pessoas. Aplicação PHP com banco MySQL na hospedagem e SQLite na prévia local. O Node.js não faz parte da execução do sistema; é usado apenas pelo teste automatizado de integração.

## Estrutura

```text
sistema-rh/
  PUBLIC/
    index.php              entrada única das requisições
    router.php             roteador do servidor PHP local
    index.html             estrutura visual
    app.js                 interação das telas
    app.css
    radeane-brand.css
    assets/                logo transparente e fontes locais
    .htaccess
  RH/
    app/
      Controllers/         operações HTTP e permissões
      Core/                banco, validações e respostas
      Services/            importação, relatórios e migrações
      bootstrap.php
    config/                configuração privada da aplicação e do banco
    database/
      migrations/          alterações versionadas do banco
      migrate.php          execução por CLI
      import-legacy.php    transferência da versão local anterior
      data/                SQLite local, não incluído no pacote
    routes/                mapa de responsabilidades das rotas
    storage/               documentos, sessões, logs e cópias do banco
    .htaccess              bloqueio adicional de acesso web
  tests/
  Iniciar RH.cmd
```

Na hospedagem, o document root precisa apontar para `PUBLIC`. Nunca exponha a raiz do projeto ou a pasta `RH`. Os arquivos HTML anteriores permanecem na pasta superior somente como histórico de protótipos.

## Abrir neste computador

1. Execute `Iniciar RH.cmd` e mantenha a janela aberta.
2. Acesse `http://localhost:4318`.
3. Entre com sua conta existente. A conta da versão anterior foi transferida com a senha preservada; o acesso principal agora é **Administrador geral do sistema**. As sessões antigas precisam de novo login.

O iniciador procura PHP no PATH e, neste computador, também em `../ferramentas/php/php.exe`. Foi preparado um PHP portátil oficial fora da aplicação, sem instalação de serviço no Windows. O ZIP de código não inclui esse runtime.

Endereços: `/` para o painel, `/ponto` para o tablet, `/atestados` para o portal do funcionário e `/banco-de-talentos` para currículos (recebimento exige configuração institucional de privacidade). Links de documentos usam `/enviar-documentos/<código temporário>`. O servidor local aceita conexões somente neste computador; ainda não está disponível no celular/tablet físico nem no subdomínio público.

A demonstração usa dados fictícios em memória e não grava registros no banco. Migrações, importações, relatórios do banco e alterações técnicas exigem uma conta real.

## Perfis separados

| Perfil | Acesso |
|---|---|
| Administrador geral do sistema | Toda a aplicação, migrações, identidade visual e criação/gestão dos perfis administrativos. |
| Administrador de RH | Colaboradores, setores, jornadas, importação, frequência, documentos, férias, folgas, relatórios e criação de acessos individuais. Não executa migrações nem altera configurações técnicas ou perfis administrativos. |
| Colaborador | Apenas seus registros, jornada, solicitações, documentos e ponto individual. |

No menu **Administração do sistema**, o administrador geral cria os administradores de RH, consulta migrações, gerencia os acessos e configura o nome do portal, a fonte dos títulos (Sora ou Inter) e a cor principal. O sistema impede remover seu próprio acesso geral e protege a permanência de um administrador geral ativo.

O painel técnico administra esta aplicação de RH. O login unificado e o painel central compartilhado com o CRM existente ainda não foram integrados; nenhum arquivo do CRM foi alterado.

## Importação

Em **Importar colaboradores**, envie Excel `.xlsx` ou CSV com as colunas `Nome`, `CPF`, `Telefone` e `Setor`. Nome e CPF são obrigatórios; telefone e setor podem ser completados depois. Cargo e admissão ficam vazios se não forem informados posteriormente.

O sistema valida CPF, duplicidades, telefone e tamanho do arquivo; mostra uma prévia por linha; pede a vinculação a um setor existente ou autorização para criar setores; e só grava após a confirmação. Linhas com erro são ignoradas conforme a confirmação exibida. CPFs já cadastrados não são sobrescritos. Uma prévia pertence à conta que a criou, expira em 30 minutos e só pode ser confirmada uma vez.

Limites: 5 MB, 2.000 colaboradores e 100 colunas. A primeira aba de um Excel é usada. Fórmulas são recusadas; cole os valores antes de importar. Excel antigo `.xls` precisa ser salvo como `.xlsx`. Formate CPF e telefone como texto para preservar zeros à esquerda. O arquivo original não é arquivado; a prévia normalizada fica temporariamente no banco privado.

## Prontuário e relatórios

Cada funcionário tem cadastro com CPF, contato, setor, cargo, admissão e observações, além de jornada, ponto, férias/folgas, documentos e histórico.

O botão **Relatório deste funcionário** abre os filtros já vinculados à pessoa. O menu **Relatórios** permite consultar frequência, férias/folgas/ausências, documentos/atestados e cadastro, filtrando funcionário, setor e período. Há download CSV e impressão pelo navegador, que também permite salvar em PDF. O CSV neutraliza prefixos de fórmula.

O relatório de frequência usa a grade preservada por vigência quando vinculada; cadastros anteriores usam a jornada atual. “Sem marcação” pede conferência e não determina automaticamente uma falta. Não há cálculo oficial de horas, atrasos, banco de horas ou remuneração.

## Ponto, documentos e férias

O ponto exige conta individual e a sequência Entrada → Saída para intervalo → Retorno → Saída. O horário é registrado pelo servidor. Depois da marcação no tablet, a interface encerra a sessão para liberar o equipamento. Ainda não há PIN rápido, biometria, homologação ou comprovantes oficiais.

O portal aceita JPG, PNG ou PDF de até 5 MB, guarda os arquivos fora de PUBLIC e exige autenticação para baixá-los. O funcionário vê os próprios protocolos e respostas do RH. A validação verifica tamanho e assinatura básica do formato, sem varredura antimalware.

Férias, folgas e ausências têm solicitação, análise e verificação de sobreposição individual. Datas contratuais, períodos aquisitivos/concessivos, cobertura por setor, escalas alternadas e jornadas noturnas ainda não são calculados automaticamente.

## Hospedagem em PHP/MySQL

Requisitos: PHP 8.2 ou superior, PDO MySQL, mbstring, Zip, XML/SimpleXML, sessões e suporte a Argon2id. Sodium é necessário para validar e converter as senhas legadas da versão Node. Recomenda-se PHP atualizado; o runtime local foi obtido na [fonte oficial do PHP](https://www.php.net/downloads.php?os=windows&version=8.4).

1. Crie um banco MySQL 8+ com charset `utf8mb4`.
2. Envie `PUBLIC` e `RH` como pastas irmãs. Aponte o domínio diretamente para `PUBLIC`.
3. Configure no ambiente do servidor as variáveis de `RH/config/environment.example`: `RH_DB_DRIVER=mysql`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` e `DB_PASS`. O arquivo de exemplo é documentação; não é carregado automaticamente como .env.
4. Configure também `RH_SETUP_TOKEN` com um código aleatório secreto de pelo menos 32 caracteres. Ele é exigido para criar a primeira conta em uma instalação publicada. Não há senha administrativa padrão.
5. Execute `php RH/database/migrate.php` no diretório do projeto. A aplicação também cria a estrutura inicial em um banco vazio; alterações seguintes podem ser aplicadas pelo administrador geral no painel.
6. Configure HTTPS e permissões de escrita somente nas pastas necessárias: `RH/storage` e, para SQLite local, `RH/database/data`.
7. Crie o primeiro administrador geral e confira acessos e fluxos antes de colocar dados reais.

A aplicação usa URLs a partir da raiz do domínio. Hospedagem em subpasta exige adaptação de base_path, ainda não implementada. O MySQL está configurado no código, mas não havia servidor MySQL disponível para teste nesta máquina; os testes executados usaram PHP/PDO SQLite. A publicação e a validação no ambiente MySQL continuam pendentes.

## Migrações, cópias e dados privados

As migrações estão em `RH/database/migrations`. O painel executa somente essas alterações versionadas, registra versões/checksums e não permite executar SQL digitado. No MySQL, alterações de estrutura fazem commit implícito; não há rollback automático de DDL. As migrações atuais são retomáveis.

Antes de aplicar alterações pendentes, o sistema cria uma cópia privada do banco em `RH/storage/backups`: SQLite completo na prévia local; snapshot JSON das tabelas persistentes no MySQL. Esse snapshot não é um dump SQL nem inclui anexos; a restauração MySQL ainda é um procedimento técnico, não um botão no painel. Mantenha também backups da hospedagem e dos arquivos de `RH/storage/uploads` e valide a recuperação.

A base local atual fica em `RH/database/data/rh.sqlite`. O histórico da versão anterior está em `../historico-versao-node`, preservado fora do pacote de entrega. Não publique ou compartilhe o banco, essa pasta histórica, os anexos, sessões, logs ou backups. Não há criptografia própria dos arquivos nem descarte automático por retenção.

## Verificação

Foram executados testes HTTP integrados em base temporária: criação de conta, login, CSRF, separação entre administradores, bloqueio de elevação de perfil pelo RH, isolamento entre funcionários, migrações, importação CSV e confirmação única, relatórios, exportação, arquivos privados, ponto, férias e troca/inativação de acessos.

O teste de serviços PHP verifica Excel, CPF com zero inicial, formatos inválidos, fórmulas, CSV e compatibilidade da senha antiga:

```text
php tests/services.php
```

O teste HTTP usa Node somente como ferramenta de teste. Configure `RH_PHP_BIN` com o executável PHP, se ele não estiver no caminho portátil desta máquina:

```text
node --test tests/php-integration.test.mjs
```

A conferência visual foi feita no servidor principal em demonstração. O servidor visual isolado adicional não pôde ser iniciado porque a revisão automática da ferramenta atingiu o limite de uso.

## Evoluções restantes

Publicação e domínio, integração de login com o CRM, validação MySQL na hospedagem, regras avançadas de jornadas/férias, validação do ponto para uso oficial, recuperação de senha e evolução das fontes automáticas de avaliação institucional. O primeiro bloco de premiações já permite ciclos, evidências e aprovação segregada, conforme o edital recebido. Perfis por coordenação de setor e auditor independente também permanecem para evolução.


## Entrega em blocos

Consulte [PLANO-EVOLUCAO.md](PLANO-EVOLUCAO.md) para a distinção entre recursos implementados e próximos blocos aprovados. Consulte [PUBLICACAO-BLOCO-1.md](PUBLICACAO-BLOCO-1.md) antes de atualizar a hospedagem. A prévia isolada e o GitHub não atualizam o domínio automaticamente.
