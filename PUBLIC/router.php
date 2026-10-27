<?php
declare(strict_types=1);
// Servidor embutido local: somente arquivos visuais conhecidos são servidos diretamente.
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH));$file=realpath(__DIR__.$path);$base=realpath(__DIR__).DIRECTORY_SEPARATOR;
if($file&&str_starts_with($file,$base)&&is_file($file)&&in_array(strtolower(pathinfo($file,PATHINFO_EXTENSION)),['css','js','png','woff2','ico'],true))return false;
require __DIR__.'/index.php';
