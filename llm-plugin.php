<?php
/*
Plugin Name: LLM Posts Plugin
Description: Generate WordPress posts using an LLM via native PHP. Includes settings for API key/model and an admin UI to create posts.
Version: 0.1.0
Author: Muhamad Dendi Purwanto
Text Domain: llm-posts
*/

if (!defined('ABSPATH')) {
    exit;
}

// Option keys
const LLMWP_OPT_API_KEY     = 'llmwp_api_key';
const LLMWP_OPT_MODEL       = 'llmwp_model';
const LLMWP_OPT_TEMP        = 'llmwp_temperature';
const LLMWP_OPT_MAXTOKENS   = 'llmwp_max_tokens';
const LLMWP_OPT_POST_STATUS = 'llmwp_post_status';
const LLMWP_OPT_API_BASE    = 'llmwp_api_base';
const LLMWP_OPT_REFERER     = 'llmwp_http_referer';
const LLMWP_OPT_XTITLE      = 'llmwp_x_title';
const LLMWP_OPT_IMG_ENABLE  = 'llmwp_img_enable';
const LLMWP_OPT_IMG_PROVIDER= 'llmwp_img_provider';
const LLMWP_OPT_PIXABAY_KEY = 'llmwp_pixabay_key';
const LLMWP_OPT_IMG_MAX     = 'llmwp_img_max';

// Defaults
function llmwp_default_options() {
    return [
        LLMWP_OPT_API_KEY     => '',
        // Default to OpenRouter free-capable model and base
        LLMWP_OPT_MODEL       => 'x-ai/grok-4-fast:free',
        LLMWP_OPT_TEMP        => 0.7,
        LLMWP_OPT_MAXTOKENS   => 1200,
        LLMWP_OPT_POST_STATUS => 'draft',
        LLMWP_OPT_API_BASE    => 'https://openrouter.ai/api/v1',
        LLMWP_OPT_REFERER     => '',
        LLMWP_OPT_XTITLE      => 'LLM Posts (WordPress)',
        LLMWP_OPT_IMG_ENABLE  => 1,
        LLMWP_OPT_IMG_PROVIDER=> 'unsplash', // unsplash | pixabay
        LLMWP_OPT_PIXABAY_KEY => '',
        LLMWP_OPT_IMG_MAX     => 3,
    ];
}

// Activation hook: seed defaults
function llmwp_activate() {
    foreach (llmwp_default_options() as $k => $v) {
        if (get_option($k, null) === null) {
            // For referer default to site home if available
            if ($k === LLMWP_OPT_REFERER) {
                $v = home_url('/');
            }
            add_option($k, $v);
        }
    }
}
register_activation_hook(__FILE__, 'llmwp_activate');

// Deactivation hook (keep options by default)
function llmwp_deactivate() {
    // Intentionally keep options so updates persist across activations.
}
register_deactivation_hook(__FILE__, 'llmwp_deactivate');

// Admin menu
function llmwp_admin_menu() {
    if (!current_user_can('manage_options')) return;

    add_menu_page(
        'LLM Posts',
        'LLM Posts',
        'manage_options',
        'llmwp',
        'llmwp_render_generate_page',
        'dashicons-edit-page',
        58
    );

    add_submenu_page(
        'llmwp',
        'Generate Post',
        'Generate',
        'manage_options',
        'llmwp',
        'llmwp_render_generate_page'
    );

    add_submenu_page(
        'llmwp',
        'Settings',
        'Settings',
        'manage_options',
        'llmwp-settings',
        'llmwp_render_settings_page'
    );

    add_submenu_page(
        'llmwp',
        'Chat Edit',
        'Chat Edit',
        'manage_options',
        'llmwp-chat',
        'llmwp_render_chat_page'
    );

    add_submenu_page(
        'llmwp',
        'Bulk Generate',
        'Bulk Generate',
        'manage_options',
        'llmwp-bulk',
        'llmwp_render_bulk_page'
    );
}
add_action('admin_menu', 'llmwp_admin_menu');

// Settings page
function llmwp_render_settings_page() {
    if (!current_user_can('manage_options')) return;

    $notice = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_admin_referer('llmwp_settings');

        $model       = sanitize_text_field($_POST['llmwp_model'] ?? 'meta-llama/llama-3.1-8b-instruct:free');
        $api_key     = trim((string)($_POST['llmwp_api_key'] ?? ''));
        $temperature = isset($_POST['llmwp_temperature']) ? floatval($_POST['llmwp_temperature']) : 0.7;
        $max_tokens  = isset($_POST['llmwp_max_tokens']) ? intval($_POST['llmwp_max_tokens']) : 1200;
        $post_status = sanitize_text_field($_POST['llmwp_post_status'] ?? 'draft');
        $api_base    = trim((string)($_POST['llmwp_api_base'] ?? 'https://openrouter.ai/api/v1'));
        $referer     = trim((string)($_POST['llmwp_http_referer'] ?? ''));
        $xtitle      = trim((string)($_POST['llmwp_x_title'] ?? ''));
        $img_enable  = isset($_POST['llmwp_img_enable']) ? 1 : 0;
        $img_provider= sanitize_text_field($_POST['llmwp_img_provider'] ?? 'unsplash');
        $pixabay_key = trim((string)($_POST['llmwp_pixabay_key'] ?? ''));
        $img_max     = isset($_POST['llmwp_img_max']) ? max(0, min(10, intval($_POST['llmwp_img_max']))) : 3;

        update_option(LLMWP_OPT_MODEL, $model);
        update_option(LLMWP_OPT_API_KEY, $api_key);
        update_option(LLMWP_OPT_TEMP, max(0.0, min(2.0, $temperature)));
        update_option(LLMWP_OPT_MAXTOKENS, max(1, min(4000, $max_tokens)));
        update_option(LLMWP_OPT_POST_STATUS, in_array($post_status, ['draft','publish','private'], true) ? $post_status : 'draft');
        // Basic validation for API base
        if (!preg_match('#^https?://#i', $api_base)) {
            $api_base = 'https://openrouter.ai/api/v1';
        }
        update_option(LLMWP_OPT_API_BASE, untrailingslashit($api_base));
        update_option(LLMWP_OPT_REFERER, $referer);
        update_option(LLMWP_OPT_XTITLE, $xtitle);
        update_option(LLMWP_OPT_IMG_ENABLE, $img_enable);
        update_option(LLMWP_OPT_IMG_PROVIDER, in_array($img_provider, ['unsplash','pixabay'], true) ? $img_provider : 'unsplash');
        update_option(LLMWP_OPT_PIXABAY_KEY, $pixabay_key);
        update_option(LLMWP_OPT_IMG_MAX, $img_max);

        $notice = '<div class="updated"><p>Settings saved.</p></div>';
    }

    $opts = [
        'model'       => get_option(LLMWP_OPT_MODEL, 'meta-llama/llama-3.1-8b-instruct:free'),
        'api_key'     => get_option(LLMWP_OPT_API_KEY, ''),
        'temperature' => (float)get_option(LLMWP_OPT_TEMP, 0.7),
        'max_tokens'  => (int)get_option(LLMWP_OPT_MAXTOKENS, 1200),
        'post_status' => get_option(LLMWP_OPT_POST_STATUS, 'draft'),
        'api_base'    => get_option(LLMWP_OPT_API_BASE, 'https://openrouter.ai/api/v1'),
        'referer'     => get_option(LLMWP_OPT_REFERER, home_url('/')),
        'xtitle'      => get_option(LLMWP_OPT_XTITLE, 'LLM Posts (WordPress)'),
        'img_enable'  => (int) get_option(LLMWP_OPT_IMG_ENABLE, 1),
        'img_provider'=> get_option(LLMWP_OPT_IMG_PROVIDER, 'unsplash'),
        'pixabay_key' => get_option(LLMWP_OPT_PIXABAY_KEY, ''),
        'img_max'     => (int) get_option(LLMWP_OPT_IMG_MAX, 3),
    ];

    echo '<div class="wrap">';
    echo '<h1>LLM Posts Settings</h1>';
    echo $notice;
    echo '<form method="post">';
    wp_nonce_field('llmwp_settings');
    echo '<table class="form-table" role="presentation">';
    echo '<tr><th scope="row"><label for="llmwp_api_key">API Key</label></th><td>';
    echo '<input type="password" id="llmwp_api_key" name="llmwp_api_key" value="' . esc_attr($opts['api_key']) . '" class="regular-text" placeholder="sk-..." />';
    echo '<p class="description">OpenAI-compatible API key (stored in options table).</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="llmwp_model">Model</label></th><td>';
    echo '<input type="text" id="llmwp_model" name="llmwp_model" value="' . esc_attr($opts['model']) . '" class="regular-text" placeholder="gpt-4o-mini" />';
    echo '<p class="description">OpenRouter example free models: meta-llama/llama-3.1-8b-instruct:free, google/gemma-2-9b-it:free</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="llmwp_temperature">Temperature</label></th><td>';
    echo '<input type="number" step="0.1" min="0" max="2" id="llmwp_temperature" name="llmwp_temperature" value="' . esc_attr($opts['temperature']) . '" />';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="llmwp_max_tokens">Max Tokens</label></th><td>';
    echo '<input type="number" min="1" max="4000" id="llmwp_max_tokens" name="llmwp_max_tokens" value="' . esc_attr($opts['max_tokens']) . '" />';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="llmwp_post_status">Post Status</label></th><td>';
    echo '<select id="llmwp_post_status" name="llmwp_post_status">';
    foreach ([
        'draft'   => 'Draft',
        'publish' => 'Publish',
        'private' => 'Private',
    ] as $val => $label) {
        $sel = selected($opts['post_status'], $val, false);
        echo '<option value="' . esc_attr($val) . '" ' . $sel . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="llmwp_api_base">API Base URL</label></th><td>';
    echo '<input type="url" id="llmwp_api_base" name="llmwp_api_base" value="' . esc_attr($opts['api_base']) . '" class="regular-text code" placeholder="https://openrouter.ai/api/v1" />';
    echo '<p class="description">For OpenRouter use https://openrouter.ai/api/v1. For OpenAI use https://api.openai.com/v1.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="llmwp_http_referer">HTTP-Referer (OpenRouter)</label></th><td>';
    echo '<input type="text" id="llmwp_http_referer" name="llmwp_http_referer" value="' . esc_attr($opts['referer']) . '" class="regular-text" placeholder="' . esc_attr(home_url('/')) . '" />';
    echo '<p class="description">Recommended by OpenRouter: your site/app URL.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="llmwp_x_title">X-Title (OpenRouter)</label></th><td>';
    echo '<input type="text" id="llmwp_x_title" name="llmwp_x_title" value="' . esc_attr($opts['xtitle']) . '" class="regular-text" placeholder="LLM Posts (WordPress)" />';
    echo '<p class="description">Optional: your app name shown in OpenRouter logs.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="llmwp_img_enable">Auto Images</label></th><td>';
    echo '<label><input type="checkbox" id="llmwp_img_enable" name="llmwp_img_enable" value="1" ' . checked($opts['img_enable'], 1, false) . ' /> Insert relevant images automatically</label>';
    echo '<p class="description">The LLM will add placeholders like [llmwp-image alt="..."] and the plugin will fetch images and attach them to the post.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="llmwp_img_provider">Image Provider</label></th><td>';
    echo '<select id="llmwp_img_provider" name="llmwp_img_provider">';
    foreach ([ 'unsplash' => 'Unsplash (Source) – no key', 'pixabay' => 'Pixabay – requires API key' ] as $val => $label) {
        $sel = selected($opts['img_provider'], $val, false);
        echo '<option value="' . esc_attr($val) . '" ' . $sel . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">Choose the provider used to fetch images for placeholders.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="llmwp_pixabay_key">Pixabay API Key</label></th><td>';
    echo '<input type="text" id="llmwp_pixabay_key" name="llmwp_pixabay_key" value="' . esc_attr($opts['pixabay_key']) . '" class="regular-text" placeholder="your-pixabay-key" />';
    echo '<p class="description">Get a free key at pixabay.com. Required when provider = Pixabay.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="llmwp_img_max">Max Images Per Post</label></th><td>';
    echo '<input type="number" min="0" max="10" id="llmwp_img_max" name="llmwp_img_max" value="' . esc_attr($opts['img_max']) . '" />';
    echo '</td></tr>';

    echo '</table>';

    submit_button('Save Settings');
    echo '</form>';
    echo '</div>';
}

// Generate page
function llmwp_render_generate_page() {
    if (!current_user_can('manage_options')) return;

    $has_key = (bool) get_option(LLMWP_OPT_API_KEY, '');
    $notice = '';
    $result_html = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['llmwp_generate'])) {
        check_admin_referer('llmwp_generate');

        $title   = sanitize_text_field($_POST['llmwp_title'] ?? '');
        $topic   = sanitize_text_field($_POST['llmwp_topic'] ?? '');
        $lang    = sanitize_text_field($_POST['llmwp_lang'] ?? 'en');
        $outline = sanitize_textarea_field($_POST['llmwp_outline'] ?? '');

        if (!$has_key) {
            $notice = '<div class="error"><p>Please set your API key in Settings.</p></div>';
        } elseif (empty($topic) && empty($title)) {
            $notice = '<div class="error"><p>Provide at least a Title or a Topic/Keywords.</p></div>';
        } else {
            $prompt = llmwp_build_prompt($title, $topic, $lang, $outline);
            $content = llmwp_generate_content($prompt);

            if (is_wp_error($content)) {
                $notice = '<div class="error"><p>Generation failed: ' . esc_html($content->get_error_message()) . '</p></div>';
            } else {
                $final_title = $title ?: llmwp_extract_title_from_content($content) ?: 'LLM Generated Post';
                // First create a post (empty content) to obtain an ID for attachments
                $post_id = wp_insert_post([
                    'post_title'   => $final_title,
                    'post_content' => '',
                    'post_status'  => get_option(LLMWP_OPT_POST_STATUS, 'draft'),
                ]);

                if (is_wp_error($post_id)) {
                    $notice = '<div class="error"><p>Could not create post: ' . esc_html($post_id->get_error_message()) . '</p></div>';
                } else {
                    // Replace image placeholders and sanitize
                    $content_with_images = llmwp_replace_image_placeholders($post_id, $content);
                    $safe_content = wp_kses_post($content_with_images);

                    // Update the post with final content
                    $update_res = wp_update_post([
                        'ID' => $post_id,
                        'post_content' => $safe_content,
                    ], true);
                    if (is_wp_error($update_res)) {
                        $notice = '<div class="error"><p>Post created but content update failed: ' . esc_html($update_res->get_error_message()) . '</p></div>';
                    } else {
                        $edit_link = get_edit_post_link($post_id, '');
                        $notice = '<div class="updated"><p>Post created. <a href="' . esc_url($edit_link) . '">Edit Post</a></p></div>';
                        $result_html = '<h3>Generated Preview</h3><div style="background:#fff;border:1px solid #ccd0d4;padding:16px;max-width:800px">' . $safe_content . '</div>';
                    }
                }
            }
        }
    }

    echo '<div class="wrap">';
    echo '<h1>Generate Post with LLM</h1>';
    echo $notice;
    if (!$has_key) {
        echo '<div class="notice notice-warning"><p>No API key configured. Please set one in Settings.</p></div>';
    }

    echo '<form method="post">';
    wp_nonce_field('llmwp_generate');
    echo '<table class="form-table" role="presentation">';
    echo '<tr><th scope="row"><label for="llmwp_title">Title (optional)</label></th><td>';
    echo '<input type="text" id="llmwp_title" name="llmwp_title" class="regular-text" placeholder="e.g., Mastering Local SEO in 2025" />';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="llmwp_topic">Topic / Keywords</label></th><td>';
    echo '<input type="text" id="llmwp_topic" name="llmwp_topic" class="regular-text" placeholder="e.g., local SEO, Google Business Profile" />';
    echo '<p class="description">Short description or keywords guiding the article.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="llmwp_lang">Language</label></th><td>';
    echo '<select id="llmwp_lang" name="llmwp_lang">';
    foreach ([ 'en' => 'English', 'id' => 'Indonesian' ] as $code => $label) {
        echo '<option value="' . esc_attr($code) . '">' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="llmwp_outline">Outline (optional)</label></th><td>';
    echo '<textarea id="llmwp_outline" name="llmwp_outline" rows="6" class="large-text" placeholder="H1, H2 sections or bullet points..."></textarea>';
    echo '</td></tr>';
    echo '</table>';

    echo '<p class="submit">';
    echo '<input type="submit" name="llmwp_generate" id="llmwp_generate" class="button button-primary" value="Generate & Create Post" />';
    echo '</p>';
    echo '</form>';

    echo $result_html;
    echo '</div>';
}

// Chat Edit page (edit selection or insert near anchor)
function llmwp_render_chat_page() {
    if (!current_user_can('manage_options')) return;

    $notice = '';
    $result_html = '';

    $selected_post_id = isset($_REQUEST['post_id']) ? intval($_REQUEST['post_id']) : 0;
    $posts = get_posts([
        'post_type'   => 'post',
        'post_status' => ['draft', 'pending'],
        'numberposts' => 50,
        'orderby'     => 'date',
        'order'       => 'DESC',
    ]);

    $current_content = '';
    if ($selected_post_id) {
        $p = get_post($selected_post_id);
        if ($p && in_array($p->post_status, ['draft','pending'], true)) {
            $current_content = (string) $p->post_content;
        } else {
            $selected_post_id = 0;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['llmwp_chat_apply'])) {
        check_admin_referer('llmwp_chat');
        $selected_post_id = intval($_POST['post_id'] ?? 0);
        $mode   = sanitize_text_field($_POST['llmwp_mode'] ?? 'selection');
        $instr  = trim((string) ($_POST['llmwp_message'] ?? ''));
        $p      = $selected_post_id ? get_post($selected_post_id) : null;

        if (!$p) {
            $notice = '<div class="error"><p>Invalid post.</p></div>';
        } elseif (!in_array($p->post_status, ['draft','pending'], true)) {
            $notice = '<div class="error"><p>Only draft/pending posts are supported.</p></div>';
        } elseif ($instr === '') {
            $notice = '<div class="error"><p>Please enter your instruction.</p></div>';
        } else {
            $content_before = (string) $p->post_content;
            if ($mode === 'selection') {
                $selection = (string) ($_POST['llmwp_selection'] ?? '');
                if ($selection === '' || strpos($content_before, $selection) === false) {
                    $notice = '<div class="error"><p>Selection not found in the post content. Ensure it matches exactly.</p></div>';
                } else {
                    $prompt = "You are editing a fragment of a WordPress post. Rewrite the provided HTML fragment based on the instruction.\n" .
                              "- Keep HTML valid and coherent with the surrounding context.\n" .
                              "- Output ONLY the revised HTML fragment without any extra commentary or code fences.\n\n" .
                              "Instruction:\n$instr\n\nFragment to revise:\n$selection";
                    $fragment = llmwp_generate_content($prompt);
                    if (is_wp_error($fragment)) {
                        $notice = '<div class="error"><p>Generation failed: ' . esc_html($fragment->get_error_message()) . '</p></div>';
                    } else {
                        $fragment = llmwp_strip_code_fences($fragment);
                        // Replace image placeholders inside the fragment before sanitizing
                        $fragment = llmwp_replace_image_placeholders($selected_post_id, $fragment);
                        $safe_fragment = wp_kses_post($fragment);
                        $pos = strpos($content_before, $selection);
                        if ($pos === false) {
                            $notice = '<div class="error"><p>Unexpected: selection vanished before apply.</p></div>';
                        } else {
                            $new_content = substr($content_before, 0, $pos) . $safe_fragment . substr($content_before, $pos + strlen($selection));
                            $res = wp_update_post([
                                'ID' => $selected_post_id,
                                'post_content' => $new_content,
                            ], true);
                            if (is_wp_error($res)) {
                                $notice = '<div class="error"><p>Could not update post: ' . esc_html($res->get_error_message()) . '</p></div>';
                            } else {
                                $notice = '<div class="updated"><p>Selection updated successfully.</p></div>';
                                $result_html = '<h3>Revised Fragment</h3><div style="background:#fff;border:1px solid #ccd0d4;padding:12px;max-width:800px">' . $safe_fragment . '</div>';
                                $current_content = $new_content;
                            }
                        }
                    }
                }
            } elseif ($mode === 'insert') {
                $anchor  = (string) ($_POST['llmwp_anchor'] ?? '');
                $where   = sanitize_text_field($_POST['llmwp_where'] ?? 'after');
                $prompt = "Generate an HTML fragment to insert into a WordPress post based on the instruction.\n" .
                          "- Output ONLY the HTML fragment, with no commentary or code fences.\n" .
                          "- Keep it consistent with typical blog structure (h2/h3, p, lists as needed).\n\n" .
                          "Instruction:\n$instr";
                $fragment = llmwp_generate_content($prompt);
                if (is_wp_error($fragment)) {
                    $notice = '<div class="error"><p>Generation failed: ' . esc_html($fragment->get_error_message()) . '</p></div>';
                } else {
                    $fragment = llmwp_strip_code_fences($fragment);
                    // Replace image placeholders inside the fragment before sanitizing
                    $fragment = llmwp_replace_image_placeholders($selected_post_id, $fragment);
                    $safe_fragment = wp_kses_post($fragment);
                    $pos = ($anchor !== '') ? strpos($content_before, $anchor) : false;
                    if ($pos === false) {
                        // Append at end if anchor not found
                        $new_content = rtrim($content_before) . "\n\n" . $safe_fragment . "\n";
                    } else {
                        if ($where === 'before') {
                            $new_content = substr($content_before, 0, $pos) . $safe_fragment . substr($content_before, $pos);
                        } else { // after
                            $new_content = substr($content_before, 0, $pos + strlen($anchor)) . $safe_fragment . substr($content_before, $pos + strlen($anchor));
                        }
                    }
                    $res = wp_update_post([
                        'ID' => $selected_post_id,
                        'post_content' => $new_content,
                    ], true);
                    if (is_wp_error($res)) {
                        $notice = '<div class="error"><p>Could not update post: ' . esc_html($res->get_error_message()) . '</p></div>';
                    } else {
                        $notice = '<div class="updated"><p>Content inserted successfully.</p></div>';
                        $result_html = '<h3>Inserted Fragment</h3><div style="background:#fff;border:1px solid #ccd0d4;padding:12px;max-width:800px">' . $safe_fragment . '</div>';
                        $current_content = $new_content;
                    }
                }
            }
        }
    }

    echo '<div class="wrap">';
    echo '<h1>Chat Edit Draft Post</h1>';
    echo $notice;

    echo '<form method="get" style="margin-bottom:16px">';
    echo '<input type="hidden" name="page" value="llmwp-chat" />';
    echo '<label for="llmwp_post_select">Choose a draft/pending post:</label> ';
    echo '<select id="llmwp_post_select" name="post_id">';
    echo '<option value="0">-- Select Post --</option>';
    foreach ($posts as $post) {
        $sel = selected($selected_post_id, $post->ID, false);
        $label = esc_html(get_the_title($post->ID) ?: ('(no title) #' . $post->ID));
        echo '<option value="' . intval($post->ID) . '" ' . $sel . '>' . $label . '</option>';
    }
    echo '</select> ';
    submit_button('Load', 'secondary', '', false);
    echo '</form>';

    if ($selected_post_id) {
        echo '<h2>Current Content (preview)</h2>';
        echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:12px;max-width:900px;overflow:auto;max-height:360px">' . wp_kses_post($current_content) . '</div>';

        echo '<form method="post" style="margin-top:16px">';
        wp_nonce_field('llmwp_chat');
        echo '<input type="hidden" name="post_id" value="' . intval($selected_post_id) . '" />';

        echo '<h2>Edit via Chat</h2>';
        echo '<p><label><input type="radio" name="llmwp_mode" value="selection" checked> Edit Selection</label> &nbsp; ';
        echo '<label><input type="radio" name="llmwp_mode" value="insert"> Insert Near Anchor</label></p>';

        echo '<div id="llmwp_mode_selection" style="padding:8px;border:1px solid #e2e4e7;margin-bottom:12px">';
        echo '<p><label for="llmwp_selection">Selection (exact text to replace)</label><br />';
        echo '<textarea id="llmwp_selection" name="llmwp_selection" rows="4" class="large-text" placeholder="Paste the exact fragment from the content you want to rewrite"></textarea></p>';
        echo '</div>';

        echo '<div id="llmwp_mode_insert" style="padding:8px;border:1px solid #e2e4e7;margin-bottom:12px">';
        echo '<p><label for="llmwp_anchor">Anchor text (optional)</label><br />';
        echo '<input type="text" id="llmwp_anchor" name="llmwp_anchor" class="regular-text" placeholder="A heading or sentence to locate" /></p>';
        echo '<p><label for="llmwp_where">Insert position</label><br />';
        echo '<select id="llmwp_where" name="llmwp_where"><option value="after">After anchor</option><option value="before">Before anchor</option></select></p>';
        echo '</div>';

        echo '<p><label for="llmwp_message">Instruction/Message</label><br />';
        echo '<textarea id="llmwp_message" name="llmwp_message" rows="5" class="large-text" placeholder="Contoh: Perbaiki grammar pada selection agar lebih formal, atau Tambahkan subbagian tentang strategi backlink dengan 2-3 paragraf dan satu daftar poin."></textarea></p>';

        echo '<p class="submit">';
        echo '<input type="submit" name="llmwp_chat_apply" class="button button-primary" value="Send & Apply" />';
        echo '</p>';
        echo '</form>';

        echo $result_html;
    }

    echo '</div>';
}

// Bulk Generate page
function llmwp_render_bulk_page() {
    if (!current_user_can('manage_options')) return;

    $has_key = (bool) get_option(LLMWP_OPT_API_KEY, '');
    $notice = '';
    $result_html = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['llmwp_bulk_generate'])) {
        check_admin_referer('llmwp_bulk_generate');

        $raw_lines = (string) ($_POST['llmwp_bulk_keywords'] ?? '');
        $lang      = sanitize_text_field($_POST['llmwp_lang'] ?? 'en');
        $outline   = sanitize_textarea_field($_POST['llmwp_outline'] ?? '');

        if (!$has_key) {
            $notice = '<div class="error"><p>Please set your API key in Settings.</p></div>';
        } else {
            $lines = array_filter(array_map('trim', preg_split('/\r?\n/', $raw_lines)));
            $total = count($lines);
            if ($total === 0) {
                $notice = '<div class="error"><p>Please enter at least one keyword/topic (one per line).</p></div>';
            } else {
                $max = 20; // safety cap for a single request
                if ($total > $max) {
                    $lines = array_slice($lines, 0, $max);
                    $notice = '<div class="notice notice-warning"><p>Limiting to ' . esc_html((string)$max) . ' items per batch for stability.</p></div>';
                }

                $items_html = '';
                foreach ($lines as $idx => $topic) {
                    $prompt  = llmwp_build_prompt('', $topic, $lang, $outline);
                    $content = llmwp_generate_content($prompt);
                    if (is_wp_error($content)) {
                        $items_html .= '<li><strong>' . esc_html($topic) . ':</strong> Failed - ' . esc_html($content->get_error_message()) . '</li>';
                        continue;
                    }

                    $final_title = llmwp_extract_title_from_content($content);
                    if ($final_title === '') {
                        $final_title = 'LLM Generated: ' . wp_strip_all_tags($topic);
                    }

                    $post_id = wp_insert_post([
                        'post_title'   => $final_title,
                        'post_content' => '',
                        'post_status'  => get_option(LLMWP_OPT_POST_STATUS, 'draft'),
                    ]);
                    if (is_wp_error($post_id)) {
                        $items_html .= '<li><strong>' . esc_html($topic) . ':</strong> Could not create post - ' . esc_html($post_id->get_error_message()) . '</li>';
                        continue;
                    }

                    $content_with_images = llmwp_replace_image_placeholders($post_id, $content);
                    $safe_content = wp_kses_post($content_with_images);
                    $update_res = wp_update_post([
                        'ID' => $post_id,
                        'post_content' => $safe_content,
                    ], true);
                    if (is_wp_error($update_res)) {
                        $items_html .= '<li><strong>' . esc_html($topic) . ':</strong> Post created but update failed - ' . esc_html($update_res->get_error_message()) . '</li>';
                    } else {
                        $edit_link = get_edit_post_link($post_id, '');
                        $items_html .= '<li><strong>' . esc_html($topic) . ':</strong> Created → <a href="' . esc_url($edit_link) . '">Edit Post</a></li>';
                    }
                }

                $result_html  = '<h3>Bulk Results</h3>';
                $result_html .= '<ol>' . $items_html . '</ol>';
            }
        }
    }

    echo '<div class="wrap">';
    echo '<h1>Bulk Generate Posts</h1>';
    echo $notice;
    if (!$has_key) {
        echo '<div class="notice notice-warning"><p>No API key configured. Please set one in Settings.</p></div>';
    }

    echo '<form method="post">';
    wp_nonce_field('llmwp_bulk_generate');
    echo '<table class="form-table" role="presentation">';
    echo '<tr><th scope="row"><label for="llmwp_bulk_keywords">Keywords / Topics</label></th><td>';
    echo '<textarea id="llmwp_bulk_keywords" name="llmwp_bulk_keywords" rows="10" class="large-text" placeholder="Satu topik per baris"></textarea>';
    echo '<p class="description">Setiap baris akan menghasilkan satu post. Maksimal 20 per batch.</p>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="llmwp_lang">Language</label></th><td>';
    echo '<select id="llmwp_lang" name="llmwp_lang">';
    foreach ([ 'en' => 'English', 'id' => 'Indonesian' ] as $code => $label) {
        echo '<option value="' . esc_attr($code) . '">' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '</td></tr>';

    echo '<tr><th scope="row"><label for="llmwp_outline">Outline (optional, applied to all)</label></th><td>';
    echo '<textarea id="llmwp_outline" name="llmwp_outline" rows="6" class="large-text" placeholder="Gunakan poin-poin atau struktur H2/H3 yang diterapkan untuk semua topik"></textarea>';
    echo '</td></tr>';
    echo '</table>';

    echo '<p class="submit">';
    echo '<input type="submit" name="llmwp_bulk_generate" id="llmwp_bulk_generate" class="button button-primary" value="Generate Bulk" />';
    echo '</p>';
    echo '</form>';

    echo $result_html;
    echo '</div>';
}

function llmwp_strip_code_fences($text) {
    $text = trim($text);
    // remove ``` blocks
    if (preg_match('/^```[a-zA-Z0-9\-]*\n([\s\S]*)\n```$/', $text, $m)) {
        return trim($m[1]);
    }
    // remove possible single backtick wrap
    if (substr($text, 0, 3) === '```') {
        $text = preg_replace('/^```[a-zA-Z0-9\-]*\n?/', '', $text);
        $text = preg_replace('/\n?```$/', '', $text);
        return trim($text);
    }
    return $text;
}

// Build prompt for the LLM
function llmwp_build_prompt($title, $topic, $lang, $outline) {
    $language = ($lang === 'id') ? 'Bahasa Indonesia' : 'English';
    $parts = [];
    if ($title) $parts[] = "Title: $title";
    if ($topic) $parts[] = "Topic/Keywords: $topic";
    if ($outline) $parts[] = "Outline: \n$outline";
    $ctx = implode("\n", $parts);

    $instructions = "Write a comprehensive, SEO-friendly blog post in $language using HTML (h2/h3, paragraphs, lists).\n" .
        "- Include an engaging introduction and conclusion.\n" .
        "- Use subheadings (h2 for sections, h3 for subsections).\n" .
        "- Do not use <h1>.\n" .
        "- Avoid inline CSS and external links unless relevant.\n" .
        "- Do not include <html>, <head>, or <body> wrappers.\n";

    // Hint the model to add image placeholders that we will convert into real images.
    if ((int) get_option(LLMWP_OPT_IMG_ENABLE, 1) === 1) {
        $instructions .= "- Where helpful, add up to 3 image placeholders using the format [llmwp-image alt=\"descriptive alt text in $language\"]. Place them after relevant sections (e.g., after an h2 or a paragraph). Do not embed <img> tags yourself.\n";
    }

    return "$instructions\nContext:\n$ctx";
}

// Replace [llmwp-image ...] placeholders with real, attached images
function llmwp_replace_image_placeholders($post_id, $html) {
    if ((int) get_option(LLMWP_OPT_IMG_ENABLE, 1) !== 1) {
        return $html;
    }

    if (!is_string($html) || $html === '') return $html;

    // Ensure media functions are available
    if (!function_exists('media_sideload_image')) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $limit_opt = (int) get_option(LLMWP_OPT_IMG_MAX, 3);
    $limit = max(0, min(10, $limit_opt));
    $count = 0;
    $first_attachment_id = 0;

    // Match [llmwp-image ...] with optional attributes
    $pattern = '/\[llmwp-image([^\]]*)\]/i';
    if (!preg_match_all($pattern, $html, $matches, PREG_OFFSET_CAPTURE)) {
        return $html;
    }

    // We will build replacements progressively; track offset shifts
    $offsetShift = 0;
    foreach ($matches[0] as $idx => $fullMatch) {
        if ($count >= $limit) break;

        $rawTag   = $fullMatch[0];
        $rawPos   = $fullMatch[1];
        $attrText = isset($matches[1][$idx][0]) ? trim($matches[1][$idx][0]) : '';

        // Parse attributes alt="..." and query="..." (alt used as fallback for query)
        $alt   = '';
        $query = '';
        if ($attrText !== '') {
            if (preg_match('/alt\s*=\s*"([^"]+)"/i', $attrText, $m)) {
                $alt = trim($m[1]);
            }
            if (preg_match('/query\s*=\s*"([^"]+)"/i', $attrText, $m)) {
                $query = trim($m[1]);
            }
        }
        if ($query === '') $query = $alt;
        if ($alt === '') $alt = $query ?: 'Illustration';

        // If still empty, skip replacement
        if ($alt === '') continue;

        // Resolve an image URL from configured provider
        $url = llmwp_get_image_url_for_query($query !== '' ? $query : $alt);
        if (!$url) {
            // No URL resolved: skip this placeholder
            $start = $rawPos + $offsetShift;
            $len   = strlen($rawTag);
            $html  = substr($html, 0, $start) . '' . substr($html, $start + $len);
            $offsetShift -= $len; // removed tag
            continue;
        }

        // Try to sideload and attach
        $attachment_id = 0;
        $img_html = '';
        $result = media_sideload_image($url, $post_id, $alt, 'id');
        if (!is_wp_error($result)) {
            $attachment_id = (int) $result;
            if ($attachment_id > 0) {
                update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
                $img_tag = wp_get_attachment_image($attachment_id, 'large', false, ['alt' => $alt]);
                if ($img_tag) {
                    $caption = esc_html($alt);
                    $img_html = '<figure class="wp-block-image">' . $img_tag . '<figcaption class="wp-element-caption">' . $caption . '</figcaption></figure>';
                }
                if ($first_attachment_id === 0) {
                    $first_attachment_id = $attachment_id;
                }
            }
        }
        // If sideload failed, skip replacement (remove placeholder)
        if ($img_html === '') {
            $start = $rawPos + $offsetShift;
            $len   = strlen($rawTag);
            $html  = substr($html, 0, $start) . '' . substr($html, $start + $len);
            $offsetShift -= $len; // removed tag
            continue;
        }

        // Replace in the original HTML considering previous replacements
        $start = $rawPos + $offsetShift;
        $len   = strlen($rawTag);
        $html  = substr($html, 0, $start) . $img_html . substr($html, $start + $len);
        $offsetShift += strlen($img_html) - $len;
        $count++;
    }

    // Set featured image to the first attachment if not set and we are on a standard post
    if ($first_attachment_id > 0 && function_exists('set_post_thumbnail')) {
        $post = get_post($post_id);
        if ($post && empty(get_post_thumbnail_id($post_id))) {
            set_post_thumbnail($post_id, $first_attachment_id);
        }
    }

    return $html;
}

// Resolve image URL based on provider settings
function llmwp_get_image_url_for_query($query) {
    $provider = get_option(LLMWP_OPT_IMG_PROVIDER, 'unsplash');
    $query = trim((string) $query);
    if ($query === '') return '';

    if ($provider === 'pixabay') {
        $key = trim((string) get_option(LLMWP_OPT_PIXABAY_KEY, ''));
        if ($key === '') return '';

        $endpoint = 'https://pixabay.com/api/';
        $args = [
            'key'          => $key,
            'q'            => $query,
            'image_type'   => 'photo',
            'orientation'  => 'horizontal',
            'safesearch'   => 'true',
            'per_page'     => 5,
            'order'        => 'popular',
        ];
        $url = add_query_arg($args, $endpoint);
        $resp = wp_remote_get($url, [ 'timeout' => 20 ]);
        if (is_wp_error($resp)) return '';
        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) return '';
        $body = wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['hits']) || !is_array($data['hits'])) return '';
        $hit = $data['hits'][0];
        if (!empty($hit['largeImageURL'])) return $hit['largeImageURL'];
        if (!empty($hit['webformatURL'])) return $hit['webformatURL'];
        return '';
    }

    // default unsplash source (no key)
    return 'https://source.unsplash.com/1024x768/?' . rawurlencode($query);
}

// Call OpenAI-compatible Chat Completions via WP HTTP API
function llmwp_generate_content($prompt) {
    $api_key    = trim((string) get_option(LLMWP_OPT_API_KEY, ''));
    $model      = get_option(LLMWP_OPT_MODEL, 'meta-llama/llama-3.1-8b-instruct:free');
    $temperature= (float) get_option(LLMWP_OPT_TEMP, 0.7);
    $max_tokens = (int) get_option(LLMWP_OPT_MAXTOKENS, 1200);
    $api_base   = untrailingslashit((string) get_option(LLMWP_OPT_API_BASE, 'https://openrouter.ai/api/v1'));
    $referer    = trim((string) get_option(LLMWP_OPT_REFERER, ''));
    $xtitle     = trim((string) get_option(LLMWP_OPT_XTITLE, ''));

    if (!$api_key) {
        return new WP_Error('missing_key', 'API key is not configured.');
    }

    $endpoint = $api_base . '/chat/completions';
    $payload = [
        'model'    => $model,
        'messages' => [
            [ 'role' => 'system', 'content' => 'You are a helpful assistant that writes high-quality, SEO-friendly blog posts in HTML.' ],
            [ 'role' => 'user',   'content' => $prompt ],
        ],
        'temperature' => $temperature,
        'max_tokens'  => $max_tokens,
    ];

    $headers = [
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type'  => 'application/json',
    ];
    // Add OpenRouter-specific headers when using OpenRouter
    if (stripos($api_base, 'openrouter.ai') !== false) {
        if ($referer !== '') {
            $headers['HTTP-Referer'] = $referer;
        }
        if ($xtitle !== '') {
            $headers['X-Title'] = $xtitle;
        }
    }

    $args = [
        'headers' => $headers,
        'timeout' => 60,
        'body'    => wp_json_encode($payload),
    ];

    $response = wp_remote_post($endpoint, $args);
    if (is_wp_error($response)) {
        return $response;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($code < 200 || $code >= 300) {
        $msg = isset($data['error']['message']) ? $data['error']['message'] : ('HTTP ' . $code);
        return new WP_Error('http_error', 'API error: ' . $msg);
    }

    if (!isset($data['choices'][0]['message']['content'])) {
        return new WP_Error('bad_response', 'Unexpected API response.');
    }

    return $data['choices'][0]['message']['content'];
}

// Try to extract a <h1> or first line as title
function llmwp_extract_title_from_content($content) {
    if (preg_match('/<h1[^>]*>(.*?)<\/h1>/i', $content, $m)) {
        return wp_strip_all_tags($m[1]);
    }
    $lines = preg_split('/\r?\n/', wp_strip_all_tags($content));
    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim !== '') return mb_substr($trim, 0, 120);
    }
    return '';
}
