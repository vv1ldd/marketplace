export class ConstitutionalEventBus {
    constructor() {
        this.listeners = {};
    }

    on(event, callback) {
        if (!this.listeners[event]) {
            this.listeners[event] = [];
        }
        this.listeners[event].push(callback);
    }

    emit(event, payload) {
        console.log(`[KERNEL BUS] 📢 ${event}`, payload || '');
        if (this.listeners[event]) {
            this.listeners[event].forEach(callback => {
                try {
                    callback(payload);
                } catch (e) {
                    console.error(`[KERNEL BUS] Error in listener for ${event}:`, e);
                }
            });
        }
    }
}

// Global Singleton Instance
export const RuntimeEvents = new ConstitutionalEventBus();
