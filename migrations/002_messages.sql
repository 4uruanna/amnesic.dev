CREATE TABLE messages (
  id           CHAR(36) CHARACTER SET ascii NOT NULL,
  from_address VARCHAR(254) COLLATE utf8mb4_bin NOT NULL,
  from_name    VARCHAR(255) NULL,
  subject      VARCHAR(998) NOT NULL DEFAULT '',
  sent_at      DATETIME(3) NULL,
  received_at  DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  KEY messages_received_at (received_at)
);

CREATE TABLE recipients (
  message_id CHAR(36) CHARACTER SET ascii NOT NULL,
  address    VARCHAR(254) COLLATE utf8mb4_bin NOT NULL,
  name       VARCHAR(255) NULL,
  PRIMARY KEY (message_id, address),
  KEY recipients_address (address),
  FOREIGN KEY (message_id) REFERENCES messages (id) ON DELETE CASCADE
);

CREATE TABLE attachments (
  message_id   CHAR(36) CHARACTER SET ascii NOT NULL,
  part_id      SMALLINT UNSIGNED NOT NULL,
  filename     VARCHAR(255) NOT NULL,
  content_type VARCHAR(255) NOT NULL,
  size         INT UNSIGNED NOT NULL,
  PRIMARY KEY (message_id, part_id),
  FOREIGN KEY (message_id) REFERENCES messages (id) ON DELETE CASCADE
);
