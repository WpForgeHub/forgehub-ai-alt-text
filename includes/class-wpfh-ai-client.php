<?php
/**
 * WPForgeHub — Universal BYOK AI Client
 *
 * Reusable, zero-dependency client supporting 6 providers:
 * 1. Google Gemini (1.5 Flash, 2.0 Flash)
 * 2. OpenAI (GPT-4o, GPT-4o-mini)
 * 3. DeepSeek (V3, R1)
 * 4. Anthropic Claude (3.5 Sonnet, 3.5 Haiku)
 * 5. OpenRouter (Multi-model Gateway)
 * 6. Custom OpenAI-Compatible Endpoint (Groq, Ollama, vLLM, xAI)
 *
 * @package WPForgeHub\CodeHub
 * @version 1.0.0
 * @author  WPForgeHub <https://wpforgehub.com>
 * @license GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WPFH_AI_Client' ) ) {

    class WPFH_AI_Client {

        /**
         * Supported providers list with metadata.
         */
        public static function get_providers() {
            return [
                'gemini' => [
                    'label'         => 'Google Gemini (Vision)',
                    'default_model' => 'gemini-1.5-flash',
                    'models'        => [
                        'gemini-1.5-flash' => 'Gemini 1.5 Flash (Ultra-Fast & Free Tier)',
                        'gemini-2.0-flash' => 'Gemini 2.0 Flash (Next-Gen Multimodal)',
                        'gemini-1.5-pro'   => 'Gemini 1.5 Pro (Deep Reasoning & High Accuracy)',
                    ],
                    'supports_vision' => true,
                ],
                'openai' => [
                    'label'         => 'OpenAI (Vision)',
                    'default_model' => 'gpt-4o-mini',
                    'models'        => [
                        'gpt-4o-mini' => 'GPT-4o Mini (Fast & Cost-Effective Vision)',
                        'gpt-4o'      => 'GPT-4o (Flagship High-Precision Vision)',
                    ],
                    'supports_vision' => true,
                ],
                'deepseek' => [
                    'label'         => 'DeepSeek (Flash Vision & Pro Reasoning)',
                    'default_model' => 'deepseek-flash',
                    'models'        => [
                        'deepseek-flash'    => 'DeepSeek Flash (Native Multimodal Vision & Ultra Fast)',
                        'deepseek-v4-pro'   => 'DeepSeek-V4 Pro (Heavy Deep Reasoning)',
                        'deepseek-chat'     => 'DeepSeek-V3 / Chat (General Fast Model)',
                        'deepseek-reasoner' => 'DeepSeek-R1 / Reasoner (Chain-of-Thought Reasoning)',
                    ],
                    'supports_vision' => true,
                ],
                'claude' => [
                    'label'         => 'Anthropic Claude (Vision)',
                    'default_model' => 'claude-3-5-haiku-20241022',
                    'models'        => [
                        'claude-3-5-haiku-20241022'  => 'Claude 3.5 Haiku (Fast & Lightweight)',
                        'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet (State-of-the-Art Vision)',
                    ],
                    'supports_vision' => true,
                ],
                'openrouter' => [
                    'label'           => 'OpenRouter (Multi-Provider Gateway)',
                    'default_model'   => 'google/gemini-flash-1.5',
                    'models'          => [
                        'google/gemini-flash-1.5'     => 'Google Gemini 1.5 Flash (via OpenRouter)',
                        'openai/gpt-4o-mini'          => 'OpenAI GPT-4o Mini (via OpenRouter)',
                        'anthropic/claude-3.5-sonnet' => 'Claude 3.5 Sonnet (via OpenRouter)',
                        'deepseek/deepseek-chat'      => 'DeepSeek-V3 (via OpenRouter)',
                    ],
                    'supports_vision' => true,
                ],
                'custom' => [
                    'label'           => 'Custom / Local (Ollama, Groq, vLLM)',
                    'default_model'   => 'llama-3.3-70b-versatile',
                    'models'          => [],
                    'supports_vision' => true,
                ],
            ];
        }

        /**
         * Analyze an image with Vision AI (Alt-Text & Image SEO).
         *
         * @param array $config {
         *     @type string $provider
         *     @type string $api_key
         *     @type string $model
         *     @type string $custom_endpoint
         * }
         * @param string $image_path Local server path to the image or URL.
         * @param string $prompt Instruction prompt for the AI.
         * @param string $lang Target output language (en, tr, de, es, fr, etc.).
         * @return array ['success' => bool, 'data' => string, 'error' => string]
         */
        public static function analyze_image( $config, $image_path, $prompt = '', $lang = 'en' ) {
            $provider        = ! empty( $config['provider'] ) ? sanitize_key( $config['provider'] ) : 'gemini';
            $api_key         = ! empty( $config['api_key'] ) ? trim( $config['api_key'] ) : '';
            $model           = ! empty( $config['model'] ) ? sanitize_text_field( $config['model'] ) : '';
            $custom_endpoint = ! empty( $config['custom_endpoint'] ) ? esc_url_raw( trim( $config['custom_endpoint'] ) ) : '';

            if ( empty( $api_key ) && 'custom' !== $provider ) {
                return [
                    'success' => false,
                    'error'   => __( 'API Key is missing. Please configure your API key in settings.', 'wpfh-ai' ),
                ];
            }

            // Prepare, validate and optimize image (format filter & memory downscaler)
            $prepared = self::prepare_image_base64( $image_path );
            if ( ! $prepared['success'] ) {
                return [
                    'success' => false,
                    'error'   => $prepared['error'],
                ];
            }

            $base64_image = $prepared['base64'];
            $mime_type    = $prepared['mime_type'];

            // Default prompt for Alt-Text if empty
            if ( empty( $prompt ) ) {
                $prompt = sprintf(
                    'Analyze this image and generate a concise, descriptive, WCAG-compliant alt text (maximum 125 characters) and a short title. Output language: %s. Respond ONLY with valid JSON in this format: {"alt_text": "...", "title": "..."}',
                    esc_html( $lang )
                );
            }

            switch ( $provider ) {
                case 'gemini':
                    return self::call_gemini_vision( $api_key, $model, $base64_image, $mime_type, $prompt );

                case 'deepseek':
                    return self::call_deepseek( $api_key, $model, $image_path, $base64_image, $mime_type, $prompt );

                case 'openai':
                    return self::call_openai_vision( $api_key, $model, $base64_image, $mime_type, $prompt );

                case 'claude':
                    return self::call_claude_vision( $api_key, $model, $base64_image, $mime_type, $prompt );

                case 'openrouter':
                    return self::call_openai_compatible_vision( 'https://openrouter.ai/api/v1', $api_key, $model, $base64_image, $mime_type, $prompt );

                case 'custom':
                    return self::call_openai_compatible_vision( $custom_endpoint, $api_key, $model, $base64_image, $mime_type, $prompt );

                default:
                    return [
                        'success' => false,
                        'error'   => __( 'Unsupported AI provider for vision.', 'wpfh-ai' ),
                    ];
            }
        }

        /**
         * Call Google Gemini API (Vision)
         */
        private static function call_gemini_vision( $api_key, $model, $base64_image, $mime_type, $prompt ) {
            if ( empty( $model ) ) {
                $model = 'gemini-1.5-flash';
            }

            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . rawurlencode( $api_key );

            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            [ 'text' => $prompt ],
                            [
                                'inline_data' => [
                                    'mime_type' => $mime_type,
                                    'data'      => $base64_image,
                                ],
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature'     => 0.2,
                    'responseMimeType'=> 'application/json',
                ],
            ];

            $response = wp_remote_post( $url, [
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body'    => wp_json_encode( $payload ),
                'timeout' => 30,
            ]);

            if ( is_wp_error( $response ) ) {
                return [ 'success' => false, 'error' => $response->get_error_message() ];
            }

            $status = wp_remote_retrieve_response_code( $response );
            $body   = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( 200 !== $status ) {
                $err_msg = isset( $body['error']['message'] ) ? $body['error']['message'] : "HTTP $status error from Gemini";
                return [ 'success' => false, 'error' => $err_msg ];
            }

            $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
            return self::parse_json_result( $text );
        }

        /**
         * Call OpenAI API (Vision)
         */
        private static function call_openai_vision( $api_key, $model, $base64_image, $mime_type, $prompt ) {
            return self::call_openai_compatible_vision( 'https://api.openai.com/v1', $api_key, $model ?: 'gpt-4o-mini', $base64_image, $mime_type, $prompt );
        }

        /**
         * Call Anthropic Claude API (Vision)
         */
        private static function call_claude_vision( $api_key, $model, $base64_image, $mime_type, $prompt ) {
            if ( empty( $model ) ) {
                $model = 'claude-3-5-haiku-20241022';
            }

            $url = 'https://api.anthropic.com/v1/messages';

            $payload = [
                'model'      => $model,
                'max_tokens' => 600,
                'messages'   => [
                    [
                        'role'    => 'user',
                        'content' => [
                            [
                                'type'   => 'image',
                                'source' => [
                                    'type'       => 'base64',
                                    'media_type' => $mime_type,
                                    'data'       => $base64_image,
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => $prompt,
                            ],
                        ],
                    ],
                ],
            ];

            $response = wp_remote_post( $url, [
                'headers' => [
                    'x-api-key'         => $api_key,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ],
                'body'    => wp_json_encode( $payload ),
                'timeout' => 30,
            ]);

            if ( is_wp_error( $response ) ) {
                return [ 'success' => false, 'error' => $response->get_error_message() ];
            }

            $status = wp_remote_retrieve_response_code( $response );
            $body   = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( 200 !== $status ) {
                $err_msg = isset( $body['error']['message'] ) ? $body['error']['message'] : "HTTP $status error from Claude";
                return [ 'success' => false, 'error' => $err_msg ];
            }

            $text = $body['content'][0]['text'] ?? '';
            return self::parse_json_result( $text );
        }

        /**
         * Generic OpenAI-compatible Vision Endpoint (OpenAI, OpenRouter, Groq, Ollama)
         */
        private static function call_openai_compatible_vision( $base_url, $api_key, $model, $base64_image, $mime_type, $prompt ) {
            $url = rtrim( $base_url, '/' ) . '/chat/completions';

            $payload = [
                'model'    => $model ?: 'gpt-4o-mini',
                'messages' => [
                    [
                        'role'    => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $prompt,
                            ],
                            [
                                'type'      => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mime_type};base64,{$base64_image}",
                                ],
                            ],
                        ],
                    ],
                ],
                'response_format' => [ 'type' => 'json_object' ],
                'max_tokens'      => 600,
            ];

            $headers = [ 'Content-Type' => 'application/json' ];
            if ( ! empty( $api_key ) ) {
                $headers['Authorization'] = 'Bearer ' . $api_key;
            }

            $response = wp_remote_post( $url, [
                'headers' => $headers,
                'body'    => wp_json_encode( $payload ),
                'timeout' => 30,
            ]);

            if ( is_wp_error( $response ) ) {
                return [ 'success' => false, 'error' => $response->get_error_message() ];
            }

            $status = wp_remote_retrieve_response_code( $response );
            $body   = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( 200 !== $status ) {
                $err_msg = isset( $body['error']['message'] ) ? $body['error']['message'] : "HTTP $status error from AI endpoint";
                return [ 'success' => false, 'error' => $err_msg ];
            }

            $text = $body['choices'][0]['message']['content'] ?? '';
            return self::parse_json_result( $text );
        }

        /**
         * Call DeepSeek API (Vision for deepseek-flash / Text Context for Pro & Chat)
         */
        private static function call_deepseek( $api_key, $model, $image_path, $base64_image, $mime_type, $prompt ) {
            $model = $model ?: 'deepseek-flash';

            // If using deepseek-flash or vision-capable model, send OpenAI-compatible multimodal payload
            if ( false !== stripos( $model, 'flash' ) || false !== stripos( $model, 'vision' ) ) {
                return self::call_openai_compatible_vision( 'https://api.deepseek.com', $api_key, $model, $base64_image, $mime_type, $prompt );
            }

            // For text-only DeepSeek models (deepseek-v4-pro, deepseek-chat, deepseek-reasoner), use filename & context
            $filename   = basename( $image_path );
            $clean_name = trim( preg_replace( '/[_\-\.]+/', ' ', pathinfo( $filename, PATHINFO_FILENAME ) ) );

            $enhanced_prompt = sprintf(
                "%s\nImage context: Filename is \"%s\" (%s). Produce descriptive, WCAG-compliant alt text and image metadata.",
                $prompt,
                $filename,
                $clean_name
            );

            $url = 'https://api.deepseek.com/chat/completions';

            $payload = [
                'model'       => $model,
                'messages'    => [
                    [
                        'role'    => 'system',
                        'content' => 'You are an accessibility and SEO specialist for WordPress. Output only valid JSON with the requested keys (e.g. "alt_text", "title", "caption", "description").',
                    ],
                    [
                        'role'    => 'user',
                        'content' => $enhanced_prompt,
                    ],
                ],
                'response_format' => [ 'type' => 'json_object' ],
                'max_tokens'      => 600,
                'temperature'     => 0.2,
            ];

            $response = wp_remote_post( $url, [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ],
                'body'    => wp_json_encode( $payload ),
                'timeout' => 30,
            ] );

            if ( is_wp_error( $response ) ) {
                return [ 'success' => false, 'error' => $response->get_error_message() ];
            }

            $status = wp_remote_retrieve_response_code( $response );
            $body   = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( 200 !== $status ) {
                $err_msg = isset( $body['error']['message'] ) ? $body['error']['message'] : "HTTP $status error from DeepSeek";
                return [ 'success' => false, 'error' => $err_msg ];
            }

            $text = $body['choices'][0]['message']['content'] ?? '';
            return self::parse_json_result( $text );
        }

        /**
         * Helper: Clean and parse JSON response from AI.
         */
        private static function parse_json_result( $text ) {
            $text = trim( $text );
            // Strip markdown block fences if model wrapped in ```json ... ```
            if ( preg_match( '/```(?:json)?\s*(\{.*?\})\s*```/s', $text, $matches ) ) {
                $text = $matches[1];
            }

            $data = json_decode( $text, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $data ) ) {
                $alt = $data['alt_text'] ?? ( $data['alt'] ?? ( $data['altText'] ?? ( $data['alternative_text'] ?? '' ) ) );
                $title = $data['title'] ?? ( $data['image_title'] ?? ( $data['name'] ?? '' ) );
                $caption = $data['caption'] ?? ( $data['image_caption'] ?? '' );
                $description = $data['description'] ?? ( $data['desc'] ?? ( $data['image_description'] ?? '' ) );

                // Fallback: If alt is empty but title exists, use title as alt
                if ( empty( $alt ) && ! empty( $title ) ) {
                    $alt = $title;
                }

                return [
                    'success' => true,
                    'data'    => [
                        'alt_text'    => sanitize_text_field( $alt ),
                        'caption'     => sanitize_text_field( $caption ),
                        'title'       => sanitize_text_field( $title ),
                        'description' => sanitize_textarea_field( $description ),
                    ],
                ];
            }

            // Fallback plain text if JSON failed
            return [
                'success' => true,
                'data'    => [
                    'alt_text'    => sanitize_text_field( wp_strip_all_tags( $text ) ),
                    'caption'     => '',
                    'title'       => '',
                    'description' => '',
                ],
            ];
        }

        /**
         * Prepare, validate and downscale image for Vision AI.
         * Ensures maximum 1200px dimensions to avoid memory crashes and API payload limits.
         */
        public static function prepare_image_base64( $image_path ) {
            if ( ! file_exists( $image_path ) ) {
                return [
                    'success' => false,
                    'error'   => __( 'Image file could not be found on server.', 'wpfh-ai-alt-text' ),
                ];
            }

            $file_info = wp_check_filetype( $image_path );
            $mime_type = $file_info['type'];
            $allowed_mimes = [ 'image/jpeg', 'image/png', 'image/webp' ];

            if ( ! in_array( $mime_type, $allowed_mimes, true ) ) {
                return [
                    'success' => false,
                    'error'   => sprintf( __( 'Unsupported image format (%s). Vision AI supports JPG, PNG, and WebP only.', 'wpfh-ai-alt-text' ), $mime_type ?: 'unknown' ),
                ];
            }

            $filesize = filesize( $image_path );
            $max_dim  = 1200;

            // If file is under 1.5MB and small dimensions, encode directly
            if ( $filesize < 1572864 ) {
                $size = @getimagesize( $image_path );
                if ( $size && $size[0] <= $max_dim && $size[1] <= $max_dim ) {
                    $data = file_get_contents( $image_path );
                    if ( false !== $data ) {
                        return [
                            'success'   => true,
                            'mime_type' => $mime_type,
                            'base64'    => base64_encode( $data ),
                        ];
                    }
                }
            }

            // Downscale high-resolution images using WordPress Image Editor
            $editor = wp_get_image_editor( $image_path );
            if ( ! is_wp_error( $editor ) ) {
                $editor->resize( $max_dim, $max_dim, false );
                $editor->set_quality( 82 );
                $upload_dir = wp_upload_dir();
                $temp_path  = trailingslashit( $upload_dir['basedir'] ) . 'wpfh_opt_' . uniqid() . '.jpg';
                $saved      = $editor->save( $temp_path, 'image/jpeg' );

                if ( ! is_wp_error( $saved ) && file_exists( $temp_path ) ) {
                    $data = file_get_contents( $temp_path );
                    @unlink( $temp_path );
                    if ( false !== $data ) {
                        return [
                            'success'   => true,
                            'mime_type' => 'image/jpeg',
                            'base64'    => base64_encode( $data ),
                        ];
                    }
                }
            }

            // Safe fallback
            $data = file_get_contents( $image_path );
            if ( false === $data ) {
                return [
                    'success' => false,
                    'error'   => __( 'Unable to read image data.', 'wpfh-ai-alt-text' ),
                ];
            }

            return [
                'success'   => true,
                'mime_type' => $mime_type,
                'base64'    => base64_encode( $data ),
            ];
        }
    }
}
