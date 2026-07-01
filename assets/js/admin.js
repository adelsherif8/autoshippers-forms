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

  /* ── Create missing UTM fields in GHL ── */
  function initCreateUtmFields() {
    const btn = document.getElementById( 'as-create-utm-fields-btn' );
    if ( ! btn ) return;

    btn.addEventListener( 'click', () => {
      const resultEl = document.getElementById( 'as-create-utm-result' );
      const folderId = document.getElementById( 'as-utm-folder-id' )?.value.trim();

      if ( ! folderId ) {
        showResult( resultEl, 'error', '✗ Paste the UTM folder UUID first — see instructions above.' );
        return;
      }

      btn.disabled    = true;
      btn.innerHTML   = '<span class="dashicons dashicons-update" style="vertical-align:middle"></span> Working…';

      const data = new FormData();
      data.append( 'action',    'as_create_utm_fields' );
      data.append( 'nonce',     asAdmin.nonce );
      data.append( 'folder_id', folderId );

      fetch( asAdmin.ajaxUrl, { method: 'POST', body: data } )
        .then( r => r.json() )
        .then( res => {
          if ( ! res.success ) {
            showResult( resultEl, 'error', '✗ ' + ( res.data?.message || 'Failed.' ) );
            return;
          }
          const c = res.data.created || [];
          const s = res.data.skipped || [];
          const f = res.data.failed  || [];

          /* Auto-fill the matching UUID inputs */
          c.forEach( item => {
            const input = document.querySelector( 'input[name="' + item.slot + '"]' );
            if ( input ) {
              input.value = item.id;
              input.style.background = '#dcfce7';
            }
          } );

          let html = '';
          if ( c.length ) {
            html += '<div style="color:#166534;font-weight:600;margin-bottom:6px">✓ Created ' + c.length + ' field' + ( c.length !== 1 ? 's' : '' ) + ': ' + c.map( i => i.name ).join( ', ' ) + '</div>';
            html += '<div style="font-size:11px;color:#166534;margin-bottom:6px">Click <strong>Save Settings</strong> below to save the new UUIDs.</div>';
          }
          if ( s.length ) {
            html += '<div style="color:#6b7280;font-size:12px;margin-bottom:4px">↪ Skipped (already configured): ' + s.join( ', ' ) + '</div>';
          }
          if ( f.length ) {
            html += '<div style="color:#dc2626;font-size:12px"><strong>✗ Failed:</strong><br>' + f.join( '<br>' ) + '</div>';
          }
          if ( ! html ) html = 'Nothing to do.';

          resultEl.innerHTML    = html;
          resultEl.className    = 'as-test-result ' + ( f.length ? 'as-result-error' : 'as-result-success' );
          resultEl.style.display = 'block';
        } )
        .catch( () => showResult( resultEl, 'error', '✗ Network error.' ) )
        .finally( () => {
          btn.disabled    = false;
          btn.textContent = 'Create missing UTM fields';
        } );
    } );
  }

  document.addEventListener( 'DOMContentLoaded', () => {
    initTabs();
    initTestConnection();
    initFetchFields();
    initCopyButtons();
    initCreateUtmFields();
  } );

} )();
