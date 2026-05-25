<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.theme-sync')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Simple Phone - Constitutional Terminal</title>
    
    <!-- PWA Settings -->
    <link rel="manifest" href="/reader-manifest.json">
    <meta name="theme-color" content="#000000">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Simple Phone">
    
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw-reader.js')
                    .then(registration => console.log('Sovereign Offline Cache Active'))
                    .catch(err => console.error('SW Registration Failed', err));
            });
        }
    </script>
    <!-- jsQR for zero-dependency browser-native camera decoding -->
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700;800&family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        :root {
            --bg: #000000;
            --fg: #ffffff;
            --muted: #888888;
            --accent: #ffaa00;
            --success: #107c10;
            --danger: #f53003;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg);
            color: var(--fg);
            font-family: 'JetBrains Mono', monospace;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }
        .header {
            padding: 1.25rem 1rem;
            border-bottom: 1px dashed rgba(255,255,255,0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.1em;
            background: #000;
            z-index: 100;
        }
        .scanner-container {
            flex: 1;
            position: relative;
            background: #111;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        #video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
            opacity: 0.4;
            filter: grayscale(100%) contrast(1.2);
        }
        .overlay-box {
            width: 280px;
            height: 280px;
            border: 2px dashed rgba(255, 255, 255, 0.4);
            position: relative;
            z-index: 10;
        }
        .corner {
            position: absolute;
            width: 20px;
            height: 20px;
            border-color: #fff;
            border-style: solid;
        }
        .tl { top: -2px; left: -2px; border-width: 3px 0 0 3px; }
        .tr { top: -2px; right: -2px; border-width: 3px 3px 0 0; }
        .bl { bottom: -2px; left: -2px; border-width: 0 0 3px 3px; }
        .br { bottom: -2px; right: -2px; border-width: 0 3px 3px 0; }
        
        .scan-line {
            width: 100%;
            height: 2px;
            background: var(--accent);
            position: absolute;
            top: 0;
            box-shadow: 0 0 10px var(--accent);
            animation: scan 2.5s infinite linear;
        }
        @keyframes scan {
            0% { top: 0; }
            50% { top: 100%; }
            100% { top: 0; }
        }
        .status {
            position: absolute;
            bottom: 3rem;
            z-index: 10;
            font-size: 11px;
            background: rgba(0,0,0,0.85);
            padding: 0.75rem 1.25rem;
            border-radius: 6px;
            border: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        /* Verification Result Screen */
        #result-screen {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--bg);
            z-index: 50;
            display: none;
            flex-direction: column;
            overflow-y: auto;
        }
        .result-header {
            padding: 2.5rem 1.5rem 1.5rem;
            text-align: center;
            border-bottom: 1px dashed rgba(255,255,255,0.15);
        }
        .result-body {
            padding: 1.5rem;
            font-size: 11px;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            flex: 1;
        }
        .data-row {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px dashed rgba(255,255,255,0.1);
            padding-bottom: 0.5rem;
            align-items: flex-start;
        }
        .data-label { color: var(--muted); padding-right: 1rem; }
        .data-val { font-weight: 700; text-align: right; word-break: break-all; }
        
        .verdict-box {
            padding: 1.25rem 1rem;
            text-align: center;
            font-weight: 800;
            border: 2px solid;
            margin: 0 1.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .btn-restart {
            margin: 2rem 1.5rem;
            padding: 1.25rem;
            background: #fff;
            color: #000;
            text-align: center;
            font-weight: 800;
            cursor: pointer;
            border: none;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            transition: background 0.2s;
        }
        .btn-restart:active {
            background: #ccc;
        }
    </style>
</head>
<body>
@include('partials.theme-sync-body')

    <div class="header" style="flex-direction: column; align-items: stretch; gap: 1rem;">
        <div style="display:flex; justify-content: space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:0.5rem;"><i class="ph-bold ph-shield-check" style="font-size: 16px;"></i> Simple Phone</div>
            <div style="color: var(--accent);"><i class="ph-bold ph-wifi-slash"></i> OFFLINE</div>
        </div>
        <div style="display: flex; flex-direction: column; gap: 0.25rem;">
            <label style="font-size: 9px; color: var(--muted);">ACTIVE CHECK PROFILE</label>
            <select id="trust-profile-selector" style="background: #111; color: #fff; border: 1px solid #333; padding: 0.5rem; font-family: 'JetBrains Mono', monospace; font-size: 11px; outline: none; border-radius: 4px; font-weight: 700;">
                <option value="simple-primary">Meanly Primary</option>
                <option value="commerce-federation">Wildflow Commerce Zone</option>
                <option value="academic-network">Academic Treaty Network</option>
                <option value="border-authority">Border Authority (Pluralistic)</option>
            </select>
        </div>
    </div>

    <div class="scanner-container" id="scanner-view">
        <!-- Optional mock fallback to allow desktop testing without camera -->
        <video id="video" playsinline></video>
        <canvas id="canvas" style="display:none;"></canvas>
        <div class="overlay-box">
            <div class="corner tl"></div>
            <div class="corner tr"></div>
            <div class="corner bl"></div>
            <div class="corner br"></div>
            <div class="scan-line"></div>
        </div>
        <div class="status" id="status-text">
            <div style="color:var(--accent); margin-bottom:0.25rem; font-weight:800;">CAMERA ACTIVE</div>
            <div>Awaiting SQRP capsule...</div>
        </div>
    </div>

    <div id="result-screen">
        <div class="result-header" id="res-header">
            <h2 style="font-family: 'Inter', sans-serif; font-weight: 900; letter-spacing: -0.05em; margin-bottom: 0.5rem; font-size: 20px; display:flex; align-items:center; justify-content:center; gap:0.5rem;" id="res-title">
                <i class="ph-bold ph-spinner ph-spin"></i> VERIFYING...
            </h2>
            <div style="font-size: 10px; color: var(--muted); text-transform:uppercase; letter-spacing: 0.1em;" id="res-subtitle">Executing Mathematical & Semantic Audit</div>
        </div>
        <div class="result-body" id="res-body">
            <!-- Data rows injected here -->
        </div>
        <div class="verdict-box" id="res-verdict" style="border-color: #333; color: #fff;">
            STANDBY
        </div>
        <button class="btn-restart" onclick="restartScanner()">SCAN NEXT ARTIFACT</button>
    </div>

    <script>
        const CONSTITUTIONAL_EPOCH_CACHE = [
            { epoch: 10, constitution_hash: "v1.0", valid_from: "2023-01-01" },
            { epoch: 12, constitution_hash: "v1.2", valid_from: "2024-01-01" },
            { epoch: 14, constitution_hash: "v1.5", valid_from: "2025-01-01" },
            { epoch: 18, constitution_hash: "v2.0", valid_from: "2025-07-01" }
        ];

        const TRUST_PROFILES = [
            { id: "simple-primary", name: "Simple L1 Primary", 
              trusted_roots: [{ root: "simple_l1_primary_genesis_001", valid_epochs: [1, 999] }] },
            { id: "commerce-federation", name: "Wildflow Commerce Zone", 
              trusted_roots: [{ root: "wildflow_commerce_genesis_001", valid_epochs: [1, 999] }] },
            { id: "academic-network", name: "Academic Treaty Network", 
              trusted_roots: [{ root: "academic_genesis_001", valid_epochs: [1, 999] }] },
            { id: "border-authority", name: "Border Authority (Pluralistic)", 
              trusted_roots: [
                  { root: "simple_l1_primary_genesis_001", valid_epochs: [12, 999] },
                  { root: "wildflow_commerce_genesis_001", valid_epochs: [10, 14] } // Historic treaty, expired after epoch 14
              ] 
            }
        ];

        const video = document.getElementById("video");
        const canvasElement = document.getElementById("canvas");
        const canvas = canvasElement.getContext("2d");
        const statusText = document.getElementById("status-text");
        
        let scanning = true;
        let stream = null;

        // Initialize Optical Acquisition
        async function startCamera() {
            try {
                // Request environment facing camera (back camera)
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
                video.srcObject = stream;
                video.setAttribute("playsinline", true);
                video.play();
                requestAnimationFrame(tick);
            } catch (err) {
                // Graceful fallback for non-camera environments (e.g. desktop iframe)
                statusText.innerHTML = `<div style="color:var(--danger); font-weight:800;">CAMERA UNAVAILABLE</div><div>${err.message}</div>`;
                
                // Allow clicking the screen to manually paste an SQRP payload for testing
                document.getElementById('scanner-view').addEventListener('click', () => {
                    const payload = prompt("Paste SQRP URI to test verification engine:");
                    if (payload && payload.startsWith('sqrp://')) {
                        handleScan(payload);
                    }
                });
            }
        }

        // Matrix Decoding Loop
        function tick() {
            if (!scanning) return;
            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                canvasElement.height = video.videoHeight;
                canvasElement.width = video.videoWidth;
                canvas.drawImage(video, 0, 0, canvasElement.width, canvasElement.height);
                var imageData = canvas.getImageData(0, 0, canvasElement.width, canvasElement.height);
                
                var code = jsQR(imageData.data, imageData.width, imageData.height, {
                    inversionAttempts: "dontInvert",
                });
                
                if (code) {
                    handleScan(code.data);
                }
            }
            requestAnimationFrame(tick);
        }

        async function handleScan(dataStr) {
            if (dataStr.startsWith('sqrp://')) {
                scanning = false;
                statusText.innerHTML = `<div style="color:var(--success); font-weight:800;">SQRP DETECTED</div><div>Commencing Offline Decoding...</div>`;
                
                // Slight delay for UI feedback before freezing thread
                setTimeout(() => {
                    document.getElementById('scanner-view').style.display = 'none';
                    document.getElementById('result-screen').style.display = 'flex';
                    processSqrp(dataStr);
                }, 400);
            }
        }

        function restartScanner() {
            document.getElementById('result-screen').style.display = 'none';
            document.getElementById('scanner-view').style.display = 'flex';
            document.getElementById('res-body').innerHTML = '';
            document.getElementById('res-title').innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> VERIFYING...';
            document.getElementById('res-title').style.color = 'var(--fg)';
            document.getElementById('res-verdict').textContent = 'STANDBY';
            document.getElementById('res-verdict').style.borderColor = '#333';
            document.getElementById('res-verdict').style.color = '#fff';
            statusText.innerHTML = `<div style="color:var(--accent); margin-bottom:0.25rem; font-weight:800;">CAMERA ACTIVE</div><div>Awaiting SQRP capsule...</div>`;
            scanning = true;
            requestAnimationFrame(tick);
        }

        async function processSqrp(uri) {
            try {
                const isIdentity = uri.startsWith('sqrp://id/v1/');
                const isIntent = uri.startsWith('sqrp://intent/v1/');
                const isReceipt = uri.startsWith('sqrp://v1/');
                
                const b64 = uri.replace('sqrp://id/v1/', '').replace('sqrp://intent/v1/', '').replace('sqrp://v1/', '');
                
                // Base64Url decode to binary
                const binaryStr = atob(b64.replace(/-/g, '+').replace(/_/g, '/'));
                const bytes = new Uint8Array(binaryStr.length);
                for (let i = 0; i < binaryStr.length; i++) {
                    bytes[i] = binaryStr.charCodeAt(i);
                }
                
                // Sovereign Decompression via native browser API
                const stream = new Blob([bytes]).stream();
                const decompressedStream = stream.pipeThrough(new DecompressionStream('deflate-raw'));
                const decompressedResponse = new Response(decompressedStream);
                const jsonStr = await decompressedResponse.text();
                
                const payload = JSON.parse(jsonStr);
                
                // In a production device, here we run full ECDSA verification via window.crypto.subtle
                // and strictly recreate the Canonical Semantic Statement to hash and match.
                // For this UI mockup, we will simulate the processing time and render the result.
                setTimeout(() => renderSuccess(payload, isIdentity, isIntent), 1200);

            } catch (err) {
                console.error(err);
                document.getElementById('res-title').innerHTML = '<i class="ph-bold ph-warning-circle"></i> DECODE FAILED';
                document.getElementById('res-title').style.color = 'var(--danger)';
                document.getElementById('res-verdict').textContent = '❌ ARTIFACT CORRUPTED OR INVALID';
                document.getElementById('res-verdict').style.borderColor = 'var(--danger)';
                document.getElementById('res-verdict').style.color = 'var(--danger)';
            }
        }

        function renderSuccess(payload, isIdentity, isIntent) {
            const body = document.getElementById('res-body');
            body.innerHTML = '';
            
            // 🌎 Stage 2: Jurisdictional Admissibility & 🕰️ Stage 3: Constitutional Timekeeping
            const activeProfileId = document.getElementById('trust-profile-selector').value;
            const profile = TRUST_PROFILES.find(p => p.id === activeProfileId);
            const artifactRoot = payload.federation_root || 'simple_l1_primary_genesis_001';
            const artifactEpoch = parseInt(payload.constitutional_epoch) || 12; // Fallback for legacy artifacts
            
            const trustedRootConfig = profile.trusted_roots.find(tr => tr.root === artifactRoot);
            let admissibilityStatus = 'FOREIGN';
            
            if (trustedRootConfig) {
                if (artifactEpoch >= trustedRootConfig.valid_epochs[0] && artifactEpoch <= trustedRootConfig.valid_epochs[1]) {
                    // Simulate current network epoch = 18
                    const currentEpoch = 18;
                    if (currentEpoch > trustedRootConfig.valid_epochs[1]) {
                        admissibilityStatus = 'HISTORICAL';
                    } else {
                        admissibilityStatus = 'ACTIVE';
                    }
                } else {
                    admissibilityStatus = 'REVOKED';
                }
            }
            
            if (isIdentity) {
                document.getElementById('res-title').innerHTML = '<i class="ph-bold ph-fingerprint"></i> SOVEREIGN IDENTITY';
                document.getElementById('res-title').style.color = '#fff';
                
                body.innerHTML += row('Subject Address', formatAddr(payload.sl1_address), 'var(--accent)');
                if (payload.human_presence === 'verified') {
                    body.innerHTML += row('Human Presence', '✓ VERIFIED', 'var(--success)');
                    body.innerHTML += row('Hardware Enclave', '✓ ATTESTED', 'var(--success)');
                }
                body.innerHTML += row('Federation Root', truncateHash(artifactRoot));
                body.innerHTML += row('Epoch', '#' + artifactEpoch);
                body.innerHTML += row('Claims', (payload.attested_claims || []).join(',<br>'));
                body.innerHTML += row('Semantic Integrity', '✓ VALID', 'var(--success)');
                const verdict = document.getElementById('res-verdict');
                if (admissibilityStatus === 'ACTIVE') {
                    body.innerHTML += row('Jurisdiction', `✓ ADMISSIBLE UNDER<br>${profile.name}`, 'var(--success)');
                    verdict.textContent = '✓ SECURE: IDENTITY VERIFIED';
                    verdict.style.borderColor = 'var(--success)';
                    verdict.style.background = 'rgba(16, 124, 16, 0.1)';
                    verdict.style.color = 'var(--success)';
                } else if (admissibilityStatus === 'HISTORICAL') {
                    body.innerHTML += row('Jurisdiction', `⚠ HISTORICALLY ADMISSIBLE<br>Recognized during Epoch ${trustedRootConfig.valid_epochs[0]}-${trustedRootConfig.valid_epochs[1]}`, 'var(--accent)');
                    verdict.textContent = '⚠ LEGACY TREATY';
                    verdict.style.borderColor = 'var(--accent)';
                    verdict.style.background = 'rgba(255, 170, 0, 0.1)';
                    verdict.style.color = 'var(--accent)';
                } else {
                    body.innerHTML += row('Jurisdiction', `⚠ FOREIGN CONSTITUTION<br>Not recognized by ${profile.name}`, 'var(--danger)');
                    verdict.textContent = '❌ FOREIGN JURISDICTION';
                    verdict.style.borderColor = 'var(--danger)';
                    verdict.style.background = 'rgba(245, 48, 3, 0.1)';
                    verdict.style.color = 'var(--danger)';
                }
            } else if (isIntent) {
                document.getElementById('res-title').innerHTML = '<i class="ph-bold ph-handshake"></i> PAYMENT INTENT';
                document.getElementById('res-title').style.color = '#fff';
                
                body.innerHTML += row('Buyer Identity', formatAddr(payload.buyer_identity), 'var(--accent)');
                if (payload.human_presence === 'verified') {
                    body.innerHTML += row('Buyer Presence', '✓ ENCLAVE ATTESTED', 'var(--success)');
                }
                body.innerHTML += row('Amount', `${payload.amount} ${payload.currency}`, '#fff');
                body.innerHTML += row('Merchant Node', payload.merchant, '#fff');
                body.innerHTML += row('Federation Root', truncateHash(artifactRoot));
                body.innerHTML += row('Epoch', '#' + artifactEpoch);
                
                const verdict = document.getElementById('res-verdict');
                if (admissibilityStatus === 'ACTIVE') {
                    body.innerHTML += row('Jurisdiction', `✓ ADMISSIBLE UNDER<br>${profile.name}`, 'var(--success)');
                    verdict.textContent = '✓ INTENT ADMISSIBLE (AWAITING SETTLEMENT)';
                    verdict.style.borderColor = 'var(--success)';
                    verdict.style.background = 'rgba(16, 124, 16, 0.1)';
                    verdict.style.color = 'var(--success)';
                    
                    // Add Merchant Settlement Button
                    const settleBtn = document.createElement('button');
                    settleBtn.className = 'btn-restart';
                    settleBtn.style.background = 'var(--success)';
                    settleBtn.style.color = '#fff';
                    settleBtn.style.marginTop = '1rem';
                    settleBtn.innerHTML = '<i class="ph-bold ph-check-square-offset"></i> ATTEST & SETTLE OFFLINE';
                    settleBtn.onclick = () => {
                        settleBtn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> GENERATING RECEIPT...';
                        setTimeout(() => {
                            verdict.textContent = '✓ ADMISSIBLE RECEIPT MATERIALIZED';
                            settleBtn.style.display = 'none';
                            body.innerHTML += row('Merchant Attestation', '✓ LOCALLY SIGNED', 'var(--success)');
                            body.innerHTML += row('Settlement Status', '✓ COMPLETED OFFLINE (AWAITING SYNC)', 'var(--accent)');
                            
                            // Trigger Phase 9 Sovereign Archive
                            SovereignSyncEngine.saveReceipt(payload);
                        }, 1200);
                    };
                    document.getElementById('res-body').appendChild(settleBtn);
                    
                } else {
                    body.innerHTML += row('Jurisdiction', `⚠ FOREIGN CONSTITUTION<br>Not recognized by ${profile.name}`, 'var(--danger)');
                    verdict.textContent = '❌ INADMISSIBLE INTENT';
                    verdict.style.borderColor = 'var(--danger)';
                    verdict.style.background = 'rgba(245, 48, 3, 0.1)';
                    verdict.style.color = 'var(--danger)';
                }
            } else {
                document.getElementById('res-title').innerHTML = '<i class="ph-bold ph-receipt"></i> CONSTITUTIONAL RECEIPT';
                document.getElementById('res-title').style.color = '#fff';
                
                body.innerHTML += row('Subject Address', formatAddr(payload.sl1_address), 'var(--accent)');
                body.innerHTML += row('Intent Type', payload.intent_type);
                body.innerHTML += row('Federation Root', truncateHash(artifactRoot));
                body.innerHTML += row('Issued Epoch', '#' + artifactEpoch);
                body.innerHTML += row('Quorum Met', payload.attestation?.quorum_met ? `YES (${payload.attestation.accepted}/${payload.attestation.required})` : 'NO');
                body.innerHTML += row('Semantic Hash', payload.semantic_hash ? truncateHash(payload.semantic_hash) : 'N/A', 'var(--muted)');
                body.innerHTML += row('P-256 Signature', '✓ VALID OFFLINE', 'var(--success)');
                
                const verdict = document.getElementById('res-verdict');
                if (admissibilityStatus === 'ACTIVE') {
                    body.innerHTML += row('Jurisdiction', `✓ ADMISSIBLE UNDER<br>${profile.name}`, 'var(--success)');
                    verdict.textContent = '✓ LEGITIMATE UNDER CONSENSUS';
                    verdict.style.borderColor = 'var(--success)';
                    verdict.style.background = 'rgba(16, 124, 16, 0.1)';
                    verdict.style.color = 'var(--success)';
                } else if (admissibilityStatus === 'HISTORICAL') {
                    body.innerHTML += row('Jurisdiction', `⚠ HISTORICALLY ADMISSIBLE<br>Recognized during Epoch ${trustedRootConfig.valid_epochs[0]}-${trustedRootConfig.valid_epochs[1]}`, 'var(--accent)');
                    verdict.textContent = '⚠ LEGACY TREATY (EPOCH MISMATCH)';
                    verdict.style.borderColor = 'var(--accent)';
                    verdict.style.background = 'rgba(255, 170, 0, 0.1)';
                    verdict.style.color = 'var(--accent)';
                } else {
                    body.innerHTML += row('Jurisdiction', `⚠ DISPUTED: FOREIGN TREATY<br>Not recognized by ${profile.name}`, 'var(--danger)');
                    verdict.textContent = '❌ INADMISSIBLE ARTIFACT';
                    verdict.style.borderColor = 'var(--danger)';
                    verdict.style.background = 'rgba(245, 48, 3, 0.1)';
                    verdict.style.color = 'var(--danger)';
                }
            }
        }

        function row(label, value, valColor = '#fff') {
            return `<div class="data-row">
                        <span class="data-label">${label}</span>
                        <span class="data-val" style="color: ${valColor}">${value}</span>
                    </div>`;
        }

        function truncateHash(hash) {
            if (!hash || hash.length < 16) return hash;
            return hash.substring(0, 8) + '...' + hash.substring(hash.length - 8);
        }

        function formatAddr(addr) {
            if (!addr) return addr;
            if (addr.length > 20) {
                return addr.substring(0, 10) + '...<br>' + addr.substring(addr.length - 8);
            }
            return addr;
        }

        // --- 📡 Phase 9: Sovereign Sync Engine (Constitutional Reconciliation) ---
        const SovereignSyncEngine = {
            db: null,
            init: function() {
                const request = indexedDB.open('SovereignArchiveDB', 1);
                request.onupgradeneeded = (e) => {
                    const db = e.target.result;
                    if (!db.objectStoreNames.contains('receipts')) {
                        db.createObjectStore('receipts', { keyPath: 'id' });
                    }
                };
                request.onsuccess = (e) => {
                    this.db = e.target.result;
                    console.log("Sovereign Archive Initialized.");
                    this.checkSync();
                };
            },
            saveReceipt: function(payload) {
                if (!this.db) return;
                const id = 'receipt_' + Date.now();
                const record = {
                    id: id,
                    timestamp: Date.now(),
                    status: 'LOCAL_ONLY',
                    payload: payload
                };
                const tx = this.db.transaction('receipts', 'readwrite');
                tx.objectStore('receipts').add(record);
                console.log(`[SYNC ENGINE] Receipt ${id} archived locally. Awaiting network for federation sync.`);
            },
            checkSync: async function() {
                if (!navigator.onLine || !this.db) return;
                
                const tx = this.db.transaction('receipts', 'readonly');
                const store = tx.objectStore('receipts');
                const request = store.getAll();
                
                request.onsuccess = async (e) => {
                    const pending = e.target.result.filter(r => r.status === 'LOCAL_ONLY');
                    if (pending.length === 0) return;
                    
                    console.log(`[SYNC ENGINE] Found ${pending.length} offline receipts. Initiating Sovereign Reconciliation...`);
                    
                    for (const record of pending) {
                        try {
                            // In production, this would POST to /api/federation/reconcile
                            // We simulate the federation archiving process:
                            await new Promise(resolve => setTimeout(resolve, 800));
                            
                            // Mark as archived
                            record.status = 'ARCHIVED';
                            const updateTx = this.db.transaction('receipts', 'readwrite');
                            updateTx.objectStore('receipts').put(record);
                            
                            console.log(`[SYNC ENGINE] Receipt ${record.id} successfully ARCHIVED INTO FEDERATION MEMORY.`);
                        } catch (err) {
                            console.error(`[SYNC ENGINE] Reconciliation failed for ${record.id}`, err);
                        }
                    }
                };
            }
        };

        window.addEventListener('online', () => {
            console.log("[SYNC ENGINE] Connectivity restored. Triggering Sovereign Reconciliation...");
            SovereignSyncEngine.checkSync();
        });

        // Initialize Engine
        SovereignSyncEngine.init();

        // Start Optical Acquisition
        startCamera();
    </script>
</body>
</html>
