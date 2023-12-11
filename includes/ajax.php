<?php
add_action('wp_ajax_my_ajax_form','plugin_ajax_action');
function plugin_ajax_action(){
    if(isset($_POST['action']) && isset($_POST['widget_uid'])){
        update_option('widget_uid',sanitize_text_field($_POST['widget_uid']));
    }else{
        echo "failed";
    }
    wp_die();
}