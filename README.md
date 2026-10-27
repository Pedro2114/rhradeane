# Portal Radeane — RH

Sistema de gestão de pessoas em PHP. Consulte [LEIA-ME.md](LEIA-ME.md) para execução, configuração, recursos e limitações.

## Código e atualizações

Repositório oficial: https://github.com/Pedro2114/rhradeane — branch `main`.

A cópia de trabalho neste computador fica em `C:\Users\Radeane\Downloads\RH\sistema-rh`.

O fluxo de manutenção é buscar atualizações, implementar a alteração solicitada, validar, registrar e enviar ao GitHub. Dados, documentos e configurações privadas permanecem fora do repositório.

## Publicação

A integração de publicação com a hospedagem está pendente de identificar o servidor e seu acesso. Um envio ao GitHub, sozinho, não atualiza o site.

Na hospedagem, `PUBLIC` e `RH` ficam lado a lado, com o domínio apontando para `PUBLIC`. Atualizações devem preservar `RH/storage`, o banco e os arquivos privados de configuração. Não envie a pasta `.git` para uma área acessível pela web.
