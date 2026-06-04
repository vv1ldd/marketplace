# Simple Layer SL1E Client

Framework-neutral PHP client for Simple Layer SL1E authorization-code flows.

## Native Deep Link Contract

Meanly can launch a local Simple L1 identity agent before falling back to the web identity provider.

The native app should register this URL scheme:

```text
simplel1://authorize
```

The query string matches the web `/authorize` request:

```text
simplel1://authorize?client_id=meanly.one&client_name=Meanly&redirect_uri=https%3A%2F%2Fmeanly.test%2Fsimple-l1%2Fcallback&scope=openid+sl1e+marketplace&state=...&nonce=...&mode=login&response_mode=code
```

Minimum native-agent behavior:

1. Validate `client_id`, `redirect_uri`, `state`, and `nonce` are present.
2. Show the requested intent/consent details when `intent_*` params are provided.
3. Ask the user to unlock/sign with the local passkey or platform secure key.
4. Return to `redirect_uri` with `code`, `proof_token`, or `proof_response` plus the original `state`.
5. If the native agent cannot complete the request, leave the browser fallback untouched.

For local-first login, `proof_response` should be base64url-encoded JSON containing `active: true`, a short-lived `proof_token` or native session handle, and a `proof` object with `clientId`, `redirectUri`, `state`, `nonce`, `mode`, `entityAddress`, `issuedAt`, and `expiresAt`.

Hosts should only accept direct native proofs when they also enforce local proof signature validation. Meanly keeps this behind an explicit development flag while the native-agent verifier is being hardened.

The web identity provider remains the compatibility path. Meanly attempts the native deep link first, then falls back to the configured `identity_provider_url`.
