export function createDocumentRequests(h){
  const {esc,field,select,btn,heading,table,pill,formEnd,openModal}=h;let state=null,error='',loading=false,owner='',publicCsrf='';
  const endpoint=()=>'/doc-public/'+location.pathname.split('/').pop();
  function clear(){state=null;error='';loading=false;}
  function page(){
    const key=(h.demo()?'demo':'real')+h.user().id;if(owner!==key){owner=key;clear();}
    if(!state&&!error&&!loading){loading=true;if(h.demo()){state={requests:[],items:[]};loading=false;}else h.api('/doc-requests').then(d=>{state=d;loading=false;h.render();}).catch(e=>{error=e.message;loading=false;h.render();});}
    return heading('Prontuário completo','Pendências documentais','Solicite o que falta e acompanhe recebimento e conferência.',btn('Solicitar documentos','doc-request'))+`<div class="note">O link permite somente os documentos solicitados, vale até sete dias e pode ser cancelado. Envie-o diretamente à pessoa correta. Recebido ainda precisa de conferência pelo RH.</div>${error?`<div class="card" role="alert">${esc(error)}</div>`:!state?'<p>Carregando…</p>':`<div class="card">${table(['Pessoa','Validade','Link','Documentos solicitados',''],state.requests.map(r=>`<tr><td><a href="#colaborador/${r.employee_id}">${esc(h.data().employees.find(e=>Number(e.id)===Number(r.employee_id))?.name)}</a></td><td>${esc(r.expires)}</td><td>${pill(!r.active?'Cancelado':r.expires<new Date().toLocaleDateString('en-CA')?'Expirado':'Ativo')}</td><td>${state.items.filter(i=>Number(i.request_id)===Number(r.id)).map(i=>`<p>${esc(i.title)} · ${pill(i.status==='Aceito'?'Conferido':i.status||'Pendente')}${i.document_id?` · <a href="/api/documents/${i.document_id}/file">Arquivo</a>`:''}</p>`).join('')}</td><td>${r.active?btn('Cancelar link','doc-revoke',r.id,'small','close'):''}</td></tr>`))}</div>`}`;
  }
  async function action(action,id){
    if(action==='doc-request'){
      openModal('Solicitar documentos',`<form class="form" data-form="doc-create">${select('Colaborador','employee_id','<option value="">Selecione</option>'+h.data().employees.map(e=>`<option value="${e.id}" ${Number(e.id)===Number(id)?'selected':''}>${esc(e.name)}</option>`).join(''))}${field('Link válido até (máximo sete dias)','expires','date')}<label>Documentos necessários — um por linha<textarea name="items" required maxlength="3000" placeholder="Carteira de trabalho&#10;Comprovante de residência"></textarea></label><p>Solicite somente documentos necessários ao prontuário. O envio entra na ficha da pessoa selecionada.</p>${formEnd('Gerar link')}</form>`);return true;
    }
    if(action==='doc-revoke'){if(h.demo())throw Error('Use uma conta real.');await h.api('/doc-requests/'+id,'PATCH',{});clear();h.render();h.toast('Link cancelado.');return true;}
    return false;
  }
  async function upload(file){if(!file?.size||file.size>5*1024*1024)throw Error('Selecione um arquivo de até 5 MB.');let binary='';const bytes=new Uint8Array(await file.arrayBuffer());for(let i=0;i<bytes.length;i+=8192)binary+=String.fromCharCode(...bytes.subarray(i,i+8192));return {filename:file.name,base64:btoa(binary)};}
  async function submit(type,form,b,fd){
    if(type==='doc-create'){
      if(h.demo())throw Error('Use uma conta real para criar um link.');const result=await h.api('/doc-requests','POST',{...b,items:b.items.split(/\r?\n/).map(s=>s.trim()).filter(Boolean)});const url=location.origin+result.path;
      clear();openModal('Link gerado',`<p>Copie e envie diretamente ao colaborador. O link permite enviar os documentos solicitados.</p><label>Link para envio<input readonly value="${esc(url)}"></label><p>Por segurança, o código completo só é exibido nesta ocasião. Se precisar, cancele esta solicitação e gere outra.</p><p><a href="${esc(result.path)}" target="_blank" rel="noopener noreferrer">Abrir página de envio</a></p>`);h.render();return true;
    }
    if(type==='doc-upload'){
      await h.api(endpoint(),'POST',{item_id:b.item_id,csrf:publicCsrf,consent:fd.has('consent'),...await upload(fd.get('file'))});await publicPage();h.toast('Documento recebido. Aguarde a conferência do RH.');return true;
    }
    return false;
  }
  async function publicPage(){
    const app=document.querySelector('#app');app.innerHTML='<main class="talent-public"><div class="card">Consultando solicitação…</div></main>';
    try{const d=await h.api(endpoint());publicCsrf=d.csrf;app.innerHTML=`<main class="talent-public"><section><img class="talent-logo" src="/assets/logo.png" alt="Radeane"><h1>Seus documentos, <br>no lugar certo.</h1><p>Envie apenas os arquivos solicitados pelo RH. Validade do link: ${esc(d.expires)}.</p><p>Esta página permite envios e não abre seu prontuário. Não compartilhe este link com outras pessoas.</p></section><section><h2>Documentos solicitados</h2>${d.items.map(i=>`<article class="card space-top"><h3>${esc(i.title)}</h3>${pill(i.status==='Aceito'?'Conferido pelo RH':i.status||'Pendente de envio')}${i.review?`<p>${esc(i.review)}</p>`:''}${!i.status||['Pendente de correção','Recusado'].includes(i.status)?`<form class="form space-top" data-form="doc-upload"><input name="item_id" type="hidden" value="${i.id}"><label>Arquivo PDF, JPG ou PNG (até 5 MB)<input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" required></label><label class="check"><input type="checkbox" name="consent" required>Confirmo o envio deste documento para meu prontuário no RH do Grupo Radeane.</label><div class="form-error" role="alert"></div><button class="btn primary" type="submit">Enviar documento</button></form>`:'<p>Aguarde a conferência. O RH poderá solicitar uma correção por aqui.</p>'}</article>`).join('')}</section></main>`;}catch(e){app.innerHTML=`<main class="talent-public"><div class="card"><h1>Envio de documentos</h1><p role="alert">${esc(e.message)}</p></div></main>`;}
  }
  return {page,action,submit,publicPage};
}
