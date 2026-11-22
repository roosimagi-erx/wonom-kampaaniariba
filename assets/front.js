/**
 * Wonom kampaaniad — riba poe esiküljel.
 *
 * Selle skripti laeb lehe päises olev laadija pärast seda, kui andmefail on
 * käes (window.wkrBoot.data). Skript valib kellaaja järgi õige kampaania,
 * joonistab riba, vahetab selle täpsel sekundil järgmise vastu ja hoolitseb
 * sulgemise, sooduskoodi kopeerimise ja tagasiloenduse eest.
 *
 * Leht ise võib olla vahemälus ükskõik kui vana — riba sisu tuleb alati
 * andmefailist.
 */
( function () {
	'use strict';

	var BOOT = window.wkrBoot;

	if ( ! BOOT ) {
		return;
	}

	// Laadija võib andmed uuesti küsida; skript ise jookseb ühe korra.
	if ( window.wkrRun ) {
		window.wkrRun();
		return;
	}

	var TOKEN = 'WKRCODE7X';
	var REFRESH_MS = 5 * 60 * 1000;

	var boundaryTimer = null;
	var resizeTimer = null;
	var started = false;

	function data() {
		return BOOT.data || {};
	}

	function txt() {
		return data().i18n || {};
	}

	/** Serveri kell: seadme kell pluss laadija mõõdetud vahe. */
	function now() {
		return Math.floor( Date.now() / 1000 ) + ( BOOT.off || 0 );
	}

	function reducedMotion() {
		return !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
	}

	function key( slot ) {
		return 'wkr_d_' + slot.getAttribute( 'data-wkr-id' ) + '_' + slot.getAttribute( 'data-wkr-place' );
	}

	function stored( name ) {
		try {
			return window.localStorage.getItem( name );
		} catch ( e ) {
			return null;
		}
	}

	function store( name, value ) {
		try {
			if ( null === value ) {
				window.localStorage.removeItem( name );
			} else {
				window.localStorage.setItem( name, value );
			}
		} catch ( e ) {
			/* privaatne aken või keelatud salvestus — riba töötab ikka, lihtsalt ei jäta meelde */
		}
	}

	function remember( slot, collapsed ) {
		store( key( slot ), collapsed ? '1' : null );
	}

	function pad( n ) {
		return n < 10 ? '0' + n : '' + n;
	}

	function esc( text ) {
		return String( text )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	/** Sooduskood on andmefailis kodeeritult; siin pööratakse see tagasi. */
	function code( campaign ) {
		if ( ! campaign.k ) {
			return '';
		}
		try {
			return decodeURIComponent( window.atob( campaign.k ).split( '' ).reverse().join( '' ) );
		} catch ( e ) {
			return '';
		}
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

			var value = button.getAttribute( 'data-wkr-code' ) || '';
			var label = button.querySelector( '.wkr-coupon-label' );
			var original = label ? label.textContent : '';

			copy( value ).then( function () {
				button.classList.add( 'is-copied' );
				if ( label ) {
					label.textContent = txt().copied || 'Kopeeritud!';
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
				refresh();
			} );
		}

		if ( expand ) {
			expand.addEventListener( 'click', function ( ev ) {
				ev.preventDefault();
				ev.stopPropagation();
				slot.classList.remove( 'is-collapsed' );
				remember( slot, false );
				refresh();
			} );
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
			if ( el.closest && el.closest( '.wkr-slot, .wkr-host' ) ) {
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

	/* Levinumate teemade „keri üles” nupud. WoodMartil on see .scrollToTop. */
	var FLOAT_SELECTOR = [
		'.scrollToTop',
		'.wd-scroll-top',
		'#scroll-to-top',
		'.scroll-to-top',
		'.back-to-top',
		'.backtotop',
		'#back-to-top',
		'.et_pb_scroll_top'
	].join( ', ' );

	/** Annab teema „keri üles” nupu talle tagasi. */
	function releaseFloating( selector ) {
		var nodes;
		try {
			nodes = document.querySelectorAll( selector || FLOAT_SELECTOR );
		} catch ( e ) {
			return;
		}
		Array.prototype.forEach.call( nodes, function ( el ) {
			if ( el.closest && el.closest( '.wkr-slot' ) ) {
				return;
			}
			el.style.removeProperty( 'bottom' );
			el.style.removeProperty( 'z-index' );
			el.style.removeProperty( 'display' );
		} );
	}

	/**
	 * Teema ujuv „keri üles” nupp istub tavaliselt samas nurgas, kus riba.
	 * Kampaania seadetest saab valida, kas see tõsta riba kohale, jätta riba
	 * taha või peita seniks, kuni riba on ekraanil.
	 */
	function applyFloating( slot ) {
		var mode = slot.getAttribute( 'data-wkr-float' ) || 'none';
		if ( 'none' === mode ) {
			return;
		}

		var selector = ( slot.getAttribute( 'data-wkr-float-sel' ) || '' ).trim() || FLOAT_SELECTOR;
		var nodes;

		try {
			nodes = document.querySelectorAll( selector );
		} catch ( e ) {
			return; // vigane valija — parem mitte midagi teha
		}

		var style = window.getComputedStyle( slot );
		var height = slot.offsetHeight;
		var showing = height > 0 && 'none' !== style.display;

		if ( ! showing ) {
			releaseFloating( selector );
			return;
		}

		// Vaba ruum riba ülaservast ekraani alaservani. Arvutatakse riba
		// kõrgusest ja alumisest nihkest, mitte asukohast ekraanil — sisse
		// liikuv riba on hetkeks veel ekraani all ja annaks vale tulemuse.
		var lift = Math.round( height + ( parseFloat( style.bottom ) || 0 ) ) + 12;
		var ourZ = parseInt( style.zIndex, 10 ) || 90;

		Array.prototype.forEach.call( nodes, function ( el ) {
			if ( el.closest && el.closest( '.wkr-slot' ) ) {
				return;
			}

			if ( 'hide' === mode ) {
				el.style.setProperty( 'display', 'none', 'important' );
			} else if ( 'behind' === mode ) {
				el.style.setProperty( 'z-index', String( Math.max( 0, ourZ - 1 ) ), 'important' );
			} else if ( 'lift' === mode ) {
				el.style.setProperty( 'bottom', lift + 'px', 'important' );
			}
		} );
	}

	/** Kleepuv riba ei tohi katta jaluse linke. */
	function padBody() {
		if ( ! document.body ) {
			return;
		}

		var stuck = document.querySelector( '.wkr-slot--stuck, .wkr-slot--floating' );
		var height = 0;

		if ( data().pad && stuck && stuck.style.display !== 'none' ) {
			height = stuck.getBoundingClientRect().height;
			if ( stuck.classList.contains( 'wkr-slot--floating' ) ) {
				height += 12;
			}
		}

		document.body.style.paddingBottom = height ? Math.ceil( height ) + 'px' : '';
	}

	/** Jätab päiseriba kõrguse meelde, et järgmine leht saaks ruumi ette kinni hoida. */
	function rememberTop() {
		var slot = document.querySelector( '#wkr-host-top > .wkr-slot' );
		var height = slot && slot.style.display !== 'none' && ! slot.style.maxHeight
			? Math.round( slot.getBoundingClientRect().height )
			: 0;
		var end = slot ? parseInt( slot.getAttribute( 'data-wkr-end' ), 10 ) || 0 : 0;

		// Sisseliikumise ajal on kõrgus poolik — siis ei kirjuta midagi üle.
		if ( slot && slot.style.maxHeight ) {
			return;
		}

		store( 'wkr_top', height > 0 && end ? JSON.stringify( { h: height, e: end } ) : null );
	}

	function refresh() {
		applyBottomInset();
		padBody();

		var bottoms = document.querySelectorAll( '.wkr-slot--stuck, .wkr-slot--floating' );
		if ( bottoms.length ) {
			Array.prototype.forEach.call( bottoms, applyFloating );
		} else {
			releaseFloating();
		}

		rememberTop();
	}

	/** Tagasiloendus. Riba lõpuhetke eest hoolitseb schedule(), mitte see. */
	function paintCountdowns() {
		var nodes = document.querySelectorAll( '.wkr-countdown' );
		if ( ! nodes.length ) {
			return;
		}

		var t = now();
		var changed = false;

		Array.prototype.forEach.call( nodes, function ( node ) {
			var end = parseInt( node.getAttribute( 'data-wkr-end' ), 10 ) || 0;
			var left = end - t;

			// Loendur ilmub alles viimasel ööpäeval (0 = alati nähtav).
			var win = parseInt( node.getAttribute( 'data-wkr-window' ), 10 ) || 0;
			var line = node.parentNode;
			var show = left > 0 && ( ! win || left < win );

			if ( line && line.classList && line.classList.contains( 'wkr-line--countdown' ) ) {
				var want = show ? '' : 'none';
				if ( line.style.display !== want ) {
					line.style.display = want;
					changed = true;
				}
			}

			if ( ! show ) {
				node.textContent = '';
				return;
			}

			var d = Math.floor( left / 86400 );
			var h = Math.floor( ( left % 86400 ) / 3600 );
			var m = Math.floor( ( left % 3600 ) / 60 );
			var s = left % 60;
			var lead = txt().ends || 'lõpeb';

			node.textContent = d > 0
				? lead + ' ' + d + ' ' + ( txt().days || 'p' ) + ' ' + pad( h ) + ':' + pad( m ) + ':' + pad( s )
				: lead + ' ' + pad( h ) + ':' + pad( m ) + ':' + pad( s );
		} );

		// Rida tuli juurde või kadus — riba kõrgus muutus, jaluse ruum peab järele tulema.
		if ( changed ) {
			refresh();
		}
	}

	/** Kampaania, mis antud kohta praegu kuulub: esimene eetris olev, tähtsam ees. */
	function pick( place ) {
		var list = data().c || [];
		var t = now();

		for ( var i = 0; i < list.length; i++ ) {
			if ( list[ i ][ place ] && list[ i ].s <= t && t < list[ i ].e ) {
				return list[ i ];
			}
		}
		return null;
	}

	function byId( id ) {
		var list = data().c || [];
		var t = now();

		for ( var i = 0; i < list.length; i++ ) {
			if ( String( list[ i ].id ) === String( id ) && list[ i ].s <= t && t < list[ i ].e ) {
				return list[ i ];
			}
		}
		return null;
	}

	/** Koht lehel: päiseriba läheb lehe algusesse, jaluseriba lõppu. */
	function host( place ) {
		var id = 'wkr-host-' + place;
		var el = document.getElementById( id );

		if ( ! el ) {
			el = document.createElement( 'div' );
			el.id = id;
			el.className = 'wkr-host wkr-host--' + place;

			if ( 'top' === place ) {
				document.body.insertBefore( el, document.body.firstChild );
			} else {
				document.body.appendChild( el );
			}
		}
		return el;
	}

	function build( campaign, html ) {
		var box = document.createElement( 'div' );
		box.innerHTML = String( html ).split( TOKEN ).join( esc( code( campaign ) ) );

		var slot = box.firstElementChild;
		if ( ! slot ) {
			return null;
		}

		slot.setAttribute( 'data-wkr-rev', campaign.r || '' );

		// Suletud olek kohe külge — riba ei tohi enne avatuna vilksatada.
		if ( stored( key( slot ) ) ) {
			slot.classList.add( 'is-collapsed' );
			if ( ! slot.querySelector( '.wkr-mini' ) ) {
				slot.style.display = 'none';
			}
		}

		return slot;
	}

	function loadFont( campaign ) {
		if ( ! campaign.font ) {
			return;
		}
		var id = 'wkr-font-' + campaign.id;
		if ( document.getElementById( id ) ) {
			return;
		}
		var link = document.createElement( 'link' );
		link.id = id;
		link.rel = 'stylesheet';
		link.href = campaign.font;
		( document.head || document.documentElement ).appendChild( link );
	}

	function same( slot, campaign ) {
		return !! slot && !! campaign &&
			slot.getAttribute( 'data-wkr-id' ) === String( campaign.id ) &&
			slot.getAttribute( 'data-wkr-rev' ) === String( campaign.r || '' );
	}

	/** Sisseliikumine: jaluseriba libiseb alt üles, päiseriba avaneb ülevalt. */
	function enter( slot, place, reserved ) {
		if ( reducedMotion() || slot.style.display === 'none' ) {
			return;
		}

		if ( 'bottom' === place ) {
			slot.classList.add( 'wkr-enter' );
			// Kaks kaadrit: esimene joonistab algoleku, teine käivitab ülemineku.
			window.requestAnimationFrame( function () {
				window.requestAnimationFrame( function () {
					slot.classList.remove( 'wkr-enter' );
				} );
			} );
			// Peidus vahelehel kaadreid ei joonistata — riba ei tohi sinna peitu jääda.
			setTimeout( function () {
				slot.classList.remove( 'wkr-enter' );
			}, 600 );
			setTimeout( refresh, 1000 );
			return;
		}

		// Kui ruum oli ette kinni hoitud, on riba juba oma kohal — ei liiguta midagi.
		if ( reserved ) {
			return;
		}

		var full = slot.scrollHeight;
		slot.style.overflow = 'hidden';
		slot.style.maxHeight = '0px';
		slot.style.transition = 'max-height .3s ease';

		setTimeout( function () {
			slot.style.maxHeight = full + 'px';
		}, 30 );

		setTimeout( function () {
			slot.style.overflow = '';
			slot.style.maxHeight = '';
			slot.style.transition = '';
			refresh();
		}, 420 );
	}

	/** Paneb antud kohta õige riba või võtab vana ära. */
	function mount( place ) {
		var want = pick( place );
		var box = document.getElementById( 'wkr-host-' + place );
		var current = box ? box.firstElementChild : null;

		if ( ! want ) {
			if ( current ) {
				box.removeChild( current );
			}
			return;
		}

		if ( same( current, want ) ) {
			return;
		}

		var slot = build( want, want[ place ] );
		if ( ! slot ) {
			return;
		}

		box = host( place );

		var reserved = 'top' === place && !! document.getElementById( 'wkr-reserve' );

		// Sama kampaania muudetud sisu vahetub vaikselt, uus kampaania liigub sisse.
		var fresh = ! current || current.getAttribute( 'data-wkr-id' ) !== String( want.id );

		if ( current ) {
			box.replaceChild( slot, current );
		} else {
			box.appendChild( slot );
		}

		loadFont( want );
		bindSlot( slot );

		if ( fresh ) {
			enter( slot, place, reserved );
		}
	}

	/** Lühikoodi kohad: riba joonistatakse lehe sisse, mitte lehe serva. */
	function mountInline() {
		var hosts = document.querySelectorAll( '.wkr-inline-host' );

		Array.prototype.forEach.call( hosts, function ( box ) {
			var place = box.getAttribute( 'data-wkr-place' ) === 'bottom' ? 'bottom' : 'top';
			var id = parseInt( box.getAttribute( 'data-wkr-id' ), 10 ) || 0;
			var want = id ? byId( id ) : pick( place );
			var current = box.firstElementChild;

			if ( ! want ) {
				if ( current ) {
					box.removeChild( current );
				}
				return;
			}

			if ( same( current, want ) ) {
				return;
			}

			var slot = build( want, want[ place ] || want.top || want.bottom );
			if ( ! slot ) {
				return;
			}

			slot.classList.remove( 'wkr-slot--stuck', 'wkr-slot--floating' );
			slot.classList.add( 'wkr-slot--inline' );
			slot.removeAttribute( 'data-wkr-float' );
			slot.removeAttribute( 'data-wkr-float-sel' );
			slot.id = slot.id + '-inline';

			if ( current ) {
				box.replaceChild( slot, current );
			} else {
				box.appendChild( slot );
			}

			loadFont( want );
			bindSlot( slot );
		} );
	}

	/** Ootab, kuni riba stiilid on laetud — stiilita riba ei tohi ekraanile jõuda. */
	function whenStyled( done ) {
		var link = document.getElementById( 'wkr-css' );

		if ( ! link || link.sheet ) {
			done();
			return;
		}

		var fired = false;
		function go() {
			if ( ! fired ) {
				fired = true;
				done();
			}
		}

		link.addEventListener( 'load', go );
		link.addEventListener( 'error', go );
		setTimeout( go, 3000 );
	}

	/** Järgmine hetk, mil mõni kampaania algab või lõpeb. */
	function schedule() {
		clearTimeout( boundaryTimer );

		var list = data().c || [];
		var t = now();
		var next = 0;

		for ( var i = 0; i < list.length; i++ ) {
			[ list[ i ].s, list[ i ].e ].forEach( function ( moment ) {
				if ( moment > t && ( ! next || moment < next ) ) {
					next = moment;
				}
			} );
		}

		if ( next ) {
			// Millisekundi täpsusega, et vahetus ei hilineks sekundi murdosa
			// ümardamise pärast. Brauseri taimeri ülempiir on umbes 24 päeva.
			var wait = next * 1000 - ( Date.now() + ( BOOT.off || 0 ) * 1000 ) + 60;
			boundaryTimer = setTimeout( run, Math.max( 60, Math.min( wait, 2147000000 ) ) );
		}
	}

	function render() {
		mount( 'top' );
		mount( 'bottom' );
		mountInline();

		// Varajane ruumihoidja on oma töö teinud.
		var reserve = document.getElementById( 'wkr-reserve' );
		if ( reserve && reserve.parentNode ) {
			reserve.parentNode.removeChild( reserve );
		}

		paintCountdowns();
		refresh();
		schedule();
	}

	/** Klõps ribal jätab sooduskoodi küpsisesse, et ostukorv saaks selle rakendada. */
	function bindApply() {
		document.addEventListener( 'click', function ( ev ) {
			var link = ev.target && ev.target.closest ? ev.target.closest( 'a[data-wkr-apply]' ) : null;
			if ( ! link ) {
				return;
			}

			var slot = link.closest( '.wkr-slot' );
			var end = slot ? parseInt( slot.getAttribute( 'data-wkr-end' ), 10 ) || 0 : 0;
			var age = end ? Math.max( 60, Math.min( 259200, end - now() ) ) : 259200;

			try {
				document.cookie = 'wkr_coupon=' + encodeURIComponent( link.getAttribute( 'data-wkr-apply' ) ) +
					';path=/;max-age=' + age + ';SameSite=Lax' +
					( 'https:' === window.location.protocol ? ';Secure' : '' );
			} catch ( e ) {
				/* küpsised keelatud — link töötab ikka, kood jääb lihtsalt rakendamata */
			}
		}, true );
	}

	/** Ühekordne seadistus: taimerid ja akna sündmused. */
	function start() {
		if ( started ) {
			return;
		}
		started = true;

		bindApply();

		setInterval( paintCountdowns, 1000 );

		// Teema kleepuv menüü võib ilmuda alles pärast tema enda skripti tööd.
		setTimeout( refresh, 400 );
		setTimeout( refresh, 1500 );

		window.addEventListener( 'resize', function () {
			clearTimeout( resizeTimer );
			resizeTimer = setTimeout( refresh, 150 );
		} );

		if ( window.matchMedia ) {
			var mq = window.matchMedia( '(orientation: portrait)' );
			if ( mq.addEventListener ) {
				mq.addEventListener( 'change', refresh );
			}
		}

		/*
		 * Serverit ei küsitleta taimeriga. Andmed küsitakse uuesti ainult siis,
		 * kui klient tuleb vahelehele tagasi ja eelmisest korrast on möödas
		 * mitu minutit — nii jõuab päeval lisatud kampaania ka kaua lahti olnud
		 * lehele. Juba teada kampaaniate algus ja lõpp uut päringut ei vaja.
		 */
		document.addEventListener( 'visibilitychange', function () {
			if ( document.hidden ) {
				return;
			}

			run();

			if ( BOOT.fetch && Date.now() - ( BOOT.at || 0 ) > REFRESH_MS ) {
				BOOT.fetch();
			}
		} );
	}

	function run() {
		if ( ! document.body ) {
			document.addEventListener( 'DOMContentLoaded', run );
			return;
		}

		start();
		whenStyled( render );
	}

	window.wkrRun = run;

	run();

	// Lühikoodi kohad lehe lõpus ei pruugi esimesel jooksul veel olemas olla.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', run );
	}
}() );
