( function () {
  'use strict';

  function initTabs() {
    const nav = document.querySelector( '.as-tab-nav' );
    if ( ! nav ) return;

    nav.addEventListener( 'click', e => {
      const btn = e.target.closest( '.as-tab-btn' );
      if ( ! btn ) return;
      const target = btn.dataset.tab;
      nav.querySelectorAll( '.as-tab-btn' ).forEach( b => b.classList.remove( 'active' ) );
      btn.classList.add( 'active' );
      document.querySelectorAll( '.as-tab-panel' ).forEach( p => {
        p.classList.toggle( 'active', p.dataset.panel === target );
      } );
      history.replaceState( null, '', '#' + target );
    } );

    const hash = location.hash.replace( '#', '' );
    if ( hash ) {
      const restore = nav.querySelector( `[data-tab="${ hash }"]` );
      if ( restore ) restore.click();
    }
  }

  function initTestConnection() {
    const btn = document.getElementById( 'as-test-conn-btn' );
    if ( ! btn ) return;

    btn.addEventListener( 'click', () => {
      const apiKey     = document.getElementById( 'as_ghl_api_key' )?.value.trim();
      const locationId = document.getElementById( 'as_ghl_location_id' )?.value.trim();
      const resultEl   = document.getElementById( 'as-test-result' );

      if ( ! apiKey || ! locationId ) {
        showResult( resultEl, 'error', 'Please enter both your API Key and Location ID first.' );
        return;
      }

      btn.disabled    = true;
      btn.textContent = 'Testing…';

      const data = new FormData();
      data.append( 'action',      'as_test_connection' );
      data.append( 'nonce',       asAdmin.nonce );
      data.append( 'api_key',     apiKey );
      data.append( 'location_id', locationId );

      fetch( asAdmin.ajaxUrl, { method: 'POST', body: data } )
        .then( r => r.json() )
        .then( res => {
          if ( res.success ) {
            showResult( resultEl, 'success', '✓ Connected! Location: ' + ( res.data.name || locationId ) );
          } else {
            showResult( resultEl, 'error', '✗ ' + ( res.data?.message || 'Connection failed.' ) );
          }
        } )
        .catch( () => showResult( resultEl, 'error', '✗ Network error.' ) )
        .finally( () => {
          btn.disabled    = false;
          btn.textContent = 'Test Connection';
        } );
    } );
  }

  function showResult( el, type, msg ) {
    if ( ! el ) return;
    el.textContent   = msg;
    el.className     = 'as-test-result as-result-' + type;
    el.style.display = 'block';
  }

  function initCopyButtons() {
    document.querySelectorAll( '.as-copy-btn' ).forEach( btn => {
      btn.addEventListener( 'click', () => {
        const code = btn.dataset.code;
        if ( ! code ) return;
        navigator.clipboard.writeText( code ).then( () => {
          const orig = btn.textContent;
          btn.textContent = 'Copied!';
          btn.classList.add( 'copied' );
          setTimeout( () => { btn.textContent = orig; btn.classList.remove( 'copied' ); }, 1800 );
        } ).catch( () => {
          const ta = document.createElement( 'textarea' );
          ta.value = code;
          ta.style.cssText = 'position:fixed;opacity:0';
          document.body.appendChild( ta );
          ta.select();
          document.execCommand( 'copy' );
          document.body.removeChild( ta );
          btn.textContent = 'Copied!';
          setTimeout( () => { btn.textContent = 'Copy'; }, 1800 );
        } );
      } );
    } );
  }

  document.addEventListener( 'DOMContentLoaded', () => {
    initTabs();
    initTestConnection();
    initCopyButtons();
  } );

} )();
