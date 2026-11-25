/* Wonom kampaaniad – küsimus kupongi kohta, kui kampaania läheb prügikasti.
 *
 * Kampaania, mis haldab WooCommerce'i kupongi, ei tohi koodi märkamatult
 * kaasa viia. Enne prügikasti saatmist küsitakse, kas kood jääb kehtima või
 * läheb mustandiks. Valik läheb serverisse parameetriga wkr_coupon.
 */
( function () {
	'use strict';

	var CFG = window.WKR_TRASH || {};
	var T = CFG.i18n;

	if ( ! T ) {
		return;
	}

	function el( tag, cls, text ) {
		var node = document.createElement( tag );
		if ( cls ) {
			node.className = cls;
		}
		if ( text ) {
			node.textContent = text;
		}
		return node;
	}

	function ask( codes, done ) {
		var dialog = el( 'dialog', 'wkr-dialog' );
		var list = codes.map( function ( code ) {
			return '„' + code + '”';
		} ).join( ', ' );

		dialog.appendChild( el( 'h2', '', T.title ) );
		dialog.appendChild( el( 'p', '', T.text.replace( '%s', list ) ) );

		function option( label, hint, value, primary ) {
			var wrap = el( 'div', 'wkr-dialog-option' );
			var btn = el( 'button', 'button' + ( primary ? ' button-primary' : '' ), label );

			btn.type = 'button';
			btn.setAttribute( 'data-wkr-choice', value );
			btn.addEventListener( 'click', function () {
				dialog.close();
				dialog.remove();
				done( value );
			} );

			wrap.appendChild( btn );
			wrap.appendChild( el( 'span', 'wkr-hint', hint ) );
			dialog.appendChild( wrap );
		}

		option( T.keep, T.keepHint, 'keep', true );
		option( T.draft, T.draftHint, 'draft', false );

		var cancel = el( 'button', 'button-link wkr-dialog-cancel', T.cancel );
		cancel.type = 'button';
		cancel.addEventListener( 'click', function () {
			dialog.close();
			dialog.remove();
		} );
		dialog.appendChild( cancel );

		dialog.addEventListener( 'cancel', function () {
			dialog.remove();
		} );

		document.body.appendChild( dialog );

		if ( typeof dialog.showModal === 'function' ) {
			dialog.showModal();
		} else {
			// Väga vana brauser: ilma küsimata jääb kood kehtima.
			dialog.remove();
			done( 'keep' );
		}
	}

	function ownedCode( row ) {
		var mark = row ? row.querySelector( '[data-wkr-owned]' ) : null;
		return mark ? mark.getAttribute( 'data-wkr-owned' ) : '';
	}

	// Üksik link: nimekirja rida või muutmisvaate „Tõsta prügikasti”.
	document.addEventListener( 'click', function ( e ) {
		var link = e.target && e.target.closest ? e.target.closest( 'a.submitdelete' ) : null;

		if ( ! link || link.href.indexOf( 'action=trash' ) === -1 ) {
			return;
		}

		var row = link.closest( 'tr' );
		var code = row ? ownedCode( row ) : ( link.closest( '#delete-action' ) ? CFG.owned : '' );

		if ( ! code ) {
			return;
		}

		e.preventDefault();

		ask( [ code ], function ( choice ) {
			window.location.href = link.href + ( link.href.indexOf( '?' ) === -1 ? '?' : '&' ) + 'wkr_coupon=' + choice;
		} );
	} );

	// Masstegevus „Tõsta prügikasti”.
	var form = document.getElementById( 'posts-filter' );

	if ( form ) {
		form.addEventListener( 'submit', function ( e ) {
			if ( form.querySelector( 'input[name="wkr_coupon"]' ) ) {
				return;
			}

			var submitter = e.submitter || null;
			var select = form.querySelector( submitter && submitter.id === 'doaction2' ? 'select[name="action2"]' : 'select[name="action"]' );

			if ( ! submitter || ( submitter.id !== 'doaction' && submitter.id !== 'doaction2' ) || ! select || select.value !== 'trash' ) {
				return;
			}

			var codes = [];

			Array.prototype.forEach.call( form.querySelectorAll( 'tbody .check-column input[type="checkbox"]:checked' ), function ( box ) {
				var code = ownedCode( box.closest( 'tr' ) );
				if ( code ) {
					codes.push( code );
				}
			} );

			if ( ! codes.length ) {
				return;
			}

			e.preventDefault();

			ask( codes, function ( choice ) {
				var input = document.createElement( 'input' );
				input.type = 'hidden';
				input.name = 'wkr_coupon';
				input.value = choice;
				form.appendChild( input );

				if ( typeof form.requestSubmit === 'function' ) {
					form.requestSubmit( submitter );
				} else {
					submitter.click();
				}
			} );
		} );
	}
}() );
