<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: same-origin');header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
try{
    require __DIR__.'/../RH/app/bootstrap.php';
    $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
    if(str_starts_with($path,'/api/'))(new ApiController($db,$migrations,$config))->dispatch($path,$_SERVER['REQUEST_METHOD']);
    if($path==='/theme.css'){
        $settings=array_column($db->all('SELECT * FROM system_settings'),'setting_value','setting_key');$accent=$settings['accent']??'#cf1034';if(!preg_match('/^#[0-9a-fA-F]{6}$/',$accent))$accent='#cf1034';$font=in_array($settings['font']??'Sora',['Sora','Inter'],true)?($settings['font']??'Sora'):'Sora';header('Content-Type: text/css; charset=utf-8');echo ':root{--brand-red:'.$accent.';--coral:'.$accent.'}h1,h2,h3,h4,.brand strong{font-family:'.$font.',Inter,sans-serif}';exit;
    }
    if(!preg_match('~^/enviar-documentos/[a-f0-9]{64}$~',$path)&&!in_array($path,['/','/login','/atestados','/ponto','/banco-de-talentos'],true))fail('Não encontrado.',404);
    header('Content-Type: text/html; charset=utf-8');readfile(__DIR__.'/index.html');
}catch(Throwable $e){
    $status=$e instanceof HttpError?$e->status:500;$message=$status===500?'Não foi possível concluir. Confira a configuração PHP e do banco.':$e->getMessage();
    if($e instanceof PDOException&&in_array((string)$e->getCode(),['23000','19'],true)){$status=409;$message='Já existe um cadastro com esses dados ou o registro está vinculado a outro cadastro.';}
    if($status===500)error_log((string)$e);http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode(['error'=>$message],JSON_UNESCAPED_UNICODE);
}
