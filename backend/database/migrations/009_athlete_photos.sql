-- Foto do atleta: guardada no banco (entra no backup/restore junto com os dados)
-- e servida só pela API autenticada. Já chega aqui redimensionada e regravada
-- em JPEG pelo servidor (sem EXIF/GPS).

CREATE TABLE athlete_photos (
    athlete_id INT UNSIGNED NOT NULL PRIMARY KEY,
    mime       VARCHAR(20) NOT NULL DEFAULT 'image/jpeg',
    data       MEDIUMBLOB NOT NULL,
    bytes      INT UNSIGNED NOT NULL,
    version    CHAR(12) NOT NULL COMMENT 'hash curto do conteúdo (cache/ETag)',
    updated_by INT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_photo_athlete FOREIGN KEY (athlete_id) REFERENCES athletes (id) ON DELETE CASCADE,
    CONSTRAINT fk_photo_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
