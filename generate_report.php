<?php
// File: generate_report.php

require_once 'vendor/autoload.php'; // Autoload dari Composer (untuk TCPDF)
require_once 'includes/db.php'; // Koneksi DB dan session_start

if (!isset($_SESSION['user_id'])) {
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Pengguna';

// --- Ambil dan Validasi Input dari Form ---
$date_range_str = $_POST['report_date_range'] ?? '';
$statuses = $_POST['statuses'] ?? [];
$chart_image_base64 = $_POST['chart_image_base64'] ?? '';

// Validasi Rentang Tanggal
$start_date_mysql = null;
$end_date_mysql = null;
if (!empty($date_range_str)) {
    $dates = explode(' - ', $date_range_str);
    if (count($dates) >= 1) {
        $date_parts_start = explode('/', trim($dates[0]));
        if (count($date_parts_start) == 3 && checkdate($date_parts_start[1], $date_parts_start[0], $date_parts_start[2])) {
            $start_date_mysql = $date_parts_start[2] . '-' . $date_parts_start[1] . '-' . $date_parts_start[0];
        }
    }
    if (count($dates) == 2) {
        $date_parts_end = explode('/', trim($dates[1]));
        if (count($date_parts_end) == 3 && checkdate($date_parts_end[1], $date_parts_end[0], $date_parts_end[2])) {
            $end_date_mysql = $date_parts_end[2] . '-' . $date_parts_end[1] . '-' . $date_parts_end[0];
        }
    } else {
        $end_date_mysql = $start_date_mysql;
    }
}
if (!$start_date_mysql || !$end_date_mysql) {
    die("Rentang tanggal tidak valid.");
}
if (strtotime($start_date_mysql) > strtotime($end_date_mysql)) {
    list($start_date_mysql, $end_date_mysql) = [$end_date_mysql, $start_date_mysql];
}


// --- Query Database Berdasarkan Filter ---
$sql_conditions_array = ["t.user_id = ?"];
$params = [$user_id];
$types = "i";

$status_conditions = [];
$has_overdue_filter = false;
$valid_statuses = ['Completed', 'In Progress', 'Not Started'];

foreach ($statuses as $status) {
    if (in_array($status, $valid_statuses)) {
        $status_conditions[] = "t.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    if ($status === 'Overdue') {
        $has_overdue_filter = true;
    }
}

$status_sql_part = "";
if (!empty($status_conditions)) {
    $status_sql_part = "(" . implode(" OR ", $status_conditions) . ")";
}

if ($has_overdue_filter) {
    $overdue_sql_part = "(t.due_date < CURDATE() AND t.status != 'Completed')";
    if (!empty($status_sql_part)) {
        $sql_conditions_array[] = "($status_sql_part OR $overdue_sql_part)";
    } else {
        $sql_conditions_array[] = $overdue_sql_part;
    }
} elseif (!empty($status_sql_part)) {
    $sql_conditions_array[] = $status_sql_part;
} else {
    die("Tidak ada status yang dipilih untuk laporan.");
}

// Filter berdasarkan rentang tanggal pada due_date
$sql_conditions_array[] = "t.due_date BETWEEN ? AND ?";
$params[] = $start_date_mysql;
$params[] = $end_date_mysql;
$types .= "ss";

$sql_conditions_string = implode(" AND ", $sql_conditions_array);
$sql = "SELECT t.title, t.priority, t.status, DATE_FORMAT(t.due_date, '%d %b %Y') as due_date_formatted, 
               (CASE WHEN t.status = 'Completed' THEN DATE_FORMAT(t.updated_at, '%d %b %Y') ELSE '-' END) as completed_date_formatted,
               (CASE WHEN t.due_date < CURDATE() AND t.status != 'Completed' THEN 'Ya' ELSE 'Tidak' END) as is_overdue
        FROM tasks t
        WHERE $sql_conditions_string
        ORDER BY t.due_date ASC, t.id ASC";

$stmt_tasks = $conn->prepare($sql);
$report_tasks = [];
if ($stmt_tasks) {
    $stmt_tasks->bind_param($types, ...$params);
    $stmt_tasks->execute();
    $result_tasks = $stmt_tasks->get_result();
    while ($row = $result_tasks->fetch_assoc()) {
        // Mapping status untuk tampilan di PDF
        if ($row['is_overdue'] === 'Ya') {
            $row['status_display'] = 'Terlewat';
        } else {
            switch ($row['status']) {
                case 'Completed': $row['status_display'] = 'Selesai'; break;
                case 'In Progress': $row['status_display'] = 'Dikerjakan'; break;
                case 'Not Started': $row['status_display'] = 'Belum Mulai'; break;
                default: $row['status_display'] = $row['status'];
            }
        }
        $report_tasks[] = $row;
    }
    $stmt_tasks->close();
} else {
    die("Gagal mempersiapkan query data laporan: " . $conn->error);
}

// ============================================================
// PEMBUATAN PDF DENGAN TCPDF
// ============================================================

// Extend class TCPDF untuk membuat Header dan Footer kustom
class MYPDF extends TCPDF {
    // Fungsi Header() dikosongkan karena kita akan menggambar header secara manual
    public function Header() {}

    // Page footer
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->getAliasNumPage() . ' dari ' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// Buat dokumen PDF baru
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set informasi dokumen
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('List In App');
$pdf->SetTitle('Laporan Produktivitas Tugas - ' . $username);
$pdf->SetSubject('Laporan Produktivitas Tugas');

// Set margin
$pdf->SetMargins(PDF_MARGIN_LEFT, 15, PDF_MARGIN_RIGHT);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Tambah halaman
$pdf->AddPage();

// --- GAMBAR HEADER MANUAL ---

// 1. Logo (lebih kecil dan lebih ke atas)
$image_file = __DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'logo.png';
if (file_exists($image_file)) {
    // x=10, y=8 (lebih atas), width=15 (lebih kecil)
    @$pdf->Image($image_file, 10, 6, 15, 0, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
}

// 2. Judul Laporan Utama
$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetY(8); // Set posisi Y untuk judul
$pdf->Cell(0, 10, 'Laporan Produktivitas Tugas', 0, 1, 'C'); // ln=1 untuk pindah baris

// 3. Garis Horizontal
$pdf->Line(10, 22, $pdf->getPageWidth() - 10, 22);

$pdf->Ln(8); 
// --- KONTEN PDF ---

// 4. Info Rentang, Pengguna, dan Tanggal Cetak (di bawah garis)
$pdf->SetFont('helvetica', '', 9);
$pdf->Ln(2); // Spasi kecil setelah garis

// Menggunakan HTML untuk layout 2 kolom agar rapi
$info_html = '
<table border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td width="50%">Rentang Laporan: ' . htmlspecialchars(date('d M Y', strtotime($start_date_mysql)) . ' - ' . date('d M Y', strtotime($end_date_mysql))) . '</td>
        <td width="50%" align="right">Pengguna: ' . htmlspecialchars($username) . '<br/>Tanggal Cetak: ' . date('d F Y') . '</td>
    </tr>
</table>';
$pdf->writeHTML($info_html, true, false, true, false, '');

$pdf->Ln(8); // Spasi sebelum diagram

// Diagram Performa
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Diagram Performa Pengerjaan', 0, 1, 'L');
$pdf->Ln(2);

if (!empty($chart_image_base64)) {
    $imgdata = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $chart_image_base64));
    // Mengatur tinggi gambar secara eksplisit (misal 55mm) untuk mengontrol spasi
    @$pdf->Image('@'.$imgdata, '', '', 180, 55, 'PNG', '', '', true, 150, '', false, false, 0, false, false, false);
} else {
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->Cell(0, 10, '[Diagram tidak tersedia]', 0, 1, 'L');
}

// Spasi setelah diagram dikurangi karena Ln(70) terlalu banyak
$pdf->Ln(60); 

// Tabel Detail Tugas
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Detail Daftar Tugas', 0, 1, 'L');
$pdf->Ln(2);


// ==========================================================
// ==========   PERBAIKAN UTAMA: GAMBAR TABEL MANUAL   ==========
// ==========================================================
// Hapus penggunaan writeHTML dan ganti dengan Cell()

// 1. Definisikan lebar setiap kolom (total harus ~190, lebar halaman dikurangi margin)
$w = array(15, 71, 28, 28, 24, 24); // No, Judul, Prioritas, Status, Deadline, Selesai

// 2. Gambar Header Tabel
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(234, 234, 234); // Warna abu-abu untuk header
$pdf->SetTextColor(0);
$pdf->SetDrawColor(128, 128, 128); // Warna border
$pdf->SetLineWidth(0.3);

// Parameter Cell: width, height, text, border, ln, align, fill, link
$pdf->Cell($w[0], 7, 'No.', 1, 0, 'C', true);
$pdf->Cell($w[1], 7, 'Judul Tugas', 1, 0, 'C', true);
$pdf->Cell($w[2], 7, 'Prioritas', 1, 0, 'C', true);
$pdf->Cell($w[3], 7, 'Status', 1, 0, 'C', true);
$pdf->Cell($w[4], 7, 'Deadline', 1, 0, 'C', true);
$pdf->Cell($w[5], 7, 'Selesai', 1, 1, 'C', true); // ln=1 untuk pindah baris

// 3. Gambar Isi Tabel (Data)
$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor(255); // Reset fill color ke putih
$fill = false; // Untuk zebra-striping (opsional, saat ini tidak dipakai)

if (empty($report_tasks)) {
    $pdf->Cell(array_sum($w), 10, 'Tidak ada data tugas untuk ditampilkan.', 1, 1, 'C');
} else {
    $no = 1;
    foreach ($report_tasks as $task) {
        $pdf->Cell($w[0], 6, $no++, 'LR', 0, 'C', $fill);
        $pdf->Cell($w[1], 6, $task['title'], 'LR', 0, 'L', $fill);
        $pdf->Cell($w[2], 6, $task['priority'], 'LR', 0, 'C', $fill);
        $pdf->Cell($w[3], 6, $task['status_display'], 'LR', 0, 'C', $fill);
        $pdf->Cell($w[4], 6, $task['due_date_formatted'], 'LR', 0, 'C', $fill);
        $pdf->Cell($w[5], 6, $task['completed_date_formatted'], 'LR', 1, 'C', $fill); // ln=1
    }
}

// 4. Gambar garis bawah tabel
$pdf->Cell(array_sum($w), 0, '', 'T');

// ==========================================================
// ==========          AKHIR DARI PERBAIKAN        ==========
// ==========================================================


// Tutup dan output dokumen PDF
$pdf->Output('Laporan_ListIn_' . date('Ymd') . '.pdf', 'I');

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}