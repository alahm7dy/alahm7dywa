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
});
