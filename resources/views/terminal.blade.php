<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.theme-sync')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#050505">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Meanly Terminal</title>
    
    <!-- Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg: #050505;
            --surface: #111;
            --border: #222;
            --fg: #eee;
            --muted: #666;
            --brand: #00ff88;
            --accent: #ffaa00;
            --danger: #ff3333;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'JetBrains Mono', monospace;
            background: var(--bg);
            color: var(--fg);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            user-select: none;
            -webkit-user-select: none;
        }

        /* Top Bar */
        .status-bar {
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.1em;
            color: var(--brand);
            background: var(--bg);
            z-index: 10;
        }

        .status-badge {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--brand);
            box-shadow: 0 0 8px var(--brand);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; box-shadow: 0 0 8px var(--brand); }
            50% { opacity: 0.5; box-shadow: 0 0 2px var(--brand); }
            100% { opacity: 1; box-shadow: 0 0 8px var(--brand); }
        }

        /* Views Container */
        .views-container {
            flex: 1;
            position: relative;
            overflow: hidden;
            background: radial-gradient(circle at 50% 0%, #1a1a1a 0%, #050505 100%);
        }

        .view-layer {
            position: absolute;
            inset: 0;
            display: none;
            flex-direction: column;
            overflow-y: auto;
            padding: 1rem;
            animation: fadeIn 0.2s ease-out;
        }
        
        .view-layer.active {
            display: flex;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Bottom Nav */
        .nav-bar {
            display: flex;
            justify-content: space-around;
            padding: 0.8rem 0;
            padding-bottom: calc(0.8rem + env(safe-area-inset-bottom, 0px));
            background: var(--surface);
            border-top: 1px solid var(--border);
            z-index: 10;
        }

        .nav-btn {
            background: transparent;
            border: none;
            color: var(--muted);
            font-family: 'JetBrains Mono', monospace;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.05em;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.4rem;
            cursor: pointer;
            transition: color 0.2s;
            width: 25%;
        }

        .nav-btn i {
            font-size: 20px;
        }

        .nav-btn.active {
            color: var(--brand);
        }

        /* UI Elements */
        .panel {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .panel-header {
            font-size: 10px;
            color: var(--muted);
            text-transform: uppercase;
            margin-bottom: 0.8rem;
            display: flex;
            justify-content: space-between;
        }
        
        .data-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 11px;
            border-bottom: 1px dashed rgba(255, 255, 255, 0.1);
            padding-bottom: 0.5rem;
        }
        
        .btn-action {
            width: 100%;
            padding: 1rem;
            background: rgba(0, 255, 136, 0.1);
            border: 1px solid var(--brand);
            color: var(--brand);
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            font-weight: 800;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            transition: background 0.2s;
        }
        .btn-action:active {
            background: var(--brand);
            color: #000;
        }
    </style>
</head>
<body>
@include('partials.theme-sync-body')

    <div class="status-bar">
        <div class="status-badge">
            <div class="status-dot"></div>
            <span>SYS: ENCLAVE ATTESTED</span>
        </div>
        <span>EPOCH #18</span>
    </div>

    <div class="views-container">
        
        <!-- VAULT VIEW -->
        <div id="view-vault" class="view-layer active">
            <div class="panel">
                <div class="panel-header">
                    <span>ACCOUNT PROFILE</span>
                    <span style="color: var(--brand)"><i class="ph-bold ph-shield-check"></i> ACTIVE</span>
                </div>
                <div class="data-row" style="flex-direction: column; gap: 0.3rem; border: none;">
                    <span style="color: #fff; font-size: 13px;">profile_7x9f...4a2b</span>
                    <span style="color: var(--muted);">Passkey Linked • Human Presence Confirmed</span>
                </div>
                <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                    <button class="btn-action" style="flex: 1;"><i class="ph-bold ph-handshake"></i> INTENT</button>
                    <button class="btn-action" style="flex: 1; background: transparent; border-color: var(--muted); color: var(--fg);"><i class="ph-bold ph-qr-code"></i> PROFILE</button>
                </div>
            </div>
            
            <div class="panel">
                <div class="panel-header"><span>SYNC ENGINE STATUS</span></div>
                <div class="data-row"><span>Pending Offline Artifacts</span> <span style="color: var(--accent)">2 LOCAL</span></div>
                <div class="data-row" style="border:none;"><span>Reconciliation Status</span> <span style="color: var(--brand)">AWAITING NET</span></div>
            </div>
        </div>

        <!-- SCAN VIEW -->
        <div id="view-scan" class="view-layer">
            <div style="flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; color: var(--muted); border: 1px dashed var(--border); border-radius: 8px; margin-bottom: 1rem;">
                <i class="ph-bold ph-scan" style="font-size: 48px; margin-bottom: 1rem;"></i>
                <p style="font-size: 11px; text-align: center; max-width: 80%;">LOCAL CHECKER<br><br>Scan a payment, profile, or receipt to verify it locally.</p>
            </div>
            <button class="btn-action" onclick="window.simulateScan()"><i class="ph-bold ph-check-circle"></i> SIMULATE ADMISSIBLE ARTIFACT</button>
            <button class="btn-action" style="background: rgba(255, 51, 51, 0.1); border-color: var(--danger); color: var(--danger);" onclick="window.simulateForeignScan()"><i class="ph-bold ph-warning-circle"></i> SIMULATE FOREIGN ARTIFACT</button>
        </div>

        <!-- HISTORY VIEW -->
        <div id="view-history" class="view-layer">
            <div class="panel">
                <div class="panel-header"><span>CIVILIZATIONAL MEMORY</span></div>
                
                <div style="border-left: 2px solid var(--border); padding-left: 1rem; margin-top: 1rem; display: flex; flex-direction: column; gap: 1.5rem;">
                    
                    <div style="position: relative;">
                        <div style="position: absolute; left: -1.05rem; top: 0; width: 8px; height: 8px; border-radius: 50%; background: var(--brand);"></div>
                        <div style="font-size: 9px; color: var(--brand); margin-bottom: 0.2rem;">EPOCH #18 • FEDERATED</div>
                        <div style="font-size: 12px; color: #fff; margin-bottom: 0.2rem;">Passkey confirmation</div>
                        <div style="font-size: 10px; color: var(--muted);">Merchant Terminal 77 • Wildflow Zone</div>
                    </div>
                    
                    <div style="position: relative;">
                        <div style="position: absolute; left: -1.05rem; top: 0; width: 8px; height: 8px; border-radius: 50%; background: var(--accent);"></div>
                        <div style="font-size: 9px; color: var(--accent); margin-bottom: 0.2rem;">EPOCH #18 • LOCAL ONLY</div>
                        <div style="font-size: 12px; color: #fff; margin-bottom: 0.2rem;">Admissible Receipt Materialized</div>
                        <div style="font-size: 10px; color: var(--muted);">12.50 USD • Awaiting reconciliation</div>
                    </div>

                    <div style="position: relative;">
                        <div style="position: absolute; left: -1.05rem; top: 0; width: 8px; height: 8px; border-radius: 50%; background: var(--muted);"></div>
                        <div style="font-size: 9px; color: var(--muted); margin-bottom: 0.2rem;">EPOCH #12 • HISTORIC</div>
                        <div style="font-size: 12px; color: #fff; margin-bottom: 0.2rem;">Treaty Recognized</div>
                        <div style="font-size: 10px; color: var(--muted);">Border Authority Treaty Network</div>
                    </div>

                </div>
            </div>
        </div>

        <!-- DIPLOMACY VIEW -->
        <div id="view-diplomacy" class="view-layer">
            <div class="panel">
                <div class="panel-header"><span>JURISDICTIONAL REALITY</span></div>
                <div style="margin-bottom: 0.5rem; font-size: 11px; color: var(--muted);">Active Trust Profile</div>
                <select style="width: 100%; background: #000; border: 1px solid var(--border); color: #fff; padding: 0.8rem; font-family: 'JetBrains Mono'; font-size: 12px; margin-bottom: 1.5rem; border-radius: 4px; outline: none;">
                    <option>Border Authority (Pluralistic)</option>
                    <option>Meanly Primary</option>
                    <option>Wildflow Commerce</option>
                </select>
                
                <div class="data-row"><span>Local Jurisdiction</span> <span style="color: var(--brand)">ADMISSIBLE</span></div>
                <div class="data-row"><span>Treaty Roots</span> <span style="color: var(--accent)">2 ACTIVE</span></div>
                <div class="data-row" style="border:none;"><span>Revoked Treaties</span> <span style="color: var(--muted)">1 HISTORIC</span></div>
            </div>
            
            <div class="panel">
                <div class="panel-header"><span>CONNECTED SERVICES</span></div>
                <div id="mesh-peers-list" style="font-size: 10px; color: var(--muted); margin-bottom: 1rem;">
                    No active mesh peers detected on channel...
                </div>
                <button class="btn-action" onclick="window.announceMeshPresence()"><i class="ph-bold ph-broadcast"></i> ANNOUNCE PRESENCE</button>
            </div>
            
            <div class="panel">
                <div class="panel-header"><span>TREATY GRAPH</span></div>
                <div style="text-align: center; color: var(--muted); font-size: 10px; padding: 1rem 0;">
                    [ NODE: LOCAL ] ←→ [ NODE: SIMPLE_L1 ]<br>
                    [ NODE: LOCAL ] ←/→ [ NODE: ACADEMIC ]
                </div>
            </div>
        </div>

    </div>

    <div id="runtime-overlay" style="position: fixed; bottom: 85px; left: 1rem; right: 1rem; background: rgba(0,0,0,0.8); border: 1px solid var(--border); border-radius: 8px; padding: 0.8rem; font-size: 9px; z-index: 100; backdrop-filter: blur(4px);">
        <div style="color: var(--brand); font-weight: 800; margin-bottom: 0.5rem; letter-spacing: 0.1em;">RUNTIME TELEMETRY</div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 0.3rem;"><span style="color: var(--muted)">KERNEL:</span><span style="color: #fff">ACTIVE</span></div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 0.3rem;"><span style="color: var(--muted)">LAST EVENT:</span><span id="rt-last-event" style="color: var(--accent)">SYSTEM_BOOT</span></div>
        <div style="display: flex; justify-content: space-between;"><span style="color: var(--muted)">VERDICT:</span><span id="rt-verdict" style="color: var(--brand)">AWAITING ARTIFACT</span></div>
    </div>

    <div class="nav-bar">
        <button class="nav-btn active" onclick="switchTab('vault', event)">
            <i class="ph-bold ph-dna"></i>
            VAULT
        </button>
        <button class="nav-btn" onclick="switchTab('scan', event)">
            <i class="ph-bold ph-eye"></i>
            SCAN
        </button>
        <button class="nav-btn" onclick="switchTab('history', event)">
            <i class="ph-bold ph-clock-counter-clockwise"></i>
            HISTORY
        </button>
        <button class="nav-btn" onclick="switchTab('diplomacy', event)">
            <i class="ph-bold ph-scales"></i>
            DIPLOMACY
        </button>
    </div>

    <script type="module">
        import { RuntimeEvents } from '/js/constitutional-runtime/core/EventBus.js';
        import { ConstitutionalAdjudicator } from '/js/constitutional-runtime/core/Adjudicator.js';
        import { MeshFederation } from '/js/constitutional-runtime/adapters/MeshFederationAdapter.js';

        // 1. Boot the Kernel
        const activeProfile = {
            name: "Border Authority (Pluralistic)",
            trusted_roots: [
                { root: "simple-l1-primary", valid_epochs: [10, 999] },
                { root: "wildflow_commerce", valid_epochs: [12, 17] }
            ]
        };
        const currentEpoch = 18;
        const kernel = new ConstitutionalAdjudicator(activeProfile, currentEpoch);

        // 2. Wire Phenomenological Observers (Telemetry)
        RuntimeEvents.on('RAW_PAYLOAD_SCANNED', (payload) => {
            document.getElementById('rt-last-event').textContent = 'RAW_PAYLOAD_SCANNED';
            document.getElementById('rt-last-event').style.color = '#fff';
            document.getElementById('rt-verdict').textContent = 'EVALUATING...';
            document.getElementById('rt-verdict').style.color = 'var(--muted)';
        });

        RuntimeEvents.on('HUMAN_PRESENCE_VERIFIED', (artifact) => {
            document.getElementById('rt-last-event').textContent = 'HUMAN_PRESENCE_VERIFIED';
            document.getElementById('rt-last-event').style.color = 'var(--brand)';
        });

        RuntimeEvents.on('ADMISSIBILITY_EVALUATED', ({ result, artifact }) => {
            document.getElementById('rt-last-event').textContent = 'ADMISSIBILITY_EVALUATED';
            const verdictEl = document.getElementById('rt-verdict');
            if (result.isAdmissible()) {
                verdictEl.textContent = 'ACTIVE (' + result.profileId + ')';
                verdictEl.style.color = 'var(--brand)';
            } else if (result.isHistoricallyAdmissible()) {
                verdictEl.textContent = 'HISTORICAL (' + result.reason + ')';
                verdictEl.style.color = 'var(--accent)';
            } else {
                verdictEl.textContent = 'FOREIGN (' + result.reason + ')';
                verdictEl.style.color = 'var(--danger)';
            }
        });

        // 3. Wire Mesh Federation Events
        RuntimeEvents.on('MESH_PEER_DISCOVERED', (peer) => {
            const list = document.getElementById('mesh-peers-list');
            list.innerHTML = `
                <div id="peer-${peer.profileName.replace(/\s+/g, '')}" style="padding: 0.5rem; background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: 4px; margin-bottom: 0.5rem;">
                    <strong>${peer.profileName}</strong><br>
                    Epoch: #${peer.epoch}<br>
                    <span id="negotiation-status-${peer.profileName.replace(/\s+/g, '')}" style="color: var(--accent)">DISCOVERED • STARTING NEGOTIATION...</span>
                </div>
            `;
            document.getElementById('rt-last-event').textContent = 'MESH_PEER_DISCOVERED';
            document.getElementById('rt-last-event').style.color = '#fff';
        });

        RuntimeEvents.on('TREATY_NEGOTIATION_STARTED', (peer) => {
            document.getElementById('rt-last-event').textContent = 'TREATY_NEGOTIATION_STARTED';
            document.getElementById('rt-last-event').style.color = 'var(--accent)';
        });

        RuntimeEvents.on('JURISDICTION_INTERSECTION_COMPUTED', (data) => {
            document.getElementById('rt-last-event').textContent = 'JURISDICTION_INTERSECTION';
            console.log(`[UI TELEMETRY] Shared trust roots discovered: ${data.sharedRoots.join(', ')}`);
        });

        RuntimeEvents.on('EPHEMERAL_FEDERATION_ESTABLISHED', (data) => {
            document.getElementById('rt-last-event').textContent = 'FEDERATION_ESTABLISHED';
            document.getElementById('rt-last-event').style.color = 'var(--brand)';
            
            // Highlight the verdict and telemetry overlay too
            document.getElementById('rt-verdict').textContent = 'FEDERATED (' + data.federationId + ')';
            document.getElementById('rt-verdict').style.color = 'var(--brand)';

            // Update peer card
            const statusEl = document.querySelector('[id^="negotiation-status-"]');
            if (statusEl) {
                statusEl.innerHTML = `<span style="color: var(--brand)">✓ TEMPORARY CONNECTION READY (${data.federationId})</span>`;
            }
        });

        RuntimeEvents.on('MESH_TREATY_SIGNED', (data) => {
            document.getElementById('rt-last-event').textContent = 'MESH_TREATY_SIGNED';
            document.getElementById('rt-last-event').style.color = 'var(--brand)';
        });

        RuntimeEvents.on('MESH_NEGOTIATION_FAILED', (data) => {
            document.getElementById('rt-last-event').textContent = 'NEGOTIATION_FAILED';
            document.getElementById('rt-last-event').style.color = 'var(--danger)';
            const statusEl = document.querySelector('[id^="negotiation-status-"]');
            if (statusEl) {
                statusEl.innerHTML = `<span style="color: var(--danger)">✗ FAILED: ${data.reason}</span>`;
            }
        });

        window.announceMeshPresence = function() {
            MeshFederation.announcePresence(activeProfile, currentEpoch);
        };

        // Expose a test function to simulate scanning an artifact
        window.simulateScan = function() {
            RuntimeEvents.emit('RAW_PAYLOAD_SCANNED', {
                schema: "sovereign_intent_v1",
                amount: "12.50",
                currency: "SL1_USD",
                sl1_address: "SL1_7x9f2a4b",
                federation_root: "simple-l1-primary",
                constitutional_epoch: 18,
                human_presence: "verified",
                attested_claims: ["PASSKEY_SECURED"]
            });
        };

        window.simulateForeignScan = function() {
            RuntimeEvents.emit('RAW_PAYLOAD_SCANNED', {
                schema: "sovereign_intent_v1",
                amount: "500",
                federation_root: "unknown-network",
                constitutional_epoch: 18
            });
        };
    </script>
    <script>
        function switchTab(tabId, event) {
            document.querySelectorAll('.view-layer').forEach(el => el.classList.remove('active'));
            document.getElementById('view-' + tabId).classList.add('active');
            
            document.querySelectorAll('.nav-btn').forEach(btn => btn.classList.remove('active'));
            if (event) {
                event.currentTarget.classList.add('active');
            }
        }
    </script>
</body>
</html>
