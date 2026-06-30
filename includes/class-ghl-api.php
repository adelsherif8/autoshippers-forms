<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AS_GHL_API {

    const BASE    = 'https://services.leadconnectorhq.com/';
    const VERSION = '2021-07-28';

    private string $api_key;
    private string $location_id;

    public function __construct( string $api_key = '', string $location_id = '' ) {
        $this->api_key     = $api_key     ?: (string) get_option( 'as_ghl_api_key', '' );
        $this->location_id = $location_id ?: (string) get_option( 'as_ghl_location_id', '' );
    }

    /* Verify credentials by fetching the location */
    public function test_connection(): array {
        $response = wp_remote_get(
            self::BASE . 'locations/' . rawurlencode( $this->location_id ),
            [ 'headers' => $this->headers(), 'timeout' => 15 ]
        );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'message' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 200 ) {
            return [ 'success' => true, 'name' => $body['location']['name'] ?? $this->location_id ];
        }

        $msg = $body['message'] ?? "HTTP $code";
        return [ 'success' => false, 'message' => $msg ];
    }

    /* Create or update a contact in GHL */
    public function upsert_contact( array $payload ): array {
        $payload['locationId'] = $this->location_id;
        $raw_body              = wp_json_encode( $payload );

        $response = wp_remote_post(
            self::BASE . 'contacts/upsert',
            [
                'headers' => array_merge( $this->headers(), [ 'Content-Type' => 'application/json' ] ),
                'body'    => $raw_body,
                'timeout' => 20,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return [
                'success'  => false,
                'message'  => $response->get_error_message(),
                'request'  => $raw_body,
                'response' => '',
                'http'     => 0,
            ];
        }

        $code     = wp_remote_retrieve_response_code( $response );
        $raw_resp = wp_remote_retrieve_body( $response );
        $body     = json_decode( $raw_resp, true );

        $base = [ 'request' => $raw_body, 'response' => $raw_resp, 'http' => $code ];

        if ( in_array( $code, [ 200, 201 ], true ) ) {
            return array_merge( $base, [ 'success' => true, 'contact' => $body['contact'] ?? [], 'message' => 'OK' ] );
        }

        $msg = $body['message'] ?? "HTTP $code";
        return array_merge( $base, [ 'success' => false, 'message' => $msg ] );
    }

    /* Build the customFields array. Skip rows where the user pasted a field
       key (contact.foo) instead of a UUID — GHL only accepts ids here. */
    public static function build_custom_fields_v2( array $map ): array {
        $fields = [];
        foreach ( $map as $option_key => $value ) {
            $stored = trim( (string) get_option( $option_key, '' ) );
            if ( $stored === '' || $value === '' || $value === null ) continue;
            if ( strpos( $stored, '.' ) !== false || strpos( $stored, '{{' ) !== false ) continue;
            $fields[] = [ 'id' => $stored, 'value' => (string) $value ];
        }
        return $fields;
    }

    /* Build the customFields array from saved option IDs and submitted values */
    public static function build_custom_fields( array $map ): array {
        $fields = [];
        foreach ( $map as $option_key => $value ) {
            $id = (string) get_option( $option_key, '' );
            if ( $id !== '' && $value !== '' && $value !== null ) {
                $fields[] = [ 'id' => $id, 'value' => (string) $value ];
            }
        }
        return $fields;
    }

    private function headers(): array {
        return [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Version'       => self::VERSION,
        ];
    }
}
