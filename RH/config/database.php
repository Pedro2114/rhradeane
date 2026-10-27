<?php
declare(strict_types=1);
// Produção: MySQL. SQLite é usado somente na prévia local ou por configuração explícita.
$driver=getenv('RH_DB_DRIVER') ?: (PHP_SAPI==='cli-server'?'sqlite':'mysql');
$config=['driver'=>$driver,'path'=>getenv('RH_SQLITE_PATH') ?: dirname(__DIR__).'/database/data/rh.sqlite','host'=>getenv('DB_HOST') ?: '127.0.0.1','port'=>getenv('DB_PORT') ?: '3306','name'=>getenv('DB_NAME') ?: 'radeane_rh','user'=>getenv('DB_USER') ?: '','password'=>getenv('DB_PASS') ?: ''];
// Hospedagem compartilhada: credenciais podem ficar neste arquivo privado, nunca em PUBLIC.
if(is_file(__DIR__.'/database.local.php')){$local=require __DIR__.'/database.local.php';if(!is_array($local))throw new RuntimeException('Configuração privada inválida.');$config=array_replace($config,array_intersect_key($local,$config));}
return $config;
