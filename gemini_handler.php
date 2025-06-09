<?php
// File: gemini_handler.php (Versi Perbaikan Batch CRUD)

require_once 'includes/db.php'; // Menggunakan koneksi dari db.php, session juga sudah dimulai

// --- Fungsi Helper ---
function send_json_response($data, $http_code = 200) {
    header('Content-Type: application/json');
    http_response_code($http_code);
    echo json_encode($data);
    exit;
}

function parse_ai_suggestions($text) {
    if (preg_match('/\[SUGGESTION_START\](.*?)\[SUGGESTION_END\]/s', $text, $matches)) {
        $json_string = trim($matches[1]);
        $parsed_json = json_decode($json_string, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            // Menangani kasus: {"intent":...} (objek tunggal)
            if (isset($parsed_json['intent'])) {
                return [$parsed_json];
            }
            // Menangani kasus: [{"intent":...}, {"intent":...}] (array dari objek)
            elseif (is_array($parsed_json) && count($parsed_json) > 0 && isset($parsed_json[0]['intent'])) {
                return $parsed_json;
            }
        }
    }
    return null; // Mengembalikan null jika tidak ada atau parsing gagal
}


// --- Pemeriksaan Keamanan & Inisialisasi ---
if (!isset($_SESSION['user_id'])) {
    send_json_response(['success' => false, 'message' => 'Sesi tidak valid. Silakan login kembali.'], 401);
}
$user_id = $_SESSION['user_id'];

// --- Router Aksi ---
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE && $_SERVER['REQUEST_METHOD'] === 'POST') {
    send_json_response(['success' => false, 'message' => 'Input JSON tidak valid.'], 400);
}
$action = $input['action'] ?? null;


// =========================================================================
// ==========                  AKSI: CHAT DENGAN AI                  ==========
// =========================================================================
if ($action === 'chat_with_ai') {
    $history = $input['history'] ?? [];
    if (empty($history)) {
        send_json_response(['success' => false, 'message' => 'Riwayat percakapan kosong.'], 400);
    }

    // Ambil konteks tugas dari DB untuk instruksi sistem
    $task_context_list = [];
    $stmt_tasks = $conn->prepare("SELECT id, title, status FROM tasks WHERE user_id = ? AND status != 'Completed' ORDER BY id ASC");
    $stmt_tasks->bind_param("i", $user_id);
    $stmt_tasks->execute();
    $result_tasks = $stmt_tasks->get_result();
    while ($row = $result_tasks->fetch_assoc()) {
        $task_context_list[] = "- \"" . $row['title'] . "\" (ID: " . $row['id'] . ", Status: " . $row['status'] . ")";
    }
    $stmt_tasks->close();
    $task_context = "Konteks Tugas Aktif Pengguna Saat Ini:\n" . (empty($task_context_list) ? "Tidak ada tugas aktif." : implode("\n", $task_context_list));

    // Instruksi sistem yang jelas untuk AI
    $system_instruction = <<<EOT
Anda adalah asisten AI yang efisien untuk aplikasi To-Do List bernama 'List In'.
Fokus utama Anda adalah mengidentifikasi niat pengguna (buat, ubah, hapus) dan mengubahnya menjadi data JSON terstruktur untuk dikonfirmasi.

INSTRUKSI:
1.  **Niat & Format JSON:** Untuk permintaan CRUD, selalu jawab dengan ringkasan dan minta konfirmasi menggunakan format JSON di dalam blok `[SUGGESTION_START]...[SUGGESTION_END]`.
2.  **Batch (Jamak):** Jika pengguna meminta beberapa aksi sekaligus (misal: 'buat 3 tugas'), buatlah ARRAY dari objek JSON di dalam blok suggestion. Setiap objek mewakili satu tugas.
3.  **Waktu & Tanggal:** Hari ini adalah `date('d F Y')`. Jika pengguna menyebut 'besok', 'lusa', 'minggu depan', konversikan ke format `YYYY-MM-DD`.
4.  **Klarifikasi:** Jika nama tugas untuk diubah/dihapus tidak jelas, tanyakan klarifikasi. Jangan menebak.
5.  **Bahasa:** Gunakan Bahasa Indonesia yang ringkas dan jelas.

FORMAT JSON:
-   **CREATE (array):** `[SUGGESTION_START][{"intent": "create_suggestion", "title": "Tugas 1", "priority": "Medium", "due_date": "YYYY-MM-DD"}, {"intent": "create_suggestion", "title": "Tugas 2", ...}][SUGGESTION_END]`
-   **UPDATE (objek):** `[SUGGESTION_START]{"intent": "update_suggestion", "title_original": "Nama Lama", "updates": {"status": "In Progress", "priority": "High"}}[SUGGESTION_END]` (Hanya satu per satu)
-   **DELETE (objek):** `[SUGGESTION_START]{"intent": "delete_suggestion", "title": "Nama Tugas"}[SUGGESTION_END]` (Hanya satu per satu)

Contoh Batch Create:
Pengguna: 'buatkan 3 tugas: belajar AI, olahraga, dan baca buku deadline besok'
AI: 'Siap, saya akan siapkan 3 tugas untuk Anda. Mohon konfirmasi detailnya di bawah ini.
[SUGGESTION_START][
  {"intent": "create_suggestion", "title": "Belajar AI", "priority": "Medium", "due_date": null},
  {"intent": "create_suggestion", "title": "Olahraga", "priority": "Medium", "due_date": null},
  {"intent": "create_suggestion", "title": "Baca Buku", "priority": "Medium", "due_date": "date('Y-m-d', strtotime('+1 day'))"}
][SUGGESTION_END]'
EOT;
    // Ganti placeholder tanggal secara dinamis
    $system_instruction = str_replace("date('d F Y')", date('d F Y'), $system_instruction);
    $system_instruction = str_replace("date('Y-m-d', strtotime('+1 day'))", date('Y-m-d', strtotime('+1 day')), $system_instruction);


    // Panggil Gemini API
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . GEMINI_API_KEY;
    $payload = [
        'contents' => $history,
        'systemInstruction' => ['role' => 'system', 'parts' => [['text' => $system_instruction]]]
    ];
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_TIMEOUT => 30]);
    $response_body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response_body) {
        send_json_response(['success' => false, 'message' => 'Gagal menghubungi AI. Silakan coba lagi.'], 500);
    }

    $responseData = json_decode($response_body, true);
    $ai_text = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? 'Maaf, saya tidak mengerti. Bisa coba lagi?';
    $suggestions = parse_ai_suggestions($ai_text);
    
    // Bersihkan teks AI dari blok suggestion untuk tampilan yang rapi
    $cleaned_ai_text = preg_replace('/\[SUGGESTION_START\].*?\[SUGGESTION_END\]/s', '', $ai_text);

    send_json_response(['success' => true, 'ai_message' => trim($cleaned_ai_text), 'suggestions_array' => $suggestions]);
    exit;
}

// =========================================================================
// ==========                  AKSI: KONFIRMASI CRUD                 ==========
// =========================================================================
elseif ($action === 'confirm_crud_action') {
    // **PERBAIKAN KUNCI**: Endpoint ini sekarang HANYA menangani SATU tugas per panggilan.
    $suggestion = $input['suggestion'] ?? null;
    if (!$suggestion || !isset($suggestion['intent'])) {
        send_json_response(['success' => false, 'message' => 'Data konfirmasi tidak lengkap.'], 400);
    }

    $intent = $suggestion['intent'];
    $message = "Aksi tidak dikenali.";
    $operation_success = false;

    if ($intent === 'create_suggestion') {
        $title = $suggestion['title'] ?? null;
        if ($title) {
            $priority = $suggestion['priority'] ?? 'Medium';
            $due_date = $suggestion['due_date'] ?? null;
            $stmt = $conn->prepare("INSERT INTO tasks (user_id, title, priority, due_date, status) VALUES (?, ?, ?, ?, 'Not Started')");
            $stmt->bind_param("isss", $user_id, $title, $priority, $due_date);
            if ($stmt->execute()) {
                $message = "Tugas '$title' berhasil dibuat.";
                $operation_success = true;
            } else { $message = "Gagal membuat tugas '$title'."; }
            $stmt->close();
        }
    } elseif ($intent === 'update_suggestion') {
        $title_original = $suggestion['title_original'] ?? null;
        $updates = $suggestion['updates'] ?? [];
        if ($title_original && !empty($updates)) {
            $set_parts = []; $update_params = []; $update_types = '';
            foreach($updates as $key => $value) {
                if (in_array($key, ['status', 'priority', 'due_date', 'title'])) { // 'title' untuk ganti nama
                    $set_parts[] = "$key = ?"; $update_params[] = $value; $update_types .= 's';
                }
            }
            if (!empty($set_parts)) {
                $stmt = $conn->prepare("UPDATE tasks SET " . implode(', ', $set_parts) . " WHERE title = ? AND user_id = ? LIMIT 1");
                $update_types .= 'si'; $update_params[] = $title_original; $update_params[] = $user_id;
                $stmt->bind_param($update_types, ...$update_params);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $message = "Tugas '$title_original' berhasil diperbarui.";
                    $operation_success = true;
                } else { $message = "Gagal memperbarui tugas '$title_original'."; }
                $stmt->close();
            }
        }
    } elseif ($intent === 'delete_suggestion') {
        $title = $suggestion['title'] ?? null;
        if ($title) {
            $stmt = $conn->prepare("DELETE FROM tasks WHERE title = ? AND user_id = ? LIMIT 1");
            $stmt->bind_param("si", $title, $user_id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = "Tugas '$title' berhasil dihapus.";
                $operation_success = true;
            } else { $message = "Gagal menghapus tugas '$title'."; }
            $stmt->close();
        }
    }

    send_json_response(['success' => $operation_success, 'message' => $message]);
    exit;
}

// Jika tidak ada aksi yang cocok
send_json_response(['success' => false, 'message' => 'Aksi tidak valid.'], 404);
