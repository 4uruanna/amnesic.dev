CREATE TABLE accounts (
  id         CHAR(36) CHARACTER SET ascii NOT NULL,
  name       VARCHAR(100) NOT NULL,
  role       ENUM('admin', 'user', 'bot') NOT NULL,
  created_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY accounts_name (name)
);

CREATE TABLE tokens (
  id         CHAR(36) CHARACTER SET ascii NOT NULL,
  account_id CHAR(36) CHARACTER SET ascii NOT NULL,
  name       VARCHAR(100) NOT NULL,
  hash       CHAR(64) CHARACTER SET ascii NOT NULL, -- SHA-256 hexadécimal du jeton
  created_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY tokens_hash (hash),
  FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE
);

CREATE TABLE bindings (
  account_id CHAR(36) CHARACTER SET ascii NOT NULL,
  address    VARCHAR(254) COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (account_id, address),
  KEY bindings_address (address),
  FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE
);
