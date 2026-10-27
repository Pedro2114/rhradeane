<?php
declare(strict_types=1);
// Somente CLI. Transfere a base local Node preservada para a base PHP; nunca sobrescreve dados existentes.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/Core/Database.php';require dirname(__DIR__).'/app/Services/Migrations.php';
$config=require dirname(__DIR__).'/config/app.php';$db=new Database(require dirname(__DIR__).'/config/database.php');$migration=new Migrations($db,$config['storage']);$migration->initial();$migration->run();
$source=$argv[1]??dirname(__DIR__,2).'/private/rh.sqlite';if(!is_file($source))throw new RuntimeException('Banco anterior não encontrado.');
if($db->one('SELECT id FROM users LIMIT 1')||$db->one('SELECT id FROM employees LIMIT 1'))throw new RuntimeException('O destino já possui dados. Importação interrompida para evitar sobrescrita.');
$old=new PDO('sqlite:'.$source,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$counts=[];$db->transaction(function()use($old,$db,&$counts){
    foreach(['sectors','employees','users','schedules','punches','leaves','documents','audit']as $table){$rows=$old->query('SELECT * FROM '.$table)->fetchAll();$counts[$table]=count($rows);foreach($rows as $r){if($table==='users'){$r['role']=$r['role']==='admin'?'system_admin':'employee';$r['session_version']=0;}
        if($table==='employees')$r['cpf']=null;if($table==='leaves')$r['review']=$r['review']??'';$fields=array_keys($r);$db->run('INSERT INTO '.$table.' ('.implode(',',$fields).') VALUES('.implode(',',array_fill(0,count($fields),'?')).')',array_values($r));}}
});
$oldUploads=dirname($source).'/uploads';$newUploads=$config['storage'].'/uploads';if(!is_dir($newUploads))mkdir($newUploads,0700,true);if(is_dir($oldUploads))foreach(glob($oldUploads.'/*')as $file)if(is_file($file))copy($file,$newUploads.'/'.basename($file));
echo json_encode(['migrated'=>true,'counts'=>$counts,'credentials'=>'Preservadas; sessões antigas exigem novo login.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
