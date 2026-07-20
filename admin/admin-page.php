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
        'as_cf_utm_medium', 'as_cf_utm_campaign', 'as_cf_utm_content',
        'as_cf_utm_keyword', 'as_cf_utm_term',
        'as_cf_gclid', 'as_cf_latest_form_date',
        'as_utm_folder_id',
    ];

    foreach ( $text_options as $opt ) {
        update_option( $opt, sanitize_text_field( $_POST[ $opt ] ?? '' ) );
    }

    /* Marketing landing pages tracked into the analytics funnel (page IDs) */
    update_option( 'as_landing_pages', array_map( 'intval', (array) ( $_POST['as_landing_pages'] ?? [] ) ) );

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
            <button class="as-tab-btn" data-tab="analytics">Analytics</button>
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
                    <h3 style="margin-top:20px">UTM &amp; GCLID Tracking</h3>

                    <!-- Auto-create UTM custom fields in GHL -->
                    <div class="as-info-box" style="margin-bottom:14px;display:flex;align-items:flex-start;gap:10px;flex-wrap:wrap;background:#fff4ea;border:1px solid #fed7aa;padding:14px;border-radius:8px">
                        <span class="dashicons dashicons-admin-tools" style="color:#ee7a00;font-size:18px"></span>
                        <div style="flex:1;min-width:280px">
                            <strong>Auto-create UTM fields in GHL</strong>
                            <div style="font-size:12px;color:#555;margin-top:4px">
                                Paste the UUID of your "UTM forms" folder below, then click the button. The plugin will create every missing UTM custom field inside it and auto-fill the IDs.
                            </div>
                            <div style="font-size:11px;color:#78350f;margin-top:6px;background:#fef3c7;padding:8px 10px;border-radius:6px">
                                <strong>How to find the folder ID:</strong> In GHL go to <em>Settings → Custom Fields → Contact tab</em>, click on the <strong>UTM forms</strong> folder. Look at your browser URL — it becomes <code>…/settings/fields?…&amp;parentId=<strong>XXXX</strong>&amp;object=contact</code>. Copy that <strong>parentId</strong> value.
                            </div>
                            <div style="display:flex;gap:8px;align-items:center;margin-top:10px;flex-wrap:wrap">
                                <input type="text" id="as-utm-folder-id"
                                       value="<?php echo esc_attr( get_option( 'as_utm_folder_id', '' ) ); ?>"
                                       placeholder="Paste UTM folder UUID (e.g. qQnV7NMUJ5QujwklRTF1)"
                                       style="flex:1;min-width:260px">
                                <button type="button" id="as-create-utm-fields-btn" class="button button-primary">
                                    <span class="dashicons dashicons-admin-tools" style="vertical-align:middle;margin-right:2px"></span>
                                    Create UTM fields
                                </button>
                            </div>
                            <div id="as-create-utm-result" class="as-test-result" style="margin-top:10px;display:none"></div>
                        </div>
                    </div>

                    <table class="as-cf-table widefat">
                        <thead><tr><th style="width:220px">GHL Field Name</th><th>GHL Custom Field ID</th></tr></thead>
                        <tbody>
                            <?php
                            $utm_fields = [
                                'as_cf_utm_campaign' => 'UTMCampaign_Custom',
                                'as_cf_utm_medium'   => 'UTMmedium_custom',
                                'as_cf_utm_content'  => 'UTMContent_custom',
                                'as_cf_utm_keyword'  => 'utmkeyword_custom',
                                'as_cf_utm_term'     => 'utmterm_custom',
                                'as_cf_gclid'        => 'gclid_custom',
                                'as_cf_latest_form_date' => 'Latest Form Date',
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
            <!-- ── Tab: Analytics ── -->
            <div class="as-tab-panel" data-panel="analytics">
                <div class="as-section">
                    <h2>Analytics Tracking</h2>
                    <p style="margin-bottom:18px;font-size:13px;color:#555">
                        The quote form itself is tracked automatically (views, steps, submissions).
                        Below you can additionally track <strong>marketing landing pages</strong> —
                        pages that don't contain the form but funnel visitors toward it.
                    </p>

                    <div class="as-field-group">
                        <label>Landing Pages <span style="font-weight:400;color:#9ca3af;">— marketing pages that lead to the quote form</span></label>
                        <?php
                        $as_lp = array_map( 'intval', (array) get_option( 'as_landing_pages', [] ) );
                        $as_all_pages = get_pages( [ 'sort_column' => 'post_title', 'sort_order' => 'ASC' ] );
                        ?>
                        <select name="as_landing_pages[]" multiple size="8" style="width:100%;max-width:520px;padding:6px;border:1px solid #8c8f94;border-radius:4px;font-size:13px;">
                            <?php foreach ( $as_all_pages as $p ): ?>
                            <option value="<?php echo (int) $p->ID; ?>" <?php echo in_array( (int) $p->ID, $as_lp, true ) ? 'selected' : ''; ?>><?php echo esc_html( $p->post_title ); ?> (/<?php echo esc_html( $p->post_name ); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <p class="as-field-desc">Hold <kbd>Cmd</kbd>/<kbd>Ctrl</kbd> to select multiple. Visitors who land on these pages are tracked as a "Marketing page visit" row at the top of the Analytics funnel, so you can see how many people came through a landing page before reaching the form. Deselect all to disable.</p>
                    </div>
                </div>

                <div class="as-save-row">
                    <?php submit_button( 'Save Settings', 'primary', 'submit', false ); ?>
                </div>
            </div>

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
