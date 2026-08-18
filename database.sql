DROP DATABASE IF EXISTS semej_corridas;
CREATE DATABASE semej_corridas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE semej_corridas;

CREATE TABLE usuario (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 `user` VARCHAR(120) NOT NULL UNIQUE,
 nome VARCHAR(180) NULL,
 telefone VARCHAR(30) NULL,
 endereco VARCHAR(255) NULL,
 email VARCHAR(180) NULL,
 cpf VARCHAR(11) NULL,
 senha_crip VARCHAR(255) NOT NULL,
 tipo_user ENUM('admin','organizador','convidado') NOT NULL DEFAULT 'convidado'
) ENGINE=InnoDB;

CREATE TABLE corrida (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 protocolo VARCHAR(24) NULL UNIQUE,
 usuario_id INT UNSIGNED NULL,
 nome_evento VARCHAR(180) NOT NULL,
 local_ini VARCHAR(60) NOT NULL COMMENT 'latitude,longitude',
 local_fin VARCHAR(60) NOT NULL COMMENT 'latitude,longitude',
 categoria VARCHAR(100) NOT NULL,
 desc_corrida TEXT NOT NULL,
 organizador VARCHAR(180) NOT NULL,
 trajeto_json LONGTEXT NULL COMMENT 'Coordenadas GeoJSON do trajeto calculado pelas ruas',
 percurso_km DECIMAL(8,2) NOT NULL DEFAULT 0,
 dia_ini DATE NOT NULL,
 dia_fin DATE NOT NULL,
 hora_ini TIME NULL,
 hora_fin TIME NULL,
 status ENUM('enviada','em_analise','alteracao_solicitada','aprovada','rejeitada') NOT NULL DEFAULT 'enviada',
 retorno TEXT NULL,
 alvara_arquivo VARCHAR(255) NULL,
 alvara_enviado_em DATETIME NULL,
 alvara_usuario_id INT UNSIGNED NULL,
 alvara_status ENUM('pendente','enviado','confirmado_sem_anexo','indeferido') NOT NULL DEFAULT 'pendente',
 alvara_motivo TEXT NULL,
 CONSTRAINT fk_corrida_usuario FOREIGN KEY(usuario_id) REFERENCES usuario(id) ON DELETE SET NULL,
 CONSTRAINT fk_alvara_usuario FOREIGN KEY(alvara_usuario_id) REFERENCES usuario(id) ON DELETE SET NULL,
 INDEX idx_periodo_evento(dia_ini,dia_fin), INDEX idx_corrida_usuario(usuario_id), INDEX idx_corrida_status(status)
) ENGINE=InnoDB;

CREATE TABLE solicitacao_historico (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,corrida_id INT UNSIGNED NOT NULL,usuario_id INT UNSIGNED NULL,status VARCHAR(40) NOT NULL,mensagem TEXT NULL,criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(corrida_id) REFERENCES corrida(id) ON DELETE CASCADE,FOREIGN KEY(usuario_id) REFERENCES usuario(id) ON DELETE SET NULL) ENGINE=InnoDB;

CREATE TABLE aviso (id TINYINT UNSIGNED PRIMARY KEY, mensagem TEXT NULL, ativo TINYINT(1) NOT NULL DEFAULT 0, atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB;
INSERT INTO aviso(id,mensagem,ativo) VALUES(1,NULL,0);
DELIMITER //
CREATE TRIGGER corrida_protocolo_bi BEFORE INSERT ON corrida FOR EACH ROW
BEGIN IF NEW.protocolo IS NULL OR NEW.protocolo='' THEN SET NEW.protocolo=CONCAT('COR-',YEAR(CURDATE()),'-',UPPER(HEX(RANDOM_BYTES(3)))); END IF; END//
DELIMITER ;

-- Super Admin (senha: Admin@123)
INSERT INTO usuario(`user`,senha_crip,tipo_user) VALUES
('admin','{SHA256}e86f78a8a3caf0b60d8e74e5942aa6d86dc150cd3c03338aef25b7d2d7e3acc7','admin');
