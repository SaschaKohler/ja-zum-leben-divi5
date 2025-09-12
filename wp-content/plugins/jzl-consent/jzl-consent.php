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
if ( ! defined( 'JZL_CONSENT_OPTION_NAME' ) ) {
    define( 'JZL_CONSENT_OPTION_NAME', 'jzl_consent_options' );
}

/**
 * Default options for the plugin settings.
 */
function jzl_consent_get_default_options() {
    return array(
        'gtm_id'      => '',
        'ga4_id'      => '',
        'region'      => 'eu',
        'policy_url'  => home_url( '/datenschutz' ),
        'imprint_url' => home_url( '/impressum' ),
        'storage_key' => 'jzl_consent',
        'show_fab'    => 1,
        'fab_label'   => __( 'Cookie-Einstellungen', 'jzl-consent' ),
        'fab_position'=> 'left', // left|right
        'show_footer_link' => 1,
        // Banner texts
        'txt_title'   => __( 'Wir respektieren deine Privatsphäre', 'jzl-consent' ),
        'txt_text'    => __( 'Wir verwenden Cookies, um unsere Website zu verbessern. Du kannst selbst entscheiden, welche Kategorien du zulassen möchtest.', 'jzl-consent' ),
        'txt_btn_accept_all' => __( 'Alle akzeptieren', 'jzl-consent' ),
        'txt_btn_reject_all' => __( 'Nur Notwendige', 'jzl-consent' ),
        'txt_btn_save'       => __( 'Auswahl speichern', 'jzl-consent' ),
        'txt_link_policy'    => __( 'Datenschutzerklärung', 'jzl-consent' ),
        'txt_link_imprint'   => __( 'Impressum', 'jzl-consent' ),
        // Category labels
        'lbl_necessary' => __( 'Notwendig', 'jzl-consent' ),
        'lbl_analytics' => __( 'Statistiken', 'jzl-consent' ),
        'lbl_marketing' => __( 'Marketing', 'jzl-consent' ),
        'lbl_functional' => __( 'Funktional', 'jzl-consent' ),
        // Category descriptions
        'desc_necessary' => __( 'Erforderlich für die Grundfunktionen der Website.', 'jzl-consent' ),
        'desc_analytics' => __( 'Hilft uns zu verstehen, wie Besucher die Website nutzen (z. B. GA4).', 'jzl-consent' ),
        'desc_marketing' => __( 'Wird verwendet, um personalisierte Werbung anzuzeigen.', 'jzl-consent' ),
        'desc_functional' => __( 'Verbessert Funktionen, z. B. Einbettungen.', 'jzl-consent' ),
        // Appearance
        'ui_position' => 'bottom', // bottom|top
        'ui_primary_color' => '#2c7be5',
        'ui_text_color' => '#111111',
        'ui_background_color' => '#ffffff',
        // Templates and FAB avatar
        'ui_template' => 'template1', // template1|template2|template3|template4
        'fab_avatar_id' => 0,
    );
}

/**
 * Retrieve merged options (saved options overriding defaults).
 */
function jzl_consent_get_options() {
    $saved = get_option( JZL_CONSENT_OPTION_NAME, array() );
    $defaults = jzl_consent_get_default_options();
    if ( ! is_array( $saved ) ) {
        $saved = array();
    }
    return wp_parse_args( $saved, $defaults );
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

    // Pass settings to JS (merged with options)
    $opts = jzl_consent_get_options();
    $settings = array(
        'storageKey' => sanitize_key( $opts['storage_key'] ),
        'region' => in_array( $opts['region'], array( 'eu', 'us', 'auto' ), true ) ? $opts['region'] : 'eu',
        'policyUrl' => esc_url( $opts['policy_url'] ),
        'imprintUrl' => esc_url( $opts['imprint_url'] ),
        'i18n' => array(
            'title' => $opts['txt_title'],
            'text' => $opts['txt_text'],
            'btnAcceptAll' => $opts['txt_btn_accept_all'],
            'btnRejectAll' => $opts['txt_btn_reject_all'],
            'btnSave' => $opts['txt_btn_save'],
            'linkPolicy' => $opts['txt_link_policy'],
            'linkImprint' => $opts['txt_link_imprint'],
            'categories' => array(
                'necessary' => $opts['lbl_necessary'],
                'analytics' => $opts['lbl_analytics'],
                'marketing' => $opts['lbl_marketing'],
                'functional' => $opts['lbl_functional'],
            ),
            'desc' => array(
                'necessary' => $opts['desc_necessary'],
                'analytics' => $opts['desc_analytics'],
                'marketing' => $opts['desc_marketing'],
                'functional' => $opts['desc_functional'],
            ),
        ),
        'ui' => array(
            'position' => in_array( $opts['ui_position'], array( 'bottom', 'top' ), true ) ? $opts['ui_position'] : 'bottom',
            'primaryColor' => $opts['ui_primary_color'],
            'textColor' => $opts['ui_text_color'],
            'backgroundColor' => $opts['ui_background_color'],
            'fabPosition' => in_array( $opts['fab_position'], array( 'left', 'right' ), true ) ? $opts['fab_position'] : 'left',
            'showFooterLink' => ! empty( $opts['show_footer_link'] ),
            'template' => in_array( $opts['ui_template'], array( 'template1','template2','template3','template4' ), true ) ? $opts['ui_template'] : 'template1',
            'fabAvatar' => ( ! empty( $opts['fab_avatar_id'] ) ? esc_url( wp_get_attachment_image_url( (int) $opts['fab_avatar_id'], 'thumbnail' ) ) : '' ),
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
    $opts = jzl_consent_get_options();
    $id = ! empty( $opts['gtm_id'] ) ? $opts['gtm_id'] : ( defined( 'JZL_GTM_ID' ) ? JZL_GTM_ID : '' );
    /**
     * Filter: jzl_consent_gtm_id
     * Return a string like 'GTM-XXXXXXX' to enable GTM output.
     */
    return apply_filters( 'jzl_consent_gtm_id', $id );
}

function jzl_consent_get_ga4_id() {
    $opts = jzl_consent_get_options();
    $id = ! empty( $opts['ga4_id'] ) ? $opts['ga4_id'] : ( defined( 'JZL_GA4_ID' ) ? JZL_GA4_ID : '' );
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
    $opts = jzl_consent_get_options();
    $show_default = ! empty( $opts['show_fab'] );
    $show = apply_filters( 'jzl_consent_show_fab', $show_default );
    if ( ! $show ) return;

    $label_default = ! empty( $opts['fab_label'] ) ? $opts['fab_label'] : __( 'Cookie-Einstellungen', 'jzl-consent' );
    $label = apply_filters( 'jzl_consent_fab_label', $label_default );
    $pos_class = ( ! empty( $opts['fab_position'] ) && $opts['fab_position'] === 'right' ) ? 'jzl-consent-fab-right' : 'jzl-consent-fab-left';
    $avatar_url = ! empty( $opts['fab_avatar_id'] ) ? wp_get_attachment_image_url( (int) $opts['fab_avatar_id'], 'thumbnail' ) : '';
    echo '<div class="jzl-consent-fab ' . esc_attr( $pos_class ) . '" aria-hidden="false">'
        . '<button type="button" class="jzl-fab-primary" aria-label="' . esc_attr( $label ) . '"'
        . ' onclick="window.JZLConsent && window.JZLConsent.openPreferences && window.JZLConsent.openPreferences(); return false;">'
        . ( $avatar_url ? '<img class="jzl-fab-avatar" src="' . esc_url( $avatar_url ) . '" alt="" /> ' : '' )
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
    $opts = jzl_consent_get_options();
    if ( empty( $opts['show_footer_link'] ) ) {
        return;
    }
    echo '<div class="jzl-consent-footer-link" style="text-align:center; font-size:12px; opacity:.7; margin:8px 0;">'
        . '<a href="#" onclick="window.JZLConsent && window.JZLConsent.openPreferences && window.JZLConsent.openPreferences(); return false;">' . esc_html__( 'Cookie-Einstellungen', 'jzl-consent' ) . '</a>'
        . '</div>';
}
add_action( 'wp_footer', 'jzl_consent_footer_link', 99 );
/**
 * Register settings, section and fields for the admin page.
 */
function jzl_consent_admin_init() {
    register_setting( 'jzl_consent_settings_group', JZL_CONSENT_OPTION_NAME, 'jzl_consent_sanitize_options' );

    add_settings_section(
        'jzl_consent_main_section',
        __( 'Allgemeine Einstellungen', 'jzl-consent' ),
        function() {
            echo '<p>' . esc_html__( 'Konfiguriere IDs, Region und Links für das Consent-Banner.', 'jzl-consent' ) . '</p>';
        },
        'jzl_consent_settings'
    );

    add_settings_field(
        'jzl_consent_gtm_id',
        __( 'Google Tag Manager ID', 'jzl-consent' ),
        'jzl_consent_field_gtm_id',
        'jzl_consent_settings',
        'jzl_consent_main_section'
    );

    add_settings_field(
        'jzl_consent_ga4_id',
        __( 'GA4 Measurement ID', 'jzl-consent' ),
        'jzl_consent_field_ga4_id',
        'jzl_consent_settings',
        'jzl_consent_main_section'
    );

    add_settings_field(
        'jzl_consent_region',
        __( 'Region', 'jzl-consent' ),
        'jzl_consent_field_region',
        'jzl_consent_settings',
        'jzl_consent_main_section'
    );

    add_settings_field(
        'jzl_consent_policy_url',
        __( 'Datenschutzerklärung URL', 'jzl-consent' ),
        'jzl_consent_field_policy_url',
        'jzl_consent_settings',
        'jzl_consent_main_section'
    );

    add_settings_field(
        'jzl_consent_imprint_url',
        __( 'Impressum URL', 'jzl-consent' ),
        'jzl_consent_field_imprint_url',
        'jzl_consent_settings',
        'jzl_consent_main_section'
    );

    add_settings_field(
        'jzl_consent_storage_key',
        __( 'Storage Key', 'jzl-consent' ),
        'jzl_consent_field_storage_key',
        'jzl_consent_settings',
        'jzl_consent_main_section'
    );

    add_settings_field(
        'jzl_consent_show_fab',
        __( 'Floating Button anzeigen', 'jzl-consent' ),
        'jzl_consent_field_show_fab',
        'jzl_consent_settings',
        'jzl_consent_main_section'
    );

    add_settings_field(
        'jzl_consent_fab_label',
        __( 'Button-Label', 'jzl-consent' ),
        'jzl_consent_field_fab_label',
        'jzl_consent_settings',
        'jzl_consent_main_section'
    );

    // FAB position
    add_settings_field(
        'jzl_consent_fab_position',
        __( 'Floating Button Position', 'jzl-consent' ),
        'jzl_consent_field_fab_position',
        'jzl_consent_settings',
        'jzl_consent_main_section'
    );

    // Footer link toggle
    add_settings_field(
        'jzl_consent_show_footer_link',
        __( 'Footer-Link anzeigen', 'jzl-consent' ),
        'jzl_consent_field_show_footer_link',
        'jzl_consent_settings',
        'jzl_consent_main_section'
    );

    // Section: Texte
    add_settings_section(
        'jzl_consent_texts_section',
        __( 'Texte (UI)', 'jzl-consent' ),
        function() { echo '<p>' . esc_html__( 'Passe die Texte im Cookie-Banner an.', 'jzl-consent' ) . '</p>'; },
        'jzl_consent_settings'
    );

    $text_fields = array(
        'txt_title' => __( 'Titel', 'jzl-consent' ),
        'txt_text' => __( 'Beschreibungstext', 'jzl-consent' ),
        'txt_btn_accept_all' => __( 'Button „Alle akzeptieren“', 'jzl-consent' ),
        'txt_btn_reject_all' => __( 'Button „Nur Notwendige“', 'jzl-consent' ),
        'txt_btn_save' => __( 'Button „Auswahl speichern“', 'jzl-consent' ),
        'txt_link_policy' => __( 'Linktext Datenschutzerklärung', 'jzl-consent' ),
        'txt_link_imprint' => __( 'Linktext Impressum', 'jzl-consent' ),
    );
    foreach ( $text_fields as $key => $label ) {
        add_settings_field(
            'jzl_consent_' . $key,
            $label,
            'jzl_consent_field_text_generic',
            'jzl_consent_settings',
            'jzl_consent_texts_section',
            array( 'key' => $key )
        );
    }

    // Section: Kategorien
    add_settings_section(
        'jzl_consent_categories_section',
        __( 'Kategorien', 'jzl-consent' ),
        function() { echo '<p>' . esc_html__( 'Passe Labels und Beschreibungen der Kategorien an.', 'jzl-consent' ) . '</p>'; },
        'jzl_consent_settings'
    );

    $cat_labels = array(
        'lbl_necessary' => __( 'Label: Notwendig', 'jzl-consent' ),
        'lbl_analytics' => __( 'Label: Statistiken', 'jzl-consent' ),
        'lbl_marketing' => __( 'Label: Marketing', 'jzl-consent' ),
        'lbl_functional' => __( 'Label: Funktional', 'jzl-consent' ),
    );
    foreach ( $cat_labels as $key => $label ) {
        add_settings_field(
            'jzl_consent_' . $key,
            $label,
            'jzl_consent_field_text_generic',
            'jzl_consent_settings',
            'jzl_consent_categories_section',
            array( 'key' => $key )
        );
    }
    $cat_descs = array(
        'desc_necessary' => __( 'Beschreibung: Notwendig', 'jzl-consent' ),
        'desc_analytics' => __( 'Beschreibung: Statistiken', 'jzl-consent' ),
        'desc_marketing' => __( 'Beschreibung: Marketing', 'jzl-consent' ),
        'desc_functional' => __( 'Beschreibung: Funktional', 'jzl-consent' ),
    );
    foreach ( $cat_descs as $key => $label ) {
        add_settings_field(
            'jzl_consent_' . $key,
            $label,
            'jzl_consent_field_textarea_generic',
            'jzl_consent_settings',
            'jzl_consent_categories_section',
            array( 'key' => $key )
        );
    }

    // Section: Darstellung
    add_settings_section(
        'jzl_consent_appearance_section',
        __( 'Darstellung', 'jzl-consent' ),
        function() { echo '<p>' . esc_html__( 'Farben und Position des Banners.', 'jzl-consent' ) . '</p>'; },
        'jzl_consent_settings'
    );
    add_settings_field(
        'jzl_consent_ui_position',
        __( 'Banner-Position', 'jzl-consent' ),
        'jzl_consent_field_ui_position',
        'jzl_consent_settings',
        'jzl_consent_appearance_section'
    );
    add_settings_field(
        'jzl_consent_ui_primary_color',
        __( 'Primärfarbe', 'jzl-consent' ),
        'jzl_consent_field_color_primary',
        'jzl_consent_settings',
        'jzl_consent_appearance_section'
    );
    add_settings_field(
        'jzl_consent_ui_text_color',
        __( 'Textfarbe', 'jzl-consent' ),
        'jzl_consent_field_color_text',
        'jzl_consent_settings',
        'jzl_consent_appearance_section'
    );
    add_settings_field(
        'jzl_consent_ui_background_color',
        __( 'Hintergrundfarbe', 'jzl-consent' ),
        'jzl_consent_field_color_background',
        'jzl_consent_settings',
        'jzl_consent_appearance_section'
    );

    // Template select
    add_settings_field(
        'jzl_consent_ui_template',
        __( 'Banner-Template', 'jzl-consent' ),
        'jzl_consent_field_ui_template',
        'jzl_consent_settings',
        'jzl_consent_appearance_section'
    );

    // FAB Avatar upload
    add_settings_field(
        'jzl_consent_fab_avatar',
        __( 'FAB Avatar', 'jzl-consent' ),
        'jzl_consent_field_fab_avatar',
        'jzl_consent_settings',
        'jzl_consent_main_section'
    );
}
add_action( 'admin_init', 'jzl_consent_admin_init' );

/**
 * Add settings page under Settings.
 */
function jzl_consent_admin_menu() {
    add_options_page(
        __( 'JZL Consent', 'jzl-consent' ),
        __( 'JZL Consent', 'jzl-consent' ),
        'manage_options',
        'jzl_consent_settings',
        'jzl_consent_render_settings_page'
    );
}
add_action( 'admin_menu', 'jzl_consent_admin_menu' );

/**
 * Enqueue admin assets (Color Picker) only on our settings page.
 */
function jzl_consent_admin_assets( $hook ) {
    if ( $hook !== 'settings_page_jzl_consent_settings' ) {
        return;
    }
    wp_enqueue_style( 'wp-color-picker' );
    // Reuse frontend CSS for accurate preview styling
    wp_enqueue_style( 'jzl-consent', JZL_CONSENT_URL . 'assets/consent.css', array(), JZL_CONSENT_VERSION );
    wp_enqueue_media();
    wp_enqueue_script( 'jzl-consent-admin', JZL_CONSENT_URL . 'assets/admin.js', array( 'wp-color-picker', 'jquery' ), JZL_CONSENT_VERSION, true );
}
add_action( 'admin_enqueue_scripts', 'jzl_consent_admin_assets' );

/**
 * Settings page renderer.
 */
function jzl_consent_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form method="post" action="options.php">
            <?php
                settings_fields( 'jzl_consent_settings_group' );
                do_settings_sections( 'jzl_consent_settings' );
                submit_button();
            ?>
        </form>
        <hr />
        <h2><?php esc_html_e( 'Live-Vorschau', 'jzl-consent' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Die Vorschau aktualisiert sich automatisch bei Änderungen an den Feldern (auch ohne Speichern).', 'jzl-consent' ); ?></p>
        <div id="jzl-consent-admin-preview" style="position:relative; min-height:220px;">
            <!-- Preview will be rendered here by admin.js -->
        </div>
    </div>
    <?php
}

/**
 * Field render callbacks
 */
function jzl_consent_field_gtm_id() {
    $opts = jzl_consent_get_options();
    printf(
        '<input type="text" class="regular-text" name="%1$s[gtm_id]" value="%2$s" placeholder="GTM-XXXXXXX" />',
        esc_attr( JZL_CONSENT_OPTION_NAME ),
        esc_attr( $opts['gtm_id'] )
    );
}

function jzl_consent_field_ga4_id() {
    $opts = jzl_consent_get_options();
    printf(
        '<input type="text" class="regular-text" name="%1$s[ga4_id]" value="%2$s" placeholder="G-XXXXXXXX" />',
        esc_attr( JZL_CONSENT_OPTION_NAME ),
        esc_attr( $opts['ga4_id'] )
    );
}

function jzl_consent_field_region() {
    $opts = jzl_consent_get_options();
    $value = in_array( $opts['region'], array( 'eu', 'us', 'auto' ), true ) ? $opts['region'] : 'eu';
    ?>
    <select name="<?php echo esc_attr( JZL_CONSENT_OPTION_NAME ); ?>[region]">
        <option value="eu" <?php selected( $value, 'eu' ); ?>><?php esc_html_e( 'EU', 'jzl-consent' ); ?></option>
        <option value="us" <?php selected( $value, 'us' ); ?>><?php esc_html_e( 'US', 'jzl-consent' ); ?></option>
        <option value="auto" <?php selected( $value, 'auto' ); ?>><?php esc_html_e( 'Auto', 'jzl-consent' ); ?></option>
    </select>
    <?php
}

function jzl_consent_field_policy_url() {
    $opts = jzl_consent_get_options();
    printf(
        '<input type="url" class="regular-text" name="%1$s[policy_url]" value="%2$s" placeholder="%3$s" />',
        esc_attr( JZL_CONSENT_OPTION_NAME ),
        esc_attr( $opts['policy_url'] ),
        esc_attr( home_url( '/datenschutz' ) )
    );
}

function jzl_consent_field_imprint_url() {
    $opts = jzl_consent_get_options();
    printf(
        '<input type="url" class="regular-text" name="%1$s[imprint_url]" value="%2$s" placeholder="%3$s" />',
        esc_attr( JZL_CONSENT_OPTION_NAME ),
        esc_attr( $opts['imprint_url'] ),
        esc_attr( home_url( '/impressum' ) )
    );
}

function jzl_consent_field_storage_key() {
    $opts = jzl_consent_get_options();
    printf(
        '<input type="text" class="regular-text" name="%1$s[storage_key]" value="%2$s" />',
        esc_attr( JZL_CONSENT_OPTION_NAME ),
        esc_attr( $opts['storage_key'] )
    );
    echo '<p class="description">' . esc_html__( 'Lokaler Speicher-Key (nur Kleinbuchstaben, Zahlen und Unterstrich).', 'jzl-consent' ) . '</p>';
}

function jzl_consent_field_show_fab() {
    $opts = jzl_consent_get_options();
    printf(
        '<label><input type="checkbox" name="%1$s[show_fab]" value="1" %2$s /> %3$s</label>',
        esc_attr( JZL_CONSENT_OPTION_NAME ),
        checked( ! empty( $opts['show_fab'] ), true, false ),
        esc_html__( 'Floating Button unten links anzeigen', 'jzl-consent' )
    );
}

function jzl_consent_field_fab_label() {
    $opts = jzl_consent_get_options();
    printf(
        '<input type="text" class="regular-text" name="%1$s[fab_label]" value="%2$s" />',
        esc_attr( JZL_CONSENT_OPTION_NAME ),
        esc_attr( $opts['fab_label'] )
    );
}

function jzl_consent_field_fab_position() {
    $opts = jzl_consent_get_options();
    $value = ( $opts['fab_position'] === 'right' ) ? 'right' : 'left';
    ?>
    <select name="<?php echo esc_attr( JZL_CONSENT_OPTION_NAME ); ?>[fab_position]">
        <option value="left" <?php selected( $value, 'left' ); ?>><?php esc_html_e( 'Links', 'jzl-consent' ); ?></option>
        <option value="right" <?php selected( $value, 'right' ); ?>><?php esc_html_e( 'Rechts', 'jzl-consent' ); ?></option>
    </select>
    <?php
}

function jzl_consent_field_show_footer_link() {
    $opts = jzl_consent_get_options();
    printf(
        '<label><input type="checkbox" name="%1$s[show_footer_link]" value="1" %2$s /> %3$s</label>',
        esc_attr( JZL_CONSENT_OPTION_NAME ),
        checked( ! empty( $opts['show_footer_link'] ), true, false ),
        esc_html__( 'Kleinen Footer-Link anzeigen', 'jzl-consent' )
    );
}

function jzl_consent_field_text_generic( $args ) {
    $opts = jzl_consent_get_options();
    $key = isset( $args['key'] ) ? $args['key'] : '';
    $val = isset( $opts[ $key ] ) ? $opts[ $key ] : '';
    printf(
        '<input type="text" class="regular-text" name="%1$s[%2$s]" value="%3$s" />',
        esc_attr( JZL_CONSENT_OPTION_NAME ),
        esc_attr( $key ),
        esc_attr( $val )
    );
}

function jzl_consent_field_textarea_generic( $args ) {
    $opts = jzl_consent_get_options();
    $key = isset( $args['key'] ) ? $args['key'] : '';
    $val = isset( $opts[ $key ] ) ? $opts[ $key ] : '';
    printf(
        '<textarea class="large-text" rows="3" name="%1$s[%2$s]">%3$s</textarea>',
        esc_attr( JZL_CONSENT_OPTION_NAME ),
        esc_attr( $key ),
        esc_textarea( $val )
    );
}

function jzl_consent_field_ui_position() {
    $opts = jzl_consent_get_options();
    $value = in_array( $opts['ui_position'], array( 'bottom', 'top' ), true ) ? $opts['ui_position'] : 'bottom';
    ?>
    <select name="<?php echo esc_attr( JZL_CONSENT_OPTION_NAME ); ?>[ui_position]">
        <option value="bottom" <?php selected( $value, 'bottom' ); ?>><?php esc_html_e( 'Unten', 'jzl-consent' ); ?></option>
        <option value="top" <?php selected( $value, 'top' ); ?>><?php esc_html_e( 'Oben', 'jzl-consent' ); ?></option>
    </select>
    <?php
}

function jzl_consent_field_color_primary() {
    $opts = jzl_consent_get_options();
    printf(
        '<input type="text" class="regular-text jzl-color" name="%1$s[ui_primary_color]" value="%2$s" placeholder="#2c7be5" />',
        esc_attr( JZL_CONSENT_OPTION_NAME ),
        esc_attr( $opts['ui_primary_color'] )
    );
}

function jzl_consent_field_color_text() {
    $opts = jzl_consent_get_options();
    printf(
        '<input type="text" class="regular-text jzl-color" name="%1$s[ui_text_color]" value="%2$s" placeholder="#111111" />',
        esc_attr( JZL_CONSENT_OPTION_NAME ),
        esc_attr( $opts['ui_text_color'] )
    );
}

function jzl_consent_field_color_background() {
    $opts = jzl_consent_get_options();
    printf(
        '<input type="text" class="regular-text jzl-color" name="%1$s[ui_background_color]" value="%2$s" placeholder="#ffffff" />',
        esc_attr( JZL_CONSENT_OPTION_NAME ),
        esc_attr( $opts['ui_background_color'] )
    );
}

function jzl_consent_field_ui_template() {
    $opts = jzl_consent_get_options();
    $value = in_array( $opts['ui_template'], array( 'template1','template2','template3','template4' ), true ) ? $opts['ui_template'] : 'template1';
    ?>
    <select name="<?php echo esc_attr( JZL_CONSENT_OPTION_NAME ); ?>[ui_template]">
        <option value="template1" <?php selected( $value, 'template1' ); ?>><?php esc_html_e( 'Template 1 (klassisch)', 'jzl-consent' ); ?></option>
        <option value="template2" <?php selected( $value, 'template2' ); ?>><?php esc_html_e( 'Template 2 (kompakt)', 'jzl-consent' ); ?></option>
        <option value="template3" <?php selected( $value, 'template3' ); ?>><?php esc_html_e( 'Template 3 (kartenartig)', 'jzl-consent' ); ?></option>
        <option value="template4" <?php selected( $value, 'template4' ); ?>><?php esc_html_e( 'Template 4 (vollbreite)', 'jzl-consent' ); ?></option>
    </select>
    <?php
}

function jzl_consent_field_fab_avatar() {
    $opts = jzl_consent_get_options();
    $id = isset( $opts['fab_avatar_id'] ) ? (int) $opts['fab_avatar_id'] : 0;
    $url = $id ? wp_get_attachment_image_url( $id, 'thumbnail' ) : '';
    $field_name = JZL_CONSENT_OPTION_NAME . '[fab_avatar_id]';
    ?>
    <div id="jzl-fab-avatar-field">
        <div class="jzl-fab-avatar-preview" style="margin-bottom:8px;">
            <?php if ( $url ) : ?>
                <img src="<?php echo esc_url( $url ); ?>" alt="" style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:1px solid #ccc;" />
            <?php else : ?>
                <span style="display:inline-block;width:48px;height:48px;border-radius:50%;background:#eee;border:1px dashed #ccc;"></span>
            <?php endif; ?>
        </div>
        <input type="hidden" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( $id ); ?>" />
        <button type="button" class="button jzl-upload-avatar"><?php esc_html_e( 'Avatar auswählen', 'jzl-consent' ); ?></button>
        <button type="button" class="button link-button jzl-remove-avatar" style="color:#b32d2e;margin-left:8px;" <?php disabled( ! $id ); ?>><?php esc_html_e( 'Entfernen', 'jzl-consent' ); ?></button>
        <p class="description"><?php esc_html_e( 'Optionales kleines Bild-Icon für den Floating Button (empfohlen: quadratisch, z. B. 64x64px).', 'jzl-consent' ); ?></p>
    </div>
    <?php
}

/**
 * Sanitize options before saving.
 */
function jzl_consent_sanitize_options( $input ) {
    $defaults = jzl_consent_get_default_options();
    $clean = array();

    $clean['gtm_id'] = isset( $input['gtm_id'] ) ? strtoupper( preg_replace( '/[^A-Z0-9\-]/', '', wp_strip_all_tags( $input['gtm_id'] ) ) ) : $defaults['gtm_id'];
    $clean['ga4_id'] = isset( $input['ga4_id'] ) ? strtoupper( preg_replace( '/[^A-Z0-9\-]/', '', wp_strip_all_tags( $input['ga4_id'] ) ) ) : $defaults['ga4_id'];

    $region = isset( $input['region'] ) ? strtolower( wp_strip_all_tags( $input['region'] ) ) : $defaults['region'];
    $clean['region'] = in_array( $region, array( 'eu', 'us', 'auto' ), true ) ? $region : 'eu';

    $clean['policy_url']  = isset( $input['policy_url'] ) ? esc_url_raw( $input['policy_url'] ) : $defaults['policy_url'];
    $clean['imprint_url'] = isset( $input['imprint_url'] ) ? esc_url_raw( $input['imprint_url'] ) : $defaults['imprint_url'];

    $key = isset( $input['storage_key'] ) ? sanitize_key( $input['storage_key'] ) : $defaults['storage_key'];
    $clean['storage_key'] = $key ? $key : 'jzl_consent';

    $clean['show_fab']  = ! empty( $input['show_fab'] ) ? 1 : 0;
    $clean['fab_label'] = isset( $input['fab_label'] ) ? sanitize_text_field( $input['fab_label'] ) : $defaults['fab_label'];
    $clean['fab_position'] = ( isset( $input['fab_position'] ) && in_array( $input['fab_position'], array( 'left', 'right' ), true ) ) ? $input['fab_position'] : $defaults['fab_position'];
    $clean['show_footer_link'] = ! empty( $input['show_footer_link'] ) ? 1 : 0;
    $clean['ui_template'] = ( isset( $input['ui_template'] ) && in_array( $input['ui_template'], array( 'template1','template2','template3','template4' ), true ) ) ? $input['ui_template'] : $defaults['ui_template'];
    $clean['fab_avatar_id'] = isset( $input['fab_avatar_id'] ) ? absint( $input['fab_avatar_id'] ) : 0;

    // Texts
    $text_keys = array( 'txt_title', 'txt_text', 'txt_btn_accept_all', 'txt_btn_reject_all', 'txt_btn_save', 'txt_link_policy', 'txt_link_imprint' );
    foreach ( $text_keys as $k ) {
        $clean[ $k ] = isset( $input[ $k ] ) ? wp_kses_post( $input[ $k ] ) : $defaults[ $k ];
    }
    // Labels
    $label_keys = array( 'lbl_necessary', 'lbl_analytics', 'lbl_marketing', 'lbl_functional' );
    foreach ( $label_keys as $k ) {
        $clean[ $k ] = isset( $input[ $k ] ) ? sanitize_text_field( $input[ $k ] ) : $defaults[ $k ];
    }
    // Descriptions
    $desc_keys = array( 'desc_necessary', 'desc_analytics', 'desc_marketing', 'desc_functional' );
    foreach ( $desc_keys as $k ) {
        $clean[ $k ] = isset( $input[ $k ] ) ? wp_kses_post( $input[ $k ] ) : $defaults[ $k ];
    }
    // Appearance
    $clean['ui_position'] = ( isset( $input['ui_position'] ) && in_array( $input['ui_position'], array( 'bottom', 'top' ), true ) ) ? $input['ui_position'] : $defaults['ui_position'];
    $clean['ui_primary_color'] = isset( $input['ui_primary_color'] ) ? sanitize_hex_color_no_hash( $input['ui_primary_color'] ) : ltrim( $defaults['ui_primary_color'], '#' );
    $clean['ui_text_color'] = isset( $input['ui_text_color'] ) ? sanitize_hex_color_no_hash( $input['ui_text_color'] ) : ltrim( $defaults['ui_text_color'], '#' );
    $clean['ui_background_color'] = isset( $input['ui_background_color'] ) ? sanitize_hex_color_no_hash( $input['ui_background_color'] ) : ltrim( $defaults['ui_background_color'], '#' );
    // Re-add hashes for storage
    foreach ( array( 'ui_primary_color', 'ui_text_color', 'ui_background_color' ) as $ck ) {
        if ( ! empty( $clean[ $ck ] ) && $clean[ $ck ][0] !== '#' ) {
            $clean[ $ck ] = '#' . $clean[ $ck ];
        }
    }

    return $clean;
}
