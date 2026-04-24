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

    /* ── Submit ── */
    submit() {
      if ( ! this._validate() ) return;

      const btn = this.wrap.querySelector( '.as-btn-submit' );
      if ( btn ) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending…';
      }

      const data = new FormData();
      data.append( 'action', 'as_submit' );
      data.append( 'nonce',  asData.nonce );

      this.wrap.querySelectorAll( 'input, select, textarea' ).forEach( el => {
        if ( ! el.name ) return;
        if ( el.type === 'radio' || el.type === 'checkbox' ) {
          if ( el.checked ) data.set( el.name, el.value );
        } else {
          data.set( el.name, el.value );
        }
      } );

      appendUtms( data );

      fetch( asData.ajaxUrl, { method: 'POST', body: data } )
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

  /* ── UTM capture ── */
  const UTM_KEYS = [ 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ];

  function captureUtms() {
    const params = new URLSearchParams( window.location.search );
    UTM_KEYS.forEach( k => {
      const v = params.get( k );
      if ( v ) sessionStorage.setItem( 'as_' + k, v );
    } );
  }

  function appendUtms( data ) {
    UTM_KEYS.forEach( k => {
      const v = sessionStorage.getItem( 'as_' + k );
      if ( v ) data.append( k, v );
    } );
  }

  document.addEventListener( 'DOMContentLoaded', () => {
    captureUtms();
    document.querySelectorAll( '.as-wrapper[data-total]' ).forEach( wrap => new AsForm( wrap ) );
  } );

} )();
