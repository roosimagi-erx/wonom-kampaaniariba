/**
 * Wonom Kampaaniariba — poe esikülje käitumine.
 * Sulgemine, kokkukäinud riba, sooduskoodi kopeerimine, tagasiloendus.
 */
( function () {
	'use strict';

	var CFG = window.WKR || {};
	var TXT = CFG.i18n || {};

	function key( slot ) {
		return 'wkr_d_' + slot.getAttribute( 'data-wkr-id' ) + '_' + slot.getAttribute( 'data-wkr-place' );
	}

	function remember( slot, collapsed ) {
		try {
			if ( collapsed ) {
				localStorage.setItem( key( slot ), '1' );
			} else {
				localStorage.removeItem( key( slot ) );
			}
		} catch ( e ) {
			/* privaatne aken või keelatud küpsised — riba töötab ikka, lihtsalt ei jäta meelde */
		}
	}

	function pad( n ) {
		return n < 10 ? '0' + n : '' + n;
	}

	function copy( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( text ).catch( function () {
				return legacy( text );
			} );
		}
		return new Promise( function ( resolve, reject ) {
			try {
				legacy( text );
				resolve();
			} catch ( e ) {
				reject( e );
			}
		} );
	}

	function legacy( text ) {
		var ta = document.createElement( 'textarea' );
		ta.value = text;
		ta.setAttribute( 'readonly', '' );
		ta.style.cssText = 'position:fixed;top:-1000px;opacity:0';
		document.body.appendChild( ta );
		ta.select();
		var ok = false;
		try {
			ok = document.execCommand( 'copy' );
		} catch ( e ) {
			ok = false;
		}
		ta.parentNode.removeChild( ta );
		if ( ! ok ) {
			throw new Error( 'copy failed' );
		}
		return true;
	}

	function bindCoupon( button ) {
		button.addEventListener( 'click', function ( ev ) {
			ev.preventDefault();
			ev.stopPropagation();

			var code = button.getAttribute( 'data-wkr-code' ) || '';
			var label = button.querySelector( '.wkr-coupon-label' );
			var original = label ? label.textContent : '';

			copy( code ).then( function () {
				button.classList.add( 'is-copied' );
				if ( label ) {
					label.textContent = TXT.copied || 'Kopeeritud!';
				}
				setTimeout( function () {
					button.classList.remove( 'is-copied' );
					if ( label ) {
						label.textContent = original;
					}
				}, 1600 );
			} );
		} );
	}

	function bindSlot( slot ) {
		var close = slot.querySelector( '.wkr-close' );
		var expand = slot.querySelector( '.wkr-expand' );
		var hasMini = !! slot.querySelector( '.wkr-mini' );

		if ( close ) {
			close.addEventListener( 'click', function ( ev ) {
				ev.preventDefault();
				ev.stopPropagation();
				slot.classList.add( 'is-collapsed' );
				remember( slot, true );
				if ( ! hasMini ) {
					slot.style.display = 'none';
				}
				pad_body();
			} );
		}

		if ( expand ) {
			expand.addEventListener( 'click', function ( ev ) {
				ev.preventDefault();
				ev.stopPropagation();
				slot.classList.remove( 'is-collapsed' );
				remember( slot, false );
				pad_body();
			} );
		}

		// Serveripoolne inline-skript pani klassi juba enne joonistamist; kui riba
		// pole kokkukäivat versiooni, peidame ta siin päris ära.
		if ( slot.classList.contains( 'is-collapsed' ) && ! hasMini ) {
			slot.style.display = 'none';
		}

		Array.prototype.forEach.call( slot.querySelectorAll( '.wkr-coupon' ), bindCoupon );
	}

	/**
	 * Mõõdab teema enda kleepuva alumise riba kõrguse.
	 *
	 * Otsime lehe ülaosast elemente, mis on position:fixed, ulatuvad ekraani
	 * alaservani, on lai ja madal. Nii tabame WoodMarti mobiilimenüü ja muud
	 * sarnased tööriistaribad, aga jätame vahele vestlusmullid (liiga kitsad)
	 * ja küpsiseteated või modaalid (liiga kõrged).
	 */
	function themeBottomBar() {
		var vh = window.innerHeight;
		var vw = window.innerWidth;
		var tallest = 0;
		var nodes;

		try {
			nodes = document.querySelectorAll( 'body > *, body > * > *, body > * > * > *' );
		} catch ( e ) {
			return 0;
		}

		for ( var i = 0; i < nodes.length; i++ ) {
			var el = nodes[ i ];

			if ( el.className && String( el.className ).indexOf( 'wkr-' ) === 0 ) {
				continue;
			}
			if ( el.closest && el.closest( '.wkr-slot' ) ) {
				continue;
			}

			var cs = window.getComputedStyle( el );
			if ( cs.position !== 'fixed' || cs.display === 'none' || cs.visibility === 'hidden' ) {
				continue;
			}
			if ( parseFloat( cs.opacity ) < 0.05 ) {
				continue;
			}

			var r = el.getBoundingClientRect();

			// Peab olema ekraani alaservas kinni, lai ja madal.
			if ( Math.abs( r.bottom - vh ) > 2 ) { continue; }
			if ( r.height < 8 || r.height > 120 ) { continue; }
			if ( r.width < vw * 0.6 ) { continue; }

			if ( r.height > tallest ) {
				tallest = r.height;
			}
		}

		return Math.round( tallest );
	}

	/** Seab kleepuvale ribale nihke, et see ei jääks teema menüü taha. */
	function applyBottomInset() {
		var slots = document.querySelectorAll( '.wkr-slot--stuck, .wkr-slot--floating' );
		if ( ! slots.length ) {
			return;
		}

		var auto = 0;
		for ( var i = 0; i < slots.length; i++ ) {
			if ( slots[ i ].getAttribute( 'data-wkr-avoid' ) === '1' ) {
				auto = themeBottomBar();
				break;
			}
		}

		Array.prototype.forEach.call( slots, function ( slot ) {
			var manual = parseInt( slot.getAttribute( 'data-wkr-offset' ), 10 ) || 0;
			var use = slot.getAttribute( 'data-wkr-avoid' ) === '1' ? auto + manual : manual;
			slot.style.setProperty( '--wkr-bottom', use + 'px' );
		} );
	}

	/** Kleepuv riba ei tohi katta jaluse linke. */
	function pad_body() {
		if ( ! CFG.pad ) {
			return;
		}
		var stuck = document.querySelector( '.wkr-slot--stuck, .wkr-slot--floating' );
		var height = 0;
		if ( stuck && stuck.style.display !== 'none' ) {
			height = stuck.getBoundingClientRect().height;
			if ( stuck.classList.contains( 'wkr-slot--floating' ) ) {
				height += 12;
			}
		}
		document.body.style.paddingBottom = height ? Math.ceil( height ) + 'px' : '';
	}

	/** Tagasiloendus ja täpne lõpuhetk. */
	function ticker() {
		var nodes = document.querySelectorAll( '.wkr-countdown' );
		if ( ! nodes.length ) {
			return;
		}

		function paint() {
			var now = Math.floor( Date.now() / 1000 );

			Array.prototype.forEach.call( nodes, function ( node ) {
				var end = parseInt( node.getAttribute( 'data-wkr-end' ), 10 ) || 0;
				var left = end - now;

				if ( left <= 0 ) {
					var slot = node.closest ? node.closest( '.wkr-slot' ) : null;
					if ( slot ) {
						slot.style.display = 'none';
						pad_body();
					}
					node.textContent = '';
					return;
				}

				var d = Math.floor( left / 86400 );
				var h = Math.floor( ( left % 86400 ) / 3600 );
				var m = Math.floor( ( left % 3600 ) / 60 );
				var s = left % 60;
				var lead = TXT.ends || 'lõpeb';

				node.textContent = d > 0
					? lead + ' ' + d + ' ' + ( TXT.days || 'p' ) + ' ' + pad( h ) + ':' + pad( m ) + ':' + pad( s )
					: lead + ' ' + pad( h ) + ':' + pad( m ) + ':' + pad( s );
			} );
		}

		paint();
		setInterval( paint, 1000 );
	}

	function refresh() {
		applyBottomInset();
		pad_body();
	}

	function start() {
		Array.prototype.forEach.call( document.querySelectorAll( '.wkr-slot' ), bindSlot );
		ticker();
		refresh();

		// Teema kleepuv menüü võib ilmuda alles pärast tema enda skripti tööd.
		setTimeout( refresh, 400 );
		setTimeout( refresh, 1500 );

		var timer = null;
		window.addEventListener( 'resize', function () {
			clearTimeout( timer );
			timer = setTimeout( refresh, 150 );
		} );

		if ( window.matchMedia ) {
			var mq = window.matchMedia( '(orientation: portrait)' );
			if ( mq.addEventListener ) {
				mq.addEventListener( 'change', refresh );
			}
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
