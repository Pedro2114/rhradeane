# Publicação — fontes institucionais 2A

O bloco 1 foi publicado em 30/10/2026. As migrações originais 001–003 do servidor têm bytes diferentes do arquivo git archive; foram preservadas do backup e os checksums conferidos. **Não reenviar migrações já aplicadas nem alterar os checksums no banco.**

Pacote incremental desta entrega: PUBLIC/app.js, PUBLIC/institutional.js, PUBLIC/radeane-brand.css, RH/app/bootstrap.php, RH/app/Controllers/ApiController.php, RH/app/Controllers/InstitutionalController.php, RH/app/Services/Migrations.php e RH/database/migrations/010_fontes_institucionais.php.

1. No cPanel autenticado, copiar PUBLIC e RH para backup privado fora da área pública. Não copiar credenciais para o GitHub ou enviar banco local.
2. Confirmar raiz /home2/radean67/rh.radeane.com.br; publicar os oito arquivos/pastas incrementais sem alterar config, storage, anexos ou migrações 001–009.
3. No painel de administrador geral, conferir checksums anteriores; executar somente 010 com cópia prévia privada. Verificar a confirmação no MySQL.
4. Conferir carregamento das quatro áreas, formulários e relatórios. Testes completos de permissão, revisão e arquivos usam ambiente temporário, sem cadastros fictícios no banco real.
5. Não conceder acesso familiar a pessoas reais automaticamente. A direção define as pessoas designadas; a concessão expõe os registros da área inteira. O próprio administrador geral tem acesso, e o RH não tem acesso familiar por padrão.

O relatório familiar exclui o relato e a referência da família. A ficha guarda contexto e retorno com acesso restrito; a instituição precisa definir finalidade, retenção e atendimento a titulares, inclusive para anexos e backups. Esta implementação não certifica conformidade legal. Arquivos passam por validação básica de assinatura/tamanho, sem análise antimalware.

Limites: apuração por fonte, sem consolidação automática em indicadores; acesso por área, ainda sem turmas/unidades; central apenas institucional. Pesquisas de satisfação, ponto/calendário e central unificada continuam no plano.
