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

  function initFetchFields() {
    const btn = document.getElementById( 'as-fetch-fields-btn' );
    if ( ! btn ) return;

    btn.addEventListener( 'click', () => {
      const apiKey     = document.getElementById( 'as_ghl_api_key' )?.value.trim();
      const locationId = document.getElementById( 'as_ghl_location_id' )?.value.trim();
      const resultEl   = document.getElementById( 'as-fields-result' );

      if ( ! apiKey || ! locationId ) {
        showResult( resultEl, 'error', 'Save your API Key and Location ID first.' );
        return;
      }

      btn.disabled    = true;
      btn.textContent = 'Fetching…';

      const data = new FormData();
      data.append( 'action',      'as_fetch_fields' );
      data.append( 'nonce',       asAdmin.nonce );
      data.append( 'api_key',     apiKey );
      data.append( 'location_id', locationId );

      fetch( asAdmin.ajaxUrl, { method: 'POST', body: data } )
        .then( r => r.json() )
        .then( res => {
          if ( res.success && res.data.fields.length ) {
            let html = '<table style="width:100%;border-collapse:collapse;font-size:12px;margin-top:6px">';
            html += '<tr style="background:#f0f0f0"><th style="padding:6px 8px;text-align:left;border:1px solid #ddd">Field Name</th><th style="padding:6px 8px;text-align:left;border:1px solid #ddd">Key (use this)</th><th style="padding:6px 8px;text-align:left;border:1px solid #ddd">UUID</th></tr>';
            res.data.fields.forEach( f => {
              html += `<tr>
                <td style="padding:5px 8px;border:1px solid #ddd">${ f.name }</td>
                <td style="padding:5px 8px;border:1px solid #ddd"><code style="background:#fff8e1;padding:2px 4px">${ f.key }</code></td>
                <td style="padding:5px 8px;border:1px solid #ddd"><code style="font-size:10px">${ f.id }</code></td>
              </tr>`;
            } );
            html += '</table>';
            resultEl.innerHTML = html;
            resultEl.className     = 'as-test-result';
            resultEl.style.display = 'block';
          } else {
            showResult( resultEl, 'error', res.data?.message || 'No fields found.' );
          }
        } )
        .catch( () => showResult( resultEl, 'error', 'Network error.' ) )
        .finally( () => {
          btn.disabled    = false;
          btn.textContent = 'Fetch Fields from GHL';
        } );
    } );
  }

  document.addEventListener( 'DOMContentLoaded', () => {
    initTabs();
    initTestConnection();
    initFetchFields();
    initCopyButtons();
  } );

} )();
