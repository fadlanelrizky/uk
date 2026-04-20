CREATE DATABASE IF NOT EXISTS event_tiket;
USE event_tiket;

CREATE TABLE users (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user', 'petugas') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE venue (
    id_venue INT AUTO_INCREMENT PRIMARY KEY,
    nama_venue VARCHAR(100) NOT NULL,
    alamat TEXT NOT NULL,
    kapasitas INT NOT NULL
);

CREATE TABLE event (
    id_event INT AUTO_INCREMENT PRIMARY KEY,
    id_venue INT,
    nama_event VARCHAR(150) NOT NULL,
    tanggal_event DATE NOT NULL,
    deskripsi TEXT,
    gambar VARCHAR(255) DEFAULT 'default.jpg',
    FOREIGN KEY (id_venue) REFERENCES venue(id_venue) ON DELETE CASCADE
);

CREATE TABLE tiket (
    id_tiket INT AUTO_INCREMENT PRIMARY KEY,
    id_event INT,
    nama_tiket VARCHAR(100) NOT NULL,
    harga DECIMAL(10,2) NOT NULL,
    kuota INT NOT NULL,
    FOREIGN KEY (id_event) REFERENCES event(id_event) ON DELETE CASCADE
);

CREATE TABLE voucher (
    id_voucher INT AUTO_INCREMENT PRIMARY KEY,
    kode_voucher VARCHAR(50) UNIQUE NOT NULL,
    diskon DECIMAL(10,2) NOT NULL,
    kuota INT NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active'
);

CREATE TABLE orders (
    id_order INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT,
    id_voucher INT NULL,
    tanggal_order TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE,
    FOREIGN KEY (id_voucher) REFERENCES voucher(id_voucher) ON DELETE SET NULL
);

CREATE TABLE order_detail (
    id_detail INT AUTO_INCREMENT PRIMARY KEY,
    id_order INT,
    id_tiket INT,
    qty INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_order) REFERENCES orders(id_order) ON DELETE CASCADE,
    FOREIGN KEY (id_tiket) REFERENCES tiket(id_tiket) ON DELETE CASCADE
);

CREATE TABLE attendee (
    id_attendee INT AUTO_INCREMENT PRIMARY KEY,
    id_order_detail INT,
    kode_tiket VARCHAR(50) UNIQUE NOT NULL,
    status_checkin ENUM('belum', 'sudah') DEFAULT 'belum',
    waktu_checkin TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (id_order_detail) REFERENCES order_detail(id_detail) ON DELETE CASCADE
);

-- Default data seeding
-- Password for all is 'password'
INSERT INTO users (nama_lengkap, email, password, role) VALUES 
('Administrator', 'admin@event.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('User Demo', 'user@event.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user'),
('Petugas Gate', 'petugas@event.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'petugas');
