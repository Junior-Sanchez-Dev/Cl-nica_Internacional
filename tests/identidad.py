"""Pruebas HTTP S01 contra XAMPP local. Crea y elimina una cuenta ficticia."""
import json, sys, uuid, subprocess
from pathlib import Path
from urllib.request import build_opener, HTTPCookieProcessor, Request
from urllib.error import HTTPError
from http.cookiejar import CookieJar
BASE=sys.argv[1] if len(sys.argv)>1 else 'http://127.0.0.1:8085'
MYSQL=r'C:\xampp\mysql\bin\mysql.exe'
email='s01-'+uuid.uuid4().hex+'@example.test'
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
    c.session(); check('Login de prueba',c.call('procesar_login.php','POST',{'email':email,'password':'Prueba-S01-2026!'}),200); c.session()
a=Client()
try:
    check('Acceso sin sesion',a.call('identidad.php','POST',{'dni':'00000001'}),401)
    a.session()
    check('Registro ficticio',a.call('procesar_registro.php','POST',{'nombre':'Prueba','apellido':'S01','email':email,'password':'Prueba-S01-2026!'}),201)
    login(a)
    check('Metodo incorrecto',a.call('identidad.php'),405)
    check('Proteccion CSRF',a.call('identidad.php','POST',{'dni':'00000001'},False),403)
    for value in ['123','abcdefgh','123456789',12345678,['00000001'],None]:
        check('Formato rechazado '+str(value),a.call('identidad.php','POST',{'dni':value}),400)
    check('Escenario invalido',a.call('identidad.php','POST',{'dni':'00000001','escenario':'otro'}),400)
    d,_=check('Identidad ficticia activa',a.call('identidad.php','POST',{'dni':'00000001'}),200)
    assert d['integracion']['httpProveedor']==200 and d['integracion']['destino']=='http://127.0.0.1:8090/identidad'
    assert d['simulado'] is True and d['proveedor']=='CATALOGO_FICTICIO' and d['datos']['dni']=='00000001'
    check('Inexistente',a.call('identidad.php','POST',{'dni':'00000099'}),404)
    check('Inactivo',a.call('identidad.php','POST',{'dni':'00000003'}),422)
    check('Indisponibilidad simulada',a.call('identidad.php','POST',{'dni':'00000001','escenario':'no_disponible'}),503)
    for i in range(6): check('Consulta permitida '+str(i+5),a.call('identidad.php','POST',{'dni':'00000002'}),200)
    _,headers=check('Limite por minuto',a.call('identidad.php','POST',{'dni':'00000001'}),429)
    assert int(headers.get('Retry-After',headers.get('Retry-after','0')))>0
    b=Client(); login(b)
    check('Limite compartido entre sesiones',b.call('identidad.php','POST',{'dni':'00000001'}),429)
    # Verificar que no se escribio un DNI simulado en el perfil.
    sql=f"SELECT COUNT(*) FROM pacientes p JOIN usuarios u ON p.usuario_id=u.id WHERE u.email='{email}' AND p.dni IS NOT NULL;"
    count=subprocess.check_output([MYSQL,'--host=127.0.0.1','--port=3307','--user=root','--database=clinica_labendecida','--skip-column-names','--execute='+sql],text=True).strip()
    assert count=='0',count
    results.append({'prueba':'El perfil no recibe el DNI ficticio','resultado':'APROBADO'})
    Path('tests/S01-ultima-ejecucion.json').write_text(json.dumps({'resultado':'APROBADO','pruebas':results},ensure_ascii=False,indent=2),encoding='utf-8')
finally:
    sql=f"START TRANSACTION; DELETE l FROM identidad_limites l JOIN usuarios u ON u.id=l.usuario_id WHERE u.email='{email}'; DELETE p FROM pacientes p JOIN usuarios u ON u.id=p.usuario_id WHERE u.email='{email}'; DELETE ur FROM usuario_roles ur JOIN usuarios u ON u.id=ur.usuario_id WHERE u.email='{email}'; DELETE ip FROM informacion_personal ip JOIN usuarios u ON u.id=ip.usuario_id WHERE u.email='{email}'; DELETE FROM usuarios WHERE email='{email}'; COMMIT;"
    subprocess.run([MYSQL,'--host=127.0.0.1','--port=3307','--user=root','--database=clinica_labendecida','--execute='+sql],check=True)
