<?php
declare(strict_types=1);

/** Fontes institucionais. Relatos familiares nunca são convertidos em notas. */
final class InstitutionalController {
    public const KINDS=['familias'=>'Atendimentos às famílias','projetos'=>'Projetos institucionais','formacoes'=>'Formações e certificados','planejamento'=>'Entregas de planejamento'];
    private const RESULTS=['Pendente','Comprovado','Não comprovado','Justificado'];
    public function __construct(private Database $db,private array $config,private mixed $audit){}
    private function allowed(string $kind,array $u):bool {
        return $u['role']==='system_admin'||($kind!=='familias'&&$u['role']==='rh_admin')||
            (bool)$this->db->one('SELECT user_id FROM institutional_access WHERE user_id=? AND kind=?',[$u['id'],$kind]);
    }
    private function authorize(string $kind,array $u):void {
        if(!isset(self::KINDS[$kind]))fail('Área inválida.');
        if(!$this->allowed($kind,$u))fail('Acesso restrito à equipe designada para esta área.',403);
    }
    private function event(int $id,array $u,string $action,int $revision,string $detail=''):void {
        $this->db->run('INSERT INTO institutional_events(record_id,actor_id,action,revision,detail,created) VALUES(?,?,?,?,?,?)',[$id,$u['id'],$action,$revision,$detail,nowUtc()]);
        // A auditoria geral não replica relatos de famílias, anexos ou evidências pessoais.
        ($this->audit)('Registro institucional · '.$action,'#'.$id.' · revisão '.$revision);
    }
    private function record(int $id,array $u):array {
        $r=$this->db->one('SELECT * FROM institutional_records WHERE id=?',[$id]);if(!$r)fail('Registro não encontrado.',404);
        $this->authorize($r['kind'],$u);return $r;
    }
    private function fields(string $kind,array $b):array {
        if(!is_array($b['payload']??null))fail('Preencha os campos do registro.');
        $keys=match($kind){
            'familias'=>['reference','nature','context','referral','response'],
            'projetos'=>['objective','outcome'],
            'formacoes'=>['objective','provider'],
            'planejamento'=>['objective','acceptance']
        };
        $p=[];foreach($keys as $key)$p[$key]=text($b['payload'][$key]??'',4000);
        if($kind==='familias'){
            if(!in_array($p['nature'],['Elogio','Preocupação','Dúvida'],true))fail('Classifique a manifestação.');
            if(!$p['reference']||!$p['context'])fail('Informe referência mínima e contexto do atendimento.');
            $related=($b['payload']['related_employee_id']??'')===''?null:(int)$b['payload']['related_employee_id'];
            if($related&&!$this->db->one('SELECT id FROM employees WHERE id=?',[$related]))fail('Colaborador relacionado inválido.');
            $p['related_employee_id']=$related;
        }elseif(!$p['objective'])fail('Descreva o objetivo e o que será acompanhado.');
        if($kind==='projetos'){
            $milestones=$b['payload']['milestones']??[];if(!is_array($milestones)||!array_is_list($milestones)||count($milestones)>30)fail('Use até 30 marcos.');
            $p['milestones']=[];
            foreach($milestones as $m){
                if(!is_array($m))fail('Marco inválido.');
                $p['milestones'][]=['title'=>required($m['title']??null,'Marco'),'due'=>validDay($m['due']??null),'completed'=>validDay($m['completed']??null,true),'evidence'=>text($m['evidence']??'',2000)];
                $last=end($p['milestones']);if($last['completed']&&($last['completed']>date('Y-m-d')||!$last['evidence']))fail('Marco concluído exige data não futura e evidência.');
            }
        }
        if(strlen(json_encode($p,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE))>60000)fail('O conteúdo ultrapassa o limite desta ficha. Resuma os marcos e use anexos para evidências extensas.');
        return $p;
    }
    private function members(string $kind,array $b):array {
        $members=$b['members']??[];if(!is_array($members)||!array_is_list($members)||count($members)>500)fail('Participantes inválidos.');
        if($kind==='familias'&&$members)fail('Atendimentos não usam medição por participantes.');
        $clean=[];$seen=[];
        foreach($members as $m){
            if(!is_array($m))fail('Participante inválido.');$id=(int)($m['employee_id']??0);
            if(isset($seen[$id])||!$this->db->one('SELECT id FROM employees WHERE id=?',[$id]))fail('Participante inválido ou duplicado.');$seen[$id]=true;
            $result=$m['result']??'Pendente';if(!in_array($result,self::RESULTS,true))fail('Resultado inválido.');
            $delivered=validDay($m['delivered']??null,true);if($delivered&&$delivered>date('Y-m-d'))fail('A comprovação não pode ter data futura.');
            $evidence=text($m['evidence']??'',2000);
            if($result!=='Pendente'&&!$evidence)fail('Cada resultado exige justificativa ou evidência.');
            if($result==='Comprovado'&&!$delivered)fail('Informe a data da entrega, participação ou conclusão comprovada.');
            $clean[]=['employee_id'=>$id,'result'=>$result,'delivered'=>$delivered,'evidence'=>$evidence];
        }
        return $clean;
    }
    public function dispatch(string $path,string $method,array $b,array $u):void {
        if(!str_starts_with($path,'/api/institutional'))return;
        if(!$this->db->one('SELECT version FROM schema_migrations WHERE version=?',['010_fontes_institucionais']))fail('Aplique a atualização de fontes institucionais em Administração do sistema.',503);
        $db=$this->db;
        if($path==='/api/institutional/access'){
            if($u['role']!=='system_admin')fail('Somente a direção administra estes acessos.',403);
            if($method==='GET')jsonResponse(['grants'=>$db->all('SELECT * FROM institutional_access'),'users'=>$db->all('SELECT id,name,role FROM users WHERE active=1 ORDER BY name'),'kinds'=>self::KINDS]);
            if($method==='POST'){
                $kind=text($b['kind']??'');$target=(int)($b['user_id']??0);if(!isset(self::KINDS[$kind])||!$db->one('SELECT id FROM users WHERE id=? AND active=1',[$target]))fail('Pessoa ou área inválida.');
                $db->transaction(function()use($db,$target,$kind,$b,$u){$db->run('DELETE FROM institutional_access WHERE user_id=? AND kind=?',[$target,$kind]);if(($b['enabled']??false)===true)$db->run('INSERT INTO institutional_access(user_id,kind,granted_by,created) VALUES(?,?,?,?)',[$target,$kind,$u['id'],nowUtc()]);});
                ($this->audit)('Acesso institucional atualizado','Usuário #'.$target.' · '.$kind.' · '.(($b['enabled']??false)?'habilitado':'revogado'));jsonResponse(['ok'=>true]);
            }
        }
        if($path==='/api/institutional/report'&&$method==='GET'){
            $kind=text($_GET['kind']??'');$this->authorize($kind,$u);[$start,$end]=period($_GET);
            if((new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days>366)fail('Escolha até 366 dias.');
            $rows=[];$users=array_column($db->all('SELECT id,name FROM users'),null,'id');$employees=array_column($db->all('SELECT id,name,sector_id FROM employees'),null,'id');
            foreach($db->all('SELECT * FROM institutional_records WHERE kind=? AND event_date>=? AND event_date<=? ORDER BY event_date,id',[$kind,$start,$end]) as $r){
                $payload=json_decode($r['payload'],true);
                if($kind==='familias'){
                    $related=$payload['related_employee_id'];if(!empty($_GET['employee_id'])&&(int)$related!==(int)$_GET['employee_id'])continue;
                    if(!empty($_GET['sector_id'])&&(int)($employees[$related]['sector_id']??0)!==(int)$_GET['sector_id'])continue;
                    $rows[]=['FI-'.$r['id'],$r['event_date'],$users[$r['owner_id']]['name']??'',$payload['nature'],$related?$employees[$related]['name']:'',$r['due']??'',$r['status'],$users[$r['reviewer_id']]['name']??''];
                }else foreach($db->all('SELECT * FROM institutional_members WHERE record_id=?',[$r['id']]) as $member){
                    $employee=$employees[$member['employee_id']];if(!empty($_GET['employee_id'])&&(int)$employee['id']!==(int)$_GET['employee_id'])continue;if(!empty($_GET['sector_id'])&&(int)$employee['sector_id']!==(int)$_GET['sector_id'])continue;
                    $rows[]=['FI-'.$r['id'],$r['event_date'],$r['title'],$employee['name'],$member['result'],$member['delivered']??'',$r['due']??'',$r['status'],$users[$r['reviewer_id']]['name']??''];
                }
            }
            $report=['title'=>self::KINDS[$kind],'start'=>$start,'end'=>$end,'headers'=>$kind==='familias'?['Protocolo','Data','Responsável','Natureza','Colaborador relacionado','Retorno até','Situação','Conferente']:['Protocolo','Data','Atividade','Participante','Comprovação','Data comprovada','Prazo','Situação da fonte','Conferente'],'rows'=>$rows,'notice'=>'Acesso por área. Manifestação familiar não é satisfação medida nem avaliação automática. Relatórios omitem relatos, identificação familiar e anexos. Fontes não validadas não devem compor indicadores.'];
            ($this->audit)('Relatório institucional consultado',$kind.' · '.$start.' a '.$end);
            if(($_GET['format']??'')==='csv'){header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="rh-fontes.csv"');echo ReportService::csv($report);exit;}jsonResponse($report);
        }
        if($path==='/api/institutional'&&$method==='GET'){
            $kinds=array_filter(self::KINDS,fn($name,$kind)=>$this->allowed($kind,$u),ARRAY_FILTER_USE_BOTH);
            $records=[];foreach(array_keys($kinds) as $kind)foreach($db->all('SELECT * FROM institutional_records WHERE kind=? ORDER BY event_date DESC,id DESC',[$kind]) as $r){
                $ids=$kind==='familias'?array_filter([json_decode($r['payload'],true)['related_employee_id']??null]):array_column($db->all('SELECT employee_id FROM institutional_members WHERE record_id=?',[$r['id']]),'employee_id');
                unset($r['payload'],$r['review']);$r['employee_ids']=array_values($ids);$records[]=$r;
            }
            $owners=[];foreach(array_keys($kinds) as $kind)$owners[$kind]=array_map(fn($person)=>['id'=>(int)$person['id'],'name'=>$person['name']],array_values(array_filter($db->all('SELECT id,name,role FROM users WHERE active=1 ORDER BY name'),fn($person)=>$this->allowed($kind,$person))));
            jsonResponse(['kinds'=>$kinds,'records'=>$records,'employees'=>$kinds?$db->all('SELECT id,name,sector_id FROM employees ORDER BY name'):[],'users'=>$kinds?$db->all('SELECT id,name FROM users WHERE active=1 ORDER BY name'):[],'owners'=>$owners]);
        }
        if($path==='/api/institutional/records'&&$method==='POST'){
            $kind=text($b['kind']??'');$this->authorize($kind,$u);$p=$this->fields($kind,$b);$members=$this->members($kind,$b);
            $day=validDay($b['event_date']??null);$due=validDay($b['due']??null,true);if($due&&$due<$day)fail('O prazo deve ser igual ou posterior à data do registro.');
            $owner=(int)($b['owner_id']??0);$ownerUser=$db->one('SELECT * FROM users WHERE id=? AND active=1',[$owner]);if(!$ownerUser||!$this->allowed($kind,$ownerUser))fail('O responsável precisa ter acesso à área.');
            $id=$db->transaction(function()use($db,$b,$kind,$p,$members,$day,$due,$owner,$u){
                $db->run('INSERT INTO institutional_records(kind,title,event_date,due,owner_id,creator_id,status,payload,review,created,updated) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$kind,mb_substr(required($b['title']??null,'Título'),0,180),$day,$due,$owner,$u['id'],'Rascunho',json_encode($p,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'',nowUtc(),nowUtc()]);$id=$db->id();
                foreach($members as $m)$db->run('INSERT INTO institutional_members(record_id,employee_id,result,delivered,evidence) VALUES(?,?,?,?,?)',[$id,...array_values($m)]);
                $this->event($id,$u,'Criado',1);return $id;
            });jsonResponse(['id'=>$id]);
        }
        if(preg_match('~^/api/institutional/records/(\d+)(/(review|files|metrics))?$~',$path,$m)){
            $r=$this->record((int)$m[1],$u);$id=(int)$r['id'];$op=$m[3]??'';
            if(!$op&&$method==='GET'){
                $this->event($id,$u,'Consultado',(int)$r['revision']);
                jsonResponse(['record'=>$r,'members'=>$db->all('SELECT * FROM institutional_members WHERE record_id=?',[$id]),'files'=>$db->all('SELECT id,original_name,mime,created FROM institutional_files WHERE record_id=?',[$id]),'events'=>$db->all('SELECT * FROM institutional_events WHERE record_id=? ORDER BY id DESC',[$id])]);
            }
            if(!$op&&$method==='PATCH'){
                $p=$this->fields($r['kind'],$b);$members=$this->members($r['kind'],$b);$day=validDay($b['event_date']??null);$due=validDay($b['due']??null,true);if($due&&$due<$day)fail('Prazo inválido.');
                $db->transaction(function()use($db,$r,$id,$b,$p,$members,$day,$due,$u){
                    $this->lock($id,$b,$u,true);
                    $db->run('UPDATE institutional_records SET title=?,event_date=?,due=?,payload=?,status=?,review=?,reviewer_id=NULL,revision=revision+1,updated=? WHERE id=?',[mb_substr(required($b['title']??null,'Título'),0,180),$day,$due,json_encode($p,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'Rascunho','',nowUtc(),$id]);
                    $db->run('DELETE FROM institutional_members WHERE record_id=?',[$id]);foreach($members as $member)$db->run('INSERT INTO institutional_members(record_id,employee_id,result,delivered,evidence) VALUES(?,?,?,?,?)',[$id,...array_values($member)]);
                    $this->event($id,$u,'Editado',(int)$r['revision']+1,'Nova revisão; exige nova conferência.');
                });jsonResponse(['ok'=>true]);
            }
            if($op==='review'&&$method==='POST'){
                $status=$b['status']??'';$review=text($b['review']??'',3000);
                if(!in_array($status,['Em conferência','Validado','Ajuste solicitado','Arquivado'],true))fail('Situação inválida.');
                $db->transaction(function()use($db,$id,$b,$u,$status,$review){
                    $current=$this->lock($id,$b,$u,false);$owner=(int)$current['owner_id']===(int)$u['id']||(int)$current['creator_id']===(int)$u['id'];
                    if($status==='Em conferência'){
                        if(!$owner&&$u['role']!=='system_admin')fail('Somente o autor ou responsável pode encaminhar.',403);
                        if(!in_array($current['status'],['Rascunho','Ajuste solicitado'],true))fail('Situação não permite encaminhar.');
                    }else{
                        if($owner)fail('A conferência deve ser feita por outra pessoa designada para a área.',403);
                        if(!$review)fail('Registre a conclusão e o contexto da conferência.');
                        if($status!=='Arquivado'&&$current['status']!=='Em conferência')fail('Encaminhe o registro para conferência antes de decidir.');
                        if($current['status']==='Arquivado')fail('Registro arquivado.');
                        if($status==='Validado')$this->validateCompletion($current);
                    }
                    $db->run('UPDATE institutional_records SET status=?,review=?,reviewer_id=?,revision=revision+1,updated=? WHERE id=?',[$status,$review,$status==='Em conferência'?null:$u['id'],nowUtc(),$id]);
                    $this->event($id,$u,$status,(int)$current['revision']+1,$review);
                });jsonResponse(['ok'=>true]);
            }
            if($op==='files'&&$method==='POST')$this->upload($id,$b,$u);
            if($op==='metrics'&&$method==='GET')jsonResponse($this->metric($r));
        }
        if(preg_match('~^/api/institutional/files/(\d+)$~',$path,$m)&&$method==='GET'){
            $f=$db->one('SELECT * FROM institutional_files WHERE id=?',[(int)$m[1]]);if(!$f)fail('Arquivo não encontrado.',404);$r=$this->record((int)$f['record_id'],$u);
            $file=$this->config['storage'].'/uploads/'.$f['stored_name'];if(!is_file($file))fail('Arquivo indisponível.',404);
            $this->event((int)$r['id'],$u,'Anexo consultado',(int)$r['revision'],'Anexo #'.$f['id']);header('Content-Type: '.$f['mime']);header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($f['original_name']));readfile($file);exit;
        }
        fail('Não encontrado.',404);
    }
    private function lock(int $id,array $b,array $u,bool $editing):array {
        $this->db->run('UPDATE institutional_records SET id=id WHERE id=?',[$id]);$r=$this->record($id,$u);
        if((int)($b['revision']??0)!==(int)$r['revision'])fail('O registro mudou. Abra novamente antes de salvar.',409);
        if($editing){
            if((int)$r['owner_id']!==(int)$u['id']&&(int)$r['creator_id']!==(int)$u['id']&&$u['role']!=='system_admin')fail('Somente autor, responsável ou direção pode editar.',403);
            if(!in_array($r['status'],['Rascunho','Ajuste solicitado'],true))fail('Registro em conferência ou encerrado não pode ser alterado.');
        }
        return $r;
    }
    private function validateCompletion(array $r):void {
        $p=json_decode($r['payload'],true);if($r['event_date']>date('Y-m-d'))fail('A data do atendimento ou atividade ainda é futura.');
        if($r['kind']==='familias'){if(!$p['referral']||!$p['response'])fail('Informe encaminhamento e retorno antes de encerrar o atendimento.');return;}
        $members=$this->db->all('SELECT * FROM institutional_members WHERE record_id=?',[$r['id']]);if(!$members)fail('Cadastre os participantes previstos.');
        foreach($members as $member)if($member['result']==='Pendente')fail('Confira todos os participantes antes de validar.');
        if($r['kind']==='planejamento'&&(!$r['due']||!$p['acceptance']))fail('Planejamento exige prazo e critérios de qualidade.');
        if($r['kind']==='projetos'){
            if(!$p['outcome']||!$p['milestones'])fail('Projetos exigem marcos e resultado institucional.');
            foreach($p['milestones'] as $m)if(!$m['completed']||!$m['evidence'])fail('Confira a conclusão e evidência dos marcos.');
        }
    }
    private function upload(int $id,array $b,array $u):never {
        $bytes=is_string($b['base64']??null)?base64_decode($b['base64'],true):false;if(!$bytes||strlen($bytes)>5*1024*1024)fail('Use arquivo de até 5 MB.');
        $mime=str_starts_with($bytes,'%PDF-')?'application/pdf':(str_starts_with($bytes,"\xff\xd8\xff")?'image/jpeg':(str_starts_with($bytes,"\x89PNG\r\n\x1a\n")?'image/png':''));if(!$mime)fail('Use PDF, JPG ou PNG.');
        $stored=bin2hex(random_bytes(24));$file=$this->config['storage'].'/uploads/'.$stored;$original=preg_replace('/[\x00-\x1f\/\\\\]/','_',text($b['filename']??'anexo',160));
        if(file_put_contents($file,$bytes,LOCK_EX)===false)fail('Não foi possível salvar o arquivo.',500);chmod($file,0600);
        try{$this->db->transaction(function()use($id,$b,$u,$stored,$original,$mime){
            $r=$this->lock($id,$b,$u,true);if((int)$this->db->one('SELECT COUNT(*) AS n FROM institutional_files WHERE record_id=?',[$id])['n']>=20)fail('Limite de 20 anexos por registro.');
            $this->db->run('INSERT INTO institutional_files(record_id,actor_id,original_name,stored_name,mime,created) VALUES(?,?,?,?,?,?)',[$id,$u['id'],$original,$stored,$mime,nowUtc()]);
            $this->db->run('UPDATE institutional_records SET revision=revision+1,updated=? WHERE id=?',[nowUtc(),$id]);$this->event($id,$u,'Anexo recebido',(int)$r['revision']+1);
        });}catch(Throwable $e){unlink($file);throw $e;}jsonResponse(['ok'=>true]);
    }
    private function metric(array $r):array {
        if($r['kind']==='familias')fail('Relatos familiares não geram nota ou indicador automático.');
        if($r['status']!=='Validado')fail('A fonte precisa ser validada por outra pessoa antes da apuração.');
        $rows=[];
        foreach($this->db->all('SELECT * FROM institutional_members WHERE record_id=?',[$r['id']]) as $m){
            $excluded=$m['result']==='Justificado';$actual=$m['result']==='Comprovado'&&($r['kind']!=='planejamento'||($r['due']&&$m['delivered']<=$r['due']));
            $rows[]=['employee_id'=>(int)$m['employee_id'],'actual'=>$excluded?0:(int)$actual,'expected'=>$excluded?0:1,'excluded'=>$excluded,'evidence'=>$m['evidence']];
        }
        return ['source'=>'institutional:'.$r['id'].':revision:'.$r['revision'],'kind'=>match($r['kind']){'planejamento'=>'planejamento','formacoes'=>'formacoes',default=>'projetos'},'date'=>$r['event_date'],'members'=>$rows,'notice'=>'Uma atividade por participante, conforme população validada. Justificados são excluídos explicitamente; não somar períodos sobrepostos nem transformar resultado em premiação automática.'];
    }
}
