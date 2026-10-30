<?php
declare(strict_types=1);
final class PeopleProgram {
    public const METRICS=['assiduidade'=>'Assiduidade (dias cumpridos / previstos)','pontualidade'=>'Pontualidade (compromissos pontuais / previstos)','planejamento'=>'Planejamento (entregas corretas no prazo / exigidas)','satisfacao'=>'Satisfação (respostas positivas / válidas)','formacoes'=>'Formações (participações / previstas)','metas'=>'Metas (entregas cumpridas / previstas)','projetos'=>'Projetos (marcos comprovados / previstos)','rematricula'=>'Rematrícula (confirmadas / carteira)'];
    public const GENERAL=[
        'requisitos'=>'Pré-requisitos específicos cumpridos integralmente',
        'vinculo'=>'Vínculo ativo no período e na concessão, ou exceção documentada pela gestão',
        'cultura'=>'Compromisso com normas, valores e cultura',
        'atribuicoes'=>'Atribuições e responsabilidades cumpridas',
        'pendencias'=>'Sem pendências nos critérios da premiação',
        'participacao'=>'Formações, reuniões e atividades exigidas para a função'
    ];
    public const PRIZES=[
        'aguia'=>['name'=>'Prêmio Águia','category'=>'Professores','reward'=>'Viagem internacional','criteria'=>[
            'presenca'=>'Pontualidade e assiduidade','planejamento'=>'Aplicativo e planejamento preenchidos corretamente e no prazo','satisfacao'=>'Relacionamento e satisfação de alunos e famílias','formacoes'=>'Participação assídua nas formações','projeto'=>'Projeto com potencial de divulgação positiva da escola']],
        'lobo'=>['name'=>'Prêmio Lobo','category'=>'Professores','reward'=>'Notebook','criteria'=>['rematricula'=>'100% de rematrícula da carteira/turma, segundo metodologia e registros oficiais']],
        'leao'=>['name'=>'Prêmio Leão','category'=>'Administrativos','reward'=>'Final de semana em hotel de cidade turística, com um acompanhante','criteria'=>[
            'assiduidade'=>'Assiduidade','pontualidade'=>'Pontualidade na jornada e responsabilidades','metas'=>'Cronograma, funções e metas da área','satisfacao'=>'Satisfação de alunos e famílias, quando aplicável','projeto'=>'Projeto com potencial de divulgação positiva da escola']]
    ];
    public const ORGANIZATION=[
        'organograma'=>['name'=>'Organograma','fields'=>['superior'=>'Reporta a (cargo / área)','responsavel'=>'Responsável','autoridade'=>'Autoridade e decisões delegadas','responsabilidades'=>'Responsabilidades']],
        'raci'=>['name'=>'Matriz RACI','fields'=>['executa'=>'R — Quem executa','aprova'=>'A — Quem responde pela entrega (um responsável)','consultados'=>'C — Quem deve ser consultado','informados'=>'I — Quem deve ser informado']],
        'cargos'=>['name'=>'Descrições de cargos','fields'=>['missao'=>'Missão do cargo','entregas'=>'Atribuições e entregas','requisitos'=>'Requisitos e competências','indicadores'=>'Indicadores de resultado']],
        'variavel'=>['name'=>'Remuneração variável','fields'=>['publico'=>'Público / cargos','metas'=>'Metas e indicadores','regra'=>'Regra de apuração e limites','periodo'=>'Período','aprovador'=>'Responsável pela aprovação']],
        'sucessao'=>['name'=>'Plano de sucessão','fields'=>['cargo'=>'Posição a suceder','responsavel'=>'Responsável pelo plano','preparacao'=>'Ações de desenvolvimento','prazo'=>'Prazo / próxima revisão','continuidade'=>'Plano de continuidade']],
        'lti'=>['name'=>'Partnership / LTI','fields'=>['objetivo'=>'Objetivo de longo prazo','publico'=>'Público / cargos','condicoes'=>'Condições e marcos','prazo'=>'Prazo','aprovador'=>'Responsável pela aprovação']]
    ];
    public static function checks(string $prize,array $input):array {
        if(!isset(self::PRIZES[$prize]))fail('Premiação inválida.');$out=[];
        foreach(self::GENERAL+self::PRIZES[$prize]['criteria'] as $key=>$label){
            $row=$input[$key]??[];if(!is_array($row))fail('Critério inválido.');
            $status=$row['status']??'Pendente';
            if(!in_array($status,['Pendente','Atende','Não atende','Não se aplica'],true))fail('Situação do critério inválida.');
            if($status==='Não se aplica'&&!($prize==='leao'&&$key==='satisfacao'))fail('Este critério é obrigatório.');
            $evidence=text($row['evidence']??'',2000);if($status!=='Pendente'&&!$evidence)fail('Registre a evidência ou justificativa de cada critério avaliado.');
            $out[$key]=['status'=>$status,'evidence'=>$evidence];
        }
        if($prize==='lobo'){
            $total=filter_var($input['total']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            $renewed=filter_var($input['renewed']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
            if($out['rematricula']['status']==='Atende'&&($total===false||$renewed===false||$renewed!==$total))fail('Para atender ao Lobo, informe carteira válida e 100% de rematrículas confirmadas.');
            if($renewed!==false&&$total!==false&&$renewed>$total)fail('Rematrículas não podem superar a carteira.');
            $out['total']=$total===false?null:$total;$out['renewed']=$renewed===false?null:$renewed;
        }
        return $out;
    }
}
