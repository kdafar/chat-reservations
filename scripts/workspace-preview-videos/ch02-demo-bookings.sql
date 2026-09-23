-- Chapter 2 is recorded on the LIVE workspace of the demo copy (SQLite), so it
-- needs two of today's bookings to check in. Demo DB only — never production.
--   sqlite3 <demo.sqlite> < ch02-demo-bookings.sql
-- Patient 5 = Maryam Al-Fadhli, patient 3 = Noura Al-Sabah, doctor 1 in room 1,
-- clinic package 1 = the demo's "Glow Package" offer.
DELETE FROM bookings WHERE booking_code IN ('RC2MAR', 'RC2NOU');
INSERT INTO bookings (branch_id, msisdn, party_size, res_date, res_time, status, booking_code, created_at, updated_at,
                      qr_token, table_id, res_start, res_end, doctor_id, patient_id, source, requested_package_id)
VALUES
 (1, '96599001122', 1, date('now', 'localtime') || ' 00:00:00', '10:30:00', 'confirmed', 'RC2MAR', datetime('now'), datetime('now'),
  '11111111-2222-4333-8444-555555555501', 1, date('now', 'localtime') || ' 10:30:00', date('now', 'localtime') || ' 11:00:00', 1, 5, 'call', NULL),
 (1, '96555443322', 1, date('now', 'localtime') || ' 00:00:00', '11:00:00', 'confirmed', 'RC2NOU', datetime('now'), datetime('now'),
  '11111111-2222-4333-8444-555555555502', 1, date('now', 'localtime') || ' 11:00:00', date('now', 'localtime') || ' 11:30:00', 1, 3, 'web', 1);
