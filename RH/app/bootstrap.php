<?php
declare(strict_types=1);
$config=require dirname(__DIR__).'/config/app.php';date_default_timezone_set($config['timezone']);
foreach(['uploads','sessions','logs','backups'] as $dir)if(!is_dir($config['storage'].'/'.$dir))mkdir($config['storage'].'/'.$dir,0700,true);
ini_set('display_errors','0');ini_set('log_errors','1');ini_set('error_log',$config['storage'].'/logs/php.log');
require __DIR__.'/Core/Database.php';require __DIR__.'/Core/Http.php';require __DIR__.'/Services/Migrations.php';require __DIR__.'/Services/ImportService.php';require __DIR__.'/Services/ReportService.php';require __DIR__.'/Services/PeopleReports.php';require __DIR__.'/Services/AttendanceReport.php';
$databaseConfig=(static fn()=>require dirname(__DIR__).'/config/database.php')();
$db=new Database($databaseConfig);$migrations=new Migrations($db,$config['storage']);$migrations->initial();
session_name('radeane_rh');session_save_path($config['storage'].'/sessions');ini_set('session.use_strict_mode','1');
session_set_cookie_params(['lifetime'=>28800,'path'=>'/','httponly'=>true,'samesite'=>'Strict','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off']);session_start();
require __DIR__.'/Services/PeopleProgram.php';require __DIR__.'/Controllers/PeopleController.php';require __DIR__.'/Controllers/WorkController.php';require __DIR__.'/Controllers/DocumentRequestController.php';
require __DIR__.'/Controllers/InstitutionalController.php';
require __DIR__.'/Controllers/ApiController.php';
