export class AdmissibilityResult {
    constructor(status, profileId, epoch, reason = null) {
        // Status: 'ACTIVE', 'HISTORICAL', 'FOREIGN', 'REVOKED', 'DISPUTED'
        this.status = status;
        this.profileId = profileId;
        this.epoch = epoch;
        this.reason = reason;
    }

    isAdmissible() {
        return this.status === 'ACTIVE';
    }

    isHistoricallyAdmissible() {
        return this.status === 'HISTORICAL';
    }
}
