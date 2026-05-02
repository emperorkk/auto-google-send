<?php
/**
 * Plugin Name: Yoast SEO Assistant (AI Tags)
 * Description: SEO helpers for Yoast free users: AI tags, optional Yoast meta title/description suggestions, and dashboard workflows.
 * Version: 0.3.0
 * Author: kourentzes.com
 * Requires at least: 6.9
 * Requires PHP: 8.2
 */

if (! defined('ABSPATH')) {
    exit;
}


define('KSEO_PLUGIN_VERSION', '0.3.0');
define('KSEO_PLUGIN_FILE', __FILE__);
define('KSEO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KSEO_PLUGIN_URL', plugin_dir_url(__FILE__));

class KSEO_Yoast_Assistant
{
    private const OPTION_GROUP = 'kseo_ai_tags_settings_group';
    private const OPTION_NAME = 'kseo_ai_tags_settings';
    private const NONCE_ACTION = 'kseo_generate_tags_nonce';
    private const NONCE_BULK_ACTION = 'kseo_generate_tags_bulk_nonce';

    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_dashboard_setup', [$this, 'register_dashboard_widget']);
        add_action('admin_post_kseo_generate_tags', [$this, 'handle_generate_tags']);
        add_action('admin_post_kseo_generate_tags_bulk', [$this, 'handle_bulk_generate_tags']);
        add_action('post_submitbox_misc_actions', [$this, 'render_post_editor_button']);
        add_action('admin_notices', [$this, 'render_admin_notices']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function enqueue_admin_assets(): void
    {
        wp_enqueue_style('kseo-admin', KSEO_PLUGIN_URL . 'assets/css/admin.css', [], KSEO_PLUGIN_VERSION);
        wp_enqueue_script('kseo-admin', KSEO_PLUGIN_URL . 'assets/js/admin.js', [], KSEO_PLUGIN_VERSION, true);
    }

    public function render_admin_notices(): void
    {
        if (isset($_GET['kseo_ai_success'])) {
            $count = absint($_GET['kseo_ai_success']);
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf('AI tags added successfully (%d tags).', $count)) . '</p></div>';
        }

        if (isset($_GET['kseo_ai_bulk_success'])) {
            $count = absint($_GET['kseo_ai_bulk_success']);
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf('Bulk AI completed for %d posts.', $count)) . '</p></div>';
        }

        if (isset($_GET['kseo_ai_error'])) {
            $message = sanitize_text_field(wp_unslash($_GET['kseo_ai_error']));
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }

    public function register_settings_page(): void
    {
        add_options_page('KSEO AI Tags Settings', 'KSEO AI Tags', 'manage_options', 'kseo-ai-tags', [$this, 'render_settings_page']);
    }

    public function register_settings(): void
    {
        register_setting(self::OPTION_GROUP, self::OPTION_NAME, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'default' => [
                'api_key' => '',
                'model' => 'gpt-4.1-mini',
                'enable_meta' => 1,
            ],
        ]);

        add_settings_section('kseo_ai_tags_main', 'OpenAI Configuration', function () {
            echo '<p>Set your OpenAI credentials and model for tag/meta generation.</p>';
        }, 'kseo-ai-tags');

        add_settings_field('api_key', 'OpenAI API Key', [$this, 'render_api_key_field'], 'kseo-ai-tags', 'kseo_ai_tags_main');
        add_settings_field('model', 'Model', [$this, 'render_model_field'], 'kseo-ai-tags', 'kseo_ai_tags_main');
        add_settings_field('enable_meta', 'Suggest Yoast Meta', [$this, 'render_enable_meta_field'], 'kseo-ai-tags', 'kseo_ai_tags_main');
    }

    public function sanitize_settings(array $input): array
    {
        return [
            'api_key' => sanitize_text_field($input['api_key'] ?? ''),
            'model' => sanitize_text_field($input['model'] ?? 'gpt-4.1-mini'),
            'enable_meta' => ! empty($input['enable_meta']) ? 1 : 0,
        ];
    }

    public function render_api_key_field(): void
    {
        $o = get_option(self::OPTION_NAME, []);
        echo '<input type="password" name="' . esc_attr(self::OPTION_NAME) . '[api_key]" value="' . esc_attr($o['api_key'] ?? '') . '" class="regular-text" autocomplete="off" />';
    }

    public function render_model_field(): void
    {
        $o = get_option(self::OPTION_NAME, []);
        echo '<input type="text" name="' . esc_attr(self::OPTION_NAME) . '[model]" value="' . esc_attr($o['model'] ?? 'gpt-4.1-mini') . '" class="regular-text" placeholder="gpt-4.1-mini" />';
    }

    public function render_enable_meta_field(): void
    {
        $o = get_option(self::OPTION_NAME, []);
        $checked = ! empty($o['enable_meta']) ? 'checked' : '';
        echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION_NAME) . '[enable_meta]" value="1" ' . $checked . ' /> Also write Yoast meta title/description</label>';
    }

    public function render_settings_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        echo '<div class="wrap"><h1>KSEO AI Tags</h1><form method="post" action="options.php">';
        settings_fields(self::OPTION_GROUP);
        do_settings_sections('kseo-ai-tags');
        submit_button('Save Settings');
        echo '</form></div>';
    }

    public function register_dashboard_widget(): void
    {
        wp_add_dashboard_widget('kseo_ai_tags_widget', 'Posts Missing Tags (AI Helper)', [$this, 'render_dashboard_widget']);
    }

    public function render_dashboard_widget(): void
    {
        $posts = $this->get_recent_untagged_posts();
        if (empty($posts)) {
            echo '<p>Great! No recent published posts without tags.</p>';
            return;
        }

        $bulk = wp_nonce_url(admin_url('admin-post.php?action=kseo_generate_tags_bulk'), self::NONCE_BULK_ACTION);
        echo '<p><a class="button button-primary kseo-confirm-bulk" href="' . esc_url($bulk) . '">Auto-complete all listed posts</a></p>';

        echo '<ul>';
        foreach ($posts as $post) {
            $action_url = wp_nonce_url(admin_url('admin-post.php?action=kseo_generate_tags&post_id=' . (int) $post->ID), self::NONCE_ACTION . '_' . (int) $post->ID);
            echo '<li style="margin-bottom:10px;"><strong>' . esc_html($post->post_title) . '</strong><br /><a class="button button-secondary" href="' . esc_url($action_url) . '">Auto-complete tags (+ meta)</a></li>';
        }
        echo '</ul>';
    }

    private function get_recent_untagged_posts(): array
    {
        return get_posts([
            'post_type' => 'post',
            'posts_per_page' => 10,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
            'tax_query' => [[
                'taxonomy' => 'post_tag',
                'operator' => 'NOT EXISTS',
            ]],
        ]);
    }

    public function render_post_editor_button(): void
    {
        global $post;
        if (! $post || 'post' !== $post->post_type || ! current_user_can('edit_post', $post->ID)) {
            return;
        }
        $action_url = wp_nonce_url(admin_url('admin-post.php?action=kseo_generate_tags&post_id=' . (int) $post->ID), self::NONCE_ACTION . '_' . (int) $post->ID);
        echo '<div class="misc-pub-section"><a class="button" href="' . esc_url($action_url) . '">Generate tags + meta with AI</a></div>';
    }

    public function handle_bulk_generate_tags(): void
    {
        check_admin_referer(self::NONCE_BULK_ACTION);
        if (! current_user_can('edit_posts')) {
            wp_die('Unauthorized request.');
        }

        $count = 0;
        foreach ($this->get_recent_untagged_posts() as $post) {
            $result = $this->apply_ai_to_post($post->ID);
            if (! is_wp_error($result)) {
                $count++;
            }
        }

        $redirect = add_query_arg('kseo_ai_bulk_success', $count, admin_url());
        wp_safe_redirect($redirect);
        exit;
    }

    public function handle_generate_tags(): void
    {
        if (! current_user_can('edit_posts')) {
            wp_die('Unauthorized request.');
        }

        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        check_admin_referer(self::NONCE_ACTION . '_' . $post_id);

        $result = $this->apply_ai_to_post($post_id);
        if (is_wp_error($result)) {
            wp_safe_redirect(add_query_arg('kseo_ai_error', rawurlencode($result->get_error_message()), wp_get_referer() ?: admin_url()));
            exit;
        }

        wp_safe_redirect(add_query_arg('kseo_ai_success', (int) $result, wp_get_referer() ?: admin_url()));
        exit;
    }

    private function apply_ai_to_post(int $post_id)
    {
        $post = get_post($post_id);
        if (! $post || 'post' !== $post->post_type) {
            return new WP_Error('invalid_post', 'Invalid post.');
        }

        $data = $this->generate_seo_data($post);
        if (is_wp_error($data)) {
            return $data;
        }

        wp_set_post_terms($post_id, $data['tags'], 'post_tag', true);

        $settings = get_option(self::OPTION_NAME, []);
        if (! empty($settings['enable_meta'])) {
            if (! empty($data['meta_title'])) {
                update_post_meta($post_id, '_yoast_wpseo_title', sanitize_text_field($data['meta_title']));
            }
            if (! empty($data['meta_description'])) {
                update_post_meta($post_id, '_yoast_wpseo_metadesc', sanitize_textarea_field($data['meta_description']));
            }
        }

        return count($data['tags']);
    }

    private function generate_seo_data(WP_Post $post)
    {
        $settings = get_option(self::OPTION_NAME, []);
        $api_key = $settings['api_key'] ?? '';
        $model = $settings['model'] ?? 'gpt-4.1-mini';
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', 'OpenAI API key is missing. Set it in Settings → KSEO AI Tags.');
        }

        $content = mb_substr(wp_strip_all_tags($post->post_title . "

" . $post->post_excerpt . "

" . $post->post_content), 0, 6000);
        $prompt = 'Return JSON object with keys: tags(array of 5-10 strings), meta_title(string <= 60 chars), meta_description(string <= 155 chars). No markdown, no extra text.';

        $payload = [
            'model' => $model,
            'input' => [
                ['role' => 'system', 'content' => [['type' => 'input_text', 'text' => $prompt]]],
                ['role' => 'user', 'content' => [['type' => 'input_text', 'text' => $content]]],
            ],
            'max_output_tokens' => 280,
        ];

        $response = wp_remote_post('https://api.openai.com/v1/responses', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ]);
        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $raw = $body['output'][0]['content'][0]['text'] ?? '';
        $decoded = json_decode(trim((string) $raw), true);
        if (! is_array($decoded)) {
            return new WP_Error('parse_error', 'Could not parse AI response JSON.');
        }

        $tags = array_slice(array_values(array_unique(array_filter(array_map('sanitize_text_field', (array) ($decoded['tags'] ?? []))))), 0, 10);
        if (empty($tags)) {
            return new WP_Error('no_tags', 'No valid tags generated.');
        }

        return [
            'tags' => $tags,
            'meta_title' => sanitize_text_field((string) ($decoded['meta_title'] ?? '')),
            'meta_description' => sanitize_textarea_field((string) ($decoded['meta_description'] ?? '')),
        ];
    }
}

new KSEO_Yoast_Assistant();
