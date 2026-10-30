<?php
declare(strict_types=1);
final class PeopleReports {
    public function __construct(private Database $db){}
    public function generate(array $q):array {
        [$start,$end]=period($q);if((new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days>366)fail('Escolha um período de até 366 dias.');
        $type=$q['type'];$version=match($type){'awards','talents'=>'005_desenvolvimento_talentos','tasks'=>'007_tarefas_entregas',default=>'006_jornadas_indicadores'};
        if(!$this->db->one('SELECT version FROM schema_migrations WHERE version=?',[$version]))fail('Aplique as migrações deste módulo.',503);
        $employees=$this->db->all('SELECT e.*,s.name AS sector FROM employees e LEFT JOIN sectors s ON s.id=e.sector_id');$map=array_column($employees,null,'id');
        $accept=fn($id)=>isset($map[$id])&&(!(int)($q['employee_id']??0)||(int)$q['employee_id']===(int)$id)&&(!(int)($q['sector_id']??0)||(int)$q['sector_id']===(int)$map[$id]['sector_id']);
        $rows=[];$summary=[];$notice='Registros restritos ao RH. Períodos indicados em cada linha; não somar bases sobrepostas.';
        if($type==='indicators'){
            $title='Indicadores de premiação';$headers=['Colaborador','Setor','Indicador','Início','Fim','Resultado comprovado','Base prevista','Índice (%)','Meta (%)','Conferência','Evidência / metodologia'];
            foreach($this->db->all('SELECT * FROM performance_metrics WHERE start<=? AND end>=? ORDER BY employee_id,kind,start',[$end,$start]) as $r){if(!$accept($r['employee_id']))continue;$rate=100*(float)$r['actual']/(float)$r['expected'];$status=$r['target']===null?'Meta não definida':($rate>=(float)$r['target']?'Meta atingida':'Abaixo da meta');$rows[]=[$map[$r['employee_id']]['name'],$map[$r['employee_id']]['sector']??'',PeopleProgram::METRICS[$r['kind']],$r['start'],$r['end'],$r['actual'],$r['expected'],round($rate,2),$r['target']??'A definir',$status,$r['evidence']];$summary[$status]=($summary[$status]??0)+1;}
        }elseif($type==='awards'){
            $title='Conferência e concessões de premiações';$headers=['Colaborador','Setor','Ciclo','Prêmio','Situação','Critério','Conferência','Evidência','Validação','Concessão até','Entrega','Decisão da comissão'];
            $cycles=array_column($this->db->all('SELECT * FROM award_cycles WHERE start<=? AND end>=?',[$end,$start]),null,'id');
            foreach($this->db->all('SELECT * FROM award_reviews ORDER BY cycle_id,employee_id,prize') as $r){if(!$accept($r['employee_id'])||!isset($cycles[$r['cycle_id']]))continue;$checks=json_decode($r['checks'],true);foreach(PeopleProgram::GENERAL+PeopleProgram::PRIZES[$r['prize']]['criteria'] as $key=>$label)$rows[]=[$map[$r['employee_id']]['name'],$map[$r['employee_id']]['sector']??'',$cycles[$r['cycle_id']]['title'],PeopleProgram::PRIZES[$r['prize']]['name'],$r['status'],$label,$checks[$key]['status']??'Pendente',$checks[$key]['evidence']??'',$r['validated']??'',$r['due']??'',$r['delivered']??'',$r['decision']];$summary[$r['status']]=($summary[$r['status']]??0)+1;}
            $notice='Uma linha por critério. Resumo conta avaliações, não linhas. A validação e o desempate são decisões da comissão; não há concessão automática.';
        }elseif($type==='work'){
            $title='Jornadas e vigências';$headers=['Colaborador','Setor','Modelo','Tipo','Vigência inicial','Vigência final','Dia da semana','Entrada prevista','Saída prevista','Turma / atividade','Intervalo previsto (min/dia)','Hora-aula (min)','Observações'];
            foreach($this->db->all('SELECT * FROM work_assignments WHERE start<=? AND (end IS NULL OR end>=?) ORDER BY employee_id,start',[$end,$start]) as $r){if(!$accept($r['employee_id']))continue;$t=json_decode($r['snapshot'],true);$slots=json_decode($t['slots'],true)?:[['day'=>'','start'=>'','end'=>'','label'=>'']];foreach($slots as $slot)$rows[]=[$map[$r['employee_id']]['name'],$map[$r['employee_id']]['sector']??'',$t['title'],$t['kind'],$r['start'],$r['end']??'',is_numeric($slot['day'])?['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'][(int)$slot['day']]:'',$slot['start'],$slot['end'],$slot['label'],$t['break_minutes'],$t['lesson_minutes'],$t['notes']];$summary['Vínculos']=($summary['Vínculos']??0)+1;}
            $notice='Grade preservada por vigência. Intervalos são previstos por dia, não por bloco. Horas-aula não equivalem automaticamente a horas trabalhadas ou remuneração.';
        }elseif($type==='tasks'){
            $title='Cronograma de tarefas e entregas';$headers=['Tarefa','Solicitante','Responsável','Setor','Prazo','Situação','Dependência','Entregas','Última entrega','Anexos','Conferência'];$users=array_column($this->db->all('SELECT id,name,employee_id FROM users'),null,'id');
            foreach($this->db->all('SELECT * FROM team_tasks WHERE due>=? AND due<? ORDER BY due',[$start,(new DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d')]) as $r){$u=$users[$r['assignee_id']];if((!empty($q['employee_id'])||!empty($q['sector_id']))&&!$accept($u['employee_id']))continue;$deliveries=$this->db->all('SELECT * FROM task_deliveries WHERE task_id=? ORDER BY id',[$r['id']]);$latest=$deliveries?end($deliveries):null;$files=count(array_filter($deliveries,fn($d)=>$d['stored_name']!==''));$rows[]=[$r['title'],$users[$r['creator_id']]['name'],$u['name'],$map[$u['employee_id']]['sector']??'',$r['due'],$r['status'],$r['dependency_id']??'',count($deliveries),$latest['created']??'',$files,$r['review']];$summary[$r['status']]=($summary[$r['status']]??0)+1;}
            $notice='Filtrado pelo prazo da tarefa. Entregue aguarda conferência; conclusão não atribui nota, pagamento ou prêmio.';
        }else{
            if(!empty($q['employee_id'])||!empty($q['sector_id']))fail('Banco de talentos não tem vínculo com colaboradores ou setores. Limpe esses filtros.');
            $title='Banco de talentos';$headers=['Protocolo','Nome','Área de interesse','Cidade','E-mail','Telefone','Recebido','Situação','Autorização','Acompanhamento'];
            foreach($this->db->all('SELECT * FROM talent_applications WHERE created>=? AND created<? ORDER BY created',[$start,(new DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d')]) as $r){$rows[]=['BT-'.str_pad((string)$r['id'],6,'0',STR_PAD_LEFT),$r['name'],$r['area'],$r['city'],$r['email'],$r['phone'],$r['created'],$r['status'],$r['consent_version'],$r['notes']];$summary[$r['status']]=($summary[$r['status']]??0)+1;}
        }
        return ['title'=>$title,'start'=>$start,'end'=>$end,'headers'=>$headers,'rows'=>$rows,'summary'=>$summary,'notice'=>$notice];
    }
}
