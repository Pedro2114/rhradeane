<?php
declare(strict_types=1);
$config=['timezone'=>'America/Sao_Paulo','name'=>'Portal Radeane','version'=>'0.2.1','local'=>PHP_SAPI==='cli-server','storage'=>getenv('RH_STORAGE_DIR') ?: dirname(__DIR__).'/storage','setup_token'=>getenv('RH_SETUP_TOKEN') ?: ''];
if(is_file(__DIR__.'/app.local.php')){$local=require __DIR__.'/app.local.php';if(!is_array($local))throw new RuntimeException('Configuração privada inválida.');$config=array_replace($config,array_intersect_key($local,$config));}
return $config;
