<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Save settings ─────────────────────────────────────────── */
add_action( 'admin_post_as_save_settings', 'as_save_settings' );
function as_save_settings(): void {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
    check_admin_referer( 'as_settings_save' );

    $text_options = [
        'as_ghl_api_key', 'as_ghl_location_id',
        'as_cf_move_type', 'as_cf_pickup_date', 'as_cf_from_city',
        'as_cf_to_city',   'as_cf_vehicle_type', 'as_cf_vehicle_status',
        'as_cf_utm_source', 'as_cf_utm_medium', 'as_cf_utm_campaign',
        'as_cf_utm_term',   'as_cf_utm_content',
    ];

    foreach ( $text_options as $opt ) {
        update_option( $opt, sanitize_text_field( $_POST[ $opt ] ?? '' ) );
    }

    wp_redirect( admin_url( 'admin.php?page=as-settings&saved=1' ) );
    exit;
}

/* ── Settings page render ───────────────────────────────────── */
function as_render_settings_page(): void {
    $saved = isset( $_GET['saved'] );
    ?>
    <div class="wrap as-admin">

        <h1 class="as-admin-title">
            <img src="https://autoshippers.ca/wp-content/uploads/2024/10/Favicon-1.png" alt="AutoShippers" style="height:36px;vertical-align:middle;margin-right:10px;object-fit:contain">
            AutoShippers Forms
        </h1>

        <?php if ( $saved ) : ?>
            <div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="as-tab-nav">
            <button class="as-tab-btn active" data-tab="connection">GHL Connection</button>
            <button class="as-tab-btn" data-tab="fields">Custom Fields</button>
            <button class="as-tab-btn" data-tab="shortcodes">Shortcode</button>
        </div>

        <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
            <?php wp_nonce_field( 'as_settings_save' ); ?>
            <input type="hidden" name="action" value="as_save_settings">

            <!-- ── Tab: Connection ── -->
            <div class="as-tab-panel active" data-panel="connection">
                <div class="as-section">
                    <h2>GoHighLevel Connection</h2>

                    <div class="as-info-box">
                        <span class="dashicons dashicons-info-outline"></span>
                        <span>Enter your GHL Location ID and Private API Key. Click <strong>Test Connection</strong> to verify before saving.</span>
                    </div>

                    <div class="as-field-group">
                        <label for="as_ghl_location_id">Location ID</label>
                        <input type="text" id="as_ghl_location_id" name="as_ghl_location_id"
                               value="<?php echo esc_attr( get_option( 'as_ghl_location_id', '' ) ); ?>"
                               placeholder="xxxxxxxxxxxxxxxxxxxxxxxx">
                        <p class="as-field-desc">Found in GHL → Settings → Business Profile.</p>
                    </div>

                    <div class="as-field-group">
                        <label for="as_ghl_api_key">Private API Key</label>
                        <input type="password" id="as_ghl_api_key" name="as_ghl_api_key"
                               value="<?php echo esc_attr( get_option( 'as_ghl_api_key', '' ) ); ?>"
                               placeholder="eyJ…">
                        <p class="as-field-desc">Found in GHL → Settings → Integrations → Private Integrations.</p>
                    </div>

                    <div class="as-test-box">
                        <button type="button" id="as-test-conn-btn">Test Connection</button>
                        <div id="as-test-result" class="as-test-result"></div>
                    </div>
                </div>

                <div class="as-save-row">
                    <?php submit_button( 'Save Settings', 'primary', 'submit', false ); ?>
                </div>
            </div>

            <!-- ── Tab: Custom Fields ── -->
            <div class="as-tab-panel" data-panel="fields">
                <div class="as-section">
                    <h2>Custom Field IDs</h2>
                    <p style="margin-bottom:18px;font-size:13px;color:#555">
                        Paste the GHL custom field UUIDs below. Leave blank to skip a field.
                        See the <a href="<?php echo admin_url( 'admin.php?page=as-instructions' ); ?>">Instructions page</a> for how to find these IDs.
                    </p>

                    <div class="as-test-box" style="margin-bottom:18px">
                        <button type="button" id="as-fetch-fields-btn">Fetch Fields from GHL</button>
                        <div id="as-fields-result" class="as-test-result" style="margin-top:10px"></div>
                    </div>

                    <h3>Shipping Details</h3>
                    <table class="as-cf-table widefat">
                        <thead><tr><th style="width:220px">Field</th><th>GHL Custom Field ID</th></tr></thead>
                        <tbody>
                            <?php
                            $shipping_fields = [
                                'as_cf_move_type'   => 'Move Type',
                                'as_cf_pickup_date' => 'Pickup Date',
                                'as_cf_from_city'   => 'From City',
                                'as_cf_to_city'     => 'To City',
                            ];
                            foreach ( $shipping_fields as $opt => $label ) :
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html( $label ); ?></strong></td>
                                <td>
                                    <input type="text" class="as-cf-id-input" name="<?php echo esc_attr( $opt ); ?>"
                                           value="<?php echo esc_attr( get_option( $opt, '' ) ); ?>"
                                           placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <h3 style="margin-top:20px">Vehicle Details</h3>
                    <table class="as-cf-table widefat">
                        <thead><tr><th style="width:220px">Field</th><th>GHL Custom Field ID</th></tr></thead>
                        <tbody>
                            <?php
                            $vehicle_fields = [
                                'as_cf_vehicle_type'   => 'Vehicle Type',
                                'as_cf_vehicle_status' => 'Vehicle Status',
                            ];
                            foreach ( $vehicle_fields as $opt => $label ) :
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html( $label ); ?></strong></td>
                                <td>
                                    <input type="text" class="as-cf-id-input" name="<?php echo esc_attr( $opt ); ?>"
                                           value="<?php echo esc_attr( get_option( $opt, '' ) ); ?>"
                                           placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <h3 style="margin-top:20px">UTM Tracking</h3>
                    <table class="as-cf-table widefat">
                        <thead><tr><th style="width:220px">UTM Parameter</th><th>GHL Custom Field ID</th></tr></thead>
                        <tbody>
                            <?php
                            $utm_fields = [
                                'as_cf_utm_source'   => 'UTM Source',
                                'as_cf_utm_medium'   => 'UTM Medium',
                                'as_cf_utm_campaign' => 'UTM Campaign',
                                'as_cf_utm_term'     => 'UTM Term',
                                'as_cf_utm_content'  => 'UTM Content',
                            ];
                            foreach ( $utm_fields as $opt => $label ) : ?>
                            <tr>
                                <td><strong><?php echo esc_html( $label ); ?></strong></td>
                                <td>
                                    <input type="text" class="as-cf-id-input" name="<?php echo esc_attr( $opt ); ?>"
                                           value="<?php echo esc_attr( get_option( $opt, '' ) ); ?>"
                                           placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="as-save-row">
                    <?php submit_button( 'Save Settings', 'primary', 'submit', false ); ?>
                </div>
            </div>

            <!-- ── Tab: Shortcode ── -->
            <div class="as-tab-panel" data-panel="shortcodes">
                <div class="as-section">
                    <h2>Shortcode</h2>
                    <p style="font-size:13px;color:#555;margin-bottom:18px">
                        Paste this shortcode into any WordPress page or post to display the vehicle shipping quote form.
                    </p>
                    <div class="as-shortcode-cards">
                        <div class="as-shortcode-card">
                            <h4>Vehicle Shipping Quote</h4>
                            <code>[as_vehicle_quote]</code>
                            <button type="button" class="as-copy-btn" data-code="[as_vehicle_quote]">Copy</button>
                        </div>
                    </div>
                </div>
            </div>

        </form>
    </div>
    <?php
}
