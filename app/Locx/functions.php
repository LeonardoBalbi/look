<?php
if (!function_exists('e')) {
  function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
function moeda($v){ return 'R$ '.number_format((float)$v,2,',','.'); }
if (!function_exists('locx_redirect')) {
  function locx_redirect($url){ header('Location: '.$url); exit; }
}
if (!function_exists('locx_asset')) {
  function locx_asset($path){
    $relative = 'locx/'.ltrim((string)$path, '/');
    $url = asset($relative);
    $file = public_path(str_replace('/', DIRECTORY_SEPARATOR, $relative));
    return is_file($file) ? $url.'?v='.filemtime($file) : $url;
  }
}
function perfil_nome($p){
  $m=['administrador_geral'=>'Administrador Geral','diretor'=>'Diretor','financeiro'=>'Financeiro','gerente_loja'=>'Gerente de Loja','atendente'=>'Atendente','cobranca'=>'Cobrança'];
  return $m[$p] ?? $p;
}
function modulos_sistema(){ return [
  'dashboard'=>'Dashboard',
  'clientes'=>'Clientes',
  'motos'=>'Motocicletas',
  'contratos'=>'Contratos',
  'financeiro'=>'Financeiro',
  'cobrancas'=>'Cobranças',
  'inadimplencia'=>'Inadimplência',
  'pix'=>'PIX',
  'pagbank'=>'PagBank',
  'whatsapp'=>'WhatsApp API',
  'relatorios'=>'Relatórios',
  'lojas'=>'Lojas / Unidades',
  'usuarios'=>'Usuários',
  'configuracoes'=>'Configurações'
]; }
function acoes_sistema(){ return ['visualizar'=>'Visualizar','criar'=>'Criar','editar'=>'Editar','excluir'=>'Excluir']; }
function upload_file($field){
  if(empty($_FILES[$field]['name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
  $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf'];
  $mime = mime_content_type($_FILES[$field]['tmp_name']);
  if(!isset($allowed[$mime])) return null;
  $dir = public_path('locx/uploads/clientes/'); if(!is_dir($dir)) mkdir($dir,0775,true);
  $name = $field.'_'.date('YmdHis').'_'.bin2hex(random_bytes(4)).'.'.$allowed[$mime];
  $dest = $dir.$name;
  move_uploaded_file($_FILES[$field]['tmp_name'], $dest);
  return 'uploads/clientes/'.$name;
}
function dias_atraso_ate_domingo($vencimento){
  $v = new DateTime($vencimento); $hoje = new DateTime('today');
  if($hoje <= $v) return 0;
  $dias = (int)$v->diff($hoje)->days;
  $domingo = clone $v; while($domingo->format('w') != 0) $domingo->modify('+1 day');
  if($hoje > $domingo) $dias = (int)$v->diff($domingo)->days;
  return max(0,$dias);
}
function valor_atualizado($principal,$pago,$vencimento){
  $principal=(float)$principal; $pago=(float)$pago; $saldo = max(0, $principal - $pago);
  $dias = dias_atraso_ate_domingo($vencimento);
  if($dias <= 0) return $saldo;
  if($pago > 0) return round($saldo * pow(1.10, $dias),2);
  return round($principal * (1 + 0.10*$dias),2);
}
function tag_status($s){$cls=['ativo'=>'ok','ativa'=>'ok','disponivel'=>'ok','paga'=>'ok','alugada'=>'info','aberta'=>'warn','parcial'=>'warn','inadimplente'=>'danger','atrasada'=>'danger','bloqueado'=>'danger','manutencao'=>'warn','recuperacao'=>'danger','encerrado'=>'muted','encerrada'=>'muted','suspenso'=>'warn','inativa'=>'danger','enviado'=>'info','conciliado'=>'ok','pendente'=>'warn'][$s]??'';return '<span class="tag '.$cls.'">'.e($s).'</span>';}
function icon($p){
  // Ícones minimalistas em SVG outline, sem preenchimento preto.
  $base = 'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
  $icons=[
    'dashboard'=>'<svg '.$base.'><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></svg>',
    'clientes'=>'<svg '.$base.'><circle cx="9" cy="8" r="3.2"/><path d="M3.5 20c.7-3.6 2.8-5.4 5.5-5.4s4.8 1.8 5.5 5.4"/><circle cx="17" cy="9" r="2.4"/><path d="M15.3 15.2c2.6.2 4.2 1.7 5.2 4.8"/></svg>',
    'motos'=>'<svg '.$base.'><circle cx="6.5" cy="17" r="3"/><circle cx="17.5" cy="17" r="3"/><path d="M9.5 17h4.5l-2-5H9.2l-2.7 5"/><path d="M12 12h3.3l2.2 5"/><path d="M13.7 8h3.8"/><path d="M15.5 8l1.2 4"/></svg>',
    'contratos'=>'<svg '.$base.'><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v5h5"/><path d="M9.5 13h5"/><path d="M9.5 17h5"/></svg>',
    'financeiro'=>'<svg '.$base.'><path d="M4 19h16"/><path d="M6 16l4-4 3 3 5-7"/><path d="M16 8h2v2"/></svg>',
    'cobrancas'=>'<svg '.$base.'><rect x="3" y="6" width="18" height="12" rx="2.5"/><path d="M3 10h18"/><path d="M7 15h5"/></svg>',
    'inadimplencia'=>'<svg '.$base.'><path d="M12 3 22 20H2z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
    'pix'=>'<svg '.$base.'><path d="M12 3.5 20.5 12 12 20.5 3.5 12z"/><path d="M8.5 12h7"/><path d="M12 8.5v7"/></svg>',
    'whatsapp'=>'<svg '.$base.'><path d="M20.5 11.7a8.5 8.5 0 0 1-12.6 7.4L3.5 20.5l1.4-4.2A8.5 8.5 0 1 1 20.5 11.7z"/><path d="M9 8.8c.2 3.5 2.1 5.4 5.4 6.2l1.5-1.5-2.2-1.2-.8.8c-1.1-.5-2-1.3-2.5-2.5l.8-.8L10 7.5z"/></svg>',
    'relatorios'=>'<svg '.$base.'><path d="M4 20V4"/><path d="M4 20h16"/><path d="M8 16v-5"/><path d="M12 16V8"/><path d="M16 16v-9"/></svg>',
    'usuarios'=>'<svg '.$base.'><circle cx="8.5" cy="8" r="3"/><path d="M3 20c.7-3.7 2.8-5.5 5.5-5.5S13.3 16.3 14 20"/><circle cx="16.5" cy="9" r="2.5"/><path d="M15 14.8c2.4.3 4.3 2 5 5.2"/></svg>',
    'lojas'=>'<svg '.$base.'><path d="M4 10h16l-1.5-5h-13z"/><path d="M5.5 10v10h13V10"/><path d="M9 20v-6h6v6"/></svg>',
    'configuracoes'=>'<svg '.$base.'><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.8 1.8 0 0 0 .3 2l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.8 1.8 0 0 0-2-.3 1.8 1.8 0 0 0-1 1.6V22h-4v-.1a1.8 1.8 0 0 0-1-1.6 1.8 1.8 0 0 0-2 .3l-.1.1A2 2 0 1 1 4 17.9l.1-.1a1.8 1.8 0 0 0 .3-2 1.8 1.8 0 0 0-1.6-1H2v-4h.8a1.8 1.8 0 0 0 1.6-1 1.8 1.8 0 0 0-.3-2L4 7.7A2 2 0 1 1 6.8 4.9l.1.1a1.8 1.8 0 0 0 2 .3 1.8 1.8 0 0 0 1-1.6V3.5h4v.2a1.8 1.8 0 0 0 1 1.6 1.8 1.8 0 0 0 2-.3l.1-.1A2 2 0 1 1 19.8 7.7l-.1.1a1.8 1.8 0 0 0-.3 2 1.8 1.8 0 0 0 1.6 1h1v4h-1a1.8 1.8 0 0 0-1.6.9z"/></svg>'
  ];
  return $icons[$p] ?? '<svg '.$base.'><circle cx="12" cy="12" r="4"/></svg>';
}

function op_lojas($lojas,$sel){ foreach($lojas as $l) echo '<option value="'.$l['id'].'" '.($sel==$l['id']?'selected':'').'>'.e($l['nome']).'</option>'; }
function pct($v,$t){ return $t>0 ? round(($v/$t)*100,1) : 0; }
