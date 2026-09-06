/**
 * Wonom Kampaaniariba — administraatori vorm.
 * Keelevahetus, oma kuupäevavalija (E-esimene, 24 h), elav eelvaade, automaatne tõlge.
 */
( function ( $ ) {
	'use strict';

	var CFG = window.WKR_ADMIN || {};
	var TXT = CFG.i18n || {};
	var previewLang = 'et';

	function pad( n ) {
		return n < 10 ? '0' + n : '' + n;
	}

	/* ---------------- kuupäevavalija ----------------
	   Brauseri oma datetime-local järgib OS-i lokaati (pühapäev esimesena, AM/PM),
	   seetõttu on siin oma valija: nädal algab esmaspäevast, kell on 24 h. */

	function parseDisplay( txt ) {
		var m = /^\s*(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})(?:[\s,]+(\d{1,2})[:.](\d{1,2}))?\s*$/.exec( txt || '' );
		if ( ! m ) {
			return null;
		}
		var d = +m[ 1 ], mo = +m[ 2 ], y = +m[ 3 ];
		var h = m[ 4 ] != null ? +m[ 4 ] : 0;
		var mi = m[ 5 ] != null ? +m[ 5 ] : 0;
		if ( mo < 1 || mo > 12 || d < 1 || d > 31 || h > 23 || mi > 59 ) {
			return null;
		}
		return { y: y, m: mo, d: d, h: h, mi: mi };
	}

	function fmt( p ) {
		return pad( p.d ) + '.' + pad( p.m ) + '.' + p.y + ' ' + pad( p.h ) + ':' + pad( p.mi );
	}

	function datePicker( input ) {
		var wrap = input.parentNode;
		var pop = document.createElement( 'div' );
		pop.className = 'wkr-dtpop';
		pop.hidden = true;
		wrap.appendChild( pop );

		var btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'wkr-dtbtn';
		btn.setAttribute( 'aria-label', 'Ava kalender' );
		btn.innerHTML = '<span aria-hidden="true">&#128197;</span>';
		wrap.insertBefore( btn, pop );

		var view = null;

		function current() {
			var now = new Date();
			return parseDisplay( input.value ) || {
				y: now.getFullYear(), m: now.getMonth() + 1, d: now.getDate(), h: 0, mi: 0
			};
		}

		function set( p ) {
			input.value = fmt( p );
			$( input ).trigger( 'change' );
			draw();
		}

		function open() {
			var p = current();
			view = { y: p.y, m: p.m };
			pop.hidden = false;
			draw();
			document.addEventListener( 'mousedown', outside, true );
			document.addEventListener( 'keydown', onKey, true );
		}

		function close() {
			pop.hidden = true;
			document.removeEventListener( 'mousedown', outside, true );
			document.removeEventListener( 'keydown', onKey, true );
		}

		function outside( e ) {
			if ( ! wrap.contains( e.target ) ) {
				close();
			}
		}

		function onKey( e ) {
			if ( e.key === 'Escape' ) {
				e.stopPropagation();
				close();
				btn.focus();
			}
		}

		btn.addEventListener( 'click', function () {
			if ( pop.hidden ) {
				open();
			} else {
				close();
			}
		} );

		function navButton( label, aria, fn ) {
			var b = document.createElement( 'button' );
			b.type = 'button';
			b.textContent = label;
			b.setAttribute( 'aria-label', aria );
			b.addEventListener( 'click', fn );
			return b;
		}

		function draw() {
			if ( pop.hidden ) {
				return;
			}
			pop.textContent = '';

			var cur = current();
			var nav = document.createElement( 'div' );
			nav.className = 'wkr-dtnav';
			nav.appendChild( navButton( '‹', 'Eelmine kuu', function () {
				view.m--;
				if ( view.m < 1 ) { view.m = 12; view.y--; }
				draw();
			} ) );
			var title = document.createElement( 'span' );
			title.className = 'wkr-dtmonth';
			title.textContent = ( CFG.months || [] )[ view.m - 1 ] + ' ' + view.y;
			nav.appendChild( title );
			nav.appendChild( navButton( '›', 'Järgmine kuu', function () {
				view.m++;
				if ( view.m > 12 ) { view.m = 1; view.y++; }
				draw();
			} ) );
			pop.appendChild( nav );

			var grid = document.createElement( 'div' );
			grid.className = 'wkr-dtgrid';
			( CFG.dow || [ 'E', 'T', 'K', 'N', 'R', 'L', 'P' ] ).forEach( function ( d ) {
				var head = document.createElement( 'span' );
				head.className = 'wkr-dtdow';
				head.textContent = d;
				grid.appendChild( head );
			} );

			var first = new Date( Date.UTC( view.y, view.m - 1, 1 ) );
			var shift = ( first.getUTCDay() + 6 ) % 7;
			var today = new Date();

			for ( var i = 0; i < 42; i++ ) {
				( function () {
					var dt = new Date( Date.UTC( view.y, view.m - 1, 1 - shift + i ) );
					var y = dt.getUTCFullYear(), mo = dt.getUTCMonth() + 1, d = dt.getUTCDate();
					var cell = document.createElement( 'button' );
					cell.type = 'button';
					cell.textContent = d;
					if ( mo !== view.m ) {
						cell.className = 'is-out';
					}
					if ( y === today.getFullYear() && mo === today.getMonth() + 1 && d === today.getDate() ) {
						cell.className += ' is-today';
					}
					if ( y === cur.y && mo === cur.m && d === cur.d ) {
						cell.className += ' is-sel';
					}
					cell.addEventListener( 'click', function () {
						set( { y: y, m: mo, d: d, h: cur.h, mi: cur.mi } );
					} );
					grid.appendChild( cell );
				}() );
			}
			pop.appendChild( grid );

			var time = document.createElement( 'div' );
			time.className = 'wkr-dttime';
			var hSel = document.createElement( 'select' );
			hSel.setAttribute( 'aria-label', 'Tund' );
			for ( var h = 0; h < 24; h++ ) {
				hSel.appendChild( new Option( pad( h ), pad( h ) ) );
			}
			hSel.value = pad( cur.h );
			var mSel = document.createElement( 'select' );
			mSel.setAttribute( 'aria-label', 'Minut' );
			for ( var mi = 0; mi < 60; mi++ ) {
				mSel.appendChild( new Option( pad( mi ), pad( mi ) ) );
			}
			mSel.value = pad( cur.mi );

			function apply() {
				var c = current();
				set( { y: c.y, m: c.m, d: c.d, h: +hSel.value, mi: +mSel.value } );
			}
			hSel.addEventListener( 'change', apply );
			mSel.addEventListener( 'change', apply );

			time.appendChild( hSel );
			time.appendChild( document.createTextNode( ':' ) );
			time.appendChild( mSel );
			pop.appendChild( time );

			var foot = document.createElement( 'div' );
			foot.className = 'wkr-dtfoot';
			var nowBtn = document.createElement( 'button' );
			nowBtn.type = 'button';
			nowBtn.className = 'button button-small';
			nowBtn.textContent = TXT.now || 'Praegu';
			nowBtn.addEventListener( 'click', function () {
				var n = new Date();
				set( { y: n.getFullYear(), m: n.getMonth() + 1, d: n.getDate(), h: n.getHours(), mi: n.getMinutes() } );
			} );
			var okBtn = document.createElement( 'button' );
			okBtn.type = 'button';
			okBtn.className = 'button button-small button-primary';
			okBtn.textContent = TXT.done || 'Valmis';
			okBtn.addEventListener( 'click', close );
			foot.appendChild( nowBtn );
			foot.appendChild( okBtn );
			pop.appendChild( foot );
		}
	}

	/* ---------------- elav eelvaade ---------------- */

	function val( name ) {
		var el = document.querySelector( '[data-wkr="' + name + '"]' );
		if ( ! el ) {
			return '';
		}
		return el.type === 'checkbox' ? ( el.checked ? 1 : 0 ) : el.value;
	}

	function text( lang, key ) {
		var el = document.querySelector( '[data-wkr-text="' + lang + '.' + key + '"]' );
		var value = el ? el.value : '';
		if ( ! value && lang !== 'et' ) {
			var fallback = document.querySelector( '[data-wkr-text="et.' + key + '"]' );
			value = fallback ? fallback.value : '';
		}
		return value;
	}

	function renderPreview() {
		var box = document.getElementById( 'wkr-preview' );
		if ( ! box ) {
			return;
		}

		var fonts = CFG.fonts || {};
		var coupon = val( 'coupon' );
		var lang = previewLang;

		var closePos = val( 'close_pos' ) || 'right';

		var slot = document.createElement( 'div' );
		slot.className = 'wkr-slot wkr-slot--close-' + closePos;
		slot.style.cssText = '--wkr-bg:' + val( 'bg' ) +
			';--wkr-fg:' + val( 'fg' ) +
			';--wkr-hl:' + val( 'hl' ) +
			';--wkr-size:' + ( val( 'size' ) || 13 ) + 'px' +
			';--wkr-close-offset:' + ( val( 'close_offset' ) || 0 ) + 'px' +
			';--wkr-font:' + ( fonts[ val( 'font' ) ] || 'inherit' );

		var banner = document.createElement( 'div' );
		banner.className = 'wkr-banner';

		[ 'l1', 'l2', 'l3' ].forEach( function ( key ) {
			var value = text( lang, key );
			if ( ! value ) {
				return;
			}
			var line = document.createElement( 'span' );
			line.className = 'wkr-line wkr-' + key;
			line.textContent = value;
			banner.appendChild( line );
		} );

		if ( coupon ) {
			var wrapLine = document.createElement( 'span' );
			wrapLine.className = 'wkr-line';
			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'wkr-coupon';
			btn.title = TXT.copy || 'Kopeeri sooduskood';
			var label = text( lang, 'coupon_label' ) || ( lang === 'en' ? 'Coupon code' : 'Sooduskood' );
			var labelSpan = document.createElement( 'span' );
			labelSpan.className = 'wkr-coupon-label';
			labelSpan.textContent = label + ':';
			var codeSpan = document.createElement( 'span' );
			codeSpan.className = 'wkr-code';
			codeSpan.textContent = coupon;
			btn.appendChild( labelSpan );
			btn.appendChild( codeSpan );
			wrapLine.appendChild( btn );
			banner.appendChild( wrapLine );
		}

		if ( val( 'countdown' ) ) {
			var cdLine = document.createElement( 'span' );
			cdLine.className = 'wkr-line';
			var cd = document.createElement( 'span' );
			cd.className = 'wkr-countdown';
			cd.textContent = lang === 'en' ? 'ends in 2 d 04:12:08' : 'lõpeb 2 p 04:12:08';
			cdLine.appendChild( cd );
			banner.appendChild( cdLine );
		}

		if ( val( 'dismissible' ) ) {
			var close = document.createElement( 'button' );
			close.type = 'button';
			close.className = 'wkr-close';
			close.textContent = '✕';
			banner.appendChild( close );
		}

		slot.appendChild( banner );

		if ( val( 'dismissible' ) && coupon ) {
			var mini = document.createElement( 'div' );
			mini.className = 'wkr-mini';
			mini.style.display = 'block';
			mini.style.marginTop = '6px';
			mini.innerHTML = banner.querySelector( '.wkr-coupon' ) ? banner.querySelector( '.wkr-coupon' ).outerHTML : '';
			var note = document.createElement( 'span' );
			note.className = 'wkr-mini-note';
			note.textContent = lang === 'en' ? 'after closing' : 'pärast sulgemist';
			mini.appendChild( note );
			slot.appendChild( mini );
		}

		box.textContent = '';
		box.appendChild( slot );

		var warn = document.getElementById( 'wkr-zwarn' );
		var z = parseInt( val( 'zindex' ), 10 ) || 0;
		if ( warn ) {
			warn.hidden = z < 400;
		}
	}

	/* ---------------- keelevahetus ---------------- */

	function bindTabs() {
		$( '[data-wkr-tab]' ).on( 'click', function () {
			var lang = $( this ).data( 'wkr-tab' );
			$( '[data-wkr-tab]' ).attr( 'aria-pressed', 'false' );
			$( this ).attr( 'aria-pressed', 'true' );
			$( '[data-wkr-panel]' ).prop( 'hidden', true );
			$( '[data-wkr-panel="' + lang + '"]' ).prop( 'hidden', false );
			$( '#wkr-translate' ).prop( 'hidden', lang === 'et' );
		} );
		$( '#wkr-translate' ).prop( 'hidden', true );

		$( '[data-wkr-prevlang]' ).on( 'click', function () {
			previewLang = $( this ).data( 'wkr-prevlang' );
			$( '[data-wkr-prevlang]' ).attr( 'aria-pressed', 'false' );
			$( this ).attr( 'aria-pressed', 'true' );
			renderPreview();
		} );
	}

	/* ---------------- WooCommerce'i kupong ---------------- */

	function bindWoo() {
		var mode = document.getElementById( 'wkr_wc_mode' );
		var fields = document.querySelector( '.wkr-woo-fields' );
		if ( ! mode || ! fields ) {
			return;
		}
		mode.addEventListener( 'change', function () {
			fields.hidden = mode.value !== 'manage';
		} );
	}

	/* ---------------- automaatne tõlge ---------------- */

	function bindTranslate() {
		$( '#wkr-translate' ).on( 'click', function () {
			var button = $( this );
			var label = button.text();
			var source = {};

			$( '[data-wkr-text^="et."]' ).each( function () {
				var key = $( this ).data( 'wkr-text' ).split( '.' )[ 1 ];
				source[ key ] = this.value;
			} );

			button.prop( 'disabled', true ).text( TXT.translating || 'Tõlgin…' );

			$.post( CFG.ajaxUrl, {
				action: 'wkr_translate',
				nonce: CFG.nonce,
				target: 'en',
				source: source
			} ).done( function ( response ) {
				if ( ! response || ! response.success ) {
					window.alert( TXT.failed || 'Tõlkimine ebaõnnestus.' );
					return;
				}

				var fields = response.data.fields || {};
				Object.keys( fields ).forEach( function ( key ) {
					var input = document.querySelector( '[data-wkr-text="en.' + key + '"]' );
					if ( input ) {
						input.value = fields[ key ];
					}
				} );

				document.getElementById( 'wkr_en_auto' ).value = '1';
				$( '#wkr-autonote' ).prop( 'hidden', false );

				if ( response.data.engine === 'dictionary' ) {
					$( '#wkr-autonote' ).addClass( 'is-rough' );
				}

				renderPreview();
			} ).fail( function () {
				window.alert( TXT.failed || 'Tõlkimine ebaõnnestus.' );
			} ).always( function () {
				button.prop( 'disabled', false ).text( label );
			} );
		} );

		// Käsitsi muudetud ingliskeelne väli ei ole enam automaatne tõlge.
		$( '[data-wkr-text^="en."]' ).on( 'input', function () {
			var flag = document.getElementById( 'wkr_en_auto' );
			if ( flag ) {
				flag.value = '0';
			}
			$( '#wkr-autonote' ).prop( 'hidden', true );
		} );
	}

	/* ---------------- käivitus ---------------- */

	$( function () {
		$( '.wkr-color' ).wpColorPicker( {
			change: function () {
				setTimeout( renderPreview, 30 );
			},
			clear: renderPreview
		} );

		Array.prototype.forEach.call( document.querySelectorAll( '.wkr-date' ), datePicker );

		bindTabs();
		bindTranslate();
		bindWoo();

		$( document ).on( 'input change', '[data-wkr], [data-wkr-text]', renderPreview );

		renderPreview();
	} );
}( window.jQuery ) );
