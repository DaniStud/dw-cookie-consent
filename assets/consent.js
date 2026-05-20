/**
 * DW Cookie Consent — Frontend consent manager.
 *
 * Handles banner display, cookie persistence, Google Consent Mode v2,
 * Matomo consent API, and dynamic script injection.
 *
 * @version 2.1.0
 */
(function () {
    'use strict';

    /** @type {Object} Server-provided config via wp_localize_script */
    const config = window.dwConsent || {};

    /**
     * Set of already-injected script IDs for deduplication.
     * More reliable than DOM queries alone (elements can be removed/moved).
     * @type {Set<string>}
     */
    const injectedScripts = new Set();

    /**
     * Allowed tracking-ID patterns for built-in scripts.
     * Prevents malformed IDs from being interpolated into inline scripts.
     */
    const TRACKING_ID_PATTERNS = {
        ga4:      /^G-[A-Z0-9]+$/i,
        gads:     /^AW-[A-Z0-9]+$/i,
        meta:     /^\d{10,20}$/,
        linkedin: /^\d{4,15}$/,
        clarity:  /^[a-z0-9]{6,20}$/i,
    };

    // ──────────────────────────────────────────────
    // Bootstrap
    // ──────────────────────────────────────────────

    function init() {
        const banner     = document.getElementById('consent-banner');
        const overlay    = document.getElementById('consent-overlay');
        const cookieIcon = document.getElementById('consent-cookie-icon');

        if (!banner || !overlay || !cookieIcon) {
            return;
        }

        const saved = getConsent();

        // Event delegation on the banner — single listener instead of N
        banner.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-consent]');
            if (!btn) return;

            const consent = mapChoice(btn.dataset.consent);
            saveConsent(consent);
            applyConsent(consent);
            logConsent(consent);
            hideBanner();
        });

        cookieIcon.addEventListener('click', () => showBanner());

        if (!saved) {
            showBanner();
        } else {
            applyConsent(saved);
            cookieIcon.hidden = false;
        }

        // ── Visibility helpers ──

        function showBanner() {
            banner.hidden  = false;
            overlay.hidden = false;
            cookieIcon.hidden = true;
            document.body.style.overflow = 'hidden';
        }

        function hideBanner() {
            banner.hidden  = true;
            overlay.hidden = true;
            cookieIcon.hidden = false;
            document.body.style.overflow = '';
        }
    }

    // ──────────────────────────────────────────────
    // Consent mapping
    // ──────────────────────────────────────────────

    /**
     * @param {string} choice - 'all' | 'statistics' | 'none'
     * @returns {{ analytics: boolean, marketing: boolean }}
     */
    function mapChoice(choice) {
        switch (choice) {
            case 'all':        return { analytics: true,  marketing: true  };
            case 'statistics': return { analytics: true,  marketing: false };
            default:           return { analytics: false, marketing: false };
        }
    }

    // ──────────────────────────────────────────────
    // Apply consent
    // ──────────────────────────────────────────────

    function applyConsent(consent) {
        // 1. Google Consent Mode v2
        if (typeof gtag === 'function') {
            gtag('consent', 'update', {
                analytics_storage:  consent.analytics ? 'granted' : 'denied',
                ad_storage:         consent.marketing ? 'granted' : 'denied',
                ad_user_data:       consent.marketing ? 'granted' : 'denied',
                ad_personalization: consent.marketing ? 'granted' : 'denied',
            });
        }

        // 2. Matomo consent API
        updateMatomoConsent(consent);

        // 3. Push dataLayer event (GTM interop)
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: 'consent_updated',
            consent_analytics: consent.analytics,
            consent_marketing: consent.marketing,
        });

        // 4. Dispatch DOM event for third-party listeners
        document.dispatchEvent(
            new CustomEvent('consent_updated', { detail: consent })
        );

        // 5. Inject scripts for granted tiers
        injectScripts(consent);
    }

    // ──────────────────────────────────────────────
    // Matomo integration
    // ──────────────────────────────────────────────

    /** @type {boolean|null} */
    let _matomoCached = null;

    function hasMatomoInConfig() {
        if (_matomoCached !== null) return _matomoCached;
        if (!config.scripts) return (_matomoCached = false);

        for (const tier of ['statistics', 'marketing']) {
            if ((config.scripts[tier] || []).some(s => s.id === 'matomo')) {
                return (_matomoCached = true);
            }
        }
        return (_matomoCached = false);
    }

    function updateMatomoConsent(consent) {
        if (!hasMatomoInConfig()) return;

        const _paq = window._paq = window._paq || [];
        if (consent.analytics) {
            _paq.push(['setConsentGiven']);
            _paq.push(['setCookieConsentGiven']);
        } else {
            _paq.push(['forgetConsentGiven']);
            _paq.push(['forgetCookieConsentGiven']);
        }
    }

    // ──────────────────────────────────────────────
    // Script injection
    // ──────────────────────────────────────────────

    function injectScripts(consent) {
        if (!config.scripts) return;

        const tiers = [];
        if (consent.analytics) tiers.push('statistics');
        if (consent.marketing) tiers.push('marketing');

        for (const tier of tiers) {
            for (const s of (config.scripts[tier] || [])) {
                const scriptId = `dw-script-${s.id}`;

                // Dedup via Set (primary) + DOM (secondary safety net)
                if (injectedScripts.has(scriptId) || document.getElementById(scriptId)) continue;

                try {
                    if (s.type === 'builtin') {
                        injectBuiltin(s, scriptId);
                    } else if (s.type === 'custom') {
                        injectCustom(s, scriptId);
                    }
                    injectedScripts.add(scriptId);
                } catch (err) {
                    // Isolate failures — one broken script must not kill the consent manager
                    console.error(`DW Consent: Failed to inject "${s.id}"`, err);
                }
            }
        }
    }

    // ── Built-in handlers ──

    function injectBuiltin(s, scriptId) {
        switch (s.id) {
            case 'ga4':
            case 'gads':     injectGtag(s.tracking_id, scriptId);      break;
            case 'meta':     injectMetaPixel(s.tracking_id, scriptId);  break;
            case 'linkedin': injectLinkedIn(s.tracking_id, scriptId);   break;
            case 'clarity':  injectClarity(s.tracking_id, scriptId);    break;
            case 'matomo':   /* handled via updateMatomoConsent() */     break;
        }
    }

    /**
     * Validate a tracking ID against known safe patterns.
     * Throws on mismatch to prevent script-injection via malformed IDs.
     */
    function validateTrackingId(type, id) {
        const pattern = TRACKING_ID_PATTERNS[type];
        if (pattern && !pattern.test(id)) {
            throw new Error(`Invalid ${type} tracking ID: "${id}"`);
        }
    }

    function injectGtag(trackingId, scriptId) {
        validateTrackingId(scriptId.includes('gads') ? 'gads' : 'ga4', trackingId);

        if (!document.getElementById('dw-gtag-js')) {
            const loader  = document.createElement('script');
            loader.id     = 'dw-gtag-js';
            loader.async  = true;
            loader.src    = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(trackingId)}`;
            document.head.appendChild(loader);
        }

        const inline   = document.createElement('script');
        inline.id      = scriptId;
        inline.textContent = [
            'window.dataLayer=window.dataLayer||[];',
            'function gtag(){dataLayer.push(arguments);}',
            "gtag('js',new Date());",
            `gtag('config','${trackingId}');`,
        ].join('');
        document.head.appendChild(inline);
    }

    function injectMetaPixel(pixelId, scriptId) {
        validateTrackingId('meta', pixelId);

        const inline   = document.createElement('script');
        inline.id      = scriptId;
        inline.textContent = [
            '!function(f,b,e,v,n,t,s)',
            '{if(f.fbq)return;n=f.fbq=function(){n.callMethod?',
            'n.callMethod.apply(n,arguments):n.queue.push(arguments)};',
            'if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version="2.0";',
            'n.queue=[];t=b.createElement(e);t.async=!0;',
            't.src=v;s=b.getElementsByTagName(e)[0];',
            's.parentNode.insertBefore(t,s)}(window,document,"script",',
            '"https://connect.facebook.net/en_US/fbevents.js");',
            `fbq('init','${pixelId}');`,
            "fbq('track','PageView');",
        ].join('');
        document.head.appendChild(inline);
    }

    function injectLinkedIn(partnerId, scriptId) {
        validateTrackingId('linkedin', partnerId);

        const inline   = document.createElement('script');
        inline.id      = scriptId;
        inline.textContent = [
            `_linkedin_partner_id="${partnerId}";`,
            'window._linkedin_data_partner_ids=window._linkedin_data_partner_ids||[];',
            'window._linkedin_data_partner_ids.push(_linkedin_partner_id);',
            '(function(l){',
            'if(!l){window.lintrk=function(a,b){window.lintrk.q.push([a,b])};',
            'window.lintrk.q=[]}',
            'var s=document.getElementsByTagName("script")[0];',
            'var b=document.createElement("script");',
            'b.type="text/javascript";b.async=true;',
            'b.src="https://snap.licdn.com/li.lms-analytics/insight.min.js";',
            's.parentNode.insertBefore(b,s);})(window.lintrk);',
        ].join('');
        document.head.appendChild(inline);
    }

    function injectClarity(projectId, scriptId) {
        validateTrackingId('clarity', projectId);

        const inline   = document.createElement('script');
        inline.id      = scriptId;
        inline.textContent = [
            '(function(c,l,a,r,i,t,y){',
            'c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};',
            't=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;',
            'y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);',
            `})(window,document,"clarity","script","${projectId}");`,
        ].join('');
        document.head.appendChild(inline);
    }

    /**
     * Inject a custom (admin-authored) script.
     *
     * Security note: custom script code is entered by admins with `manage_options`
     * capability — equivalent to the Theme/Plugin Editor. Code is stored unsanitised
     * (like `unfiltered_html`). We use textContent for inline scripts to prevent
     * double-parse XSS, and createElement for external scripts.
     */
    function injectCustom(s, scriptId) {
        const target = (s.position === 'footer') ? document.body : document.head;

        const temp = document.createElement('div');
        temp.innerHTML = s.code;

        const scriptEls = temp.querySelectorAll('script');

        if (scriptEls.length > 0) {
            scriptEls.forEach((origScript, idx) => {
                const newScript = document.createElement('script');

                for (const attr of origScript.attributes) {
                    newScript.setAttribute(attr.name, attr.value);
                }
                if (origScript.textContent) {
                    newScript.textContent = origScript.textContent;
                }
                if (idx === 0) newScript.id = scriptId;
                target.appendChild(newScript);
            });
        } else {
            // Non-script markup (noscript, img pixel, etc.)
            const wrapper   = document.createElement('div');
            wrapper.id      = scriptId;
            wrapper.style.display = 'none';
            wrapper.innerHTML = s.code;
            target.appendChild(wrapper);
        }
    }

    // ──────────────────────────────────────────────
    // Cookie read / write
    // ──────────────────────────────────────────────

    function saveConsent(consent) {
        const data = {
            analytics: consent.analytics,
            marketing: consent.marketing,
            v: Number(config.consentVersion) || 1,
        };

        const parts = [
            `${encodeURIComponent(config.cookieName || 'site_consent')}=${encodeURIComponent(JSON.stringify(data))}`,
            'path=/',
            `max-age=${(Number(config.cookieLifetime) || 365) * 86400}`,
            'SameSite=Lax',
        ];

        if (config.cookieDomain) {
            parts.push(`domain=${config.cookieDomain}`);
        }

        document.cookie = parts.join(';');
    }

    /**
     * @returns {{ analytics: boolean, marketing: boolean } | null}
     */
    function getConsent() {
        const needle  = `${encodeURIComponent(config.cookieName || 'site_consent')}=`;
        const cookies = document.cookie.split(';');

        for (const raw of cookies) {
            const c = raw.trim();
            if (!c.startsWith(needle)) continue;

            try {
                const data = JSON.parse(decodeURIComponent(c.substring(needle.length)));

                // Guard against primitives / null
                if (typeof data !== 'object' || data === null) return null;

                const storedVersion = Number(data.v) || 0;
                if (storedVersion < (Number(config.consentVersion) || 1)) {
                    return null; // outdated — re-prompt
                }

                return {
                    analytics: data.analytics === true,
                    marketing: data.marketing === true,
                };
            } catch {
                return null;
            }
        }
        return null;
    }

    // ──────────────────────────────────────────────
    // AJAX consent logging
    // ──────────────────────────────────────────────

    function logConsent(consent) {
        if (!config.ajaxUrl || !config.nonce) return;

        const body = new FormData();
        body.append('action', 'dw_log_consent');
        body.append('nonce', config.nonce);
        body.append('choices', JSON.stringify(consent));
        body.append('consent_version', Number(config.consentVersion) || 1);

        fetch(config.ajaxUrl, {
            method: 'POST',
            body,
            credentials: 'same-origin',
        }).catch(() => {
            // Non-critical — silently fail
        });
    }

    // ──────────────────────────────────────────────
    // Boot
    // ──────────────────────────────────────────────

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
