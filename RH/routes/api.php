<?php
// O controlador despacha somente rotas explícitas. Migrações e configurações exigem system_admin.
return ['public'=>['GET /api/status','POST /api/setup','POST /api/login'],'system_admin'=>['/api/system','/api/system/migrations','/api/system/settings','/api/system/users'],'rh_admin'=>['/api/employees','/api/import','/api/reports','/api/sectors','/api/schedules','/api/users'],'employee'=>['/api/me','/api/password','/api/punches','/api/leaves','/api/documents']];
