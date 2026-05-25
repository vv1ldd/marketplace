export class ConstitutionalArtifact {
    constructor(payload) {
        this.schema = payload.schema || 'unknown';
        this.intentType = payload.intent_type || null;
        this.amount = payload.amount || null;
        this.currency = payload.currency || null;
        this.merchant = payload.merchant || null;
        this.subjectAddress = payload.sl1_address || payload.buyer_identity || null;
        this.federationRoot = payload.federation_root || null;
        this.epoch = parseInt(payload.constitutional_epoch) || 0;
        this.attestedClaims = payload.attested_claims || [];
        this.humanPresence = payload.human_presence === 'verified';
        this.deviceClass = payload.device_class || 'unknown';
        this.semanticHash = payload.semantic_hash || null;
        this.signature = payload.signature || null;
        this.attestation = payload.attestation || null;
        
        // Deduce artifact type
        this.isIdentity = this.schema.includes('identity');
        this.isIntent = this.schema.includes('intent');
        this.isReceipt = this.schema.includes('receipt') || this.attestation !== null;
    }
}
