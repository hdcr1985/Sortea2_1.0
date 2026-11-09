ALTER TABLE cab_usuario_verificacion
    ADD COLUMN uver_tipo VARCHAR(32) NOT NULL DEFAULT 'email_verification' AFTER uver_usuario_id,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (uver_usuario_id, uver_tipo);
