-- Add package_id to redeem_keys to map keys to specific product package
ALTER TABLE redeem_keys
  ADD COLUMN package_id INT NULL AFTER product_id;

-- Optional: prevent duplicate key codes
ALTER TABLE redeem_keys
  ADD UNIQUE KEY uq_redeem_key_code (key_code);

-- Helpful index for assignment queries
CREATE INDEX idx_redeem_keys_prod_pkg_status ON redeem_keys(product_id, package_id, status, key_id);

-- Note: For existing generic keys, leave package_id as NULL.
-- Assignment prefers exact package match, then falls back to NULL pool.

