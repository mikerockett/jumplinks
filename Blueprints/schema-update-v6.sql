ALTER TABLE
  <table-name>_nf
MODIFY
  COLUMN referrer VARCHAR(512),
MODIFY
  COLUMN request_uri VARCHAR(512),
MODIFY
  COLUMN user_agent VARCHAR(512);
