<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AS_Form_Handler {

    public function __construct() {
        add_action( 'wp_ajax_as_submit',           [ $this, 'handle_submit' ] );
        add_action( 'wp_ajax_nopriv_as_submit',    [ $this, 'handle_submit' ] );
        add_action( 'wp_ajax_as_test_connection',  [ $this, 'handle_test_connection' ] );
        add_action( 'wp_ajax_as_fetch_fields',     [ $this, 'handle_fetch_fields' ] );
        /* Always-fresh nonce — bypass page caching */
        add_action( 'wp_ajax_as_get_nonce',        [ $this, 'handle_get_nonce' ] );
        add_action( 'wp_ajax_nopriv_as_get_nonce', [ $this, 'handle_get_nonce' ] );
        /* Auto-create UTM custom fields in GHL inside a chosen folder */
        add_action( 'wp_ajax_as_create_utm_fields', [ $this, 'handle_create_utm_fields' ] );
    }

    /* ── Auto-create the UTM custom fields in GHL ──
       Resolves the folder automatically (existing "UTM forms" folder or
       the folder containing existing utm fields, otherwise creates one),
       then creates each missing field inside it. */
    public function handle_create_utm_fields(): void {
        if ( ! check_ajax_referer( 'as_admin', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $api_key     = get_option( 'as_ghl_api_key', '' );
        $location_id = get_option( 'as_ghl_location_id', '' );

        if ( $api_key === '' || $location_id === '' ) {
            wp_send_json_error( [ 'message' => 'Save API Key and Location ID first.' ] );
        }

        /* Resolve folder ID: cached → discovered from existing fields → create new */
        $folder_id    = trim( (string) get_option( 'as_utm_folder_id', '' ) );
        $folder_notes = [];

        if ( $folder_id === '' ) {
            $folder_id = $this->discover_utm_folder( $api_key, $location_id, $folder_notes );
        }
        if ( $folder_id === '' ) {
            $folder_id = $this->create_utm_folder( $api_key, $location_id, $folder_notes );
        }
        if ( $folder_id === '' ) {
            wp_send_json_error( [ 'message' => 'Could not find or create a UTM folder. ' . implode( ' | ', $folder_notes ) ] );
        }
        update_option( 'as_utm_folder_id', $folder_id );

        /* Plugin slot → [ name shown in GHL, fieldKey ]
           These are the 6 canonical fields the scad_tracking_params script uses. */
        $defs = [
            'as_cf_utm_campaign' => [ 'UTMCampaign_Custom', 'utmcampaign_custom' ],
            'as_cf_utm_medium'   => [ 'UTMmedium_custom',   'utmmedium_custom'   ],
            'as_cf_utm_content'  => [ 'UTMContent_custom',  'utmcontent_custom'  ],
            'as_cf_utm_keyword'  => [ 'utmkeyword_custom',  'utmkeyword_custom'  ],
            'as_cf_utm_term'     => [ 'utmterm_custom',     'utmterm_custom'     ],
            'as_cf_gclid'        => [ 'gclid_custom',       'gclid_custom'       ],
        ];

        $created = [];
        $skipped = [];
        $failed  = [];
        $position = 0;

        foreach ( $defs as $option_key => [ $name, $field_key ] ) {
            $existing = trim( (string) get_option( $option_key, '' ) );
            if ( $existing !== '' ) {
                $skipped[] = $name;
                continue;
            }

            $resp = wp_remote_post(
                'https://services.leadconnectorhq.com/locations/' . rawurlencode( $location_id ) . '/customFields',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type'  => 'application/json',
                        'Version'       => '2021-07-28',
                    ],
                    'body'    => wp_json_encode( [
                        'name'        => $name,
                        'dataType'    => 'TEXT',
                        'fieldKey'    => $field_key,
                        'parentId'    => $folder_id,
                        'placeholder' => '',
                        'model'       => 'contact',
                        'position'    => $position++,
                        'showInForms' => true,
                    ] ),
                    'timeout' => 20,
                ]
            );

            if ( is_wp_error( $resp ) ) {
                $failed[] = "$name: " . $resp->get_error_message();
                continue;
            }
            $code = wp_remote_retrieve_response_code( $resp );
            $body = json_decode( wp_remote_retrieve_body( $resp ), true );

            if ( in_array( $code, [ 200, 201 ], true ) ) {
                $id = $body['customField']['id'] ?? $body['id'] ?? '';
                if ( $id ) {
                    update_option( $option_key, sanitize_text_field( $id ) );
                    $created[] = [ 'name' => $name, 'id' => $id, 'slot' => $option_key ];
                } else {
                    $failed[] = "$name: created but no id returned";
                }
            } else {
                $msg = $body['message'] ?? $body['msg'] ?? "HTTP $code";
                $failed[] = "$name: $msg";
            }
        }

        wp_send_json_success( [
            'created'   => $created,
            'skipped'   => $skipped,
            'failed'    => $failed,
            'folder_id' => $folder_id,
            'folder'    => $folder_notes,
        ] );
    }

    /* Try to discover an existing folder: pick the parentId of any field
       whose name or key contains "utm". Returns '' if none found. */
    private function discover_utm_folder( string $api_key, string $location_id, array &$notes ): string {
        $resp = wp_remote_get(
            'https://services.leadconnectorhq.com/locations/' . rawurlencode( $location_id ) . '/customFields?model=contact',
            [
                'headers' => [ 'Authorization' => 'Bearer ' . $api_key, 'Version' => '2021-07-28' ],
                'timeout' => 20,
            ]
        );
        if ( is_wp_error( $resp ) ) { $notes[] = 'discover: ' . $resp->get_error_message(); return ''; }
        $code = wp_remote_retrieve_response_code( $resp );
        $body = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( $code !== 200 ) { $notes[] = 'discover HTTP ' . $code; return ''; }

        foreach ( ( $body['customFields'] ?? [] ) as $f ) {
            $name = strtolower( $f['name']     ?? '' );
            $key  = strtolower( $f['fieldKey'] ?? '' );
            if ( ( strpos( $name, 'utm' ) !== false || strpos( $key, 'utm' ) !== false )
                 && ! empty( $f['parentId'] ) ) {
                $notes[] = 'discover: reused folder from "' . ( $f['name'] ?? '' ) . '"';
                return $f['parentId'];
            }
        }
        $notes[] = 'discover: no existing UTM field found';
        return '';
    }

    /* Create a new "UTM forms" folder via GHL API. */
    private function create_utm_folder( string $api_key, string $location_id, array &$notes ): string {
        $resp = wp_remote_post(
            'https://services.leadconnectorhq.com/locations/' . rawurlencode( $location_id ) . '/customFields/folder',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                    'Version'       => '2021-07-28',
                ],
                'body'    => wp_json_encode( [ 'name' => 'UTM forms', 'model' => 'contact' ] ),
                'timeout' => 20,
            ]
        );
        if ( is_wp_error( $resp ) ) { $notes[] = 'create folder: ' . $resp->get_error_message(); return ''; }
        $code = wp_remote_retrieve_response_code( $resp );
        $body = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( ! in_array( $code, [ 200, 201 ], true ) ) {
            $msg = $body['message'] ?? "HTTP $code";
            $notes[] = 'create folder: ' . $msg;
            return '';
        }
        $id = $body['folder']['id'] ?? $body['id'] ?? $body['customFieldFolder']['id'] ?? '';
        if ( $id ) $notes[] = 'created new folder "UTM forms"';
        return $id;
    }

    public function handle_get_nonce(): void {
        nocache_headers();
        wp_send_json_success( [ 'nonce' => wp_create_nonce( 'as_submit' ) ] );
    }

    /* ── Form submission ── */
    public function handle_submit(): void {
        if ( ! check_ajax_referer( 'as_submit', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'Security check failed.' ], 403 );
        }

        $p = $this->sanitize_post();

        /* Resolve "Other" city values */
        $from = $p['from_city'] === 'Other' && $p['from_other'] !== ''
            ? $p['from_other']
            : $p['from_city'];

        $to = $p['to_city'] === 'Other' && $p['to_other'] !== ''
            ? $p['to_other']
            : $p['to_city'];

        /* Build custom fields. UTMs come from the canonical scad_tracking_params
           keys (utmcampaign_custom, utmmedium_custom, …). We still write to the
           existing utm_medium / utm_campaign / utm_content / utm_keyword admin
           slots since the user already has them mapped. */
        $custom_fields = AS_GHL_API::build_custom_fields_v2( [
            'as_cf_move_type'         => $p['move_type'],
            'as_cf_pickup_date'       => $p['pickup_date'],
            'as_cf_from_city'         => $from,
            'as_cf_to_city'           => $to,
            'as_cf_vehicle_type'      => $p['vehicle_type'],
            'as_cf_vehicle_status'    => $p['vehicle_status'],
            'as_cf_utm_campaign' => $p['utmcampaign_custom'],
            'as_cf_utm_medium'   => $p['utmmedium_custom'],
            'as_cf_utm_content'  => $p['utmcontent_custom'],
            'as_cf_utm_keyword'  => $p['utmkeyword_custom'],
            'as_cf_utm_term'     => $p['utmterm_custom'],
            'as_cf_gclid'        => $p['gclid_custom'],
        ] );

        $payload = [
            'firstName' => $p['first_name'],
            'lastName'  => $p['last_name'],
            'email'     => $p['email'],
            'phone'     => $p['phone'],
            'tags'      => [ 'AutoShippers - Vehicle Quote' ],
        ];

        if ( ! empty( $custom_fields ) ) {
            $payload['customFields'] = $custom_fields;
        }

        /* Save entry locally before calling GHL so we never lose a lead */
        $entry_data = [
            'move_type'      => $p['move_type'],
            'pickup_date'    => $p['pickup_date'],
            'from_city'      => $from,
            'to_city'        => $to,
            'vehicle_type'   => $p['vehicle_type'],
            'vehicle_status' => $p['vehicle_status'],
        ];
        foreach ( [ 'utmcampaign_custom','utmmedium_custom','utmcontent_custom','utmkeyword_custom','utmterm_custom','gclid_custom' ] as $k ) {
            if ( ! empty( $p[ $k ] ) ) $entry_data[ $k ] = $p[ $k ];
        }

        $entry_id = AS_Entries::insert( [
            'first_name' => $p['first_name'],
            'last_name'  => $p['last_name'],
            'email'      => $p['email'],
            'phone'      => $p['phone'],
            'tag'        => $payload['tags'][0] ?? '',
            'data'       => $entry_data,
            'ip'         => $this->get_ip(),
        ] );

        $api    = new AS_GHL_API();
        $result = $api->upsert_contact( $payload );

        AS_Entries::update_ghl_status(
            $entry_id,
            $result['success'] ? 'sent' : 'failed',
            $result['message']  ?? '',
            $result['request']  ?? '',
            $result['response'] ?? '',
            intval( $result['http'] ?? 0 )
        );

        if ( $result['success'] ) {
            wp_send_json_success( [ 'message' => 'Quote request sent.' ] );
        } else {
            wp_send_json_error( [ 'message' => $result['message'] ?? 'Submission failed.' ] );
        }
    }

    /* ── Test connection ── */
    public function handle_test_connection(): void {
        if ( ! check_ajax_referer( 'as_admin', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $api_key     = sanitize_text_field( $_POST['api_key']     ?? '' );
        $location_id = sanitize_text_field( $_POST['location_id'] ?? '' );

        $api    = new AS_GHL_API( $api_key, $location_id );
        $result = $api->test_connection();

        if ( $result['success'] ) {
            wp_send_json_success( [ 'name' => $result['name'] ] );
        } else {
            wp_send_json_error( [ 'message' => $result['message'] ] );
        }
    }

    /* ── Fetch GHL custom fields ── */
    public function handle_fetch_fields(): void {
        if ( ! check_ajax_referer( 'as_admin', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $api_key     = sanitize_text_field( $_POST['api_key']     ?? '' );
        $location_id = sanitize_text_field( $_POST['location_id'] ?? '' );

        $response = wp_remote_get(
            'https://services.leadconnectorhq.com/locations/' . rawurlencode( $location_id ) . '/customFields',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Version'       => '2021-07-28',
                ],
                'timeout' => 15,
            ]
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => $response->get_error_message() ] );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            wp_send_json_error( [ 'message' => $body['message'] ?? "HTTP $code" ] );
        }

        $fields = array_map( fn( $f ) => [
            'name' => $f['name']     ?? '',
            'key'  => $f['fieldKey'] ?? '',
            'id'   => $f['id']       ?? '',
        ], $body['customFields'] ?? [] );

        wp_send_json_success( [ 'fields' => $fields ] );
    }

    /* ── Helpers ── */
    private function sanitize_post(): array {
        $keys = [
            'move_type', 'pickup_date', 'from_city', 'from_other',
            'to_city', 'to_other', 'vehicle_type', 'vehicle_status',
            'first_name', 'last_name', 'email', 'phone',
            'utmcampaign_custom', 'utmmedium_custom', 'utmcontent_custom',
            'utmkeyword_custom',  'utmterm_custom',    'gclid_custom',
        ];
        $out = [];
        foreach ( $keys as $k ) {
            $out[ $k ] = sanitize_text_field( $_POST[ $k ] ?? '' );
        }
        $out['email'] = sanitize_email( $_POST['email'] ?? '' );
        return $out;
    }

    private function get_ip(): string {
        foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                return sanitize_text_field( explode( ',', $_SERVER[ $key ] )[0] );
            }
        }
        return '0.0.0.0';
    }
}

new AS_Form_Handler();
