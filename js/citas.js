document.addEventListener('DOMContentLoaded', async () => {
  'use strict';
  const $ = id => document.getElementById(id);
  const S = {token:'',view:'reserva',step:1,patient:null,coverage:null,mode:null,sites:[],doctors:[],site:null,doctor:null,slot:null,booking:null,log:[],generation:0,slotsRevision:0,busy:false,identityBusy:false,coverageBusy:false,ready:false,appointments:[],receiptBusy:false};
  const specialtyNames = {medicina_general:'Medicina General',cardiologia:'Cardiología',pediatria:'Pediatría'};
  const money = n => new Intl.NumberFormat('es-PE',{style:'currency',currency:'PEN'}).format(Number(n));
  const clean = s => String(s || '').replace(/\s*\(prueba\)/gi,'').replace(/\s+Demo$/,'');
  const initials = s => String(s || '').trim().split(/\s+/).slice(0,2).map(v=>v[0]).join('').toUpperCase();
  const localToday = () => new Intl.DateTimeFormat('en-CA',{timeZone:'America/Lima',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
  const formatDate = (date, options={day:'numeric',month:'long'}) => date ? new Intl.DateTimeFormat('es-PE',{...options,timeZone:'UTC'}).format(new Date(date+'T12:00:00Z')) : 'Por elegir';
  function el(tag,text,cls) { const n=document.createElement(tag); if(text!==undefined)n.textContent=text; if(cls)n.className=cls; return n; }
  function feedback(id,message,type='info') { const n=$(id); n.textContent=message; n.className='feedback '+type; n.hidden=!message; }
  function global(message,error=false) { $('global-status').textContent=message; $('global-status').className='notice'+(error?' error':''); $('global-status').hidden=!message; }
  function setBusy(button,busy,text) { button.disabled=busy; if(busy){button.dataset.original=button.textContent;button.textContent=text || 'Consultando…';} else if(button.dataset.original){button.textContent=button.dataset.original;delete button.dataset.original;} }
  function lockSession() { S.ready=false; document.querySelectorAll('.step-panel button,.step-panel input,.step-panel select,.step-panel textarea').forEach(n=>{n.disabled=true;}); global('Tu sesión terminó. Vuelve a iniciar sesión para continuar.',true); const a=el('a',' Iniciar sesión');a.href='login.html';$('global-status').append(a); }
  function evidence(service,path,status,data,method,engine,trace) {
    S.log.unshift({service,path,status,data,method,engine,trace,time:new Date().toLocaleTimeString('es-PE')}); S.log=S.log.slice(0,35); renderLog();
  }
  function renderLog() {
    $('request-list').replaceChildren();
    S.log.forEach((v,i)=>{const b=el('button',undefined,'request-item');b.type='button';b.append(el('span',`${v.service} · ${v.method} · ${v.time}`),el('b',String(v.status)));b.addEventListener('click',()=>showEvidence(i));$('request-list').append(b);});
    if(S.log.length)showEvidence(0);
  }
  function showEvidence(i) {const v=S.log[i];$('evidence-title').textContent=`${v.service} · ${v.method} ${v.path}`;$('evidence-json').textContent=JSON.stringify({httpStatus:v.status,integracionESB:{motor:v.engine,idTraza:v.trace},...v.data},null,2);}
  async function request(path,{method='GET',body,service='Sistema',domain=false,timeout=16000}={}) {
    let r,d;
    try {const headers={};if(!['GET','HEAD'].includes(method.toUpperCase()))headers['X-CSRF-Token']=S.token;if(body)headers['Content-Type']='application/json';r=await fetch(window.ClinicaESB.url(path),{method,credentials:'same-origin',signal:AbortSignal.timeout(timeout),headers,...(body?{body:JSON.stringify(body)}:{})});d=await r.json();}
    catch (_) {throw Error('No pudimos conectar con el servicio. Intenta nuevamente.');}
    evidence(service,window.ClinicaESB.url(path),r.status,d,method,r.headers.get('X-ESB-Engine'),r.headers.get('X-ESB-Trace'));
    if(r.status===401)lockSession();
    if((!r.ok || !d.success) && !domain){const e=Error(d.message || 'No se pudo completar la operación.');e.status=r.status;e.data=d;throw e;}
    return {status:r.status,data:d};
  }
  function fullName() {return S.patient?[S.patient.nombres,S.patient.apellidoPaterno,S.patient.apellidoMaterno].join(' '):'';}
  function selectedDoctor(){return S.doctors.find(d=>String(d.id)===String(S.doctor));}
  function selectedSite(){return S.sites.find(d=>String(d.id)===String(S.site));}
  function amount(){return S.coverage && S.mode ? (S.mode==='seguro'?S.coverage.copago:S.coverage.tarifaParticular):null;}
  function row(label,value){const div=el('div');div.append(el('dt',label),el('dd',value));return div;}
  function summary(){
    const doctor=selectedDoctor(),site=selectedSite();
    $('booking-summary').replaceChildren(row('Paciente',fullName()||'Por identificar'),row('Especialidad',S.coverage?specialtyNames[S.coverage.tipoAtencion]:'Por elegir'),row('Modalidad',S.mode==='seguro'?'Convenio de salud':S.mode==='particular'?'Atención particular':'Por elegir'),row('Sede',site?clean(site.nombre):'Por elegir'),row('Profesional',doctor?clean(doctor.nombre):'Por elegir'),row('Fecha y hora',S.slot?`${formatDate($('appointment-date').value)} · ${S.slot}`:'Por elegir'));
    $('summary-amount').textContent=amount()===null?'—':money(amount());
    $('next-patient').disabled=!S.patient || !S.ready;
    $('next-coverage').disabled=!S.coverage || !S.mode || !S.ready;
    $('next-schedule').disabled=!S.slot || !S.site || !S.doctor || !S.ready;
  }
  function move(step){
    if(step>1&&!S.patient)return;
    if(step>2&&(!S.coverage||!S.mode))return;
    if(step>3&&(!S.slot||!S.site||!S.doctor))return;
    S.step=step;
    document.querySelectorAll('.step-panel').forEach((n,i)=>{n.hidden=i+1!==step;});
    document.querySelectorAll('[data-step]').forEach(n=>{const k=Number(n.dataset.step);n.className=k===step?'current':k<step?'done':'';if(k===step)n.setAttribute('aria-current','step');else n.removeAttribute('aria-current');});
    if(step===3){filterDoctors();renderSites();if(!S.slot)loadSlots();}
    if(step===4&&!S.booking)renderReview();
    summary();
  }
  function resetBooking(){
    S.generation++;S.patient=null;S.coverage=null;S.mode=null;S.site=null;S.doctor=null;S.slot=null;S.booking=null;
    $('document').value='';$('reason').value='';$('patient-card').hidden=true;$('coverage-options').hidden=true;
    ['patient-feedback','coverage-feedback','schedule-feedback','confirmation-feedback','receipt-feedback'].forEach(id=>feedback(id,''));
    $('review-content').hidden=false;$('booking-success').hidden=true;$('create-receipt').disabled=false;delete $('create-receipt').dataset.generated;$('create-receipt').textContent='Generar comprobante académico →';
    $('appointment-date').value='';move(1);summary();
  }
  async function view(name){
    S.view=name;document.querySelectorAll('.view').forEach(v=>v.hidden=v.id!=='view-'+name);
    document.querySelectorAll('.nav-item[data-view]').forEach(b=>{b.classList.toggle('active',b.dataset.view===name);if(b.dataset.view===name)b.setAttribute('aria-current','page');else b.removeAttribute('aria-current');});
    $('breadcrumb').textContent={reserva:'NUEVA CITA',citas:'MIS CITAS',documentos:'COMPROBANTES',tecnico:'EVIDENCIAS TÉCNICAS'}[name];
    try{if(name==='citas')await loadAppointments();if(name==='documentos')await loadReceipts();}catch(e){global(e.message,true);}
    if(name==='reserva')move(S.step);
  }
  document.querySelectorAll('[data-view]').forEach(b=>b.addEventListener('click',()=>{if(b.dataset.view==='reserva'&&S.booking)resetBooking();view(b.dataset.view);}));
  document.querySelectorAll('[data-go]').forEach(b=>b.addEventListener('click',()=>move(Number(b.dataset.go))));
  $('next-patient').addEventListener('click',()=>{$('coverage-patient').textContent=fullName();move(2);});
  $('next-coverage').addEventListener('click',()=>move(3));$('next-schedule').addEventListener('click',()=>move(4));
  $('new-booking').addEventListener('click',()=>{resetBooking();view('reserva');});
  ['alcance-open','footer-scope'].forEach(id=>$(id).addEventListener('click',()=>$('scope-dialog').showModal()));
  $('close-scope').addEventListener('click',()=>$('scope-dialog').close());
  function providerLabel(){ $('identity-source').textContent=$('provider').value==='factiliza'?'Proveedor: Factiliza · consulta externa por HTTPS':'Proveedor académico · datos sintéticos para pruebas'; }
  $('provider').addEventListener('change',()=>{resetBooking();providerLabel();global('Se inició una nueva reserva al cambiar el proveedor.');});
  $('use-demo').addEventListener('click',()=>{$('provider').value='academico';resetBooking();providerLabel();$('document').value='81000021';view('reserva');});
  $('document').addEventListener('input',()=>{S.generation++;S.patient=null;S.coverage=null;S.mode=null;S.slot=null;$('patient-card').hidden=true;summary();});
  $('patient-form').addEventListener('submit',async e=>{
    e.preventDefault();if(!S.ready||S.identityBusy)return;
    S.identityBusy=true;const generation=++S.generation;S.patient=null;S.coverage=null;S.mode=null;S.slot=null;$('patient-card').hidden=true;summary();
    const dni=$('document').value.trim(),provider=$('provider').value;setBusy($('lookup-patient'),true);feedback('patient-feedback','Consultando documento…');
    try{
      const {data}=await request('identidad.php',{method:'POST',body:{dni,proveedor:provider,escenario:'normal'},service:'S01 · Identidad'});
      if(generation!==S.generation)return;
      S.patient=data.datos;$('patient-name').textContent=fullName();$('patient-initials').textContent=initials(fullName());$('patient-document').textContent=`DNI ${dni} · ${provider==='factiliza'?'Factiliza':'Datos de pruebas'}`;$('patient-card').hidden=false;feedback('patient-feedback','');summary();
    }catch(err){if(generation===S.generation)feedback('patient-feedback',err.message,'error');}
    finally{S.identityBusy=false;setBusy($('lookup-patient'),false);summary();}
  });
  function clearCoverage(){S.coverage=null;S.mode=null;S.slot=null;$('coverage-options').hidden=true;feedback('coverage-feedback','');summary();}
  $('specialty').addEventListener('change',()=>{S.generation++;clearCoverage();});
  function renderModes(){
    const c=S.coverage;const target=$('coverage-options');target.replaceChildren();target.hidden=false;
    const modes=c.cobertura?[['seguro','Convenio de salud',c.copago,'Importe de copago'],['particular','Atención particular',c.tarifaParticular,'Tarifa completa']]:[['particular','Atención particular',c.tarifaParticular,'Tarifa completa']];
    modes.forEach(([id,title,price,note])=>{const b=el('button',undefined,'choice-card'+(S.mode===id?' selected':''));b.type='button';b.setAttribute('aria-pressed',String(S.mode===id));b.append(el('b',title),el('span',note),el('strong',money(price)),el('small',id==='seguro'?'Condiciones del convenio académico':'Sin aplicación de cobertura'));b.addEventListener('click',()=>{S.mode=id;renderModes();summary();});target.append(b);});
  }
  $('coverage-form').addEventListener('submit',async e=>{
    e.preventDefault();if(!S.ready||!S.patient||S.coverageBusy)return;
    S.coverageBusy=true;const generation=S.generation;clearCoverage();const tipo=$('specialty').value;setBusy($('lookup-coverage'),true);feedback('coverage-feedback','Consultando condiciones de atención…');
    try{
      const {status,data}=await request('seguros.php',{method:'POST',body:{dni:S.patient.dni,aseguradora:'ASEGURADORA_DEMO',tipoAtencion:tipo,escenario:'normal'},service:'S02 · Cobertura',domain:true});
      if(generation!==S.generation)return;
      const missing=status===404&&data.error?.codigo==='AFILIACION_FICTICIA_NO_ENCONTRADA';
      if((status!==200&&!missing)||(!data.success&&!missing)||!data.datos)throw Error(data.message || 'No pudimos determinar la cobertura.');
      S.coverage=data.datos;S.mode=S.coverage.cobertura?'seguro':'particular';
      const c=S.coverage;
      feedback('coverage-feedback',c.cobertura?`${c.plan} · cobertura disponible para ${specialtyNames[tipo]}.`:missing?'No hay una afiliación registrada en el convenio académico. Puedes continuar como particular.':c.resultado==='SEGURO_VENCIDO'?'La póliza de pruebas no está vigente. Puedes elegir atención particular.':'La especialidad no está incluida en este convenio. Puedes continuar como particular.',c.cobertura?'success':'info');
      renderModes();summary();
    }catch(err){if(generation===S.generation)feedback('coverage-feedback',err.message,'error');}
    finally{S.coverageBusy=false;setBusy($('lookup-coverage'),false);summary();}
  });
  function renderSites(){
    $('site-cards').replaceChildren();S.sites.forEach(site=>{const b=el('button',undefined,'site-card'+(String(S.site)===String(site.id)?' selected':''));b.type='button';b.setAttribute('aria-pressed',String(String(S.site)===String(site.id)));b.append(el('span','⌖','site-icon'),el('b',clean(site.nombre)),el('small','Ubicación de referencia'));b.addEventListener('click',()=>{S.site=site.id;renderSites();summary();if(mapa)mapa.setView([site.latitud,site.longitud],14);});$('site-cards').append(b);});
  }
  function filterDoctors(){
    const allowed=S.doctors.filter(d=>clean(d.especialidad).normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase()===specialtyNames[S.coverage.tipoAtencion].normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase());
    const old=S.doctor;$('doctor').replaceChildren();allowed.forEach(d=>$('doctor').add(new Option(clean(d.nombre),d.id)));
    S.doctor=allowed.some(d=>String(d.id)===String(old))?old:allowed[0]?.id || null;
    if(S.doctor)$('doctor').value=S.doctor;
  }
  $('doctor').addEventListener('change',()=>{S.doctor=$('doctor').value;S.slot=null;summary();loadSlots();});
  $('appointment-date').addEventListener('change',()=>{S.slot=null;summary();loadSlots();});
  async function loadSlots(){
    const rev=++S.slotsRevision;S.slot=null;summary();$('slots').replaceChildren();
    if(!S.doctor||!$('appointment-date').value){$('slots').append(el('p','Selecciona una fecha para consultar la agenda.','muted'));return;}
    $('slots').append(el('p','Consultando disponibilidad…','muted'));
    try{
      const {data}=await request('disponibilidad.php?'+new URLSearchParams({doctor_id:S.doctor,fecha:$('appointment-date').value}),{service:'S04 · Disponibilidad'});
      if(rev!==S.slotsRevision)return;$('slots').replaceChildren();
      if(!data.datos.horarios.length)$('slots').append(el('p','No quedan turnos. Elige otra fecha.','muted'));
      data.datos.horarios.forEach(h=>{const b=el('button',h);b.type='button';b.setAttribute('aria-label','Reservar horario '+h);b.addEventListener('click',()=>{S.slot=h;$('slots').querySelectorAll('button').forEach(v=>{v.classList.toggle('selected',v===b);v.setAttribute('aria-pressed',String(v===b));});summary();});$('slots').append(b);});
    }catch(e){if(rev===S.slotsRevision){$('slots').replaceChildren(el('p',e.message,'muted'));}}
  }
  function details(target){
    target.replaceChildren();
    [['Paciente',fullName()],['Especialidad',specialtyNames[S.coverage.tipoAtencion]],['Profesional',clean(selectedDoctor()?.nombre)],['Sede',clean(selectedSite()?.nombre)],['Fecha',formatDate($('appointment-date').value,{day:'numeric',month:'long',year:'numeric'})],['Hora',S.slot],['Modalidad',S.mode==='seguro'?'Convenio de salud':'Atención particular'],['Importe del paciente',money(amount())]].forEach(([k,v])=>{const d=el('div');d.append(el('span',k),el('b',v));target.append(d);});
  }
  function renderReview(){details($('review-details'));}
  $('confirm-booking').addEventListener('click',async()=>{
    if(S.busy||!S.ready||S.booking)return;
    if(!$('reason').value.trim()){move(3);feedback('schedule-feedback','Escribe un breve motivo de consulta para continuar.','error');$('reason').focus();return;}
    S.busy=true;setBusy($('confirm-booking'),true,'Registrando reserva…');feedback('confirmation-feedback','');
    try{
      const {data}=await request('admision.php',{method:'POST',service:'S04 · Reserva integrada',body:{doctor_id:Number(S.doctor),fecha:$('appointment-date').value,hora:S.slot,motivo:$('reason').value.trim(),idSede:Number(S.site),modalidad:S.mode}});
      S.booking=data.datos;$('booking-code').textContent=`Código ${S.booking.codigoReserva} · Conserva este número para tu atención.`;details($('success-details'));$('review-content').hidden=true;$('booking-success').hidden=false;await loadAppointments();global('');
    }catch(err){feedback('confirmation-feedback',err.message,'error');if(err.data?.error?.codigo==='HORARIO_NO_DISPONIBLE'){move(3);feedback('schedule-feedback','El turno acaba de ocuparse. Selecciona otro horario.','error');loadSlots();}}
    finally{S.busy=false;setBusy($('confirm-booking'),false);}
  });
  function empty(container,title,text){const box=el('div',undefined,'empty-state');box.append(el('h3',title),el('p',text));container.append(box);}
  async function loadAppointments(){
    const {data}=await request('obtener_citas.php',{service:'S04 · Mis citas'});S.appointments=data.datos;$('citas-count').textContent=String(data.datos.length);$('appointment-list').replaceChildren();
    if(!data.datos.length)empty($('appointment-list'),'Tu agenda está por comenzar','Cuando registres una cita, encontrarás sus detalles aquí.');
    data.datos.forEach(c=>{
      const card=el('article',undefined,'record-card');const day=el('div',String(Number(c.fecha.slice(-2))),'date-tile');day.append(el('small',formatDate(c.fecha,{month:'short'})));
      const content=el('div',undefined,'record-content');content.append(el('span','Reserva registrada','badge'),el('h3',clean(c.especialidad)),el('p',`${clean(c.medico)} · ${c.hora} h`),el('p',`${formatDate(c.fecha,{day:'numeric',month:'long',year:'numeric'})} · ${clean(S.sites.find(v=>String(v.id)===String(c.idSede))?.nombre)||'Sede no registrada'}`),el('p','Motivo: '+c.motivo));
      const side=el('div',undefined,'record-side');side.append(el('span','Cita #'+c.id));const b=el('button','Ver comprobantes →','text-link');b.type='button';b.addEventListener('click',()=>view('documentos'));side.append(document.createElement('br'),b);card.append(day,content,side);$('appointment-list').append(card);
    });
  }
  function receiptLabel(state){return {ACEPTADO_SIMULADO:'Validado en pruebas',PENDIENTE_SIMULADO:'Pendiente de envío',RECHAZADO_SIMULADO:'Requiere revisión'}[state] || state;}
  async function loadReceipts(){
    const {data}=await request('comprobantes.php',{service:'S05 · Mis comprobantes'});$('receipt-list').replaceChildren();
    if(!data.datos.length)empty($('receipt-list'),'Aún no hay comprobantes','Genera un documento académico después de confirmar tu cita.');
    data.datos.forEach(f=>{const card=el('article',undefined,'record-card');const icon=el('div','▤','date-tile');const content=el('div',undefined,'record-content');content.append(el('span',receiptLabel(f.estado),'badge'+(f.estado==='ACEPTADO_SIMULADO'?'':' pending')),el('h3',f.codigo),el('p',`Cita #${f.idCita} · ${clean(f.concepto)}`),el('p','Documento académico · sin validez tributaria'));const side=el('div',undefined,'record-side');side.append(el('strong',money(f.montoTotal)));
      if(f.estado!=='ACEPTADO_SIMULADO'){const b=el('button','Reenviar documento →','text-link');b.type='button';b.addEventListener('click',()=>receiptOperation({operacion:'reenviar',idComprobante:f.idComprobante,escenario:$('receipt-scenario').value}));side.append(b);}
      card.append(icon,content,side);$('receipt-list').append(card);
    });
  }
  async function receiptOperation(body){
    if(S.receiptBusy||!S.ready)return;S.receiptBusy=true;setBusy($('create-receipt'),true,'Validando documento…');
    try{const {status,data}=await request('comprobantes.php',{method:'POST',body,service:'S05 · Envío XML',domain:true});
      const f=data.datos;
      if(status===401||status===403)throw Error(data.message);
      if(f?.idComprobante){feedback('receipt-feedback',`${f.codigo} · ${receiptLabel(f.estado)} · ${money(f.montoTotal)}. Documento académico sin cobro.`,f.estado==='ACEPTADO_SIMULADO'?'success':'info');global(`${f.codigo}: ${receiptLabel(f.estado)}.`);if(S.booking&&Number(f.idCita)===Number(S.booking.idCita))$('create-receipt').dataset.generated='true';}
      else throw Error(data.message || 'No se pudo registrar el documento.');
      if(S.view==='documentos')await loadReceipts();
    }catch(e){feedback('receipt-feedback',e.message,'error');global(e.message,true);}
    finally{S.receiptBusy=false;setBusy($('create-receipt'),false);if($('create-receipt').dataset.generated==='true'){$('create-receipt').textContent='Documento registrado ✓';$('create-receipt').disabled=true;}}
  }
  $('create-receipt').addEventListener('click',()=>{if(S.booking)receiptOperation({operacion:'generar',idCita:S.booking.idCita,escenario:$('receipt-scenario').value});});
  $('refresh-receipts').addEventListener('click',async()=>{setBusy($('refresh-receipts'),true);try{await loadReceipts();}catch(e){global(e.message,true);}finally{setBusy($('refresh-receipts'),false);}});
  $('check-providers').addEventListener('click',async()=>{setBusy($('check-providers'),true);try{await request('estado_integraciones.php',{service:'Conexión HTTP'});global('Proveedor académico conectado correctamente.');}catch(e){global(e.message,true);}finally{setBusy($('check-providers'),false);}});
  let mapa=null,markers=[];
  $('map-toggle').addEventListener('click',()=>{
    $('map-area').hidden=!$('map-area').hidden;if($('map-area').hidden)return;
    if(!window.L){$('route-result').textContent='No se pudo cargar el mapa local.';return;}
    if(!mapa){mapa=L.map('clinic-map').setView([-12.08,-77.02],12);L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>'}).addTo(mapa);S.sites.forEach(site=>{const marker=L.circleMarker([site.latitud,site.longitud],{radius:8,color:'#106c61',fillOpacity:.8}).addTo(mapa);marker.bindTooltip(clean(site.nombre));marker.on('click',()=>{S.site=site.id;renderSites();summary();});markers.push(marker);});}
    mapa.invalidateSize();const site=selectedSite();if(site)mapa.setView([site.latitud,site.longitud],14);
  });
  $('route-form').addEventListener('submit',async e=>{
    e.preventDefault();if(!S.site){$('route-result').textContent='Selecciona una sede primero.';return;}const site=S.site;setBusy($('route-submit'),true,'Consultando ruta…');
    try{const {data}=await request('sedes.php?'+new URLSearchParams({id:site,latitud:$('route-lat').value,longitud:$('route-lon').value,ruta:'1'}),{service:'S03 · Ruta externa OSRM',timeout:12000});if(String(site)!==String(S.site))return;$('route-result').textContent=`Ruta vial: ${data.datos.distanciaKm} km · ${data.datos.duracionMinutos} min estimados. OSRM/FOSSGIS y OpenStreetMap. Sin tráfico en tiempo real.`;}
    catch(err){$('route-result').textContent=err.message;}finally{setBusy($('route-submit'),false);}
  });
  $('salir').addEventListener('click',async()=>{try{await request('cerrar_sesion.php',{method:'POST',service:'Sesión'});window.location.assign('index.html');}catch(e){global(e.message,true);}});
  try{
    document.querySelectorAll('#patient-form button,#coverage-form button').forEach(b=>b.disabled=true);
    const {data:session}=await request('sesion.php',{service:'Sesión'});S.token=session.csrfToken;if(!session.usuario){window.location.replace('login.html');return;}
    $('user-name').textContent=session.usuario.nombre;$('avatar').textContent=initials(session.usuario.nombre);$('provider').value=session.factilizaConfigurado?'factiliza':'academico';providerLabel();
    const {data:doctors}=await request('doctores.php',{service:'S04 · Profesionales'});const {data:sites}=await request('sedes.php',{service:'S03 · Sedes'});S.doctors=doctors.datos;S.sites=sites.datos;
    const today=localToday();$('appointment-date').min=today;const max=new Date(today+'T12:00:00Z');max.setUTCDate(max.getUTCDate()+90);$('appointment-date').max=max.toISOString().slice(0,10);
    await loadAppointments();S.ready=true;document.querySelectorAll('#patient-form button,#coverage-form button').forEach(b=>b.disabled=false);renderSites();summary();
  }catch(e){global(e.message,true);}
});
