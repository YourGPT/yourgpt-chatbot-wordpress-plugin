jQuery(function ($) {
    var ajaxurl = getOption.ajaxurl || window.ajaxurl || '/wp-admin/admin-ajax.php';

    // --- Settings form ---------------------------------------------------

    var uidError = $('#widgetUID-error');

    function showFieldError(message) {
        $('#widgetUID').addClass('is-invalid').focus();
        uidError.text(message).prop('hidden', false);
    }

    function clearFieldError() {
        $('#widgetUID').removeClass('is-invalid');
        uidError.text('').prop('hidden', true);
    }

    $('#widgetUID')
        .on('blur change', function () {
            this.value = this.value.trim();
        })
        .on('input', clearFieldError);

    $('#ajax_form').on('submit', function (e) {
        e.preventDefault();

        var form = e.target;
        var uidField = $('#widgetUID');
        var widgetUid = (uidField.val() || '').trim();

        $('.ygc-notice').remove();

        if (!widgetUid) {
            showFieldError('Widget UID is required.');
            return;
        }
        if (!/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(widgetUid)) {
            showFieldError('Invalid Widget UID. Copy it from YourGPT Dashboard \u2192 Integrations.');
            return;
        }
        clearFieldError();

        var formData = {
            action: "save_ygc_settings_ajax",
            nonce: $('#ygc_settings_nonce').val(),
            widget_uid: widgetUid,
            chatbot_admin_enabled: form.chatbot_admin_enabled && form.chatbot_admin_enabled.checked ? '1' : '0'
        };

        var submitButton = $(e.originalEvent && e.originalEvent.submitter || $(form).find('button[type="submit"]').first());
        // The button holds an icon plus a label span; only swap the label.
        var buttonLabel = submitButton.find('span').last();
        if (!buttonLabel.length) {
            buttonLabel = submitButton;
        }
        var originalButtonText = buttonLabel.text();

        submitButton.prop('disabled', true);
        buttonLabel.text('Saving...');

        $.post(ajaxurl, formData, function (response) {
            if (response.success) {
                buttonLabel.text('Saved! Reloading...');

                $('#ajax_form').before(
                    '<div class="ygc-notice notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>'
                );
                $('html, body').animate({ scrollTop: 0 }, 300);

                setTimeout(function () {
                    window.location.reload();
                }, 1500);
            } else {
                submitButton.prop('disabled', false);
                buttonLabel.text(originalButtonText);
                $('#ajax_form').before(
                    '<div class="ygc-notice notice notice-error is-dismissible"><p>' + response.data.message + '</p></div>'
                );
            }
        }).fail(function () {
            submitButton.prop('disabled', false);
            buttonLabel.text(originalButtonText);
            $('#ajax_form').before(
                '<div class="ygc-notice notice notice-error is-dismissible"><p>Error saving settings. Please try again.</p></div>'
            );
        });
    });

    // --- Demo video tabs: only the visible tab's iframe is loaded -------

    $('.ygc-tab').on('click', function () {
        var tab = $(this).data('tab');
        if ($(this).hasClass('is-active')) {
            return;
        }

        $('.ygc-tab').removeClass('is-active').attr('aria-selected', 'false');
        $(this).addClass('is-active').attr('aria-selected', 'true');

        $('.ygc-video').each(function () {
            var panel = $(this);
            var iframe = panel.find('iframe');
            var active = panel.data('tab') === tab;

            panel.toggleClass('is-active', active).prop('hidden', !active);
            panel.find('.ygc-video__end').prop('hidden', true);

            if (active) {
                if (!iframe.attr('src')) {
                    iframe.attr('src', iframe.data('src'));
                }
                attachPlayer(panel);
            } else if (iframe.attr('src')) {
                // Unload so a playing video stops when its tab is hidden
                detachPlayer(panel);
                iframe.data('src', iframe.attr('src')).removeAttr('src');
            }
        });

        $('.ygc-video__more').each(function () {
            $(this).prop('hidden', $(this).data('tab') !== tab);
        });
    });

    // --- End-of-Short prompt (YouTube IFrame API) ------------------------
    // When a Short finishes we overlay a "Watch full tutorial" card. Loads the
    // API only if the video card is actually on the page.

    var ytReady = false;

    function attachPlayer(panel) {
        var iframe = panel.find('iframe').get(0);
        if (!iframe || !ytReady || panel.data('ytPlayer')) {
            return;
        }
        var player = new YT.Player(iframe, {
            events: {
                onStateChange: function (e) {
                    var overlay = panel.find('.ygc-video__end');
                    if (e.data === YT.PlayerState.ENDED) {
                        overlay.prop('hidden', false);
                    } else if (e.data === YT.PlayerState.PLAYING) {
                        overlay.prop('hidden', true);
                    }
                }
            }
        });
        panel.data('ytPlayer', player);
    }

    function detachPlayer(panel) {
        var player = panel.data('ytPlayer');
        if (player && typeof player.destroy === 'function') {
            // destroy() removes the iframe; re-create a bare one so the tab can reload later
            var src = panel.find('iframe').attr('src') || panel.find('iframe').data('src');
            var title = panel.find('iframe').attr('title');
            player.destroy();
            $('<iframe>', {
                'data-src': src,
                title: title,
                allow: 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
                referrerpolicy: 'strict-origin-when-cross-origin',
                allowfullscreen: 'allowfullscreen',
                loading: 'lazy'
            }).prependTo(panel);
        }
        panel.removeData('ytPlayer');
    }

    $('.ygc-video').on('click', '.ygc-video__replay', function () {
        var panel = $(this).closest('.ygc-video');
        var player = panel.data('ytPlayer');
        panel.find('.ygc-video__end').prop('hidden', true);
        if (player) {
            player.seekTo(0);
            player.playVideo();
        }
    });

    if ($('.ygc-video').length) {
        // Chain rather than clobber, in case another plugin on this screen also uses the API
        var previousReady = window.onYouTubeIframeAPIReady;
        window.onYouTubeIframeAPIReady = function () {
            if (typeof previousReady === 'function') {
                previousReady();
            }
            ytReady = true;
            attachPlayer($('.ygc-video.is-active'));
        };
        if (window.YT && window.YT.Player) {
            window.onYouTubeIframeAPIReady();
        } else {
            $('<script>', { src: 'https://www.youtube.com/iframe_api', async: true }).appendTo('head');
        }
    }
})
