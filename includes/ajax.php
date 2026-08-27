<?php
add_action('wp_ajax_save_ygc_settings_ajax', 'ygc_save_settings_ajax');

// Widget UIDs issued by YourGPT are UUIDs (8-4-4-4-12 hex)
function ygc_is_valid_widget_uid($uid) {
    return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uid);
}

function ygc_save_settings_ajax() {
    // Verify nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ygc_settings_action')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        wp_die();
    }

    // Check user permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
        wp_die();
    }

    // Widget UID is required
    $widget_uid = isset($_POST['widget_uid']) ? trim(sanitize_text_field(wp_unslash($_POST['widget_uid']))) : '';
    if ($widget_uid === '') {
        wp_send_json_error(array('message' => 'Widget UID is required.'));
        wp_die();
    }
    if (!ygc_is_valid_widget_uid($widget_uid)) {
        wp_send_json_error(array('message' => 'Invalid Widget UID. Copy it from YourGPT Dashboard → Integrations.'));
        wp_die();
    }
    update_option('widget_uid', $widget_uid);

    // Save chatbot admin enabled (from checkbox directly)
    $chatbot_admin_enabled = isset($_POST['chatbot_admin_enabled']) && sanitize_text_field(wp_unslash($_POST['chatbot_admin_enabled'])) === '1' ? '1' : '0';
    update_option('chatbot_admin_enabled', $chatbot_admin_enabled);

    // Return success response
    wp_send_json_success(array(
        'message' => 'Settings saved successfully!',
        'saved_values' => array(
            'widget_uid' => get_option('widget_uid'),
            'chatbot_admin_enabled' => get_option('chatbot_admin_enabled')
        )
    ));

    wp_die();
}
