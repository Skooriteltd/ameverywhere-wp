import '../css/cookie-banner.css';

/**
 * AmEveryWhere Cookie Consent Banner
 * Zero-dependency vanilla JS. Reads config from amEveryWhereCookieConfig.
 */
(function () {
	'use strict';

	var cfg = window.amEveryWhereCookieConfig || { cookieName: 'ameverywhere_consent', cookieDays: 365 };

	function getCookie(name) {
		var v = document.cookie.match( '(^|;)\\s*' + name + '\\s*=\\s*([^;]+)' );
		return v ? decodeURIComponent( v.pop() ) : null;
	}

	function setCookie(name, value, days) {
		var expires = new Date();
		expires.setTime( expires.getTime() + days * 24 * 60 * 60 * 1000 );
		document.cookie = name + '=' + encodeURIComponent( value ) +
			'; expires=' + expires.toUTCString() +
			'; path=/; SameSite=Lax';
	}

	function getBanner() {
		return document.getElementById( 'ameverywhere-cookie-banner' );
	}

	function hideBanner() {
		var banner = getBanner();
		if (banner) {
			banner.style.display = 'none';
			banner.setAttribute( 'aria-hidden', 'true' );
		}
	}

	function showBanner() {
		var banner = getBanner();
		if (banner) {
			banner.style.display = '';
			banner.removeAttribute( 'aria-hidden' );
		}
	}

	function handleConsent(accepted) {
		setCookie( cfg.cookieName, accepted ? 'accepted' : 'declined', cfg.cookieDays );
		hideBanner();
		if (accepted) {
			document.dispatchEvent( new CustomEvent( 'ameverywhere:consent:accepted' ) );
		} else {
			document.dispatchEvent( new CustomEvent( 'ameverywhere:consent:declined' ) );
		}
	}

	function init() {
		var existing = getCookie( cfg.cookieName );

		if (existing === 'accepted' || existing === 'declined') {
			return;
		}

		showBanner();

		var acceptBtn  = document.getElementById( 'aew-cookie-accept' );
		var declineBtn = document.getElementById( 'aew-cookie-decline' );

		if (acceptBtn) {
			acceptBtn.addEventListener(
				'click',
				function () {
					handleConsent( true ); }
			);
		}
		if (declineBtn) {
			declineBtn.addEventListener(
				'click',
				function () {
					handleConsent( false ); }
			);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	// Expose consent check
	var consentApi = {
		hasConsent: function () {
			var val = getCookie( cfg.cookieName );
			return val === 'accepted';
		},
		getStatus: function () {
			return getCookie( cfg.cookieName ) || 'unknown';
		},
	};

	window.amEveryWhereConsent = consentApi;
})();
