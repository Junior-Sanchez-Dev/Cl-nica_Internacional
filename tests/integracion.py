"""Pruebas HTTP reales. Usar solo contra la BD local de demostracion.
Crea dos cuentas ficticias y elimina sus datos al terminar.
python tests/integracion.py [http://127.0.0.1:8085]
"""
import json, sys, uuid, subprocess
from pathlib import Path
from urllib.request import build_opener, HTTPCookieProcessor, Request
from urllib.error import HTTPError
from http.cookiejar import CookieJar
from datetime import datetime, timedelta, timezone
BASE = sys.argv[1] if len(sys.argv)>1 else 'http://127.0.0.1:8085'
MYSQL = r'C:\xampp\mysql\bin\mysql.exe'
class Client:
    def __init__(self):
        self.opener = build_opener(HTTPCookieProcessor(CookieJar()))
        self.token = None
    def call(self, path, method='GET', data=None, csrf=True):
        headers = {}
        if data is not None:
            headers['Content-Type']='application/json'
            data = json.dumps(data).encode()
        if csrf and self.token: headers['X-CSRF-Token']=self.token
        req = Request(BASE+'/php/'+path, data=data, headers=headers, method=method)
        try: response=self.opener.open(req,timeout=10)
        except HTTPError as e: response=e
        with response:
            return response.status,json.loads(response.read())
    def session(self):
        status,d=self.call('sesion.php'); assert status==200
        self.token=d['csrfToken']; return d
results=[]
def check(name, result, expected):
    status,data=result
    assert status==expected, (name,status,data)
    results.append({'prueba':name,'http':status,'respuesta':data})
    print('OK',name,status)
    return data
suffix=uuid.uuid4().hex
emails=[f'soa-{suffix}-{i}@example.test' for i in range(2)]
a,b=Client(),Client()
try:
    check('Consulta sin sesion',a.call('obtener_citas.php'),401)
    a.session(); b.session()
    payload={'nombre':'Paciente prueba','apellido':'SOA','email':emails[0],'password':'Prueba-SOA-2026!'}
    check('Registro sin CSRF',a.call('procesar_registro.php','POST',payload,False),403)
    check('Registro invalido',a.call('procesar_registro.php','POST',{**payload,'password':'123'}),400)
    for i,c in enumerate([a,b]):
        check(f'Registro atomico {i+1}',c.call('procesar_registro.php','POST',{**payload,'email':emails[i]}),201)
    check('Correo duplicado',a.call('procesar_registro.php','POST',payload),409)
    check('Login incorrecto',a.call('procesar_login.php','POST',{'email':emails[0],'password':'incorrecta'}),401)
    for i,c in enumerate([a,b]):
        check(f'Login {i+1}',c.call('procesar_login.php','POST',{'email':emails[i],'password':payload['password']}),200)
        c.session()
    doctors=check('Catalogo de medicos',a.call('doctores.php'),200)['datos']
    doctor=int(doctors[0]['id'])
    date=(datetime.now(timezone(timedelta(hours=-5)))+timedelta(days=7)).strftime('%Y-%m-%d')
    slots=check('Disponibilidad',a.call(f'disponibilidad.php?doctor_id={doctor}&fecha={date}'),200)['datos']['horarios']
    assert slots,'No hay turnos libres para la prueba'
    body={'doctor_id':doctor,'fecha':date,'hora':slots[0],'motivo':'Prueba de integracion SOA'}
    check('Fecha inexistente',a.call('reservar_cita.php','POST',{**body,'fecha':'2026-02-30'}),400)
    check('Fecha pasada',a.call('reservar_cita.php','POST',{**body,'fecha':'2020-01-01'}),400)
    check('Turno fuera de grilla',a.call('reservar_cita.php','POST',{**body,'hora':'08:15'}),400)
    check('Medico inexistente',a.call('reservar_cita.php','POST',{**body,'doctor_id':999999}),404)
    check('Suplantacion de paciente',a.call('reservar_cita.php','POST',{**body,'paciente_id':1}),400)
    check('Reserva sin CSRF',a.call('reservar_cita.php','POST',body,False),403)
    booking=check('Reserva creada',a.call('reservar_cita.php','POST',body),201)
    check('Conflicto entre pacientes',b.call('reservar_cita.php','POST',body),409)
    mine=check('Consulta de citas propias',a.call('obtener_citas.php'),200)['datos']
    assert len(mine)==1 and int(mine[0]['id'])==booking['datos']['idCita']
    other=check('Aislamiento de otra cuenta',b.call('obtener_citas.php?paciente_id=1'),200)['datos']
    assert other==[],other
    free=check('Turno ocupado excluido',a.call(f'disponibilidad.php?doctor_id={doctor}&fecha={date}'),200)['datos']['horarios']
    assert slots[0] not in free
    check('Metodo incorrecto',a.call('reservar_cita.php'),405)
    check('Cerrar sesion',a.call('cerrar_sesion.php','POST'),200)
    check('Sesion invalidada',a.call('obtener_citas.php'),401)
    # Redact personal session material: evidence only contains operation responses.
    Path('tests/ultima-ejecucion.json').write_text(json.dumps({'resultado':'APROBADO','pruebas':results},ensure_ascii=False,indent=2),encoding='utf-8')
finally:
    quoted=','.join("'"+e+"'" for e in emails)
    sql=f"START TRANSACTION; DELETE c FROM citas c JOIN pacientes p ON p.id=c.paciente_id JOIN usuarios u ON u.id=p.usuario_id WHERE u.email IN ({quoted}); DELETE p FROM pacientes p JOIN usuarios u ON u.id=p.usuario_id WHERE u.email IN ({quoted}); DELETE ur FROM usuario_roles ur JOIN usuarios u ON u.id=ur.usuario_id WHERE u.email IN ({quoted}); DELETE ip FROM informacion_personal ip JOIN usuarios u ON u.id=ip.usuario_id WHERE u.email IN ({quoted}); DELETE FROM usuarios WHERE email IN ({quoted}); COMMIT;"
    subprocess.run([MYSQL,'--host=127.0.0.1','--port=3307','--user=root','--database=clinica_labendecida','--execute='+sql],check=True)
