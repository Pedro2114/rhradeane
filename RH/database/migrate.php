<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/Core/Database.php';require dirname(__DIR__).'/app/Services/Migrations.php';
$config=require dirname(__DIR__).'/config/app.php';$db=new Database(require dirname(__DIR__).'/config/database.php');$m=new Migrations($db,$config['storage']);$m->initial();$result=$m->run();echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
