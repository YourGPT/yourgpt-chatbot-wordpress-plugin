jQuery(function ($) {
    var widget_uid = getOption.widget_uid;
    $('#ajax_form').on('submit', function (el) {
        el.preventDefault();
        console.log("widget_uid",el.target.widget_uid.value);
        $.post(ajaxurl, {action: "my_ajax_form", widget_uid: el.target.widget_uid.value }, function (val) {
            window.location.href = "http://localhost/wordpress/wp-admin/plugins.php";
        })
    })

})