<?php
session_start();
header('Content-Type: application/json');

// Debug mode - bật để xem lỗi
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Chỉ cho phép sinh viên đã đăng nhập
if(!isset($_SESSION['ma']) || !isset($_SESSION['mk'])) {
    echo json_encode(array('error' => 'Bạn cần đăng nhập để sử dụng chat AI'));
    exit;
}

// Nhận dữ liệu từ request
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['prompt'])) {
    echo json_encode(array('error' => 'Vui lòng nhập câu hỏi'));
    exit;
}

$prompt = trim($data['prompt']);

// Giới hạn độ dài prompt
// if (strlen($prompt) > 2000) {
$prompt = "Bạn là một trợ lý AI học tập.

Bạn KHÔNG phải là người dùng.
Bạn chỉ sử dụng thông tin người dùng để tham khảo khi cần.

Thông tin người dùng:
- Tên: Thương Hoài
- Sinh: 10/3/2004
- Giới tính: Nữ
- Quê: Nghệ An
- Tính cách: hiền, dễ thương, xinh gái
- Ngành: Công nghệ thông tin

Chỉ sử dụng thông tin này nếu câu hỏi liên quan trực tiếp đến người dùng.

Hãy trả lời câu hỏi sau một cách rõ ràng:

Câu hỏi: 

" .  $prompt;
// }



// URL API Ollama - PORT 11435
$ollamaUrl = 'http://127.0.0.1:11435/api/generate';

$payload = json_encode(array(
    'model' => 'llama3.1:8b',
    'prompt' => $prompt,
    'stream' => false
));

// Khởi tạo cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $ollamaUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Log lỗi để debug
if ($error) {
    echo json_encode(array(
        'error' => 'Lỗi kết nối đến Ollama: ' . $error,
        'debug' => 'Kiểm tra xem Ollama có đang chạy trên port 11435 không'
    ));
    exit;
}

if ($httpCode !== 200) {
    echo json_encode(array(
        'error' => 'Ollama API lỗi (mã HTTP: ' . $httpCode . ')',
        'debug' => 'Response: ' . substr($response, 0, 500)
    ));
    exit;
}

$result = json_decode($response, true);

if (isset($result['response'])) {
    echo json_encode(array('response' => $result['response']));
} else {
    echo json_encode(array(
        'error' => 'Không nhận được phản hồi từ AI',
        'debug' => 'Response: ' . substr($response, 0, 500)
    ));
}
