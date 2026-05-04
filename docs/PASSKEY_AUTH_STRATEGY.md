# Passkey-First Authentication Strategy for jp-esim.vip

## Why Passkey-First

### Security
- Passkeys are phishing-resistant: bound to the RP ID (jp-esim.vip), they cannot be replayed on fake domains.
- No shared secrets: the private key never leaves the user's device/iCloud Keychain.
- No password reuse risk: each credential is unique per site.
- Built-in replay protection via challenge-response and sign count.

### UX
- Face ID / Touch ID / Windows Hello — one gesture to log in.
- iCloud Keychain syncs passkeys across all Apple devices automatically.
- Google Password Manager syncs across Android/Chrome.
- No passwords to remember, type, or reset.

### Future-Proof
- WebAuthn/FIDO2 is a W3C standard, backed by Apple, Google, Microsoft.
- Passkeys are the industry direction; passwords are legacy.
- Preparing now means smooth transition when passkey-only becomes the norm.

## Relying Party Configuration

| Setting      | Value                   |
|-------------|------------------------|
| RP ID       | `jp-esim.vip`          |
| RP Name     | `JP eSIM`              |
| Origin      | `https://jp-esim.vip`  |

The RP ID is the domain. Origin must be HTTPS. Subdomains of jp-esim.vip can also use this RP ID if needed in future.

## User Categories and Auth Flows

### 1. Admin
- **Current**: HTTP Basic Auth (ADMIN_USER + ADMIN_PASS from config)
- **Phase 1**: After Basic Auth, optional passkey registration. If passkey registered, can optionally verify via passkey.
- **Phase 3**: `ADMIN_REQUIRE_PASSKEY=1` adds mandatory passkey step after Basic Auth. Recovery: disable the env var to bypass.

### 2. CTV (B2B Resellers)
- **Current**: Email/password login via `CtvAuth::login()`. Session stored in `ctv_sessions` table. Cookie: `ctv_session`.
- **Phase 1**: After login+email verify, CTV can register passkeys on `/ctv/security.php`. Login page shows "Đăng nhập bằng Passkey" button for discoverable credential login.
- **Phase 2**: If CTV has passkey, UI encourages passkey-first. Password is always available as fallback.
- **Phase 4**: New CTV registration can be passkey-first: email → verify → create passkey → use passkey to log in. Password becomes optional.

### 3. Retail Customers
- **Current**: No login. Email-only flow for orders.
- **Future**: Not in scope for passkeys. Retail remains email-based.

## Current Auth System (Existing Code)

### CtvAuth (home/foamljf4kvet/app/services/CtvAuth.php)
- `register()`: email + bcrypt password → insert `ctv_users` → send verify email
- `verifyEmail()`: token → set `email_verified=1`
- `login()`: email + password → verify → insert `ctv_sessions` → set `ctv_session` cookie
- `logout()`: delete session row + clear cookie
- `currentUser()`: read cookie → join `ctv_sessions` + `ctv_users` → return user or null
- `requireUser()`: currentUser() or redirect to login
- `csrfToken()` / `checkCsrf()`: PHP session-based CSRF

### Admin Guard (public_html/admin/ctv/_guard.php)
- `admin_ctv_require()`: HTTP Basic Auth against `ADMIN_USER` / `ADMIN_PASS` from config
- PHP session (`jp_esim_admin_ctv`) for CSRF tokens and admin state
- No session-based login; re-authenticates via Basic Auth on every request

## 4-Phase Rollout Plan

### Phase 1: Optional Passkey (IMPLEMENT NOW)
- Add `user_passkeys` and `webauthn_challenges` tables
- Create `PasskeyService.php` wrapping a WebAuthn library
- CTV: register passkeys after login on `/ctv/security.php`
- CTV: login page shows "Đăng nhập bằng Passkey" (discoverable credential)
- Admin: optional passkey setup page
- Password login remains unchanged and is always available

### Phase 2: Passkey-Preferred
- CTV accounts with passkeys see passkey prompt first on login
- Password form is collapsed/secondary
- Profile shows passkey status and encouragement to register

### Phase 3: Admin Passkey Required (Optional)
- New config: `ADMIN_REQUIRE_PASSKEY=1`
- After Basic Auth, must verify passkey to proceed
- Recovery: remove the env var → passkey check skipped
- Never lock out admin permanently

### Phase 4: Passkey-First Registration
- New CTV: email → verify → create passkey (required)
- Password becomes optional fallback set during registration
- Existing CTV accounts unaffected unless they opt in

## Security Considerations

### Attestation
- **None** (attestation: "none"). We don't need to verify hardware manufacturer identity. This simplifies the flow and maximizes device compatibility.

### User Verification
- **Preferred** (userVerification: "preferred"). When biometric/PIN is available (Face ID, Touch ID, Windows Hello), the authenticator will require it. If not available, the authenticator will proceed without it. This balances security and compatibility.

### Resident Keys / Discoverable Credentials
- **Required** for CTV passkey login (requireResidentKey: true, residentKey: "required"). This enables login without typing email — the authenticator presents available credentials for jp-esim.vip.

### Challenge Lifecycle
- Challenges are one-time-use, stored in DB with expiry (5 minutes).
- Consumed immediately on verification, then deleted.
- Stale challenges cleaned up on insert.

### Sign Count Validation
- Server stores and increments sign count on each authentication.
- If client sign count <= stored sign count, credential may be cloned → reject and alert.

### Credential Storage
- Public key stored as PEM in database.
- Credential ID stored as base64url.
- No private keys ever touch the server.

## Fallback and Recovery

| Scenario | Recovery |
|----------|----------|
| CTV lost device | Login with password, register new passkey |
| CTV passkey not working | Password login is always available |
| Admin passkey required but broken | Remove `ADMIN_REQUIRE_PASSKEY` env var, restart |
| Browser doesn't support WebAuthn | Password login shown, passkey button hidden |
| iCloud Keychain sync issue | Login with password, re-register passkey |

## Browser/Device Compatibility

| Platform | Support | Notes |
|----------|---------|-------|
| Safari 16+ (iOS/macOS) | Full | iCloud Keychain passkeys |
| Chrome 108+ | Full | Google Password Manager passkeys |
| Firefox 122+ | Full | OS-level passkey support |
| Edge 108+ | Full | Windows Hello passkeys |
| Android Chrome | Full | Google Password Manager |
| Samsung Internet 21+ | Full | Samsung Pass / Google |
| Older browsers | None | Password login fallback |

WebAuthn API availability check: `window.PublicKeyCredential` existence test + `isUserVerifyingPlatformAuthenticatorAvailable()` for optimal UX.
