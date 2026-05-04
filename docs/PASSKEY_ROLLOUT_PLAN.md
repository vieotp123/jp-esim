# Passkey Rollout Plan — Phase 1

## Database Schema

### user_passkeys
```sql
CREATE TABLE user_passkeys (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_type ENUM('ctv','admin') NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  credential_id VARCHAR(512) NOT NULL,        -- base64url, unique
  public_key_pem TEXT NOT NULL,               -- PEM-encoded public key
  sign_count INT UNSIGNED NOT NULL DEFAULT 0,
  transports JSON NULL,                        -- ["internal","hybrid"] etc
  aaguid VARCHAR(36) NULL,                     -- authenticator AAGUID
  device_name VARCHAR(128) NULL,               -- user-assigned name
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME NULL,
  UNIQUE KEY uk_credential_id (credential_id(255)),
  KEY idx_user (user_type, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### webauthn_challenges
```sql
CREATE TABLE webauthn_challenges (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  challenge VARCHAR(128) NOT NULL,             -- base64url
  user_type ENUM('ctv','admin') NOT NULL,
  user_id INT UNSIGNED NULL,                   -- NULL for discoverable auth
  type ENUM('register','authenticate') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  KEY idx_challenge (challenge(64)),
  KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Files to Create

| File | Purpose |
|------|---------|
| `migrations/004_passkey_auth.sql` | Database tables |
| `home/foamljf4kvet/app/services/PasskeyService.php` | WebAuthn wrapper |
| `public_html/ctv/security.php` | CTV passkey management page |
| `public_html/ctv/passkey-api.php` | CTV passkey session-based API |
| `public_html/assets/passkey.js` | Client-side WebAuthn helpers |
| `public_html/admin/ctv/passkey-setup.php` | Admin passkey management |
| `public_html/admin/ctv/passkey-api.php` | Admin passkey API |

## Files to Modify

| File | Changes |
|------|---------|
| `public_html/ctv/login.php` | Add "Đăng nhập bằng Passkey" button |
| `public_html/ctv/_layout.php` | Add "Bảo mật" nav link |
| `public_html/admin/ctv/_guard.php` | Add Passkey nav link, optional passkey check |
| `home/foamljf4kvet/app/services/CtvAuth.php` | Add `loginWithPasskey()` method |

## API Endpoint Design

### CTV Passkey API (`/ctv/passkey-api.php`)
Session-authenticated (uses `ctv_session` cookie).

| Action | Method | Auth | Description |
|--------|--------|------|-------------|
| `register_begin` | POST | CTV session | Returns PublicKeyCredentialCreationOptions |
| `register_finish` | POST | CTV session | Validates attestation, stores credential |
| `authenticate_begin` | POST | None | Returns PublicKeyCredentialRequestOptions |
| `authenticate_finish` | POST | None | Validates assertion, creates session |
| `list` | GET | CTV session | Returns user's registered passkeys |
| `revoke` | POST | CTV session | Deletes a passkey by credential ID |

### Admin Passkey API (`/admin/ctv/passkey-api.php`)
Basic Auth + session.

| Action | Method | Auth | Description |
|--------|--------|------|-------------|
| `register_begin` | POST | Admin Basic Auth | Returns creation options |
| `register_finish` | POST | Admin Basic Auth | Stores credential |
| `authenticate_begin` | POST | Admin Basic Auth | Returns request options |
| `authenticate_finish` | POST | Admin Basic Auth | Validates, sets session flag |
| `list` | GET | Admin Basic Auth | Returns admin passkeys |
| `revoke` | POST | Admin Basic Auth | Deletes a passkey |

## UI/UX Flow — CTV Passkey Registration

```
[CTV Dashboard] → [Bảo mật / Security page]
     |
     v
[List existing passkeys (table)]
     |
     v
[Click "Thêm Passkey"]
     |
     v
[JS: POST /ctv/passkey-api.php?action=register_begin]
     |
     v
[Server returns PublicKeyCredentialCreationOptions]
     |
     v
[JS: navigator.credentials.create(options)]
     |
     v
[Face ID / Touch ID / Windows Hello prompt]
     |
     v
[JS: POST /ctv/passkey-api.php?action=register_finish with attestation]
     |
     v
[Server validates and stores → success message]
     |
     v
[Optional: name the passkey ("iPhone của tôi")]
```

## UI/UX Flow — CTV Passkey Login

```
[Login page: email/password form]
     |
     v
[Below form: "Đăng nhập bằng Passkey" button]
     |
     v
[Click button → JS: POST /ctv/passkey-api.php?action=authenticate_begin]
     |
     v
[Server returns PublicKeyCredentialRequestOptions (no allowCredentials = discoverable)]
     |
     v
[JS: navigator.credentials.get(options)]
     |
     v
[OS shows passkey picker / iCloud Keychain / Face ID]
     |
     v
[JS: POST /ctv/passkey-api.php?action=authenticate_finish with assertion]
     |
     v
[Server validates → creates ctv_sessions row → sets cookie → redirect dashboard]
```

## Test Plan

### Registration Tests
1. CTV logs in with password → goes to /ctv/security.php → clicks "Thêm Passkey"
2. Face ID/Touch ID/Windows Hello prompt appears → approve
3. Passkey appears in list with device name and date
4. Try registering a second passkey → works
5. Try registering with max passkeys (5) → error message

### Login Tests
1. On login page, click "Đăng nhập bằng Passkey"
2. iCloud Keychain / platform authenticator shows jp-esim.vip credentials
3. Select credential → Face ID → logged in, redirected to dashboard
4. Verify session cookie is set correctly
5. Verify last_used_at is updated on the passkey

### Password Login Unchanged
1. Login with email + password → works as before
2. Register passkey, then login with password → still works
3. Revoke all passkeys, login with password → still works

### Revocation Tests
1. Go to security page → click revoke on a passkey → confirm
2. Passkey removed from list
3. Try logging in with revoked passkey → fails, fall back to password

### Lost Device Recovery
1. Register passkey on device A
2. From device B, login with email + password → works
3. Register new passkey on device B
4. Revoke device A passkey from security page

### Admin Tests
1. Admin logs in with Basic Auth → goes to passkey-setup.php
2. Registers passkey → appears in list
3. `ADMIN_REQUIRE_PASSKEY` not set → admin can use site normally
4. Future: when set, admin must verify passkey after Basic Auth

### Browser Compatibility
1. Safari on iOS 16+ → full passkey support
2. Chrome on macOS/Windows → full passkey support
3. Firefox → passkey support
4. Old browser / WebView → passkey button hidden, password-only

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| WebAuthn library bug | Auth bypass | Use well-maintained library, validate all fields |
| Challenge replay | Auth bypass | One-time challenges, 5-min expiry, DB cleanup |
| Cloned credential | Unauthorized access | Sign count validation |
| DB corruption | Lost passkeys | Password login always works as fallback |
| Admin lockout | Site inaccessible | Remove env var to disable passkey requirement |
| Browser incompatibility | Can't login | Feature detect, hide button, password fallback |
| iCloud Keychain desync | Passkey not found | Password fallback, re-register |
