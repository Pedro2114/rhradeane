<?php
declare(strict_types=1);
final class WorkController {
    public function __construct(private Database $db,private array $config,private mixed $audit){}
    private function ready(string $v):void{if(!$this->db->one('SELECT version FROM schema_migrations WHERE version=?',[$v]))fail('Aplique as migrações pendentes em Administração do sistema.',503);}
    public function dispatch(string $path,string $method,array $b,array $u,callable $rh):void {
        $db=$this->db;
        if(str_starts_with($path,'/api/work')){
            $rh();$this->ready('006_jornadas_indicadores');
            if($path==='/api/work'&&$method==='GET')jsonResponse(['templates'=>$db->all('SELECT * FROM work_templates ORDER BY title'),'assignments'=>$db->all('SELECT * FROM work_assignments ORDER BY start DESC'),'metrics'=>$db->all('SELECT * FROM performance_metrics ORDER BY start DESC'),'kinds'=>PeopleProgram::METRICS]);
            if($path==='/api/work/templates'&&$method==='POST'){
                $kind=$b['kind']??'';if(!in_array($kind,['Fixa','Sem horário fixo','Hora-aula'],true))fail('Tipo de jornada inválido.');
                $lesson=filter_var($b['lesson_minutes']??50,FILTER_VALIDATE_INT);$break=filter_var($b['break_minutes']??0,FILTER_VALIDATE_INT);
                if($lesson===false||$lesson<1||$lesson>240||$break===false||$break<0||$break>240)fail('Duração inválida.');
                $slots=$b['slots']??[];if(!is_array($slots)||count($slots)>100)fail('Grade inválida.');$clean=[];$byDay=[];
                foreach($slots as $s){if(!is_array($s))fail('Bloco inválido.');$day=filter_var($s['day']??null,FILTER_VALIDATE_INT);$start=$s['start']??'';$end=$s['end']??'';if($day===false||$day<0||$day>6||!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$start)||!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$end)||$start>=$end)fail('Cada bloco deve ter dia e horários crescentes no mesmo dia.');
                    foreach($byDay[$day]??[] as $other)if($start<$other['end']&&$end>$other['start'])fail('Há blocos sobrepostos na grade.');
                    $row=['day'=>$day,'start'=>$start,'end'=>$end,'label'=>text($s['label']??'',180)];$clean[]=$row;$byDay[$day][]=$row;
                }
                if($kind==='Sem horário fixo'&&$clean)fail('Jornada sem horário fixo não usa grade.');if($kind!=='Sem horário fixo'&&!$clean)fail('Adicione os dias e blocos da jornada.');
                foreach($byDay as $rows){$minutes=0;foreach($rows as $s)$minutes+=(int)substr($s['end'],0,2)*60+(int)substr($s['end'],3)-((int)substr($s['start'],0,2)*60+(int)substr($s['start'],3));if($break>=$minutes)fail('O intervalo deve ser menor que o tempo previsto no dia.');}
                $db->run('INSERT INTO work_templates(title,kind,slots,break_minutes,lesson_minutes,notes) VALUES(?,?,?,?,?,?)',[required($b['title']??null,'Nome'),$kind,json_encode($clean,JSON_UNESCAPED_UNICODE),$break,$lesson,text($b['notes']??'',2000)]);$id=$db->id();($this->audit)('Modelo de jornada criado','#'.$id);jsonResponse(['id'=>$id]);
            }
            if($path==='/api/work/assignments'&&$method==='POST'){
                $employee=(int)($b['employee_id']??0);if(!$db->one('SELECT id FROM employees WHERE id=?',[$employee]))fail('Colaborador inválido.');$template=$db->one('SELECT * FROM work_templates WHERE id=?',[(int)($b['template_id']??0)]);if(!$template)fail('Modelo inválido.');$start=validDay($b['start']??null);$end=validDay($b['end']??null,true);if($end&&$end<$start)fail('Vigência inválida.');
                $db->transaction(function()use($db,$employee,$template,$start,$end,$b){
                    // Serializa atribuições por colaborador também no MySQL.
                    $db->run('UPDATE employees SET id=id WHERE id=?',[$employee]);
                    $active=$db->all('SELECT * FROM work_assignments WHERE employee_id=? AND start<=? AND (end IS NULL OR end>=?)',[$employee,$end??'9999-12-31',$start]);
                    foreach($active as $row){if(empty($b['replace_previous'])||$row['start']>=$start||($row['end']&&$row['end']<$start))fail('Existe jornada nessa vigência. Autorize encerrar a anterior ou ajuste as datas.');$db->run('UPDATE work_assignments SET end=? WHERE id=?',[(new DateTimeImmutable($start))->modify('-1 day')->format('Y-m-d'),$row['id']]);}
                    $db->run('INSERT INTO work_assignments(employee_id,template_id,start,end,snapshot) VALUES(?,?,?,?,?)',[$employee,$template['id'],$start,$end,json_encode($template,JSON_UNESCAPED_UNICODE)]);
                });($this->audit)('Jornada vinculada','Colaborador #'.$employee.' · '.$start);jsonResponse(['ok'=>true]);
            }
            if(preg_match('~^/api/work/metrics(?:/(\d+))?$~',$path,$m)&&in_array($method,['POST','PATCH'],true)){
                $id=isset($m[1])?(int)$m[1]:null;if(($id!==null)!==($method==='PATCH'))fail('Operação inválida.');if($id&&!$db->one('SELECT id FROM performance_metrics WHERE id=?',[$id]))fail('Indicador não encontrado.',404);
                $employee=(int)($b['employee_id']??0);$kind=$b['kind']??'';if(!$db->one('SELECT id FROM employees WHERE id=?',[$employee])||!isset(PeopleProgram::METRICS[$kind]))fail('Colaborador ou indicador inválido.');[$start,$end]=period($b);
                foreach(['actual','expected'] as $k)if(!is_numeric($b[$k]??null)||!is_finite((float)$b[$k])||(float)$b[$k]<0||(float)$b[$k]>1000000000)fail('Resultados devem ser números não negativos.');
                if((float)$b['expected']<=0||(float)$b['actual']>(float)$b['expected'])fail('Informe uma base maior que zero e resultado até o total previsto.');
                $target=($b['target']??'')===''?null:$b['target'];if($target!==null&&(!is_numeric($target)||!is_finite((float)$target)||(float)$target<0||(float)$target>100))fail('Meta percentual inválida.');
                $evidence=text($b['evidence']??'',4000);if(!$evidence)fail('Informe os registros oficiais e a metodologia usados na medição.');
                $values=[$employee,$kind,$start,$end,$b['actual'],$b['expected'],$target,$evidence,nowUtc()];
                if($id)$db->run('UPDATE performance_metrics SET employee_id=?,kind=?,start=?,end=?,actual=?,expected=?,target=?,evidence=?,updated=? WHERE id=?',[...$values,$id]);else{$db->run('INSERT INTO performance_metrics(employee_id,kind,start,end,actual,expected,target,evidence,updated) VALUES(?,?,?,?,?,?,?,?,?)',$values);$id=$db->id();}
                ($this->audit)('Indicador registrado',json_encode(['id'=>$id,'data'=>$values],JSON_UNESCAPED_UNICODE));jsonResponse(['id'=>$id]);
            }
            fail('Não encontrado.',404);
        }
        if(!str_starts_with($path,'/api/team'))return;$this->ready('007_tarefas_entregas');
        $manager=in_array($u['role'],['system_admin','rh_admin'],true);
        $visible=function(array $task)use($manager,$u){if(!$manager&&(int)$task['creator_id']!==(int)$u['id']&&(int)$task['assignee_id']!==(int)$u['id'])fail('Tarefa restrita aos participantes e RH.',403);};
        if($path==='/api/team'&&$method==='GET'){
            $tasks=$db->all('SELECT * FROM team_tasks'.($manager?'':' WHERE creator_id=? OR assignee_id=?').' ORDER BY due,id',$manager?[]:[$u['id'],$u['id']]);$deliveries=[];foreach($tasks as $task)$deliveries=[...$deliveries,...$db->all('SELECT id,task_id,actor_id,notes,original_name,mime,created FROM task_deliveries WHERE task_id=? ORDER BY id',[$task['id']])];
            jsonResponse(['tasks'=>$tasks,'deliveries'=>$deliveries,'people'=>$db->all('SELECT id,name FROM users WHERE active=1 ORDER BY name')]);
        }
        if($path==='/api/team/tasks'&&$method==='POST'){
            $assignee=$db->one('SELECT id FROM users WHERE id=? AND active=1',[(int)($b['assignee_id']??0)]);if(!$assignee)fail('Responsável inválido.');
            $d=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',text($b['due']??'',16));if(!$d||$d->format('Y-m-d\TH:i')!==($b['due']??''))fail('Prazo inválido.');$dependency=null;
            if(!empty($b['dependency_id'])){$parent=$db->one('SELECT * FROM team_tasks WHERE id=?',[(int)$b['dependency_id']]);if(!$parent)fail('Dependência inválida.');$visible($parent);$dependency=$parent['id'];}
            $db->run('INSERT INTO team_tasks(title,description,creator_id,assignee_id,due,dependency_id,status,review,created,updated) VALUES(?,?,?,?,?,?,?,?,?,?)',[required($b['title']??null,'Tarefa'),text($b['description']??'',4000),$u['id'],$assignee['id'],$d->format('Y-m-d\TH:i'),$dependency,'A fazer','',nowUtc(),nowUtc()]);$id=$db->id();($this->audit)('Tarefa criada','#'.$id);jsonResponse(['id'=>$id]);
        }
        if(preg_match('~^/api/team/tasks/(\d+)(/deliveries)?$~',$path,$m)){
            $task=$db->one('SELECT * FROM team_tasks WHERE id=?',[(int)$m[1]]);if(!$task)fail('Tarefa não encontrada.',404);$visible($task);
            $executor=$manager||(int)$task['assignee_id']===(int)$u['id'];$reviewer=$manager||(int)$task['creator_id']===(int)$u['id'];
            if(isset($m[2])&&$method==='POST'){
                if(!$executor)fail('Somente o responsável ou RH pode entregar.',403);if(in_array($task['status'],['Concluída','Cancelada'],true))fail('Tarefa encerrada.');
                if($task['dependency_id']&&$db->one('SELECT status FROM team_tasks WHERE id=?',[$task['dependency_id']])['status']!=='Concluída')fail('A entrega anterior ainda precisa ser concluída.');
                $notes=text($b['notes']??'',4000);$stored='';$mime='';$original='';$file=null;
                if(!empty($b['base64'])){$bytes=is_string($b['base64'])?base64_decode($b['base64'],true):false;if(!$bytes||strlen($bytes)>5*1024*1024)fail('Anexo inválido ou acima de 5 MB.');$mime=str_starts_with($bytes,'%PDF-')?'application/pdf':(str_starts_with($bytes,"\xff\xd8\xff")?'image/jpeg':(str_starts_with($bytes,"\x89PNG\r\n\x1a\n")?'image/png':''));if(!$mime)fail('Use PDF, JPG ou PNG.');$stored=bin2hex(random_bytes(24));$original=preg_replace('/[\x00-\x1f\/\\\\]/','_',text($b['filename']??'anexo',160));$file=$this->config['storage'].'/uploads/'.$stored;if(file_put_contents($file,$bytes,LOCK_EX)===false)fail('Não foi possível salvar o anexo.',500);chmod($file,0600);}
                if(!$notes&&!$stored)fail('Descreva a entrega ou anexe um arquivo.');
                try{$db->transaction(function()use($db,$task,$u,$notes,$original,$stored,$mime){$db->run('INSERT INTO task_deliveries(task_id,actor_id,notes,original_name,stored_name,mime,created) VALUES(?,?,?,?,?,?,?)',[$task['id'],$u['id'],$notes,$original,$stored,$mime,nowUtc()]);$db->run('UPDATE team_tasks SET status=?,updated=? WHERE id=?',['Entregue',nowUtc(),$task['id']]);});}catch(Throwable $e){if($file)unlink($file);throw $e;}($this->audit)('Tarefa entregue','#'.$task['id']);jsonResponse(['ok'=>true]);
            }
            if(!isset($m[2])&&$method==='PATCH'){
                $status=$b['status']??'';$review=text($b['review']??'',2000);
                if($status==='Em andamento'){if(!$executor)fail('Somente o responsável pode iniciar.',403);if(!in_array($task['status'],['A fazer','Ajuste solicitado'],true))fail('Situação não permite iniciar.');}
                elseif(in_array($status,['Concluída','Ajuste solicitado','Cancelada'],true)){if(!$reviewer)fail('Somente o solicitante ou RH pode conferir.',403);if($status!=='Cancelada'&&$task['status']!=='Entregue')fail('A tarefa precisa ser entregue antes da conferência.');if($status!=='Concluída'&&!$review)fail('Registre o motivo.');}
                else fail('Situação inválida.');
                $db->run('UPDATE team_tasks SET status=?,review=?,updated=? WHERE id=?',[$status,$review,nowUtc(),$task['id']]);($this->audit)('Tarefa atualizada','#'.$task['id'].' · '.$status);jsonResponse(['ok'=>true]);
            }
        }
        if(preg_match('~^/api/team/deliveries/(\d+)/file$~',$path,$m)&&$method==='GET'){
            $row=$db->one('SELECT * FROM task_deliveries WHERE id=?',[(int)$m[1]]);if(!$row||!$row['stored_name'])fail('Anexo não encontrado.',404);$visible($db->one('SELECT * FROM team_tasks WHERE id=?',[$row['task_id']]));$file=$this->config['storage'].'/uploads/'.$row['stored_name'];if(!is_file($file))fail('Arquivo indisponível.',404);($this->audit)('Anexo de tarefa consultado','#'.$row['id']);header('Content-Type: '.$row['mime']);header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($row['original_name']));readfile($file);exit;
        }
        fail('Não encontrado.',404);
    }
}
