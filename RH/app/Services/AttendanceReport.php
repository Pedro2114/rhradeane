<?php
declare(strict_types=1);
final class AttendanceReport {
    public function __construct(private Database $db){}
    public function generate(array $q):array{
        [$start,$end]=period($q);if((new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days>366)fail('Escolha até 366 dias.');$rows=[];
        $totals=['Dias com jornada prevista'=>0,'Dias com entrada registrada'=>0,'Dias sem entrada — conferir'=>0,'Entradas até o horário previsto'=>0,'Entradas após o horário previsto'=>0];
        $modern=(bool)$this->db->one('SELECT version FROM schema_migrations WHERE version=?',['006_jornadas_indicadores']);
        $employees=$this->db->all('SELECT e.*,s.name AS sector FROM employees e LEFT JOIN sectors s ON s.id=e.sector_id ORDER BY e.name');
        foreach($employees as $e){if(!empty($q['employee_id'])&&(int)$q['employee_id']!==(int)$e['id'])continue;if(!empty($q['sector_id'])&&(int)$q['sector_id']!==(int)$e['sector_id'])continue;
            if((int)($e['punch_exempt']??0)===1){$rows[]=[$e['name'],$e['sector']??'',$start.' a '.$end,'Dispensado de ponto','','','Sem apuração automática'];continue;}
            $legacy=$this->db->one('SELECT * FROM schedules WHERE employee_id=?',[$e['id']]);
            for($date=new DateTimeImmutable($start);$date->format('Y-m-d')<=$end&&$date->format('Y-m-d')<=date('Y-m-d');$date=$date->modify('+1 day')){
                $day=$date->format('Y-m-d');$weekday=(int)$date->format('w');$expected=null;$labels=[];
                $assignment=$modern?$this->db->one('SELECT * FROM work_assignments WHERE employee_id=? AND start<=? AND (end IS NULL OR end>=?) ORDER BY start DESC LIMIT 1',[$e['id'],$day,$day]):null;
                if($assignment){$template=json_decode($assignment['snapshot'],true);foreach(json_decode($template['slots'],true) as $slot)if((int)$slot['day']===$weekday){$expected=$expected===null?$slot['start']:min($expected,$slot['start']);$labels[]=$slot['start'].'–'.$slot['end'].($slot['label']?' · '.$slot['label']:'');}}
                elseif($legacy&&in_array($weekday,json_decode($legacy['days'],true),true)){$expected=$legacy['entry'];$labels[]=$legacy['entry'].'–'.$legacy['departure'].' (jornada atual)';}
                $lower=$date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s');$upper=$date->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s');$marks=$this->db->all('SELECT * FROM punches WHERE employee_id=? AND at>=? AND at<? ORDER BY at',[$e['id'],$lower,$upper]);$entry=null;foreach($marks as $mark)if($mark['kind']==='Entrada'){$entry=(new DateTimeImmutable($mark['at']))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('H:i');break;}
                if(!$expected&&!$marks)continue;$note=[];
                $leave=$this->db->one("SELECT id,kind FROM leaves WHERE employee_id=? AND start<=? AND end>=? AND status='Aprovado'",[$e['id'],$day,$day]);
                $documents=$this->db->all("SELECT category,status FROM documents WHERE employee_id=? AND start<=? AND end>=? AND category IN ('Atestado médico','Atestado odontológico','Declaração de comparecimento','Outro documento justificativo')",[$e['id'],$day,$day]);
                if($leave)$note[]=$leave['kind'].' aprovada; excluído desta base';
                elseif($expected){$totals['Dias com jornada prevista']++;if($entry){$totals['Dias com entrada registrada']++;$totals[$entry<=$expected?'Entradas até o horário previsto':'Entradas após o horário previsto']++;$note[]=$entry<=$expected?'Entrada até o horário previsto':'Entrada após o horário previsto — conferir';}else{$totals['Dias sem entrada — conferir']++;$note[]='Sem entrada registrada — conferir';}}
                else $note[]='Marcação sem jornada prevista — conferir';
                if($documents)$note[]='Há justificativa documental; conferir alcance e decisão do RH';
                if($marks&&count($marks)!==4)$note[]='Marcações incompletas ou adicionais';
                $rows[]=[$e['name'],$e['sector']??'',$day,implode(' / ',$labels),$entry??'Sem entrada',count($marks),implode('; ',$note)];
            }
        }
        return ['title'=>'Frequência — apuração preliminar do ponto','start'=>$start,'end'=>$end,'headers'=>['Colaborador','Setor','Dia','Grade prevista','Primeira entrada','Marcações','Conferência'],'rows'=>$rows,'summary'=>$totals,'notice'=>'Apuração automática preliminar dos registros. Férias/folgas/ausências aprovadas são excluídas da base; documentos justificativos pedem análise. Sem marcação não significa falta. Não considera automaticamente feriados, tolerâncias contratuais, jornada completa, trabalho externo ou qualidade da entrega. Não concede prêmios nem gera pagamentos. A gestão deve conferir a base e os critérios antes de validar indicadores.'];
    }
}
