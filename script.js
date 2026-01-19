'use strict';

/**
 * State Management
 */
const state = {
    selectedInstanceKey: null,
    currentMessageTab: 'incoming',
    statusCheckInterval: null,
    qrRetryCount: 0,
    MAX_QR_RETRIES: 10
};

/**
 * Helpers
 */
const $ = (id) => document.getElementById(id);
const $all = (sel) => document.querySelectorAll(sel);

const notify = (msg, type = 'info') => {
    const el = $('notification');
    el.textContent = msg;
    el.className = `notification ${type} show`;
    setTimeout(() => el.classList.remove('show'), 3000);
};

const apiCall = async (action, data = {}) => {
    const formData = new FormData();
    formData.append('action', action);
    for (const [k, v] of Object.entries(data)) {
        if (v !== null && v !== undefined) formData.append(k, v);
    }

    try {
        const res = await fetch('api-handler.php', { method: 'POST', body: formData });
        return await res.json();
    } catch (e) {
        throw new Error(e.message || 'API Error');
    }
};

const renderLoader = (msg, subMsg = '') => `
    <div class="qr-placeholder">
        <div class="loading-spinner"></div>
        <p>${msg}</p>
        ${subMsg ? `<p style="font-size: 12px; color: var(--text-secondary); margin-top: 10px;">${subMsg}</p>` : ''}
    </div>
`;

const renderError = (msg) => `
    <div class="qr-placeholder">
        <svg width="100" height="100" viewBox="0 0 100 100" fill="none">
            <circle cx="50" cy="50" r="40" stroke="#dc3545" stroke-width="4" fill="none"/>
            <path d="M35 35 L65 65 M65 35 L35 65" stroke="#dc3545" stroke-width="4" stroke-linecap="round"/>
        </svg>
        <p style="color: #dc3545;">${msg}</p>
    </div>
`;

const renderSuccess = (msg) => `
    <div class="qr-placeholder">
        <svg width="100" height="100" viewBox="0 0 100 100" fill="none">
            <circle cx="50" cy="50" r="40" stroke="#25D366" stroke-width="4" fill="none"/>
            <path d="M35 50 L45 60 L65 40" stroke="#25D366" stroke-width="4" fill="none" stroke-linecap="round"/>
        </svg>
        <p style="color: #25D366; font-weight: 600;">${msg}</p>
    </div>
`;

/**
 * Core Functions
 */
async function loadInstances() {
    notify('Loading instances...', 'info');
    try {
        const res = await apiCall('list_instances');
        const list = $('instancesList');

        if (res.success && res.data?.data?.length) {
            list.innerHTML = res.data.data.map(inst => {
                const isSelected = state.selectedInstanceKey === inst.instance_key;
                const statusClass = ['CONNECTED', 'READY'].includes(inst.status) ? 'connected' :
                    inst.status === 'DISCONNECTED' ? 'disconnected' : 'pending';

                return `
                    <div class="instance-card ${isSelected ? 'selected' : ''}" 
                         onclick="selectInstance('${inst.instance_key}', '${inst.phone_number}', '${inst.status}')">
                        <div class="instance-key">${inst.instance_key}</div>
                        <div class="instance-phone">📱 ${inst.phone_number}</div>
                        <span class="instance-status ${statusClass}">${inst.status}</span>
                    </div>
                `;
            }).join('');
            notify('Instances loaded!', 'success');
        } else {
            list.innerHTML = '<p class="no-instances">No instances found. Create one first!</p>';
            notify('No instances found', 'info');
        }
    } catch (e) {
        notify('Error: ' + e.message, 'error');
    }
}

async function selectInstance(key, phone, status) {
    try {
        const res = await apiCall('select_instance', { instance_key: key });
        if (res.success) {
            state.selectedInstanceKey = key;

            // UI Updates
            $all('.instance-card').forEach(c => {
                c.classList.toggle('selected', c.querySelector('.instance-key').textContent === key);
            });

            const isConnected = ['CONNECTED', 'READY'].includes(status);
            $('selectedInstanceInfo').innerHTML = `
                <p class="instance-details">
                    <strong>Selected:</strong> ${phone} 
                    <span style="margin-left: 10px; color: var(--text-secondary);">(${key})</span>
                    <span style="margin-left: 10px;" class="instance-status ${isConnected ? 'connected' : 'disconnected'}">${status}</span>
                </p>
            `;

            $('generateQRBtn').disabled = false;
            updateStatusIndicator(status);
            notify(`Instance ${phone} selected`, 'success');

            if (isConnected) {
                $('qrContainer').innerHTML = renderSuccess('Connected Successfully!');
                stopStatusChecking();
            }
        }
    } catch (e) {
        notify('Selection failed: ' + e.message, 'error');
    }
}

async function createNewInstance() {
    const phone = $('newPhoneNumber').value;
    const key = $('customInstanceKey').value;

    if (!phone) return notify('Please enter a phone number', 'error');

    notify('Creating instance...', 'info');
    try {
        const res = await apiCall('create_instance', { phone_number: phone, instance_key: key });
        if (res.success) {
            notify('Instance created!', 'success');
            $('newPhoneNumber').value = '';
            $('customInstanceKey').value = '';
            setTimeout(loadInstances, 1000);
        } else {
            notify('Failed: ' + (res.message || 'Unknown error'), 'error');
        }
    } catch (e) {
        notify('Error: ' + e.message, 'error');
    }
}

/**
 * Configuration Logic
 */
async function saveConfiguration() {
    const apiKey = $('apiKey').value;
    const baseUrl = $('baseUrl').value;

    if (!apiKey || !baseUrl) return notify('Please fill all config fields', 'error');

    try {
        const res = await apiCall('save_config', { api_key: apiKey, base_url: baseUrl });
        if (res.success) {
            notify('Configuration saved!', 'success');
            loadInstances(); // Reload instances with new config
        }
    } catch (e) { notify('Save failed: ' + e.message, 'error'); }
}

async function loadConfiguration() {
    try {
        const res = await apiCall('get_config');
        if (res.success && res.data) {
            $('apiKey').value = res.data.api_key || '';
            $('baseUrl').value = res.data.base_url || '';
        }
    } catch (e) { console.error('Config load failed', e); }
}

/**
 * QR & Connection Logic
 */
async function generateQR() {
    if (!state.selectedInstanceKey) return notify('Select an instance first', 'error');

    state.qrRetryCount = 0;
    $('qrContainer').innerHTML = renderLoader('Starting instance...');
    notify('Starting instance...', 'info');

    try {
        const res = await apiCall('start_instance');
        if (res.success) {
            $('qrContainer').innerHTML = renderLoader('Generating QR Code...', 'This may take a few seconds');
            setTimeout(getQRCode, 3000);
            startStatusChecking();
        } else {
            throw new Error('Failed to start instance');
        }
    } catch (e) {
        notify(e.message, 'error');
        $('qrContainer').innerHTML = renderError('Failed to start instance');
    }
}

async function getQRCode() {
    try {
        const res = await apiCall('get_qr');
        const data = res.data?.data || {};
        const qrContainer = $('qrContainer');

        if (data.qr || data.qr_code) {
            const qr = data.qr || data.qr_code;
            if (qr.startsWith('data:image')) {
                qrContainer.innerHTML = `<img id="qrImage" src="${qr}" alt="QR Code">`;
            } else {
                qrContainer.innerHTML = `<div id="qrcode" style="display:inline-block;padding:20px;background:white;border-radius:12px;"></div>`;
                new QRCode($("qrcode"), { text: qr, width: 256, height: 256 });
            }
            notify('QR Generated! Scan it.', 'success');
            state.qrRetryCount = 0;

        } else if (['CONNECTED', 'READY'].includes(data.status)) {
            qrContainer.innerHTML = renderSuccess('Already Connected!');
            notify('Instance connected', 'success');
            stopStatusChecking();
            loadInstances();

        } else if (state.qrRetryCount < state.MAX_QR_RETRIES) {
            state.qrRetryCount++;
            qrContainer.innerHTML = renderLoader('Generating QR Code...', `Retry ${state.qrRetryCount}/${state.MAX_QR_RETRIES}`);
            setTimeout(getQRCode, 3000);

        } else {
            qrContainer.innerHTML = `
                <div class="qr-placeholder">
                    <p style="color: #ffc107;">Timeout</p>
                    <button class="btn btn-secondary" onclick="generateQR()" style="margin-top: 15px;">Try Again</button>
                </div>`;
            stopStatusChecking();
        }
    } catch (e) {
        console.error(e);
        $('qrContainer').innerHTML = renderError('Failed to get QR');
    }
}

async function checkStatus() {
    if (!state.selectedInstanceKey) return;

    try {
        const res = await apiCall('get_status');
        if (res.success && res.data?.data) {
            const status = res.data.data.status;
            updateStatusIndicator(status);

            const statusSpan = document.querySelector('#selectedInstanceInfo .instance-status');
            if (statusSpan) {
                statusSpan.className = `instance-status ${['CONNECTED', 'READY'].includes(status) ? 'connected' : 'disconnected'}`;
                statusSpan.textContent = status;
            }

            if (['CONNECTED', 'READY'].includes(status)) {
                stopStatusChecking();
                $('qrContainer').innerHTML = renderSuccess('Connected Successfully!');
                loadInstances();
            }
        }
    } catch (e) { console.error('Status check failed', e); }
}

function updateStatusIndicator(status) {
    const el = $('connectionStatus');
    el.querySelector('.status-text').textContent = status;
    el.classList.toggle('connected', ['CONNECTED', 'READY'].includes(status));
}

function startStatusChecking() {
    stopStatusChecking();
    state.statusCheckInterval = setInterval(checkStatus, 5000);
}

function stopStatusChecking() {
    if (state.statusCheckInterval) clearInterval(state.statusCheckInterval);
    state.statusCheckInterval = null;
}

/**
 * Message Handling
 */
function switchTab(tab) {
    $all('.message-section .tab-btn').forEach(b => b.classList.toggle('active',
        (tab === 'text' && b.innerText.includes('Text')) || (tab === 'media' && b.innerText.includes('Media'))));
    $('textTab').classList.toggle('active', tab === 'text');
    $('mediaTab').classList.toggle('active', tab === 'media');
}

function switchMessageTab(tab) {
    state.currentMessageTab = tab;
    $all('.messages-section .tab-btn').forEach(b => b.classList.toggle('active',
        (tab === 'incoming' && b.innerText === 'Incoming') || (tab === 'outgoing' && b.innerText === 'Outgoing')));
    refreshMessages();
}

async function sendMessage(type) {
    if (!state.selectedInstanceKey) return notify('Select instance first', 'error');

    const isText = type === 'text';
    const payload = isText ? {
        action: 'send_text',
        to: $('textTo').value,
        text: $('messageText').value
    } : {
        action: 'send_media',
        to: $('mediaTo').value,
        media: $('mediaUrl').value,
        caption: $('mediaCaption').value
    };

    if (!payload.to || (isText ? !payload.text : !payload.media)) {
        return notify('Please fill all fields', 'error');
    }

    try {
        const res = await apiCall(payload.action, payload);
        if (res.success) {
            notify('Sent successfully!', 'success');
            if (isText) $('messageText').value = '';
            else { $('mediaUrl').value = ''; $('mediaCaption').value = ''; }
            setTimeout(refreshMessages, 1000);
        } else {
            notify('Failed to send', 'error');
        }
    } catch (e) {
        notify('Error: ' + e.message, 'error');
    }
}

// Map old function names to new unified function
const sendTextMessage = () => sendMessage('text');
const sendMediaMessage = () => sendMessage('media');

async function refreshMessages() {
    if (!state.selectedInstanceKey) return;

    try {
        const direction = state.currentMessageTab === 'incoming' ? 'IN' : 'OUT';
        const res = await apiCall('get_messages', { direction });
        const container = $('messagesContainer');

        if (res.success && res.data?.data?.length) {
            container.innerHTML = res.data.data.map(msg => {
                const isOut = direction === 'OUT';
                const from = msg.from || msg.to || 'Unknown';
                const text = msg.payload?.text || msg.payload?.caption || 'Media message';
                const time = new Date(msg.created_at || Date.now()).toLocaleString();

                return `
                    <div class="message-item ${isOut ? 'outgoing' : 'incoming'}">
                        <div class="message-from">${isOut ? 'To' : 'From'}: ${from}</div>
                        <div class="message-text">${text}</div>
                        <div class="message-time">${time}</div>
                    </div>
                `;
            }).join('');
        } else {
            container.innerHTML = '<p class="no-messages">No messages yet</p>';
        }
    } catch (e) { console.error(e); }
}

// Init
window.addEventListener('DOMContentLoaded', () => {
    loadConfiguration();
    loadInstances();
    setInterval(refreshMessages, 10000);
});
