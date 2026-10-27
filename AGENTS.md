# Fluxo de manutenção

Este diretório contém o sistema PHP do Portal Radeane. O repositório oficial é https://github.com/Pedro2114/rhradeane, branch main.

O usuário autorizou manter o código local e o GitHub sincronizados ao solicitar alterações neste sistema. Antes de editar, confira o estado local e busque atualizações remotas. Preserve alterações existentes; nunca force push. Após implementar e verificar, registre e envie as mudanças autorizadas e confirme a sincronização.

Nunca versione RH/database/data, RH/storage, configurações privadas, credenciais, dados de funcionários ou backups. O document root da hospedagem deve ser PUBLIC.

A publicação no servidor ainda não foi configurada. Enviar ao GitHub não atualiza por si só o site. Antes de publicar, identifique e valide o destino e o mecanismo de publicação. Preserve banco de dados, anexos e configurações do servidor.

Validação disponível: testes PHP em tests/services.php e integração com node --test tests/php-integration.test.mjs. O PHP portátil local fica em ../ferramentas/php/php.exe. Os testes devem usar arquivos temporários próprios, sem modificar o banco real.
