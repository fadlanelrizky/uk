<?php
/**
 * AUTO CANCEL - Pending Orders > 24 Jam
 * File ini dipindahkan ke folder user/ karena hanya digunakan
 * oleh halaman-halaman user, bukan admin/petugas.
 *
 * Dijalankan melalui includes/header.php setiap kali user membuka halaman.
 * Membatalkan order pending yang sudah lebih dari 24 jam,
 * mengembalikan kuota tiket, dan menyimpan info ke session
 * agar bisa ditampilkan sebagai alert ke user yang bersangkutan.
 */

if (isset($conn) && isset($_SESSION['id_user'])) {
    $id_user = (int)$_SESSION['id_user'];

    // Cari order pending > 24 jam milik user yang sedang login
    $expired = $conn->query("
        SELECT id_order 
        FROM orders 
        WHERE status = 'pending' 
          AND id_user = $id_user
          AND tanggal_order < DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");

    if ($expired && $expired->num_rows > 0) {
        $cancelled_events = [];

        while ($row = $expired->fetch_assoc()) {
            $id_order = (int)$row['id_order'];

            $conn->begin_transaction();
            try {
                // Batalkan order
                $conn->query("UPDATE orders SET status='cancelled' WHERE id_order=$id_order");

                // Kembalikan kuota tiket
                $details = $conn->query("SELECT id_tiket, qty FROM order_detail WHERE id_order=$id_order");
                while ($d = $details->fetch_assoc()) {
                    $qty      = (int)$d['qty'];
                    $id_tiket = (int)$d['id_tiket'];
                    $conn->query("UPDATE tiket SET kuota = kuota + $qty WHERE id_tiket=$id_tiket");
                }

                // Ambil nama event untuk ditampilkan di alert
                $info = $conn->query("
                    SELECT e.nama_event 
                    FROM order_detail od 
                    JOIN tiket t ON od.id_tiket = t.id_tiket 
                    JOIN event e ON t.id_event = e.id_event 
                    WHERE od.id_order = $id_order 
                    LIMIT 1
                ");
                if ($info && $r = $info->fetch_assoc()) {
                    $cancelled_events[] = $r['nama_event'];
                }

                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
            }
        }

        // Simpan ke session agar bisa ditampilkan sebagai SweetAlert
        if (!empty($cancelled_events)) {
            $_SESSION['auto_cancelled_events'] = $cancelled_events;
        }
    }
}
