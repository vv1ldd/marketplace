export class StorageAdapter {
    constructor(dbName = 'SovereignArchiveDB') {
        this.dbName = dbName;
        this.db = null;
    }

    async init() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, 1);
            request.onupgradeneeded = (e) => {
                const db = e.target.result;
                if (!db.objectStoreNames.contains('artifacts')) {
                    db.createObjectStore('artifacts', { keyPath: 'id' });
                }
            };
            request.onsuccess = (e) => {
                this.db = e.target.result;
                console.log("[STORAGE ADAPTER] Civilizational Memory Initialized.");
                resolve();
            };
            request.onerror = (e) => reject(e);
        });
    }

    async archive(artifact) {
        if (!this.db) await this.init();
        return new Promise((resolve, reject) => {
            const id = 'artifact_' + Date.now();
            const record = {
                id: id,
                timestamp: Date.now(),
                status: 'LOCAL_ONLY',
                payload: artifact
            };
            const tx = this.db.transaction('artifacts', 'readwrite');
            tx.objectStore('artifacts').add(record);
            tx.oncomplete = () => resolve(id);
            tx.onerror = (e) => reject(e);
        });
    }

    async retrieveHistoricalArtifacts() {
        if (!this.db) await this.init();
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction('artifacts', 'readonly');
            const store = tx.objectStore('artifacts');
            const request = store.getAll();
            request.onsuccess = (e) => resolve(e.target.result);
            request.onerror = (e) => reject(e);
        });
    }
}

export const CivilizationalMemory = new StorageAdapter();
