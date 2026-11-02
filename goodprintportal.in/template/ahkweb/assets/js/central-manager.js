/* =========================================================
   Central API Domain Manager — Client Side Helper Library
   EXTERNAL FILE to avoid:
   - PHP complex curly syntax parsing JS ${...} as PHP vars
   - PHP short_open_tag misdetect of <? sequences in JS/comments
   - HTML parser seeing literal </script> and closing block early
   Loaded only from settings page.
   ========================================================= */
(function() {
    'use strict';

    // 🔗 API URL link decode (base64 encoded obfuscated link)
    try {
        var encoded = "aHR0cHM6Ly9hcGlhZGRhLmluLw==";
        var a = document.getElementById("authUrlLink");
        if (a) {
            try { a.href = (typeof atob === 'function') ? atob(encoded) : "https://apiadda.in/"; }
            catch(e) { a.href = "https://apiadda.in/"; }
        }
    } catch(e) {}

    // ---------- GLOBAL ERROR BANNER (visible to users, not just console) ----------
    // User requested: background me jo error aa raha hai usko hide mat karo, page par dikhao
    function _cdmShowTopErrorBanner(title, msg, rawInfo) {
        try {
            var bannerId = 'cdm_js_error_banner';
            var wrap = document.getElementById(bannerId);
            if (!wrap) {
                wrap = document.createElement('div');
                wrap.id = bannerId;
                wrap.style.cssText = 'position:fixed;top:8px;left:8px;right:8px;z-index:99999;max-height:42vh;overflow:auto;';
                document.body.appendChild(wrap);
            }
            var entry = document.createElement('div');
            entry.style.cssText = 'background:#fff1f2;border:1px solid #fecdd3;border-left:4px solid #dc2626;border-radius:8px;padding:12px 14px;margin-bottom:8px;font-size:13px;color:#7f1d1d;box-shadow:0 8px 20px rgba(0,0,0,.08);';
            var info = (rawInfo ? '\n' + String(rawInfo).substring(0, 1200) : '');
            entry.innerHTML =
                '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;">' +
                '<div>' +
                  '<div style="font-weight:700;margin-bottom:4px;">&#9888;&#65039; JS Error: ' + _escapeHtml(title) + '</div>' +
                  '<div style="white-space:pre-wrap;word-break:break-word;">' + _escapeHtml(String(msg)) + _escapeHtml(info) + '</div>' +
                '</div>' +
                '<button type="button" aria-label="Close" style="background:#fee2e2;border:1px solid #fecaca;border-radius:6px;padding:2px 8px;color:#991b1b;font-weight:700;cursor:pointer;">&#10005;</button>' +
                '</div>';
            entry.querySelector('button').addEventListener('click', function(){ entry.remove(); });
            wrap.appendChild(entry);
        } catch(e) { /* swallow — can't show anything more */ }
    }
    function _escapeHtml(x) {
        // XSS-safe escape for innerHTML
        if (x === null || x === undefined) return '';
        return String(x)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
    if (typeof window !== 'undefined') {
        var _prevOnerror = window.onerror;
        window.onerror = function(msg, src, lineno, colno, err) {
            try {
                var title = String(src ? (src.split('/').pop() || 'page') : 'page') + ':' + (lineno||'?') + ':' + (colno||'?');
                var info = '';
                if (err && err.stack) info = '\nStack:\n' + String(err.stack).substring(0, 900);
                _cdmShowTopErrorBanner(title, String(msg), info);
            } catch(x){}
            if (typeof _prevOnerror === 'function') {
                try { return _prevOnerror.apply(this, arguments); } catch(y){}
            }
            return false;
        };
        var _prevOnrej = window.onunhandledrejection;
        window.addEventListener('unhandledrejection', function(ev) {
            try {
                var title = 'Unhandled Promise';
                var msg = (ev && ev.reason) ? (String((ev.reason && ev.reason.message) ? ev.reason.message : ev.reason)) : '(no reason)';
                var info = '';
                if (ev && ev.reason && ev.reason.stack) info = '\nStack:\n' + String(ev.reason.stack).substring(0, 900);
                _cdmShowTopErrorBanner(title, msg, info);
            } catch(x){}
        });
    }

    // ---------- UI HELPERS ----------
    function cdmShowResult(html, kind) {
        var box = document.getElementById('central_result');
        if (!box) return;
        var body = document.getElementById('central_result_body');
        if (!body) return;
        box.classList.remove('d-none');
        if (box.firstElementChild) {
            box.firstElementChild.classList.remove('border-primary','border-success','border-danger','border-warning');
            box.firstElementChild.classList.add('border-' + (kind || 'primary'));
        }
        body.innerHTML = html;
        try { box.scrollIntoView({behavior:'smooth', block:'center'}); } catch(e){}
    }
    function cdmSpinner(text) {
        return '<div class="d-flex align-items-center gap-3"><div class="spinner-border text-primary" role="status"></div><div class="fw-semibold">' + (text || 'Working...') + '</div></div>';
    }
    function cdmFormData(action) {
        var body = new FormData();
        body.append(action, '1');
        var cmUrl = document.getElementById('central_manager_url');
        var cAk  = document.getElementById('central_access_key');
        body.append('central_manager_url', cmUrl ? String(cmUrl.value || '').trim() : '');
        body.append('central_access_key',  cAk  ? String(cAk.value  || '').trim() : '');
        var csrf = document.querySelector('input[name="_ahkweb_csrf"]');
        if (csrf) body.append('_ahkweb_csrf', csrf.value);
        var pk = document.querySelector('input[name="pariprint_api_key"]');
        if (pk) body.append('pariprint_api_key', pk.value);
        return body;
    }
    function cdmPrettyDebug(v) {
        if (v === null || v === undefined) return '';
        if (typeof v === 'string') return v;
        try { return JSON.stringify(v, null, 2); } catch(e) { return String(v); }
    }
    function _H(s) { return _escapeHtml(s == null ? '' : s); } // alias used by action functions

    // ---------- LEADING HTML / TAG STRIPPER from JSON responses ----------
    // (handles sweetalert2 script prepends by bad auto_prepend_file)
    function cdmStripLeadingHtmlToFindJSON(text) {
        var t = String(text == null ? '' : text);
        var stripCount = 0;
        var strippedBytes = 0;
        var loopMax = 200;
        var loop = 0;
        // Build dangerous open/close strings via concat so this .js file itself is also safe to grep
        var piOpen  = '<' + '?';
        var piClose = '?' + '>';
        var sOpen   = '<' + 'script';
        var sClose  = '<' + '/script>';
        function _reEsc(s) { return s.replace(/[.?*+^$[\]\\(){}|-]/g,'\\$&'); }
        var commentRe    = /^<!--[\s\S]*?-->/;
        var doctypeRe    = /^<!doctype[^>]*>/i;
        var piRe         = new RegExp('^' + _reEsc(piOpen)  + '[\\s\\S]*?' + _reEsc(piClose),  '');
        var openScriptRe = new RegExp('^' + _reEsc(sOpen)   + '\\b[^>]*>[\\s\\S]*?' + _reEsc(sClose), 'i');
        var closeOnlyRe  = new RegExp('^' + _reEsc(sClose), 'i');
        var leadingTagRe = /^<\/?[a-zA-Z!][^>]*>/;
        var jsonStartRe  = /^\s*[\{\[]/;

        while (loop++ < loopMax) {
            var before = t.length;
            t = t.replace(/^\s+/, '');
            t = t.replace(commentRe, '');
            t = t.replace(piRe, '');
            t = t.replace(doctypeRe, '');
            if (openScriptRe.test(t)) {
                var m1 = t.match(openScriptRe);
                if (m1) { t = t.substring(m1[0].length); stripCount++; }
            }
            t = t.replace(closeOnlyRe, '');
            if (leadingTagRe.test(t) && !jsonStartRe.test(t)) {
                t = t.replace(leadingTagRe, '');
                stripCount++;
            }
            var after = t.length;
            if (after === before) break;
            strippedBytes += (before - after);
        }
        return { text: t, strip_count: stripCount, stripped_bytes: strippedBytes };
    }

    // ---------- SAFE FETCH -> TEXT -> STRIP -> JSON PARSE ----------
    function cdmFetchJSON(url, opts) {
        return fetch(url, opts)
            .then(async function(r) {
                var text = await r.text();
                var cleaned = cdmStripLeadingHtmlToFindJSON(text);
                var parsed = null;
                var parseErr = null;
                try { parsed = JSON.parse(cleaned.text); } catch(e) { parseErr = e && e.message ? e.message : String(e); }
                var hadInjection = (parseErr === null && cleaned.strip_count > 0);
                if (parseErr !== null) {
                    var idxCur = cleaned.text.indexOf('{');
                    var idxBrk = cleaned.text.indexOf('[');
                    var cands = [idxCur, idxBrk].filter(function(i){ return i >= 0; });
                    cands.sort(function(a,b){ return a-b; });
                    for (var i = 0; i < cands.length; i++) {
                        var sub = cleaned.text.substring(cands[i]);
                        try { parsed = JSON.parse(sub); parseErr = null; break; } catch(_x) { /* try next */ }
                    }
                }
                return {
                    _http_code: r.status,
                    _http_ok:  r.ok,
                    _text: text,
                    _cleaned_text: cleaned.text,
                    _stripped_count: cleaned.strip_count,
                    _stripped_bytes: cleaned.stripped_bytes,
                    _parse_error: parseErr,
                    _had_injection: hadInjection,
                    data: parseErr ? null : parsed,
                    json: parsed
                };
            })
            .then(function(bag) {
                if (bag._parse_error || bag.json === null) {
                    var first1500 = bag._text ? bag._text.substring(0, 1500) : '';
                    var d = {
                        status: false,
                        message: 'Invalid response: ' + (bag._parse_error || 'empty'),
                        http_code: bag._http_code,
                        debug: {
                            http_code: bag._http_code,
                            parse_error: bag._parse_error,
                            stripped_tags: bag._stripped_count,
                            stripped_bytes: bag._stripped_bytes,
                            had_script_injection: bag._had_injection,
                            raw_response_start: first1500,
                            raw_response_len: bag._text ? bag._text.length : 0,
                            cleaned_text_start: (bag._cleaned_text && bag._cleaned_text !== bag._text)
                                ? bag._cleaned_text.substring(0, 500)
                                : null
                        }
                    };
                    if (bag.json) { for (var k in bag.json) if (bag.json.hasOwnProperty(k)) d[k] = bag.json[k]; }
                    return d;
                }
                var out = { http_code: bag._http_code, _stripped_tags: bag._stripped_count||0, _had_script_injection: !!bag._had_injection };
                if (bag.json && typeof bag.json === 'object') { for (var k2 in bag.json) if (bag.json.hasOwnProperty(k2)) out[k2] = bag.json[k2]; }
                else out = bag.json;
                return out;
            })
            .catch(function(e) {
                return {
                    status: false,
                    message: 'Network/Server error: ' + (e && e.message ? e.message : String(e)),
                    debug: { error_type: 'fetch_exception', name: (e && e.name) ? e.name : '', stack: (e && e.stack) ? String(e.stack).substring(0,400) : '' }
                };
            });
    }

    // ---------- ACTIONS wired to buttons ----------
    function registerSite() {
        cdmShowResult(cdmSpinner('Contacting Central Manager → registering this site...'), 'primary');
        var fd = cdmFormData('central_ajax_register');
        cdmFetchJSON('', {method:'POST', body: fd, credentials:'same-origin'})
            .then(function(d) {
                if (d && d.status === true && d.access_key) {
                    var k = document.getElementById('central_access_key');
                    if (k) {
                        k.value = String(d.access_key);
                        try { k.classList.add('is-valid'); setTimeout(function(){ try { k.classList.remove('is-valid'); } catch(_){} }, 1500); } catch(_x) {}
                    }
                    var rows = '<div class="fw-bold text-success mb-2"><i class="bi bi-check-circle-fill me-1"></i> Registration SUCCESS — Access Key auto-filled above.</div>';
                    rows += '<div class="table-responsive"><table class="table table-sm mb-0 align-middle"><tbody>';
                    rows += '<tr><th>Manager URL</th><td class="font-monospace small">' + _H(d.manager_url || '-') + '</td></tr>';
                    var accessKeyEscaped = String(d.access_key || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
                    rows += '<tr><th>Access Key</th><td class="font-monospace small"><code>' + _H(d.access_key) + '</code> <button type="button" class="btn btn-sm btn-outline-info ms-2" onclick="navigator.clipboard.writeText(\'' + accessKeyEscaped + '\')">Copy</button></td></tr>';
                    rows += '<tr><th>Site label</th><td>' + _H(d.site_label || '-') + '</td></tr>';
                    if (d.manager_instructions) rows += '<tr><th>Next step</th><td><span class="text-success fw-semibold">Click Save Changes below to persist, then visit <b>manager dashboard</b> to approve this site.</span><br><div class="small text-muted mt-1">' + _H(d.manager_instructions) + '</div></td></tr>';
                    rows += '</tbody></table></div>';
                    cdmShowResult(rows, 'success');
                } else {
                    var extra = '';
                    if (d && d.debug && d.debug.raw_response_start) {
                        extra += '<div class="mt-2 mb-1 fw-semibold small text-secondary">Actual raw response returned by server (causes Unexpected token &lt;)</div><pre class="small bg-white border border-danger rounded p-3 overflow-auto" style="max-height:320px">' + _H(d.debug.raw_response_start) + '</pre>';
                    }
                    cdmShowResult(
                        '<div class="text-danger fw-bold"><i class="bi bi-x-circle-fill me-1"></i> Registration Failed</div>'
                        + '<div class="small mt-1">' + ((d && d.message) || 'Unknown error') + '</div>'
                        + ((d && d.debug) ? '<pre class="small mt-2 bg-light p-2 border rounded overflow-auto">' + _H(cdmPrettyDebug(d.debug)) + '</pre>' : '')
                        + extra,
                        'danger'
                    );
                }
            })
            .catch(function(e) {
                cdmShowResult('<div class="text-danger fw-bold">Network/Server error</div><div class="small">' + (e.message || String(e)) + '</div>', 'danger');
            });
    }
    function testConnection() {
        cdmShowResult(cdmSpinner('Testing Central connection + showing live domains...'), 'primary');
        var fd = cdmFormData('central_ajax_test');
        cdmFetchJSON('', {method:'POST', body: fd, credentials:'same-origin'})
            .then(function(d) {
                if (!d || d.status !== true) {
                    var extra = '';
                    if (d && d.debug && d.debug.raw_response_start) {
                        extra += '<div class="mt-2 mb-1 fw-semibold small text-secondary">Actual raw response returned by server</div><pre class="small bg-white border border-danger rounded p-3 overflow-auto" style="max-height:320px">' + _H(d.debug.raw_response_start) + '</pre>';
                    }
                    cdmShowResult(
                        '<div class="text-danger fw-bold"><i class="bi bi-x-circle-fill me-1"></i> Test FAILED</div>'
                        + '<div class="small mt-1">' + ((d && d.message) || 'Unknown') + '</div>'
                        + ((d && d.debug) ? '<pre class="small mt-2 bg-light p-2 border rounded overflow-auto">' + _H(cdmPrettyDebug(d.debug)) + '</pre>' : '')
                        + extra,
                        'danger'
                    );
                    return;
                }
                var r = d.resolved || {};
                var env = d.env || {};
                var html = '<div class="fw-bold text-success mb-2"><i class="bi bi-check-circle-fill me-1"></i> Connection SUCCESS</div>';
                html += '<div class="table-responsive"><table class="table table-sm mb-0 align-middle"><tbody>';
                html += '<tr><th>Manager URL</th><td class="font-monospace small">' + _H(d.manager_url || '-') + '</td></tr>';
                html += '<tr><th>Access key present</th><td>' + (d.has_key ? '<span class="badge bg-success">Yes &#9989;</span>' : '<span class="badge bg-warning text-dark">No (public/access-key disabled)</span>') + '</td></tr>';
                html += '<tr><th>Source</th><td><span class="badge bg-secondary text-uppercase">' + _H(r.source || '-') + '</span> ' + (r.transport ? '<small class="text-muted">(via ' + _H(r.transport) + ')</small>' : '') + '</td></tr>';
                var apiDom = r.api_domain || '#', gwayDom = r.gateway_domain || '#';
                html += '<tr><th>Live API Domain</th><td class="font-monospace small"><a target="_blank" rel="noopener noreferrer" href="' + _H(apiDom) + '">' + _H(r.api_domain || '-') + '</a></td></tr>';
                html += '<tr><th>Live Gateway Domain</th><td class="font-monospace small"><a target="_blank" rel="noopener noreferrer" href="' + _H(gwayDom) + '">' + _H(r.gateway_domain || '-') + '</a></td></tr>';
                html += '<tr><th>Version</th><td>' + (r.version ? 'v'+String(r.version) : '-') + '</td></tr>';
                if (r.age_sec !== undefined && r.age_sec !== null) html += '<tr><th>Freshness</th><td>' + (r.age_sec<10 ? '<10' : Math.round(r.age_sec)) + ' sec ago</td></tr>';
                if (d.cache_path) html += '<tr><th>Cache file</th><td class="font-monospace small text-muted">' + _H(d.cache_path) + '</td></tr>';
                if (env.curl !== undefined) {
                    html += '<tr><th>Server Env</th><td>'
                        + (env.curl ? '<span class="badge bg-success me-1">curl &#9989;</span>' : '<span class="badge bg-danger me-1">curl &#10060;</span>')
                        + (env.allow_url_fopen ? '<span class="badge bg-success me-1">fopen &#9989;</span>' : '<span class="badge bg-danger me-1">fopen &#10060;</span>')
                        + (env.php_version ? '<small class="text-muted ms-1">PHP ' + _H(env.php_version) + '</small>' : '')
                        + '</td></tr>';
                }
                if (d.last_fetch_error) html += '<tr class="table-warning"><th>Last Fetch Warning</th><td class="text-warning-emphasis">' + _H(d.last_fetch_error) + '</td></tr>';
                if (d.last_fetch_debug) html += '<tr><th>Transport Debug</th><td><details open><summary class="cursor-pointer small text-muted">Click to view</summary><pre class="small bg-light p-2 mt-1 border rounded overflow-auto">' + _H(cdmPrettyDebug(d.last_fetch_debug)) + '</pre></details></td></tr>';
                html += '</tbody></table></div>';
                cdmShowResult(html, 'success');
            })
            .catch(function(e) {
                cdmShowResult('<div class="text-danger fw-bold">Network/Server error</div><div class="small">' + (e.message || String(e)) + '</div>', 'danger');
            });
    }
    function forceRefreshCache() {
        cdmShowResult(cdmSpinner('Bypassing cache → fetching fresh domains...'), 'primary');
        var fd = cdmFormData('central_ajax_refresh');
        cdmFetchJSON('', {method:'POST', body: fd, credentials:'same-origin'})
            .then(function(d) {
                if (!d || d.status !== true) {
                    var extra = '';
                    if (d && d.debug && d.debug.raw_response_start) {
                        extra += '<div class="mt-2 mb-1 fw-semibold small text-secondary">Actual raw response</div><pre class="small bg-white border border-danger rounded p-3 overflow-auto" style="max-height:320px">' + _H(d.debug.raw_response_start) + '</pre>';
                    }
                    cdmShowResult(
                        '<div class="text-danger fw-bold"><i class="bi bi-x-circle-fill me-1"></i> Force Refresh FAILED</div>'
                        + '<div class="small mt-1">' + ((d && d.message) || 'Unknown') + '</div>'
                        + ((d && d.debug) ? '<pre class="small mt-2 bg-light p-2 border rounded overflow-auto">' + _H(cdmPrettyDebug(d.debug)) + '</pre>' : '')
                        + extra,
                        'danger'
                    );
                    return;
                }
                var r = d.resolved || {};
                var html = '<div class="fw-bold text-success mb-2"><i class="bi bi-arrow-repeat me-1"></i> Cache REFRESHED — reloading in 3s to update badges...</div>';
                html += '<div class="table-responsive"><table class="table table-sm mb-0 align-middle"><tbody>';
                html += '<tr><th>Source</th><td><span class="badge bg-success text-uppercase">' + _H(r.source || '-') + '</span></td></tr>';
                var apiDom2 = r.api_domain || '#', gwayDom2 = r.gateway_domain || '#';
                html += '<tr><th>New API Domain</th><td class="font-monospace small"><a target="_blank" rel="noopener noreferrer" href="' + _H(apiDom2) + '">' + _H(r.api_domain || '-') + '</a></td></tr>';
                html += '<tr><th>New Gateway Domain</th><td class="font-monospace small"><a target="_blank" rel="noopener noreferrer" href="' + _H(gwayDom2) + '">' + _H(r.gateway_domain || '-') + '</a></td></tr>';
                if (r.version) html += '<tr><th>Version</th><td>v' + String(r.version) + '</td></tr>';
                html += '</tbody></table></div>';
                cdmShowResult(html, 'success');
                try { setTimeout(function(){ location.reload(); }, 2500); } catch(_x){}
            })
            .catch(function(e) {
                cdmShowResult('<div class="text-danger fw-bold">Network/Server error</div><div class="small">' + (e.message || String(e)) + '</div>', 'danger');
            });
    }

    // ---------- EXPORTS (global) ----------
    window.cdmShowResult = cdmShowResult;
    window.cdmSpinner    = cdmSpinner;
    window.cdmFormData   = cdmFormData;
    window.cdmPrettyDebug = cdmPrettyDebug;
    window.cdmFetchJSON  = cdmFetchJSON;
    window.cdmStripLeadingHtmlToFindJSON = cdmStripLeadingHtmlToFindJSON;
    window.registerSite  = registerSite;
    window.testConnection = testConnection;
    window.forceRefreshCache = forceRefreshCache;
})();
