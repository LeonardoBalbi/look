<?php
/* =========================================================
   LOCX V9 - INTEGRAÇÃO PAGBANK / PIX
   - Modo demo para testes sem credencial real
   - Modo oficial usando API de Pedidos PagBank
   ========================================================= */
function pagbank_config($pdo){
  try{
    $st=$pdo->query("SELECT * FROM pagbank_config ORDER BY id DESC LIMIT 1");
    $cfg=$st->fetch(PDO::FETCH_ASSOC);
    if($cfg) return $cfg;
  }catch(Throwable $e){}
  return [
    'id'=>0,
    'modo'=>'demo',
    'ambiente'=>'sandbox',
    'ativo'=>1,
    'client_id'=>'',
    'client_secret'=>'',
    'access_token'=>'',
    'webhook_url'=>'',
    'merchant_reference'=>'LOCX'
  ];
}
function pagbank_base_url($cfg){
  return (($cfg['ambiente'] ?? 'sandbox') === 'producao') ? 'https://api.pagseguro.com' : 'https://sandbox.api.pagseguro.com';
}
function pagbank_limpar_doc($v){ return preg_replace('/\D+/', '', (string)$v); }
function pagbank_valor_centavos($v){ return (int)round(((float)$v)*100); }
function pagbank_webhook_padrao(){
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'seudominio.com.br';
  $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/locx/index.php'), '/\\');
  if(substr($base,-9)==='/webhooks') $base = dirname($base);
  return $https.'://'.$host.$base.'/webhooks/pagbank.php';
}
function pagbank_request($pdo,$method,$path,$payload=null,$extraHeaders=[]){
  $cfg=pagbank_config($pdo);
  if(empty($cfg['access_token'])) return ['ok'=>false,'http_code'=>0,'erro'=>'Access Token PagBank não configurado.'];
  $url=pagbank_base_url($cfg).$path;
  $headers=array_merge(['Authorization: Bearer '.$cfg['access_token'],'Content-Type: application/json','Accept: application/json'],$extraHeaders);
  $ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>40,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers]);
  if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
  $res=curl_exec($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
  $json=json_decode((string)$res,true);
  $ok=$http>=200 && $http<300;
  return ['ok'=>$ok,'http_code'=>$http,'resposta'=>$res,'json'=>$json,'erro'=>$ok?null:($err ?: $res)];
}
function pagbank_log($pdo,$dados){
  try{
    $pdo->prepare("INSERT INTO pagbank_logs (cobranca_id,tipo,status,http_code,payload,resposta_api,erro,criado_em) VALUES (?,?,?,?,?,?,?,NOW())")
      ->execute([$dados['cobranca_id']??null,$dados['tipo']??'evento',$dados['status']??'pendente',$dados['http_code']??null,$dados['payload']??null,$dados['resposta_api']??null,$dados['erro']??null]);
  }catch(Throwable $e){}
}
function pagbank_extrair_pix($json){
  $out=['qr_code'=>'','qr_code_base64'=>''];
  if(!is_array($json)) return $out;
  if(!empty($json['qr_codes'][0]['text'])) $out['qr_code']=$json['qr_codes'][0]['text'];
  if(!empty($json['qr_codes'][0]['links'])){
    foreach($json['qr_codes'][0]['links'] as $l){
      if(($l['rel']??'')==='QRCODE.PNG' || str_contains(($l['href']??''),'base64')) $out['qr_code_base64']=$l['href'];
    }
  }
  return $out;
}
function pagbank_criar_pix($pdo,$cobranca_id){
  $st=$pdo->prepare('SELECT cb.*, cl.nome, cl.email, cl.cpf, cl.telefone, cl.whatsapp FROM cobrancas cb JOIN clientes cl ON cl.id=cb.cliente_id WHERE cb.id=?');
  $st->execute([$cobranca_id]); $c=$st->fetch(PDO::FETCH_ASSOC);
  if(!$c) return ['ok'=>false,'erro'=>'Cobrança não encontrada.'];
  $cfg=pagbank_config($pdo);
  $valor=valor_atualizado($c['valor_principal'],$c['valor_pago'],$c['vencimento']);
  $reference='LOCX-COBRANCA-'.$c['id'];
  if(empty($cfg['ativo'])) return ['ok'=>false,'erro'=>'Integração PagBank inativa.'];
  if(($cfg['modo'] ?? 'demo') === 'demo'){
    $pix='00020126580014BR.GOV.BCB.PIX0136LOCX-DEMO-COBRANCA-'.$c['id'].'520400005303986540'.number_format($valor,2,'.','').'5802BR5904LOCX6009MANGARATIBA62070503***6304DEMO';
    $pdo->prepare('UPDATE cobrancas SET pix_copia_cola=?, pix_qrcode=?, pagbank_order_id=?, pagbank_status=?, pagbank_payload=?, atualizado_em=NOW() WHERE id=?')
      ->execute([$pix,$pix,'DEMO-'.$c['id'],'DEMO','PIX demo gerado pelo LocX',$c['id']]);
    pagbank_log($pdo,['cobranca_id'=>$c['id'],'tipo'=>'criar_pix','status'=>'demo','http_code'=>200,'payload'=>'demo','resposta_api'=>$pix]);
    return ['ok'=>true,'demo'=>true,'pix'=>$pix,'order_id'=>'DEMO-'.$c['id']];
  }
  $documento=pagbank_limpar_doc($c['cpf']);
  if(!in_array(strlen($documento),[11,14],true)){
    return ['ok'=>false,'erro'=>'O cliente precisa ter CPF ou CNPJ válido para gerar o PIX PagBank.'];
  }
  if(empty($c['email']) || !filter_var($c['email'],FILTER_VALIDATE_EMAIL)){
    return ['ok'=>false,'erro'=>'O cliente precisa ter um e-mail válido para gerar o PIX PagBank.'];
  }
  if($valor<=0){
    return ['ok'=>false,'erro'=>'O valor da cobrança precisa ser maior que zero.'];
  }
  $webhook=$cfg['webhook_url'] ?: pagbank_webhook_padrao();
  $payload=[
    'reference_id'=>$reference,
    'customer'=>[
      'name'=>$c['nome'] ?: 'Cliente LocX',
      'email'=>$c['email'],
      'tax_id'=>$documento
    ],
    'items'=>[[
      'reference_id'=>(string)$c['id'],
      'name'=>'Cobrança LocX #'.$c['id'],
      'quantity'=>1,
      'unit_amount'=>pagbank_valor_centavos($valor)
    ]],
    'qr_codes'=>[[
      'amount'=>['value'=>pagbank_valor_centavos($valor)],
      'expiration_date'=>date('c', strtotime('+7 days'))
    ]],
    'notification_urls'=>[$webhook]
  ];
  $idempotency=hash('sha256','locx|'.$reference.'|'.pagbank_valor_centavos($valor));
  $ret=pagbank_request($pdo,'POST','/orders',$payload,['x-idempotency-key: '.$idempotency]);
  pagbank_log($pdo,['cobranca_id'=>$c['id'],'tipo'=>'criar_pix','status'=>$ret['ok']?'enviado':'erro','http_code'=>$ret['http_code'],'payload'=>json_encode($payload,JSON_UNESCAPED_UNICODE),'resposta_api'=>$ret['resposta']??null,'erro'=>$ret['erro']??null]);
  if(!$ret['ok']) return $ret;
  $json=$ret['json']; $pix=pagbank_extrair_pix($json);
  $order_id=$json['id'] ?? null;
  $pdo->prepare('UPDATE cobrancas SET pix_copia_cola=?, pix_qrcode=?, pagbank_order_id=?, pagbank_status=?, pagbank_payload=?, atualizado_em=NOW() WHERE id=?')
    ->execute([$pix['qr_code'],$pix['qr_code_base64'] ?: $pix['qr_code'],$order_id,$json['status'] ?? 'CREATED',$ret['resposta'],$c['id']]);
  return ['ok'=>true,'order_id'=>$order_id,'pix'=>$pix['qr_code'],'resposta'=>$ret['resposta']];
}
function pagbank_baixar_cobranca($pdo,$cobranca_id,$valor_pago,$forma='pix',$pagbank_status='PAID'){
  $cst=$pdo->prepare('SELECT * FROM cobrancas WHERE id=?'); $cst->execute([$cobranca_id]); $c=$cst->fetch(PDO::FETCH_ASSOC);
  if(!$c) return false;
  $dup=$pdo->prepare("SELECT COUNT(*) FROM pagamentos WHERE cobranca_id=? AND comprovante LIKE 'PagBank %'");
  $dup->execute([$cobranca_id]);
  if((int)$dup->fetchColumn()>0){
    $pdo->prepare('UPDATE cobrancas SET pagbank_status=?, atualizado_em=NOW() WHERE id=?')->execute([$pagbank_status,$cobranca_id]);
    return true;
  }
  $ja=(float)$c['valor_pago']; $valor=(float)$valor_pago;
  if($valor<=0) $valor=max(0,(float)$c['valor_atualizado']-$ja);
  $novo=$ja+$valor; $status=$novo >= (float)$c['valor_principal'] ? 'paga' : 'parcial';
  $pdo->prepare('INSERT INTO pagamentos (cobranca_id,valor,forma,pago_em,comprovante) VALUES (?,?,?,?,?)')->execute([$cobranca_id,$valor,$forma,date('Y-m-d H:i:s'),'PagBank '.$pagbank_status]);
  $pdo->prepare('UPDATE cobrancas SET valor_pago=?, status=?, pagbank_status=?, whatsapp_status=IF(?="paga","conciliado",whatsapp_status), atualizado_em=NOW() WHERE id=?')->execute([$novo,$status,$pagbank_status,$status,$cobranca_id]);
  return true;
}
function pagbank_processar_webhook($pdo,$raw){
  $json=json_decode($raw,true); if(!is_array($json)) return ['ok'=>false,'erro'=>'JSON inválido'];
  $reference=$json['reference_id'] ?? '';
  $order_id=$json['id'] ?? ($json['order']['id'] ?? '');
  $status=$json['status'] ?? ($json['charges'][0]['status'] ?? '');
  $cobranca_id=null;
  if(preg_match('/LOCX-COBRANCA-(\d+)/',$reference,$m)) $cobranca_id=(int)$m[1];
  if(!$cobranca_id && $order_id){
    $st=$pdo->prepare('SELECT id FROM cobrancas WHERE pagbank_order_id=? LIMIT 1'); $st->execute([$order_id]); $cobranca_id=(int)$st->fetchColumn();
  }
  pagbank_log($pdo,['cobranca_id'=>$cobranca_id,'tipo'=>'webhook','status'=>$status ?: 'recebido','http_code'=>200,'payload'=>$raw,'resposta_api'=>'webhook recebido']);
  if(!$cobranca_id) return ['ok'=>false,'erro'=>'Cobrança não localizada'];
  $paidStatuses=['PAID','AVAILABLE','AUTHORIZED','COMPLETED','RECEIVED'];
  if(in_array(strtoupper($status),$paidStatuses,true)){
    $valor=0;
    if(isset($json['charges'][0]['amount']['value'])) $valor=((float)$json['charges'][0]['amount']['value'])/100;
    elseif(isset($json['qr_codes'][0]['amount']['value'])) $valor=((float)$json['qr_codes'][0]['amount']['value'])/100;
    pagbank_baixar_cobranca($pdo,$cobranca_id,$valor,'pix','PAGBANK_'.$status);
    return ['ok'=>true,'baixado'=>true];
  }
  try{$pdo->prepare('UPDATE cobrancas SET pagbank_status=?, atualizado_em=NOW() WHERE id=?')->execute([$status,$cobranca_id]);}catch(Throwable $e){}
  return ['ok'=>true,'baixado'=>false,'status'=>$status];
}
function pagbank_validar_assinatura_webhook($pdo,$raw,$assinatura){
  $cfg=pagbank_config($pdo);
  if(($cfg['modo'] ?? 'demo') === 'demo') return true;
  $token=(string)($cfg['access_token'] ?? '');
  $assinatura=trim((string)$assinatura);
  if($token==='' || $assinatura==='') return false;
  $esperada=hash('sha256',$token.'-'.$raw);
  return hash_equals(strtolower($esperada),strtolower($assinatura));
}
function pagbank_testar($pdo){
  $cfg=pagbank_config($pdo);

  if(($cfg['modo'] ?? 'demo') === 'demo'){
    return [
      'ok'=>true,
      'demo'=>true,
      'mensagem'=>'Modo demo ativo. Nenhuma chamada externa foi feita.'
    ];
  }

  if(empty($cfg['access_token'])){
    return [
      'ok'=>false,
      'http_code'=>0,
      'erro'=>'Access Token PagBank não configurado.'
    ];
  }

  // Consulta sem efeito financeiro. Um token aceito pode retornar 400/404
  // porque o charge_id de teste não existe; 401/403 indica credencial inválida.
  $charge='CHAR_00000000-0000-0000-0000-000000000000';
  $ret=pagbank_request($pdo,'GET','/orders?charge_id='.rawurlencode($charge));
  $http=(int)($ret['http_code'] ?? 0);
  if(in_array($http,[200,400,404],true)){
    return [
      'ok'=>true,
      'http_code'=>$http,
      'mensagem'=>'A API PagBank respondeu e aceitou a autenticação. O teste real do PIX é feito em uma cobrança.'
    ];
  }
  return $ret;
}
?>
