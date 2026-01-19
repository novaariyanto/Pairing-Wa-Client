<?php
session_start();

// Default values if session is empty
$defaultApiKey = 'sk-vP3IIzdq6QG8EnDajb7nEdiEkbK3vz0w';
$defaultBaseUrl = 'http://localhost:8000/api/v1';

$apiKey = $_SESSION['api_key'] ?? $defaultApiKey;
$baseUrl = $_SESSION['base_url'] ?? $defaultBaseUrl;

function apiRequest($method, $endpoint, $data = null) {
    global $apiKey, $baseUrl;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // Disable SSL verification for testing purposes (often needed for local->public)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'GET') {
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return [
            'code' => 0,
            'data' => [
                'error' => 'cURL Error: ' . $error_msg,
                'details' => 'Failed to connect to ' . $baseUrl . $endpoint
            ]
        ];
    }
    
    curl_close($ch);
    
    $decoded = json_decode($response, true);
    
    // If JSON decode failed but we have a response, maybe it's an HTML error page or standard text
    if ($decoded === null && !empty($response)) {
        return [
            'code' => $httpCode,
            'data' => [
                'error' => 'Invalid JSON Response',
                'raw_response' => substr($response, 0, 500) // Limit length
            ]
        ];
    }
    
    return [
        'code' => $httpCode,
        'data' => $decoded
    ];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'save_config':
            $_SESSION['api_key'] = $_POST['api_key'] ?? '';
            $_SESSION['base_url'] = $_POST['base_url'] ?? '';
            echo json_encode(['success' => true]);
            exit;

        case 'get_config':
            echo json_encode([
                'success' => true, 
                'data' => [
                    'api_key' => $apiKey,
                    'base_url' => $baseUrl,
                    'instance_key' => $_SESSION['instance_key'] ?? null
                ]
            ]);
            exit;

        case 'create_instance':
            $phoneNumber = $_POST['phone_number'] ?? '';
            $instanceKey = $_POST['instance_key'] ?? null;
            
            $payload = ['phone_number' => $phoneNumber];
            if ($instanceKey) {
                $payload['instance_key'] = $instanceKey;
            }
            
            $result = apiRequest('POST', '/instances', $payload);
            
            if ($result['code'] === 200 || $result['code'] === 201) {
                $_SESSION['instance_key'] = $result['data']['data']['instance_key'];
                echo json_encode(['success' => true, 'data' => $result['data']]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create instance', 'error' => $result['data']]);
            }
            exit;
            
        case 'start_instance':
            $instanceKey = $_SESSION['instance_key'] ?? '';
            $result = apiRequest('POST', "/instances/{$instanceKey}/start");
            echo json_encode(['success' => true, 'data' => $result['data']]);
            exit;
            
        case 'get_qr':
            $instanceKey = $_SESSION['instance_key'] ?? '';
            $result = apiRequest('GET', "/instances/{$instanceKey}/qr");
            echo json_encode(['success' => true, 'data' => $result['data']]);
            exit;
            
        case 'get_status':
            $instanceKey = $_SESSION['instance_key'] ?? '';
            $result = apiRequest('GET', "/instances/{$instanceKey}/status");
            echo json_encode(['success' => true, 'data' => $result['data']]);
            exit;
            
        case 'list_instances':
            $result = apiRequest('GET', '/instances');
            echo json_encode(['success' => true, 'data' => $result['data']]);
            exit;
            
        case 'select_instance':
            $instanceKey = $_POST['instance_key'] ?? '';
            $_SESSION['instance_key'] = $instanceKey;
            echo json_encode(['success' => true, 'instance_key' => $instanceKey]);
            exit;
            
        case 'send_text':
            $instanceKey = $_SESSION['instance_key'] ?? '';
            $to = $_POST['to'] ?? '';
            $text = $_POST['text'] ?? '';
            
            $result = apiRequest('POST', '/messages/text', [
                'instance_key' => $instanceKey,
                'to' => $to,
                'text' => $text
            ]);
            
            echo json_encode(['success' => true, 'data' => $result['data']]);
            exit;
            
        case 'send_media':
            $instanceKey = $_SESSION['instance_key'] ?? '';
            $to = $_POST['to'] ?? '';
            $media = $_POST['media'] ?? '';
            $caption = $_POST['caption'] ?? '';
            
            $result = apiRequest('POST', '/messages/media', [
                'instance_key' => $instanceKey,
                'to' => $to,
                'media' => $media,
                'caption' => $caption
            ]);
            
            echo json_encode(['success' => true, 'data' => $result['data']]);
            exit;
            
        case 'get_messages':
            $instanceKey = $_SESSION['instance_key'] ?? '';
            $direction = $_POST['direction'] ?? 'all';
            
            $queryParams = [
                'instance_key' => $instanceKey,
                'limit' => 20 // Default limit as per requirement
            ];

            if ($direction !== 'all') {
                $queryParams['direction'] = $direction;
            }

            $queryString = http_build_query($queryParams);
            
            $result = apiRequest('GET', "/messages?{$queryString}");
            echo json_encode(['success' => true, 'data' => $result['data']]);
            exit;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
}

// If accessed directly without POST
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
