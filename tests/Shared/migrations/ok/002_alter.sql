ALTER TABLE migrator_ok ADD COLUMN label VARCHAR(10) NULL;
UPDATE migrator_ok SET label = 'b';
