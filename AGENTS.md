# Fluxo de manutenção

Este diretório contém o sistema PHP do Portal Radeane. O repositório oficial é https://github.com/Pedro2114/rhradeane, branch main.

O usuário autorizou manter o código local e o GitHub sincronizados ao solicitar alterações neste sistema. Antes de editar, confira o estado local e busque atualizações remotas. Preserve alterações existentes; nunca force push. Após implementar e verificar, registre e envie as mudanças autorizadas e confirme a sincronização.

Nunca versione RH/database/data, RH/storage, configurações privadas, credenciais, dados de funcionários ou backups. O document root da hospedagem deve ser PUBLIC.

A publicação no servidor ainda não foi configurada. Enviar ao GitHub não atualiza por si só o site. Antes de publicar, identifique e valide o destino e o mecanismo de publicação. Preserve banco de dados, anexos e configurações do servidor.

O domínio do RH já responde em https://rh.radeane.com.br e o cPanel é https://radeane.com.br:2083. A sessão da hospedagem precisa ser autenticada pelo usuário antes da publicação; não usar as pastas ou credenciais do CRM como se fossem do RH. Consulte PUBLICACAO-BLOCO-1.md.

Pedro autorizou evoluir em blocos, com testes e conferência antes de publicar cada bloco. Escopo e pendências estão em PLANO-EVOLUCAO.md. Preserve a composição visual preta, vermelha (#cf1034) e cinza do RH publicado, incluindo portais públicos. Apresente feedback crítico, riscos concretos e sugestões junto da execução, distinguindo recursos concluídos dos próximos blocos.

Avaliações e mudanças de carreira precisam de contexto e aprovação identificada; coleta e cálculos objetivos podem ser automatizados, decisões não devem ser inferidas de reclamações ou marcações ausentes. Proteja dados pessoais por finalidade e necessidade de acesso. Não declare adequação à LGPD apenas com checkbox, configuração ou testes técnicos.

Validação disponível: testes PHP em tests/services.php e integração com node --test tests/php-integration.test.mjs. O PHP portátil local fica em ../ferramentas/php/php.exe. Os testes devem usar arquivos temporários próprios, sem modificar o banco real.
