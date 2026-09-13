<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MpwaPdfInvoice {

    public function __construct() {
        add_action( 'template_redirect', [ $this, 'render_receipt' ] );
    }

    public function render_receipt() {
        if ( ! isset( $_GET['mpwa_receipt'] ) ) return;

        $order_key = sanitize_text_field( wp_unslash( $_GET['mpwa_receipt'] ) );
        if ( empty( $order_key ) ) {
            wp_die( 'الفاتورة المطلوبة غير صالحة أو غير موجودة.' );
        }

        $order_id = wc_get_order_id_by_order_key( $order_key );
        $order    = wc_get_order( $order_id );

        if ( ! $order ) {
            wp_die( 'تعذر العثور على بيانات الفاتورة. تأكد من صحة الرابط.' );
        }

        $shop_name       = get_bloginfo( 'name' );
        $order_number    = $order->get_order_number();
        $date_created    = wc_format_datetime( $order->get_date_created() );
        $customer_name   = $order->get_formatted_billing_full_name();
        $payment_method  = $order->get_payment_method_title();
        $billing_phone   = $order->get_billing_phone();
        $billing_address = $order->get_formatted_billing_address();
        $currency        = $order->get_currency();
        ?>
        <!DOCTYPE html>
        <html dir="rtl" lang="ar">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>فاتورة الطلب #<?php echo esc_html( $order_number ); ?> - <?php echo esc_html( $shop_name ); ?></title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
            <style>
                :root {
                    --primary: #128C7E;
                    --primary-dark: #075E54;
                    --whatsapp: #25D366;
                    --text-main: #1e293b;
                    --text-muted: #64748b;
                    --bg: #f8fafc;
                    --card: #ffffff;
                    --border: #e2e8f0;
                }
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    font-family: 'Cairo', sans-serif;
                    background: var(--bg);
                    color: var(--text-main);
                    padding: 30px 15px;
                    line-height: 1.6;
                }
                .invoice-card {
                    max-width: 650px;
                    margin: 0 auto;
                    background: var(--card);
                    border-radius: 20px;
                    padding: 36px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.06);
                    border: 1px solid var(--border);
                    position: relative;
                    overflow: hidden;
                }
                .invoice-card::before {
                    content: '';
                    position: absolute;
                    top: 0; right: 0; left: 0;
                    height: 6px;
                    background: linear-gradient(90deg, var(--whatsapp), var(--primary));
                }
                .header-flex {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    border-bottom: 2px dashed var(--border);
                    padding-bottom: 24px;
                    margin-bottom: 24px;
                }
                .brand-title {
                    font-size: 22px;
                    font-weight: 800;
                    color: var(--primary-dark);
                }
                .brand-sub {
                    font-size: 13px;
                    color: var(--text-muted);
                    margin-top: 4px;
                }
                .invoice-badge {
                    background: #f0fdf4;
                    color: #166534;
                    border: 1px solid #bbf7d0;
                    padding: 6px 14px;
                    border-radius: 9999px;
                    font-size: 12px;
                    font-weight: 700;
                }
                .info-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 16px;
                    margin-bottom: 28px;
                    background: #f8fafc;
                    padding: 18px;
                    border-radius: 14px;
                }
                .info-item {
                    font-size: 13px;
                }
                .info-label {
                    color: var(--text-muted);
                    font-size: 12px;
                    margin-bottom: 2px;
                }
                .info-val {
                    font-weight: 700;
                    color: var(--text-main);
                }
                table.invoice-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 24px;
                }
                table.invoice-table th {
                    text-align: right;
                    padding: 12px 14px;
                    background: #f1f5f9;
                    font-size: 13px;
                    font-weight: 700;
                    color: var(--text-muted);
                    border-radius: 8px;
                }
                table.invoice-table td {
                    padding: 14px;
                    border-bottom: 1px solid var(--border);
                    font-size: 14px;
                }
                .item-qty {
                    display: inline-block;
                    background: #e2e8f0;
                    padding: 2px 8px;
                    border-radius: 6px;
                    font-size: 12px;
                    font-weight: 600;
                    margin-right: 6px;
                }
                .totals-box {
                    border-top: 2px solid #0f172a;
                    padding-top: 16px;
                    margin-top: 10px;
                }
                .total-line {
                    display: flex;
                    justify-content: space-between;
                    font-size: 14px;
                    padding: 4px 0;
                    color: var(--text-muted);
                }
                .total-line.final {
                    color: var(--text-main);
                    font-size: 18px;
                    font-weight: 800;
                    margin-top: 8px;
                    padding-top: 8px;
                    border-top: 1px solid var(--border);
                }
                .total-line.final span:last-child {
                    color: var(--primary);
                }
                .footer-box {
                    text-align: center;
                    margin-top: 32px;
                    padding-top: 20px;
                    border-top: 1px solid var(--border);
                    font-size: 13px;
                    color: var(--text-muted);
                }
                .footer-box a {
                    color: var(--primary);
                    text-decoration: none;
                    font-weight: 600;
                }
                .actions-row {
                    display: flex;
                    gap: 12px;
                    margin-top: 24px;
                }
                .btn {
                    flex: 1;
                    padding: 13px;
                    border-radius: 12px;
                    font-weight: 700;
                    font-size: 14px;
                    cursor: pointer;
                    text-align: center;
                    border: none;
                    text-decoration: none;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                    transition: all 0.2s;
                }
                .btn-print {
                    background: #0f172a;
                    color: #fff;
                }
                .btn-print:hover {
                    background: #1e293b;
                }
                .btn-store {
                    background: #f1f5f9;
                    color: var(--text-main);
                }
                .btn-store:hover {
                    background: #e2e8f0;
                }
                @media print {
                    body { background: #fff; padding: 0; }
                    .invoice-card { box-shadow: none; border: none; padding: 0; max-width: 100%; }
                    .actions-row { display: none; }
                }
                @media (max-width: 480px) {
                    .info-grid { grid-template-columns: 1fr; }
                    .invoice-card { padding: 22px; }
                }
            </style>
        </head>
        <body>
            <div class="invoice-card">
                <div class="header-flex">
                    <div>
                        <div class="brand-title"><?php echo esc_html( $shop_name ); ?></div>
                        <div class="brand-sub">فاتورة إلكترونية معتمدة</div>
                    </div>
                    <div>
                        <span class="invoice-badge">مكتمل بنجاح ✓</span>
                    </div>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">رقم الطلب</div>
                        <div class="info-val">#<?php echo esc_html( $order_number ); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">تاريخ الطلب</div>
                        <div class="info-val"><?php echo esc_html( $date_created ); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">العميل</div>
                        <div class="info-val"><?php echo esc_html( $customer_name ?: 'عميل' ); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">طريقة الدفع</div>
                        <div class="info-val"><?php echo esc_html( $payment_method ?: 'دفع إلكتروني' ); ?></div>
                    </div>
                    <?php if ( ! empty( $billing_phone ) ) : ?>
                    <div class="info-item">
                        <div class="info-label">رقم الهاتف</div>
                        <div class="info-val" style="direction:ltr; text-align:right;"><?php echo esc_html( $billing_phone ); ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th>المنتج</th>
                            <th style="text-align: left;">المجموع</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $order->get_items() as $item_id => $item ) : ?>
                            <tr>
                                <td>
                                    <span class="item-qty"><?php echo esc_html( $item->get_quantity() ); ?>×</span>
                                    <strong><?php echo esc_html( $item->get_name() ); ?></strong>
                                </td>
                                <td style="text-align: left; font-weight: 600;">
                                    <?php echo wp_kses_post( wc_price( $item->get_total(), [ 'currency' => $currency ] ) ); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="totals-box">
                    <?php if ( $order->get_total_discount() > 0 ) : ?>
                    <div class="total-line">
                        <span>الخصم</span>
                        <span>-<?php echo wp_kses_post( wc_price( $order->get_total_discount(), [ 'currency' => $currency ] ) ); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ( $order->get_shipping_total() > 0 ) : ?>
                    <div class="total-line">
                        <span>تكلفة الشحن والتوصيل</span>
                        <span><?php echo wp_kses_post( wc_price( $order->get_shipping_total(), [ 'currency' => $currency ] ) ); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ( $order->get_total_tax() > 0 ) : ?>
                    <div class="total-line">
                        <span>ضريبة القيمة المضافة</span>
                        <span><?php echo wp_kses_post( wc_price( $order->get_total_tax(), [ 'currency' => $currency ] ) ); ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="total-line final">
                        <span>الإجمالي الكلي المستحق</span>
                        <span><?php echo wp_kses_post( wc_price( $order->get_total(), [ 'currency' => $currency ] ) ); ?></span>
                    </div>
                </div>

                <div class="footer-box">
                    <p>نشكرك على ثقتك وتسوقك معنا في <strong><?php echo esc_html( $shop_name ); ?></strong></p>
                    <p style="margin-top:4px;"><a href="<?php echo esc_url( site_url() ); ?>"><?php echo esc_html( site_url() ); ?></a></p>
                </div>

                <div class="actions-row">
                    <button class="btn btn-print" onclick="window.print()">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
                        <span>طباعة الفاتورة</span>
                    </button>
                    <a href="<?php echo esc_url( site_url() ); ?>" class="btn btn-store">
                        <span>زيارة المتجر</span>
                    </a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

new MpwaPdfInvoice();
