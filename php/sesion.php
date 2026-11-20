<?php
require __DIR__.'/api.php';
metodo('GET');
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$configPath=dirname(__DIR__,2).'/factiliza-config.json';
$config=is_file($configPath)?json_decode(file_get_contents($configPath),true):[];
$token=$config['token'] ?? '';
$configurado=is_string($token) && strlen($token)>=8 && !preg_match('/[\s\x00-\x1F\x7F]/',$token);
responder(200, ['success'=>true, 'csrfToken'=>$_SESSION['csrf'], 'factilizaConfigurado'=>$configurado,'usuario'=>empty($_SESSION['user_id']) ? null : ['nombre'=>$_SESSION['user_nombre'], 'rol'=>$_SESSION['user_rol']]]);
