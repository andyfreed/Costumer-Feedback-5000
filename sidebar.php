<?php
/*
Plugin Name: Customer Feedback 5000
Description: Adds a sidebar with a customizable form that hovers on the right side of every page, records submissions, and resets form data on page navigation.
Version: 2.3
Author: Your boy
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class HoverSidebarFormPlugin {
    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('wp_footer', [$this, 'render_sidebar']);
        add_action('wp_ajax_submit_form', [$this, 'handle_form_submission']);
        add_action('wp_ajax_nopriv_submit_form', [$this, 'handle_form_submission']);
        add_action('wp_ajax_clear_submissions', [$this, 'clear_submissions']);
        add_action('wp_ajax_add_to_dropdown', [$this, 'add_to_dropdown']);
        add_action('admin_post_download_submissions_csv', [$this, 'download_submissions_csv']);

        // Generate CSS and JS files dynamically
        $this->generate_assets();
    }

    private function generate_assets() {
        // CSS
        file_put_contents(plugin_dir_path(__FILE__) . 'sidebar-style.css', "#hover-sidebar {position: fixed;top: 0;right: 0;width: 300px;height: 100%;background: #f9f9f9;padding: 10px;box-shadow: -2px 0 5px rgba(0, 0, 0, 0.1);transition: transform 0.3s ease;transform: translateX(100%);z-index: 1000;}
#hover-sidebar.visible {transform: translateX(0);}
#hover-sidebar #toggle-sidebar {position: absolute;left: -40px;top: 50%;transform: translateY(-50%);background: #0073aa;color: #fff;width: 40px;height: 40px;display: flex;align-items: center;justify-content: center;cursor: pointer;border-radius: 5px 0 0 5px;box-shadow: 0 0 5px rgba(0, 0, 0, 0.3);}
#hover-sidebar-content {overflow-y: auto;padding: 10px;height: calc(100% - 20px);}
label {display: block;margin-bottom: 10px;}input {width: calc(100% - 10px);padding: 5px;margin-top: 5px;}button#clear-submissions {margin: 10px 0;}");

        // JS
        file_put_contents(plugin_dir_path(__FILE__) . 'sidebar-script.js', "jQuery(document).ready(function($) {
            $('#toggle-sidebar').on('click', function() {
                $('#hover-sidebar').toggleClass('visible');
                const icon = $(this).text() === '«' ? '»' : '«';
                $(this).text(icon);
            });
            $('#hover-sidebar-form').on('submit', function(e) {
                e.preventDefault();
                const data = $(this).serializeArray();
                data.push({name: 'action', value: 'submit_form'});
                data.push({name: 'page', value: window.location.href});
                $.post(hoverSidebarAjax.ajax_url, data, function(response) {
                    alert(response.data);
                    $('#hover-sidebar-form')[0].reset();
                });
            });
            $('#clear-submissions').on('click', function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to clear all submissions?')) {
                    $.post(hoverSidebarAjax.ajax_url, {action: 'clear_submissions'}, function(response) {
                        if (response.success) {
                            alert('All submissions have been cleared.');
                            location.reload();
                        } else {
                            alert('Failed to clear submissions: ' + response.data);
                        }
                    });
                }
            });
            $('#add-to-dropdown').on('click', function() {
                const context = $(this).data('context');
                $.post(hoverSidebarAjax.ajax_url, {action: 'add_to_dropdown', context: context}, function(response) {
                    alert(response.data);
                });
            });
        });");
    }

    public function enqueue_scripts() {
        wp_enqueue_style('hover-sidebar-style', plugin_dir_url(__FILE__) . 'sidebar-style.css');
        wp_enqueue_script('hover-sidebar-script', plugin_dir_url(__FILE__) . 'sidebar-script.js', ['jquery'], null, true);
        wp_localize_script('hover-sidebar-script', 'hoverSidebarAjax', [
            'ajax_url' => admin_url('admin-ajax.php')
        ]);
    }

    public function admin_menu() {
        add_menu_page(
            'Hover Sidebar Form',
            'Hover Sidebar Form',
            'manage_options',
            'hover-sidebar-form',
            [$this, 'admin_page'],
            'dashicons-forms'
        );
    }

    public function admin_page() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['questions'])) {
            $questions = sanitize_textarea_field($_POST['questions']);
            update_option('hover_sidebar_questions', $questions);
            update_option('hover_sidebar_question_pages', $_POST['question_pages'] ?? []);
            update_option('hover_sidebar_question_post_ids', $_POST['question_post_ids'] ?? []);
        }

        $questions = get_option('hover_sidebar_questions', "Question 1\nQuestion 2\nQuestion 3\nQuestion 4\nQuestion 5");
        $question_pages = get_option('hover_sidebar_question_pages', []);
        $question_post_ids = get_option('hover_sidebar_question_post_ids', []);
        $submissions = get_option('hover_sidebar_submissions', []);

        echo '<div class="wrap">
            <h1>Hover Sidebar Form</h1>
            <form method="post">
                <h2>Form Questions</h2>
                <textarea name="questions" rows="5" style="width:100%;">' . esc_textarea($questions) . '</textarea>
                <h2>Assign Questions to Pages, Post Types, Templates, or Post IDs</h2>';

        $questionsArray = explode("\n", $questions);
        foreach ($questionsArray as $index => $question) {
            echo '<label>' . esc_html($question) . ':
                <select name="question_pages[' . $index . ']" style="width:100%;">
                    <option value="">All Pages</option>';

            // Include Pages
            $pages = get_pages();
            foreach ($pages as $page) {
                $selected = isset($question_pages[$index]) && $question_pages[$index] == $page->post_name ? 'selected' : '';
                echo '<option value="' . esc_attr($page->post_name) . '" ' . $selected . '>' . esc_html($page->post_title) . '</option>';
            }

            // Include Custom Post Types
            $post_types = get_post_types(['public' => true], 'objects');
            foreach ($post_types as $post_type) {
                $selected = isset($question_pages[$index]) && $question_pages[$index] == $post_type->name ? 'selected' : '';
                echo '<option value="' . esc_attr($post_type->name) . '" ' . $selected . '>' . esc_html($post_type->label) . '</option>';
            }

            // Include Page Templates
            $templates = wp_get_theme()->get_page_templates();
            foreach ($templates as $template_name => $template_filename) {
                $selected = isset($question_pages[$index]) && $question_pages[$index] == $template_filename ? 'selected' : '';
                echo '<option value="' . esc_attr($template_filename) . '" ' . $selected . '>' . esc_html($template_name) . ' (Template)</option>';
            }

            echo '</select>
                <label>Assign by Post ID (comma-separated, e.g., "2,15,23"):<br>
                    <input type="text" name="question_post_ids[' . $index . ']" style="width:100%;" value="' . esc_attr($question_post_ids[$index] ?? '') . '" />
                </label>
            </label>';
        }

        echo '<button type="submit" class="button button-primary">Save Questions</button>
            </form>
            <h2>Form Submissions</h2>';

        if ($submissions) {
            echo '<form id="clear-submissions-form">
                <button type="button" id="clear-submissions" class="button button-secondary">Clear All Submissions</button>
            </form>
            <a href="' . esc_url(admin_url('admin-post.php?action=download_submissions_csv')) . '" class="button button-primary">Download Submissions as CSV</a>
            <table class="widefat"><thead><tr><th>User</th><th>Answers</th><th>Page</th><th>Time</th></tr></thead><tbody>';
            foreach ($submissions as $submission) {
                echo '<tr>
                    <td>' . esc_html($submission['user']) . '</td>
                    <td>';
                foreach ($submission['answers'] as $index => $answer) {
                    echo '<strong>' . esc_html($submission['questions'][$index] ?? "Question $index") . ':</strong> ' . esc_html($answer) . '<br>';
                }
                echo '</td>
                    <td>' . esc_html($submission['page']) . '</td>
                    <td>' . esc_html($submission['time']) . '</td>
                </tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>No submissions yet.</p>';
        }

        echo '</div>';
    }

    public function render_sidebar() {
        $questions = explode("\n", get_option('hover_sidebar_questions', "Question 1\nQuestion 2\nQuestion 3\nQuestion 4\nQuestion 5"));
        $question_pages = get_option('hover_sidebar_question_pages', []);
        $question_post_ids = get_option('hover_sidebar_question_post_ids', []);
        $current_post_type = get_post_type();
        $current_template = get_page_template_slug();
        $current_post_id = is_singular() ? get_the_ID() : '';
        $current_url = $_SERVER['REQUEST_URI'];

        $context = $current_post_id ?: ($current_template ?: $current_post_type);

        echo '<div id="hover-sidebar">
            <div id="toggle-sidebar">&raquo;</div>
            <div id="hover-sidebar-content">
                <form id="hover-sidebar-form">
                    <h3>Feedback Form</h3>';

        foreach ($questions as $index => $question) {
            if (empty($question_pages[$index]) || 
                $question_pages[$index] === $current_post_id || 
                $question_pages[$index] === $current_post_type || 
                $question_pages[$index] === $current_template || 
                (isset($question_post_ids[$index]) && in_array($current_post_id, explode(',', $question_post_ids[$index])))) {
                echo '<label>' . esc_html($question) . '<input type="text" name="question_' . $index . '" /></label>';
            }
        }

        if (current_user_can('manage_options')) {
            echo '<div style="font-size: small; margin-top: 10px; background: #f4f4f4; padding: 5px;">
                <strong>Debug Info:</strong><br>
                Post Type: ' . esc_html($current_post_type ?: 'N/A') . '<br>
                Template: ' . esc_html($current_template ?: 'N/A') . '<br>
                Page ID: ' . esc_html($current_post_id ?: 'N/A') . '<br>
                URL: ' . esc_html($current_url) . '<br>
                <button type="button" id="add-to-dropdown" class="button button-link" data-context="' . esc_attr($context) . '">Add to Dropdown</button>
            </div>';
        }

        echo '<button type="submit">Submit</button>
                </form>
            </div>
        </div>';
    }

    public function handle_form_submission() {
        $submissions = get_option('hover_sidebar_submissions', []);
        $user = wp_get_current_user();
        $answers = [];
        $questions = explode("\n", get_option('hover_sidebar_questions', "Question 1\nQuestion 2\nQuestion 3\nQuestion 4\nQuestion 5"));

        foreach ($_POST as $key => $value) {
            if (strpos($key, 'question_') === 0) {
                $answers[] = sanitize_text_field($value);
            }
        }

        $submissions[] = [
            'user' => $user->exists() ? $user->display_name : 'Guest',
            'answers' => $answers,
            'questions' => $questions,
            'page' => $_POST['page'],
            'time' => current_time('mysql')
        ];

        update_option('hover_sidebar_submissions', $submissions);
        wp_send_json_success('Form submitted successfully.');
    }

    public function clear_submissions() {
        if (current_user_can('manage_options')) {
            update_option('hover_sidebar_submissions', []);
            wp_send_json_success('All submissions have been cleared.');
        } else {
            wp_send_json_error('Unauthorized action.');
        }
    }

    public function add_to_dropdown() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized action.');
        }

        $context = sanitize_text_field($_POST['context']);
        $existing = get_option('hover_sidebar_question_pages', []);

        if (!in_array($context, $existing)) {
            $existing[] = $context;
            update_option('hover_sidebar_question_pages', $existing);
            wp_send_json_success('Context added to dropdown.');
        } else {
            wp_send_json_success('Context already exists in dropdown.');
        }
    }

    public function download_submissions_csv() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }

        $submissions = get_option('hover_sidebar_submissions', []);

        if (!$submissions) {
            wp_die('No submissions to download');
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=submissions.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['User', 'Answers', 'Page', 'Time']);

        foreach ($submissions as $submission) {
            $answers = implode(' | ', $submission['answers']);
            fputcsv($output, [$submission['user'], $answers, $submission['page'], $submission['time']]);
        }

        fclose($output);
        exit;
    }
}

// Activate the plugin
new HoverSidebarFormPlugin();
