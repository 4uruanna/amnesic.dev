CREATE TABLE message_reads (
  account_id CHAR(36) CHARACTER SET ascii NOT NULL,
  message_id CHAR(36) CHARACTER SET ascii NOT NULL,
  PRIMARY KEY (account_id, message_id),
  KEY message_reads_message (message_id),
  FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE,
  FOREIGN KEY (message_id) REFERENCES messages (id) ON DELETE CASCADE
);
