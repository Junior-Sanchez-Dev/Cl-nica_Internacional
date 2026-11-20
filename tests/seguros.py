"""Pruebas HTTP S02 contra XAMPP local. Crea y elimina una cuenta ficticia."""
import json, sys, uuid, subprocess
from pathlib import Path
from urllib.request import build_opener, HTTPCookieProcessor, Request
from urllib.error import HTTPError
from http.cookiejar import CookieJar
BASE=sys.argv[1] if len(sys.argv)>1 else 'http://127.0.0.1:8085'
MYSQL=r'C:\xampp\mysql\bin\mysql.exe'
email='s02-'+uuid.uuid4().hex+'@example.test'
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
    c.session(); check('Login de prueba',c.call('procesar_login.php','POST',{'email':email,'password':'Prueba-S02-2026!'}),200); c.session()
a=Client()
try:
    body={'dni':'00000001','aseguradora':'ASEGURADORA_DEMO','tipoAtencion':'medicina_general'}
    check('Acceso sin sesion',a.call('seguros.php','POST',body),401)
    a.session()
    check('Registro de prueba',a.call('procesar_registro.php','POST',{'nombre':'Prueba','apellido':'S02','email':email,'password':'Prueba-S02-2026!'}),201)
    login(a)
    check('Metodo incorrecto',a.call('seguros.php'),405)
    check('CSRF requerido',a.call('seguros.php','POST',body,False),403)
    for value in ['123','abcdefgh',12345678,['00000001'],None]:
        check('DNI invalido '+str(value),a.call('seguros.php','POST',{**body,'dni':value}),400)
    check('Aseguradora invalida',a.call('seguros.php','POST',{**body,'aseguradora':'otra'}),400)
    check('Atencion invalida',a.call('seguros.php','POST',{**body,'tipoAtencion':'cirugia'}),400)
    check('Escenario invalido',a.call('seguros.php','POST',{**body,'escenario':'otro'}),400)
    for atencion,tarifa,copago in [('medicina_general',100,30),('cardiologia',150,50),('pediatria',120,40)]:
        d,_=check('Cobertura '+atencion,a.call('seguros.php','POST',{**body,'tipoAtencion':atencion}),200)
        v=d['datos']; assert d['integracion']['httpProveedor']==200 and d['integracion']['destino']=='http://127.0.0.1:8090/seguros'
        assert d['simulado'] and d['proveedor']=='ASEGURADORA_DEMO'
        assert v['cobertura'] is True and v['vigente'] is True and v['copago']==copago and v['tarifaParticular']==tarifa
        assert v['importeAseguradora']+v['copago']==tarifa and not v['ofrecerParticular']
    d,_=check('Seguro vencido',a.call('seguros.php','POST',{**body,'dni':'00000002'}),200)
    assert d['success'] and d['datos']['resultado']=='SEGURO_VENCIDO' and d['datos']['copago'] is None and d['datos']['ofrecerParticular']
    d,_=check('Atencion no cubierta',a.call('seguros.php','POST',{**body,'dni':'00000004','tipoAtencion':'cardiologia'}),200)
    assert d['datos']['resultado']=='ATENCION_NO_CUBIERTA' and not d['datos']['cobertura'] and d['datos']['importeAseguradora'] is None
    d,_=check('Plan basico cubierto',a.call('seguros.php','POST',{**body,'dni':'00000004'}),200)
    assert d['datos']['copago']==20
    check('Afiliacion inexistente',a.call('seguros.php','POST',{**body,'dni':'00000099'}),404)
    d,_=check('Proveedor no disponible',a.call('seguros.php','POST',{**body,'escenario':'no_disponible'}),503)
    assert 'datos' not in d # La falla no concede ni deniega cobertura.
    d,_=check('Tarifa calculada por servidor',a.call('seguros.php','POST',{**body,'copago':0,'tarifaParticular':1,'cobertura':True}),200)
    assert d['datos']['copago']==30 and d['datos']['tarifaParticular']==100
    check('Cerrar sesion',a.call('cerrar_sesion.php','POST'),200)
    check('Sesion cerrada',a.call('seguros.php','POST',body),401)
    Path('tests/S02-ultima-ejecucion.json').write_text(json.dumps({'resultado':'APROBADO','pruebas':results},ensure_ascii=False,indent=2),encoding='utf-8')
finally:
    sql=f"START TRANSACTION; DELETE p FROM pacientes p JOIN usuarios u ON u.id=p.usuario_id WHERE u.email='{email}'; DELETE ur FROM usuario_roles ur JOIN usuarios u ON u.id=ur.usuario_id WHERE u.email='{email}'; DELETE ip FROM informacion_personal ip JOIN usuarios u ON u.id=ip.usuario_id WHERE u.email='{email}'; DELETE FROM usuarios WHERE email='{email}'; COMMIT;"
    subprocess.run([MYSQL,'--host=127.0.0.1','--port=3307','--user=root','--database=clinica_labendecida','--execute='+sql],check=True)
