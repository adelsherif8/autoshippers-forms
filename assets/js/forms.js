( function () {
  'use strict';

  class AsForm {
    constructor( wrap ) {
      this.wrap    = wrap;
      this.total   = parseInt( wrap.dataset.total, 10 );
      this.current = 1;
      this.uid     = wrap.id;

      this.progFill = wrap.querySelector( '.as-prog-fill' );
      this.tabs     = wrap.querySelectorAll( '.as-tab' );
      this.stepTabs = wrap.querySelector( '.as-step-tabs' );
      this.trust    = wrap.querySelector( '.as-trust' );
      this.errorEl  = wrap.querySelector( '.as-error-msg' );

      this._bind();
      this._refresh();
      this._initPhone();
    }

    /* Initialise intl-tel-input on the phone field so users get a country
       flag dropdown and the number is auto-formatted per country. */
    _initPhone() {
      const phoneEl = this.wrap.querySelector( 'input[name="phone"]' );
      if ( ! phoneEl || ! window.intlTelInput ) return;
      this.iti = window.intlTelInput( phoneEl, {
        initialCountry:     'ca',
        preferredCountries: [ 'ca', 'us' ],
        separateDialCode:   true,
        utilsScript:        'https://cdn.jsdelivr.net/npm/intl-tel-input@18.5.3/build/js/utils.js',
      } );
      /* Live format on input */
      phoneEl.addEventListener( 'input', () => {
        if ( typeof window.intlTelInputUtils === 'undefined' ) return;
        const raw = phoneEl.value;
        const formatted = this.iti.getNumber( window.intlTelInputUtils.numberFormat.NATIONAL );
        if ( formatted && formatted !== raw ) phoneEl.value = formatted;
      } );
    }

    /* ── Step visibility ── */
    _showStep( n ) {
      this.wrap.querySelectorAll( '.as-step' ).forEach( s => {
        s.classList.toggle( 'active', parseInt( s.dataset.step, 10 ) === n );
      } );
    }

    _refresh() {
      const isSuccess = this.current > this.total;
      this._showStep( isSuccess ? this.total + 1 : this.current );

      /* progress bar */
      if ( this.progFill ) {
        const pct = isSuccess ? 100 : ( this.current / this.total ) * 100;
        this.progFill.style.width = pct + '%';
      }

      /* tabs */
      this.tabs.forEach( tab => {
        const n   = parseInt( tab.dataset.tab, 10 );
        const num = tab.querySelector( '.as-tab-num' );
        tab.classList.remove( 'active', 'done' );
        if ( n === this.current )      tab.classList.add( 'active' );
        else if ( n < this.current )   tab.classList.add( 'done' );
        if ( num ) {
          num.innerHTML = n < this.current
            ? '<i class="fa-solid fa-check" style="font-size:9px"></i>'
            : String( n );
        }
      } );

      /* hide chrome on success */
      if ( isSuccess ) {
        if ( this.stepTabs ) this.stepTabs.style.display = 'none';
        if ( this.trust )    this.trust.style.display    = 'none';
      }
    }

    /* ── Navigation ── */
    next() {
      if ( ! this._validate() ) return;
      if ( this.current < this.total ) {
        this.current++;
        this._refresh();
        this._scrollTop();
      }
    }

    back() {
      if ( this.current > 1 ) {
        this.current--;
        this._refresh();
        this._scrollTop();
      }
    }

    _scrollTop() {
      this.wrap.scrollIntoView( { behavior: 'smooth', block: 'start' } );
    }

    /* ── Validation ── */
    _validate() {
      const step = this.wrap.querySelector( `.as-step[data-step="${ this.current }"]` );
      if ( ! step ) return true;

      /* required text/email/tel/date */
      for ( const inp of step.querySelectorAll( 'input[required], select[required], textarea[required]' ) ) {
        if ( ! inp.value.trim() ) {
          this._showError( 'Please fill in all required fields.' );
          inp.focus();
          return false;
        }
      }

      /* Phone: must be a valid number for the selected country */
      const phoneEl = step.querySelector( 'input[name="phone"]' );
      if ( phoneEl && this.iti && phoneEl.value.trim() ) {
        if ( ! this.iti.isValidNumber() ) {
          this._showError( 'Please enter a valid phone number for the selected country.' );
          phoneEl.focus();
          return false;
        }
      }

      /* required radio groups */
      const radioGroups = new Set(
        [ ...step.querySelectorAll( 'input[type="radio"]' ) ].map( r => r.name )
      );
      for ( const name of radioGroups ) {
        const checked = step.querySelector( `input[name="${ name }"]:checked` );
        if ( ! checked ) {
          this._showError( 'Please make a selection before continuing.' );
          return false;
        }
      }

      /* required selects (From / To city) */
      for ( const sel of step.querySelectorAll( 'select.as-select' ) ) {
        if ( ! sel.value ) {
          this._showError( 'Please select a city for both From and To.' );
          sel.focus();
          return false;
        }
      }

      this._clearError();
      return true;
    }

    _showError( msg ) {
      if ( this.errorEl ) {
        this.errorEl.textContent = msg;
        this.errorEl.style.display = 'block';
      }
    }

    _clearError() {
      if ( this.errorEl ) {
        this.errorEl.textContent   = '';
        this.errorEl.style.display = 'none';
      }
    }

    /* Fetch a fresh nonce so cached pages with a stale nonce still submit */
    _freshNonce() {
      const d = new FormData();
      d.append( 'action', 'as_get_nonce' );
      return fetch( asData.ajaxUrl, { method: 'POST', body: d, cache: 'no-store' } )
        .then( r => r.json() )
        .then( res => ( res && res.success && res.data && res.data.nonce ) ? res.data.nonce : asData.nonce )
        .catch( () => asData.nonce );
    }

    /* ── Submit ── */
    submit() {
      if ( ! this._validate() ) return;

      const btn = this.wrap.querySelector( '.as-btn-submit' );
      if ( btn ) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending…';
      }

      this._freshNonce().then( nonce => {
        const data = new FormData();
        data.append( 'action', 'as_submit' );
        data.append( 'nonce',  nonce );

        this.wrap.querySelectorAll( 'input, select, textarea' ).forEach( el => {
          if ( ! el.name ) return;
          if ( el.type === 'radio' || el.type === 'checkbox' ) {
            if ( el.checked ) data.set( el.name, el.value );
          } else if ( el.name === 'phone' && this.iti ) {
            /* Send the full E.164 international number (e.g. +14165550100) */
            const intl = this.iti.getNumber();
            data.set( 'phone', intl || el.value );
          } else {
            // date inputs store ISO value in dataset.dateVal; display text is in .value
            data.set( el.name, el.dataset.dateVal ?? el.value );
          }
        } );

        appendUtms( data );

        return fetch( asData.ajaxUrl, { method: 'POST', body: data } );
      } )
        .then( r => r.json() )
        .then( res => {
          if ( res.success ) {
            this.current = this.total + 1;
            this._refresh();
          } else {
            const msg = res.data?.message || 'Something went wrong. Please try again.';
            this._showError( msg );
            if ( btn ) {
              btn.disabled = false;
              btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Me My Quote';
            }
          }
        } )
        .catch( () => {
          this._showError( 'Network error. Please check your connection and try again.' );
          if ( btn ) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Me My Quote';
          }
        } );
    }

    /* ── Event binding ── */
    _bind() {
      this.wrap.addEventListener( 'click', e => {
        if ( e.target.closest( '.as-btn-next' ) )   this.next();
        if ( e.target.closest( '.as-btn-back' ) )   this.back();
        if ( e.target.closest( '.as-btn-submit' ) ) this.submit();
      } );

      /* "Other" city conditionals */
      this.wrap.addEventListener( 'change', e => {
        const sel = e.target.closest( 'select[data-other-target]' );
        if ( ! sel ) return;
        const targetId = sel.dataset.otherTarget + '-' + this.uid;
        const panel    = document.getElementById( targetId );
        if ( panel ) panel.classList.toggle( 'visible', sel.value === 'Other' );
      } );
    }
  }

  /* ── UTM / GCLID: read whatever the site-wide tracking script
        stashed in sessionStorage['scad_tracking_params'] and append
        the canonical lowercase keys to the form submission. ── */
  const CUSTOM_KEYS = [
    'utmcampaign_custom', 'utmmedium_custom', 'utmcontent_custom',
    'utmkeyword_custom',  'utmterm_custom',    'gclid_custom'
  ];

  function appendUtms( data ) {
    let stored = {};
    try { stored = JSON.parse( sessionStorage.getItem( 'scad_tracking_params' ) || '{}' ); } catch ( e ) {}
    CUSTOM_KEYS.forEach( k => {
      if ( stored[ k ] ) data.append( k, stored[ k ] );
    } );
  }

  /* ── iOS date input fix ──
     Real iOS Safari shows a blank / oversized box for type="date".
     Keep it as type="text" always on iOS; swap to date only while the picker
     is open, then restore text with a readable display value.
     On every other platform (desktop, Android), leave the native date input
     alone — it works perfectly and the swap trick breaks Chrome's picker. */
  const IS_IOS = /iPad|iPhone|iPod/.test( navigator.userAgent ) && ! window.MSStream;
  function fixDateInputs() {
    if ( ! IS_IOS ) return;
    document.querySelectorAll( 'input[type="date"].as-input' ).forEach( inp => {
      inp.type = 'text';

      function showDisplay() {
        const raw = inp.dataset.dateVal || '';
        if ( raw ) {
          const d = new Date( raw + 'T12:00:00' );
          inp.value = d.toLocaleDateString( 'en-US', { month: 'short', day: 'numeric', year: 'numeric' } );
        } else {
          inp.value       = '';
          inp.placeholder = 'Optional — tap to select';
        }
      }

      showDisplay();

      inp.addEventListener( 'focus', () => {
        inp.type  = 'date';
        inp.value = inp.dataset.dateVal || '';
      } );

      inp.addEventListener( 'change', () => {
        inp.dataset.dateVal = inp.value; // store real ISO value
      } );

      inp.addEventListener( 'blur', () => {
        showDisplay();
        inp.type = 'text'; // switch back so it stays constrained
      } );
    } );
  }

  document.addEventListener( 'DOMContentLoaded', () => {
    fixDateInputs();
    document.querySelectorAll( '.as-wrapper[data-total]' ).forEach( wrap => new AsForm( wrap ) );
  } );

} )();
