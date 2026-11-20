import urllib.request,urllib.error,http.cookiejar,json,ssl
jar=http.cookiejar.CookieJar();opener=urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPSHandler(context=ssl.create_default_context()),urllib.request.HTTPCookieProcessor(jar))
checks=[]
def check(name,url,status,body=None,method=None):
 headers={'Content-Type':'application/json'} if body is not None else {}
 req=urllib.request.Request(url,data=body,headers=headers,method=method)
 try:r=opener.open(req,timeout=15)
 except urllib.error.HTTPError as e:r=e
 assert r.status==status,(name,r.status)
 raw=r.read().decode()
 checks.append({'prueba':name,'http':r.status,'motor':r.headers.get('X-ESB-Engine'),'traza':r.headers.get('X-ESB-Trace')})
 return r,raw
base='https://localhost:8443/php/esb.php?servicio='
check('Acceso directo al portal bloqueado','https://localhost:8443/php/sesion.php',403)
check('Acceso directo al backend bloqueado','http://127.0.0.1:8086/php/sesion.php',403)
check('ESB rechaza cliente sin credencial interna','http://127.0.0.1:8280/clinica/sesion',403)
check('Ruta no registrada',base+'inexistente',404)
check('Metodo no permitido',base+'sesion',405,method='POST')
r,raw=check('Sesion a traves de Synapse',base+'sesion',200)
assert r.headers.get('X-ESB-Engine')=='Apache-Synapse-3.0.2'
assert any(c.secure for c in jar),'Cookie no segura'
assert 'httponly' in r.headers.get('Set-Cookie','').lower()
check('Autenticacion requerida S01',base+'identidad',401,b'{}')
check('Autenticacion requerida S02',base+'seguros',401,b'{}')
check('Autenticacion requerida S03',base+'sedes',401)
check('Autenticacion requerida S04',base+'obtener_citas',401)
check('Autenticacion requerida S05',base+'comprobantes',401)
check('CSRF validado en backend',base+'procesar_login',403,b'{"email":"esb@example.test","password":"Prueba"}')
assert all(v['motor']=='Apache-Synapse-3.0.2' and v['traza'] for v in checks[5:])
from pathlib import Path
Path('../../outputs/ESB-verificacion.json').write_text(json.dumps({'resultado':'APROBADO','pruebas':checks},ensure_ascii=False,indent=2),encoding='utf-8')
print('APROBADO',len(checks),'comprobaciones HTTP y seguridad. Cookie Secure y trazas Synapse verificadas.')
