<?php
session_start();
header('Content-Type: application/json');

// Debug mode - bật để xem lỗi
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);
// Chỉ cho phép sinh viên đã đăng nhập
// if(!isset($_SESSION['ma']) || !isset($_SESSION['mk'])) {
//     echo json_encode(array('error' => 'Bạn cần đăng nhập để sử dụng chat AI'));
//     exit;
// }

// Nhận dữ liệu từ request
$data = json_decode(file_get_contents('php://input'), true);

// if (!isset($data['prompt'])) {
//     echo json_encode(array('error' => 'Vui lòng nhập câu hỏi'));
//     exit;
// }

$prompt = isset($data['prompt']) && trim($data['prompt']) !== ''
    ? trim($data['prompt'])
    : "ví dụ tôi học dốt toán thì tôi có thể học các môn lập trình điểm A+ được không";

$payload = json_encode(array(
    "text" => $prompt
));

$ch1 = curl_init();
curl_setopt($ch1, CURLOPT_URL, "http://127.0.0.1:8000/embedding");
curl_setopt($ch1, CURLOPT_POST, true);
curl_setopt($ch1, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch1, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch1, CURLOPT_TIMEOUT, 200);
curl_setopt($ch1, CURLOPT_CONNECTTIMEOUT, 10);

$response1 = curl_exec($ch1);
$httpCode1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
$error1 = curl_error($ch1);

curl_close($ch1);
$rs = json_decode($response1, true);
$vector_data = $rs['vector'];

// var_dump($rs['vector']);

// exit;

$urlQdrant = "http://localhost:6333/collections/iuh_subjects/points/search";

$data = array(
    "vector" => $vector_data,
    "limit" => 5,
    "with_payload" => true
)

;

$ch2 = curl_init($urlQdrant);

curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_HTTPHEADER, array('Content-Type: application/json')
    
);
curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($data));

$response2 = curl_exec($ch2);
curl_close($ch2);

$result2 = json_decode($response2, true);


// var_dump($result2);
// ghép thành context
$context = "";

foreach ($result2['result'] as $i => $item) {
    $p = $item['payload'];

    $context .= "Môn " . ($i+1) . ":\n";
    $context .= "- Tên: " . $p['tenhocphan'] . "\n";
    $context .= "- Ngành: " . $p['tenchuyennganh'] . "\n";
    $context .= "- Nhóm: " . $p['nhom_mon'] . "\n";
    $context .= "- Mô tả: " . $p['text_content'] . "\n\n";
}

// var_dump($context);
// exit;

$prompt = "
Bạn là một AI hỗ trợ sinh viên, thân thiện và dễ hiểu.

--- NGỮ CẢNH ---
Dưới đây là thông tin về các môn học:
$context

--- NHIỆM VỤ ---
Hãy trả lời câu hỏi của sinh viên:
$prompt

--- QUY TẮC ---
1. Nếu câu hỏi liên quan đến ngành học:
   - Trả lời dựa trên thông tin đã cho
   - Có thể diễn giải lại cho dễ hiểu
   - Không tự thêm môn học không có trong dữ liệu

2. Nếu câu hỏi KHÔNG liên quan đến dữ liệu:
   - Trả lời bằng hiểu biết chung một cách hợp lý
   - Không cần phụ thuộc vào context

3. Tuyệt đối:
   - Không nói 'dựa trên văn bản', 'dữ liệu cung cấp'
   - Không hỏi ngược lại
   - Không yêu cầu thêm thông tin

--- PHONG CÁCH ---
- Tự nhiên, giống người thật
- Ngắn gọn, rõ ràng
- Trả lời trực tiếp vào vấn đề
";


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
curl_setopt($ch, CURLOPT_TIMEOUT, 300);
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
