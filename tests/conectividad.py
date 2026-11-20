"""Pruebas HTTP RUTAS contra XAMPP local. Crea y elimina una cuenta ficticia."""
import json, sys, uuid, subprocess
from pathlib import Path
from urllib.request import build_opener, HTTPCookieProcessor, Request
from urllib.error import HTTPError
from http.cookiejar import CookieJar
BASE=sys.argv[1] if len(sys.argv)>1 else 'http://127.0.0.1:8085'
MYSQL=r'C:\xampp\mysql\bin\mysql.exe'
email='rutas-'+uuid.uuid4().hex+'@example.test'
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
    c.session(); check('Login de prueba',c.call('procesar_login.php','POST',{'email':email,'password':'Prueba-RUTAS-2026!'}),200); c.session()
a=Client()
try:
    a.session()
    check('Registro de prueba',a.call('procesar_registro.php','POST',{'nombre':'Prueba','apellido':'Rutas','email':email,'password':'Prueba-RUTAS-2026!'}),201)
    login(a)
    check('Proveedor academico accesible',a.call('estado_integraciones.php'),200)
    result=a.call('sedes.php?id=1&latitud=-12.1211&longitud=-77.0297&ruta=1')
    actual,data,_=result
    assert actual in [200,503],result
    results.append({'prueba':'OSRM externo','http':actual,'respuesta':data,'integracionExternaConfirmada':actual==200})
    print('OSRM:',actual,'confirmado' if actual==200 else 'no disponible: integracion externa pendiente de verificacion')
    Path('tests/integraciones-conectividad.json').write_text(json.dumps(results,ensure_ascii=False,indent=2),encoding='utf-8')
finally:
    sql=f"START TRANSACTION; DELETE p FROM pacientes p JOIN usuarios u ON u.id=p.usuario_id WHERE u.email='{email}'; DELETE ur FROM usuario_roles ur JOIN usuarios u ON u.id=ur.usuario_id WHERE u.email='{email}'; DELETE ip FROM informacion_personal ip JOIN usuarios u ON u.id=ip.usuario_id WHERE u.email='{email}'; DELETE FROM usuarios WHERE email='{email}'; COMMIT;"
    subprocess.run([MYSQL,'--host=127.0.0.1','--port=3307','--user=root','--database=clinica_labendecida','--execute='+sql],check=True)
