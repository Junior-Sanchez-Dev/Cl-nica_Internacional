<?php
function catalogoPolizas(): array {
 $db=new PDO('sqlite:'.dirname(__DIR__,2).'/proveedor-seguros.sqlite');
 $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 $db->exec('CREATE TABLE IF NOT EXISTS polizas(dni TEXT PRIMARY KEY,plan TEXT NOT NULL,inicio TEXT NOT NULL,fin TEXT NOT NULL)');
 $db->exec('CREATE TABLE IF NOT EXISTS prestaciones(dni TEXT NOT NULL,tipo TEXT NOT NULL,copago INTEGER NOT NULL,PRIMARY KEY(dni,tipo))');
 $db->exec("INSERT OR IGNORE INTO polizas VALUES ('00000001','Plan Integral Academico','2020-01-01','2099-12-31'),('00000002','Plan Vencido Academico','2019-01-01','2020-12-31'),('00000004','Plan Basico Academico','2020-01-01','2099-12-31')");
 $db->exec("INSERT OR IGNORE INTO prestaciones VALUES ('00000001','medicina_general',3000),('00000001','cardiologia',5000),('00000001','pediatria',4000),('00000004','medicina_general',2000)");
 $db->exec("INSERT OR IGNORE INTO polizas VALUES ('81000021','Plan Integral','2020-01-01','2099-12-31'),('81000022','Plan Esencial','2019-01-01','2020-12-31'),('81000024','Plan Esencial','2020-01-01','2099-12-31')");
 $db->exec("INSERT OR IGNORE INTO prestaciones VALUES ('81000021','medicina_general',3000),('81000021','cardiologia',5000),('81000021','pediatria',4000),('81000024','medicina_general',2000)");
 $catalogo=['aseguradora'=>'ASEGURADORA_DEMO','tarifasCentimos'=>['medicina_general'=>10000,'cardiologia'=>15000,'pediatria'=>12000],'polizas'=>[]];
 $hoy=(new DateTimeImmutable('now',new DateTimeZone('America/Lima')))->format('Y-m-d');
 foreach ($db->query('SELECT * FROM polizas') as $v) {
  $s=$db->prepare('SELECT tipo,copago FROM prestaciones WHERE dni=?');$s->execute([$v['dni']]);$c=[];
  foreach($s as $prestacion) $c[$prestacion['tipo']]=(int)$prestacion['copago'];
  $catalogo['polizas'][$v['dni']]=['plan'=>$v['plan'],'vigente'=>$v['inicio']<=$hoy && $v['fin']>=$hoy,'inicio'=>$v['inicio'],'fin'=>$v['fin'],'coberturas'=>$c];
 }
 return $catalogo;
}
