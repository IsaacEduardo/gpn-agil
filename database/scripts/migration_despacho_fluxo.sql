-- =============================================================================
-- Script SQL de Migração Incremental: Fluxo Operacional, Despacho e Expediente
-- =============================================================================

-- 1. Adicionar flag `is_area_expediente` na tabela `departamentos`
ALTER TABLE `departamentos`
ADD COLUMN `is_area_expediente` TINYINT(1) NOT NULL DEFAULT 0 AFTER `gabinete_id`;

-- 2. Adicionar campos de despacho na tabela `documentos_entradas`
ALTER TABLE `documentos_entradas`
ADD COLUMN `texto_despacho` TEXT NULL AFTER `observacoes`,
ADD COLUMN `despachado_por_id` BIGINT UNSIGNED NULL AFTER `texto_despacho`,
ADD COLUMN `data_despacho` DATETIME NULL AFTER `despachado_por_id`,
ADD CONSTRAINT `fk_documentos_entradas_despachado_por`
FOREIGN KEY (`despachado_por_id`) REFERENCES `users` (`id`)
ON DELETE SET NULL;

-- 3. Criar tabela pivô `documento_entrada_departamentos_destino`
CREATE TABLE IF NOT EXISTS `documento_entrada_departamentos_destino` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `documento_entrada_id` BIGINT UNSIGNED NOT NULL,
  `departamento_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `doc_dep_destino_unique` (`documento_entrada_id`, `departamento_id`),
  CONSTRAINT `fk_doc_dep_destino_doc` FOREIGN KEY (`documento_entrada_id`) REFERENCES `documentos_entradas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_doc_dep_destino_dep` FOREIGN KEY (`departamento_id`) REFERENCES `departamentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
