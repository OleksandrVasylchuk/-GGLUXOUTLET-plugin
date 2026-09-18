/**
 * Promo Engine storefront: countdown timers and the promotion popup.
 */
( () => {
	'use strict';

	const config = window.promoEngine || {};
	const reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	const FOCUSABLE = [
		'a[href]',
		'button:not([disabled])',
		'input:not([disabled]):not([type="hidden"])',
		'select:not([disabled])',
		'textarea:not([disabled])',
		'[tabindex]:not([tabindex="-1"])',
	].join( ',' );

	const session = {
		get( key ) {
			try {
				return window.sessionStorage.getItem( key );
			} catch ( e ) {
				return null;
			}
		},
		set( key, value ) {
			try {
				window.sessionStorage.setItem( key, value );
			} catch ( e ) {}
		},
	};

	const pad = ( n ) => String( n ).padStart( 2, '0' );

	function formatRemaining( ms ) {
		const total = Math.max( 0, Math.floor( ms / 1000 ) );
		const days = Math.floor( total / 86400 );
		const clock = [ Math.floor( ( total % 86400 ) / 3600 ), Math.floor( ( total % 3600 ) / 60 ), total % 60 ]
			.map( pad )
			.join( ':' );

		return days ? `${ days }d ${ clock }` : clock;
	}

	function initCountdowns() {
		const timers = Array.from( document.querySelectorAll( '[data-pe-countdown]' ) );

		if ( ! timers.length ) {
			return;
		}

		const tick = () => {
			const now = Date.now();

			timers.forEach( ( timer ) => {
				const left = Number( timer.dataset.peCountdown ) * 1000 - now;

				timer.textContent = formatRemaining( left );

				if ( left <= 0 && ! timer.dataset.peExpired ) {
					timer.dataset.peExpired = '1';
					timer.dispatchEvent( new CustomEvent( 'pe:expired', { bubbles: true } ) );
				}
			} );
		};

		tick();
		window.setInterval( tick, 1000 );
	}

	/**
	 * Sticky 50/50 assignment. Stored in a cookie rather than storage so the
	 * server can attribute add to cart and orders to the same variant.
	 *
	 * @param {string} promotion Promotion ID.
	 * @return {string} 'a' or 'b'.
	 */
	function abVariant( promotion ) {
		const name = `promo_engine_ab_${ promotion }`;
		const match = document.cookie.match( new RegExp( `(?:^|; )${ name }=([ab])(?:;|$)` ) );

		if ( match ) {
			return match[ 1 ];
		}

		const variant = Math.random() < 0.5 ? 'a' : 'b';
		document.cookie = `${ name }=${ variant }; path=/; max-age=${ 30 * 86400 }; SameSite=Lax`;

		return variant;
	}

	function track( event, promotion ) {
		if ( ! config.ajaxUrl ) {
			return;
		}

		const body = new FormData();
		body.append( 'action', config.action );
		body.append( 'nonce', config.nonce );
		body.append( 'event', event );
		body.append( 'promotion', promotion );

		if ( navigator.sendBeacon && navigator.sendBeacon( config.ajaxUrl, body ) ) {
			return;
		}

		window.fetch( config.ajaxUrl, { method: 'POST', body, credentials: 'same-origin', keepalive: true } ).catch( () => {} );
	}

	function initPopup() {
		const popup = document.querySelector( '[data-pe-popup]' );

		if ( ! popup ) {
			return;
		}

		const id = popup.dataset.pePopup;
		const seenKey = `promoEngine:popup:${ id }`;
		const dialog = popup.querySelector( '[role="dialog"]' );
		let returnFocus = null;

		if ( session.get( seenKey ) ) {
			return;
		}

		if ( popup.hasAttribute( 'data-pe-ab' ) && 'b' === abVariant( id ) ) {
			const template = popup.querySelector( '[data-pe-variant-b]' );
			const cta = popup.querySelector( '[data-pe-cta]' );

			popup.querySelector( '[data-pe-copy]' ).replaceChildren( template.content.cloneNode( true ) );
			cta.textContent = cta.dataset.peCtaB;
		}

		if ( ! popup.querySelector( '#pe-popup-text' ) ) {
			dialog.removeAttribute( 'aria-describedby' );
		}

		const focusables = () =>
			Array.from( dialog.querySelectorAll( FOCUSABLE ) ).filter( ( el ) => el.getClientRects().length > 0 );

		const onKeydown = ( event ) => {
			if ( 'Escape' === event.key ) {
				event.preventDefault();
				close();
				return;
			}

			if ( 'Tab' !== event.key ) {
				return;
			}

			const items = focusables();

			if ( ! items.length ) {
				event.preventDefault();
				dialog.focus();
				return;
			}

			const first = items[ 0 ];
			const last = items[ items.length - 1 ];
			const active = document.activeElement;

			if ( event.shiftKey && ( active === first || active === dialog ) ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && ( active === last || ! dialog.contains( active ) ) ) {
				event.preventDefault();
				first.focus();
			}
		};

		const expired = () => {
			const timer = popup.querySelector( '[data-pe-countdown]' );

			return timer && Number( timer.dataset.peCountdown ) * 1000 <= Date.now();
		};

		function open() {
			if ( expired() ) {
				return;
			}

			// Wait until the visitor is actually looking at the page, otherwise
			// the impression is counted for a popup nobody saw.
			if ( 'visible' !== document.visibilityState ) {
				document.addEventListener( 'visibilitychange', open, { once: true } );
				return;
			}

			returnFocus = document.activeElement;
			popup.hidden = false;
			document.documentElement.classList.add( 'pe-popup-open' );
			popup.getBoundingClientRect(); // Flush styles so the opacity transition runs.
			popup.classList.add( 'is-open' );
			dialog.focus();
			document.addEventListener( 'keydown', onKeydown );

			session.set( seenKey, '1' );
			track( 'impression', id );
		}

		function close() {
			if ( popup.hidden ) {
				return;
			}

			document.removeEventListener( 'keydown', onKeydown );
			popup.classList.remove( 'is-open' );
			document.documentElement.classList.remove( 'pe-popup-open' );
			window.setTimeout( () => {
				popup.hidden = true;
			}, reducedMotion.matches ? 0 : 250 );

			if ( returnFocus && typeof returnFocus.focus === 'function' ) {
				returnFocus.focus();
			}
		}

		popup.addEventListener( 'click', ( event ) => {
			if ( event.target.closest( '[data-pe-close]' ) ) {
				close();
			} else if ( event.target.closest( '[data-pe-cta]' ) ) {
				track( 'click', id );
			}
		} );

		popup.addEventListener( 'pe:expired', close );

		window.setTimeout( open, Math.max( 0, Number( config.delay ) || 0 ) * 1000 );
	}

	const init = () => {
		initCountdowns();
		initPopup();
	};

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
