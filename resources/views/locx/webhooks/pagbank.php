<?php
require_once app_path('Locx/database.php');
require_once app_path('Locx/functions.php');
require_once app_path('Locx/pagbank.php');
$raw=file_get_contents('php://input');
$assinatura=$_SERVER['HTTP_X_AUTHENTICITY_TOKEN'] ?? '';
if(!pagbank_validar_assinatura_webhook($pdo,$raw,$assinatura)){
  http_response_code(401);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>false,'erro'=>'Assinatura do webhook PagBank inválida.'],JSON_UNESCAPED_UNICODE);
  exit;
}
$ret=pagbank_processar_webhook($pdo,$raw);
http_response_code(!empty($ret['ok']) ? 200 : 400);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($ret,JSON_UNESCAPED_UNICODE);
