<?php
declare(strict_types=1);
@set_time_limit(600); // 10 menit timeout

// Sembunyikan error PHP dari output, log ke file saja
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/_php_error.log');

header('Content-Type: application/json; charset=utf-8');

// =============================================================================
// KONFIGURASI (WAJIB DISESUAIKAN)
// =============================================================================
$CONFIG = [
    // Path ABSOLUT ke interpreter Python di dalam virtual environment Anda
    'python_path' => 'C:\\xampp\\htdocs\\kerja_praktek\\.venv\\Scripts\\python.exe',

    // Path ABSOLUT ke direktori kerja tempat skrip Python berada
    'work_dir' => 'C:\\xampp\\htdocs\\kerja_praktek\\api',

    // Konfigurasi untuk setiap platform
    'scrapers' => [
        'Tokopedia' => [
            'script' => 'tokopedia.py', // Nama file Python yang benar
        ],
    ]
];

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================
function json_fail(int $code, string $msg, array $extra = []): void {
    http_response_code($code);
    echo json_encode(['error' => $msg] + $extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================================================================
// LOGIKA UTAMA
// =============================================================================

// 1. Baca dan validasi input JSON dari request
$inputJSON = file_get_contents('php://input');
if ($inputJSON === false) {
    json_fail(400, 'Gagal membaca body request.');
}
$input = json_decode($inputJSON, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    json_fail(400, 'Request body berisi JSON tidak valid: ' . json_last_error_msg());
}

$namaItem = trim($input['nama_item'] ?? '');
$pages = max(1, (int)($input['pages'] ?? 1));
$referensi = array_filter(array_map('trim', explode(',', $input['referensi'] ?? '')));

if (empty($namaItem)) json_fail(422, 'Parameter "nama_item" wajib diisi.');
if (empty($referensi)) json_fail(422, 'Parameter "referensi" wajib diisi (contoh: Tokopedia).');

// 2. Fungsi untuk menjalankan satu proses scraper
function run_scraper(string $platform, string $keywords, int $pages_to_scrape, array $cfg): array {
    $scraper_cfg = $cfg['scrapers'][$platform] ?? null;
    if (!$scraper_cfg) {
        return ['error' => "Konfigurasi untuk platform '{$platform}' tidak ditemukan."];
    }
    
    $python_path = $cfg['python_path'];
    $work_dir = $cfg['work_dir'];
    $script_path = $work_dir . DIRECTORY_SEPARATOR . $scraper_cfg['script'];

    if (!is_file($python_path)) return ['error' => "Interpreter Python tidak ditemukan di: {$python_path}"];
    if (!is_file($script_path)) return ['error' => "Skrip scraper tidak ditemukan di: {$script_path}"];
    if (!is_dir($work_dir)) return ['error' => "Direktori kerja tidak ditemukan di: {$work_dir}"];

    // Deskriptor untuk mengontrol stdin, stdout, stderr dari proses Python
    $descriptors = [
        0 => ['pipe', 'r'], // stdin: untuk mengirim input ke Python
        1 => ['pipe', 'w'], // stdout: untuk membaca output dari Python
        2 => ['pipe', 'w'], // stderr: untuk membaca error dari Python
    ];
    
    // Perintah yang akan dieksekusi. Menggunakan array lebih aman.
    $cmd = [$python_path, $script_path];
    
    // Set environment variable `RUN_FROM_PHP=1`.
    // Ini adalah sinyal bagi skrip Python untuk berjalan dalam mode headless.
    $env = ['RUN_FROM_PHP' => '1'] + $_ENV + $_SERVER;

    $proc = proc_open($cmd, $descriptors, $pipes, $work_dir, $env);
    if (!is_resource($proc)) {
        return ['error' => 'Gagal memulai proses Python via proc_open. Cek konfigurasi server.'];
    }

    // Pastikan pipe terbuka sebelum menulis
    if (!$pipes[0] || !$pipes[1] || !$pipes[2]) {
        return ['error' => 'Gagal membuka pipe untuk komunikasi dengan Python.'];
    }

    // Kirim input ke stdin Python
    fwrite($pipes[0], $keywords . PHP_EOL);
    fwrite($pipes[0], $pages_to_scrape . PHP_EOL);
    fclose($pipes[0]);

    // Baca output dan error
    $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
    
    // Tutup proses dan dapatkan exit code-nya
    $exit_code = proc_close($proc);

    // Cek jika proses Python gagal
    if ($exit_code !== 0) {
        $log = "STDOUT:\n" . ($stdout ?: '(empty)') . "\n\nSTDERR:\n" . ($stderr ?: '(empty)');
        return [
            'error' => "Skrip Python [{$platform}] gagal dengan exit code {$exit_code}. Lihat log untuk detail.",
            'log' => $log,
        ];
    }

    // Ekstrak nama file JSON dari output stdout Python
    if (!preg_match('/Data JSON berhasil disimpan:\s*([^\s|]+\.json)/u', $stdout, $matches)) {
        return [
            'error' => 'Skrip Python berhasil, namun tidak menemukan nama file JSON di output.',
            'log' => "STDOUT:\n" . $stdout . "\n\nSTDERR:\n" . $stderr,
        ];
    }

    $json_filename = $matches[1];
    $json_path = $work_dir . DIRECTORY_SEPARATOR . $json_filename;
    
    if (!is_file($json_path)) {
        return ['error' => "File JSON '{$json_filename}' dilaporkan ada, namun tidak ditemukan di path: {$json_path}"];
    }

    $json_content = file_get_contents($json_path);
    $data = json_decode($json_content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Gagal mem-parsing file JSON: ' . json_last_error_msg()];
    }
    
    // Hapus file JSON setelah berhasil dibaca agar tidak menumpuk
    @unlink($json_path);

    return ['data' => $data];
}

// 3. Jalankan scraper untuk setiap referensi yang diminta
$all_results = [];
$errors = [];

foreach ($referensi as $platform) {
    if (!isset($CONFIG['scrapers'][$platform])) {
        $errors[] = "Referensi tidak valid: {$platform}";
        continue;
    }
    
    $result = run_scraper($platform, $namaItem, $pages, $CONFIG);

    if (isset($result['error'])) {
        $errors[] = "Error from [{$platform}]: " . $result['error'];
    } elseif (!empty($result['data'])) {
        $all_results = array_merge($all_results, $result['data']);
    }
}

// 4. Jika ada error, kembalikan response error
if (!empty($errors)) {
    json_fail(500, "Satu atau lebih scraper gagal dieksekusi.", [
        'details' => $errors,
    ] + (empty($all_results) ? [] : ['successful_data' => $all_results]));
}

// 5. Jika tidak ada hasil sama sekali
if (empty($all_results)) {
    json_fail(404, 'Scraping berhasil, namun tidak ada produk yang ditemukan atau cocok dengan kriteria.');
}

// 6. Jika berhasil, kembalikan hasilnya
http_response_code(200);
echo json_encode($all_results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
