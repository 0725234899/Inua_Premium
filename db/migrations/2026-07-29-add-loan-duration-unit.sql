-- Migration: Add loan_duration_unit and interest_calculation to loan_applications
ALTER TABLE loan_applications
    ADD COLUMN IF NOT EXISTS loan_duration_unit VARCHAR(50) NOT NULL DEFAULT 'months',
    ADD COLUMN IF NOT EXISTS interest_calculation VARCHAR(50) NOT NULL DEFAULT 'monthly';

-- Ensure existing rows have default values
UPDATE loan_applications SET loan_duration_unit = 'months' WHERE loan_duration_unit IS NULL OR loan_duration_unit = '';
UPDATE loan_applications SET interest_calculation = 'monthly' WHERE interest_calculation IS NULL OR interest_calculation = '';
UPDATE loan_applications SET repayment_cycle = 'once' WHERE repayment_cycle IS NULL OR repayment_cycle = '';
UPDATE loan_applications SET number_of_repayments = 1 WHERE number_of_repayments IS NULL OR number_of_repayments <= 0;