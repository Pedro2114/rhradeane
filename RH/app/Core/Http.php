<?php
declare(strict_types=1);
function fail(string $message,int $status=400):never{throw new HttpError($message,$status);}
final class HttpError extends RuntimeException{public function __construct(string $message,public int $status){parent::__construct($message);}}
function text(mixed $v,int $max=300):string{return mb_substr(trim(is_scalar($v)?(string)$v:''),0,$max);}
function required(mixed $v,string $label):string{$s=text($v);if($s==='')fail($label.' é obrigatório.');return $s;}
function validDay(mixed $v,bool $optional=false):?string{if($optional&&($v===null||$v===''))return null;$s=text($v,10);$d=DateTimeImmutable::createFromFormat('!Y-m-d',$s);if(!$d||$d->format('Y-m-d')!==$s)fail('Data inválida.');return $s;}
function period(array $b):array{$s=validDay($b['start']??null);$e=validDay($b['end']??null);if($e<$s)fail('O fim precisa ser igual ou posterior ao início.');return [$s,$e];}
function jsonResponse(mixed $body,int $status=200):never{$normalize=function(mixed $v)use(&$normalize):mixed{if(!is_array($v))return $v;foreach($v as $k=>$x)$v[$k]=in_array($k,['id','employee_id','sector_id','actor','active'],true)&&is_numeric($x)?(int)$x:$normalize($x);return $v;};http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($normalize($body),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);exit;}
function nowUtc():string{return gmdate('Y-m-d\TH:i:s\Z');}
function html(mixed $v):string{return htmlspecialchars((string)($v??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function validCpf(mixed $raw):?string{$cpf=preg_replace('/\D/','',text($raw,40));if(strlen($cpf)!==11||preg_match('/^(\d)\1{10}$/',$cpf))return null;for($t=9;$t<11;$t++){$sum=0;for($i=0;$i<$t;$i++)$sum+=(int)$cpf[$i]*($t+1-$i);$dv=(($sum*10)%11)%10;if((int)$cpf[$t]!==$dv)return null;}return $cpf;}
function passwordHash(mixed $p):string{if(!is_string($p)||strlen($p)<12||strlen($p)>200)fail('A senha deve ter entre 12 e 200 caracteres.');return password_hash($p,PASSWORD_ARGON2ID);}
function passwordCheck(mixed $p,string $stored):bool{
    if(!is_string($p)||strlen($p)>200)return false;
    if(preg_match('/^([a-f0-9]{32}):([a-f0-9]{128})$/',$stored,$m))return function_exists('sodium_crypto_pwhash_scryptsalsa208sha256')&&hash_equals($m[2],bin2hex(sodium_crypto_pwhash_scryptsalsa208sha256(64,$p,$m[1],524288,16777216)));
    return password_verify($p,$stored);
}
