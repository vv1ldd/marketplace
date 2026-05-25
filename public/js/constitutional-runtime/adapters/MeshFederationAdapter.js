import { RuntimeEvents } from '../core/EventBus.js';

export class MeshFederationAdapter {
    constructor(channelName = 'sl1_mesh_federation') {
        this.channel = new BroadcastChannel(channelName);
        this.channel.onmessage = (event) => this.handleMessage(event.data);
    }

    announcePresence(profile, epoch) {
        this.localProfile = profile;
        this.localEpoch = epoch;
        this.channel.postMessage({
            type: 'FEDERATION_HELLO',
            payload: {
                profileName: profile.name,
                trustedRoots: profile.trusted_roots || [],
                epoch: epoch,
                timestamp: Date.now()
            }
        });
    }

    publishArtifact(artifact) {
        this.channel.postMessage({
            type: 'ARTIFACT_BROADCAST',
            payload: artifact
        });
    }

    handleMessage(msg) {
        if (!msg || !msg.type) return;

        switch (msg.type) {
            case 'FEDERATION_HELLO':
                console.log(`[MESH FEDERATION] Peer discovered: ${msg.payload.profileName} (Epoch #${msg.payload.epoch})`);
                RuntimeEvents.emit('MESH_PEER_DISCOVERED', msg.payload);
                this.negotiateTreaty(msg.payload);
                break;

            case 'ARTIFACT_BROADCAST':
                console.log(`[MESH FEDERATION] Incoming broadcasted artifact from mesh...`);
                RuntimeEvents.emit('RAW_PAYLOAD_SCANNED', msg.payload);
                break;

            case 'TREATY_NEGOTIATED':
                console.log(`[MESH FEDERATION] Joint treaty signed by peer: ${msg.payload.negotiatedName}`);
                RuntimeEvents.emit('MESH_TREATY_SIGNED', msg.payload);
                break;
        }
    }

    negotiateTreaty(remotePeer) {
        if (!this.localProfile) {
            console.log("[MESH FEDERATION] Negotiation skipped: Local profile not announced yet.");
            return;
        }

        RuntimeEvents.emit('TREATY_NEGOTIATION_STARTED', remotePeer);

        // Find intersection of trusted roots
        const localRoots = this.localProfile.trusted_roots || [];
        const remoteRoots = remotePeer.trustedRoots || [];

        const sharedRoots = localRoots.filter(localRoot => 
            remoteRoots.some(remoteRoot => remoteRoot.root === localRoot.root)
        );

        if (sharedRoots.length > 0) {
            const sharedRootNames = sharedRoots.map(r => r.root);
            RuntimeEvents.emit('JURISDICTION_INTERSECTION_COMPUTED', {
                sharedRoots: sharedRootNames,
                localEpoch: this.localEpoch,
                remoteEpoch: remotePeer.epoch
            });

            const federationId = 'mesh://temporary/' + Math.random().toString(36).substring(2, 6);
            const negotiatedName = `Mesh Joint Authority (${sharedRootNames.join(', ')})`;
            
            RuntimeEvents.emit('EPHEMERAL_FEDERATION_ESTABLISHED', {
                federationId: federationId,
                negotiatedName: negotiatedName,
                validity: 'session'
            });

            // Announce agreement back to the peer
            this.channel.postMessage({
                type: 'TREATY_NEGOTIATED',
                payload: {
                    federationId: federationId,
                    negotiatedName: negotiatedName
                }
            });
        } else {
            RuntimeEvents.emit('MESH_NEGOTIATION_FAILED', {
                reason: 'No overlapping treaty roots found between entities'
            });
        }
    }
}

export const MeshFederation = new MeshFederationAdapter();
