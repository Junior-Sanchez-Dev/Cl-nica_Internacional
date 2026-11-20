"""Pruebas HTTP FLUJO contra XAMPP local. Crea y elimina una cuenta ficticia."""
import json, sys, uuid, subprocess
from pathlib import Path
from urllib.request import build_opener, HTTPCookieProcessor, Request
from urllib.error import HTTPError
from http.cookiejar import CookieJar
BASE=sys.argv[1] if len(sys.argv)>1 else 'http://127.0.0.1:8085'
MYSQL=r'C:\xampp\mysql\bin\mysql.exe'
email='flujo-'+uuid.uuid4().hex+'@example.test'
results=[]
class Client:
    def __init__(self): self.opener=build_opener(HTTPCookieProcessor(CookieJar())); self.token=''
    def call(self,path,method='GET',data=None,csrf=True):
        headers={}
        if data is not None: headers['Content-Type']='application/json'; data=json.dumps(data).encode()
        if csrf and self.token: headers['X-CSRF-Token']=self.token
        try: r=self.opener.open(Request(BASE+'/php/'+path,data=data,headers=headers,method=method),timeout=10)
        except HTTPError as e: r=e
        with r: return r.status,json.loads(r.read()),dict(r.headers)
    def session(self): self.token=self.call('sesion.php')[1]['csrfToken']
def check(name,result,status):
    actual,data,headers=result
    assert actual==status,(name,actual,data)
    results.append({'prueba':name,'http':actual,'respuesta':data})
    print('OK',name,status)
    return data,headers
def login(c):
    c.session(); check('Login de prueba',c.call('procesar_login.php','POST',{'email':email,'password':'Prueba-FLUJO-2026!'}),200); c.session()
from datetime import datetime,timedelta,timezone
a=Client()
try:
 a.session();check('Crear cuenta',a.call('procesar_registro.php','POST',{'nombre':'Prueba','apellido':'Integrada','email':email,'password':'Prueba-FLUJO-2026!'}),201);login(a)
 docs=a.call('doctores.php')[1]['datos'];medico=next(int(d['id']) for d in docs if d['especialidad']=='Medicina General')
 date=(datetime.now(timezone(timedelta(hours=-5)))+timedelta(days=12)).strftime('%Y-%m-%d')
 slots=a.call(f'disponibilidad.php?doctor_id={medico}&fecha={date}')[1]['datos']['horarios']
 body={'doctor_id':medico,'fecha':date,'hora':slots[0],'motivo':'Flujo completo SOA','idSede':2,'modalidad':'seguro'}
 check('No permite flujo sin identidad',a.call('admision.php','POST',body),409)
 if not Path('../factiliza-config.json').exists():
  check('Factiliza sin token no inventa datos',a.call('identidad.php','POST',{'dni':'00000001','proveedor':'factiliza'}),503)
 check('Identidad por HTTP',a.call('identidad.php','POST',{'dni':'00000001'}),200)
 check('Exige consulta cobertura',a.call('admision.php','POST',body),409)
 check('Consulta de otra especialidad',a.call('seguros.php','POST',{'dni':'00000001','aseguradora':'ASEGURADORA_DEMO','tipoAtencion':'cardiologia'}),200)
 check('Impide mezclar especialidad',a.call('admision.php','POST',body),409)
 check('Consulta correcta',a.call('seguros.php','POST',{'dni':'00000001','aseguradora':'ASEGURADORA_DEMO','tipoAtencion':'medicina_general'}),200)
 d,_=check('Reserva integrada',a.call('admision.php','POST',body),201);cita=d['datos']['idCita'];assert d['datos']['montoPaciente']==30 and d['datos']['idSede']==2
 d,_=check('Comprobante aplica copago',a.call('comprobantes.php','POST',{'idCita':cita}),201);assert d['datos']['montoTotal']==30 and d['datos']['modalidad']=='seguro' and d['datos']['integracion']['httpProveedor']==200
 check('Cambio de identidad invalida cobertura',a.call('identidad.php','POST',{'dni':'00000002'}),200)
 check('Seguro vencido',a.call('seguros.php','POST',{'dni':'00000002','aseguradora':'ASEGURADORA_DEMO','tipoAtencion':'medicina_general'}),200)
 body['hora']=slots[1]
 check('No reserva asegurado con seguro vencido',a.call('admision.php','POST',body),409)
 body['modalidad']='particular'
 d,_=check('Particular con confirmacion explicita',a.call('admision.php','POST',body),201);assert d['datos']['montoPaciente']==100
 Path('tests/flujo-ultima-ejecucion.json').write_text(json.dumps({'resultado':'APROBADO','pruebas':results},ensure_ascii=False,indent=2),encoding='utf-8')
finally:
 sql=f"START TRANSACTION; DELETE f FROM comprobantes_demo f JOIN pacientes p ON p.id=f.paciente_id JOIN usuarios u ON u.id=p.usuario_id WHERE u.email='{email}'; DELETE x FROM cita_contexto x JOIN citas c ON c.id=x.cita_id JOIN pacientes p ON p.id=c.paciente_id JOIN usuarios u ON u.id=p.usuario_id WHERE u.email='{email}'; DELETE c FROM citas c JOIN pacientes p ON p.id=c.paciente_id JOIN usuarios u ON u.id=p.usuario_id WHERE u.email='{email}'; DELETE l FROM identidad_limites l JOIN usuarios u ON u.id=l.usuario_id WHERE u.email='{email}'; DELETE p FROM pacientes p JOIN usuarios u ON u.id=p.usuario_id WHERE u.email='{email}'; DELETE ur FROM usuario_roles ur JOIN usuarios u ON u.id=ur.usuario_id WHERE u.email='{email}'; DELETE ip FROM informacion_personal ip JOIN usuarios u ON u.id=ip.usuario_id WHERE u.email='{email}'; DELETE FROM usuarios WHERE email='{email}'; COMMIT;"
 subprocess.run([MYSQL,'--host=127.0.0.1','--port=3307','--user=root','--database=clinica_labendecida','--execute='+sql],check=True)
