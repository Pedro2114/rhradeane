<?php
declare(strict_types=1);
final class ImportService {
    public function __construct(private Database $db){}
    private function normalize(string $s):string{$s=mb_strtolower(trim($s));return strtr($s,['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c']);}
    public function parse(array $body):array{
        $encoded=$body['base64']??'';if(!is_string($encoded)||strlen($encoded)>8*1024*1024)fail('Planilha acima de 5 MB.');$bytes=base64_decode($encoded,true);if($bytes===false||strlen($bytes)>5*1024*1024||!$bytes)fail('Planilha inválida ou acima de 5 MB.');
        $ext=strtolower(pathinfo(text($body['filename']??''),PATHINFO_EXTENSION));
        if($ext==='csv'){
            if(!mb_check_encoding($bytes,'UTF-8'))$bytes=mb_convert_encoding($bytes,'UTF-8','Windows-1252');$bytes=preg_replace('/^\xEF\xBB\xBF/','',$bytes);
            $sample=strtok($bytes,"\r\n");$counts=[];foreach([';',',',"\t"]as $d)$counts[$d]=count(str_getcsv($sample,$d,'"',''));arsort($counts);$delimiter=array_key_first($counts);
            $stream=fopen('php://temp','r+');fwrite($stream,$bytes);rewind($stream);$rows=[];while(($row=fgetcsv($stream,0,$delimiter,'"',''))!==false){if(count($rows)>2001)fail('Importe até 2.000 colaboradores por arquivo.');$rows[]=$row;}fclose($stream);
        }elseif($ext==='xlsx')$rows=$this->xlsx($bytes);
        else fail('Use uma planilha Excel .xlsx ou CSV. Arquivos .xls antigos precisam ser salvos como .xlsx.');
        if(count($rows)<2)fail('A planilha precisa de cabeçalho e pelo menos um colaborador.');
        $headers=array_map(fn($x)=>$this->normalize((string)$x),array_shift($rows));$map=[];
        foreach(['name'=>['nome','nome completo','funcionario','colaborador'],'cpf'=>['cpf'],'phone'=>['telefone','celular','fone'],'sector'=>['setor'],'role'=>['cargo','funcao'],'email'=>['email','e-mail'],'punch_exempt'=>['dispensado de ponto']]as $key=>$aliases){foreach($headers as $i=>$header)if(in_array($header,$aliases,true)){$map[$key]=$i;break;}}
        foreach(['name','cpf']as $key)if(!isset($map[$key]))fail('Inclua as colunas Nome e CPF no cabeçalho.');
        $out=[];$seen=[];$seenNames=[];$allowMissing=($body['allow_missing_cpf']??false)===true;$existingNames=array_map(fn($r)=>$this->normalize($r['name']),$this->db->all('SELECT name FROM employees'));
        foreach($rows as $i=>$row){if(!array_filter($row,fn($x)=>trim((string)$x)!==''))continue;if(count($out)>=2000)fail('Importe até 2.000 colaboradores por arquivo.');
            $read=fn($key)=>text($row[$map[$key]??-1]??'',180);$name=$read('name');$raw=$read('cpf');$cpf=validCpf($raw);$phone=$read('phone');$sector=$read('sector');$errors=[];
            if(!$name)$errors[]='Nome obrigatório';if(!$cpf&&($raw!==''||!$allowMissing))$errors[]='CPF inválido';
            $nameKey=$this->normalize($name);if(!$cpf&&(in_array($nameKey,$existingNames,true)||isset($seenNames[$nameKey])))$errors[]='Nome já cadastrado ou repetido; confira a identidade';$seenNames[$nameKey]=true;
            if($cpf){if(isset($seen[$cpf]))$errors[]='CPF repetido na planilha (linha '.$seen[$cpf].')';if($this->db->one('SELECT id FROM employees WHERE cpf=?',[$cpf]))$errors[]='CPF já cadastrado';$seen[$cpf]=$i+2;}
            $digits=preg_replace('/\D/','',$phone);if($phone!==''&&(strlen($digits)<10||strlen($digits)>13))$errors[]='Telefone inválido: inclua DDD';
            $email=$read('email');if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='E-mail inválido';$exempt=$this->normalize($read('punch_exempt'));if(!in_array($exempt,['','0','1','sim','nao'],true))$errors[]='Dispensa de ponto: use Sim ou Não';
            $out[]=['line'=>$i+2,'name'=>$name,'cpf'=>$cpf?:$raw,'phone'=>$phone,'sector'=>$sector,'role'=>$read('role'),'email'=>$email,'punch_exempt'=>in_array($exempt,['1','sim'],true)?1:0,'warnings'=>$raw===''&&$allowMissing?['CPF pendente de preenchimento']:[],'errors'=>$errors];
        }
        if(!$out)fail('Nenhuma linha com dados encontrada.');return $out;
    }
    private function xlsx(string $bytes):array{
        if(!class_exists('ZipArchive'))fail('A extensão PHP Zip precisa estar habilitada para importar Excel.',503);
        $file=tempnam(sys_get_temp_dir(),'rh-xlsx-');file_put_contents($file,$bytes);$zip=new ZipArchive();$opened=false;
        try{if($zip->open($file)!==true)fail('O arquivo Excel não é válido.');$opened=true;$size=0;for($i=0;$i<$zip->numFiles;$i++){$stat=$zip->statIndex($i);$size+=(int)$stat['size'];if($size>30*1024*1024||$zip->numFiles>5000)fail('Planilha descompactada acima do limite.');}
            $xml=function(string $path)use($zip):?SimpleXMLElement{$s=$zip->getFromName($path);if($s===false)return null;if(strlen($s)>15*1024*1024||stripos($s,'<!DOCTYPE')!==false||stripos($s,'<!ENTITY')!==false)fail('XML não permitido na planilha.');$old=libxml_use_internal_errors(true);$x=simplexml_load_string($s,SimpleXMLElement::class,LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($old);if(!$x)fail('Estrutura Excel inválida.');$x->registerXPathNamespace('s','http://schemas.openxmlformats.org/spreadsheetml/2006/main');return $x;};
            $shared=[];$ss=$xml('xl/sharedStrings.xml');if($ss)foreach($ss->xpath('//s:si')as $si){$si->registerXPathNamespace('s','http://schemas.openxmlformats.org/spreadsheetml/2006/main');$shared[]=implode('',array_map(fn($n)=>(string)$n,$si->xpath('.//s:t')));}
            $book=$xml('xl/workbook.xml');$rels=$xml('xl/_rels/workbook.xml.rels');$sheetId=null;if($book){$sheets=$book->xpath('//s:sheet');$sheetId=isset($sheets[0])?(string)$sheets[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id']:null;}
            $target='xl/worksheets/sheet1.xml';if($rels&&$sheetId)foreach($rels->children()as $r)if((string)$r['Id']===$sheetId){if((string)$r['TargetMode']==='External')fail('A planilha não pode depender de arquivo externo.');$t=(string)$r['Target'];if(str_contains($t,'..')||str_contains($t,'\\'))fail('Caminho Excel inválido.');$target=str_starts_with($t,'/')?ltrim($t,'/'):'xl/'.$t;}
            $sheet=$xml($target);if(!$sheet)fail('A primeira aba da planilha não foi encontrada.');$rows=[];
            foreach($sheet->xpath('//s:sheetData/s:row')as $r){if(count($rows)>2001)fail('Importe até 2.000 colaboradores.');$row=[];foreach($r->children()as $c){$ref=(string)$c['r'];preg_match('/^[A-Z]+/',$ref,$m);$column=0;foreach(str_split($m[0]??'A')as $letter)$column=$column*26+ord($letter)-64;$column--;if($column>100)fail('Use uma planilha com até 100 colunas.');if(isset($c->f))fail('A planilha contém fórmulas. Cole somente os valores antes de importar.');$type=(string)$c['t'];$value=(string)$c->v;if($type==='s')$value=$shared[(int)$value]??'';if($type==='inlineStr'){$c->registerXPathNamespace('s','http://schemas.openxmlformats.org/spreadsheetml/2006/main');$value=implode('',array_map(fn($n)=>(string)$n,$c->xpath('.//s:t')));}$row[$column]=$value;}$rows[]=$row;}return $rows;
        }finally{if($opened)$zip->close();unlink($file);}
    }
    public function preview(array $body,int $userId):array{
        $rows=$this->parse($body);$token=bin2hex(random_bytes(24));$this->db->run('DELETE FROM import_previews WHERE expires<?',[time()]);$this->db->run('INSERT INTO import_previews(token_hash,user_id,rows_json,expires) VALUES(?,?,?,?)',[hash('sha256',$token),$userId,json_encode($rows,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),time()+1800]);
        $known=array_map(fn($s)=>$this->normalize($s['name']),$this->db->all('SELECT name FROM sectors'));$unknown=[];foreach($rows as $r)if($r['sector']!==''&&!in_array($this->normalize($r['sector']),$known,true))$unknown[$this->normalize($r['sector'])]=$r['sector'];
        return ['token'=>$token,'rows'=>$rows,'valid'=>count(array_filter($rows,fn($r)=>!$r['errors'])),'invalid'=>count(array_filter($rows,fn($r)=>(bool)$r['errors'])),'unknown_sectors'=>array_values($unknown)];
    }
    public function commit(array $body,int $userId):array{
        return $this->db->transaction(function()use($body,$userId){$hash=hash('sha256',text($body['token']??'',100));$preview=$this->db->one('SELECT * FROM import_previews WHERE token_hash=? AND user_id=? AND expires>?',[$hash,$userId,time()]);if(!$preview)fail('Prévia expirada ou já utilizada. Leia a planilha novamente.',409);$rows=json_decode($preview['rows_json'],true,512,JSON_THROW_ON_ERROR);$sectors=$this->db->all('SELECT id,name FROM sectors');$known=[];foreach($sectors as $s)$known[$this->normalize($s['name'])]=(int)$s['id'];$created=0;$ids=[];$skipped=0;
            foreach($rows as $r){if($r['errors']){$skipped++;continue;}if($r['cpf']!==''&&$this->db->one('SELECT id FROM employees WHERE cpf=?',[$r['cpf']]))fail('Um CPF foi cadastrado após a prévia. Leia o arquivo novamente.',409);if($r['cpf']===''){foreach($this->db->all('SELECT name FROM employees')as $existing)if($this->normalize($existing['name'])===$this->normalize($r['name']))fail('Um nome sem CPF já foi cadastrado. Leia o arquivo novamente.',409);}$sid=null;$key=$this->normalize($r['sector']);if($key!==''){if(isset($known[$key]))$sid=$known[$key];else{$mapping=$body['sector_map'][$r['sector']]??null;if($mapping==='create'){ $this->db->run('INSERT INTO sectors(name,responsible) VALUES(?,?)',[$r['sector'],'']);$sid=$this->db->id();$known[$key]=$sid;$created++;}elseif(is_numeric($mapping)&&$this->db->one('SELECT id FROM sectors WHERE id=?',[(int)$mapping])){$sid=(int)$mapping;$known[$key]=$sid;}else fail('Escolha um setor ou autorize criar: '.$r['sector']);}}
                $this->db->run('INSERT INTO employees(name,cpf,phone,sector_id,notes,email,role,punch_exempt) VALUES(?,?,?,?,?,?,?,?)',[$r['name'],$r['cpf']?:null,$r['phone'],$sid,$r['cpf']===''?'Importado com CPF pendente; completar o cadastro.':'',$r['email']??'',$r['role']??'',(int)($r['punch_exempt']??0)]);$ids[]=$this->db->id();}
            if(!$ids)fail('Não existem linhas válidas para importar.');$this->db->run('DELETE FROM import_previews WHERE token_hash=?',[$hash]);return ['imported'=>count($ids),'skipped'=>$skipped,'sectors_created'=>$created,'ids'=>$ids];});
    }
}
