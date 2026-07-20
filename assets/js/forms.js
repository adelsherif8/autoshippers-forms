( function () {
  'use strict';

  /* ── Analytics event tracker ──
     Fires view/start/step/complete events to the as_track AJAX endpoint.
     Deduped per session_id so refreshes don't double-count views/starts. */
  var _asSid = ( function () {
    try {
      var id = sessionStorage.getItem( 'as_sid' );
      if ( ! id ) { id = Math.random().toString( 36 ).slice( 2 ) + Date.now().toString( 36 ); sessionStorage.setItem( 'as_sid', id ); }
      return id;
    } catch ( e ) { return Math.random().toString( 36 ).slice( 2 ) + Date.now().toString( 36 ); }
  } )();
  function asTrack( ev, sk ) {
    try {
      var fd = new FormData();
      fd.append( 'action',     'as_track' );
      fd.append( 'event_type', ev );
      fd.append( 'step_key',   sk || '' );
      fd.append( 'session_id', _asSid );
      fetch( asData.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' } ).catch( function () {} );
    } catch ( e ) {}
  }

  /* Step number → step_key used by the analytics funnel */
  var AS_STEP_KEYS = { 1: 'shipping', 2: 'vehicle', 3: 'contact' };

  class AsForm {
    constructor( wrap ) {
      this.wrap     = wrap;
      this.total    = parseInt( wrap.dataset.total, 10 );
      this.current  = 1;
      this.uid      = wrap.id;

      this.progFill = wrap.querySelector( '.as-prog-fill' );
      this.tabs     = wrap.querySelectorAll( '.as-tab' );
      this.stepTabs = wrap.querySelector( '.as-step-tabs' );
      this.trust    = wrap.querySelector( '.as-trust' );

      this._bind();
      this._refresh();
      this._initPhone();

      /* Fire 'view' once per session (page load / first visit to the form) */
      try { if ( ! sessionStorage.getItem( 'as_v' ) ) { sessionStorage.setItem( 'as_v', '1' ); asTrack( 'view', '' ); } } catch ( e ) { asTrack( 'view', '' ); }

      /* Fire 'start' once per session on the first real interaction, plus the
         step event for the step the visitor is engaging with. */
      const firstStep = () => {
        try {
          if ( ! sessionStorage.getItem( 'as_s' ) ) {
            sessionStorage.setItem( 'as_s', '1' );
            asTrack( 'start', AS_STEP_KEYS[ 1 ] );
          }
        } catch ( e ) { asTrack( 'start', AS_STEP_KEYS[ 1 ] ); }
        asTrack( 'step', AS_STEP_KEYS[ 1 ] );
        this.wrap.removeEventListener( 'focusin', firstStep );
        this.wrap.removeEventListener( 'click',   firstStep );
        this.wrap.removeEventListener( 'change',  firstStep );
      };
      this.wrap.addEventListener( 'focusin', firstStep );
      this.wrap.addEventListener( 'click',   firstStep );
      this.wrap.addEventListener( 'change',  firstStep );
    }

    /* Initialise intl-tel-input on the phone field so users get a country
       flag dropdown and the number is auto-formatted per country. */
    _initPhone() {
      const phoneEl = this.wrap.querySelector( 'input[name="phone"]' );
      /* asIntlTelInput is our bundled copy. Never fall back to the shared
         window.intlTelInput global: other plugins (requestquote) load their own
         older version there, and mixing its JS with our CSS/sprite renders the
         wrong flags and breaks the dropdown. */
      const iti = window.asIntlTelInput;
      if ( ! phoneEl || ! iti ) return;
      this.iti = iti( phoneEl, {
        initialCountry:     'ca',
        preferredCountries: [ 'ca', 'us' ],
        separateDialCode:   true,
        /* keep the dropdown inside .as-wrapper so our namespaced CSS styles it
           and foreign intl-tel-input stylesheets can't reach it */
        useFullscreenPopup: false,
        utilsScript:        ( window.asData && asData.itiUtils ) || '',
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
        asTrack( 'step', AS_STEP_KEYS[ this.current ] || '' );
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
      const step = this._step();
      if ( ! step ) return true;
      this._clearError();

      /* required text/email/tel/date — skip conditionals that are hidden right
         now (the "Other" city boxes), they only count once revealed. */
      for ( const inp of step.querySelectorAll( 'input[required], select[required], textarea[required]' ) ) {
        if ( inp.offsetParent === null ) continue;
        if ( ! inp.value.trim() ) {
          this._showError( 'Please fill in all required fields.', inp );
          return false;
        }
      }

      /* Phone: required, and still checked when intl-tel-input failed to load
         (blocked CDN) — otherwise leads reach the CRM with no phone number. */
      const phoneEl = step.querySelector( 'input[name="phone"]' );
      if ( phoneEl ) {
        const raw = phoneEl.value.trim();
        if ( ! raw ) {
          this._showError( 'Please enter your phone number.', phoneEl );
          return false;
        }
        const valid = this.iti
          ? this.iti.isValidNumber()
          : raw.replace( /\D/g, '' ).length >= 10;
        if ( ! valid ) {
          this._showError( 'Please enter a valid phone number for the selected country.', phoneEl );
          return false;
        }
      }

      /* required radio groups */
      const radioGroups = new Set(
        [ ...step.querySelectorAll( 'input[type="radio"]' ) ].map( r => r.name )
      );
      for ( const name of radioGroups ) {
        if ( ! step.querySelector( `input[name="${ name }"]:checked` ) ) {
          this._showError( 'Please make a selection before continuing.', step.querySelector( `input[name="${ name }"]` ) );
          return false;
        }
      }

      /* required selects (From / To city) */
      for ( const sel of step.querySelectorAll( 'select.as-select' ) ) {
        if ( ! sel.value ) {
          this._showError( 'Please select a city for both From and To.', sel );
          return false;
        }
      }

      return true;
    }

    _step() {
      return this.wrap.querySelector( `.as-step[data-step="${ this.current }"]` );
    }

    /* The banner has to live in the step the visitor is actually looking at. A
       single shared one sat inside step 3, so a failure on step 1 or 2 wrote into
       a hidden element — the Continue button just looked dead and people left. */
    _errorEl() {
      const step = this._step();
      if ( ! step ) return null;
      let el = step.querySelector( '.as-error-msg' );
      if ( ! el ) {
        el = document.createElement( 'div' );
        el.className = 'as-error-msg';
        const actions = step.querySelector( '.as-actions' );
        if ( actions ) actions.insertAdjacentElement( 'afterend', el );
        else step.appendChild( el );
      }
      return el;
    }

    /* Radios are visually hidden, so flag the card group the visitor can see. */
    _fieldToFlag( el ) {
      if ( ! el ) return null;
      if ( el.type === 'radio' ) {
        return el.closest( '.as-choices-row, .as-size-choices, .as-status-row' );
      }
      return el;
    }

    _showError( msg, field ) {
      const el = this._errorEl();
      if ( el ) {
        el.textContent = msg;
        el.style.display = 'block';
      }

      const flag = this._fieldToFlag( field );
      if ( flag ) {
        flag.classList.add( 'as-invalid' );
        flag.scrollIntoView( { behavior: 'smooth', block: 'center' } );
        if ( typeof field.focus === 'function' && field.type !== 'radio' ) {
          field.focus( { preventScroll: true } );
        }
      }
    }

    _clearError() {
      const step = this._step();
      if ( ! step ) return;
      const el = step.querySelector( '.as-error-msg' );
      if ( el ) {
        el.textContent   = '';
        el.style.display = 'none';
      }
      step.querySelectorAll( '.as-invalid' ).forEach( n => n.classList.remove( 'as-invalid' ) );
    }

    /* Normalise to E.164 (+14165550100) so GHL always gets a dialable number.
       intl-tel-input gives us that directly; if it never loaded, rebuild it from
       the digits and assume +1 for a bare 10-digit North American number. */
    _e164( value ) {
      if ( this.iti ) {
        const intl = this.iti.getNumber();
        if ( intl ) return intl;
      }
      const raw    = ( value || '' ).trim();
      const digits = raw.replace( /\D/g, '' );
      if ( ! digits ) return '';
      if ( raw.startsWith( '+' ) ) return '+' + digits;
      if ( digits.length === 10 )  return '+1' + digits;
      if ( digits.length === 11 && digits.charAt( 0 ) === '1' ) return '+' + digits;
      return raw;
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
          } else if ( el.name === 'phone' ) {
            data.set( 'phone', this._e164( el.value ) );
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
            asTrack( 'complete', '' );
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

      /* Drop the warning as soon as they put it right, so it never nags. */
      const forgive = e => {
        const el = e.target;
        if ( ! el.name ) return;
        const flagged = this._fieldToFlag( el );
        if ( flagged && flagged.classList.contains( 'as-invalid' ) ) this._clearError();
      };
      this.wrap.addEventListener( 'input',  forgive );
      this.wrap.addEventListener( 'change', forgive );

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

  /* On desktop/Android, open the browser's native calendar as soon as the
     user clicks anywhere on the date input — not only on the tiny calendar
     icon. Uses HTMLInputElement.showPicker() (Chrome 99+, FF 101+, Safari 16.4+). */
  function makeDateFieldsClickable() {
    document.querySelectorAll( 'input[type="date"].as-input' ).forEach( inp => {
      const openPicker = e => {
        if ( typeof inp.showPicker !== 'function' ) return;
        try { inp.showPicker(); } catch ( err ) {}
      };
      inp.addEventListener( 'click', openPicker );
      inp.addEventListener( 'focus', openPicker );
    } );
  }

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
    makeDateFieldsClickable();
    document.querySelectorAll( '.as-wrapper[data-total]' ).forEach( wrap => new AsForm( wrap ) );
  } );

} )();
