<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MpwaGeminiHandler {
    
    private $api_key;
    private $system_prompt;

    public function __construct() {
        $this->api_key       = get_option( 'mpedia_wagatewaygemini_api_key', '' );
        $this->system_prompt = get_option( 'mpedia_wagatewaygemini_system_prompt', "أنت مندوب مبيعات محترف ومساعد ذكي لمتجرنا.\nمهمتك مساعدة العملاء بلطف، والرد على استفساراتهم، والبحث عن المنتجات التي يطلبونها وتقديم روابطها وأسعارها.\nتحدث دائماً بلهجة ودودة ومرحبة باللغة العربية." );
    }

    public function is_configured() {
        return ! empty( $this->api_key );
    }

    public function process_message( $phone, $user_message, $audio_data = null ) {
        if ( ! $this->is_configured() ) return false;

        $history = $this->get_history( $phone );
        
        if ( ! empty( $audio_data ) ) {
            $base64 = '';
            if ( preg_match( '/^data:audio/i', $audio_data ) || ! filter_var( $audio_data, FILTER_VALIDATE_URL ) ) {
                $base64 = $audio_data;
            } else {
                $host = wp_parse_url( $audio_data, PHP_URL_HOST );
                $allowed_host = (string) apply_filters( 'mpwa_allowed_audio_host', '' );
                if ( ! $host || ( $allowed_host && ! hash_equals( strtolower( $allowed_host ), strtolower( $host ) ) ) ) {
                    return false;
                }
                $audio_raw = wp_safe_remote_get( $audio_data, [
                    'timeout'             => 12,
                    'redirection'         => 0,
                    'limit_response_size' => 5 * MB_IN_BYTES,
                ] );
                if ( ! is_wp_error( $audio_raw ) && wp_remote_retrieve_response_code( $audio_raw ) === 200 ) {
                    $content_type = wp_remote_retrieve_header( $audio_raw, 'content-type' );
                    if ( strpos( strtolower( $content_type ), 'audio/' ) === 0 ) {
                        $base64 = base64_encode( wp_remote_retrieve_body( $audio_raw ) );
                    }
                }
            }

            if ( strlen( $base64 ) > 7 * MB_IN_BYTES ) return false;

            if ( ! empty( $base64 ) ) {
                if ( strpos( $base64, ',' ) !== false ) {
                    $base64 = explode( ',', $base64 )[1];
                }
                $history[] = [
                    'role'  => 'user',
                    'parts' => [
                        [
                            'inlineData' => [
                                'mimeType' => 'audio/mp3',
                                'data'     => $base64
                            ]
                        ]
                    ]
                ];
            } else {
                $history[] = [ 'role' => 'user', 'parts' => [ [ 'text' => '(أرسل رسالة صوتية لم نتمكن من معالجتها)' ] ] ];
            }
        } else {
            $history[] = [ 'role' => 'user', 'parts' => [ [ 'text' => (string) $user_message ] ] ];
        }
        
        $response = $this->call_gemini( $history );
        if ( ! $response ) return 'عذراً، لم أتمكن من الرد في الوقت الحالي. يرجى المحاولة لاحقاً.';
        
        // If an error string was returned directly
        if ( is_string( $response ) ) {
            return $response;
        }

        $reply = '';
        
        // Handle function calling in Gemini
        if ( isset( $response['functionCall'] ) ) {
            $func_call = $response['functionCall'];
            $func_name = $func_call['name'];
            $args      = $func_call['args'] ?? [];
            
            $history[] = [
                'role'  => 'model',
                'parts' => [ [ 'functionCall' => $func_call ] ]
            ];

            $func_result = '';
            if ( $func_name === 'search_products' ) {
                $func_result = $this->search_products( $phone, $args['query'] ?? '' );
            }
            
            // Add function response to history
            $history[] = [
                'role'  => 'function',
                'parts' => [
                    [
                        'functionResponse' => [
                            'name'     => $func_name,
                            'response' => [ 'result' => $func_result ]
                        ]
                    ]
                ]
            ];
            
            // Call Gemini again with function result
            $second_response = $this->call_gemini( $history );
            if ( is_string( $second_response ) ) {
                $reply = $second_response;
            } elseif ( is_array( $second_response ) && isset( $second_response['text'] ) ) {
                $reply = $second_response['text'];
                $history[] = [ 'role' => 'model', 'parts' => [ [ 'text' => $reply ] ] ];
            }
        } elseif ( isset( $response['text'] ) ) {
            $reply = $response['text'];
            $history[] = [ 'role' => 'model', 'parts' => [ [ 'text' => $reply ] ] ];
        }

        if ( ! empty( $reply ) ) {
            $this->save_history( $phone, $history );
        }

        return $reply ?: 'أهلاً بك! كيف يمكنني مساعدتك اليوم؟';
    }

    private function call_gemini( $messages ) {
        $tools = [
            [
                'function_declarations' => [
                    [
                        'name'        => 'search_products',
                        'description' => 'ابحث في المتجر عن المنتجات المتاحة باستخدام كلمة مفتاحية',
                        'parameters'  => [
                            'type'       => 'OBJECT',
                            'properties' => [
                                'query' => [
                                    'type'        => 'STRING',
                                    'description' => 'الكلمة المفتاحية للبحث (مثال: عطر، قميص، شوز)'
                                ]
                            ],
                            'required'   => ['query']
                        ]
                    ]
                ]
            ]
        ];

        $payload = [
            'system_instruction' => [
                'parts' => [ [ 'text' => $this->system_prompt ] ]
            ],
            'contents'           => $messages,
            'tools'              => $tools
        ];

        // Use stable gemini-1.5-flash model
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $this->api_key;

        $response = wp_remote_post( $url, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 30
        ] );

        if ( is_wp_error( $response ) ) {
            return 'عذراً، حدث خطأ في الاتصال بمزود الذكاء الاصطناعي: ' . $response->get_error_message();
        }

        $code     = wp_remote_retrieve_response_code( $response );
        $raw_body = wp_remote_retrieve_body( $response );
        $body     = json_decode( $raw_body, true );
        
        if ( $code < 200 || $code >= 300 || isset( $body['error'] ) ) {
            $error_msg = $body['error']['message'] ?? ( 'رمز الاستجابة: ' . $code );
            return 'تنبيه: واجه البوت مشكلة من خادم جوجل (' . $error_msg . ')';
        }

        if ( isset( $body['candidates'][0]['content']['parts'][0] ) ) {
            return $body['candidates'][0]['content']['parts'][0];
        }

        return 'عذراً، لم أتمكن من فهم الرد بالشكل المطلوب.';
    }

    private function get_history( $phone ) {
        $history = get_transient( 'mpwa_gemini_hist_' . $phone );
        return is_array( $history ) ? $history : [];
    }

    private function save_history( $phone, $history ) {
        if ( count( $history ) > 10 ) {
            $history = array_slice( $history, -10 );
        }
        set_transient( 'mpwa_gemini_hist_' . $phone, $history, HOUR_IN_SECONDS );
    }

    private function search_products( $phone, $query ) {
        if ( ! function_exists( 'wc_get_products' ) ) {
            return 'خدمة المنتجات غير متاحة حالياً.';
        }

        $args  = [ 'status' => 'publish', 'limit' => 3 ];
        $query = trim( (string) $query );
        if ( ! empty( $query ) && $query !== 'الكل' && strtolower( $query ) !== 'all' ) {
            $args['s'] = $query;
        }
        
        $products      = wc_get_products( $args );
        $fallback_used = false;

        if ( empty( $products ) ) {
            $products      = wc_get_products( [ 'status' => 'publish', 'limit' => 3 ] );
            $fallback_used = true;
        }

        if ( empty( $products ) ) return 'المتجر لا يحتوي على أي منتجات متوفرة حالياً.';

        $trigger = new SendTrigger();
        if ( $fallback_used ) {
            $result = "لم أجد منتجات تطابق مباشرة '{$query}'، ولكن إليك أحدث المنتجات المتوفرة لدينا:\n";
        } else {
            $result = "تم العثور على المنتجات التالية وإرسال كروتها للعميل:\n";
        }

        foreach ( $products as $product ) {
            $product_url = $product->get_permalink();
            $price_html  = strip_tags( wc_price( $product->get_price(), [ 'currency' => $product->get_currency() ] ) );
            $caption     = "🛒 *{$product->get_name()}*\n💰 السعر: {$price_html}\n🔗 الرابط: {$product_url}\n(لطلب المنتج، رقم المعرف هو: {$product->get_id()})";
            
            if ( $phone !== 'test_bot_000' && class_exists( 'SendTrigger' ) ) {
                $image_id  = $product->get_image_id();
                $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';
                if ( $image_url ) {
                    $trigger->send_media( $phone, 'image', $image_url, $caption );
                } else {
                    $trigger->send_direct( $phone, $caption );
                }
            }
            
            $result .= "- رقم: {$product->get_id()} | {$product->get_name()} | السعر: {$price_html}\n";
        }

        $result .= 'أخبر العميل أنك أرسلت له صور وتفاصيل المنتجات للتو، واطلب منه اختيار ما يناسبه لمساعدته.';
        return $result;
    }
}
