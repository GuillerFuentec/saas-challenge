CREATE DATABASE IF NOT EXISTS client1_db;
CREATE DATABASE IF NOT EXISTS client2_db;

CREATE USER IF NOT EXISTS 'client1_user'@'%' IDENTIFIED WITH mysql_native_password BY 'client1_pass';
CREATE USER IF NOT EXISTS 'client2_user'@'%' IDENTIFIED WITH mysql_native_password BY 'client2_pass';

GRANT ALL PRIVILEGES ON client1_db.* TO 'client1_user'@'%';
GRANT ALL PRIVILEGES ON client2_db.* TO 'client2_user'@'%';
FLUSH PRIVILEGES;

USE client1_db;

CREATE TABLE IF NOT EXISTS colors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  hexadecimal CHAR(7) NOT NULL,
  UNIQUE KEY colors_name_unique (name),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO colors (name, hexadecimal) VALUES
  ('Ocean Blue', '#1B6CA8'),
  ('Tangerine', '#FF864E'),
  ('Graphite', '#414141')
ON DUPLICATE KEY UPDATE
  hexadecimal = VALUES(hexadecimal);

USE client2_db;

CREATE TABLE IF NOT EXISTS colors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  hexadecimal CHAR(7) NOT NULL,
  UNIQUE KEY colors_name_unique (name),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO colors (name, hexadecimal) VALUES
  ('Lime', '#B2FF59'),
  ('Crimson', '#DC143C'),
  ('Charcoal', '#36454F')
ON DUPLICATE KEY UPDATE
  hexadecimal = VALUES(hexadecimal);
