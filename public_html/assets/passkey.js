/**
 * Passkey (WebAuthn) client helpers for jp-esim.vip
 */
(function(window) {
  'use strict';

  function b64url_encode(buf) {
    var bytes = new Uint8Array(buf);
    var str = '';
    for (var i = 0; i < bytes.length; i++) str += String.fromCharCode(bytes[i]);
    return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }

  function b64url_decode(str) {
    str = str.replace(/-/g, '+').replace(/_/g, '/');
    while (str.length % 4) str += '=';
    var bin = atob(str);
    var buf = new Uint8Array(bin.length);
    for (var i = 0; i < bin.length; i++) buf[i] = bin.charCodeAt(i);
    return buf.buffer;
  }

  function b64_to_buf(str) {
    var bin = atob(str);
    var buf = new Uint8Array(bin.length);
    for (var i = 0; i < bin.length; i++) buf[i] = bin.charCodeAt(i);
    return buf.buffer;
  }

  function buf_to_b64(buf) {
    var bytes = new Uint8Array(buf);
    var str = '';
    for (var i = 0; i < bytes.length; i++) str += String.fromCharCode(bytes[i]);
    return btoa(str);
  }

  function isSupported() {
    return !!(window.PublicKeyCredential);
  }

  async function isPlatformAvailable() {
    if (!isSupported()) return false;
    try {
      return await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
    } catch(e) {
      return false;
    }
  }

  function prepareCreateOptions(opts) {
    var pk = opts.publicKey;
    if (pk.challenge) pk.challenge = b64url_decode(pk.challenge);
    if (pk.user && pk.user.id) pk.user.id = b64url_decode(pk.user.id);
    if (pk.excludeCredentials) {
      pk.excludeCredentials = pk.excludeCredentials.map(function(c) {
        c.id = b64url_decode(c.id);
        return c;
      });
    }
    return opts;
  }

  function prepareGetOptions(opts) {
    var pk = opts.publicKey;
    if (pk.challenge) pk.challenge = b64url_decode(pk.challenge);
    if (pk.allowCredentials) {
      pk.allowCredentials = pk.allowCredentials.map(function(c) {
        c.id = b64url_decode(c.id);
        return c;
      });
    }
    return opts;
  }

  async function register(apiBase) {
    if (!isSupported()) throw new Error('Trình duyệt không hỗ trợ Passkey');

    var beginResp = await fetch(apiBase + '?action=register_begin', {
      method: 'POST',
      headers: {'Accept': 'application/json'}
    });
    var beginJson = await beginResp.json();
    if (!beginJson.ok) throw new Error(beginJson.error || 'Lỗi khởi tạo đăng ký passkey');

    var createOptions = prepareCreateOptions(beginJson.data);
    var credential = await navigator.credentials.create(createOptions);

    var attestationResponse = credential.response;
    var finishBody = {
      clientDataJSON: buf_to_b64(attestationResponse.clientDataJSON),
      attestationObject: buf_to_b64(attestationResponse.attestationObject),
      deviceName: ''
    };

    var transports = [];
    if (attestationResponse.getTransports) {
      transports = attestationResponse.getTransports();
    }

    var finishResp = await fetch(apiBase + '?action=register_finish', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
      body: JSON.stringify(finishBody)
    });
    var finishJson = await finishResp.json();
    if (!finishJson.ok) throw new Error(finishJson.error || 'Lỗi đăng ký passkey');

    return finishJson.data;
  }

  async function login(apiBase) {
    if (!isSupported()) throw new Error('Trình duyệt không hỗ trợ Passkey');

    var beginResp = await fetch(apiBase + '?action=authenticate_begin', {
      method: 'POST',
      headers: {'Accept': 'application/json'}
    });
    var beginJson = await beginResp.json();
    if (!beginJson.ok) throw new Error(beginJson.error || 'Lỗi khởi tạo đăng nhập passkey');

    var getOptions = prepareGetOptions(beginJson.data);
    var assertion = await navigator.credentials.get(getOptions);

    var assertionResponse = assertion.response;
    var finishBody = {
      credentialId: b64url_encode(assertion.rawId),
      clientDataJSON: buf_to_b64(assertionResponse.clientDataJSON),
      authenticatorData: buf_to_b64(assertionResponse.authenticatorData),
      signature: buf_to_b64(assertionResponse.signature),
      userHandle: assertionResponse.userHandle ? b64url_encode(assertionResponse.userHandle) : null
    };

    var finishResp = await fetch(apiBase + '?action=authenticate_finish', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
      body: JSON.stringify(finishBody)
    });
    var finishJson = await finishResp.json();
    if (!finishJson.ok) throw new Error(finishJson.error || 'Đăng nhập passkey thất bại');

    return finishJson.data;
  }

  window.Passkey = {
    isSupported: isSupported,
    isPlatformAvailable: isPlatformAvailable,
    register: register,
    login: login
  };

})(window);
