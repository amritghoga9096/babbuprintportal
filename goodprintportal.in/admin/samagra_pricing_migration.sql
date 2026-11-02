-- Samagra Services Pricing Entries
-- Run this SQL in your phpMyAdmin / cPanel MySQL
-- INSERT IGNORE ensures existing entries will not be overwritten

INSERT IGNORE INTO `pricing` (`service_name`, `price`) VALUES
('samagra_pdf_fee', '20'),
('samagra_new_apply_fee', '150'),
('samagra_member_delete_fee', '150'),
('samagra_member_transfer_fee', '300'),
('samagra_married_divorce_fee', '350'),
('samagra_kyc_fee', '70'),
('samagra_new_family_id_fee', '250'),
('samagra_pravasan_fee', '1000'),
('samagra_merge_family_id_fee', '600'),
('samagra_recover_member_fee', '199');
