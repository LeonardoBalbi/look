<?php
/* =========================================================
   LOCX V8 - WHATSAPP BUSINESS API / CLOUD API
   - Modo demo para testes sem token real
   - Modo oficial via Graph API da Meta
   ========================================================= */
function whatsapp_config($pdo){
  try{
    $st=$pdo->query("SELECT * FROM whatsapp_config ORDER BY id DESC LIMIT 1");
    $cfg=$st->fetch(PDO::FETCH_ASSOC);
    if($cfg) return $cfg;
  }catch(Throwable $e){}
  return [
    'id'=>0,
    'modo'=>'demo',
    'ativo'=>1,
    'phone_number_id'=>'',
    'access_token'=>'',
    'verify_token'=>'locx_webhook_token',
    'template_cobranca'=>'locx_cobranca_atraso',
    'template_lembrete'=>'locx_lembrete_vencimento',
    'template_bloqueio'=>'locx_aviso_bloqueio'
  ];
}
function whatsapp_normalizar_telefone($telefone){
  $n=preg_replace('/\D+/', '', (string)$telefone);
  if($n && strlen($n)<=11) $n='55'.$n;
  return $n;
}
function whatsapp_graph_version(){
  $version=(string)config('services.whatsapp.graph_version','v25.0');
  return preg_match('/^v\d+\.\d+$/',$version) ? $version : 'v25.0';
}
function whatsapp_request($cfg,$method,$path,$payload=null){
  if(empty($cfg['access_token'])){
    return ['ok'=>false,'http_code'=>0,'erro'=>'Access Token da Meta não configurado.'];
  }
  $url='https://graph.facebook.com/'.whatsapp_graph_version().'/'.ltrim($path,'/');
  $headers=['Authorization: Bearer '.$cfg['access_token'],'Accept: application/json'];
  if($payload!==null) $headers[]='Content-Type: application/json';
  $ch=curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_HTTPHEADER=>$headers,
    CURLOPT_CUSTOMREQUEST=>$method,
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_CONNECTTIMEOUT=>10,
    CURLOPT_TIMEOUT=>30
  ]);
  if($payload!==null){
    curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
  }
  $res=curl_exec($ch);
  $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
  $err=curl_error($ch);
  curl_close($ch);
  $json=json_decode((string)$res,true);
  $ok=$http>=200 && $http<300;
  $apiError=$json['error']['message'] ?? null;
  return [
    'ok'=>$ok,
    'http_code'=>$http,
    'resposta'=>$res,
    'json'=>$json,
    'erro'=>$ok?null:($apiError ?: $err ?: $res ?: 'Falha ao acessar a API da Meta.')
  ];
}
function whatsapp_testar_conexao($pdo){
  $cfg=whatsapp_config($pdo);
  if(empty($cfg['ativo'])) return ['ok'=>false,'erro'=>'WhatsApp API inativa.'];
  if(($cfg['modo'] ?? 'demo') === 'demo'){
    return ['ok'=>true,'demo'=>true,'mensagem'=>'Modo demo ativo. Nenhuma chamada externa foi feita.'];
  }
  if(empty($cfg['phone_number_id']) || empty($cfg['access_token'])){
    return ['ok'=>false,'erro'=>'Informe o Phone Number ID e o Access Token da Meta.'];
  }
  $fields='id,display_phone_number,verified_name,quality_rating';
  $ret=whatsapp_request($cfg,'GET',rawurlencode($cfg['phone_number_id']).'?fields='.rawurlencode($fields));
  if($ret['ok']){
    $nome=$ret['json']['verified_name'] ?? 'conta WhatsApp';
    $numero=$ret['json']['display_phone_number'] ?? '';
    $ret['mensagem']='Conexão validada com '.$nome.($numero?' ('.$numero.')':'').'.';
  }
  return $ret;
}
function whatsapp_msg_cobranca($cliente,$placa,$saldo,$dias,$pix=''){
  $msg="Olá, {$cliente}. Identificamos uma pendência no seu contrato LocX";
  if($placa) $msg.=" referente à moto {$placa}";
  $msg.=".\n\nDias em atraso: {$dias}\nSaldo atualizado: ".moeda($saldo)."\n\n";
  if($pix) $msg.="PIX copia e cola:\n{$pix}\n\n";
  $msg.="Regularize o pagamento para evitar bloqueio e recolhimento da motocicleta.\n\nLocX - Gestão de Locação";
  return $msg;
}
function whatsapp_log($pdo,$dados){
  try{
    $pdo->prepare("INSERT INTO whatsapp_logs (cobranca_id,cliente_id,telefone,tipo,mensagem,status,http_code,resposta_api,erro,criado_em) VALUES (?,?,?,?,?,?,?,?,?,NOW())")
      ->execute([
        $dados['cobranca_id'] ?? null,
        $dados['cliente_id'] ?? null,
        $dados['telefone'] ?? null,
        $dados['tipo'] ?? 'cobranca',
        $dados['mensagem'] ?? '',
        $dados['status'] ?? 'pendente',
        $dados['http_code'] ?? null,
        $dados['resposta_api'] ?? null,
        $dados['erro'] ?? null
      ]);
  }catch(Throwable $e){}
}
function whatsapp_enviar_texto($pdo,$telefone,$mensagem,$meta=[]){
  $cfg=whatsapp_config($pdo);
  $telefone=whatsapp_normalizar_telefone($telefone);
  if(!$telefone){
    whatsapp_log($pdo, array_merge($meta,['telefone'=>$telefone,'mensagem'=>$mensagem,'status'=>'erro','erro'=>'Telefone inválido']));
    return ['ok'=>false,'erro'=>'Telefone inválido'];
  }
  if(empty($cfg['ativo'])){
    whatsapp_log($pdo, array_merge($meta,['telefone'=>$telefone,'mensagem'=>$mensagem,'status'=>'erro','erro'=>'WhatsApp API inativa']));
    return ['ok'=>false,'erro'=>'WhatsApp API inativa'];
  }
  if(($cfg['modo'] ?? 'demo') === 'demo'){
    whatsapp_log($pdo, array_merge($meta,['telefone'=>$telefone,'mensagem'=>$mensagem,'status'=>'demo','http_code'=>200,'resposta_api'=>'Envio simulado em modo demo']));
    return ['ok'=>true,'demo'=>true,'resposta'=>'Envio simulado com sucesso'];
  }
  if(empty($cfg['phone_number_id']) || empty($cfg['access_token'])){
    whatsapp_log($pdo, array_merge($meta,['telefone'=>$telefone,'mensagem'=>$mensagem,'status'=>'erro','erro'=>'Phone Number ID ou Access Token não configurado']));
    return ['ok'=>false,'erro'=>'Phone Number ID ou Access Token não configurado'];
  }
  $payload=[
    'messaging_product'=>'whatsapp',
    'recipient_type'=>'individual',
    'to'=>$telefone,
    'type'=>'text',
    'text'=>['preview_url'=>false,'body'=>$mensagem]
  ];
  $api=whatsapp_request($cfg,'POST',rawurlencode($cfg['phone_number_id']).'/messages',$payload);
  $res=$api['resposta'] ?? '';
  $http=(int)($api['http_code'] ?? 0);
  $ok=!empty($api['ok']);
  whatsapp_log($pdo, array_merge($meta,[
    'telefone'=>$telefone,
    'mensagem'=>$mensagem,
    'status'=>$ok?'enviado':'erro',
    'http_code'=>$http,
    'resposta_api'=>$res,
    'erro'=>$ok?null:($api['erro'] ?? $res)
  ]));
  return ['ok'=>$ok,'http_code'=>$http,'resposta'=>$res,'erro'=>$ok?null:($api['erro'] ?? $res)];
}
function whatsapp_enviar_template($pdo,$telefone,$template,$parametros=[],$meta=[],$idioma='pt_BR'){
  $cfg=whatsapp_config($pdo);
  $telefone=whatsapp_normalizar_telefone($telefone);
  $mensagem='Template '.$template.' | '.implode(' | ',array_map('strval',$parametros));
  if(!$telefone){
    whatsapp_log($pdo,array_merge($meta,['telefone'=>$telefone,'mensagem'=>$mensagem,'status'=>'erro','erro'=>'Telefone inválido']));
    return ['ok'=>false,'erro'=>'Telefone inválido'];
  }
  if(empty($cfg['ativo'])){
    whatsapp_log($pdo,array_merge($meta,['telefone'=>$telefone,'mensagem'=>$mensagem,'status'=>'erro','erro'=>'WhatsApp API inativa']));
    return ['ok'=>false,'erro'=>'WhatsApp API inativa'];
  }
  if(($cfg['modo'] ?? 'demo') === 'demo'){
    whatsapp_log($pdo,array_merge($meta,['telefone'=>$telefone,'mensagem'=>$mensagem,'status'=>'demo','http_code'=>200,'resposta_api'=>'Envio de template simulado em modo demo']));
    return ['ok'=>true,'demo'=>true,'resposta'=>'Envio simulado com sucesso'];
  }
  if(empty($cfg['phone_number_id']) || empty($cfg['access_token']) || trim((string)$template)===''){
    $erro='Phone Number ID, Access Token ou nome do template não configurado';
    whatsapp_log($pdo,array_merge($meta,['telefone'=>$telefone,'mensagem'=>$mensagem,'status'=>'erro','erro'=>$erro]));
    return ['ok'=>false,'erro'=>$erro];
  }
  $params=[];
  foreach($parametros as $valor){
    $params[]=['type'=>'text','text'=>(string)$valor];
  }
  $payload=[
    'messaging_product'=>'whatsapp',
    'to'=>$telefone,
    'type'=>'template',
    'template'=>[
      'name'=>trim((string)$template),
      'language'=>['code'=>$idioma],
      'components'=>[['type'=>'body','parameters'=>$params]]
    ]
  ];
  $api=whatsapp_request($cfg,'POST',rawurlencode($cfg['phone_number_id']).'/messages',$payload);
  $ok=!empty($api['ok']);
  whatsapp_log($pdo,array_merge($meta,[
    'telefone'=>$telefone,
    'mensagem'=>$mensagem,
    'status'=>$ok?'enviado':'erro',
    'http_code'=>$api['http_code'] ?? 0,
    'resposta_api'=>$api['resposta'] ?? '',
    'erro'=>$ok?null:($api['erro'] ?? 'Falha ao enviar template')
  ]));
  return $api;
}
function whatsapp_enviar_cobranca($pdo,$cobranca_id){
  $st=$pdo->prepare('SELECT cb.*, cl.nome, cl.whatsapp, m.placa FROM cobrancas cb JOIN clientes cl ON cl.id=cb.cliente_id LEFT JOIN contratos ct ON ct.id=cb.contrato_id LEFT JOIN motocicletas m ON m.id=ct.motocicleta_id WHERE cb.id=?');
  $st->execute([$cobranca_id]);
  $r=$st->fetch(PDO::FETCH_ASSOC);
  if(!$r) return ['ok'=>false,'erro'=>'Cobrança não encontrada'];
  $dias=dias_atraso_ate_domingo($r['vencimento']);
  $saldo=valor_atualizado($r['valor_principal'],$r['valor_pago'],$r['vencimento']);
  $msg=whatsapp_msg_cobranca($r['nome'],$r['placa'],$saldo,$dias,$r['pix_copia_cola'] ?? '');
  $meta=['cobranca_id'=>$r['id'],'cliente_id'=>$r['cliente_id'],'tipo'=>'cobranca_inadimplencia'];
  $cfg=whatsapp_config($pdo);
  if(($cfg['modo'] ?? 'demo') === 'demo'){
    $ret=whatsapp_enviar_texto($pdo,$r['whatsapp'],$msg,$meta);
  }else{
    $ret=whatsapp_enviar_template($pdo,$r['whatsapp'],$cfg['template_cobranca'] ?? '',[
      $r['nome'],
      $r['placa'] ?: 'não informada',
      (string)$dias,
      moeda($saldo),
      $r['pix_copia_cola'] ?: 'não disponível'
    ],$meta);
  }
  if(!empty($ret['ok'])){
    try{$pdo->prepare('UPDATE cobrancas SET whatsapp_status=? WHERE id=?')->execute([!empty($ret['demo'])?'demo':'enviado',$r['id']]);}catch(Throwable $e){}
  }
  return $ret;
}
?>
