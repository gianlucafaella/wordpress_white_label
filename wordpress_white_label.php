<?php
/**
 * Plugin Name: GF White Label Studio
 * Description: White label per WordPress: admin, login, footer, dashboard e privacy overlay per scorciatoie tipo F12.
 * Version:     1.1.0
 * Author:      Gianluca Faella
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'GFWLS_White_Label_Studio' ) ) {
    final class GFWLS_White_Label_Studio {
        const VERSION      = '1.1.0';
        const OPTION       = 'gfwls_options';
        const OPTION_GROUP = 'gfwls_option_group';
        const SLUG         = 'gfwls-white-label-studio';

        private static $instance = null;
        private $settings_hook   = '';

        public static function instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public static function default_options() {
            $site_name = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'name' ) : 'Studio';

            return array(
                'company_name'                   => $site_name,
                'logo_url'                       => '',
                'login_background_image'         => '',
                'primary_color'                  => '#5b5cf6',
                'secondary_color'                => '#111827',
                'accent_color'                   => '#22c55e',
                'login_background_color'         => '#0f172a',
                'footer_text'                    => 'Realizzato con cura da ' . $site_name,
                'dashboard_title'                => 'Benvenuto',
                'dashboard_widget_text'          => 'Qui trovi gli strumenti principali per gestire il tuo sito in modo semplice e veloce.',
                'login_logo_url'                 => '',
                'custom_admin_css'               => '',
                'custom_login_css'               => '',
                'hide_wp_logo'                   => 1,
                'hide_wp_version_footer'         => 1,
                'hide_help_tabs'                 => 1,
                'hide_screen_options'            => 1,
                'hide_generator_meta'            => 1,
                'hide_update_notices_non_admins' => 1,
                'hide_wp_dashboard_widgets'      => 1,
                'hide_menus_non_admins'          => 0,
                'enable_privacy_shield'          => 1,
                'shield_admin'                   => 1,
                'shield_login'                   => 1,
                'shield_frontend'                => 0,
                'disable_context_menu'           => 0,
            );
        }

        public static function activate() {
            $stored = get_option( self::OPTION, array() );

            if ( ! is_array( $stored ) ) {
                $stored = array();
            }

            add_option( self::OPTION, wp_parse_args( $stored, self::default_options() ) );
        }

        private function __construct() {
            add_action( 'init', array( $this, 'frontend_cleanup' ) );

            add_action( 'admin_init', array( $this, 'register_settings' ) );
            add_action( 'admin_init', array( $this, 'maybe_hide_update_notices' ) );
            add_action( 'admin_menu', array( $this, 'register_menu' ) );
            add_action( 'admin_menu', array( $this, 'maybe_hide_admin_menus' ), 999 );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
            add_action( 'admin_head', array( $this, 'admin_head_cleanup' ) );
            add_action( 'wp_dashboard_setup', array( $this, 'dashboard_setup' ), 99 );
            add_action( 'admin_bar_menu', array( $this, 'customize_admin_bar' ), 11 );

            add_action( 'login_enqueue_scripts', array( $this, 'enqueue_login_assets' ) );
            add_filter( 'login_headerurl', array( $this, 'login_header_url' ) );
            add_filter( 'login_headertext', array( $this, 'login_header_text' ) );

            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

            add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ), 99 );
            add_filter( 'update_footer', array( $this, 'admin_update_footer' ), 99 );
            add_filter( 'screen_options_show_screen', array( $this, 'screen_options_show_screen' ), 99 );
            add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
        }

        private function options() {
            $stored = get_option( self::OPTION, array() );

            if ( ! is_array( $stored ) ) {
                $stored = array();
            }

            return wp_parse_args( $stored, self::default_options() );
        }

        public function register_settings() {
            register_setting(
                self::OPTION_GROUP,
                self::OPTION,
                array(
                    'type'              => 'array',
                    'sanitize_callback' => array( $this, 'sanitize_options' ),
                    'default'           => self::default_options(),
                )
            );
        }

        public function sanitize_options( $input ) {
            $input    = is_array( $input ) ? $input : array();
            $defaults = self::default_options();
            $out      = array();

            foreach ( array( 'company_name', 'dashboard_title' ) as $key ) {
                $out[ $key ] = isset( $input[ $key ] )
                    ? sanitize_text_field( wp_unslash( $input[ $key ] ) )
                    : $defaults[ $key ];
            }

            $out['footer_text'] = isset( $input['footer_text'] )
                ? wp_kses_post( wp_unslash( $input['footer_text'] ) )
                : $defaults['footer_text'];

            $out['dashboard_widget_text'] = isset( $input['dashboard_widget_text'] )
                ? wp_kses_post( wp_unslash( $input['dashboard_widget_text'] ) )
                : $defaults['dashboard_widget_text'];

            foreach ( array( 'logo_url', 'login_background_image', 'login_logo_url' ) as $key ) {
                $out[ $key ] = isset( $input[ $key ] )
                    ? esc_url_raw( wp_unslash( $input[ $key ] ) )
                    : '';
            }

            foreach ( array( 'primary_color', 'secondary_color', 'accent_color', 'login_background_color' ) as $key ) {
                $value       = isset( $input[ $key ] ) ? sanitize_hex_color( wp_unslash( $input[ $key ] ) ) : '';
                $out[ $key ] = $value ? $value : $defaults[ $key ];
            }

            foreach ( array( 'custom_admin_css', 'custom_login_css' ) as $key ) {
                $value       = isset( $input[ $key ] ) ? sanitize_textarea_field( wp_unslash( $input[ $key ] ) ) : '';
                $value       = str_ireplace( array( '</style', '<script', '</script' ), '', $value );
                $out[ $key ] = $value;
            }

            foreach (
                array(
                    'hide_wp_logo',
                    'hide_wp_version_footer',
                    'hide_help_tabs',
                    'hide_screen_options',
                    'hide_generator_meta',
                    'hide_update_notices_non_admins',
                    'hide_wp_dashboard_widgets',
                    'hide_menus_non_admins',
                    'enable_privacy_shield',
                    'shield_admin',
                    'shield_login',
                    'shield_frontend',
                    'disable_context_menu',
                ) as $key
            ) {
                $out[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
            }

            return wp_parse_args( $out, $defaults );
        }

        public function register_menu() {
            $this->settings_hook = add_options_page(
                'White Label Studio',
                'White Label Studio',
                'manage_options',
                self::SLUG,
                array( $this, 'render_settings_page' )
            );
        }

        public function plugin_action_links( $links ) {
            $url = admin_url( 'options-general.php?page=' . self::SLUG );
            array_unshift( $links, '<a href="' . esc_url( $url ) . '">Impostazioni</a>' );

            return $links;
        }

        public function render_settings_page() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Non hai i permessi per accedere a questa pagina.', 'gf-white-label-studio' ) );
            }

            $opts = $this->options();
            ?>
            <div class="wrap gfwls-settings-wrap">
                <div class="gfwls-hero">
                    <div class="gfwls-hero-content">
                        <p class="gfwls-kicker">White Label WordPress</p>
                        <h1>White Label Studio</h1>
                        <p>Personalizza admin, schermata di login, footer, dashboard e privacy overlay.</p>
                    </div>
                    <div class="gfwls-hero-card">
                        <strong><?php echo esc_html( $opts['company_name'] ); ?></strong>
                        <span>Brand attivo</span>
                    </div>
                </div>

                <?php settings_errors(); ?>

                <form method="post" action="options.php" class="gfwls-settings-form">
                    <?php settings_fields( self::OPTION_GROUP ); ?>

                    <div class="gfwls-grid">
                        <section class="gfwls-card">
                            <h2>Brand</h2>
                            <?php
                            $this->text_field( 'company_name', 'Nome brand / agenzia', 'Il nome mostrato in admin, login e widget dashboard.' );
                            $this->url_media_field( 'logo_url', 'Logo principale', 'URL del logo per admin bar, dashboard e login se non imposti un logo login separato.' );
                            $this->url_media_field( 'login_logo_url', 'Logo login alternativo', 'Facoltativo. Se vuoto viene usato il logo principale.' );
                            $this->url_media_field( 'login_background_image', 'Sfondo immagine login', 'Facoltativo. Consigliato: 1920×1080 o superiore.' );
                            ?>
                        </section>

                        <section class="gfwls-card">
                            <h2>Colori</h2>
                            <?php
                            $this->color_field( 'primary_color', 'Colore primario' );
                            $this->color_field( 'secondary_color', 'Colore secondario' );
                            $this->color_field( 'accent_color', 'Colore accento' );
                            $this->color_field( 'login_background_color', 'Colore sfondo login' );
                            ?>
                        </section>

                        <section class="gfwls-card">
                            <h2>Admin</h2>
                            <?php
                            $this->textarea_field( 'footer_text', 'Testo footer admin', 'Supporta HTML base.' );
                            $this->text_field( 'dashboard_title', 'Titolo widget dashboard' );
                            $this->textarea_field( 'dashboard_widget_text', 'Testo widget dashboard', 'Supporta HTML base.' );
                            $this->checkbox_field( 'hide_wp_logo', 'Nascondi logo WordPress nella admin bar' );
                            $this->checkbox_field( 'hide_wp_version_footer', 'Nascondi versione WordPress nel footer admin' );
                            $this->checkbox_field( 'hide_help_tabs', 'Nascondi tab “Aiuto”' );
                            $this->checkbox_field( 'hide_screen_options', 'Nascondi “Impostazioni schermata”' );
                            $this->checkbox_field( 'hide_update_notices_non_admins', 'Nascondi avvisi aggiornamento agli utenti non amministratori' );
                            $this->checkbox_field( 'hide_wp_dashboard_widgets', 'Nascondi widget dashboard nativi WordPress' );
                            $this->checkbox_field( 'hide_menus_non_admins', 'Nascondi Plugin, Temi, Strumenti e Impostazioni ai non amministratori' );
                            ?>
                        </section>

                        <section class="gfwls-card">
                            <h2>Privacy overlay F12</h2>
                            <p class="description">Intercetta F12 e alcune scorciatoie DevTools mostrando un overlay che maschera la pagina. Non può impedire davvero l’ispezione del codice lato client.</p>
                            <?php
                            $this->checkbox_field( 'enable_privacy_shield', 'Attiva Privacy Shield' );
                            $this->checkbox_field( 'shield_admin', 'Usa in area admin' );
                            $this->checkbox_field( 'shield_login', 'Usa nella schermata login' );
                            $this->checkbox_field( 'shield_frontend', 'Usa nel frontend pubblico' );
                            $this->checkbox_field( 'disable_context_menu', 'Blocca clic destro e mostra overlay' );
                            $this->checkbox_field( 'hide_generator_meta', 'Rimuovi meta generator WordPress dal frontend' );
                            ?>
                        </section>

                        <section class="gfwls-card gfwls-card-wide">
                            <h2>CSS extra</h2>
                            <?php
                            $this->textarea_field( 'custom_admin_css', 'CSS extra admin', 'Solo CSS. Verrà iniettato nelle pagine admin.' );
                            $this->textarea_field( 'custom_login_css', 'CSS extra login', 'Solo CSS. Verrà iniettato nella schermata login.' );
                            ?>
                        </section>
                    </div>

                    <p class="submit gfwls-submit">
                        <?php submit_button( 'Salva impostazioni', 'primary large', 'submit', false ); ?>
                    </p>
                </form>
            </div>
            <?php
        }

        private function field_name( $key ) {
            return self::OPTION . '[' . $key . ']';
        }

        private function text_field( $key, $label, $description = '' ) {
            $opts = $this->options();
            ?>
            <label class="gfwls-field">
                <span><?php echo esc_html( $label ); ?></span>
                <input type="text" name="<?php echo esc_attr( $this->field_name( $key ) ); ?>" value="<?php echo esc_attr( $opts[ $key ] ); ?>" class="regular-text" />
                <?php if ( $description ) : ?>
                    <small><?php echo esc_html( $description ); ?></small>
                <?php endif; ?>
            </label>
            <?php
        }

        private function url_media_field( $key, $label, $description = '' ) {
            $opts     = $this->options();
            $input_id = 'gfwls-' . esc_attr( $key );
            ?>
            <label class="gfwls-field">
                <span><?php echo esc_html( $label ); ?></span>
                <div class="gfwls-media-row">
                    <input id="<?php echo esc_attr( $input_id ); ?>" type="url" name="<?php echo esc_attr( $this->field_name( $key ) ); ?>" value="<?php echo esc_url( $opts[ $key ] ); ?>" class="regular-text" />
                    <button type="button" class="button gfwls-media-select" data-target="#<?php echo esc_attr( $input_id ); ?>">Scegli</button>
                    <button type="button" class="button gfwls-media-clear" data-target="#<?php echo esc_attr( $input_id ); ?>">Rimuovi</button>
                </div>
                <?php if ( ! empty( $opts[ $key ] ) ) : ?>
                    <img class="gfwls-preview" src="<?php echo esc_url( $opts[ $key ] ); ?>" alt="Anteprima" />
                <?php endif; ?>
                <?php if ( $description ) : ?>
                    <small><?php echo esc_html( $description ); ?></small>
                <?php endif; ?>
            </label>
            <?php
        }

        private function color_field( $key, $label ) {
            $opts = $this->options();
            ?>
            <label class="gfwls-field gfwls-color-field">
                <span><?php echo esc_html( $label ); ?></span>
                <input type="color" name="<?php echo esc_attr( $this->field_name( $key ) ); ?>" value="<?php echo esc_attr( $opts[ $key ] ); ?>" />
                <code><?php echo esc_html( $opts[ $key ] ); ?></code>
            </label>
            <?php
        }

        private function textarea_field( $key, $label, $description = '' ) {
            $opts = $this->options();
            ?>
            <label class="gfwls-field">
                <span><?php echo esc_html( $label ); ?></span>
                <textarea name="<?php echo esc_attr( $this->field_name( $key ) ); ?>" rows="6" class="large-text code"><?php echo esc_textarea( $opts[ $key ] ); ?></textarea>
                <?php if ( $description ) : ?>
                    <small><?php echo esc_html( $description ); ?></small>
                <?php endif; ?>
            </label>
            <?php
        }

        private function checkbox_field( $key, $label ) {
            $opts = $this->options();
            ?>
            <label class="gfwls-check">
                <input type="checkbox" name="<?php echo esc_attr( $this->field_name( $key ) ); ?>" value="1" <?php checked( ! empty( $opts[ $key ] ) ); ?> />
                <span><?php echo esc_html( $label ); ?></span>
            </label>
            <?php
        }

        public function enqueue_admin_assets( $hook_suffix ) {
            $opts = $this->options();

            wp_register_style( 'gfwls-admin', false, array(), self::VERSION );
            wp_enqueue_style( 'gfwls-admin' );
            wp_add_inline_style( 'gfwls-admin', $this->admin_css() );

            if ( ! empty( $opts['enable_privacy_shield'] ) && ! empty( $opts['shield_admin'] ) ) {
                $this->enqueue_privacy_shield_script();
            }

            if ( $hook_suffix === $this->settings_hook ) {
                wp_enqueue_media();
                wp_enqueue_script( 'jquery' );
                wp_add_inline_script( 'jquery', $this->settings_page_js(), 'after' );
            }
        }

        public function enqueue_login_assets() {
            $opts = $this->options();

            wp_register_style( 'gfwls-login', false, array(), self::VERSION );
            wp_enqueue_style( 'gfwls-login' );
            wp_add_inline_style( 'gfwls-login', $this->login_css() );

            if ( ! empty( $opts['enable_privacy_shield'] ) && ! empty( $opts['shield_login'] ) ) {
                $this->enqueue_privacy_shield_script();
            }
        }

        public function enqueue_frontend_assets() {
            $opts = $this->options();

            if ( ! empty( $opts['enable_privacy_shield'] ) && ! empty( $opts['shield_frontend'] ) ) {
                $this->enqueue_privacy_shield_script();
            }
        }

        private function enqueue_privacy_shield_script() {
            wp_register_script( 'gfwls-privacy-shield', false, array(), self::VERSION, true );
            wp_enqueue_script( 'gfwls-privacy-shield' );
            wp_add_inline_script( 'gfwls-privacy-shield', $this->privacy_shield_js(), 'after' );
        }

        private function admin_css() {
            $opts      = $this->options();
            $primary   = $this->css_color( $opts['primary_color'] );
            $secondary = $this->css_color( $opts['secondary_color'] );
            $accent    = $this->css_color( $opts['accent_color'] );
            $logo      = esc_url( $opts['logo_url'] );
            $custom    = ! empty( $opts['custom_admin_css'] ) ? $opts['custom_admin_css'] : '';

            $logo_css = '';

            if ( $logo ) {
                $logo_css = '.gfwls-brand-logo{background-image:url("' . $logo . '");}';
            }

            return <<<CSS
:root{
    --gfwls-primary: {$primary};
    --gfwls-secondary: {$secondary};
    --gfwls-accent: {$accent};
    --gfwls-surface:#ffffff;
    --gfwls-bg:#f5f7fb;
    --gfwls-border:#e5e7eb;
    --gfwls-text:#111827;
    --gfwls-muted:#6b7280;
}

html.wp-toolbar,
body.wp-admin{
    overflow-x:hidden;
}

body.wp-admin{
    background:var(--gfwls-bg);
}

body.wp-admin *{
    box-sizing:border-box;
}

#wpadminbar{
    background:linear-gradient(90deg,var(--gfwls-secondary),var(--gfwls-primary));
    box-shadow:0 10px 30px rgba(15,23,42,.18);
}

#wpadminbar .ab-top-menu>li.hover>.ab-item,
#wpadminbar.nojq .quicklinks .ab-top-menu>li>.ab-item:focus,
#wpadminbar:not(.mobile) .ab-top-menu>li:hover>.ab-item,
#wpadminbar:not(.mobile) .ab-top-menu>li>.ab-item:focus{
    background:rgba(255,255,255,.12);
    color:#fff;
}

#wpadminbar #wp-admin-bar-gfwls-brand>.ab-item{
    display:flex;
    align-items:center;
    gap:8px;
    font-weight:700;
    letter-spacing:.01em;
    max-width:260px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.gfwls-brand-logo{
    width:22px;
    height:22px;
    display:inline-block;
    flex:0 0 22px;
    border-radius:7px;
    background:rgba(255,255,255,.18) center/contain no-repeat;
    vertical-align:middle;
}

{$logo_css}

#adminmenuback,
#adminmenuwrap,
#adminmenu{
    background:linear-gradient(180deg,var(--gfwls-secondary) 0%,#111827 52%,#020617 100%);
}

#adminmenu .wp-has-current-submenu .wp-submenu,
#adminmenu .wp-has-current-submenu.opensub .wp-submenu,
#adminmenu .wp-submenu,
#adminmenu a.wp-has-current-submenu:focus+.wp-submenu{
    background:#0b1220;
}

#adminmenu li.menu-top:hover,
#adminmenu li.opensub>a.menu-top,
#adminmenu li>a.menu-top:focus{
    background:rgba(255,255,255,.08);
}

#adminmenu .wp-has-current-submenu .wp-submenu .wp-submenu-head,
#adminmenu .wp-menu-arrow,
#adminmenu .wp-menu-arrow div,
#adminmenu li.current a.menu-top,
#adminmenu li.wp-has-current-submenu a.wp-has-current-submenu{
    background:var(--gfwls-primary);
}

#wpcontent{
    background:radial-gradient(circle at top left,rgba(91,92,246,.10),transparent 32%),var(--gfwls-bg);
    min-height:calc(100vh - 32px);
}

.wrap{
    max-width:100%;
}

.wrap h1,
.wrap h2{
    color:var(--gfwls-text);
    letter-spacing:-.02em;
}

.wp-core-ui .button-primary,
.wp-core-ui .button-primary.focus,
.wp-core-ui .button-primary.hover,
.wp-core-ui .button-primary:focus,
.wp-core-ui .button-primary:hover{
    background:linear-gradient(135deg,var(--gfwls-primary),var(--gfwls-accent));
    border-color:transparent;
    box-shadow:0 10px 22px rgba(91,92,246,.25);
}

.wp-core-ui .button,
.wp-core-ui .button-primary,
.wp-core-ui .button-secondary{
    border-radius:10px;
}

.postbox,
.card,
.welcome-panel,
.notice,
.health-check-accordion,
.plugins .active th,
.plugins .active td{
    border-radius:16px;
    border-color:var(--gfwls-border);
    box-shadow:0 14px 34px rgba(15,23,42,.06);
}

.notice{
    border-left-width:5px;
}

.wp-list-table{
    border-radius:14px;
    overflow:hidden;
}

.gfwls-dashboard-widget{
    display:flex;
    gap:18px;
    align-items:flex-start;
}

.gfwls-dashboard-widget .gfwls-dashboard-logo{
    width:64px;
    height:64px;
    flex:0 0 64px;
    border-radius:20px;
    background:linear-gradient(135deg,var(--gfwls-primary),var(--gfwls-accent));
    box-shadow:0 16px 32px rgba(91,92,246,.24);
    overflow:hidden;
}

.gfwls-dashboard-widget .gfwls-dashboard-logo img{
    width:100%;
    height:100%;
    object-fit:contain;
    background:#fff;
}

.gfwls-dashboard-widget h3{
    margin:.15rem 0 .35rem;
    font-size:20px;
}

.gfwls-dashboard-widget p{
    margin:0;
    color:#4b5563;
    font-size:14px;
    line-height:1.6;
}

.gfwls-settings-wrap{
    width:100%;
    max-width:1220px;
}

.gfwls-hero{
    margin:26px 0 22px;
    padding:clamp(20px,3vw,28px);
    display:flex;
    justify-content:space-between;
    gap:24px;
    align-items:center;
    border-radius:26px;
    color:#fff;
    background:linear-gradient(135deg,var(--gfwls-secondary),var(--gfwls-primary));
    box-shadow:0 22px 50px rgba(15,23,42,.18);
    overflow:hidden;
    position:relative;
}

.gfwls-hero:after{
    content:"";
    width:260px;
    height:260px;
    position:absolute;
    right:-90px;
    top:-110px;
    border-radius:999px;
    background:rgba(255,255,255,.14);
}

.gfwls-hero-content{
    position:relative;
    z-index:1;
    min-width:0;
}

.gfwls-hero h1{
    color:#fff;
    margin:4px 0 8px;
    font-size:clamp(26px,4vw,36px);
    line-height:1.05;
    word-break:break-word;
}

.gfwls-hero p{
    margin:0;
    color:rgba(255,255,255,.82);
    font-size:15px;
    line-height:1.5;
}

.gfwls-kicker{
    text-transform:uppercase;
    letter-spacing:.12em;
    font-size:12px!important;
    font-weight:700;
}

.gfwls-hero-card{
    position:relative;
    z-index:1;
    min-width:210px;
    padding:18px;
    border:1px solid rgba(255,255,255,.22);
    border-radius:20px;
    background:rgba(255,255,255,.12);
    backdrop-filter:blur(14px);
}

.gfwls-hero-card strong,
.gfwls-hero-card span{
    display:block;
}

.gfwls-hero-card strong{
    font-size:18px;
    overflow-wrap:anywhere;
}

.gfwls-hero-card span{
    margin-top:6px;
    color:rgba(255,255,255,.72);
}

.gfwls-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:18px;
}

.gfwls-card{
    min-width:0;
    padding:22px;
    background:#fff;
    border:1px solid var(--gfwls-border);
    border-radius:22px;
    box-shadow:0 16px 36px rgba(15,23,42,.06);
}

.gfwls-card-wide{
    grid-column:1/-1;
}

.gfwls-card h2{
    margin:0 0 16px;
    font-size:18px;
}

.gfwls-field{
    display:block;
    margin:0 0 16px;
}

.gfwls-field>span,
.gfwls-check span{
    display:block;
    margin-bottom:7px;
    color:var(--gfwls-text);
    font-weight:650;
}

.gfwls-field input[type="text"],
.gfwls-field input[type="url"],
.gfwls-field textarea{
    width:100%;
    max-width:100%;
    border-radius:12px;
    border-color:#d1d5db;
}

.gfwls-field textarea{
    min-height:130px;
    resize:vertical;
}

.gfwls-field small,
.gfwls-card .description{
    display:block;
    color:var(--gfwls-muted);
    line-height:1.5;
}

.gfwls-media-row{
    display:flex;
    gap:8px;
    align-items:center;
}

.gfwls-media-row input{
    min-width:0;
    flex:1 1 auto;
}

.gfwls-media-row .button{
    flex:0 0 auto;
}

.gfwls-preview{
    display:block;
    max-width:180px;
    max-height:90px;
    margin-top:10px;
    border-radius:12px;
    border:1px solid var(--gfwls-border);
    background:#f9fafb;
    padding:8px;
}

.gfwls-color-field{
    display:grid;
    grid-template-columns:minmax(0,1fr) 74px 86px;
    gap:12px;
    align-items:center;
}

.gfwls-color-field span{
    margin:0;
}

.gfwls-color-field input[type="color"]{
    width:74px;
    height:42px;
    padding:3px;
    border-radius:12px;
    border:1px solid #d1d5db;
    background:#fff;
}

.gfwls-color-field code{
    overflow:hidden;
    text-overflow:ellipsis;
}

.gfwls-check{
    display:flex;
    gap:10px;
    align-items:flex-start;
    margin:0 0 12px;
}

.gfwls-check span{
    margin:0;
    font-weight:500;
    line-height:1.45;
}

.gfwls-check input{
    margin-top:2px;
    flex:0 0 auto;
}

.gfwls-submit{
    position:sticky;
    bottom:0;
    z-index:20;
    padding:16px 0 18px!important;
    background:linear-gradient(180deg,rgba(245,247,251,0),var(--gfwls-bg) 35%);
}

/* Admin responsive generico */
@media (max-width:1200px){
    .gfwls-settings-wrap{
        max-width:100%;
    }

    .gfwls-grid{
        gap:16px;
    }

    .gfwls-card{
        padding:20px;
    }
}

@media (max-width:960px){
    .gfwls-grid{
        grid-template-columns:1fr;
    }

    .gfwls-hero{
        display:block;
    }

    .gfwls-hero-card{
        width:100%;
        min-width:0;
        margin-top:18px;
    }
}

/* Breakpoint WordPress tablet/mobile admin */
@media (max-width:782px){
    html.wp-toolbar{
        padding-top:46px;
    }

    #wpcontent{
        min-height:calc(100vh - 46px);
        padding-left:10px;
        padding-right:10px;
    }

    .auto-fold #wpcontent{
        padding-left:10px;
    }

    .wrap{
        margin:10px 0 0;
    }

    #wpadminbar{
        height:46px;
    }

    #wpadminbar #wp-admin-bar-gfwls-brand>.ab-item{
        max-width:180px;
    }

    #wpadminbar #wp-admin-bar-gfwls-brand>.ab-item span:last-child{
        display:none;
    }

    .gfwls-brand-logo{
        width:28px;
        height:28px;
        border-radius:10px;
    }

    .gfwls-hero{
        margin:16px 0;
        padding:20px;
        border-radius:22px;
    }

    .gfwls-hero h1{
        font-size:28px;
    }

    .gfwls-hero p{
        font-size:14px;
    }

    .gfwls-card{
        padding:18px;
        border-radius:18px;
    }

    .gfwls-media-row{
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:8px;
    }

    .gfwls-media-row input{
        grid-column:1/-1;
        width:100%;
    }

    .gfwls-media-row .button{
        width:100%;
        text-align:center;
    }

    .gfwls-color-field{
        grid-template-columns:1fr;
        gap:8px;
    }

    .gfwls-color-field input[type="color"]{
        width:100%;
        max-width:140px;
    }

    .gfwls-submit{
        margin-left:-10px;
        margin-right:-10px;
        padding:14px 10px 16px!important;
    }

    .gfwls-submit .button{
        width:100%;
        min-height:46px;
        justify-content:center;
        text-align:center;
    }

    .gfwls-dashboard-widget{
        gap:14px;
    }

    .gfwls-dashboard-widget .gfwls-dashboard-logo{
        width:52px;
        height:52px;
        flex-basis:52px;
        border-radius:16px;
    }

    .gfwls-dashboard-widget h3{
        font-size:18px;
    }

    .wp-list-table{
        border-radius:10px;
    }

    .form-table,
    .form-table tbody,
    .form-table tr,
    .form-table th,
    .form-table td{
        display:block;
        width:100%;
    }

    .form-table th{
        padding-bottom:4px;
    }

    .form-table td{
        padding-top:4px;
    }

    input.regular-text,
    input.large-text,
    textarea.large-text,
    select{
        max-width:100%;
    }
}

@media (max-width:480px){
    #wpcontent{
        padding-left:8px;
        padding-right:8px;
    }

    .wrap{
        margin-top:8px;
    }

    .gfwls-hero{
        padding:18px;
        border-radius:20px;
    }

    .gfwls-hero h1{
        font-size:25px;
    }

    .gfwls-card{
        padding:16px;
        border-radius:16px;
    }

    .gfwls-media-row{
        grid-template-columns:1fr;
    }

    .gfwls-preview{
        width:100%;
        max-width:100%;
        max-height:130px;
        object-fit:contain;
    }

    .gfwls-dashboard-widget{
        display:block;
    }

    .gfwls-dashboard-widget .gfwls-dashboard-logo{
        margin-bottom:12px;
    }

    #wpadminbar #wp-admin-bar-gfwls-brand{
        display:block;
    }
}

{$custom}
CSS;
        }

        private function login_css() {
            $opts        = $this->options();
            $primary     = $this->css_color( $opts['primary_color'] );
            $secondary   = $this->css_color( $opts['secondary_color'] );
            $accent      = $this->css_color( $opts['accent_color'] );
            $bg_color    = $this->css_color( $opts['login_background_color'] );
            $logo        = esc_url( $opts['login_logo_url'] ? $opts['login_logo_url'] : $opts['logo_url'] );
            $bg_image    = esc_url( $opts['login_background_image'] );
            $company     = wp_json_encode( $opts['company_name'] );
            $custom      = ! empty( $opts['custom_login_css'] ) ? $opts['custom_login_css'] : '';

            $bg_image_css = $bg_image
                ? 'background-image:linear-gradient(135deg,rgba(15,23,42,.84),rgba(15,23,42,.68)),url("' . $bg_image . '");'
                : 'background-image:radial-gradient(circle at 20% 20%,rgba(91,92,246,.38),transparent 32%),radial-gradient(circle at 80% 10%,rgba(34,197,94,.24),transparent 28%);';

            if ( $logo ) {
                $logo_css = <<<CSS
.login h1 a{
    width:min(240px,76vw);
    height:92px;
    background-image:url("{$logo}");
    background-size:contain;
    background-position:center;
    background-repeat:no-repeat;
    text-indent:-9999px;
    overflow:hidden;
    margin:0 auto 22px;
}
CSS;
            } else {
                $logo_css = <<<CSS
.login h1 a{
    width:100%;
    height:auto;
    background:none;
    text-indent:0;
    overflow:visible;
    color:#fff;
    font-size:0;
    margin:0 auto 22px;
    text-decoration:none;
}
.login h1 a:before{
    content:{$company};
    display:inline-flex;
    max-width:100%;
    padding:12px 18px;
    border-radius:18px;
    color:#fff;
    font-size:28px;
    line-height:1.08;
    font-weight:800;
    letter-spacing:-.04em;
    background:linear-gradient(135deg,{$primary},{$accent});
    box-shadow:0 18px 42px rgba(0,0,0,.24);
    overflow-wrap:anywhere;
}
CSS;
            }

            return <<<CSS
html,
body.login{
    min-height:100%;
}

body.login{
    min-height:100vh;
    min-height:100dvh;
    margin:0;
    padding:clamp(16px,4vw,32px);
    background-color:{$bg_color};
    {$bg_image_css}
    background-size:cover;
    background-position:center;
    background-attachment:fixed;
    display:grid;
    place-items:center;
    overflow-x:hidden;
}

body.login *{
    box-sizing:border-box;
}

.login #login{
    width:min(430px,100%);
    max-width:100%;
    padding:0;
    margin:0 auto;
}

{$logo_css}

.login form{
    width:100%;
    margin:0;
    padding:clamp(22px,5vw,30px);
    border:1px solid rgba(255,255,255,.22);
    border-radius:28px;
    background:rgba(255,255,255,.92);
    box-shadow:0 28px 80px rgba(0,0,0,.30);
    backdrop-filter:blur(18px);
}

.login label{
    color:{$secondary};
    font-weight:700;
}

.login form .input,
.login input[type="text"],
.login input[type="password"],
.login input[type="email"]{
    width:100%;
    min-height:48px;
    margin-top:6px;
    border-radius:14px;
    border-color:#d1d5db;
    background:#f9fafb;
    box-shadow:none;
    font-size:16px;
}

.login form .input:focus,
.login input[type="text"]:focus,
.login input[type="password"]:focus,
.login input[type="email"]:focus{
    border-color:{$primary};
    box-shadow:0 0 0 4px rgba(91,92,246,.14);
}

.login .forgetmenot{
    display:flex;
    align-items:center;
    min-height:44px;
}

.login .forgetmenot label{
    display:flex;
    align-items:center;
    gap:8px;
    font-size:14px;
}

.login .forgetmenot input[type="checkbox"]{
    margin:0;
}

.login .submit{
    display:flex;
    justify-content:flex-end;
    margin-top:12px;
}

.wp-core-ui .button-primary{
    min-height:44px;
    padding:0 22px;
    border:0;
    border-radius:14px;
    background:linear-gradient(135deg,{$primary},{$accent});
    box-shadow:0 14px 26px rgba(91,92,246,.28);
    font-weight:800;
    text-align:center;
}

.wp-core-ui .button-primary:hover,
.wp-core-ui .button-primary:focus{
    background:linear-gradient(135deg,{$primary},{$accent});
    filter:brightness(1.03);
}

.login #nav,
.login #backtoblog{
    text-align:center;
    margin:18px 0 0;
    padding:0;
}

.login #nav a,
.login #backtoblog a,
.login .privacy-policy-page-link a{
    color:rgba(255,255,255,.86);
    text-decoration:none;
    font-weight:700;
    line-height:1.5;
}

.login #nav a:hover,
.login #backtoblog a:hover,
.login .privacy-policy-page-link a:hover{
    color:#fff;
}

.login .message,
.login .notice,
.login .success{
    width:100%;
    margin:0 0 16px;
    border-radius:16px;
    border-left-color:{$primary};
    box-shadow:0 16px 38px rgba(0,0,0,.14);
}

.language-switcher{
    width:100%;
    margin:18px auto 0;
    text-align:center;
}

.language-switcher form{
    width:100%;
}

.language-switcher select{
    max-width:100%;
    min-height:42px;
    border-radius:12px;
}

.language-switcher .button{
    min-height:42px;
    border-radius:12px;
}

.login .privacy-policy-page-link{
    margin:18px 0 0;
    text-align:center;
}

/* Login responsive */
@media (max-width:600px){
    body.login{
        padding:18px;
        background-attachment:scroll;
        align-items:center;
    }

    .login #login{
        width:100%;
    }

    .login h1 a{
        height:76px;
        margin-bottom:18px;
    }

    .login h1 a:before{
        font-size:23px;
        padding:11px 15px;
        border-radius:16px;
    }

    .login form{
        padding:22px;
        border-radius:22px;
    }

    .login .forgetmenot{
        float:none;
        width:100%;
        margin-bottom:8px;
    }

    .login .submit{
        float:none;
        display:block;
        width:100%;
    }

    .wp-core-ui .button-primary{
        width:100%;
        min-height:48px;
        margin-top:6px;
    }

    .login #nav,
    .login #backtoblog{
        font-size:14px;
    }

    .language-switcher form{
        display:grid;
        grid-template-columns:1fr;
        gap:8px;
    }

    .language-switcher select,
    .language-switcher .button{
        width:100%;
    }
}

@media (max-width:380px){
    body.login{
        padding:12px;
    }

    .login form{
        padding:18px;
        border-radius:20px;
    }

    .login h1 a{
        height:66px;
        margin-bottom:14px;
    }

    .login h1 a:before{
        font-size:20px;
        padding:10px 13px;
    }

    .login #nav,
    .login #backtoblog,
    .login .privacy-policy-page-link{
        font-size:13px;
    }
}

@media (max-height:620px){
    body.login{
        align-items:start;
        padding-top:14px;
        padding-bottom:14px;
    }

    .login h1 a{
        height:58px;
        margin-bottom:12px;
    }

    .login form{
        padding:18px;
    }

    .login #nav,
    .login #backtoblog,
    .language-switcher,
    .login .privacy-policy-page-link{
        margin-top:10px;
    }
}

{$custom}
CSS;
        }

        private function settings_page_js() {
            return <<<JS
(function($){
    'use strict';

    $('.gfwls-media-select').on('click', function(e){
        e.preventDefault();

        var target = $($(this).data('target'));

        var frame = wp.media({
            title: 'Scegli immagine',
            button: { text: 'Usa questa immagine' },
            multiple: false
        });

        frame.on('select', function(){
            var attachment = frame.state().get('selection').first().toJSON();

            if (attachment && attachment.url) {
                target.val(attachment.url).trigger('change');
            }
        });

        frame.open();
    });

    $('.gfwls-media-clear').on('click', function(e){
        e.preventDefault();
        $($(this).data('target')).val('').trigger('change');
    });
})(jQuery);
JS;
        }

        private function privacy_shield_js() {
            $opts                 = $this->options();
            $company              = wp_json_encode( $opts['company_name'] );
            $primary              = wp_json_encode( $opts['primary_color'] );
            $accent               = wp_json_encode( $opts['accent_color'] );
            $disable_context_menu = ! empty( $opts['disable_context_menu'] ) ? 'true' : 'false';

            return <<<JS
(function(){
    'use strict';

    if (window.GFWLSPrivacyShield) {
        return;
    }

    window.GFWLSPrivacyShield = true;

    var settings = {
        company: {$company},
        primary: {$primary},
        accent: {$accent},
        disableContextMenu: {$disable_context_menu}
    };

    function addStyles(){
        if (document.getElementById('gfwls-privacy-style')) {
            return;
        }

        var style = document.createElement('style');
        style.id = 'gfwls-privacy-style';
        style.textContent = '' +
            'html.gfwls-shield-active body>*:not(#gfwls-privacy-shield){filter:blur(16px)!important;pointer-events:none!important;user-select:none!important;}' +
            '#gfwls-privacy-shield{box-sizing:border-box;position:fixed;inset:0;z-index:2147483647;display:none;align-items:center;justify-content:center;padding:24px;background:rgba(2,6,23,.78);backdrop-filter:blur(18px);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}' +
            'html.gfwls-shield-active #gfwls-privacy-shield{display:flex!important;}' +
            '#gfwls-privacy-shield *{box-sizing:border-box;}' +
            '#gfwls-privacy-shield .gfwls-privacy-card{max-width:520px;width:100%;padding:clamp(22px,5vw,30px);border-radius:28px;color:#fff;text-align:center;background:linear-gradient(135deg,rgba(255,255,255,.18),rgba(255,255,255,.08));border:1px solid rgba(255,255,255,.24);box-shadow:0 30px 90px rgba(0,0,0,.38);}' +
            '#gfwls-privacy-shield .gfwls-privacy-badge{width:68px;height:68px;margin:0 auto 18px;border-radius:24px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,' + settings.primary + ',' + settings.accent + ');box-shadow:0 18px 42px rgba(0,0,0,.28);font-size:30px;}' +
            '#gfwls-privacy-shield h2{margin:0 0 10px;color:#fff;font-size:clamp(23px,6vw,28px);line-height:1.1;overflow-wrap:anywhere;}' +
            '#gfwls-privacy-shield p{margin:0 auto 20px;color:rgba(255,255,255,.78);font-size:15px;line-height:1.6;}' +
            '#gfwls-privacy-shield button{appearance:none;border:0;border-radius:14px;padding:12px 18px;background:#fff;color:#111827;font-weight:800;cursor:pointer;min-height:44px;}' +
            '#gfwls-privacy-shield small{display:block;margin-top:14px;color:rgba(255,255,255,.55);}' +
            '@media(max-width:480px){#gfwls-privacy-shield{padding:16px;}#gfwls-privacy-shield .gfwls-privacy-card{border-radius:22px;}#gfwls-privacy-shield .gfwls-privacy-badge{width:58px;height:58px;border-radius:20px;font-size:26px;}#gfwls-privacy-shield button{width:100%;}}';

        document.head.appendChild(style);
    }

    function ensureOverlay(){
        addStyles();

        var overlay = document.getElementById('gfwls-privacy-shield');

        if (overlay) {
            return overlay;
        }

        overlay = document.createElement('div');
        overlay.id = 'gfwls-privacy-shield';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.innerHTML = '<div class="gfwls-privacy-card"><div class="gfwls-privacy-badge">🔒</div><h2>' + escapeHtml(settings.company) + '</h2><p>Privacy Shield attivo. La pagina è stata mascherata per proteggere la visualizzazione durante l’apertura degli strumenti sviluppatore.</p><button type="button" id="gfwls-privacy-close">Mostra di nuovo</button><small>Premi ESC per chiudere l’overlay.</small></div>';

        document.body.appendChild(overlay);

        var close = document.getElementById('gfwls-privacy-close');

        if (close) {
            close.addEventListener('click', hideShield);
        }

        return overlay;
    }

    function escapeHtml(value){
        return String(value || '').replace(/[&<>'"]/g, function(ch){
            return ({
                '&':'&amp;',
                '<':'&lt;',
                '>':'&gt;',
                "'":'&#039;',
                '"':'&quot;'
            })[ch];
        });
    }

    function showShield(){
        ensureOverlay();
        document.documentElement.classList.add('gfwls-shield-active');
    }

    function hideShield(){
        document.documentElement.classList.remove('gfwls-shield-active');
    }

    function isDevToolsShortcut(event){
        var key = String(event.key || '').toLowerCase();
        var code = event.keyCode || event.which;

        return code === 123 ||
            (event.ctrlKey && event.shiftKey && ['i','j','c','k'].indexOf(key) !== -1) ||
            (event.metaKey && event.altKey && ['i','j','c','k'].indexOf(key) !== -1) ||
            (event.ctrlKey && key === 'u') ||
            (event.metaKey && key === 'u');
    }

    document.addEventListener('keydown', function(event){
        if (isDevToolsShortcut(event)) {
            event.preventDefault();
            event.stopPropagation();
            showShield();
            return false;
        }

        if (String(event.key || '').toLowerCase() === 'escape') {
            hideShield();
        }
    }, true);

    if (settings.disableContextMenu) {
        document.addEventListener('contextmenu', function(event){
            event.preventDefault();
            event.stopPropagation();
            showShield();
            return false;
        }, true);
    }
})();
JS;
        }

        private function css_color( $value ) {
            $color = sanitize_hex_color( $value );

            return $color ? $color : '#111827';
        }

        public function customize_admin_bar( $wp_admin_bar ) {
            if ( ! is_admin_bar_showing() ) {
                return;
            }

            $opts = $this->options();

            if ( ! empty( $opts['hide_wp_logo'] ) ) {
                $wp_admin_bar->remove_node( 'wp-logo' );
            }

            if ( ! current_user_can( 'update_core' ) && ! empty( $opts['hide_update_notices_non_admins'] ) ) {
                $wp_admin_bar->remove_node( 'updates' );
            }

            $title = '<span class="gfwls-brand-logo"></span><span>' . esc_html( $opts['company_name'] ) . '</span>';

            $wp_admin_bar->add_node(
                array(
                    'id'    => 'gfwls-brand',
                    'title' => $title,
                    'href'  => admin_url(),
                    'meta'  => array(
                        'title' => esc_attr( $opts['company_name'] ),
                    ),
                )
            );
        }

        public function admin_footer_text( $text ) {
            $opts = $this->options();

            if ( ! empty( $opts['footer_text'] ) ) {
                return wp_kses_post( $opts['footer_text'] );
            }

            return $text;
        }

        public function admin_update_footer( $text ) {
            $opts = $this->options();

            if ( ! empty( $opts['hide_wp_version_footer'] ) ) {
                return '';
            }

            return $text;
        }

        public function screen_options_show_screen( $show ) {
            $opts = $this->options();

            if ( ! empty( $opts['hide_screen_options'] ) ) {
                return false;
            }

            return $show;
        }

        public function admin_head_cleanup() {
            $opts = $this->options();

            if ( ! empty( $opts['hide_help_tabs'] ) && function_exists( 'get_current_screen' ) ) {
                $screen = get_current_screen();

                if ( $screen && method_exists( $screen, 'remove_help_tabs' ) ) {
                    $screen->remove_help_tabs();
                }
            }
        }

        public function maybe_hide_update_notices() {
            $opts = $this->options();

            if ( empty( $opts['hide_update_notices_non_admins'] ) || current_user_can( 'update_core' ) ) {
                return;
            }

            remove_action( 'admin_notices', 'update_nag', 3 );
            remove_action( 'network_admin_notices', 'update_nag', 3 );
        }

        public function maybe_hide_admin_menus() {
            $opts = $this->options();

            if ( empty( $opts['hide_menus_non_admins'] ) || current_user_can( 'manage_options' ) ) {
                return;
            }

            remove_menu_page( 'plugins.php' );
            remove_menu_page( 'themes.php' );
            remove_menu_page( 'tools.php' );
            remove_menu_page( 'options-general.php' );
        }

        public function dashboard_setup() {
            $opts = $this->options();

            if ( ! empty( $opts['hide_wp_dashboard_widgets'] ) ) {
                remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
                remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
                remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
                remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' );
                remove_meta_box( 'dashboard_site_health', 'dashboard', 'normal' );
            }

            wp_add_dashboard_widget(
                'gfwls_brand_dashboard_widget',
                esc_html( $opts['dashboard_title'] ),
                array( $this, 'render_dashboard_widget' )
            );
        }

        public function render_dashboard_widget() {
            $opts = $this->options();
            ?>
            <div class="gfwls-dashboard-widget">
                <div class="gfwls-dashboard-logo">
                    <?php if ( ! empty( $opts['logo_url'] ) ) : ?>
                        <img src="<?php echo esc_url( $opts['logo_url'] ); ?>" alt="<?php echo esc_attr( $opts['company_name'] ); ?>" />
                    <?php endif; ?>
                </div>
                <div>
                    <h3><?php echo esc_html( $opts['company_name'] ); ?></h3>
                    <p><?php echo wp_kses_post( $opts['dashboard_widget_text'] ); ?></p>
                </div>
            </div>
            <?php
        }

        public function login_header_url() {
            return home_url( '/' );
        }

        public function login_header_text() {
            $opts = $this->options();

            return $opts['company_name'];
        }

        public function frontend_cleanup() {
            $opts = $this->options();

            if ( empty( $opts['hide_generator_meta'] ) ) {
                return;
            }

            remove_action( 'wp_head', 'wp_generator' );
            add_filter( 'the_generator', '__return_empty_string' );
        }
    }
}

register_activation_hook( __FILE__, array( 'GFWLS_White_Label_Studio', 'activate' ) );
add_action( 'plugins_loaded', array( 'GFWLS_White_Label_Studio', 'instance' ) );
