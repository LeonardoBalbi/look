-- LOCX V36 - alternativa para hospedagens sem acesso ao comando Artisan.
-- Importe este arquivo somente se nao puder executar: php artisan migrate --force

CREATE TABLE IF NOT EXISTS `portal_cliente_mensagens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `cliente_id` int unsigned NOT NULL,
  `loja_id` int unsigned DEFAULT NULL,
  `usuario_id` int unsigned DEFAULT NULL,
  `tipo` varchar(30) NOT NULL DEFAULT 'informacao',
  `assunto` varchar(160) NOT NULL,
  `mensagem` text NOT NULL,
  `enviada_em` datetime NOT NULL,
  `lida_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pcm_cliente_id_index` (`cliente_id`),
  KEY `pcm_loja_id_index` (`loja_id`),
  KEY `pcm_usuario_id_index` (`usuario_id`),
  KEY `pcm_tipo_index` (`tipo`),
  KEY `pcm_enviada_em_index` (`enviada_em`),
  KEY `pcm_lida_em_index` (`lida_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
