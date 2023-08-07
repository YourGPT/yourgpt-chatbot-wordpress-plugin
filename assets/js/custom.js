jQuery(function ($) {
    var project_uid = getOption.project_uid;
    var widget_uid = getOption.widget_uid;
    $('#ajax_form').on('submit', function (el) {
        el.preventDefault();
        console.log(el);
        $.post(ajaxurl, {action: "my_ajax_form",project_uid: el.target.project_uid.value, widget_uid: el.target.widget_uid.value }, function (val) {
            window.location.href = "http://localhost/wordpress/wp-admin/plugins.php";
        })
    })

})