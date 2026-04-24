<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AS_Form_Handler {

    public function __construct() {
        add_action( 'wp_ajax_as_submit',           [ $this, 'handle_submit' ] );
        add_action( 'wp_ajax_nopriv_as_submit',    [ $this, 'handle_submit' ] );
        add_action( 'wp_ajax_as_test_connection',  [ $this, 'handle_test_connection' ] );
        add_action( 'wp_ajax_as_fetch_fields',     [ $this, 'handle_fetch_fields' ] );
    }

    /* ── Form submission ──────────────────────────────────────── */
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

        /* Build custom fields */
        $utm_fields = [];
        $utm_map = [
            'UTMContent_custom'  => $p['utm_content'],
            'UTMCampaign_Custom' => $p['utm_campaign'],
            'UTMmedium_custom'   => $p['utm_medium'],
            'utm Keyword'        => $p['utm_term'],
            'utm Content'        => $p['utm_content'],
            'utm Campaign'       => $p['utm_campaign'],
        ];
        foreach ( $utm_map as $field_id => $value ) {
            if ( $value !== '' ) {
                $utm_fields[] = [ 'id' => sanitize_text_field( $field_id ), 'value' => sanitize_text_field( $value ) ];
            }
        }

        $custom_fields = array_merge(
            AS_GHL_API::build_custom_fields( [
                'as_cf_move_type'      => $p['move_type'],
                'as_cf_pickup_date'    => $p['pickup_date'],
                'as_cf_from_city'      => $from,
                'as_cf_to_city'        => $to,
                'as_cf_vehicle_type'   => $p['vehicle_type'],
                'as_cf_vehicle_status' => $p['vehicle_status'],
            ] ),
            $utm_fields
        );

        $payload = [
            'firstName'   => $p['first_name'],
            'lastName'    => $p['last_name'],
            'email'       => $p['email'],
            'phone'       => $p['phone'],
            'tags'        => [ 'AutoShippers - Vehicle Quote' ],
        ];

        if ( ! empty( $custom_fields ) ) {
            $payload['customFields'] = $custom_fields;
        }

        $api    = new AS_GHL_API();
        $result = $api->upsert_contact( $payload );

        if ( $result['success'] ) {
            wp_send_json_success( [ 'message' => 'Quote request sent.' ] );
        } else {
            wp_send_json_error( [ 'message' => $result['message'] ?? 'Submission failed.' ] );
        }
    }

    /* ── Test connection ─────────────────────────────────────── */
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

    /* ── Fetch GHL custom fields ─────────────────────────────── */
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

        $code   = wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            wp_send_json_error( [ 'message' => $body['message'] ?? "HTTP $code" ] );
        }

        $fields = array_map( fn( $f ) => [
            'name' => $f['name'] ?? '',
            'key'  => $f['fieldKey'] ?? '',
            'id'   => $f['id']       ?? '',
        ], $body['customFields'] ?? [] );

        wp_send_json_success( [ 'fields' => $fields ] );
    }

    /* ── Helpers ─────────────────────────────────────────────── */
    private function sanitize_post(): array {
        $keys = [
            'move_type', 'pickup_date', 'from_city', 'from_other',
            'to_city', 'to_other', 'vehicle_type', 'vehicle_status',
            'first_name', 'last_name', 'email', 'phone',
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        ];
        $out = [];
        foreach ( $keys as $k ) {
            $out[ $k ] = sanitize_text_field( $_POST[ $k ] ?? '' );
        }
        $out['email'] = sanitize_email( $_POST['email'] ?? '' );
        return $out;
    }
}

new AS_Form_Handler();
