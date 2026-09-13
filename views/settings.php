<?php if ( ! defined('ABSPATH') ) exit;
$p = 'mpedia_wagateway';
$saved = isset($_GET['saved']);
$statuses = wc_get_order_statuses();
$tags = ['{{shop_name}}','{{order_id}}','{{order_amount}}','{{order_status}}','{{first_name}}','{{last_name}}','{{billing_city}}','{{customer_phone}}','{{billing_email}}', '{{tracking_link}}'];
?>
<div class="mpwa-dashboard-wrapper">
  
  <?php if ($saved): ?>
  <div class="mpwa-notice mpwa-notice-success">
    <div class="mpwa-notice-icon">✅</div>
    <div class="mpwa-notice-text">تم حفظ الإعدادات بنجاح.</div>
  </div>
  <?php endif; ?>

  <div class="mpwa-dashboard">
    
    <!-- Sidebar -->
    <aside class="mpwa-sidebar">
      <div class="mpwa-brand">
        <div class="mpwa-brand-icon">
          <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M12.01 2.01A10 10 0 002.01 12c0 1.76.45 3.44 1.3 4.95L2.01 22l5.22-1.37a9.97 9.97 0 004.78 1.21h.01a10 10 0 0010-10 10 10 0 00-10-9.83zM12.01 20.14h-.01a8.31 8.31 0 01-4.23-1.15l-.3-.18-3.15.83.84-3.07-.2-.32A8.34 8.34 0 013.68 12a8.34 8.34 0 018.33-8.33 8.34 8.34 0 018.33 8.33 8.34 8.34 0 01-8.33 8.14zM16.58 13.9c-.25-.12-1.48-.73-1.7-.81-.23-.08-.4-.12-.57.12-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-1.1-.49-2.07-1.1-2.92-2.14-.23-.28-.02-.27.22-.52.05-.05.11-.12.16-.18.06-.06.08-.12.12-.18.04-.08.02-.15-.01-.21-.03-.06-.25-.6-.35-.82-.09-.21-.18-.18-.25-.18h-.21c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1 0 1.24.9 2.44 1.02 2.6.12.16 1.78 2.71 4.3 3.8.6.26 1.07.41 1.43.53.6.19 1.15.16 1.58.1.48-.07 1.48-.6 1.69-1.18.21-.58.21-1.07.15-1.18-.06-.1-.23-.16-.48-.28z"/></svg>
        </div>
        <div class="mpwa-brand-text">
          <h1>Alahm7dy</h1>
          <p>WhatsApp Pro</p>
        </div>
      </div>

      <nav class="mpwa-nav">
        <button class="mpwa-nav-btn active" data-tab="api"><span class="mpwa-nav-icon">🔌</span> إعدادات الربط</button>
        <button class="mpwa-nav-btn" data-tab="customer"><span class="mpwa-nav-icon">👤</span> إشعارات العملاء</button>
        <button class="mpwa-nav-btn" data-tab="admin"><span class="mpwa-nav-icon">🛡️</span> إشعارات الإدارة</button>
        <button class="mpwa-nav-btn" data-tab="templates"><span class="mpwa-nav-icon">📝</span> قوالب الرسائل</button>
        <button class="mpwa-nav-btn" data-tab="ai"><span class="mpwa-nav-icon">🤖</span> الذكاء الاصطناعي</button>
        <button class="mpwa-nav-btn" data-tab="advanced"><span class="mpwa-nav-icon">🚀</span> الويب هوك & OTP</button>
        <button class="mpwa-nav-btn" data-tab="device"><span class="mpwa-nav-icon">📱</span> إدارة الجهاز</button>
        <button class="mpwa-nav-btn" data-tab="api-tools"><span class="mpwa-nav-icon">🧰</span> أدوات البوابة</button>
        <button class="mpwa-nav-btn" data-tab="updates"><span class="mpwa-nav-icon">🔄</span> التحديثات التلقائية</button>
        <hr style="border:none; border-top:1px solid var(--c-border); margin:10px 0;">
        <button class="mpwa-nav-btn" data-tab="logs"><span class="mpwa-nav-icon">📊</span> سجل الإرسال</button>
        <button class="mpwa-nav-btn" data-tab="contacts"><span class="mpwa-nav-icon">📇</span> دفتر العملاء</button>
      </nav>

      <div class="mpwa-sidebar-footer">
        تطوير علي الاحمدي<br>
        <a href="https://alahm7dy.com" target="_blank">alahm7dy.com</a>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="mpwa-main">
      
      <!-- Top Stats Bar -->
      <div class="mpwa-topbar">
        <div class="mpwa-stat-card primary">
          <div class="mpwa-stat-icon">📈</div>
          <div class="mpwa-stat-info">
            <span class="mpwa-stat-val"><?php echo MpwaLogger::count_total(); ?></span>
            <span class="mpwa-stat-lbl">إجمالي الرسائل</span>
          </div>
        </div>
        <div class="mpwa-stat-card success">
          <div class="mpwa-stat-icon">✅</div>
          <div class="mpwa-stat-info">
            <span class="mpwa-stat-val"><?php echo MpwaLogger::count_success(); ?></span>
            <span class="mpwa-stat-lbl">ناجحة</span>
          </div>
        </div>
        <div class="mpwa-stat-card failed">
          <div class="mpwa-stat-icon">❌</div>
          <div class="mpwa-stat-info">
            <span class="mpwa-stat-val"><?php echo MpwaLogger::count_failed(); ?></span>
            <span class="mpwa-stat-lbl">فاشلة</span>
          </div>
        </div>
        <div class="mpwa-topbar-actions">
          <button type="button" class="mpwa-btn mpwa-btn-ghost mpwa-nav-btn-proxy" data-target="logs">📊 عرض السجلات</button>
          <button type="button" class="mpwa-btn mpwa-btn-primary mpwa-nav-btn-proxy" data-target="contacts">📇 إدارة العملاء</button>
        </div>
      </div>

      <form method="POST" class="mpwa-form">
      <?php wp_nonce_field('mpwa_save_settings','mpwa_nonce_field'); ?>
      <input type="hidden" name="mpwa_save" value="1">

      <div class="mpwa-content-area">

        <section class="mpwa-overview-grid" aria-label="نظرة عامة">
          <div class="mpwa-welcome-card">
            <span class="mpwa-welcome-eyebrow">WhatsApp Commerce</span>
            <h2>مرحباً بك في لوحة التحكم 👋</h2>
            <p>أدر رسائل العملاء والطلبات والجهاز المتصل من مكان واحد.</p>
            <div class="mpwa-welcome-actions">
              <button type="button" class="mpwa-btn mpwa-btn-primary mpwa-nav-btn-proxy" data-target="contacts">إرسال رسالة جديدة</button>
              <button type="button" class="mpwa-btn mpwa-btn-light mpwa-nav-btn-proxy" data-target="device">فحص الجهاز</button>
            </div>
          </div>
          <div class="mpwa-quick-card">
            <div class="mpwa-quick-card-icon">✓</div>
            <div><strong>حالة الإضافة</strong><span>جاهزة للعمل</span></div>
            <span class="mpwa-live-dot"></span>
          </div>
        </section>

        <!-- API Config -->
        <div class="mpwa-panel active" id="mpwa-panel-api">
          <div class="mpwa-card">
            <div class="mpwa-card-header">
              <h2>بيانات بوابة الواتساب الأساسية</h2>
              <p>قم بربط الإضافة مع مزود الخدمة الخاص بك لتبدأ بإرسال الرسائل.</p>
            </div>
            <div class="mpwa-card-body">
              <div class="mpwa-grid-2">
                <div class="mpwa-field">
                  <label>رابط البوابة (API URL)</label>
                  <input type="url" name="<?php echo $p; ?>url" value="<?php echo esc_attr(get_option($p.'url', 'https://app.x-growth.live')); ?>" placeholder="https://app.x-growth.live">
                </div>
                <div class="mpwa-field">
                  <label>رقم المُرسل المربوط</label>
                  <input type="text" name="<?php echo $p; ?>device" value="<?php echo esc_attr(get_option($p.'device')); ?>" placeholder="مثال: 96550000000">
                </div>
              </div>
              </div>
              <div class="mpwa-grid-2" style="margin-top:20px;">
                <div class="mpwa-field">
                  <label>مفتاح الربط (API Key)</label>
                  <div class="mpwa-input-group">
                    <input type="password" id="mpwa-api-key-input" name="<?php echo $p; ?>api_key" value="<?php echo esc_attr(get_option($p.'api_key')); ?>" placeholder="••••••••">
                    <button type="button" id="mpwa-toggle-apikey" class="mpwa-btn mpwa-btn-ghost">إظهار</button>
                  </div>
                </div>
                <div class="mpwa-field">
                  <label>رمز الدولة الافتراضي (بدون +)</label>
                  <input type="text" name="<?php echo $p; ?>default_country_code" value="<?php echo esc_attr(get_option($p.'default_country_code', '965')); ?>" placeholder="مثال: 966, 20, 965">
                  <p class="hint">يُستخدم عند عدم اختيار دولة الفوترة في الطلب. لا يتم تحديد الدولة عن طريق IP.</p>
                </div>
              </div>
              <div class="mpwa-actions-row" style="margin-top:20px;">
                <button type="button" id="mpwa-test-btn" class="mpwa-btn mpwa-btn-light">🔌 اختبار الاتصال</button>
                <button type="button" id="mpwa-send-test-msg-btn" class="mpwa-btn mpwa-btn-light">📱 إرسال رسالة تجربة لنفسي</button>
              </div>
            </div>
          </div>
          
          <div class="mpwa-card" style="margin-top:20px;">
            <div class="mpwa-card-header">
              <h2>المتغيرات (Shortcodes)</h2>
              <p>استخدم هذه المتغيرات داخل رسائلك لتخصيصها للعميل. (اضغط للنسخ)</p>
            </div>
            <div class="mpwa-card-body">
              <div class="mpwa-tags">
                <?php foreach($tags as $t): ?><span class="mpwa-tag"><?php echo esc_html($t); ?></span><?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Customer Alerts -->
        <div class="mpwa-panel" id="mpwa-panel-customer">
          <div class="mpwa-card">
            <div class="mpwa-card-header">
              <h2>تخصيص إشعارات العملاء</h2>
              <p>تفعيل أو تعطيل الإشعارات لكل حالة من حالات الطلب. يتم إرسالها عبر طابور صغير بفاصل عشوائي لتقليل التكرار والضغط على WhatsApp.</p>
            </div>
            <div class="mpwa-card-body">
              <div class="mpwa-status-grid">
              <?php foreach($statuses as $key=>$label):
                $k=str_replace('wc-','',$key);
                $on=get_option($p.'send_sms_'.$k, 'yes')==='yes';
                $tpl=get_option($p.$k.'_sms_template','');
              ?>
                <div class="mpwa-status-card">
                  <div class="mpwa-status-card-header">
                    <label class="mpwa-switch">
                      <input type="checkbox" name="<?php echo $p.'send_sms_'.$k; ?>" <?php checked($on); ?>>
                      <span class="mpwa-slider"></span>
                    </label>
                    <span class="mpwa-status-label"><?php echo esc_html($label); ?></span>
                  </div>
                  <div class="mpwa-status-card-body">
                    <textarea name="<?php echo $p.$k.'_sms_template'; ?>" placeholder="الرسالة الخاصة بهذه الحالة (اتركها فارغة لاستخدام القالب الافتراضي)"><?php echo esc_textarea($tpl); ?></textarea>
                  </div>
                </div>
              <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Admin Alerts -->
        <div class="mpwa-panel" id="mpwa-panel-admin">
          <div class="mpwa-card">
            <div class="mpwa-card-header">
              <h2>إشعارات واتساب للإدارة</h2>
            </div>
            <div class="mpwa-card-body">
              <div class="mpwa-toggle-row">
                <div class="mpwa-toggle-label">تفعيل إرسال تنبيه للإدارة عند كل طلب جديد</div>
                <label class="mpwa-switch"><input type="checkbox" name="<?php echo $p; ?>enable_admin_sms" <?php checked(get_option($p.'enable_admin_sms'),'yes'); ?>><span class="mpwa-slider"></span></label>
              </div>
              <div class="mpwa-field" style="margin-top:20px">
                <label>أرقام الإدارة (افصل بفاصلة لعدة أرقام)</label>
                <input type="text" name="<?php echo $p; ?>admin_sms_recipients" value="<?php echo esc_attr(get_option($p.'admin_sms_recipients')); ?>" placeholder="201001234567, 96655001234">
              </div>
              <div class="mpwa-field">
                <label>رسالة التنبيه للإدارة</label>
                <textarea name="<?php echo $p; ?>admin_sms_template"><?php echo esc_textarea(get_option($p.'admin_sms_template','طلب جديد #{{order_id}} من {{first_name}} {{last_name}}. المبلغ: {{order_amount}}.')); ?></textarea>
              </div>
            </div>
          </div>

          <div class="mpwa-card" style="margin-top:20px;">
            <div class="mpwa-card-header">
              <h2>إشعارات تليجرام للإدارة</h2>
            </div>
            <div class="mpwa-card-body">
              <div class="mpwa-toggle-row">
                <div class="mpwa-toggle-label">استلام إشعارات الطلبات على تليجرام</div>
                <label class="mpwa-switch"><input type="checkbox" name="<?php echo $p; ?>enable_telegram_sms" <?php checked(get_option($p.'enable_telegram_sms'),'yes'); ?>><span class="mpwa-slider"></span></label>
              </div>
              <div class="mpwa-grid-2" style="margin-top:20px">
                <div class="mpwa-field">
                  <label>Bot Token</label>
                  <input type="text" name="<?php echo $p; ?>telegram_bot_token" value="<?php echo esc_attr(get_option($p.'telegram_bot_token')); ?>">
                </div>
                <div class="mpwa-field">
                  <label>Chat ID</label>
                  <input type="text" name="<?php echo $p; ?>telegram_chat_id" value="<?php echo esc_attr(get_option($p.'telegram_chat_id')); ?>">
                </div>
              </div>
              <div class="mpwa-field">
                <label>رسالة تليجرام</label>
                <textarea name="<?php echo $p; ?>telegram_template"><?php echo esc_textarea(get_option($p.'telegram_template','طلب جديد #{{order_id}} من {{first_name}} {{last_name}}. المبلغ: {{order_amount}}.')); ?></textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- Templates -->
        <div class="mpwa-panel" id="mpwa-panel-templates">
          <div class="mpwa-card">
            <div class="mpwa-card-header">
              <h2>القوالب الافتراضية والملاحظات</h2>
            </div>
            <div class="mpwa-card-body">
              <div class="mpwa-field">
                <label>القالب الافتراضي العام</label>
                <textarea name="<?php echo $p; ?>default_sms_template"><?php echo esc_textarea(get_option($p.'default_sms_template','مرحباً {{first_name}}، طلبك #{{order_id}} أصبح {{order_status}}. شكراً لتسوقك في {{shop_name}}!')); ?></textarea>
                <p class="hint">هذا القالب يعمل للحالات التي لم تقم بتخصيص رسالة لها.</p>
              </div>
              <hr style="border:none; border-top:1px solid var(--c-border); margin:20px 0;">
              <div class="mpwa-toggle-row">
                <div class="mpwa-toggle-label">إشعار العميل عند إضافة "ملاحظة عميل" للطلب</div>
                <label class="mpwa-switch"><input type="checkbox" name="<?php echo $p; ?>enable_notes_sms" <?php checked(get_option($p.'enable_notes_sms'),'yes'); ?>><span class="mpwa-slider"></span></label>
              </div>
              <div class="mpwa-field" style="margin-top:20px">
                <label>مقدمة رسالة الملاحظة</label>
                <textarea name="<?php echo $p; ?>note_sms_template"><?php echo esc_textarea(get_option($p.'note_sms_template','مرحباً {{first_name}}، ملاحظة جديدة على طلبك #{{order_id}}: ')); ?></textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- AI Bot Integration -->
        <div class="mpwa-panel" id="mpwa-panel-ai">
          <div class="mpwa-card">
            <div class="mpwa-card-header" style="background: linear-gradient(135deg, #128C7E 0%, #25D366 100%); color:white;">
              <h2 style="color:white; margin:0; display:flex; align-items:center; gap:10px;">🤖 مندوب المبيعات الذكي (Gemini)</h2>
              <p style="color:rgba(255,255,255,0.8); margin-top:5px;">حوّل الويب هوك الخاص بك لبوت ذكي يبيع بالنيابة عنك.</p>
            </div>
            <div class="mpwa-card-body">
              <div class="mpwa-toggle-row" style="background:#f0f2f5; border-color:#e0e0e0;">
                <div class="mpwa-toggle-label">تفعيل الذكاء الاصطناعي للمحادثات الواردة</div>
                <label class="mpwa-switch"><input type="checkbox" name="<?php echo $p; ?>enable_ai_bot" <?php checked(get_option($p.'enable_ai_bot'),'yes'); ?>><span class="mpwa-slider"></span></label>
              </div>
              
              <div class="mpwa-field" style="margin-top:25px">
                <label>مفتاح ربط (Google Gemini API Key)</label>
                <input type="password" name="<?php echo $p; ?>gemini_api_key" value="<?php echo esc_attr(get_option($p.'gemini_api_key')); ?>" placeholder="AIza..." style="direction:ltr; text-align:left;">
                <p class="hint">يمكنك جلبه مجاناً من Google AI Studio.</p>
              </div>

              <div class="mpwa-field">
                <label>تعليمات الذكاء الاصطناعي (System Prompt)</label>
                <textarea name="<?php echo $p; ?>gemini_system_prompt" rows="5"><?php echo esc_textarea(get_option($p.'gemini_system_prompt', "أنت مندوب مبيعات محترف لمتجرنا. \nمهمتك مساعدة العملاء، والبحث عن المنتجات التي يطلبونها، ومساعدتهم في إنشاء طلب جديد.\nتحدث بلهجة ودودة.")); ?></textarea>
              </div>

              <div class="mpwa-tester-box">
                <label>🧪 تجربة سريعة للبوت</label>
                <div class="mpwa-input-group">
                  <input type="text" id="mpwa-ai-test-input" placeholder="اكتب سؤالاً لتجربة البوت...">
                  <button type="button" id="mpwa-test-ai-btn" class="mpwa-btn mpwa-btn-primary">اختبار الرد</button>
                </div>
                <div id="mpwa-ai-test-response" class="mpwa-tester-response"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Advanced -->
        <div class="mpwa-panel" id="mpwa-panel-advanced">
          <div class="mpwa-card" style="margin-bottom:20px;">
            <div class="mpwa-card-header">
              <h2>إدارة ميزات الإضافة</h2>
              <p>قم بتفعيل أو تعطيل الميزات الإضافية للتحكم بأداء الموقع.</p>
            </div>
            <div class="mpwa-card-body">
              <div class="mpwa-toggle-row">
                <div class="mpwa-toggle-label">تفعيل نظام السلات المتروكة (Abandoned Cart)</div>
                <label class="mpwa-switch"><input type="checkbox" name="<?php echo $p; ?>enable_feature_abandoned_cart" <?php checked(get_option($p.'enable_feature_abandoned_cart', 'yes'), 'yes'); ?>><span class="mpwa-slider"></span></label>
              </div>
              <hr style="border:none; border-top:1px solid var(--c-border); margin:15px 0;">
              <div class="mpwa-toggle-row">
                <div class="mpwa-toggle-label">تفعيل نظام الدخول السريع (OTP Login)</div>
                <label class="mpwa-switch"><input type="checkbox" name="<?php echo $p; ?>enable_feature_otp_login" <?php checked(get_option($p.'enable_feature_otp_login', 'yes'), 'yes'); ?>><span class="mpwa-slider"></span></label>
              </div>
              <hr style="border:none; border-top:1px solid var(--c-border); margin:15px 0;">
                <div class="mpwa-toggle-row">
                <div class="mpwa-toggle-label">تفعيل نظام الويب هوك (Webhook) للرد التلقائي وتأكيد الطلبات</div>
                <label class="mpwa-switch"><input type="checkbox" name="<?php echo $p; ?>enable_feature_webhook" <?php checked(get_option($p.'enable_feature_webhook', 'yes'), 'yes'); ?>><span class="mpwa-slider"></span></label>
              </div>
              <hr style="border:none; border-top:1px solid var(--c-border); margin:15px 0;">
              <div class="mpwa-toggle-row">
                <div class="mpwa-toggle-label">تفعيل حماية الدخول للمديرين (2FA) عبر الواتساب</div>
                <label class="mpwa-switch"><input type="checkbox" name="<?php echo $p; ?>enable_admin_2fa" <?php checked(get_option($p.'enable_admin_2fa', 'no'), 'yes'); ?>><span class="mpwa-slider"></span></label>
              </div>
              <hr style="border:none; border-top:1px solid var(--c-border); margin:15px 0;">
              <div class="mpwa-toggle-row">
                <div class="mpwa-toggle-label">إرسال الفاتورة الإلكترونية عبر الواتساب عند اكتمال الطلب (Completed)</div>
                <label class="mpwa-switch"><input type="checkbox" name="<?php echo $p; ?>enable_invoice_receipt" <?php checked(get_option($p.'enable_invoice_receipt', 'no'), 'yes'); ?>><span class="mpwa-slider"></span></label>
              </div>
              <hr style="border:none; border-top:1px solid var(--c-border); margin:15px 0;">
              <div class="mpwa-toggle-row">
                <div class="mpwa-toggle-label">إرسال استطلاع تقييم التجربة (Poll) بعد 24 ساعة من اكتمال الطلب</div>
                <label class="mpwa-switch"><input type="checkbox" name="<?php echo $p; ?>enable_feedback_poll" <?php checked(get_option($p.'enable_feedback_poll', 'no'), 'yes'); ?>><span class="mpwa-slider"></span></label>
              </div>
            </div>
          </div>

          <div class="mpwa-card" style="margin-bottom:20px;">
            <div class="mpwa-card-header">
              <h2>النشر التلقائي في قناة الواتساب</h2>
              <p>سيتم نشر المنتجات الجديدة تلقائياً في القناة المحددة أدناه.</p>
            </div>
            <div class="mpwa-card-body">
              <div class="mpwa-field">
                <label>رابط قناة واتساب</label>
                <input type="url" name="<?php echo $p; ?>whatsapp_channel_id" value="<?php echo esc_attr(get_option($p.'whatsapp_channel_id', '')); ?>" placeholder="https://whatsapp.com/channel/ABCDEF123456">
                <p class="mpwa-hint">تأكد من أن حساب الواتساب الخاص بك هو مدير في هذه القناة.</p>
              </div>
            </div>
          </div>

          <div class="mpwa-card">
            <div class="mpwa-card-header">
              <h2>الويب هوك (Webhook) للربط العكسي</h2>
              <p>ضع هذا الرابط في منصة الإرسال الخاصة بك لاستقبال ردود العملاء.</p>
            </div>
            <div class="mpwa-card-body">
              <div class="mpwa-field">
                <input type="text" class="mpwa-copy-input" readonly value="<?php echo esc_url( rest_url('mpwa/v1/webhook') ); ?>" onfocus="this.select();">
              </div>
              <div class="mpwa-field">
                <label>Webhook Secret</label>
                <input type="password" name="<?php echo esc_attr( $p ); ?>webhook_secret" value="<?php echo esc_attr( get_option( $p . 'webhook_secret', '' ) ); ?>" autocomplete="new-password">
                <p class="mpwa-hint">أرسل القيمة في الترويسة X-MPWA-Webhook-Secret، أو استخدمها لتوقيع جسم الطلب بـ HMAC-SHA256 في X-MPWA-Signature.</p>
              </div>
            </div>
          </div>

          <div class="mpwa-card" style="margin-top:20px;">
            <div class="mpwa-card-header">
              <h2>نموذج تسجيل الدخول بالواتساب (OTP)</h2>
            </div>
            <div class="mpwa-card-body">
              <p>شورت كود يمكنك وضعه في أي صفحة לעرض نموذج الدخول بالواتساب:</p>
              <div class="mpwa-field">
                <input type="text" class="mpwa-copy-input text-center" readonly value="[mpwa_otp_login]" onfocus="this.select();">
              </div>
            </div>
          </div>

          <div class="mpwa-card" style="margin-top:20px;">
            <div class="mpwa-card-header">
              <h2>تخصيص مربع الاشتراك في النشرة البريدية (WhatsApp Newsletter)</h2>
              <p>تحكم في المظهر والنصوص الترويجية مع دعم 6 ستايلات عصرية تناسب كل مكان في متجرك (بدون أعلام مزعجة وبأعلى تجاوب).</p>
            </div>
            <div class="mpwa-card-body">
              <div class="mpwa-grid-2">
                <div class="mpwa-field">
                  <label>الستايل الافتراضي للشورت كود [mpwa_newsletter]</label>
                  <?php $cur_style = get_option( $p . 'newsletter_default_style', 'simple' ); ?>
                  <select name="<?php echo esc_attr( $p ); ?>newsletter_default_style">
                    <option value="simple" <?php selected( $cur_style, 'simple' ); ?>>⚡ بسيط وعادي (مربع إدخال وبجواره زر - الافتراضي)</option>
                    <option value="card" <?php selected( $cur_style, 'card' ); ?>>🌟 بطاقة عصرية فاخرة (Modern Card)</option>
                    <option value="inline" <?php selected( $cur_style, 'inline' ); ?>>📏 شريط أفقي مدمج (Minimal Inline Banner للفوتر والأشرطة)</option>
                    <option value="dark" <?php selected( $cur_style, 'dark' ); ?>>🌙 الوضع الداكن الفاخر (VIP Dark Glassmorphism)</option>
                    <option value="floating" <?php selected( $cur_style, 'floating' ); ?>>📌 شريط عائم أسفل المتجر (Sticky Floating Bar مع زر إغلاق)</option>
                    <option value="minimal" <?php selected( $cur_style, 'minimal' ); ?>>✨ تصميم مينيمال بسيط (Clean Minimal للودجات والقوائم)</option>
                    <option value="gradient" <?php selected( $cur_style, 'gradient' ); ?>>💎 التدرج الزمردي الملكي (Royal Emerald Gradient)</option>
                  </select>
                  <p class="mpwa-hint">يحدد الستايل الذي سيظهر عند كتابة <code>[mpwa_newsletter]</code> بدون تحديد style.</p>
                </div>

                <div class="mpwa-field">
                  <label>شارة التمييز العلوية (Badge)</label>
                  <input type="text" name="<?php echo esc_attr( $p ); ?>newsletter_badge" value="<?php echo esc_attr( get_option( $p . 'newsletter_badge', 'عروض حصرية 🔥' ) ); ?>" placeholder="عروض حصرية 🔥">
                </div>
              </div>

              <div class="mpwa-grid-2">
                <div class="mpwa-field">
                  <label>عنوان مربع الاشتراك</label>
                  <input type="text" name="<?php echo esc_attr( $p ); ?>newsletter_title" value="<?php echo esc_attr( get_option( $p . 'newsletter_title', 'انضم إلى مجتمعنا على واتساب! 💬' ) ); ?>" placeholder="انضم إلى مجتمعنا على واتساب! 💬">
                </div>

                <div class="mpwa-field">
                  <label>نص زر الاشتراك</label>
                  <input type="text" name="<?php echo esc_attr( $p ); ?>newsletter_btn_text" value="<?php echo esc_attr( get_option( $p . 'newsletter_btn_text', 'اشترك الآن ⚡' ) ); ?>" placeholder="اشترك الآن ⚡">
                </div>
              </div>

              <div class="mpwa-field">
                <label>الوصف الترويجي</label>
                <textarea name="<?php echo esc_attr( $p ); ?>newsletter_desc" rows="2" placeholder="كن أول من يعلم بأحدث العروض والكوبونات والتخفيضات الحصرية مباشرة عبر واتساب."><?php echo esc_textarea( get_option( $p . 'newsletter_desc', 'كن أول من يعلم بأحدث العروض والكوبونات والتخفيضات الحصرية مباشرة عبر واتساب.' ) ); ?></textarea>
              </div>

              <div class="mpwa-field">
                <label>النص التوضيحي داخل حقل الهاتف (Placeholder)</label>
                <input type="text" name="<?php echo esc_attr( $p ); ?>newsletter_placeholder" value="<?php echo esc_attr( get_option( $p . 'newsletter_placeholder', 'أدخل رقم الواتساب (مثال: 05xxxxxxxx)' ) ); ?>" placeholder="أدخل رقم الواتساب (مثال: 05xxxxxxxx)">
                <p class="mpwa-hint">يتم تطبيع وتنسيق الأرقام تلقائياً مع كود الدولة المحدد لمتجرك دون الحاجة لأعلام أو تعقيدات.</p>
              </div>

              <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--c-border);">
                <h3 style="font-size: 15px; font-weight: 800; margin-bottom: 12px; color: var(--c-primary-dark);">📋 دليل الشورت كود وخيارات الستايلات المتاحة:</h3>
                <div class="mpwa-table-responsive">
                  <table class="mpwa-table" style="font-size: 13px;">
                    <thead>
                      <tr>
                        <th>الستايل</th>
                        <th>الشورت كود الجاهز</th>
                        <th>أفضل استخدام</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td><strong>الافتراضي</strong></td>
                        <td><code>[mpwa_newsletter]</code></td>
                        <td>يستخدم الإعدادات المحددة بالأعلى تلقائياً.</td>
                      </tr>
                      <tr>
                        <td><strong>بطاقة فاخرة (Card)</strong></td>
                        <td><code>[mpwa_newsletter style="card"]</code></td>
                        <td>الصفحة الرئيسية، صفحات الهبوط، أو أسفل المقالات.</td>
                      </tr>
                      <tr>
                        <td><strong>شريط أفقي (Inline)</strong></td>
                        <td><code>[mpwa_newsletter style="inline"]</code></td>
                        <td>تذييل الموقع (Footer)، أو شريط كامل العرض.</td>
                      </tr>
                      <tr>
                        <td><strong>الوضع الداكن (Dark)</strong></td>
                        <td><code>[mpwa_newsletter style="dark"]</code></td>
                        <td>المواقع ذات الخلفيات الداكنة وعروض الـ VIP.</td>
                      </tr>
                      <tr>
                        <td><strong>شريط عائم (Floating)</strong></td>
                        <td><code>[mpwa_newsletter style="floating"]</code></td>
                        <td>شريط مثبت أسفل المتصفح يظهر للزائر مع زر إغلاق.</td>
                      </tr>
                      <tr>
                        <td><strong>مينيمال (Minimal)</strong></td>
                        <td><code>[mpwa_newsletter style="minimal"]</code></td>
                        <td>الشريط الجانبي (Sidebar) والمساحات الصغيرة.</td>
                      </tr>
                      <tr>
                        <td><strong>تدرج زمردي (Gradient)</strong></td>
                        <td><code>[mpwa_newsletter style="gradient"]</code></td>
                        <td>البانرات الترويجية وصفحات العروض الكبرى.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <p class="mpwa-hint" style="margin-top: 10px;">💡 يمكنك أيضاً تخصيص النصوص داخل أي شورت كود، مثال: <code>[mpwa_newsletter style="inline" title="عروض خاصة" button="احصل على الخصم"]</code></p>
              </div>
            </div>
          </div>
        </div>

        <!-- Device Management -->
        <div class="mpwa-panel" id="mpwa-panel-api-tools">
          <div class="mpwa-card">
            <div class="mpwa-card-header"><h2>أدوات بوابة X-Growth</h2><p>إرسال مباشر وتجربة وظائف البوابة من داخل لوحة التحكم.</p></div>
            <div class="mpwa-card-body">
              <div class="mpwa-field"><label>نوع الإرسال</label><select id="mpwa-tool-type"><option value="text">رسالة نصية</option><option value="media">صورة أو ملف</option><option value="sticker">استيكر</option><option value="product">منتج واتساب</option><option value="channel">رسالة إلى قناة</option><option value="button">أزرار تفاعلية</option><option value="poll">استطلاع</option><option value="list">قائمة تفاعلية</option><option value="location">موقع جغرافي</option><option value="vcard">بطاقة جهة اتصال</option><option value="check">فحص رقم واتساب</option></select></div>
              <div class="mpwa-field"><label>رقم الهاتف الدولي</label><input id="mpwa-tool-number" type="text" placeholder="96594067042"></div>
              <div class="mpwa-field mpwa-tool-url-wrap" style="display:none"><label>رابط الملف أو الصورة</label><input id="mpwa-tool-url" type="url" placeholder="https://example.com/file.jpg"></div>
              <div class="mpwa-field"><label>الرسالة</label><textarea id="mpwa-tool-message" rows="5" placeholder="اكتب رسالة التجربة هنا"></textarea></div>
              <div class="mpwa-field mpwa-tool-json-wrap" style="display:none"><label>البيانات الإضافية (JSON)</label><textarea id="mpwa-tool-json" rows="7" placeholder='{"options":["نعم","لا"],"countable":1}'></textarea><small id="mpwa-tool-help">ستظهر هنا صيغة جاهزة حسب النوع.</small></div>
              <button type="button" id="mpwa-tool-send" class="mpwa-btn mpwa-btn-primary">📤 إرسال رسالة عبر البوابة</button>
              <div id="mpwa-tool-result" class="mpwa-notice" style="display:none;margin-top:14px;"></div>
            </div>
          </div>
        </div>

        <!-- Device Management -->
        <div class="mpwa-panel" id="mpwa-panel-device">
          <div class="mpwa-card">
            <div class="mpwa-card-header">
              <h2>حالة الجهاز والاشتراك (X-Growth)</h2>
              <p>قم بمراقبة حالة اتصال رقمك واشتراكك عبر البوابة مباشرة.</p>
            </div>
            <div class="mpwa-card-body">
              <div id="mpwa-device-status-container" style="text-align: center; padding: 20px;">
                <button type="button" id="mpwa-check-device-btn" class="mpwa-btn mpwa-btn-primary">فحص حالة الجهاز 🔄</button>
                <div id="mpwa-device-status-result" style="margin-top:20px; text-align:right;"></div>
                <div id="mpwa-device-qr-container" style="margin-top:20px; display:none;">
                    <button type="button" id="mpwa-generate-qr-btn" class="mpwa-btn mpwa-btn-success">توليد كود QR للربط 📲</button>
                    <div id="mpwa-qr-image-wrapper" style="margin-top:15px;"></div>
                </div>
                <div id="mpwa-device-logout-container" style="margin-top:20px; display:none;">
                    <button type="button" id="mpwa-logout-device-btn" class="mpwa-btn mpwa-btn-danger">تسجيل الخروج من الواتساب 🚪</button>
                    <button type="button" id="mpwa-delete-device-btn" class="mpwa-btn mpwa-btn-danger">حذف الجهاز نهائيًا 🗑</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Updates Panel -->
        <div class="mpwa-panel" id="mpwa-panel-updates">
          <div class="mpwa-card">
            <div class="mpwa-card-header">
              <h2>نظام التحديثات التلقائية عبر GitHub (Auto-Updater)</h2>
              <p>استلم وقم بتثبيت أحدث ميزات وترقيات الإضافة تلقائياً بمجرد إطلاقها على GitHub بضغطة زر واحدة داخل ووردبريس.</p>
            </div>
            <div class="mpwa-card-body">
              <!-- Live Status Banner -->
              <div style="background: linear-gradient(135deg, #091e28 0%, #0d3238 100%); color:#fff; border-radius:14px; padding:22px; margin-bottom:24px; border:1px solid rgba(255,255,255,0.1);">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
                  <div>
                    <span style="font-size:12px; color:#94a3b8; display:block; margin-bottom:4px;">الإصدار المثبت حالياً</span>
                    <strong style="font-size:22px; color:#25D366; font-family:Consolas, monospace;">v<?php echo esc_html( MPWA_VERSION ); ?></strong>
                    <span class="mpwa-badge" style="background:rgba(37,211,102,0.15); color:#25D366; border:1px solid rgba(37,211,102,0.3); margin-right:10px;">إصدار نشط</span>
                  </div>
                  <div style="text-align:left;">
                    <button type="button" id="mpwa-check-gh-update-btn" class="mpwa-btn mpwa-btn-primary" style="padding:12px 20px; font-weight:800;">
                      <span>🔄 فحص التحديثات الآن من GitHub</span>
                    </button>
                  </div>
                </div>
                <div id="mpwa-gh-update-result" style="display:none; margin-top:18px; padding:14px; border-radius:10px; font-size:14px; line-height:1.6;"></div>
              </div>

              <!-- Repository Settings -->
              <h3 style="font-size:16px; font-weight:800; margin-bottom:14px; color:var(--c-text);">⚙️ إعدادات مستودع GitHub (Repository Settings)</h3>
              <div class="mpwa-grid-2">
                <div class="mpwa-field">
                  <label>اسم المستودع على GitHub (Owner/Repository)</label>
                  <?php
                    $cur_repo = get_option( $p . 'github_repo', '' );
                    if ( empty( $cur_repo ) || $cur_repo === 'alahm7dy/mpwa-woocommerce' ) {
                        $cur_repo = 'alahm7dy/alahm7dywa';
                    }
                  ?>
                  <input type="text" name="<?php echo esc_attr( $p ); ?>github_repo" value="<?php echo esc_attr( $cur_repo ); ?>" placeholder="مثال: alahm7dy/alahm7dywa" dir="ltr" style="text-align:left;">
                  <p class="mpwa-hint">يجب أن يتطابق مع اسم حسابك واسم المستودع على GitHub بصيغة <code>username/repo</code>.</p>
                </div>

                <div class="mpwa-field">
                  <label>رمز الوصول الشخصي GitHub Access Token (اختياري)</label>
                  <input type="password" name="<?php echo esc_attr( $p ); ?>github_token" value="<?php echo esc_attr( get_option( $p . 'github_token', '' ) ); ?>" placeholder="ghp_xxxxxxxxxxxxxxxxxxxx" autocomplete="new-password" dir="ltr" style="text-align:left;">
                  <p class="mpwa-hint">مطلوب فقط في حال كان المستودع <strong>خاصاً (Private)</strong>. إذا كان المستودع عاماً اتركه فارغاً.</p>
                </div>
              </div>

              <!-- Developer Guide -->
              <div style="margin-top:24px; padding-top:20px; border-top:1px solid var(--c-border);">
                <h3 style="font-size:15px; font-weight:800; margin-bottom:12px; color:var(--c-primary-dark);">📖 كيف تنشر أي تحديث جديد ليصل لكل المواقع تلقائياً؟</h3>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:18px; line-height:1.8; font-size:13.5px;">
                  <ol style="margin:0; padding-right:20px;">
                    <li><strong>تغيير رقم الإصدار:</strong> افتح ملف <code>mpwa-woocommerce.php</code> وغير رقم الإصدار (مثلاً من <code>3.0.0</code> إلى <code>3.1.0</code>).</li>
                    <li><strong>رفع الكود على GitHub:</strong> ارفع تعديلاتك على المستودع عبر Git مع Tag برقم الإصدار:
                      <div style="direction:ltr; text-align:left; background:#0f172a; color:#f8fafc; padding:8px 12px; border-radius:6px; margin:6px 0; font-family:Consolas, monospace; font-size:13px;">
                        git add .<br>
                        git commit -m "إصدار جديد 3.1.0"<br>
                        git tag v3.1.0<br>
                        git push origin main --tags
                      </div>
                    </li>
                    <li><strong>الإنشاء التلقائي (Automation):</strong> ملف الـ GitHub Action المدمج يقوم آلياً بضغط الإضافة وتوليد الـ Release وربط ملف الـ ZIP مباشرة.</li>
                    <li><strong>الوصول للمواقع:</strong> خلال دقائق، يظهر إشعار التحديث البرتقالي داخل ووردبريس لجميع المواقع التي تستخدم الإضافة ليقوموا بالتحديث بضغطة زر واحدة!</li>
                  </ol>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Logs Panel -->
        <div class="mpwa-panel" id="mpwa-panel-logs">
          <div class="mpwa-card">
            <div class="mpwa-card-header" style="display:flex; justify-content:space-between; align-items:center;">
              <div>
                <h2>📋 سجل العمليات</h2>
                <p>سجل الرسائل المرسلة وحالتها.</p>
              </div>
              <button type="button" id="mpwa-clear-logs-btn" class="mpwa-btn mpwa-btn-danger">🗑 مسح الكل</button>
            </div>
            <div class="mpwa-card-body">
              <?php
              $logs = MpwaLogger::get_logs(100);
              if ( empty($logs) ): ?>
                <div style="text-align:center;padding:40px;color:var(--c-text-muted);">📭 لا توجد سجلات.</div>
              <?php else: ?>
                <div class="mpwa-table-wrap">
                  <table class="mpwa-table" style="width:100%; text-align:right;">
                    <thead><tr><th>#</th><th>الوقت</th><th>طلب</th><th>المستلم</th><th>الحدث</th><th>الحالة</th><th>تفاصيل</th></tr></thead>
                    <tbody>
                    <?php foreach($logs as $log): 
                      $badge = $log->status==='success' ? 'success' : ($log->status==='failed' ? 'failed' : 'error');
                    ?>
                      <tr class="mpwa-log-row">
                        <td><?php echo absint($log->id); ?></td>
                        <td style="direction:ltr;"><?php echo esc_html($log->created_at); ?></td>
                        <td><?php echo $log->order_id ? '#'.absint($log->order_id) : '—'; ?></td>
                        <td style="direction:ltr;"><?php echo esc_html($log->recipient); ?></td>
                        <td><?php echo esc_html($log->event_type); ?></td>
                        <td><span class="mpwa-badge mpwa-badge-<?php echo $badge; ?>"><?php echo esc_html($log->status); ?></span></td>
                        <td>
                          <button type="button" class="mpwa-btn mpwa-btn-ghost mpwa-log-detail-btn" style="padding:4px 8px;font-size:11px;">عرض</button>
                          <?php if ( $log->status !== 'success' ) : ?>
                            <button type="button" class="mpwa-btn mpwa-btn-light mpwa-retry-log" data-id="<?php echo absint($log->id); ?>" style="padding:4px 8px;font-size:11px;margin-right:4px;">🔄 إعادة</button>
                          <?php endif; ?>
                        </td>
                      </tr>
                      <tr class="mpwa-log-detail-tr" style="display:none; background:#f9fafb;">
                        <td colspan="7" style="padding:15px; border-bottom:1px solid #e5e7eb;">
                          <div style="font-size:12px; margin-bottom:10px;"><strong>الرسالة:</strong><br><span style="white-space:pre-wrap;"><?php echo esc_html($log->message); ?></span></div>
                          <?php if($log->api_response): ?><div style="font-size:12px; margin-bottom:10px;"><strong>الرد:</strong><br><code><?php echo esc_html($log->api_response); ?></code></div><?php endif; ?>
                          <?php if($log->error_msg): ?><div style="font-size:12px; color:red;"><strong>الخطأ:</strong><br><?php echo esc_html($log->error_msg); ?></div><?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Contacts Panel -->
        <div class="mpwa-panel" id="mpwa-panel-contacts">
          <div class="mpwa-card">
            <div class="mpwa-card-header" style="display:flex; justify-content:space-between; align-items:center;">
              <div>
                <h2>📇 دفتر العملاء</h2>
                <p>إدارة أرقام العملاء والرسائل الجماعية.</p>
              </div>
              <div>
                <button type="button" id="mpwa-sync-orders-btn" class="mpwa-btn mpwa-btn-light">🔄 مزامنة الطلبات القديمة</button>
                <button type="button" id="mpwa-export-contacts-btn" class="mpwa-btn mpwa-btn-light">📥 تصدير CSV</button>
                <button type="button" id="mpwa-add-contact-btn" class="mpwa-btn mpwa-btn-primary">➕ إضافة رقم</button>
              </div>
            </div>
            
            <div class="mpwa-card-body" style="background:#f0fdf4; border-bottom:1px solid var(--c-border);">
              <h3 style="margin-top:0; font-size:14px; color:#166534;">📢 إرسال حملة تسويقية ذكية</h3>
              <div style="display:flex; gap:10px; margin-bottom:10px;">
                <select id="mpwa-bulk-target" style="flex:1;">
                    <option value="all">الكل (جميع المسجلين والعملاء)</option>
                    <option value="newsletter">المشتركين في النشرة فقط</option>
                    <option value="buyers">العملاء الذين قاموا بالشراء فقط</option>
                </select>
                <input type="text" id="mpwa-bulk-media" placeholder="رابط صورة (اختياري)" style="flex:1;">
                <input type="text" id="mpwa-bulk-btn-text" placeholder="نص الزر (اختياري)" style="flex:1;">
              </div>
              <textarea id="mpwa-bulk-msg" placeholder="اكتب رسالتك لإرسالها..." style="width:100%; min-height:80px; margin-bottom:10px;"></textarea>
              <button type="button" id="mpwa-send-bulk-btn" class="mpwa-btn mpwa-btn-primary" style="width:100%;">🚀 إرسال الحملة الآن (<?php echo MpwaPhoneBook::count(); ?>)</button>
            </div>

            <div class="mpwa-card-body">
              <?php
              $contacts = MpwaPhoneBook::get_all(100, 0, '');
              if(empty($contacts)): ?>
                <div style="text-align:center;padding:40px;color:var(--c-text-muted);">📇 لا يوجد عملاء.</div>
              <?php else: ?>
                <div class="mpwa-table-wrap">
                  <table class="mpwa-table" style="width:100%; text-align:right;">
                    <thead><tr><th>الاسم</th><th>الرقم</th><th>المصدر</th><th>الطلبات</th><th>إجراء</th></tr></thead>
                    <tbody>
                    <?php foreach($contacts as $c): ?>
                      <tr>
                        <td><strong><?php echo esc_html($c->name); ?></strong></td>
                        <td style="direction:ltr;"><?php echo esc_html($c->phone); ?></td>
                        <td><span class="mpwa-badge"><?php echo esc_html( $c->source === 'woocommerce' ? 'طلب' : ( $c->source === 'newsletter' ? 'نشرة' : 'يدوي' ) ); ?></span></td>
                        <td><?php echo absint($c->order_count); ?></td>
                        <td>
                          <button type="button" class="mpwa-btn mpwa-btn-primary mpwa-send-to-contact" data-phone="<?php echo esc_attr($c->phone); ?>" data-name="<?php echo esc_attr($c->name); ?>" style="padding:4px 8px;font-size:11px;">إرسال</button>
                          <button type="button" class="mpwa-btn mpwa-btn-danger mpwa-delete-contact" data-id="<?php echo absint($c->id); ?>" style="padding:4px 8px;font-size:11px;">حذف</button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

      </div> <!-- End Content Area -->

      <!-- Save Bar -->
      <div class="mpwa-save-bar">
        <p class="mpwa-save-hint">تم إجراء تغييرات غير محفوظة.</p>
        <button type="submit" class="mpwa-btn mpwa-btn-primary mpwa-btn-lg">حفظ التغييرات</button>
      </div>

      </form>
    </main>
  </div>

  <!-- Modal: Send Direct Message -->
  <div id="mpwa-send-modal" class="mpwa-modal-overlay">
    <div class="mpwa-modal-dialog">
      <div class="mpwa-modal-header">
        <h3 id="mpwa-modal-title">إرسال رسالة واتساب مباشرة</h3>
        <button type="button" class="mpwa-modal-close" aria-label="إغلاق">&times;</button>
      </div>
      <div class="mpwa-modal-body">
        <div class="mpwa-field">
          <label>رقم المستلم</label>
          <input type="text" id="mpwa-modal-phone" readonly class="mpwa-input-readonly" style="direction:ltr; text-align:left;">
        </div>
        <div class="mpwa-field" style="margin-top:14px;">
          <label>نص الرسالة</label>
          <textarea id="mpwa-modal-msg" rows="5" placeholder="اكتب رسالتك هنا للعميل..."></textarea>
        </div>
      </div>
      <div class="mpwa-modal-footer">
        <button type="button" id="mpwa-modal-send-btn" class="mpwa-btn mpwa-btn-primary">📤 إرسال الآن</button>
        <button type="button" id="mpwa-modal-cancel" class="mpwa-btn mpwa-btn-ghost">إلغاء</button>
      </div>
    </div>
  </div>

  <!-- Modal: Add New Contact -->
  <div id="mpwa-add-contact-modal" class="mpwa-modal-overlay">
    <div class="mpwa-modal-dialog">
      <div class="mpwa-modal-header">
        <h3>إضافة جهة اتصال جديدة 📇</h3>
        <button type="button" class="mpwa-modal-close" aria-label="إغلاق">&times;</button>
      </div>
      <div class="mpwa-modal-body">
        <div class="mpwa-field">
          <label>الاسم <span class="required" style="color:red;">*</span></label>
          <input type="text" id="mpwa-new-name" placeholder="مثال: محمد عبدالله">
        </div>
        <div class="mpwa-field" style="margin-top:12px;">
          <label>رقم الهاتف الدولي <span class="required" style="color:red;">*</span></label>
          <input type="tel" id="mpwa-new-phone" placeholder="965xxxxxxxx أو 966xxxxxxxxx" style="direction:ltr; text-align:left;">
          <p class="hint">اكتب الرقم بكود الدولة بدون أصفار بادئة وبدون علامة +.</p>
        </div>
        <div class="mpwa-field" style="margin-top:12px;">
          <label>البريد الإلكتروني (اختياري)</label>
          <input type="email" id="mpwa-new-email" placeholder="example@mail.com" style="direction:ltr; text-align:left;">
        </div>
        <div class="mpwa-field" style="margin-top:12px;">
          <label>الوسوم / التاغات (مفصولة بفاصلة)</label>
          <input type="text" id="mpwa-new-tags" placeholder="vip, عميل مميز, عطور">
        </div>
      </div>
      <div class="mpwa-modal-footer">
        <button type="button" id="mpwa-add-contact-submit" class="mpwa-btn mpwa-btn-primary">💾 حفظ جهة الاتصال</button>
        <button type="button" class="mpwa-btn mpwa-btn-ghost mpwa-modal-close">إلغاء</button>
      </div>
    </div>
  </div>

</div>

