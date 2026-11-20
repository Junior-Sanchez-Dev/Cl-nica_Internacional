// Prueba DOM del flujo, no una prueba visual de navegador. Requiere ../ui-check/node_modules/jsdom.
const {JSDOM}=require('../../ui-check/node_modules/jsdom');
const fs=require('fs');const {execFileSync}=require('child_process');
const assert=require('node:assert/strict');
const BASE=process.env.CLINICA_BASE || 'https://localhost:8443/';let cookie='';
const email=`ui-${Date.now()}@example.test`;
const testDni=process.env.UI_DNI || '81000021'; const expectedAmount=testDni==='81000021'?30:100;
async function http(path,options={}) {
  const headers=new Headers(options.headers||{});if(cookie)headers.set('Cookie',cookie);
  const target=new URL(path,BASE);
  if(target.pathname.startsWith('/php/')&&!target.pathname.endsWith('/esb.php')){const op=target.pathname.split('/').pop().replace(/\.php$/,'');target.pathname='/php/esb.php';target.searchParams.set('servicio',op);}
  const r=await fetch(target,{...options,headers});
  assert.equal(r.headers.get('x-esb-engine'),'Apache-Synapse-3.0.2','La respuesta debe proceder de Synapse');
  assert.ok(r.headers.get('x-esb-trace'),'Falta traza del bus');
  const all=r.headers.getSetCookie();if(all.length)cookie=all[0].split(';')[0];return r;
}
async function json(path,body,token){const r=await http(path,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':token},body:JSON.stringify(body)});assert.ok(r.ok,await r.clone().text());return r.json();}
async function until(fn,label){const end=Date.now()+7000;while(Date.now()<end){if(fn())return;await new Promise(r=>setTimeout(r,25));}throw Error('Espera agotada: '+label);}
(async()=>{
 try {
  const session=await(await http('php/sesion.php')).json();
  await json('php/procesar_registro.php',{nombre:'Prueba UI',apellido:'Academica',email,password:'Prueba-UI-2026!'},session.csrfToken);
  await json('php/procesar_login.php',{email,password:'Prueba-UI-2026!'},session.csrfToken);
  const dom=new JSDOM(fs.readFileSync('citas.html','utf8'),{url:BASE+'citas.html',runScripts:'outside-only'});
  const w=dom.window,d=w.document;w.fetch=http;w.AbortSignal=AbortSignal;w.Headers=Headers;
  w.eval(fs.readFileSync('js/esb-client.js','utf8'));
  w.eval(fs.readFileSync('js/citas.js','utf8'));
  const $=id=>d.getElementById(id);
  await until(()=>$('request-list').children.length>=4 && !$('lookup-patient').disabled,'inicializacion');
  $('use-demo').click();assert.equal($('provider').value,'academico');assert.equal($('document').value,'81000021');
  $('document').value=testDni; $('document').dispatchEvent(new w.Event('input'));
  $('patient-form').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
  await until(()=>!$('next-patient').disabled,'identidad');assert.match($('patient-name').textContent,/Valeria|Mateo|Sofia/);$('next-patient').click();assert.equal($('step-2').hidden,false);
  $('coverage-form').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));await until(()=>!$('next-coverage').disabled,'cobertura');assert.equal($('coverage-options').children.length,testDni==='81000021'?2:1);
  $('next-coverage').click();$('site-cards').querySelector('button').click();
  const next=new Date(Date.now()+14*86400000).toISOString().slice(0,10);$('appointment-date').value=next;$('appointment-date').dispatchEvent(new w.Event('change'));
  await until(()=>$('slots').querySelector('button'),'horarios');$('slots').querySelector('button').click();$('reason').value='Prueba del nuevo flujo guiado';
  $('next-schedule').click();assert.equal($('step-4').hidden,false);assert.ok($('review-details').textContent.includes(String(expectedAmount)));
  $('confirm-booking').click();await until(()=>!$('booking-success').hidden,'reserva confirmada');assert.match($('booking-code').textContent,/CITA-/);
  $('create-receipt').click();await until(()=>$('create-receipt').dataset.generated==='true','documento');assert.ok($('receipt-feedback').textContent.includes(String(expectedAmount)));
  d.querySelector('[data-view="documentos"]').click();await until(()=>$('receipt-list').querySelector('.record-card'),'listado de documentos');
  assert.match($('receipt-list').textContent,/sin validez tributaria/);
  d.querySelector('[data-view="tecnico"]').click();assert.equal($('view-tecnico').hidden,false);assert.ok($('request-list').children.length>=9);
  $('new-booking').click();assert.equal($('create-receipt').dataset.generated,undefined);
  $('salir').click();
  await until(()=>$('request-list').textContent.includes('Sesión') && $('evidence-json').textContent.includes('Sesion cerrada'),'logout sin cuerpo JSON');
  assert.equal(JSON.parse($('evidence-json').textContent).integracionESB.motor,'Apache-Synapse-3.0.2');
  const afterLogout=await(await http('php/sesion.php')).json();assert.equal(afterLogout.usuario,null);
  console.log('DNI sintetico '+testDni+' / importe '+expectedAmount+' — APROBADO: identidad → cobertura → horario → reserva → comprobante → evidencias → nueva reserva. Sin llamadas Factiliza.');
  dom.window.close();
 } finally {
  const sql=`START TRANSACTION; DELETE f FROM comprobantes_demo f JOIN pacientes p ON p.id=f.paciente_id JOIN usuarios u ON u.id=p.usuario_id WHERE u.email='${email}'; DELETE x FROM cita_contexto x JOIN citas c ON c.id=x.cita_id JOIN pacientes p ON p.id=c.paciente_id JOIN usuarios u ON u.id=p.usuario_id WHERE u.email='${email}'; DELETE c FROM citas c JOIN pacientes p ON p.id=c.paciente_id JOIN usuarios u ON u.id=p.usuario_id WHERE u.email='${email}'; DELETE l FROM identidad_limites l JOIN usuarios u ON u.id=l.usuario_id WHERE u.email='${email}'; DELETE p FROM pacientes p JOIN usuarios u ON u.id=p.usuario_id WHERE u.email='${email}'; DELETE ur FROM usuario_roles ur JOIN usuarios u ON u.id=ur.usuario_id WHERE u.email='${email}'; DELETE ip FROM informacion_personal ip JOIN usuarios u ON u.id=ip.usuario_id WHERE u.email='${email}'; DELETE FROM usuarios WHERE email='${email}'; COMMIT;`;
  execFileSync('C:\\xampp\\mysql\\bin\\mysql.exe',['--host=127.0.0.1','--port=3307','--user=root','--database=clinica_labendecida','--execute='+sql],{stdio:'pipe'});
 }
})().catch(e=>{console.error(e);process.exitCode=1;});
