<?php
/*
 * Plugin Name:       YourGPT Chatbot - AI chatbot, AI helpdesk, AI Automation and more
 * Plugin URI:        https://yourgpt.ai/chatbot
 * Description:       YourGPT chatbot for your WordPress Website. Take your WordPress Site to the next level with AI Chatbot - 24/7 AI Assistant Chatbot for Customer Support!
 * Version:           1.0.6
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            YourGPT Team
 * Author URI:        https://yourgpt.ai
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       your-ai-chatbot
 */
if (!defined('ABSPATH')) {
    header("Location: /wordpress");
    die("");
}
define('PLUGIN_PATH', plugin_dir_path(__FILE__));
// include PLUGIN_PATH . "includes/activation.php";
include PLUGIN_PATH . "includes/ajax.php";
add_action("wp_enqueue_scripts", "my_js_script");
add_action("admin_enqueue_scripts", "my_admin_js_script");

function my_js_script()
{
    // The chatbot widget is injected in wp_footer and needs no plugin assets on the public site.
    wp_enqueue_script("jquery");
}
;
function my_admin_js_script($hook)
{
    // Version by file mtime so admins never see a stale stylesheet after an update.
    // The stylesheet is admin-wide (it also styles the "setup now" notice on plugins.php);
    // the settings-page script is only loaded on the plugin's own settings screen.
    wp_enqueue_style("test_css", plugin_dir_url(__FILE__) . "assets/css/style.css", array(), filemtime(PLUGIN_PATH . "assets/css/style.css"), false);

    if ($hook !== 'settings_page_add-apiKey') {
        return;
    }

    wp_enqueue_script("test_js", plugin_dir_url(__FILE__) . "assets/js/custom.js", array('jquery'), filemtime(PLUGIN_PATH . "assets/js/custom.js"), false);
    wp_localize_script(
        'test_js',
        'getOption',
        array(
            'widget_uid' => get_option("widget_uid"),
            'ajaxurl' => admin_url('admin-ajax.php')
        )
    );
}
/**
 * Legacy search widget (pre-1.0.6).
 *
 * The Search Widget settings tab was removed in 1.0.6 — search is now a layout of the
 * main widget, configured from the YourGPT Dashboard. Sites that saved a search widget
 * UID before upgrading keep working: if the option is still in the database we inject
 * the old script exactly as before. There is no UI for this; it can be dropped later.
 */
function ygc_legacy_search_widget_script()
{
    $search_widget_id = get_option('search_widget_id');
    if (empty($search_widget_id)) {
        return;
    }
    $search_widget_type = get_option('search_widget_type') ? get_option('search_widget_type') : 'floating';

    echo '<script>
  window.YGC_SEARCH_WIDGET = {
    id: "'.esc_js($search_widget_id).'",
    type: "'.esc_js($search_widget_type).'"
  };
  (function(){
    var script=document.createElement("script");
    script.src="https://search-widget.yourgpt.ai/script.js";
    script.id="ygc-search-widget-script";
    document.body.appendChild(script);
  })();
  </script>';
}

function add_html_script_to_frontend()
{
    // Load main chatbot widget
    echo '<script>
  window.YGC_WIDGET_ID="'.esc_js(get_option("widget_uid")).'";
  (function(){
    var script=document.createElement("script");
    script.src="https://widget.yourgpt.ai/script.js";
    script.id="yourgpt-chatbot";
    document.body.appendChild(script);
  })();
  </script>';

    ygc_legacy_search_widget_script();
}

function add_html_script_to_admin()
{
    // Load chatbot widget if enabled for admin
    $chatbot_admin_enabled = get_option('chatbot_admin_enabled');
    if ($chatbot_admin_enabled == '1') {
        echo '<script>
  window.YGC_WIDGET_ID="'.esc_js(get_option("widget_uid")).'";
  (function(){
    var script=document.createElement("script");
    script.src="https://widget.yourgpt.ai/script.js";
    script.id="yourgpt-chatbot";
    document.body.appendChild(script);
  })();
  </script>';
    }

    // Legacy search widget on admin pages, only if the user had enabled it before 1.0.6
    if (get_option('search_admin_enabled') == '1') {
        ygc_legacy_search_widget_script();
    }
}

add_action('admin_footer', 'add_html_script_to_admin');
add_action("wp_footer", "add_html_script_to_frontend");
function my_openai_update_notice()
{
    global $pagenow;
    if (!esc_html(get_option("widget_uid")) && $pagenow === 'plugins.php') {
        ?>
        <div class="add-apiKey">
            <h2 class="title">Please setup your chatbot</h2>
            <div>
                <a class="add-api-btn" href="options-general.php?page=add-apiKey">setup now</a>
            </div>
        </div>
        <?php
    }
}
add_action('admin_notices', 'my_openai_update_notice');

add_action('admin_menu', 'plugin_menu');
add_action('admin_menu', 'plugin_menu_process');
function plugin_menu()
{

    add_submenu_page('options-general.php', 'YourGPT Chatbot', 'YourGPT Chatbot', 'manage_options', 'add-apiKey', 'plugin_menu_option_func');
}
;

function plugin_menu_process()
{
    register_setting('plugin_option_group', 'plugin_option_name', array(
        'sanitize_callback' => 'sanitize_text_field'
    ));
    if (isset($_POST['action']) && sanitize_text_field(wp_unslash($_POST['action'])) === 'save_ygc_settings' && current_user_can('manage_options')) {
        // Verify nonce
        if (!isset($_POST['ygc_settings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ygc_settings_nonce'])), 'ygc_settings_action')) {
            wp_die('Security check failed');
        }

        // Widget UID is required
        $widget_uid = isset($_POST['widget_uid']) ? trim(sanitize_text_field(wp_unslash($_POST['widget_uid']))) : '';
        if ($widget_uid === '') {
            wp_safe_redirect(add_query_arg(array('page' => 'add-apiKey', 'ygc-error' => 'missing-uid'), admin_url('options-general.php')));
            exit;
        }
        if (!ygc_is_valid_widget_uid($widget_uid)) {
            wp_safe_redirect(add_query_arg(array('page' => 'add-apiKey', 'ygc-error' => 'invalid-uid'), admin_url('options-general.php')));
            exit;
        }
        update_option('widget_uid', $widget_uid);

        // Save chatbot admin display option (checkbox is absent from POST when unchecked)
        $chatbot_admin_value = isset($_POST['chatbot_admin_enabled']) && sanitize_text_field(wp_unslash($_POST['chatbot_admin_enabled'])) === '1' ? '1' : '0';
        update_option('chatbot_admin_enabled', $chatbot_admin_value);

        // Redirect back with success message
        $redirect_url = add_query_arg(
            array(
                'page' => 'add-apiKey',
                'settings-updated' => 'true'
            ),
            admin_url('options-general.php')
        );
        wp_safe_redirect($redirect_url);
        exit;
    }
}
;

function ygc_icon($name)
{
    $icons = array(
        'youtube'  => '<path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"/><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02" fill="currentColor" stroke="none"/>',
        // Discord brand mark (from the YourGPT dashboard assets); filled, not stroked
        'discord'  => array(
            'viewBox' => '0 0 100 100',
            'filled'  => true,
            'body'    => '<path d="M84.7832 18.4188C96.077 34.9812 101.654 53.6631 99.5695 75.1703C99.5607 75.2613 99.5132 75.3449 99.4382 75.3997C90.8857 81.6636 82.5995 85.4651 74.4295 87.9861C74.3659 88.0054 74.2978 88.0043 74.2348 87.9831C74.1718 87.9619 74.1171 87.9215 74.0783 87.8677C72.1908 85.2482 70.4758 82.4865 68.9733 79.5865C68.887 79.4157 68.9658 79.2099 69.1433 79.1426C71.867 78.119 74.457 76.8922 76.9483 75.4396C77.1445 75.3249 77.157 75.0444 76.9757 74.9098C76.447 74.5183 75.9233 74.1068 75.422 73.6954C75.3283 73.6193 75.202 73.6044 75.0958 73.6555C58.922 81.1051 41.2046 81.1051 24.8396 73.6555C24.7333 73.6081 24.6071 73.6243 24.5158 73.6991C24.0158 74.1106 23.4908 74.5183 22.9671 74.9098C22.7858 75.0444 22.8008 75.3249 22.9983 75.4396C25.4896 76.8647 28.0796 78.119 30.7996 79.1476C30.9758 79.2149 31.0596 79.4157 30.9721 79.5865C29.5021 82.4903 27.7871 85.2519 25.8646 87.8714C25.7808 87.9774 25.6433 88.026 25.5133 87.9861C17.3821 85.4651 9.09586 81.6636 0.543378 75.3997C0.472128 75.3449 0.420878 75.2576 0.413378 75.1666C-1.32912 56.5632 2.22212 37.7266 15.1859 18.415C15.2171 18.3639 15.2646 18.324 15.3196 18.3003C21.6983 15.3803 28.5321 13.2321 35.6746 12.0053C35.8046 11.9853 35.9346 12.0452 36.0021 12.1599C36.8846 13.7184 37.8933 15.717 38.5758 17.3503C46.1046 16.2032 53.7508 16.2032 61.437 17.3503C62.1195 15.7519 63.0933 13.7184 63.972 12.1599C64.0033 12.103 64.0518 12.0574 64.1106 12.0296C64.1695 12.0018 64.2356 11.9933 64.2995 12.0053C71.4458 13.2358 78.2795 15.3841 84.6532 18.3003C84.7095 18.324 84.7557 18.3639 84.7832 18.4188ZM42.4033 53.7903C42.4821 48.2907 38.4621 43.7399 33.4158 43.7399C28.4108 43.7399 24.4296 48.2508 24.4296 53.7903C24.4296 59.3286 28.4896 63.8395 33.4158 63.8395C38.4221 63.8395 42.4033 59.3286 42.4033 53.7903ZM75.6307 53.7903C75.7095 48.2907 71.6895 43.7399 66.6445 43.7399C61.6383 43.7399 57.657 48.2508 57.657 53.7903C57.657 59.3286 61.717 63.8395 66.6445 63.8395C71.6895 63.8395 75.6307 59.3286 75.6307 53.7903Z" fill="currentColor"/>',
        ),
        'book'     => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
        'headset'  => '<path d="M3 14v-3a9 9 0 0 1 18 0v3"/><path d="M21 16a2 2 0 0 1-2 2h-1v-6h1a2 2 0 0 1 2 2z"/><path d="M3 16a2 2 0 0 0 2 2h1v-6H5a2 2 0 0 0-2 2z"/>',
        'info'     => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
        'save'     => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>',
        'external' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
        'replay'   => '<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>',
        'x'        => '<path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" fill="currentColor" stroke="none"/>',
    );

    if (!isset($icons[$name])) {
        return;
    }

    $icon = is_array($icons[$name]) ? $icons[$name] : array('body' => $icons[$name]);
    $view = isset($icon['viewBox']) ? $icon['viewBox'] : '0 0 24 24';
    $attr = !empty($icon['filled'])
        ? 'fill="currentColor"'
        : 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';

    echo '<svg class="ygc-icon" viewBox="' . esc_attr($view) . '" ' . $attr . ' aria-hidden="true" focusable="false">' . $icon['body'] . '</svg>';
}

function plugin_menu_option_func()
{
    $widget_uid    = get_option('widget_uid');
    $dashboard_url = 'https://chatbot.yourgpt.ai/dashboard';
    $docs_url      = 'https://docs.yourgpt.ai/chatbot/integrations/website-builders/wordpress';
    $discord_url   = 'https://discord.com/invite/57C9uTkD6g';
    $x_url         = 'https://x.com/YourGPTAI';
    $youtube_url   = 'https://www.youtube.com/@YourGPTAI';
    $site_url      = 'https://yourgpt.ai/';
    // 'id' = the Short shown in the card; 'full' = the long tutorial offered when the Short ends
    $videos        = array(
        'wordpress'   => array('label' => 'WordPress',   'id' => '8_5Ajv5hJLo', 'full' => 'yFmkFaCqVhs'),
        'woocommerce' => array('label' => 'WooCommerce', 'id' => 'XopLevrMNE0', 'full' => 'f6ALvUvKgxw'),
    );

    // Show success / error message if redirected after save
    $ygc_error = isset($_GET['ygc-error']) ? sanitize_text_field(wp_unslash($_GET['ygc-error'])) : '';
    if (isset($_GET['settings-updated']) && sanitize_text_field(wp_unslash($_GET['settings-updated'])) === 'true') {
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
    } elseif ($ygc_error === 'missing-uid') {
        echo '<div class="notice notice-error is-dismissible"><p>Widget UID is required.</p></div>';
    } elseif ($ygc_error === 'invalid-uid') {
        echo '<div class="notice notice-error is-dismissible"><p>Invalid Widget UID. Copy it from YourGPT Dashboard &rarr; Integrations.</p></div>';
    }
    ?>
    <?php settings_errors(); ?>
    <div class="ygc-wrap">

        <div class="ygc-topbar">
            <div>
                <h1 class="ygc-topbar__title">YourGPT Chatbot</h1>
                <p class="ygc-topbar__subtitle">Configure and customize your AI-powered chatbot widget.</p>
            </div>
            <div class="ygc-topbar__actions">
                <a class="ygc-btn ygc-btn--ghost" href="<?php echo esc_url($youtube_url); ?>" target="_blank" rel="noopener noreferrer">
                    <?php ygc_icon('youtube'); ?><span>YouTube</span>
                </a>
                <a class="ygc-btn ygc-btn--ghost" href="<?php echo esc_url($x_url); ?>" target="_blank" rel="noopener noreferrer">
                    <?php ygc_icon('x'); ?><span>Follow on X</span>
                </a>
            </div>
        </div>

        <div class="ygc-grid">

            <div class="ygc-main">

            <div class="ygc-card ygc-card--form">
                <h2 class="ygc-card__title">AI Chatbot Widget</h2>
                <p class="ygc-card__subtitle">Engage your website visitors 24/7 with an AI-powered chatbot.</p>

                <form id="ajax_form" method="post" novalidate>
                    <?php wp_nonce_field('ygc_settings_action', 'ygc_settings_nonce'); ?>
                    <input type="hidden" name="action" value="save_ygc_settings">

                    <div class="ygc-field">
                        <label class="ygc-field__label" for="widgetUID">
                            Widget UID
                            <span class="ygc-hint" title="The unique ID of the chatbot widget you want to show on this site."><?php ygc_icon('info'); ?></span>
                        </label>
                        <input type="text" id="widgetUID" name="widget_uid" class="ygc-input" spellcheck="false" autocomplete="off"
                            placeholder="e.g. 1a2b3c4d-5e6f-4a8b-9c0d-1e2f3a4b5c6d"
                            pattern="[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}"
                            title="Widget UID must look like 1a2b3c4d-5e6f-4a8b-9c0d-1e2f3a4b5c6d."
                            value="<?php echo esc_attr($widget_uid); ?>" required aria-describedby="widgetUID-error">
                        <p class="ygc-field__error" id="widgetUID-error" role="alert" hidden></p>
                        <p class="ygc-field__help">
                            Get your Widget UID from:
                            <a href="<?php echo esc_url($dashboard_url); ?>" target="_blank" rel="noopener noreferrer">YourGPT Dashboard</a>
                            &rarr; Integrations &rarr; Copy Widget UID
                        </p>
                    </div>

                    <hr class="ygc-divider">

                    <h3 class="ygc-section-title">Display Settings</h3>
                    <label class="ygc-check" for="chatbotAdminEnabled">
                        <input type="checkbox" id="chatbotAdminEnabled" name="chatbot_admin_enabled" value="1" <?php checked(get_option('chatbot_admin_enabled'), '1'); ?>>
                        <span>
                            <strong>Show chatbot on admin pages</strong>
                            <em>Enable to display the chatbot in WordPress admin area.</em>
                        </span>
                    </label>

                    <div class="ygc-callout">
                        <span class="ygc-callout__icon"><?php ygc_icon('info'); ?></span>
                        <div>
                            <strong>Good to know</strong>
                            <p>
                                The chatbot appears on all frontend pages automatically once you save the Widget UID.
                                Widget appearance and layout are configured from the
                                <a href="<?php echo esc_url($dashboard_url); ?>" target="_blank" rel="noopener noreferrer">YourGPT Dashboard</a>
                                &rarr; <strong>Widget</strong> &rarr; <strong>Layout</strong>.
                            </p>
                        </div>
                    </div>

                    <button type="submit" name="submit" class="ygc-btn ygc-btn--primary">
                        <?php ygc_icon('save'); ?><span>Save Chatbot Settings</span>
                    </button>
                </form>
            </div>

                <div class="ygc-card">
                    <h2 class="ygc-card__title ygc-card__title--sm"><?php ygc_icon('headset'); ?> Need help?</h2>
                    <p class="ygc-card__subtitle">Check out our documentation or contact our support team.</p>
                    <div class="ygc-side__actions">
                        <a class="ygc-btn ygc-btn--ghost" href="<?php echo esc_url($docs_url); ?>" target="_blank" rel="noopener noreferrer">
                            <?php ygc_icon('book'); ?><span>View Documentation</span>
                        </a>
                        <a class="ygc-btn ygc-btn--ghost" href="<?php echo esc_url($discord_url); ?>" target="_blank" rel="noopener noreferrer">
                            <?php ygc_icon('discord'); ?><span>Join Discord</span>
                        </a>
                    </div>
                </div>

            </div>

            <div class="ygc-side">

                <div class="ygc-card ygc-card--video">
                    <div class="ygc-card__head">
                        <h2 class="ygc-card__title ygc-card__title--sm">See it in action</h2>
                        <div class="ygc-tabs" role="tablist" aria-label="Demo video">
                            <?php $first = true; foreach ($videos as $key => $video) : ?>
                                <button type="button" class="ygc-tab<?php echo $first ? ' is-active' : ''; ?>" role="tab"
                                    data-tab="<?php echo esc_attr($key); ?>" aria-selected="<?php echo $first ? 'true' : 'false'; ?>">
                                    <?php echo esc_html($video['label']); ?>
                                </button>
                            <?php $first = false; endforeach; ?>
                        </div>
                    </div>
                    <div class="ygc-video-stage">
                        <?php $first = true; foreach ($videos as $key => $video) :
                            $embed    = 'https://www.youtube-nocookie.com/embed/' . rawurlencode($video['id']) . '?rel=0&modestbranding=1&enablejsapi=1';
                            $full_url = 'https://www.youtube.com/watch?v=' . rawurlencode($video['full']); ?>
                            <div class="ygc-video<?php echo $first ? ' is-active' : ''; ?>"
                                data-tab="<?php echo esc_attr($key); ?>" role="tabpanel"<?php echo $first ? '' : ' hidden'; ?>>
                                <iframe
                                    <?php echo $first ? 'src' : 'data-src'; ?>="<?php echo esc_url($embed); ?>"
                                    title="<?php echo esc_attr($video['label']); ?> demo"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                    referrerpolicy="strict-origin-when-cross-origin"
                                    allowfullscreen
                                    loading="lazy"></iframe>
                                <!-- Shown when the Short finishes: offer the full-length tutorial -->
                                <div class="ygc-video__end" hidden>
                                    <p class="ygc-video__end-title">Want the full walkthrough?</p>
                                    <p class="ygc-video__end-text">Step-by-step setup for <?php echo esc_html($video['label']); ?>.</p>
                                    <a class="ygc-btn ygc-btn--primary" href="<?php echo esc_url($full_url); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php ygc_icon('youtube'); ?><span>Watch full tutorial</span>
                                    </a>
                                    <button type="button" class="ygc-video__replay"><?php ygc_icon('replay'); ?><span>Replay</span></button>
                                </div>
                            </div>
                        <?php $first = false; endforeach; ?>
                    </div>
                    <?php $first = true; foreach ($videos as $key => $video) : ?>
                        <p class="ygc-video__more" data-tab="<?php echo esc_attr($key); ?>"<?php echo $first ? '' : ' hidden'; ?>>
                            Prefer the full tutorial?
                            <a href="<?php echo esc_url('https://www.youtube.com/watch?v=' . rawurlencode($video['full'])); ?>" target="_blank" rel="noopener noreferrer">Watch the full video <?php ygc_icon('external'); ?></a>
                        </p>
                    <?php $first = false; endforeach; ?>
                </div>

            </div>
        </div>

        <p class="ygc-colophon">
            Thank you for creating with
            <a href="<?php echo esc_url($site_url); ?>" target="_blank" rel="noopener noreferrer">YourGPT</a>.
        </p>
    </div>
    <?php
}

function my_plugin_activation()
{
    if (!esc_html(get_option("widget_uid"))) {
        delete_option('widget_uid');
    }
    add_option('widget_uid');

    // Initialize chatbot admin display option
    add_option('chatbot_admin_enabled', '0');
}

register_activation_hook(__FILE__, 'my_plugin_activation');

function my_plugin_deactivation()
{
    delete_option('widget_uid');
    delete_option('chatbot_admin_enabled');

    // Clean up legacy search widget options (search layout is now configured from the YourGPT dashboard)
    delete_option('search_widget_id');
    delete_option('search_widget_type');
    delete_option('search_admin_enabled');
}

register_deactivation_hook(__FILE__, 'my_plugin_deactivation');
