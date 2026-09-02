-- =============================================================================
-- Script SQL de Migração de Dados: Catálogo de Procedências / Origem
-- =============================================================================

-- 1. Criar a tabela catálogo `procedencias`
CREATE TABLE IF NOT EXISTS `procedencias` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(255) NOT NULL UNIQUE,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Migração de Dados: Extrair valores distintos da coluna `procedencia`
-- e inseri-los na tabela de catálogo `procedencias`
INSERT IGNORE INTO `procedencias` (`nome`, `ativo`, `created_at`, `updated_at`)
SELECT DISTINCT TRIM(`procedencia`) AS `nome`, 1 AS `ativo`, NOW(), NOW()
FROM `documentos_entradas`
WHERE `procedencia` IS NOT NULL AND TRIM(`procedencia`) != '';

-- 3. Atualizar a tabela de documentos: Adicionar a chave estrangeira `procedencia_id`
ALTER TABLE `documentos_entradas`
ADD COLUMN `procedencia_id` BIGINT UNSIGNED NULL AFTER `procedencia`,
ADD CONSTRAINT `fk_documentos_entradas_procedencia`
FOREIGN KEY (`procedencia_id`) REFERENCES `procedencias` (`id`)
ON DELETE SET NULL;

-- 4. Popular `procedencia_id` com base no texto exato existente
UPDATE `documentos_entradas` d
INNER JOIN `procedencias` p ON TRIM(d.`procedencia`) = p.`nome`
SET d.`procedencia_id` = p.`id`;
