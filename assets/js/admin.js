(function($){
    'use strict';

    /* ── Tab switching ──────────────────────────────────── */
    $(document).on('click', '.mpwa-nav-btn', function(){
        var tab = $(this).data('tab');
        $('.mpwa-nav-btn').removeClass('active');
        $(this).addClass('active');
        $('.mpwa-panel').removeClass('active');
        $('#mpwa-panel-' + tab).addClass('active');
        history.replaceState(null, '', '?page=mpwa-settings&tab=' + tab);
    });

    var activeTab = new URLSearchParams(window.location.search).get('tab') || 'api';
    $('.mpwa-nav-btn[data-tab="' + activeTab + '"]').trigger('click');

    /* ── Proxy Tab switching (from welcome card & top stats) ── */
    $(document).on('click', '.mpwa-nav-btn-proxy', function(){
        var target = $(this).data('target');
        if(target) {
            $('.mpwa-nav-btn[data-tab="' + target + '"]').trigger('click');
            $('html, body').animate({
                scrollTop: $('.mpwa-panel.active').offset().top - 40
            }, 300);
        }
    });

    /* ── Accordion for message templates ────────────────── */
    $(document).on('click', '.mpwa-status-card-header', function(){
        var body = $(this).next('.mpwa-status-card-body');
        var icon = $(this).find('.mpwa-accordion-icon');
        body.toggleClass('open');
        icon.text(body.hasClass('open') ? '▲' : '▼');
    });

    /* ── Copy shortcode to clipboard ────────────────────── */
    $(document).on('click', '.mpwa-tag, .mpwa-copy-input', function(){
        var text = $(this).is('input') ? $(this).val() : $(this).text();
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function(){
                toast('تم نسخ النص: ' + text, 'success');
            });
        } else {
            // Fallback
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();
            toast('تم النسخ: ' + text, 'success');
        }
    });

    /* ── Show/hide API key ──────────────────────────────── */
    $(document).on('click', '#mpwa-toggle-apikey', function(){
        var inp = $('#mpwa-api-key-input');
        var hide = inp.attr('type') === 'password';
        inp.attr('type', hide ? 'text' : 'password');
        $(this).text(hide ? '🙈 إخفاء' : '👁 إظهار');
    });

    /* ── Test connection ────────────────────────────────── */
    $(document).on('click', '#mpwa-test-btn', function(){
        var btn = $(this).prop('disabled', true).text('جارٍ الاختبار...');
        $.post(ajaxurl, { action: 'mpwa_test_connection', nonce: mpwaAdmin.nonce }, function(r){
            toast(r.data, r.success ? 'success' : 'error');
        }).always(function(){
            btn.prop('disabled', false).html('🔌 اختبار الاتصال');
        });
    });

    $(document).on('click', '#mpwa-send-test-msg-btn', function(){
        var btn = $(this).prop('disabled', true).text('جارٍ الإرسال...');
        $.post(ajaxurl, { action: 'mpwa_send_test', nonce: mpwaAdmin.nonce }, function(r){
            toast(r.data, r.success ? 'success' : 'error');
        }).always(function(){
            btn.prop('disabled', false).html('📱 إرسال رسالة تجربة لنفسي');
        });
    });

    /* ── Gateway Tool Testing ───────────────────────────── */
    $(document).on('click', '#mpwa-tool-send', function(){
        var btn = $(this), result = $('#mpwa-tool-result');
        btn.prop('disabled', true).text('جاري الإرسال...');
        result.hide();

        $.post(ajaxurl, {
            action:  'mpwa_api_tool_send',
            nonce:   mpwaAdmin.nonce,
            type:    $('#mpwa-tool-type').val(),
            url:     $('#mpwa-tool-url').val(),
            extra:   $('#mpwa-tool-json').val(),
            phone:   $('#mpwa-tool-number').val(),
            message: $('#mpwa-tool-message').val()
        }, function(r){
            var output = typeof r.data === 'object' ? JSON.stringify(r.data, null, 2) : r.data;
            result.removeClass('mpwa-notice-success mpwa-notice-error')
                  .addClass(r.success ? 'mpwa-notice-success' : 'mpwa-notice-error')
                  .text(output).fadeIn();
        }).always(function(){
            btn.prop('disabled', false).text('📤 إرسال رسالة عبر البوابة');
        });
    });

    $(document).on('change', '#mpwa-tool-type', function(){
        var t = $(this).val();
        var needsUrl = ['media', 'sticker', 'product', 'channel', 'button', 'list'].indexOf(t) !== -1;
        var samples = {
            media: { media_type: 'image' },
            channel: { footer: 'مرسل من المتجر' },
            button: { footer: 'اختر إجراءً', buttons: [ { type: 'reply', displayText: 'موافق' }, { type: 'url', displayText: 'زيارة المتجر', url: 'https://example.com' } ] },
            poll: { options: ['نعم', 'لا'], countable: 1 },
            list: { name: 'قائمة المتجر', title: 'اختر خدمة', buttontext: 'عرض القائمة', footer: 'متجرنا', sections: [ { title: 'الخدمات', rows: [ { title: 'خدمة العملاء', rowId: 'support', description: 'تواصل معنا' } ] } ] },
            location: { latitude: '24.7136', longitude: '46.6753' },
            vcard: { name: 'خدمة العملاء', phone: '966500000000' }
        };

        $('.mpwa-tool-url-wrap').toggle(needsUrl);
        $('.mpwa-tool-json-wrap').toggle(!!samples[t]);
        if (samples[t]) {
            $('#mpwa-tool-json').val(JSON.stringify(samples[t], null, 2));
        } else {
            $('#mpwa-tool-json').val('');
        }
    });

    /* ── Test AI ────────────────────────────────────────── */
    $(document).on('click', '#mpwa-test-ai-btn', function(){
        var msg = $('#mpwa-ai-test-input').val().trim();
        if(!msg) { toast('اكتب رسالة لتجربة البوت', 'error'); return; }
        
        var btn = $(this).prop('disabled', true).text('جارٍ التفكير...');
        $('#mpwa-ai-test-response').hide().text('');
        
        $.post(ajaxurl, { action: 'mpwa_test_ai', nonce: mpwaAdmin.nonce, message: msg }, function(r){
            if(r.success) {
                $('#mpwa-ai-test-response').css('color', '#166534').text('🤖 البوت يرد:\n' + r.data).fadeIn();
            } else {
                $('#mpwa-ai-test-response').css('color', '#991b1b').text('❌ خطأ:\n' + r.data).fadeIn();
            }
        }).always(function(){
            btn.prop('disabled', false).text('اختبار الرد');
        });
    });

    /* ── Clear logs ─────────────────────────────────────── */
    $(document).on('click', '#mpwa-clear-logs-btn', function(){
        if(!confirm('هل أنت متأكد من مسح جميع سجلات الإرسال نهائياً؟')) return;
        $.post(ajaxurl, { action: 'mpwa_clear_logs', nonce: mpwaAdmin.nonce }, function(r){
            if (r.success) {
                toast('تم مسح السجلات بنجاح.', 'success');
                setTimeout(function(){ location.reload(); }, 600);
            } else {
                toast('خطأ: ' + r.data, 'error');
            }
        });
    });

    /* ── Expand log row & Retry ─────────────────────────── */
    $(document).on('click', '.mpwa-log-detail-btn', function(e){
        e.stopPropagation();
        var row = $(this).closest('tr');
        row.next('.mpwa-log-detail-tr').toggle();
    });

    $(document).on('click', '.mpwa-retry-log', function(e){
        e.stopPropagation();
        var id = $(this).data('id');
        var btn = $(this).prop('disabled', true).text('جارٍ...');
        $.post(ajaxurl, { action: 'mpwa_retry_log', nonce: mpwaAdmin.nonce, id: id }, function(r){
            toast(r.data, r.success ? 'success' : 'error');
            if (r.success) {
                setTimeout(function(){ location.reload(); }, 1200);
            }
        }).always(function(){
            btn.prop('disabled', false).text('🔄 إعادة');
        });
    });

    /* ── Contact: Delete ────────────────────────────────── */
    $(document).on('click', '.mpwa-delete-contact', function(){
        if(!confirm('هل تريد بالتأكيد حذف جهة الاتصال هذه؟')) return;
        var id  = $(this).data('id');
        var row = $(this).closest('tr');
        $.post(ajaxurl, { action: 'mpwa_delete_contact', nonce: mpwaAdmin.nonce, id: id }, function(r){
            if(r.success){
                row.fadeOut(300, function(){ $(this).remove(); });
                toast('تم حذف جهة الاتصال بنجاح.', 'success');
            } else {
                toast('فشل الحذف: ' + (r.data || ''), 'error');
            }
        });
    });

    /* ── Contact: Open send modal ───────────────────────── */
    $(document).on('click', '.mpwa-send-to-contact', function(){
        var phone = $(this).data('phone');
        var name  = $(this).data('name') || 'العميل';
        $('#mpwa-modal-phone').val(phone);
        $('#mpwa-modal-title').text('إرسال رسالة إلى: ' + name);
        $('#mpwa-modal-msg').val('');
        $('#mpwa-send-modal').addClass('open');
    });

    /* ── Add Contact: Open modal ────────────────────────── */
    $(document).on('click', '#mpwa-add-contact-btn', function(){
        $('#mpwa-new-name').val('');
        $('#mpwa-new-phone').val('');
        $('#mpwa-new-email').val('');
        $('#mpwa-new-tags').val('');
        $('#mpwa-add-contact-modal').addClass('open');
    });

    /* ── Modals: Close handlers ─────────────────────────── */
    $(document).on('click', '.mpwa-modal-close, #mpwa-modal-cancel', function(){
        $('.mpwa-modal-overlay').removeClass('open');
    });

    $(document).on('click', '.mpwa-modal-overlay', function(e){
        if (e.target === this) {
            $(this).removeClass('open');
        }
    });

    $(document).on('keydown', function(e){
        if (e.key === 'Escape' || e.keyCode === 27) {
            $('.mpwa-modal-overlay').removeClass('open');
        }
    });

    /* ── Modal: Send direct message submit ──────────────── */
    $(document).on('click', '#mpwa-modal-send-btn', function(){
        var phone = $('#mpwa-modal-phone').val();
        var msg   = $('#mpwa-modal-msg').val().trim();
        if(!msg){ toast('يرجى كتابة نص الرسالة أولاً', 'error'); return; }
        
        var btn = $(this).prop('disabled', true).text('جارٍ الإرسال...');
        $.post(ajaxurl, {
            action: 'mpwa_send_to_contact',
            nonce: mpwaAdmin.nonce,
            phone: phone,
            message: msg
        }, function(r){
            toast(r.data, r.success ? 'success' : 'error');
            if(r.success) {
                $('#mpwa-send-modal').removeClass('open');
            }
        }).always(function(){
            btn.prop('disabled', false).text('📤 إرسال الآن');
        });
    });

    /* ── Modal: Add contact submit ──────────────────────── */
    $(document).on('click', '#mpwa-add-contact-submit', function(){
        var name  = $('#mpwa-new-name').val().trim();
        var phone = $('#mpwa-new-phone').val().trim();
        var email = $('#mpwa-new-email').val().trim();
        var tags  = $('#mpwa-new-tags').val().trim();

        if(!name || !phone){
            toast('الاسم ورقم الهاتف مطلوبان', 'error');
            return;
        }

        var btn = $(this).prop('disabled', true).text('جارٍ الحفظ...');
        $.post(ajaxurl, {
            action: 'mpwa_add_contact',
            nonce: mpwaAdmin.nonce,
            name: name,
            phone: phone,
            email: email,
            tags: tags
        }, function(r){
            toast(r.data, r.success ? 'success' : 'error');
            if(r.success){
                $('#mpwa-add-contact-modal').removeClass('open');
                setTimeout(function(){ location.reload(); }, 700);
            }
        }).always(function(){
            btn.prop('disabled', false).text('💾 حفظ جهة الاتصال');
        });
    });

    /* ── Bulk send campaign ─────────────────────────────── */
    $(document).on('click', '#mpwa-send-bulk-btn', function(){
        var msg = $('#mpwa-bulk-msg').val().trim();
        var target = $('#mpwa-bulk-target').val();
        var media = $('#mpwa-bulk-media').val().trim();
        var btn_text = $('#mpwa-bulk-btn-text').val().trim();
        
        if(!msg){ toast('اكتب نص الحملة أولاً', 'error'); return; }
        if(!confirm('سيتم إرسال الحملة إلى الجمهور المحدد. هل أنت متأكد من المتابعة؟')) return;
        
        var btn = $(this).prop('disabled', true).text('جارٍ الإرسال إلى العملاء...');
        $.post(ajaxurl, { 
            action: 'mpwa_bulk_send', 
            nonce: mpwaAdmin.nonce, 
            message: msg,
            target: target,
            media: media,
            btn_text: btn_text
        }, function(r){
            toast(r.data, r.success ? 'success' : 'error');
        }).always(function(){
            btn.prop('disabled', false).text('🚀 إرسال الحملة الآن');
        });
    });

    /* ── Export CSV ─────────────────────────────────────── */
    $(document).on('click', '#mpwa-export-contacts-btn', function(){
        var url = ajaxurl + '?action=mpwa_export_contacts&nonce=' + mpwaAdmin.nonce;
        window.location.href = url;
    });

    /* ── Sync Old Orders ────────────────────────────────── */
    $(document).on('click', '#mpwa-sync-orders-btn', function(){
        if(!confirm('هل تريد مزامنة أرقام العملاء من الطلبات السابقة وإضافتها لدفتر العملاء؟')) return;
        var btn = $(this).prop('disabled', true).text('جارٍ الاستيراد...');
        
        function syncPage(page) {
            $.post(ajaxurl, { action: 'mpwa_sync_old_orders', nonce: mpwaAdmin.nonce, sync_page: page }, function(r){
                if(r.success) {
                    if (r.data && !r.data.done) {
                        btn.text('جارٍ استيراد الصفحة ' + r.data.next_page + '...');
                        syncPage(r.data.next_page);
                    } else {
                        toast('اكتملت مزامنة الطلبات بنجاح!', 'success');
                        setTimeout(function(){ location.reload(); }, 1200);
                    }
                } else {
                    toast(r.data, 'error');
                    btn.prop('disabled', false).text('🔄 مزامنة الطلبات القديمة');
                }
            }).fail(function(){
                toast('تعذر إكمال المزامنة.', 'error');
                btn.prop('disabled', false).text('🔄 مزامنة الطلبات القديمة');
            });
        }
        syncPage(1);
    });

    /* ── Device Management ──────────────────────────────── */
    $(document).on('click', '#mpwa-check-device-btn', function(){
        var btn = $(this).prop('disabled', true).text('جارٍ الفحص...');
        $('#mpwa-device-status-result').html('');
        
        $.post(ajaxurl, { action: 'mpwa_get_device_status', nonce: mpwaAdmin.nonce }, function(r){
            if(r.success && r.data) {
                var html = '';
                var isConnected = false;
                
                if ( r.data.user && r.data.user.info ) {
                    var u = r.data.user.info;
                    html += '<div class="mpwa-device-card">';
                    html += '<p><strong>👤 حساب المستخدم:</strong> ' + (u.username || 'غير محدد') + '</p>';
                    html += '<p><strong>📅 انتهاء الاشتراك:</strong> <span>' + (u.subscription_expired || 'نشط') + '</span></p>';
                    html += '</div>';
                }
                
                if ( r.data.device && r.data.device.info && r.data.device.info.length > 0 ) {
                    var dev = r.data.device.info[0];
                    var s = (dev.status || '').toLowerCase();
                    if(s === 'connected' || s === 'authenticated') isConnected = true;
                    
                    var color = isConnected ? '#166534' : '#991b1b';
                    var bg = isConnected ? '#f0fdf4' : '#fef2f2';
                    var statusText = isConnected ? 'متصل وجاهز للإرسال ✓' : (dev.status || 'غير متصل');
                    
                    html += '<div class="mpwa-device-card" style="margin-top:10px; background:' + bg + '; border:1px solid ' + (isConnected ? '#bbf7d0' : '#fecaca') + ';">';
                    html += '<p><strong>📱 رقم الهاتف المربوط:</strong> <span style="direction:ltr;display:inline-block;font-weight:bold;">' + (dev.device || dev.number || '') + '</span></p>';
                    html += '<p><strong>⚡ حالة الاتصال:</strong> <span style="color:' + color + '; font-weight:800;">' + statusText + '</span></p>';
                    html += '</div>';
                } else {
                    html += '<div class="mpwa-device-card" style="margin-top:10px; background:#fff1f2; border:1px solid #fecaca;"><p style="color:#991b1b; font-weight:bold;">⚠️ الجهاز غير متصل حالياً، يرجى توليد رمز QR وربطه عبر تطبيق الواتساب.</p></div>';
                }
                
                $('#mpwa-device-status-result').html(html);
                
                if (isConnected) {
                    $('#mpwa-device-qr-container').hide();
                    $('#mpwa-device-logout-container').fadeIn();
                } else {
                    $('#mpwa-device-logout-container').hide();
                    $('#mpwa-device-qr-container').fadeIn();
                }
            } else {
                $('#mpwa-device-status-result').html('<p style="color:#991b1b; padding:12px; background:#fff1f2; border-radius:10px; font-weight:600;">❌ ' + (r.data || 'تعذر فحص حالة الجهاز. تأكد من صحة بيانات الربط.') + '</p>');
            }
        }).always(function(){
            btn.prop('disabled', false).text('فحص حالة الجهاز 🔄');
        });
    });

    $(document).on('click', '#mpwa-generate-qr-btn', function(){
        var btn = $(this).prop('disabled', true).text('جارٍ توليد الكود...');
        $('#mpwa-qr-image-wrapper').html('');
        
        $.post(ajaxurl, { action: 'mpwa_generate_qr', nonce: mpwaAdmin.nonce }, function(r){
            if(r.success && r.data && r.data.qrcode) {
                if ( /^data:image\/(png|jpeg|jpg|gif);base64,/i.test(r.data.qrcode) ) {
                    $('<img>', {
                        src: r.data.qrcode,
                        css: { maxWidth: '280px', borderRadius: '16px', boxShadow: '0 10px 30px rgba(0,0,0,0.15)', padding: '12px', background: '#fff' }
                    }).appendTo('#mpwa-qr-image-wrapper');
                    toast('يرجى مسح كود QR من تطبيق WhatsApp الآن.', 'success');
                } else {
                    toast('استجابة QR غير صالحة من البوابة.', 'error');
                }
            } else {
                toast('تعذر توليد كود QR: ' + (r.data || ''), 'error');
            }
        }).always(function(){
            btn.prop('disabled', false).text('توليد كود QR للربط 📲');
        });
    });

    $(document).on('click', '#mpwa-logout-device-btn', function(){
        if(!confirm('هل أنت متأكد من تسجيل الخروج؟ سيتوقف إرسال الرسائل عبر هذا الرقم.')) return;
        var btn = $(this).prop('disabled', true).text('جارٍ تسجيل الخروج...');
        
        $.post(ajaxurl, { action: 'mpwa_logout_device', nonce: mpwaAdmin.nonce }, function(r){
            if(r.success) {
                toast('تم تسجيل الخروج بنجاح.', 'success');
                $('#mpwa-check-device-btn').trigger('click');
            } else {
                toast('فشل تسجيل الخروج: ' + (r.data || ''), 'error');
            }
        }).always(function(){
            btn.prop('disabled', false).text('تسجيل الخروج من الواتساب 🚪');
        });
    });

    $(document).on('click', '#mpwa-delete-device-btn', function(){
        if(!confirm('سيتم حذف الجهاز نهائياً من البوابة. هل توافق على المتابعة؟')) return;
        var btn = $(this).prop('disabled', true).text('جاري حذف الجهاز...');
        $.post(ajaxurl, { action: 'mpwa_delete_device', nonce: mpwaAdmin.nonce }, function(r){
            toast(r.success ? 'تم حذف الجهاز من البوابة.' : r.data, r.success ? 'success' : 'error');
            if(r.success) $('#mpwa-check-device-btn').trigger('click');
        }).always(function(){
            btn.prop('disabled', false).text('حذف الجهاز نهائيًا 🗑');
        });
    });

    /* ── Modern Toast Notification ──────────────────────── */
    function toast(msg, type){
        type = type || 'success';
        var isSuccess = type === 'success';
        var bg = isSuccess ? '#064e3b' : '#7f1d1d';
        var border = isSuccess ? '#10b981' : '#f87171';
        var icon = isSuccess ? '✓' : '⚠️';
        
        var $el = $('<div>').css({
            position: 'fixed',
            bottom: '25px',
            left: '25px',
            zIndex: 999999,
            background: bg,
            border: '1px solid ' + border,
            color: '#ffffff',
            padding: '12px 20px',
            borderRadius: '14px',
            fontFamily: 'Cairo, sans-serif',
            fontSize: '14px',
            fontWeight: '600',
            boxShadow: '0 10px 25px rgba(0,0,0,0.3)',
            direction: 'rtl',
            display: 'flex',
            alignItems: 'center',
            gap: '10px',
            opacity: 0,
            transform: 'translateY(15px)',
            transition: 'all 0.3s cubic-bezier(0.16, 1, 0.3, 1)'
        }).html('<span>' + icon + '</span><span>' + msg + '</span>').appendTo('body');

        setTimeout(function(){
            $el.css({ opacity: 1, transform: 'translateY(0)' });
        }, 20);

        setTimeout(function(){
            $el.css({ opacity: 0, transform: 'translateY(15px)' });
            setTimeout(function(){ $el.remove(); }, 350);
        }, 3500);
    }

    /* ── Check GitHub Update Live Handler ────────────────── */
    $(document).on('click', '#mpwa-check-gh-update-btn', function(){
        var $btn = $(this);
        var $res = $('#mpwa-gh-update-result');
        var origHtml = $btn.html();

        $btn.prop('disabled', true).html('<span>⏳ جارٍ فحص المستودع...</span>');
        $res.hide();

        $.post(ajaxurl, {
            action: 'mpwa_check_github_update',
            nonce: mpwaAdmin.nonce
        }, function(r){
            $res.show();
            if(r.success) {
                if(r.data.is_new) {
                    $res.css({ background: '#f0fdf4', color: '#166534', border: '1.5px solid #bbf7d0' })
                        .html('<div style="font-weight:bold; font-size:15px;">' + r.data.message + '</div>' +
                              '<div style="margin-top:6px; font-size:13px; color:#15803d;">تاريخ النشر: ' + r.data.published_at + '</div>' +
                              '<div style="margin-top:12px;"><a href="' + r.data.update_url + '" class="mpwa-btn mpwa-btn-success" style="display:inline-block; text-decoration:none;">⚡ الانتقال لصفحة تحديثات ووردبريس والتحديث الآن</a></div>');
                    toast('يوجد إصدار جديد متاح (' + r.data.latest_version + ')!', 'success');
                } else {
                    $res.css({ background: '#f8fafc', color: '#0f172a', border: '1.5px solid #cbd5e1' })
                        .html('<div style="font-weight:bold; font-size:14px; color:#128C7E;">' + r.data.message + '</div>' +
                              '<div style="margin-top:4px; font-size:12px; color:#64748b;">أحدث إصدار على GitHub هو: v' + r.data.latest_version + ' (' + r.data.published_at + ')</div>');
                    toast(r.data.message, 'success');
                }
            } else {
                $res.css({ background: '#fef2f2', color: '#991b1b', border: '1.5px solid #fecaca' })
                    .html('<strong>⚠️ تنبيه الفحص:</strong> ' + r.data);
                toast(r.data, 'error');
            }
        }).fail(function(){
            $res.show().css({ background: '#fef2f2', color: '#991b1b', border: '1.5px solid #fecaca' })
                .text('حدث خطأ أثناء الاتصال بالخادم. يرجى المحاولة لاحقاً.');
            toast('حدث خطأ أثناء الاتصال بالخادم.', 'error');
        }).always(function(){
            $btn.prop('disabled', false).html(origHtml);
        });
    });

})(jQuery);
