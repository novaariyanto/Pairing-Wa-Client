# WhatsApp API - Dokumentasi Lengkap

## Overview
Dokumentasi ini menjelaskan penggunaan API WhatsApp Client baik melalui Dashboard UI maupun integrasi kode PHP.

## Dashboard Interface
Dashboard (`index.php`) menyediakan antarmuka pengguna untuk:
1.  **Konfigurasi API**: Mengatur API Key dan Base URL secara dinamis.
2.  **Manajemen Instance**: Membuat, memilih, dan melihat status instance.
3.  **Koneksi WhatsApp**: Generate QR Code untuk pair device.
4.  **Fitur Pesan**: Mengirim pesan teks dan media.

### Konfigurasi API
Pada Dashboard, Anda dapat mengatur koneksi ke server API:
-   **API Key**: Kunci otentikasi (Default: `sk-vP3IIzdq6QG8EnDajb7nEdiEkbK3vz0w`)
-   **Base URL**: Alamat server API (Default: `http://localhost:8000/api/v1`)
-   Klik **Save Config** untuk menyimpan pengaturan di session browser.

---

## Integrasi PHP (Backend)

Jika Anda ingin membuat integrasi custom menggunakan PHP (seperti di `api-handler.php`), berikut adalah referensinya.

### Base Configuration

```php
// Contoh implementasi dinamis menggunakan Session
session_start();
$apiKey = $_SESSION['api_key'] ?? 'sk-vP3IIzdq6QG8EnDajb7nEdiEkbK3vz0w';
$baseUrl = $_SESSION['base_url'] ?? 'http://localhost:8000/api/v1';
```

### Helper Function

```php
function apiRequest($method, $endpoint, $data = null) {
    global $apiKey, $baseUrl;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'data' => json_decode($response, true)
    ];
}
```

## API Endpoints

### 1. Create Instance
**Endpoint:** `POST /instances`

**Request:**
```php
// With auto-generated key
apiRequest('POST', '/instances', [
    'phone_number' => '6281234567890'
]);

// With custom key (optional)
apiRequest('POST', '/instances', [
    'instance_key' => 'my-custom-key',
    'phone_number' => '6281234567890'
]);
```

**Response:**
```json
{
    "data": {
        "instance_key": "my-custom-key",
        "phone_number": "6281234567890",
        "status": "DISCONNECTED"
    }
}
```

---

### 2. List All Instances
**Endpoint:** `GET /instances`

**Request:**
```php
apiRequest('GET', '/instances');
```

**Response:**
```json
{
    "data": [
        {
            "instance_key": "abc123",
            "phone_number": "6281234567890",
            "status": "CONNECTED"
        }
    ]
}
```

---

### 3. Start Instance
**Endpoint:** `POST /instances/{instance_key}/start`

**Request:**
```php
apiRequest('POST', "/instances/{$instanceKey}/start");
```

**Response:**
```json
{
    "success": true,
    "message": "Instance started"
}
```

---

### 4. Get Instance Status
**Endpoint:** `GET /instances/{instance_key}/status`

**Request:**
```php
apiRequest('GET', "/instances/{$instanceKey}/status");
```

**Response:**
```json
{
    "data": {
        "status": "CONNECTED",
        "instance_key": "abc123"
    }
}
```

**Status Values:**
- `DISCONNECTED`: Instance belum terhubung
- `CONNECTING`: Sedang menghubungkan
- `CONNECTED`: Terhubung ke WhatsApp Web
- `READY`: Siap mengirim pesan

---

### 5. Get QR Code
**Endpoint:** `GET /instances/{instance_key}/qr`

**Request:**
```php
apiRequest('GET', "/instances/{$instanceKey}/qr");
```

**Response:**
```json
{
    "data": {
        "qr_code": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...",
        "instance_key": "abc123"
    }
}
```

---

### 6. Send Text Message
**Endpoint:** `POST /messages/text`

**Request:**
```php
apiRequest('POST', '/messages/text', [
    'instance_key' => $instanceKey,
    'to' => '6281234567890',
    'text' => 'Hello from PHP!'
]);
```

**Response:**
```json
{
    "success": true,
    "message_id": "msg_123456",
    "status": "sent"
}
```

---

### 7. Send Media Message
**Endpoint:** `POST /messages/media`

**Request:**
```php
apiRequest('POST', '/messages/media', [
    'instance_key' => $instanceKey,
    'to' => '6281234567890',
    'media' => 'https://example.com/image.jpg',
    'caption' => 'Photo caption'
]);
```

**Response:**
```json
{
    "success": true,
    "message_id": "msg_123457",
    "status": "sent"
}
```

---

### 8. Get Messages
**Endpoint:** `GET /messages`

**Request:**
```php
// Incoming
apiRequest('GET', "/messages?instance_key={$instanceKey}&direction=IN");

// Outgoing
apiRequest('GET', "/messages?instance_key={$instanceKey}&direction=OUT");
```

---

## Struktur File Project

```
wa-client/
├── index.php             # Main Dashboard UI (sebelumnya dashboard.php)
├── api-handler.php       # PHP Backend Proxy & AJAX Handler
├── script.js             # Frontend Logic (API Calls, UI Updates)
├── styles.css            # Styling
└── API_DOCUMENTATION.md  # Dokumentasi ini
```

## Catatan Penting
1.  **Session Storage**: `api-handler.php` sekarang menggunakan PHP Session untuk menyimpan `api_key` dan `base_url` yang di-input via Dashboard.
2.  **Server Requirement**: Pastikan server API berjalan di URL yang dikonfigurasi (default: `http://localhost:8000`).
3.  **Cross-Origin (CORS)**: Jika API Server dan Client berada di domain berbeda, pastikan CORS diaktifkan di server.
