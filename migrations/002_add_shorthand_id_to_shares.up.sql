ALTER TABLE shares ADD COLUMN shorthand_id TEXT;

CREATE UNIQUE INDEX idx_shares_shorthand_id ON shares(shorthand_id);
