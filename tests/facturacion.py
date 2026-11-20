"""Pruebas HTTP S05 contra XAMPP local. Crea y elimina una cuenta ficticia."""
import json, sys, uuid, subprocess
from pathlib import Path
from urllib.request import build_opener, HTTPCookieProcessor, Request
from urllib.error import HTTPError
from http.cookiejar import CookieJar
BASE=sys.argv[1] if len(sys.argv)>1 else 'http://127.0.0.1:8085'
MYSQL=r'C:\xampp\mysql\bin\mysql.exe'
email='s05-'+uuid.uuid4().hex+'@example.test'
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
    c.session(); check('Login de prueba',c.call('procesar_login.php','POST',{'email':email,'password':'Prueba-S05-2026!'}),200); c.session()
from datetime import datetime,timedelta,timezone
a,b=Client(),Client(); emails=[email,'other-'+email]
try:
    check('Sin sesion',a.call('comprobantes.php'),401)
    for address,c in zip(emails,[a,b]):
        c.session();check('Registro',c.call('procesar_registro.php','POST',{'nombre':'Prueba','apellido':'S05','email':address,'password':'Prueba-S05-2026!'}),201)
        check('Login',c.call('procesar_login.php','POST',{'email':address,'password':'Prueba-S05-2026!'}),200);c.session()
    d,_=check('Sin comprobantes',a.call('comprobantes.php'),200);assert d['datos']==[]
    date=(datetime.now(timezone(timedelta(hours=-5)))+timedelta(days=10)).strftime('%Y-%m-%d')
    doctors=a.call('doctores.php')[1]['datos'];doctor=int(doctors[0]['id'])
    slots=a.call(f'disponibilidad.php?doctor_id={doctor}&fecha={date}')[1]['datos']['horarios'];assert len(slots)>=3
    citas=[]
    for slot in slots[:3]:
        d,_=check('Crear cita de prueba',a.call('reservar_cita.php','POST',{'doctor_id':doctor,'fecha':date,'hora':slot,'motivo':'Prueba S05'}),201);citas.append(d['datos']['idCita'])
    check('CSRF',a.call('comprobantes.php','POST',{'idCita':citas[0]},False),403)
    check('ID invalido',a.call('comprobantes.php','POST',{'idCita':0}),400)
    check('Escenario invalido',a.call('comprobantes.php','POST',{'idCita':citas[0],'escenario':'otro'}),400)
    check('Operacion invalida',a.call('comprobantes.php','POST',{'idCita':citas[0],'operacion':'borrar'}),400)
    check('Monto manipulado',a.call('comprobantes.php','POST',{'idCita':citas[0],'montoTotal':1}),400)
    check('Cita ajena',b.call('comprobantes.php','POST',{'idCita':citas[0]}),404)
    d,_=check('Generar aceptado',a.call('comprobantes.php','POST',{'idCita':citas[0]}),201);first=d['datos'];assert first['validezTributaria'] is False and first['estado']=='ACEPTADO_SIMULADO' and not first['pagoRealizado']
    assert first['integracion']['httpProveedor']==200 and first['integracion']['transporte']=='SOAP_XML_HTTP'
    assert '<soap:Envelope' in first['xmlEnviado'] and '<demo:Estado>ACEPTADO_SIMULADO' in first['xmlRecibido']
    expected={'Cardiologia':150,'Medicina General':100,'Pediatria':120}[doctors[0]['especialidad']];assert first['montoTotal']==expected
    d,_=check('Duplicado',a.call('comprobantes.php','POST',{'idCita':citas[0]}),409);assert d['datos']['idComprobante']==first['idComprobante']
    check('Consultar detalle',a.call('comprobantes.php?id='+str(first['idComprobante'])),200)
    check('Detalle ajeno',b.call('comprobantes.php?id='+str(first['idComprobante'])),404)
    check('Reenvio ajeno',b.call('comprobantes.php','POST',{'operacion':'reenviar','idComprobante':first['idComprobante']}),404)
    check('Aceptado no se reenvia',a.call('comprobantes.php','POST',{'operacion':'reenviar','idComprobante':first['idComprobante']}),409)
    d,_=check('Rechazo simulado guardado',a.call('comprobantes.php','POST',{'idCita':citas[1],'escenario':'rechazado'}),201);rejected=d['datos'];assert rejected['estado']=='RECHAZADO_SIMULADO'
    d,_=check('Reenviar rechazado',a.call('comprobantes.php','POST',{'operacion':'reenviar','idComprobante':rejected['idComprobante']}),200);assert d['datos']['codigo']==rejected['codigo'] and d['datos']['intentos']==2
    d,_=check('Timeout simulado persistido',a.call('comprobantes.php','POST',{'idCita':citas[2],'escenario':'no_disponible'}),503);pending=d['datos'];assert pending['estado']=='PENDIENTE_SIMULADO'
    d,_=check('No duplica pendiente',a.call('comprobantes.php','POST',{'idCita':citas[2]}),409);assert d['datos']['codigo']==pending['codigo']
    d,_=check('Reenviar pendiente',a.call('comprobantes.php','POST',{'operacion':'reenviar','idComprobante':pending['idComprobante']}),200);assert d['datos']['estado']=='ACEPTADO_SIMULADO' and d['datos']['codigo']==pending['codigo'] and d['datos']['montoTotal']==pending['montoTotal']
    d,_=check('Listado propio',a.call('comprobantes.php'),200);assert len(d['datos'])==3
    d,_=check('Listado ajeno vacio',b.call('comprobantes.php?paciente_id=1'),200);assert d['datos']==[]
    check('Cerrar sesion',a.call('cerrar_sesion.php','POST'),200)
    check('Sesion invalidada',a.call('comprobantes.php'),401)
    Path('tests/S05-ultima-ejecucion.json').write_text(json.dumps({'resultado':'APROBADO','pruebas':results},ensure_ascii=False,indent=2),encoding='utf-8')
finally:
    addresses=','.join("'"+e+"'" for e in emails)
    sql=f"START TRANSACTION; DELETE f FROM comprobantes_demo f JOIN pacientes p ON p.id=f.paciente_id JOIN usuarios u ON u.id=p.usuario_id WHERE u.email IN ({addresses}); DELETE c FROM citas c JOIN pacientes p ON p.id=c.paciente_id JOIN usuarios u ON u.id=p.usuario_id WHERE u.email IN ({addresses}); DELETE p FROM pacientes p JOIN usuarios u ON u.id=p.usuario_id WHERE u.email IN ({addresses}); DELETE ur FROM usuario_roles ur JOIN usuarios u ON u.id=ur.usuario_id WHERE u.email IN ({addresses}); DELETE ip FROM informacion_personal ip JOIN usuarios u ON u.id=ip.usuario_id WHERE u.email IN ({addresses}); DELETE FROM usuarios WHERE email IN ({addresses}); COMMIT;"
    subprocess.run([MYSQL,'--host=127.0.0.1','--port=3307','--user=root','--database=clinica_labendecida','--execute='+sql],check=True)
