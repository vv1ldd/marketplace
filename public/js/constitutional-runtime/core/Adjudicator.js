import { RuntimeEvents } from './EventBus.js';
import { ConstitutionalArtifact } from '../types/ConstitutionalArtifact.js';
import { AdmissibilityResult } from '../types/AdmissibilityResult.js';
import { CryptoAdapter } from '../adapters/CryptoAdapter.js';
import { SemanticEngine } from './SemanticEngine.js';

export class ConstitutionalAdjudicator {
    constructor(activeProfile, currentEpoch) {
        this.activeProfile = activeProfile;
        this.currentEpoch = currentEpoch;
        
        // Listen to external raw scanning events
        RuntimeEvents.on('RAW_PAYLOAD_SCANNED', async (payload) => {
            await this.evaluate(payload);
        });
    }

    async evaluate(rawPayload) {
        console.log(`[ADJUDICATOR] Commencing institutional evaluation...`);
        
        try {
            // 1. Normalize
            const artifact = new ConstitutionalArtifact(rawPayload);
            RuntimeEvents.emit('ARTIFACT_NORMALIZED', artifact);

            // 2. Semantic Integrity
            const meaningValid = await SemanticEngine.verifyMeaning(artifact);
            if (!meaningValid) {
                this._emitVerdict(new AdmissibilityResult('FOREIGN', null, artifact.epoch, 'Semantic Integrity Failed'), artifact);
                return;
            }

            // 3. Cryptography & Hardware Enclave
            if (artifact.humanPresence) {
                const enclaveValid = await CryptoAdapter.verifyEnclaveAttestation(artifact.attestedClaims);
                if (!enclaveValid) {
                    this._emitVerdict(new AdmissibilityResult('FOREIGN', null, artifact.epoch, 'Enclave Attestation Failed'), artifact);
                    return;
                }
                RuntimeEvents.emit('HUMAN_PRESENCE_VERIFIED', artifact);
            }

            // 4. Diplomacy (Treaties)
            if (!this.activeProfile || !this.activeProfile.trusted_roots) {
                this._emitVerdict(new AdmissibilityResult('FOREIGN', null, artifact.epoch, 'No Trust Profile Active'), artifact);
                return;
            }

            const treaty = this.activeProfile.trusted_roots.find(t => t.root === artifact.federationRoot);
            if (!treaty) {
                this._emitVerdict(new AdmissibilityResult('FOREIGN', this.activeProfile.name, artifact.epoch, 'Federation Root Not Recognized'), artifact);
                return;
            }

            // 5. History (Epochs)
            if (artifact.epoch >= treaty.valid_epochs[0] && artifact.epoch <= treaty.valid_epochs[1]) {
                if (artifact.epoch === this.currentEpoch) {
                    // Fully Admissible
                    this._emitVerdict(new AdmissibilityResult('ACTIVE', this.activeProfile.name, artifact.epoch), artifact);
                } else {
                    // Historically Admissible
                    this._emitVerdict(new AdmissibilityResult('HISTORICAL', this.activeProfile.name, artifact.epoch, 'Epoch Mismatch (Legacy Treaty)'), artifact);
                }
            } else {
                // Treaty expired before this epoch
                this._emitVerdict(new AdmissibilityResult('REVOKED', this.activeProfile.name, artifact.epoch, 'Treaty Expired'), artifact);
            }

        } catch (err) {
            console.error(`[ADJUDICATOR] Evaluation failed:`, err);
            this._emitVerdict(new AdmissibilityResult('FOREIGN', null, null, 'Artifact Unreadable'));
        }
    }

    _emitVerdict(result, artifact = null) {
        console.log(`[ADJUDICATOR] Verdict reached: ${result.status}`);
        RuntimeEvents.emit('ADMISSIBILITY_EVALUATED', { result, artifact });
    }
}
