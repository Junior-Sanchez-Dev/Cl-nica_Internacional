"""Pruebas HTTP S03 contra XAMPP local. Crea y elimina una cuenta ficticia."""
import json, sys, uuid, subprocess
from pathlib import Path
from urllib.request import build_opener, HTTPCookieProcessor, Request
from urllib.error import HTTPError
from http.cookiejar import CookieJar
BASE=sys.argv[1] if len(sys.argv)>1 else 'http://127.0.0.1:8085'
MYSQL=r'C:\xampp\mysql\bin\mysql.exe'
email='s03-'+uuid.uuid4().hex+'@example.test'
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
    c.session(); check('Login de prueba',c.call('procesar_login.php','POST',{'email':email,'password':'Prueba-S03-2026!'}),200); c.session()
a=Client()
try:
    check('Acceso sin sesion',a.call('sedes.php'),401)
    a.session()
    check('Registro de prueba',a.call('procesar_registro.php','POST',{'nombre':'Prueba','apellido':'S03','email':email,'password':'Prueba-S03-2026!'}),201)
    login(a)
    d,_=check('Listado de sedes',a.call('sedes.php'),200)
    assert len(d['datos'])==3 and all(v['habilitada'] for v in d['datos']) and d['datosDemostracion']
    d,_=check('Detalle de sede',a.call('sedes.php?id=1'),200)
    assert d['datos']['nombre']=='Sede Centro Demo' and d['datos']['mapaUrl'].startswith('https://www.google.com/maps/search/')
    assert 'distanciaKm' not in d['datos']
    d,_=check('Distancia cero',a.call('sedes.php?id=1&latitud=-12.0464&longitud=-77.0428'),200)
    assert d['datos']['distanciaKm']==0 and d['datos']['tipoDistancia']=='LINEA_RECTA_APROXIMADA'
    d,_=check('Distancia punto sur',a.call('sedes.php?id=1&latitud=-12.1211&longitud=-77.0297'),200)
    assert 8<d['datos']['distanciaKm']<9
    check('Sede inactiva',a.call('sedes.php?id=4'),404)
    check('Sede inexistente',a.call('sedes.php?id=999'),404)
    for value in ['0','-1','abc','1.5','']:
        check('ID invalido '+value,a.call('sedes.php?id='+value),400)
    for query in ['latitud=0&longitud=0','id=1&latitud=0','id=1&longitud=0','id=1&latitud=91&longitud=0','id=1&latitud=0&longitud=-181','id=1&latitud=abc&longitud=0','id=1&latitud[]=0&longitud=0']:
        check('Parametros invalidos '+query,a.call('sedes.php?'+query),400)
    check('Metodo incorrecto',a.call('sedes.php','POST',{}),405)
    check('Cerrar sesion',a.call('cerrar_sesion.php','POST'),200)
    check('Sesion cerrada',a.call('sedes.php'),401)
    Path('tests/S03-ultima-ejecucion.json').write_text(json.dumps({'resultado':'APROBADO','pruebas':results},ensure_ascii=False,indent=2),encoding='utf-8')
finally:
    sql=f"START TRANSACTION; DELETE p FROM pacientes p JOIN usuarios u ON u.id=p.usuario_id WHERE u.email='{email}'; DELETE ur FROM usuario_roles ur JOIN usuarios u ON u.id=ur.usuario_id WHERE u.email='{email}'; DELETE ip FROM informacion_personal ip JOIN usuarios u ON u.id=ip.usuario_id WHERE u.email='{email}'; DELETE FROM usuarios WHERE email='{email}'; COMMIT;"
    subprocess.run([MYSQL,'--host=127.0.0.1','--port=3307','--user=root','--database=clinica_labendecida','--execute='+sql],check=True)
