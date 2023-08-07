<?php
/*
 * Plugin Name:       YourGPT Chatbot
 * Plugin URI:        https://yourgpt.ai/chatbot
 * Description:       ChatGPT chatbot for your WordPress Website. Take your WordPress Site to the next level with YourGPT Chatbot - 24/7 AI Assistant Chatbot for Customer Support!
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            YourGPT Team
 * Author URI:        https://yourgpt.ai
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://yourgpt.ai/chatbot/
 * Text Domain:       YourGPT Chatbot
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
    wp_enqueue_script("jquery");
    wp_enqueue_style("test_css", plugin_dir_url(__FILE__) . "assets/css/style.css");
    wp_enqueue_script("test_js", plugin_dir_url(__FILE__) . "assets/js/custom.js", array(), '1.0.0', false);
    wp_localize_script(
        'test_js',
        'getOption',
        array(
            'project_uid' => get_option("project_uid"),
            'widget_uid' => get_option("widget_uid")
        )
    );
}
;
function my_admin_js_script()
{
    wp_enqueue_script("test_js", plugin_dir_url(__FILE__) . "assets/js/custom.js", array(), '1.1.7', false);
    wp_enqueue_style("test_css", plugin_dir_url(__FILE__) . "assets/css/style.css", array(), '1.2.4', false);
    wp_localize_script(
        'test_js',
        'getOption',
        array(
            'project_uid' => get_option("project_uid"),
            'widget_uid' => get_option("widget_uid")
        )
    );
}
function add_html_script_to_admin()
{
    echo '<script id="yourgpt-chatbot"></script>';
    echo '
    <script type="text/javascript">
        window.YOURGPT_PROJECT_UID="' . esc_js(get_option("project_uid")) . '";
        window.YOURGPT_WIDGET_UID="' . esc_js(get_option("widget_uid")) . '";
       
        window.onload =(function(){
            root=document.createElement("div");
            root.style.zIndex=99999;
            root.style.position="fixed";
            root.id="yourgpt_root";
            const sc=document.createElement("script");
            sc.src="https://widget.yourgpt.ai/main.js";
            document.body.appendChild(root);
            document.body.appendChild(sc);
        });
        </script>
    ';
}

add_action('admin_enqueue_scripts', 'add_html_script_to_admin');
add_action("wp_enqueue_scripts", "add_html_script_to_admin");
function my_openai_update_notice()
{
    global $pagenow;
    if (!esc_html(get_option("project_uid")) && !esc_html(get_option("widget_uid")) && $pagenow === 'plugins.php') {
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
    register_setting('plugin_option_group', 'plugin_option_name');
    if (isset($_POST['action']) && current_user_can('manage_options')) {
        # code...
        // print_r($_POST);
        // exit;
        update_option('project_uid', sanitize_text_field($_POST['project_uid']));
        update_option('widget_uid', sanitize_text_field($_POST['widget_uid']));
    }
    ;
}
;

function plugin_menu_option_func()
{ ?>
    <?php settings_errors(); ?>
    <div class="outer">
        <div class="animated-background"></div>
        <div class="container">
            <h2 class="heading">YourGPT Chatbot Settings</h2>
            <form id="ajax_form" action="options.php" method="post">
                <?php settings_fields('plugin_option_group'); ?>
                <div class="input-group">
                    <label for="projectUID" class="label">Enter your Chatbot Project UID:</label>
                    <input type="text" id="projectUID" class="input-text" placeholder="Project UID" name="project_uid" value= "<?php echo esc_html(get_option("project_uid")) && esc_html(get_option("widget_uid")) ? esc_html(get_option("project_uid")) : ""?>">
                </div>
                <div class="input-group">
                    <label for="widgetUID" class="label">Enter your Chatbot Widget UID:</label>
                    <input type="text" id="widgetUID" class="input-text" placeholder="Widget UID" name="widget_uid" value= "<?php echo esc_html(get_option("project_uid")) && esc_html(get_option("widget_uid")) ? esc_html(get_option("widget_uid")) : ""?>">
                </div>
                <input type="submit" name="submit" id="submit" class="btn" value="Save Changes">
            </form>
            <br />
            <a href='https://yourgpt.ai/chatbot' target="_blank" class="help">Need help ?</a>
        </div>
    </div>
    <?php
}

function my_plugin_activation()
{
    if (!esc_html(get_option("project_uid")) && !esc_html(get_option("widget_uid"))) {
        delete_option('project_uid');
        delete_option('widget_uid');
    }
    add_option('project_uid');
    add_option('widget_uid');
}

register_activation_hook(__FILE__, 'my_plugin_activation');

function my_plugin_deactivation()
{
    delete_option('project_uid');
    delete_option('widget_uid');
}

register_deactivation_hook(__FILE__, 'my_plugin_deactivation');