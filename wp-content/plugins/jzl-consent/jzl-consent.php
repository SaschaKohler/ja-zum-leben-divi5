<?php
/**
 * Plugin Name: JZL Consent (GDPR + Google Consent Mode v2)
 * Description: Lightweight cookie banner with Consent Mode v2 integration for GTM/GA4. Pushes consent updates via gtag/dataLayer.
 * Version: 0.1.0
 * Author: Sascha Kohler
 * License: GPLv2 or later
 * Text Domain: jzl-consent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Constants
if ( ! defined( 'JZL_CONSENT_VERSION' ) ) {
    define( 'JZL_CONSENT_VERSION', '0.1.0' );
}
if ( ! defined( 'JZL_CONSENT_URL' ) ) {
    define( 'JZL_CONSENT_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'JZL_CONSENT_PATH' ) ) {
    define( 'JZL_CONSENT_PATH', plugin_dir_path( __FILE__ ) );
}

/**
 * Enqueue frontend assets and inject minimal gtag stub + default consent (denied) early.
 */
function jzl_consent_enqueue_assets() {
    // CSS
    wp_enqueue_style(
        'jzl-consent',
        JZL_CONSENT_URL . 'assets/consent.css',
        array(),
        JZL_CONSENT_VERSION
    );

    // JS
    wp_enqueue_script(
        'jzl-consent',
        JZL_CONSENT_URL . 'assets/consent.js',
        array(),
        JZL_CONSENT_VERSION,
        true
    );

    // Pass settings to JS
    $settings = array(
        'storageKey' => 'jzl_consent',
        'region' => 'eu',
        'policyUrl' => home_url( '/datenschutz' ),
        'imprintUrl' => home_url( '/impressum' ),
        'i18n' => array(
            'title' => __( 'Wir respektieren deine Privatsphäre', 'jzl-consent' ),
            'text' => __( 'Wir verwenden Cookies, um unsere Website zu verbessern. Du kannst selbst entscheiden, welche Kategorien du zulassen möchtest.', 'jzl-consent' ),
            'btnAcceptAll' => __( 'Alle akzeptieren', 'jzl-consent' ),
            'btnRejectAll' => __( 'Nur Notwendige', 'jzl-consent' ),
            'btnSave' => __( 'Auswahl speichern', 'jzl-consent' ),
            'linkPolicy' => __( 'Datenschutzerklärung', 'jzl-consent' ),
            'linkImprint' => __( 'Impressum', 'jzl-consent' ),
            'categories' => array(
                'necessary' => __( 'Notwendig', 'jzl-consent' ),
                'analytics' => __( 'Statistiken', 'jzl-consent' ),
                'marketing' => __( 'Marketing', 'jzl-consent' ),
                'functional' => __( 'Funktional', 'jzl-consent' ),
            ),
            'desc' => array(
                'necessary' => __( 'Erforderlich für die Grundfunktionen der Website.', 'jzl-consent' ),
                'analytics' => __( 'Hilft uns zu verstehen, wie Besucher die Website nutzen (z. B. GA4).', 'jzl-consent' ),
                'marketing' => __( 'Wird verwendet, um personalisierte Werbung anzuzeigen.', 'jzl-consent' ),
                'functional' => __( 'Verbessert Funktionen, z. B. Einbettungen.', 'jzl-consent' ),
            ),
        ),
    );
    wp_localize_script( 'jzl-consent', 'JZL_CONSENT_SETTINGS', $settings );
}
add_action( 'wp_enqueue_scripts', 'jzl_consent_enqueue_assets' );

/**
 * Output gtag stub and default denied consent in the head as early as possible.
 */
function jzl_consent_head_gtag_stub() {
    ?>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      // Consent Mode v2 default (deny non-essential)
      gtag('consent', 'default', {
        'ad_storage': 'denied',
        'analytics_storage': 'denied',
        'ad_user_data': 'denied',
        'ad_personalization': 'denied',
        'functionality_storage': 'granted',
        'security_storage': 'granted',
        'wait_for_update': 500
      });
    </script>
    <?php
}
add_action( 'wp_head', 'jzl_consent_head_gtag_stub', 1 );

/**
 * Get GTM and GA4 IDs via constants or filters.
 */
function jzl_consent_get_gtm_id() {
    $id = defined( 'JZL_GTM_ID' ) ? JZL_GTM_ID : '';
    /**
     * Filter: jzl_consent_gtm_id
     * Return a string like 'GTM-XXXXXXX' to enable GTM output.
     */
    return apply_filters( 'jzl_consent_gtm_id', $id );
}

function jzl_consent_get_ga4_id() {
    $id = defined( 'JZL_GA4_ID' ) ? JZL_GA4_ID : '';
    /**
     * Filter: jzl_consent_ga4_id
     * Return a GA4 Measurement ID like 'G-XXXXXXXX' to enable gtag output.
     */
    return apply_filters( 'jzl_consent_ga4_id', $id );
}

/**
 * Output GTM script (head) after consent stub, if ID present.
 */
function jzl_consent_output_gtm_head() {
    $gtm = jzl_consent_get_gtm_id();
    if ( ! $gtm ) return;
    ?>
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','<?php echo esc_js( $gtm ); ?>');</script>
    <!-- End Google Tag Manager -->
    <?php
}
add_action( 'wp_head', 'jzl_consent_output_gtm_head', 2 );

/**
 * Output GTM noscript (body). Uses wp_body_open hook.
 */
function jzl_consent_output_gtm_noscript() {
    $gtm = jzl_consent_get_gtm_id();
    if ( ! $gtm ) return;
    ?>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr( $gtm ); ?>"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    <?php
}
add_action( 'wp_body_open', 'jzl_consent_output_gtm_noscript', 1 );

/**
 * Output GA4 gtag.js loader + config after consent stub, if GA4 ID present.
 * This is optional; usually GTM is preferred. If both are set, both will load.
 */
function jzl_consent_output_ga4() {
    $ga4 = jzl_consent_get_ga4_id();
    if ( ! $ga4 ) return;
    ?>
    <!-- Google Analytics 4 -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $ga4 ); ?>"></script>
    <script>
      // gtag stub already defined above. Configure GA4 after consent defaults.
      gtag('js', new Date());
      gtag('config', '<?php echo esc_js( $ga4 ); ?>', { 'anonymize_ip': true });
    </script>
    <!-- End Google Analytics 4 -->
    <?php
}
add_action( 'wp_head', 'jzl_consent_output_ga4', 3 );

/**
 * Render the banner container in footer if consent not stored yet.
 */
function jzl_consent_render_banner_container() {
    echo '<div id="jzl-consent-banner-root" class="jzl-hidden" aria-hidden="true"></div>';
}
add_action( 'wp_footer', 'jzl_consent_render_banner_container' );

/**
 * Floating bottom-left preferences button
 */
function jzl_consent_render_fab() {
    $show = apply_filters( 'jzl_consent_show_fab', true );
    if ( ! $show ) return;

    $label = apply_filters( 'jzl_consent_fab_label', __( 'Cookie-Einstellungen', 'jzl-consent' ) );
    echo '<div class="jzl-consent-fab jzl-consent-fab-left" aria-hidden="false">'
        . '<button type="button" class="jzl-fab-primary" aria-label="' . esc_attr( $label ) . '"'
        . ' onclick="window.JZLConsent && window.JZLConsent.openPreferences && window.JZLConsent.openPreferences(); return false;">'
        . esc_html( $label )
        . '</button>'
        . '</div>';
}
add_action( 'wp_footer', 'jzl_consent_render_fab', 98 );

/**
 * Shortcode to render a link/button to open the consent preferences dialog.
 * Usage: [jzl_consent_preferences label="Cookie-Einstellungen"]
 */
function jzl_consent_shortcode_preferences( $atts ) {
    $atts = shortcode_atts( array(
        'label' => __( 'Cookie-Einstellungen', 'jzl-consent' ),
        'class' => 'jzl-btn jzl-btn-outline',
    ), $atts, 'jzl_consent_preferences' );

    $label = esc_html( $atts['label'] );
    $class = esc_attr( $atts['class'] );

    return '<button type="button" class="' . $class . '" onclick="window.JZLConsent && window.JZLConsent.openPreferences && window.JZLConsent.openPreferences(); return false;">' . $label . '</button>';
}
add_shortcode( 'jzl_consent_preferences', 'jzl_consent_shortcode_preferences' );

/**
 * Optional small footer link to open preferences (can be hidden via CSS if not desired).
 */
function jzl_consent_footer_link() {
    echo '<div class="jzl-consent-footer-link" style="text-align:center; font-size:12px; opacity:.7; margin:8px 0;">'
        . '<a href="#" onclick="window.JZLConsent && window.JZLConsent.openPreferences && window.JZLConsent.openPreferences(); return false;">' . esc_html__( 'Cookie-Einstellungen', 'jzl-consent' ) . '</a>'
        . '</div>';
}
add_action( 'wp_footer', 'jzl_consent_footer_link', 99 );

