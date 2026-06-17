<?php
require_once app_path('Locx/database.php');
$verifyToken = 'locx_webhook_token';
try{
  $cfg=$pdo->query("SELECT verify_token FROM whatsapp_config ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
  if(!empty($cfg['verify_token'])) $verifyToken=$cfg['verify_token'];
}catch(Throwable $e){}
if($_SERVER['REQUEST_METHOD']==='GET'){
  $mode=$_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '';
  $token=$_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
  $challenge=$_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '';
  if($mode==='subscribe' && hash_equals($verifyToken,$token)){ echo $challenge; exit; }
  http_response_code(403); echo 'Token inválido'; exit;
}
$raw=file_get_contents('php://input');
try{
  $pdo->prepare("INSERT INTO whatsapp_logs (tipo,mensagem,status,resposta_api,criado_em) VALUES ('webhook','Webhook recebido','recebido',?,NOW())")->execute([$raw]);
}catch(Throwable $e){}
http_response_code(200); echo 'EVENT_RECEIVED';
