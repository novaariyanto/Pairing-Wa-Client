<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Dashboard</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>WhatsApp API Dashboard</h1>
            <div class="status-indicator" id="connectionStatus">
                <span class="status-dot"></span>
                <span class="status-text">Disconnected</span>
            </div>
        </header>

        <!-- API Configuration Section -->
        <div class="card config-section" style="margin-bottom: 20px;">
            <h2>API Configuration</h2>
            <div class="instance-actions">
                <div class="form-group" style="flex: 2;">
                    <label for="apiKey">API Key</label>
                    <input type="password" id="apiKey" placeholder="sk-..." value="">
                </div>
                <div class="form-group" style="flex: 2;">
                    <label for="baseUrl">Base URL</label>
                    <input type="text" id="baseUrl" placeholder="http://localhost:8000/api/v1" value="">
                </div>
                <button class="btn btn-primary" onclick="saveConfiguration()">
                    Save Config
                </button>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- Instance Management Section -->
            <div class="card instance-section">
                <h2>Instance Management</h2>
                <div class="instance-actions">
                    <div class="form-group">
                        <label for="newPhoneNumber">Phone Number</label>
                        <input type="text" id="newPhoneNumber" placeholder="6281234567890" value="6281234567890">
                    </div>
                    <div class="form-group">
                        <label for="customInstanceKey">Custom Instance Key (Optional)</label>
                        <input type="text" id="customInstanceKey" placeholder="my-custom-key">
                    </div>
                    <button class="btn btn-primary" onclick="createNewInstance()">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="display: inline-block; vertical-align: middle; margin-right: 5px;">
                            <path d="M8 3V13M3 8H13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        Create Instance
                    </button>
                    <button class="btn btn-secondary" onclick="loadInstances()" style="margin-left: 10px;">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="display: inline-block; vertical-align: middle; margin-right: 5px;">
                            <path d="M13 3L3 3M3 3L6 6M3 3L6 0M3 13L13 13M13 13L10 10M13 13L10 16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                        Refresh List
                    </button>
                </div>
                
                <div class="instances-list" id="instancesList">
                    <p class="no-instances">Click "Refresh List" to load instances</p>
                </div>
            </div>

            <!-- QR Code Section -->
            <div class="card qr-section">
                <h2>WhatsApp Connection</h2>
                <div class="selected-instance-info" id="selectedInstanceInfo">
                    <p class="no-selection">⚠️ Please select an instance from the list above</p>
                </div>
                <div class="qr-container" id="qrContainer">
                    <div class="qr-placeholder">
                        <svg width="100" height="100" viewBox="0 0 100 100" fill="none">
                            <rect x="10" y="10" width="35" height="35" fill="#25D366"/>
                            <rect x="55" y="10" width="35" height="35" fill="#25D366"/>
                            <rect x="10" y="55" width="35" height="35" fill="#25D366"/>
                        </svg>
                        <p>Select an instance to generate QR Code</p>
                    </div>
                </div>
                <div class="qr-actions">
                    <button class="btn btn-primary" onclick="generateQR()" id="generateQRBtn" disabled>
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="display: inline-block; vertical-align: middle; margin-right: 5px;">
                            <rect x="2" y="2" width="5" height="5" stroke="currentColor" stroke-width="1.5" fill="none"/>
                            <rect x="9" y="2" width="5" height="5" stroke="currentColor" stroke-width="1.5" fill="none"/>
                            <rect x="2" y="9" width="5" height="5" stroke="currentColor" stroke-width="1.5" fill="none"/>
                        </svg>
                        Generate QR Code
                    </button>
                    <button class="btn btn-secondary" onclick="checkStatus()" style="margin-left: 10px;">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="display: inline-block; vertical-align: middle; margin-right: 5px;">
                            <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5" fill="none"/>
                            <path d="M6 8L7.5 9.5L10 6.5" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/>
                        </svg>
                        Check Status
                    </button>
                </div>
            </div>

            <!-- Send Message Section -->
            <div class="card message-section">
                <h2>Send Message</h2>
                <div class="tabs">
                    <button class="tab-btn active" onclick="switchTab('text')">Text Message</button>
                    <button class="tab-btn" onclick="switchTab('media')">Media Message</button>
                </div>
                
                <div id="textTab" class="tab-content active">
                    <div class="form-group">
                        <label for="textTo">Recipient Number</label>
                        <input type="text" id="textTo" placeholder="6281234567890">
                    </div>
                    <div class="form-group">
                        <label for="messageText">Message</label>
                        <textarea id="messageText" rows="4" placeholder="Type your message here..."></textarea>
                    </div>
                    <button class="btn btn-success" onclick="sendTextMessage()">Send Text</button>
                </div>
                
                <div id="mediaTab" class="tab-content">
                    <div class="form-group">
                        <label for="mediaTo">Recipient Number</label>
                        <input type="text" id="mediaTo" placeholder="6281234567890">
                    </div>
                    <div class="form-group">
                        <label for="mediaUrl">Media URL</label>
                        <input type="text" id="mediaUrl" placeholder="https://example.com/image.jpg">
                    </div>
                    <div class="form-group">
                        <label for="mediaCaption">Caption</label>
                        <input type="text" id="mediaCaption" placeholder="Photo caption">
                    </div>
                    <button class="btn btn-success" onclick="sendMediaMessage()">Send Media</button>
                </div>
            </div>

            <!-- Messages Section -->
            <div style="display:none" class="card messages-section">
                <h2>Messages</h2>
                <div class="tabs">
                    <button class="tab-btn active" onclick="switchMessageTab('incoming')">Incoming</button>
                    <button class="tab-btn" onclick="switchMessageTab('outgoing')">Outgoing</button>
                </div>
                <div class="messages-container" id="messagesContainer">
                    <p class="no-messages">No messages yet</p>
                </div>
                <button class="btn btn-secondary" onclick="refreshMessages()">Refresh Messages</button>
            </div>
        </div>
    </div>

    <div id="notification" class="notification"></div>

    <script src="script.js"></script>
</body>
</html>
