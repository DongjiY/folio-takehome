ALTER TABLE shares ADD COLUMN available_at TEXT;

UPDATE shares
SET available_at = created_at
WHERE available_at IS NULL;
