-- Runs once, when the mariadb volume is created: a separate database for Pest.
CREATE DATABASE IF NOT EXISTS rent_radar_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON rent_radar_test.* TO 'rent_radar'@'%';
