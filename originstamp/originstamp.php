<?php

// Add font-awesome styles to Originstamp settings page.
function admin_register_head() {
    $siteurl = get_option('siteurl');
    $url = $siteurl . '/wp-content/plugins/' . basename(dirname(__FILE__)) . '/font-awesome-4.7.0/css/font-awesome.min.css';
    echo "<link rel='stylesheet' type='text/css' href='$url' />\n";
}
add_action('admin_head', 'admin_register_head');

// Define api key and email to save for future uses.
define("ORIGINSTAMP_SETTINGS", serialize(array(
    "api_key" => "",
    "email" => ""
)));

/**
 * Plugin Name: OriginStamp
 * Plugin URI: http://www.originstamp.org
 * Description: Creates a tamper-proof timestamp of your content each time it is modified. The timestamp is created with the Bitcoin blockchain.
 * Version: 0.3
 * Author: Thomas Hepp, André Gernandt, Eugen Stroh
 * Author URI: https://github.com/thhepp/
 * License: The MIT License (MIT)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 *  http://wordpress.org/plugins/about/readme.txt, http://generatewp.com/plugin-readme/
 */

function create_originstamp($post_id)
{
    if (wp_is_post_revision($post_id))
        return;

    $result = get_post($post_id);

    $data = serialize([$result->post_title, $result->post_content]);
    $hash_string = hash('sha256', $data);
    $body['hash_string'] = $hash_string;

    send_to_originstamp_api($body, $hash_string);
    send_confirm_email($data, $hash_string);
}

function send_to_originstamp_api($body, $hashString)
{
    $options = get_options();
    $body['email'] = $options['email'];

    $response = wp_remote_post('https://api.originstamp.org/api/' . $hashString, array(
        'method' => "POST",
        'timeout' => 45,
        'redirection' => 5,
        'httpversion' => "1.1",
        'blocking' => true,
        'headers' => array(
            'content-type' => "application/json",
            'Authorization' => $options['api_key']
        ),
        'body' => json_encode($body),
        'cookies' => array()

    ));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        $message = "Sorry, we had some issues posting your data to OriginStamp:\n";
        wp_mail($options['email'], "Originstamp: Error.", $message . $error_message);

        return $error_message;
    }

    return $response;
}

function send_confirm_email($data, $hash_string)
{
    $instructions = "Please store this Email. You need to hash following value with a SHA256:\n";
    $options = get_options();
    $response = wp_mail($options['email'], "OriginStamp " . $hash_string, $instructions . $data);

    return $response;
}

function originstamp_action_links($links)
{
    array_unshift($links, '<a href="' . admin_url('options-general.php?page=originstamp') . '">Settings</a>');
    return $links;
}

function get_hashes_for_api_key($offset, $records)
{
    // POST fields for table request.
    // email
    // hash_string
    // comment
    // date_created
    // api_key
    // offset
    // records
    $options = get_options();
    $body['api_key'] = $options['api_key'];

    if ($body['api_key'] == '')
        return array();

    // Start offset
    $body['offset'] = $offset;
    // Number of records
    $body['records'] = $records;

    $response = wp_remote_post('https://api.originstamp.org/api/table', array(
        'method' => "POST",
        'timeout' => 45,
        'redirection' => 5,
        'httpversion' => "1.1",
        'blocking' => true,
        'headers' => array(
            'content-type' => "application/json",
            'Authorization' => $options['api_key']
        ),
        'body' => json_encode($body),
        'cookies' => array()

    ));

    return $response;
}

function originstamp_admin_menu()
{
    register_setting('originstamp', 'originstamp');

    add_settings_section('originstamp', __('Settings'), 'settings_section', 'originstamp');
    add_settings_field('originstamp_api_key', __('API Key'), 'api_key', 'originstamp', 'originstamp');
    add_settings_field('originstamp_sender_email', __('Sender Email'), 'sender_email', 'originstamp', 'originstamp');
    add_settings_field('oroginstamp_hash_table', __('Hash table'), 'hashes_for_api_key', 'originstamp', 'originstamp');
    add_options_page(__('OriginStamp'), __('OriginStamp'), 'manage_options', 'originstamp', 'originstamp_admin_page');
}

add_action('save_post', 'create_originstamp');
add_action('admin_menu', 'originstamp_admin_menu');
add_action('wp_head', 'hashes_for_api_key');
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'originstamp_action_links');

function add_cors_http_header(){
    header("Access-Control-Allow-Origin: *");
}
add_action('init','add_cors_http_header');

function validate_options()
{
    $options = unserialize(ORIGINSTAMP_SETTINGS);
    return $options;
}

function settings_section()
{
} // stub

function get_options()
{
    $options = (array)get_option('originstamp');
    return $options;
}

function originstamp_admin_page()
{
    ?>
    <div class="wrap">
        <h2><?php _e('OriginStamp'); ?></h2>
        <?php if (!empty($options['invalid_emails']) && $_GET['settings-updated']) : ?>
            <div class="error">
                <p><?php  ?></p>
            </div>
        <?php endif; ?>

        <form action="options.php" method="post">
            <?php settings_fields('originstamp'); ?>
            <?php do_settings_sections('originstamp'); ?>
            <p class="submit">
                <input type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes'); ?>"/>
            </p>
        </form>
    </div>
    <?php
}

function api_key()
{
    $options = get_options();
    ?>
    <input type="text" name="originstamp[api_key]" size="40" value="<?php echo $options['api_key'] ?>"/>
    <p class="description"><?php _e('An API key is required to create timestamps. Receive your personal key here:') ?>
       <a href="https://www.originstamp.org/dev">https://www.originstamp.org/dev</a></p>
    <?php
}

function sender_email()
{
    $options = get_options();
    ?>
    <input type="text" name="originstamp[email]" size="40" value="<?php echo $options['email'] ?>"/>
    <p class="description"><?php _e('Please provide an Email address so that we can send your data. You need to store your data to be able to verify it.') ?>
    <?php
}

function hashes_for_api_key()
{
    // Maximum number of pages the API will return.
    $limit = 50;

    // Get first record, to determine, how many records there are overall.
    $get_page_info = get_hashes_for_api_key(0, 1);

    // Extract bory from response.
    $page_info_json_obj = json_decode($get_page_info['body']);

    // Total number of records in the database.
    $total = $page_info_json_obj->total_records;

    // Overall number of pages
    $num_of_pages = ceil($page_info_json_obj->total_records / $limit);

    // GET current page
    $page = min($num_of_pages, filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT, array(
        'options' => array(
            'default'   => 1,
            'min_range' => 1,
        ),
    )));
    // Calculate the offset for the query
    $offset = ($page - 1) * $limit;

    // Some information to display to the user
    $start = $offset + 1;
    $end = min(($offset + $limit), $total);

    // The "back" link
    $prevlink = ($page > 1) ? '<a href="?page=originstamp&p=1" title="First page">&laquo;</a> <a href="?page=originstamp' . '&p=' . ($page - 1) . '" title="Previous page">&lsaquo;</a>' : '<span class="disabled">&laquo;</span> <span class="disabled">&lsaquo;</span>';

    // The "forward" link
    $nextlink = ($page < $num_of_pages) ? '<a href="?page=originstamp' . '&p=' . ($page + 1) . '" title="Next page">&rsaquo;</a> <a href="?page=originstamp' . '&p=' . $num_of_pages . '" title="Last page">&raquo;</a>' : '<span class="disabled">&rsaquo;</span> <span class="disabled">&raquo;</span>';

    // Display the paging information
    echo '<p class="description">A list of all your hashes submitted with API key above: <br></p>';
    echo '<div id="paging"><p>', $prevlink, ' Page ', $page, ' of ', $num_of_pages, ' pages, displaying ', $start, '-', $end, ' of ', $total, ' results ', $nextlink, ' </p></div>';

    // Get data from API.
    $response = get_hashes_for_api_key($offset, $limit);
    $response_json_obj = json_decode($response['body']);

    // Parse response.
    ?>
        <?php echo '<table style="display: inline-table;">'?>
        <?php echo '<p style="display:inline-table;">' ?>
            <?php foreach ($response_json_obj->hashes as $hash) {
                // From milliseconds to seconds.
                $date_created = $hash->date_created / 1000;
                $submit_status = $hash->submit_status->multi_seed;
                $hash_string = $hash->hash_string;
                echo '<tr>';
                    echo '<td>' . gmdate("Y-m-d H:i:s", $date_created). '</td>';
                echo '</tr>';
                echo '<tr>';
                    echo '<td>';
                        echo '<a href="https://originstamp.org/s/'
                            . $hash_string
                            . '"'
                            . ' target="_blank"'
                            . '">'
                            . $hash_string
                            . '</a>';
                    echo '</td>';
                echo '</tr>';
                echo '<tr>';
                    echo '<td>';
                        echo '<i class="fa fa-check-circle-o" aria-hidden="true"></i>';
                    echo '</td>';
                echo '</tr>';
            }
            ?>
        <?php echo '</table>' ?>
            <?php
}
?>
