<?php
declare(strict_types=1);
final class PeopleController {
    public function __construct(private Database $db,private array $config,private mixed $audit){}
    private function ready():void{if(!$this->db->one('SELECT version FROM schema_migrations WHERE version=?',['009_aprovacao_privacidade']))fail('O administrador geral precisa aplicar as migrações em Administração do sistema para ativar este módulo.',503);}
    public function publicRoute(string $path,string $method,array $b,callable $throttle):void {
        if(!str_starts_with($path,'/api/talent-public'))return;
        $this->ready();
        $privacy=array_column($this->db->all('SELECT * FROM system_settings'),'setting_value','setting_key');
        if(($privacy['talent_enabled']??'0')!=='1'||empty($privacy['privacy_contact'])||empty($privacy['privacy_policy'])||empty($privacy['privacy_institution']))fail('O banco de talentos aguarda a configuração do aviso e do canal de privacidade pela instituição.',503);
        $privacy=array_intersect_key($privacy,array_flip(['privacy_institution','privacy_contact','privacy_policy','privacy_review_days']));ksort($privacy);$privacyVersion='talentos-'.substr(hash('sha256',json_encode($privacy)),0,16);
        if($path==='/api/talent-public'&&$method==='GET'){
            $_SESSION['talent_token']??=bin2hex(random_bytes(24));$_SESSION['talent_privacy']=$privacyVersion;
            jsonResponse(['token'=>$_SESSION['talent_token'],'consent_version'=>$privacyVersion,'privacy'=>array_intersect_key($privacy,array_flip(['privacy_institution','privacy_contact','privacy_policy','privacy_review_days']))]);
        }
        if($path==='/api/talent-public'&&$method==='POST'){
            $throttle('talent');
            if(!is_string($b['token']??null)||!hash_equals($_SESSION['talent_token']??'', $b['token'])||empty($_SESSION['talent_token']))fail('Atualize o formulário antes de enviar.',403);
            if(!empty($b['website']))fail('Envio inválido.');
            if(($_SESSION['talent_privacy']??'')!==$privacyVersion)fail('O aviso foi atualizado. Reabra o formulário antes de enviar.',409);
            if(($b['consent']??false)!==true)fail('Autorize o armazenamento dos dados e currículo para participar do banco de talentos.');
            $name=required($b['name']??null,'Nome');$email=text($b['email']??'',240);if(!filter_var($email,FILTER_VALIDATE_EMAIL))fail('E-mail inválido.');
            $phone=preg_replace('/\D/','',text($b['phone']??'',30));if(!preg_match('/^\d{10,13}$/',$phone))fail('Telefone inválido.');
            $area=required($b['area']??null,'Área de interesse');$city=required($b['city']??null,'Cidade');
            $bytes=is_string($b['base64']??null)?base64_decode($b['base64'],true):false;
            if(!$bytes||strlen($bytes)>5*1024*1024||!str_starts_with($bytes,'%PDF-'))fail('Envie um currículo em PDF de até 5 MB.');
            $original=preg_replace('/[\x00-\x1f\/\\\\]/','_',text($b['filename']??'curriculo.pdf',160));
            $stored=bin2hex(random_bytes(24));$file=$this->config['storage'].'/uploads/'.$stored;
            if(file_put_contents($file,$bytes,LOCK_EX)===false)fail('Não foi possível armazenar o currículo.',500);chmod($file,0600);
            try{$this->db->run('INSERT INTO talent_applications(name,email,phone,area,city,summary,original_name,stored_name,status,notes,consent_version,created,privacy_snapshot,review_due) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$name,$email,$phone,$area,$city,text($b['summary']??'',2000),$original,$stored,'Recebido','',$privacyVersion,nowUtc(),json_encode($privacy,JSON_UNESCAPED_UNICODE),(new DateTimeImmutable('today'))->modify('+'.(int)$privacy['privacy_review_days'].' days')->format('Y-m-d')]);$id=$this->db->id();}catch(Throwable $e){unlink($file);throw $e;}
            unset($_SESSION['talent_token']);jsonResponse(['protocol'=>'BT-'.str_pad((string)$id,6,'0',STR_PAD_LEFT)]);
        }
        fail('Não encontrado.',404);
    }
    public function managerRoute(string $path,string $method,array $b,array $user,callable $authorize):void {
        if(!str_starts_with($path,'/api/people'))return;
        $authorize();$this->ready();$db=$this->db;
        if($path==='/api/people'&&$method==='GET')jsonResponse([
            'approvers'=>$db->all("SELECT id,name FROM users WHERE active=1 AND role IN ('system_admin','rh_admin') ORDER BY name"),'general'=>PeopleProgram::GENERAL,'prizes'=>PeopleProgram::PRIZES,'organization'=>PeopleProgram::ORGANIZATION,
            'regulation'=>file_get_contents(dirname(__DIR__,2).'/resources/premiacoes.txt'),
            'cycles'=>$db->all('SELECT * FROM award_cycles ORDER BY start DESC,id DESC'),
            'reviews'=>$db->all('SELECT * FROM award_reviews ORDER BY id DESC'),
            'records'=>$db->all('SELECT * FROM organization_records ORDER BY kind,title'),
            'talents'=>$db->all('SELECT id,name,email,phone,area,city,summary,original_name,status,notes,consent_version,created,review_due FROM talent_applications ORDER BY id DESC')
        ]);
        if($path==='/api/people/cycles'&&$method==='POST'){
            [$start,$end]=period($b);$methodology=text($b['methodology']??'',8000);if(!$methodology)fail('Defina os índices, metas e metodologia de apuração antes de abrir o ciclo.');
            $db->run('INSERT INTO award_cycles(title,start,end,methodology) VALUES(?,?,?,?)',[required($b['title']??null,'Nome do ciclo'),$start,$end,$methodology]);$id=$db->id();($this->audit)('Ciclo de premiação criado','#'.$id);jsonResponse(['id'=>$id]);
        }
        if(preg_match('~^/api/people/reviews(?:/(\d+))?$~',$path,$m)&&in_array($method,['POST','PATCH'],true)){
            $id=isset($m[1])?(int)$m[1]:null;if(($id!==null)!==($method==='PATCH'))fail('Operação inválida.');
            $old=$id?$db->one('SELECT * FROM award_reviews WHERE id=?',[$id]):null;if($id&&!$old)fail('Avaliação não encontrada.',404);
            $cycle=$db->one('SELECT * FROM award_cycles WHERE id=?',[(int)($b['cycle_id']??0)]);
            $employee=$db->one('SELECT * FROM employees WHERE id=?',[(int)($b['employee_id']??0)]);
            if(!$cycle||!$employee)fail('Selecione ciclo e colaborador válidos.');
            $prize=$b['prize']??'';if(!is_array($b['checks']??null))fail('Informe a conferência dos critérios.');$checks=PeopleProgram::checks($prize,$b['checks']);
            $createdBy=(int)($old['created_by']??$user['id']);$reviewer=(int)($b['reviewer_id']??$old['reviewer_id']??0);
            if($reviewer&&!$db->one("SELECT id FROM users WHERE id=? AND active=1 AND role IN ('system_admin','rh_admin')",[$reviewer]))fail('Selecione um aprovador ativo da gestão.');
            if($reviewer===$createdBy)fail('O aprovador precisa ser outra pessoa da gestão.');
            $status=$b['status']??'Rascunho';if(!in_array($status,['Rascunho','Em validação','Validado','Não validado','Revogado','Entregue'],true))fail('Situação inválida.');
            if($old&&($old['cycle_id']!=$cycle['id']||$old['employee_id']!=$employee['id']||$old['prize']!==$prize))fail('Ciclo, colaborador e prêmio da avaliação não podem ser trocados.');
            if($old&&in_array($old['status'],['Validado','Entregue','Revogado'],true)&&in_array($status,['Rascunho','Em validação'],true))fail('Registre revisão formal ou revogação, preservando a validação.');
            if(in_array($status,['Validado','Não validado','Revogado','Entregue'],true)&&($createdBy===(int)$user['id']||$reviewer!==(int)$user['id']))fail('A decisão precisa ser registrada pelo aprovador designado, diferente de quem criou a avaliação.',403);
            $decision=text($b['decision']??'',2000);$validated=$old['validated']??null;$due=$old['due']??null;$delivered=null;
            if(in_array($status,['Validado','Entregue'],true)){
                if($cycle['end']>date('Y-m-d'))fail('A validação oficial ocorre após a conclusão do período de avaliação.');
                foreach(PeopleProgram::GENERAL+PeopleProgram::PRIZES[$prize]['criteria'] as $key=>$label)if(!in_array($checks[$key]['status'],['Atende','Não se aplica'],true))fail('Todos os requisitos devem ser comprovados cumulativamente antes da validação.');
                if(!$decision)fail('Registre a decisão da comissão, categoria funcional e análise de desempate quando houver.');
                if($employee['status']!=='Ativo'&&empty($b['exception']))fail('Colaborador inativo: registre a exceção deliberada pela gestão antes de validar.');
                if($employee['status']!=='Ativo')$decision.=' | Exceção de vínculo: '.required($b['exception'],'Exceção');
                if(!$validated){$validated=date('Y-m-d');$d=new DateTimeImmutable($validated);$target=$d->modify('first day of this month')->modify('+12 months');$day=min((int)$d->format('d'),(int)$target->format('t'));$due=$target->setDate((int)$target->format('Y'),(int)$target->format('m'),$day)->format('Y-m-d');}
                if($status==='Entregue'){$delivered=validDay($b['delivered']??null);if($delivered<$validated||$delivered>date('Y-m-d'))fail('A data da entrega deve estar entre a validação e hoje.');}
            }
            if(in_array($status,['Não validado','Revogado'],true)&&!$decision)fail('Registre a justificativa da decisão da gestão.');
            $values=[$cycle['id'],$employee['id'],$prize,$status,json_encode($checks,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$decision,$validated,$due,$delivered,nowUtc()];
            if($id)$db->run('UPDATE award_reviews SET cycle_id=?,employee_id=?,prize=?,status=?,checks=?,decision=?,validated=?,due=?,delivered=?,updated=? WHERE id=?',[...$values,$id]);
            else{$db->run('INSERT INTO award_reviews(cycle_id,employee_id,prize,status,checks,decision,validated,due,delivered,updated) VALUES(?,?,?,?,?,?,?,?,?,?)',$values);$id=$db->id();}
            $db->run('UPDATE award_reviews SET created_by=?,reviewer_id=? WHERE id=?',[$createdBy,$reviewer?:null,$id]);
            ($this->audit)('Avaliação de premiação registrada',json_encode(['id'=>$id,'created_by'=>$createdBy,'reviewer_id'=>$reviewer,'before_status'=>$old['status']??null,'status'=>$status,'previous_evidence_hash'=>$old?hash('sha256',$old['checks']):null,'evidence_hash'=>hash('sha256',json_encode($checks)),'decision'=>$decision,'validated'=>$validated,'due'=>$due,'delivered'=>$delivered],JSON_UNESCAPED_UNICODE));jsonResponse(['id'=>$id]);
        }
        if(preg_match('~^/api/people/records(?:/(\d+))?$~',$path,$m)&&in_array($method,['POST','PATCH'],true)){
            $id=isset($m[1])?(int)$m[1]:null;if(($id!==null)!==($method==='PATCH'))fail('Operação inválida.');
            $old=$id?$db->one('SELECT * FROM organization_records WHERE id=?',[$id]):null;if($id&&!$old)fail('Registro não encontrado.',404);
            $kind=$b['kind']??'';if(!isset(PeopleProgram::ORGANIZATION[$kind])||($old&&$old['kind']!==$kind))fail('Ferramenta inválida.');
            $details=[];foreach(PeopleProgram::ORGANIZATION[$kind]['fields'] as $key=>$label)$details[$key]=text($b['details'][$key]??'',2000);
            if($kind==='raci'&&(!$details['executa']||!$details['aprova']))fail('Informe quem executa e um responsável pela entrega.');
            $values=[required($b['title']??null,'Título'),json_encode($details,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),nowUtc()];
            if($id)$db->run('UPDATE organization_records SET title=?,details=?,updated=? WHERE id=?',[...$values,$id]);
            else{$db->run('INSERT INTO organization_records(title,details,updated,kind) VALUES(?,?,?,?)',[...$values,$kind]);$id=$db->id();}
            ($this->audit)('Organização atualizada',$kind.' #'.$id);jsonResponse(['id'=>$id]);
        }
        if(preg_match('~^/api/people/talents/(\d+)(/file)?$~',$path,$m)){
            $row=$db->one('SELECT * FROM talent_applications WHERE id=?',[(int)$m[1]]);if(!$row)fail('Cadastro não encontrado.',404);
            if(isset($m[2])&&$method==='GET'){
                ($this->audit)('Currículo consultado','BT-'.$row['id']);header('Content-Type: application/pdf');header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($row['original_name']));$file=$this->config['storage'].'/uploads/'.$row['stored_name'];if(!is_file($file))fail('Arquivo indisponível.',404);readfile($file);exit;
            }
            if(!isset($m[2])&&$method==='PATCH'){
                $status=$b['status']??'';if(!in_array($status,['Recebido','Em análise','Contato realizado','Arquivado'],true))fail('Situação inválida.');
                $db->run('UPDATE talent_applications SET status=?,notes=? WHERE id=?',[$status,text($b['notes']??'',2000),$row['id']]);($this->audit)('Banco de talentos atualizado','BT-'.$row['id'].' · '.$status);jsonResponse(['ok'=>true]);
            }
        }
        fail('Não encontrado.',404);
    }
}
