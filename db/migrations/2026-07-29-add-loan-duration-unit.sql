-- Migration: Add loan_duration_unit to loan_applications
ALTER TABLE loan_applications
    ADD COLUMN IF NOT EXISTS loan_duration_unit VARCHAR(50) NOT NULL DEFAULT 'months';

-- Ensure existing rows have a default value
UPDATE loan_applications SET loan_duration_unit = 'months' WHERE loan_duration_unit IS NULL OR loan_duration_unit = '';