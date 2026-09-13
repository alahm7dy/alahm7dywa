jQuery(document).ready(function($) {
    'use strict';
    
    // Initialize intlTelInput if library exists
    function initPhoneInput(selector) {
        var input = document.querySelector(selector);
        if(!input || typeof window.intlTelInput !== 'function') return null;
        return window.intlTelInput(input, {
            utilsScript: mpwaFrontend.utils_url,
            initialCountry: "kw",
            preferredCountries: ['kw', 'sa', 'eg', 'ae', 'qa', 'bh', 'om'],
            separateDialCode: true
        });
    }

    var otpIti = initPhoneInput('#mpwa_otp_phone');

    /* ── Newsletter Form Submit (All Styles) ─────────────────── */
    $(document).on('submit', '.mpwa-newsletter-form', function(e) {
        e.preventDefault();
        
        var $form       = $(this);
        var $wrapper    = $form.closest('.mpwa-newsletter-wrapper');
        var $msg        = $form.find('.mpwa-newsletter-msg').length ? $form.find('.mpwa-newsletter-msg') : $wrapper.find('.mpwa-newsletter-msg');
        var $btn        = $form.find('button[type="submit"]');
        var phone       = $form.find('input[name="mpwa_phone"]').val();
        var origBtnHtml = $btn.html();

        if (!phone || !phone.trim()) {
            $msg.show().removeClass('success').addClass('error').text('يرجى إدخال رقم الهاتف أولاً.');
            return;
        }
        phone = phone.trim();

        $btn.prop('disabled', true).html('<span>جارٍ الاشتراك...</span>');
        $msg.hide().removeClass('success error');

        $.ajax({
            url: mpwaFrontend.ajax_url,
            type: 'POST',
            data: {
                action: 'mpwa_subscribe_newsletter',
                nonce:  mpwaFrontend.nonce,
                phone:  phone
            },
            success: function(response) {
                $btn.prop('disabled', false).html(origBtnHtml);
                $msg.show().text(response.data);
                
                if (response.success) {
                    $msg.removeClass('error').addClass('success');
                    $form[0].reset();
                } else {
                    $msg.removeClass('success').addClass('error');
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(origBtnHtml);
                $msg.show().text('حدث خطأ أثناء الاتصال، يرجى المحاولة لاحقاً.').removeClass('success').addClass('error');
            }
        });
    });

    /* ── Floating Newsletter Dismiss ─────────────────────── */
    try {
        if (sessionStorage.getItem('mpwa_floating_dismissed') === '1') {
            $('.mpwa-nl-style-floating').hide();
        }
    } catch(err){}

    $(document).on('click', '.mpwa-nl-close-btn', function(e) {
        e.preventDefault();
        var $floating = $(this).closest('.mpwa-nl-style-floating');
        $floating.fadeOut(250);
        try {
            sessionStorage.setItem('mpwa_floating_dismissed', '1');
        } catch(err){}
    });

    /* ── OTP Login Request ───────────────────────────────── */
    $('#mpwa-otp-phone-form').on('submit', function(e) {
        e.preventDefault();
        var $form    = $(this);
        var $btn     = $form.find('button[type="submit"]');
        var $msg     = $form.find('.mpwa-otp-msg');
        var phone    = otpIti ? otpIti.getNumber() : $form.find('input[name="mpwa_otp_phone"]').val();
        var origBtn  = $btn.html();

        if(!phone) return;

        $btn.prop('disabled', true).html('<span>جارٍ إرسال الرمز...</span>');
        $msg.hide().removeClass('success error');

        $.ajax({
            url: mpwaFrontend.ajax_url,
            type: 'POST',
            data: {
                action: 'mpwa_request_otp',
                nonce:  mpwaFrontend.nonce,
                phone:  phone
            },
            success: function(res) {
                $btn.prop('disabled', false).html(origBtn);
                if(res.success) {
                    $form.hide();
                    var $verifyForm = $('#mpwa-otp-verify-form');
                    $verifyForm.fadeIn();
                    $verifyForm.data('phone', res.data.phone || phone);
                    $verifyForm.find('input[name="mpwa_otp_code"]').focus();
                } else {
                    $msg.show().addClass('error').text(res.data);
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(origBtn);
                $msg.show().addClass('error').text('حدث خطأ في الاتصال بالخادم.');
            }
        });
    });

    /* ── Back to phone input ────────────────────────────── */
    $(document).on('click', '#mpwa-back-to-phone', function(e){
        e.preventDefault();
        $('#mpwa-otp-verify-form').hide();
        $('#mpwa-otp-phone-form').fadeIn();
        $('#mpwa-otp-phone-form').find('.mpwa-otp-msg').hide();
    });

    /* ── OTP Verify ──────────────────────────────────────── */
    $('#mpwa-otp-verify-form').on('submit', function(e) {
        e.preventDefault();
        var $form   = $(this);
        var $btn    = $form.find('button[type="submit"]');
        var $msg    = $form.find('.mpwa-otp-msg');
        var code    = $form.find('input[name="mpwa_otp_code"]').val().trim();
        var phone   = $form.data('phone');
        var origBtn = $btn.html();

        if(!code || !phone) return;

        $btn.prop('disabled', true).html('<span>جارٍ التحقق...</span>');
        $msg.hide().removeClass('success error');

        $.ajax({
            url: mpwaFrontend.ajax_url,
            type: 'POST',
            data: {
                action: 'mpwa_verify_otp',
                nonce:  mpwaFrontend.nonce,
                phone:  phone,
                code:   code
            },
            success: function(res) {
                if(res.success) {
                    $btn.html('<span>تم الدخول بنجاح ✓</span>');
                    $msg.show().addClass('success').text(res.data.message || 'تم تسجيل الدخول!');
                    setTimeout(function(){
                        window.location.href = res.data.redirect;
                    }, 800);
                } else {
                    $btn.prop('disabled', false).html(origBtn);
                    $msg.show().addClass('error').text(res.data);
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(origBtn);
                $msg.show().addClass('error').text('حدث خطأ أثناء محاولة تسجيل الدخول.');
            }
        });
    });

    /* ── Floating WhatsApp Chat Widget ───────────────────── */
    var $widget   = $('#mpwa-chat-widget');
    var $trigger  = $('#mpwa-chat-trigger');
    var $tooltip  = $('#mpwa-chat-tooltip');
    var $chatInp  = $('#mpwa-chat-user-input');

    if ($widget.length) {
        var delaySec = parseInt($widget.data('delay'), 10);
        if (isNaN(delaySec)) delaySec = 3;

        // Auto-show tooltip if not dismissed
        var tooltipDismissed = false;
        try {
            tooltipDismissed = sessionStorage.getItem('mpwa_tooltip_dismissed') === '1';
        } catch(e) {}

        if (!tooltipDismissed && delaySec > 0 && $tooltip.length) {
            setTimeout(function() {
                if (!$widget.hasClass('mpwa-open')) {
                    $tooltip.addClass('mpwa-tooltip-visible');
                }
            }, delaySec * 1000);
        }

        // Trigger Click: Toggle Chat Box
        $trigger.on('click', function(e) {
            e.preventDefault();
            var isOpen = $widget.hasClass('mpwa-open');
            if (isOpen) {
                $widget.removeClass('mpwa-open');
            } else {
                $widget.addClass('mpwa-open mpwa-opened');
                $tooltip.removeClass('mpwa-tooltip-visible');
                setTimeout(function() {
                    $chatInp.focus();
                }, 200);
            }
        });

        // Click on tooltip opens chat box
        $tooltip.on('click', function(e) {
            if ($(e.target).closest('.mpwa-chat-tooltip-close').length) return;
            $widget.addClass('mpwa-open mpwa-opened');
            $tooltip.removeClass('mpwa-tooltip-visible');
            setTimeout(function() {
                $chatInp.focus();
            }, 200);
        });

        // Close tooltip button
        $(document).on('click', '.mpwa-chat-tooltip-close', function(e) {
            e.stopPropagation();
            $tooltip.removeClass('mpwa-tooltip-visible');
            try {
                sessionStorage.setItem('mpwa_tooltip_dismissed', '1');
            } catch(err) {}
        });

        // Close chat box button
        $(document).on('click', '#mpwa-chat-box-close', function(e) {
            e.preventDefault();
            $widget.removeClass('mpwa-open');
        });

        // Close on escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $widget.hasClass('mpwa-open')) {
                $widget.removeClass('mpwa-open');
            }
        });

        // Send Message action
        function launchWhatsAppChat() {
            var phone = $widget.data('phone') || '';
            var text  = $chatInp.val() || $widget.data('default-msg') || '';
            if (!phone) return;

            var waUrl = 'https://wa.me/' + phone + '?text=' + encodeURIComponent(text);
            window.open(waUrl, '_blank', 'noopener,noreferrer');
            $widget.removeClass('mpwa-open');
        }

        $(document).on('click', '#mpwa-chat-send-btn', function(e) {
            e.preventDefault();
            launchWhatsAppChat();
        });

        $chatInp.on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                launchWhatsAppChat();
            }
        });
    }
});
