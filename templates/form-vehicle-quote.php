<?php if ( ! defined( 'ABSPATH' ) ) exit;
$uid = 'as-' . wp_unique_id();
$cities = [
  'Toronto','Ottawa','Calgary','Vancouver','Edmonton',
  'Saskatoon','Regina','Winnipeg','Thunder Bay',
  'Moncton','Halifax','Saint John NB','Montreal',"St. John's NFL",'Other',
];
?>
<!-- Hero banner -->
<div class="as-hero">
  <div class="as-hero-eyebrow">FREE QUOTE</div>
  <h2 class="as-hero-title">Reserve within 24hrs &amp; save $100 more</h2>
  <p class="as-hero-sub">Get your instant quote online and save an extra $100 if you reserve online. Cancel for free at any time before your car is loaded</p>
</div>

<div class="as-wrapper" id="<?php echo esc_attr( $uid ); ?>" data-total="3">

  <!-- Logo -->
  <div class="as-logo">
    <img src="https://autoshippers.ca/wp-content/uploads/2024/10/Favicon-1.png" alt="AutoShippers">
  </div>

  <div class="as-card">

    <!-- Progress bar -->
    <div class="as-prog-track">
      <div class="as-prog-fill" style="width:33.33%"></div>
    </div>

    <!-- Step tabs -->
    <div class="as-step-tabs">
      <div class="as-tab active" data-tab="1"><div class="as-tab-num">1</div> <span class="as-tab-label">Shipping Details</span></div>
      <div class="as-tab" data-tab="2"><div class="as-tab-num">2</div> <span class="as-tab-label">Vehicle Details</span></div>
      <div class="as-tab" data-tab="3"><div class="as-tab-num">3</div> <span class="as-tab-label">Get Quote</span></div>
    </div>

    <div class="as-body">

      <!-- ── Step 1: Shipping Details ── -->
      <div class="as-step active" data-step="1">
        <div class="as-step-heading">
          <div class="as-step-icon"><i class="fa-solid fa-route"></i></div>
          <div>
            <div class="as-step-title">Enter Shipping Details</div>
            <div class="as-step-sub">Tell us where and how you'd like your vehicle moved</div>
          </div>
        </div>

        <div class="as-section-label">Move Type <span class="as-required-star">*</span></div>
        <div class="as-choices-row col2" style="margin-bottom:22px">
          <label class="as-choice">
            <input type="radio" name="move_type" value="City To City">
            <div class="as-choice-card">
              <div class="as-choice-icon-wrap"><i class="fa-solid fa-city"></i></div>
              <div class="as-choice-info">
                <div class="as-choice-name">City To City</div>
                <div class="as-choice-desc">More affordable</div>
              </div>
            </div>
          </label>
          <label class="as-choice">
            <input type="radio" name="move_type" value="Door To Door">
            <div class="as-choice-card">
              <div class="as-choice-icon-wrap"><i class="fa-solid fa-house"></i></div>
              <div class="as-choice-info">
                <div class="as-choice-name">Door To Door</div>
                <div class="as-choice-desc">More convenient</div>
              </div>
            </div>
          </label>
        </div>

        <div class="as-field">
          <label class="as-label">Pickup Date</label>
          <input type="date" class="as-input" name="pickup_date">
        </div>

        <div class="as-divider"></div>

        <div class="as-route-row">
          <div class="as-field" style="margin-bottom:0">
            <label class="as-label">From <span class="as-required-star">*</span></label>
            <select class="as-select" name="from_city" data-other-target="from-other">
              <option value="" disabled selected>Select city</option>
              <?php foreach ( $cities as $c ) : ?>
              <option><?php echo esc_html( $c ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="as-route-arrow"><i class="fa-solid fa-arrow-right"></i></div>
          <div class="as-field" style="margin-bottom:0">
            <label class="as-label">To <span class="as-required-star">*</span></label>
            <select class="as-select" name="to_city" data-other-target="to-other">
              <option value="" disabled selected>Select city</option>
              <?php foreach ( $cities as $c ) : ?>
              <option><?php echo esc_html( $c ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="as-conditional" id="from-other-<?php echo esc_attr( $uid ); ?>" style="margin-top:10px">
          <div class="as-field">
            <label class="as-label">From (specify) <span class="as-required-star">*</span></label>
            <input type="text" class="as-input" name="from_other" placeholder="Enter your city or town">
          </div>
        </div>

        <div class="as-conditional" id="to-other-<?php echo esc_attr( $uid ); ?>" style="margin-top:10px">
          <div class="as-field">
            <label class="as-label">To (specify) <span class="as-required-star">*</span></label>
            <input type="text" class="as-input" name="to_other" placeholder="Enter destination city or town">
          </div>
        </div>

        <div class="as-actions" style="margin-top:24px">
          <button class="as-btn-next" type="button">Vehicle Details <i class="fa-solid fa-arrow-right"></i></button>
        </div>
      </div>

      <!-- ── Step 2: Vehicle Details ── -->
      <div class="as-step" data-step="2">
        <div class="as-step-heading">
          <div class="as-step-icon"><i class="fa-solid fa-car"></i></div>
          <div>
            <div class="as-step-title">Enter Vehicle Details</div>
            <div class="as-step-sub">Help us classify your vehicle for accurate pricing</div>
          </div>
        </div>

        <div class="as-section-label" style="margin-bottom:12px">Vehicle Type <span class="as-required-star">*</span></div>
        <div class="as-size-choices">
          <?php
          $types = [
            ['Small',   'fa-car',          'Sedan, compact SUVs'],
            ['Medium',  'fa-car-side',      'Full-size SUV, ½ ton, Van'],
            ['Large',   'fa-truck-pickup',  '¾ ton pick-up'],
            ['X Large', 'fa-truck',         '1 ton pick-up'],
            ['XXL',     'fa-van-shuttle',   'Sprinters'],
          ];
          foreach ( $types as [$val, $icon, $desc] ) :
          ?>
          <label class="as-size-choice">
            <input type="radio" name="vehicle_type" value="<?php echo esc_attr( $val ); ?>">
            <div class="as-size-card">
              <span class="as-size-icon"><i class="fa-solid <?php echo esc_attr( $icon ); ?>"></i></span>
              <span class="as-size-name"><?php echo esc_html( $val ); ?></span>
              <span class="as-size-desc"><?php echo esc_html( $desc ); ?></span>
            </div>
          </label>
          <?php endforeach; ?>
        </div>

        <div class="as-divider"></div>

        <div class="as-section-label" style="margin-bottom:12px">Vehicle Status <span class="as-required-star">*</span></div>
        <div class="as-status-row">
          <label class="as-status-choice as-status-running">
            <input type="radio" name="vehicle_status" value="Running &amp; Driving">
            <div class="as-status-card">
              <div class="as-status-icon"><i class="fa-solid fa-circle-check"></i></div>
              <div class="as-status-info">
                <strong>Running &amp; Driving</strong>
                <span>Vehicle is in working condition</span>
              </div>
            </div>
          </label>
          <label class="as-status-choice as-status-nonrunner">
            <input type="radio" name="vehicle_status" value="Non Runner">
            <div class="as-status-card">
              <div class="as-status-icon"><i class="fa-solid fa-circle-xmark"></i></div>
              <div class="as-status-info">
                <strong>Non Runner</strong>
                <span>Vehicle does not start or drive</span>
              </div>
            </div>
          </label>
        </div>

        <div class="as-actions">
          <button class="as-btn-back" type="button"><i class="fa-solid fa-arrow-left"></i> Back</button>
          <button class="as-btn-next" type="button">Get My Quote <i class="fa-solid fa-arrow-right"></i></button>
        </div>
      </div>

      <!-- ── Step 3: Contact ── -->
      <div class="as-step" data-step="3">
        <div class="as-step-heading">
          <div class="as-step-icon"><i class="fa-solid fa-envelope"></i></div>
          <div>
            <div class="as-step-title">Get Quote Emailed &amp; Texted</div>
            <div class="as-step-sub">We'll send your personalised quote right away</div>
          </div>
        </div>

        <div class="as-row">
          <div class="as-field">
            <label class="as-label">First Name <span class="as-required-star">*</span></label>
            <input type="text" class="as-input" name="first_name" placeholder="John">
          </div>
          <div class="as-field">
            <label class="as-label">Last Name <span class="as-required-star">*</span></label>
            <input type="text" class="as-input" name="last_name" placeholder="Smith">
          </div>
        </div>
        <div class="as-field">
          <label class="as-label">Email <span class="as-required-star">*</span></label>
          <input type="email" class="as-input" name="email" placeholder="john@example.com">
        </div>
        <div class="as-field">
          <label class="as-label">Phone <span class="as-required-star">*</span></label>
          <input type="tel" class="as-input" name="phone" placeholder="+1 (416) 555-0100">
        </div>

        <div class="as-actions" style="margin-top:20px">
          <button class="as-btn-back" type="button"><i class="fa-solid fa-arrow-left"></i> Back</button>
          <button class="as-btn-submit" type="button"><i class="fa-solid fa-paper-plane"></i> Send Me My Quote</button>
        </div>
        <div class="as-error-msg" style="display:none"></div>
      </div>

      <!-- ── Success ── -->
      <div class="as-step" data-step="4">
        <div class="as-success">
          <div class="as-success-ring"><i class="fa-solid fa-check"></i></div>
          <h2>Quote on its Way!</h2>
          <p>We've received your shipping request. Your personalised quote will be emailed and texted to you shortly.</p>
          <div class="as-success-details">
            <i class="fa-solid fa-clock"></i>
            <span>Our team typically responds within <strong>15–30 minutes</strong> during business hours.</span>
          </div>
        </div>
      </div>

    </div><!-- /.as-body -->

    <!-- Trust strip -->
    <div class="as-trust">
      <div class="as-trust-item"><i class="fa-solid fa-shield-halved"></i> Fully Insured</div>
      <div class="as-trust-item"><i class="fa-solid fa-bolt"></i> Fast Response</div>
      <div class="as-trust-item"><i class="fa-solid fa-dollar-sign"></i> No Hidden Fees</div>
      <div class="as-trust-item"><i class="fa-solid fa-map-location-dot"></i> Canada Wide</div>
    </div>

  </div><!-- /.as-card -->
<!-- Contact strip -->
<div class="as-contact-strip">
  <a class="as-contact-item" href="tel:+16473706268">
    <div class="as-contact-icon"><i class="fa-solid fa-phone-volume"></i></div>
    <div class="as-contact-label">Phone</div>
    <div class="as-contact-value">(647) 370-6268</div>
  </a>
  <a class="as-contact-item" href="mailto:dispatch@autoshippers.ca">
    <div class="as-contact-icon"><i class="fa-solid fa-envelope-open-text"></i></div>
    <div class="as-contact-label">Email</div>
    <div class="as-contact-value">dispatch@autoshippers.ca</div>
  </a>
  <a class="as-contact-item" href="https://maps.google.com/?q=482+South+Service+Road+Unit+134+Oakville+ON" target="_blank" rel="noopener">
    <div class="as-contact-icon"><i class="fa-solid fa-map-location-dot"></i></div>
    <div class="as-contact-label">Address</div>
    <div class="as-contact-value">482 South Service Road Unit #134</div>
  </a>
</div>

</div><!-- /.as-wrapper -->
