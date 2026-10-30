# Publicação controlada — bloco 1

Esta versão foi testada com PHP/PDO SQLite em base temporária. Teste de serviços e fluxo HTTP integrado aprovados. Não houve execução de migrações no banco real nem envio de dados reais para a prévia. A validação do ambiente MySQL continua necessária.

1. Identificar no cPanel o document root de `rh.radeane.com.br` e a pasta privada `RH` correspondente. Não usar as pastas do CRM.
2. Fazer cópia recuperável do código, banco e anexos do RH no servidor, fora da área pública. Conferir possibilidade de restauração antes da atualização.
3. Publicar apenas os arquivos de código versionados das pastas irmãs `PUBLIC` e `RH`. Preservar `RH/config/*.local.php`, `RH/database/data`, `RH/storage`, banco, anexos e sessões. Não enviar `.git`, credenciais nem bancos locais. Não extrair um pacote indiscriminadamente sobre a hospedagem.
4. Preferir homologação MySQL antes da troca no domínio. Verificar PHP/extensões e permissões. MySQL faz commit implícito em DDL: restauração depende da cópia, não de rollback automático da migração.
5. Como administrador geral, conferir e aplicar as migrações versionadas até `009_aprovacao_privacidade`. O painel cria cópia prévia. Conferir que todas constam aplicadas e sem alteração de checksum.
6. Testar acessos de administrador geral, RH e colaborador. Colaborador não pode consultar os documentos, currículos, avaliações ou tarefas de terceiros. Participantes de tarefa só acessam os anexos da própria tarefa.
7. Conferir checklist: link em janela não autenticada, arquivo chegando à ficha correta, pedido de correção e cancelamento. Não usar documentos reais no teste inicial.
8. Conferir modelos, vigências e relatório de frequência por setor, pessoa e período. Não usar o resultado preliminar como falta, desconto ou decisão de premiação.
9. Conferir tarefas com entrega, anexo e aprovação do solicitante. Conferir avaliação preenchida por uma pessoa e validada por outra designada.
10. Antes de habilitar o banco de talentos, o administrador geral precisa configurar em **Banco de talentos → Configurar privacidade** o responsável institucional, canal, aviso e prazo de revisão. Os procedimentos de retenção, direitos e descarte precisam ser definidos pela instituição; não há exclusão automática por retenção nesta versão. O ambiente de teste usa aviso fictício que jamais deve ser publicado como política real.
11. Validar HTTPS, armazenamento privado, logs, política de backup e proteção do servidor; assinaturas básicas de arquivo não são análise antimalware. Links de documentação não devem aparecer em capturas compartilhadas ou logs públicos.
12. Conferir telas públicas em computador e celular. A rota de talentos é `/banco-de-talentos`; a rota de documentos tem código secreto e temporário.

Fontes de referência para revisão institucional: guia de segurança da ANPD e princípios da Lei 13.709/2018. A implementação técnica, sozinha, não comprova adequação à LGPD.
