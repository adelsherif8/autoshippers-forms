<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ═══════════════════════════════════════════════════════════════
   AUTO-PROVISION GHL CUSTOM FIELDS — same pattern as
   sleep-apnea-ghl / contact-form-ghl: runs once per version flag on
   admin_init, creates any missing folders + fields in the subaccount
   and force-moves fields into the right folder. Skips silently while
   credentials are missing so it retries on the next admin visit.
   ═══════════════════════════════════════════════════════════════ */

function as_ghl_clean_key( $api_key ) {
    $k = trim( (string) $api_key );
    if ( stripos( $k, 'Bearer ' ) === 0 ) $k = trim( substr( $k, 7 ) );
    return $k;
}

function as_ghl_prov_headers( $api_key ) {
    return [
        'Authorization' => 'Bearer ' . as_ghl_clean_key( $api_key ),
        'Content-Type'  => 'application/json',
        'Version'       => '2021-07-28',
    ];
}

function as_ghl_prov_base() { return 'https://services.leadconnectorhq.com'; }

/* Field definitions grouped by folder. 'option' is the plugin setting slot
   that stores the GHL field UUID — auto-filled when the field is found or
   created, because this plugin sends custom fields by UUID. */
function as_ghl_field_definitions() {
    $form_fields = [
        [ 'name' => 'Move Type',        'key' => 'move_type',        'option' => 'as_cf_move_type' ],
        [ 'name' => 'Pickup Date',      'key' => 'pickup_date',      'option' => 'as_cf_pickup_date' ],
        [ 'name' => 'From City',        'key' => 'from_city',        'option' => 'as_cf_from_city' ],
        [ 'name' => 'To City',          'key' => 'to_city',          'option' => 'as_cf_to_city' ],
        [ 'name' => 'Vehicle Type',     'key' => 'vehicle_type',     'option' => 'as_cf_vehicle_type' ],
        [ 'name' => 'Vehicle Status',   'key' => 'vehicle_status',   'option' => 'as_cf_vehicle_status' ],
        [ 'name' => 'Latest Form Date', 'key' => 'latest_form_date', 'option' => 'as_cf_latest_form_date' ],
    ];
    $utm_fields = [
        [ 'name' => 'UTMCampaign_Custom', 'key' => 'utmcampaign_custom', 'option' => 'as_cf_utm_campaign' ],
        [ 'name' => 'UTMmedium_custom',   'key' => 'utmmedium_custom',   'option' => 'as_cf_utm_medium' ],
        [ 'name' => 'UTMContent_custom',  'key' => 'utmcontent_custom',  'option' => 'as_cf_utm_content' ],
        [ 'name' => 'utmkeyword_custom',  'key' => 'utmkeyword_custom',  'option' => 'as_cf_utm_keyword' ],
        [ 'name' => 'utmterm_custom',     'key' => 'utmterm_custom',     'option' => 'as_cf_utm_term' ],
        [ 'name' => 'gclid_custom',       'key' => 'gclid_custom',       'option' => 'as_cf_gclid' ],
    ];
    return [
        'AutoShippers Form' => $form_fields,
        'UTM Forms'         => $utm_fields,
    ];
}

function as_folder_names() {
    return array_keys( as_ghl_field_definitions() );
}

function as_get_folder_ids( $location_id ) {
    return get_option( 'as_folder_ids_' . md5( $location_id ), [] );
}

add_action( 'admin_init', 'as_auto_provision_all_fields' );
function as_auto_provision_all_fields() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( get_option( 'as_auto_provision_fields_v1' ) === '1' ) return;

    $api_key     = as_ghl_clean_key( (string) get_option( 'as_ghl_api_key', '' ) );
    $location_id = trim( (string) get_option( 'as_ghl_location_id', '' ) );
    if ( $api_key === '' || $location_id === '' ) return; // retry when credentials appear

    $headers = as_ghl_prov_headers( $api_key );
    $base    = as_ghl_prov_base();

    // 1) Fetch existing custom fields, indexed by bare fieldKey
    $r = wp_remote_get( "{$base}/locations/{$location_id}/customFields", [ 'headers' => $headers, 'timeout' => 15 ] );
    if ( is_wp_error( $r ) ) return;
    $code = wp_remote_retrieve_response_code( $r );
    if ( $code === 401 || $code === 403 ) { update_option( 'as_auto_provision_fields_v1', '1' ); return; } // bad token — don't spam
    if ( $code < 200 || $code >= 300 ) return;
    $existing = [];
    foreach ( json_decode( wp_remote_retrieve_body( $r ), true )['customFields'] ?? [] as $f ) {
        if ( ! empty( $f['fieldKey'] ) ) {
            $bare = strtolower( preg_replace( '/^contact\./', '', $f['fieldKey'] ) );
            $existing[ $bare ] = $f;
        }
    }

    // 2) Fetch (or create) each required folder
    $folder_names = as_folder_names();
    $fr = wp_remote_get( "{$base}/locations/{$location_id}/customFieldsFolders", [ 'headers' => $headers, 'timeout' => 15 ] );
    $folder_ids = [];
    if ( ! is_wp_error( $fr ) && wp_remote_retrieve_response_code( $fr ) < 300 ) {
        foreach ( json_decode( wp_remote_retrieve_body( $fr ), true )['folders'] ?? [] as $folder ) {
            $folder_ids[ strtolower( trim( $folder['name'] ?? '' ) ) ] = $folder['id'];
        }
    }
    foreach ( $folder_names as $fname ) {
        $key = strtolower( $fname );
        if ( isset( $folder_ids[ $key ] ) ) continue;
        $cr = wp_remote_post( "{$base}/locations/{$location_id}/customFieldsFolders", [
            'headers' => $headers,
            'body'    => wp_json_encode( [ 'name' => $fname ] ),
            'timeout' => 15,
        ] );
        if ( ! is_wp_error( $cr ) && wp_remote_retrieve_response_code( $cr ) < 300 ) {
            $cb  = json_decode( wp_remote_retrieve_body( $cr ), true );
            $fid = $cb['folder']['id'] ?? $cb['id'] ?? null;
            if ( $fid ) $folder_ids[ $key ] = $fid;
        }
    }

    // 3) Create each missing field + force-move all fields into the right folder
    foreach ( as_ghl_field_definitions() as $group => $fields ) {
        $folder_id = $folder_ids[ strtolower( $group ) ] ?? null;
        foreach ( $fields as $def ) {
            $bare      = strtolower( $def['key'] );
            $field_row = $existing[ $bare ] ?? null;
            $field_id  = $field_row['id'] ?? null;
            if ( ! $field_row ) {
                // Create it
                $payload = [
                    'name'     => $def['name'],
                    'fieldKey' => $def['key'],
                    'dataType' => 'TEXT',
                    'position' => 0,
                ];
                if ( $folder_id ) $payload['parentId'] = $folder_id;
                $cr = wp_remote_post( "{$base}/locations/{$location_id}/customFields", [
                    'headers' => $headers,
                    'body'    => wp_json_encode( $payload ),
                    'timeout' => 15,
                ] );
                $ccode = is_wp_error( $cr ) ? 0 : wp_remote_retrieve_response_code( $cr );
                if ( $ccode >= 200 && $ccode < 300 ) {
                    $cb       = json_decode( wp_remote_retrieve_body( $cr ), true );
                    $field_id = $cb['customField']['id'] ?? $cb['id'] ?? null;
                }
            }
            // Force-move into the correct folder (GHL sometimes ignores parentId on create)
            if ( $field_id && $folder_id && ( ( $field_row['parentId'] ?? null ) !== $folder_id ) ) {
                wp_remote_request( "{$base}/locations/{$location_id}/customFields/{$field_id}", [
                    'method'  => 'PUT',
                    'headers' => $headers,
                    'body'    => wp_json_encode( [ 'parentId' => $folder_id ] ),
                    'timeout' => 15,
                ] );
            }
            // Fill the plugin's UUID slot so submissions map to this field
            if ( $field_id && ! empty( $def['option'] ) && trim( (string) get_option( $def['option'], '' ) ) === '' ) {
                update_option( $def['option'], sanitize_text_field( $field_id ) );
            }
        }
    }

    // Save resolved folder IDs so other tools work without re-detecting
    if ( ! empty( $folder_ids ) ) {
        $stored = as_get_folder_ids( $location_id );
        foreach ( $folder_names as $fname ) {
            $key = strtolower( $fname );
            if ( isset( $folder_ids[ $key ] ) ) $stored[ $fname ] = $folder_ids[ $key ];
        }
        update_option( 'as_folder_ids_' . md5( $location_id ), $stored );
    }

    update_option( 'as_auto_provision_fields_v1', '1' );
}
