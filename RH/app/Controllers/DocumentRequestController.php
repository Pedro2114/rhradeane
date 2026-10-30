<?php
declare(strict_types=1);
final class DocumentRequestController {
    public function __construct(private Database $db,private array $config,private mixed $audit){}
    private function ready():void{if(!$this->db->one('SELECT version FROM schema_migrations WHERE version=?',['008_solicitacoes_documentos']))fail('Aplique as migrações de solicitações de documentos.',503);}
    public function publicRoute(string $path,string $method,array $b,callable $throttle):void {
        if(!str_starts_with($path,'/api/doc-public'))return;$this->ready();
        if(!preg_match('~^/api/doc-public/([a-f0-9]{64})$~',$path,$m))fail('Link inválido.',404);
        $hash=hash('sha256',$m[1]);$request=$this->db->one('SELECT * FROM document_requests WHERE token_hash=? AND active=1 AND expires>=?',[$hash,date('Y-m-d')]);if(!$request)fail('Este link expirou ou foi cancelado. Solicite um novo link ao RH.',410);
        $items=$this->db->all('SELECT i.id,i.title,d.status,d.review FROM document_request_items i LEFT JOIN documents d ON d.id=i.document_id WHERE i.request_id=?',[$request['id']]);
        if($method==='GET'){$_SESSION['doc_tokens']??=[];if(count($_SESSION['doc_tokens'])>20)$_SESSION['doc_tokens']=[];$_SESSION['doc_tokens'][$hash]??=bin2hex(random_bytes(24));jsonResponse(['expires'=>$request['expires'],'items'=>$items,'csrf'=>$_SESSION['doc_tokens'][$hash]]);}
        if($method==='POST'){
            $throttle('doc-upload:'.$hash);if(!is_string($b['csrf']??null)||empty($_SESSION['doc_tokens'][$hash])||!hash_equals($_SESSION['doc_tokens'][$hash],$b['csrf']))fail('Atualize a página antes de enviar.',403);
            if(($b['consent']??false)!==true)fail('Confirme o envio para seu prontuário no RH.');$id=(int)($b['item_id']??0);$item=null;foreach($items as $r)if((int)$r['id']===$id)$item=$r;if(!$item)fail('Documento não solicitado.',403);
            if($item['status']&&!in_array($item['status'],['Pendente de correção','Recusado'],true))fail('Este documento já foi recebido. Aguarde a conferência do RH.',409);
            $bytes=is_string($b['base64']??null)?base64_decode($b['base64'],true):false;if(!$bytes||strlen($bytes)>5*1024*1024)fail('Arquivo inválido ou acima de 5 MB.');$mime=str_starts_with($bytes,'%PDF-')?'application/pdf':(str_starts_with($bytes,"\xff\xd8\xff")?'image/jpeg':(str_starts_with($bytes,"\x89PNG\r\n\x1a\n")?'image/png':''));if(!$mime)fail('Use PDF, JPG ou PNG.');
            $stored=bin2hex(random_bytes(24));$name=preg_replace('/[\x00-\x1f\/\\\\]/','_',text($b['filename']??'documento',160));$file=$this->config['storage'].'/uploads/'.$stored;if(file_put_contents($file,$bytes,LOCK_EX)===false)fail('Não foi possível salvar o documento.',500);chmod($file,0600);
            try{$this->db->transaction(function()use($request,$item,$id,$name,$stored,$mime){
                $this->db->run('UPDATE document_requests SET id=id WHERE id=?',[$request['id']]);$fresh=$this->db->one('SELECT * FROM document_requests WHERE id=?',[$request['id']]);if(!$fresh['active']||$fresh['expires']<date('Y-m-d'))fail('Link expirado ou cancelado.',410);
                $current=$this->db->one('SELECT d.status FROM document_request_items i LEFT JOIN documents d ON d.id=i.document_id WHERE i.id=?',[$id]);if($current['status']&&!in_array($current['status'],['Pendente de correção','Recusado'],true))fail('Documento já recebido.',409);
                $this->db->run('INSERT INTO documents(employee_id,category,start,end,notes,original_name,stored_name,mime,review,created) VALUES(?,?,?,?,?,?,?,?,?,?)',[$request['employee_id'],'Documento pessoal',date('Y-m-d'),date('Y-m-d'),'Solicitação #'.$request['id'].' · '.$item['title'],$name,$stored,$mime,'',nowUtc()]);$doc=$this->db->id();$this->db->run('UPDATE document_request_items SET document_id=? WHERE id=?',[$doc,$id]);($this->audit)('Documento recebido por link','Solicitação #'.$request['id'].' · documento RH-'.$doc);
            });}catch(Throwable $e){unlink($file);throw $e;}
            jsonResponse(['ok'=>true]);
        }
        fail('Método não permitido.',405);
    }
    public function managerRoute(string $path,string $method,array $b,callable $rh):void {
        if(!str_starts_with($path,'/api/doc-requests'))return;$rh();$this->ready();
        if($path==='/api/doc-requests'&&$method==='GET')jsonResponse(['requests'=>$this->db->all('SELECT id,employee_id,expires,active,created FROM document_requests ORDER BY id DESC'),'items'=>$this->db->all('SELECT i.*,d.status,d.review FROM document_request_items i LEFT JOIN documents d ON d.id=i.document_id ORDER BY i.id')]);
        if($path==='/api/doc-requests'&&$method==='POST'){
            $employee=(int)($b['employee_id']??0);if(!$this->db->one('SELECT id FROM employees WHERE id=?',[$employee]))fail('Colaborador inválido.');$expires=validDay($b['expires']??null);if($expires<date('Y-m-d')||$expires>(new DateTimeImmutable('today'))->modify('+7 days')->format('Y-m-d'))fail('Escolha validade de hoje até sete dias.');
            $items=$b['items']??[];if(!is_array($items)||count($items)<1||count($items)>15)fail('Solicite de 1 a 15 documentos.');$titles=[];foreach($items as $title){$v=text($title,180);if($v==='')fail('Informe o nome de cada documento.');$titles[]=$v;}if(count(array_unique($titles))!==count($titles))fail('Remova documentos repetidos.');$token=bin2hex(random_bytes(32));
            $id=$this->db->transaction(function()use($employee,$expires,$token,$titles){$this->db->run('INSERT INTO document_requests(employee_id,token_hash,expires,created) VALUES(?,?,?,?)',[$employee,hash('sha256',$token),$expires,nowUtc()]);$id=$this->db->id();foreach($titles as $title)$this->db->run('INSERT INTO document_request_items(request_id,title) VALUES(?,?)',[$id,$title]);return $id;});($this->audit)('Link de documentos criado','Solicitação #'.$id.' · colaborador #'.$employee);jsonResponse(['id'=>$id,'path'=>'/enviar-documentos/'.$token]);
        }
        if(preg_match('~^/api/doc-requests/(\d+)$~',$path,$m)&&$method==='PATCH'){
            if(!$this->db->one('SELECT id FROM document_requests WHERE id=?',[(int)$m[1]]))fail('Solicitação não encontrada.',404);$this->db->run('UPDATE document_requests SET active=0 WHERE id=?',[(int)$m[1]]);($this->audit)('Link de documentos cancelado','#'.$m[1]);jsonResponse(['ok'=>true]);
        }
        fail('Não encontrado.',404);
    }
}
