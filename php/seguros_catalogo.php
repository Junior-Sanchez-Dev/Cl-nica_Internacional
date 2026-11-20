<?php
// Aseguradora, polizas y tarifas inventadas exclusivamente para pruebas.
return [
    'aseguradora'=>'ASEGURADORA_DEMO',
    'tarifasCentimos'=>['medicina_general'=>10000,'cardiologia'=>15000,'pediatria'=>12000],
    'polizas'=>[
        '00000001'=>['plan'=>'Plan Integral Demo','vigente'=>true,'coberturas'=>['medicina_general'=>3000,'cardiologia'=>5000,'pediatria'=>4000]],
        '00000002'=>['plan'=>'Plan Vencido Demo','vigente'=>false,'coberturas'=>[]],
        '00000004'=>['plan'=>'Plan Basico Demo','vigente'=>true,'coberturas'=>['medicina_general'=>2000]],
    ],
];
