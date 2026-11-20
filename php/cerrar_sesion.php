<?php
require __DIR__.'/api.php';
metodo('POST'); csrf(); $_SESSION=[]; session_destroy();
setcookie(session_name(), '', ['expires'=>time()-3600,'path'=>'/','httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off']);
responder(200,['success'=>true,'message'=>'Sesion cerrada.']);
