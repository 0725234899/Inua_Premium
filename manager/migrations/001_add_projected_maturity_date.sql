-- Migration: add projected_maturity_date to loan_applications
-- Run this on your MySQL/MariaDB database connected to the application
ALTER TABLE loan_applications
ADD COLUMN IF NOT EXISTS projected_maturity_date DATE NULL;
