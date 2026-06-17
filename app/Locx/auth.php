<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__.'/database.php';
require_once __DIR__.'/functions.php';
require_once __DIR__.'/pagbank.php';
function usuario(){ return $_SESSION['usuario'] ?? null; }
function exigir_login(){ if(!usuario()) locx_redirect('login.php'); }
function eh_admin(){
  $u=usuario();
  if(!$u) return false;
  $perfil = strtolower(trim((string)($u['perfil'] ?? '')));
  return in_array($perfil, ['administrador_geral','diretor','admin','administrador'], true);
}
function carregar_permissoes_usuario($pdo,$usuario_id){
  $st=$pdo->prepare('SELECT modulo,acao FROM usuario_permissoes WHERE usuario_id=?');
  $st->execute([$usuario_id]);
  $perms=[]; foreach($st as $r){ $perms[$r['modulo']][$r['acao']]=true; }
  return $perms;
}
function pode($modulo,$acao='visualizar'){
  $u=usuario();
  if(!$u) return false;
  $perfil = strtolower(trim((string)($u['perfil'] ?? '')));
  if(in_array($perfil, ['administrador_geral','diretor','admin','administrador'], true)) return true;
  return !empty($u['permissoes'][$modulo][$acao]);
}
function exigir_perm($modulo,$acao='visualizar'){ if(!pode($modulo,$acao)) die('Acesso negado para este módulo.'); }
function lojas_usuario_ids(){ $u=usuario(); return $u['lojas_ids'] ?? []; }
function filtro_loja_sql($alias=''){
  $u=usuario();
  $perfil = strtolower(trim((string)($u['perfil'] ?? '')));
  if(!$u || in_array($perfil, ['administrador_geral','diretor','admin','administrador'], true)) return ['sql'=>'','params'=>[]];
  $ids=lojas_usuario_ids(); if(!$ids) return ['sql'=>' AND 1=0','params'=>[]];
  $pref=$alias ? $alias.'.' : '';
  return ['sql'=>' AND '.$pref.'loja_id IN ('.implode(',',array_fill(0,count($ids),'?')).')','params'=>$ids];
}
