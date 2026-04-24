<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function as_render_instructions_page(): void {
    ?>
    <div class="wrap as-admin as-instructions">
        <h1 class="as-admin-title">
            <img src="https://autoshippers.ca/wp-content/uploads/2024/10/Favicon-1.png" alt="AutoShippers" style="height:36px;vertical-align:middle;margin-right:10px;object-fit:contain">
            Setup Instructions
        </h1>

        <div class="as-instructions-grid">

            <!-- Sidebar TOC -->
            <aside class="as-toc">
                <h3>On this page</h3>
                <ul>
                    <li><a href="#step-1">1. Get GHL Credentials</a></li>
                    <li><a href="#step-2">2. Create Custom Fields in GHL</a></li>
                    <li><a href="#step-3">3. Add Field IDs to Plugin</a></li>
                    <li><a href="#step-4">4. Add Shortcode to Page</a></li>
                    <li><a href="#step-5">5. Test a Submission</a></li>
                    <li><a href="#troubleshooting">Troubleshooting</a></li>
                </ul>
            </aside>

            <main class="as-instructions-body">

                <!-- Step 1 -->
                <section id="step-1" class="as-instr-section">
                    <div class="as-instr-step-num">1</div>
                    <div class="as-instr-content">
                        <h2>Get Your GHL Credentials</h2>
                        <p>You need two things from GoHighLevel: your <strong>Location ID</strong> and a <strong>Private API Key</strong>.</p>
                        <ol>
                            <li>Log in to your GoHighLevel account and navigate to the correct sub-account.</li>
                            <li>Go to <strong>Settings → Business Profile</strong> and copy your <strong>Location ID</strong>.</li>
                            <li>Go to <strong>Settings → Integrations → Private Integrations</strong>.</li>
                            <li>Click <strong>+ Add Key</strong>, name it "AutoShippers Forms", and copy the generated key.</li>
                        </ol>
                        <div class="as-instr-note">
                            <span class="dashicons dashicons-lock"></span>
                            <span>Keep your API key private. It provides full access to your GHL location.</span>
                        </div>
                        <p>Paste both in the <a href="<?php echo admin_url( 'admin.php?page=as-settings&tab=connection' ); ?>">GHL Connection tab</a>, then click <strong>Test Connection</strong>.</p>
                    </div>
                </section>

                <!-- Step 2 -->
                <section id="step-2" class="as-instr-section">
                    <div class="as-instr-step-num">2</div>
                    <div class="as-instr-content">
                        <h2>Create Custom Fields in GoHighLevel</h2>
                        <p>Go to <strong>Settings → Custom Fields → Contacts</strong> and create each field below.</p>

                        <h3 class="as-cf-group-title"><span class="as-form-badge">Vehicle Shipping Quote</span></h3>
                        <table class="as-cf-table widefat">
                            <thead><tr><th>Field Name (Label)</th><th>Field Type</th><th>Notes</th></tr></thead>
                            <tbody>
                                <tr><td><strong>Move Type</strong></td><td>Text / Radio</td><td>City To City or Door To Door</td></tr>
                                <tr><td><strong>Pickup Date</strong></td><td>Date</td><td>Requested pickup date</td></tr>
                                <tr><td><strong>From City</strong></td><td>Text</td><td>Origin city; custom text if "Other" selected</td></tr>
                                <tr><td><strong>To City</strong></td><td>Text</td><td>Destination city; custom text if "Other" selected</td></tr>
                                <tr><td><strong>Vehicle Type</strong></td><td>Text / Radio</td><td>Small, Medium, Large, X Large, XXL</td></tr>
                                <tr><td><strong>Vehicle Status</strong></td><td>Text / Radio</td><td>Running &amp; Driving or Non Runner</td></tr>
                            </tbody>
                        </table>

                        <div class="as-instr-note" style="margin-top:16px">
                            <span class="dashicons dashicons-info-outline"></span>
                            <span>Standard GHL contact fields — <strong>First Name, Last Name, Email, Phone</strong> — are mapped automatically.</span>
                        </div>
                    </div>
                </section>

                <!-- Step 3 -->
                <section id="step-3" class="as-instr-section">
                    <div class="as-instr-step-num">3</div>
                    <div class="as-instr-content">
                        <h2>Copy Field IDs into the Plugin</h2>
                        <ol>
                            <li>In GHL go to <strong>Settings → Custom Fields → Contacts</strong>.</li>
                            <li>Click on each field you created.</li>
                            <li>Copy the field <strong>ID</strong> (format: <code>xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx</code>).</li>
                            <li>In this plugin go to the <a href="<?php echo admin_url( 'admin.php?page=as-settings&tab=fields' ); ?>"><strong>Custom Fields tab</strong></a> and paste the ID.</li>
                            <li>Click <strong>Save Settings</strong>.</li>
                        </ol>
                    </div>
                </section>

                <!-- Step 4 -->
                <section id="step-4" class="as-instr-section">
                    <div class="as-instr-step-num">4</div>
                    <div class="as-instr-content">
                        <h2>Add the Shortcode to a Page</h2>
                        <p>Paste the shortcode into any WordPress page or post:</p>
                        <table class="as-cf-table widefat">
                            <thead><tr><th>Form</th><th>Shortcode</th></tr></thead>
                            <tbody>
                                <tr><td>Vehicle Shipping Quote</td><td><code>[as_vehicle_quote]</code></td></tr>
                            </tbody>
                        </table>
                        <p style="margin-top:12px">In Gutenberg use a <strong>Shortcode block</strong>; in Elementor use the <strong>Shortcode widget</strong>.</p>
                    </div>
                </section>

                <!-- Step 5 -->
                <section id="step-5" class="as-instr-section">
                    <div class="as-instr-step-num">5</div>
                    <div class="as-instr-content">
                        <h2>Test a Live Submission</h2>
                        <ol>
                            <li>Open the page containing the shortcode.</li>
                            <li>Fill in all steps and submit the form.</li>
                            <li>In GHL, go to <strong>Contacts</strong> and search for the email used.</li>
                            <li>Confirm the contact was created with the correct custom field values and the tag <code>AutoShippers - Vehicle Quote</code>.</li>
                        </ol>
                        <div class="as-instr-note as-note-success">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <span>If everything is correct you should see the contact in GHL within seconds.</span>
                        </div>
                    </div>
                </section>

                <!-- Troubleshooting -->
                <section id="troubleshooting" class="as-instr-section">
                    <div class="as-instr-step-num" style="background:#6b7280">?</div>
                    <div class="as-instr-content">
                        <h2>Troubleshooting</h2>
                        <table class="as-cf-table widefat">
                            <thead><tr><th>Problem</th><th>Solution</th></tr></thead>
                            <tbody>
                                <tr>
                                    <td><strong>Test Connection fails</strong></td>
                                    <td>Check that your API key and Location ID are correct and haven't been revoked in GHL.</td>
                                </tr>
                                <tr>
                                    <td><strong>Contact created but custom fields are empty</strong></td>
                                    <td>Verify the field IDs in the Custom Fields tab match the IDs in GHL exactly.</td>
                                </tr>
                                <tr>
                                    <td><strong>Form submits but nothing in GHL</strong></td>
                                    <td>Confirm the API key has permission to create contacts. Check server PHP error log for curl/HTTP errors.</td>
                                </tr>
                                <tr>
                                    <td><strong>Shortcode shows raw text</strong></td>
                                    <td>Make sure the plugin is activated under Plugins → Installed Plugins.</td>
                                </tr>
                                <tr>
                                    <td><strong>Form styles look broken</strong></td>
                                    <td>Some themes add conflicting CSS. Add <code>max-width: none; box-sizing: border-box;</code> to the page's custom CSS.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

            </main>
        </div>
    </div>
    <?php
}
