<?php

define("ORIGINSTAMP_SETTINGS", serialize(array(
    "api_token" => "",
    "email" => ""
)));

/**
 * Plugin Name: OriginStamp
 * Plugin URI: http://www.originstamp.org
 * Description: Creates a tamper-proof timestamp of your content each time it is modified. The timestamp is created with the Bitcoin blockchain.
 * Version: 0.3
 * Author: Thomas Hepp, André Gernandt
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

    global $blog_id;
    get_currentuserinfo();

    // include_once( ABSPATH . WPINC . '/class-IXR.php' );
    // include_once( ABSPATH . WPINC . '/class-wp-http-ixr-client.php' );
    // $result = new WP_HTTP_IXR_CLIENT( 'http://localhost/wordpress/xmlrpc.php' );
    // $result->query( 'wp.getPost', $blog_id, "user", "password", $post_id );
    $result = get_post($post_id);

    $data = serialize([$result->post_title, $result->post_content]);
    $hash_string = hash('sha256', $data);
    $body['hash_string'] = $hash_string;

    $response = serialize(send_to_originstamp_api($body, $hash_string));
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
            'Authorization' => $options['api_token']
        ),
        'body' => json_encode($body),
        'cookies' => array()

    ));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
    }

    return $response;
}

function send_confirm_email($data, $hash_string)
{
    $instructions = "Please store this Email. You need to hash following value:";
    $options = get_options();
    $response = wp_mail($options['email'], "OriginStamp " . $hash_string, $data);
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
    }
}

function originstamp_action_links($links)
{
    array_unshift($links, '<a href="' . admin_url('options-general.php?page=originstamp') . '">Settings</a>');
    return $links;
}

function originstamp_table_information()
{

}

function originstamp_admin_menu()
{
    register_setting('originstamp', 'originstamp');
    // apply_filters( 'originstamp_default_options', array(
    //   'api_token'  => "",
    //   'sender'     => get_option( 'admin_email' )
    // ) );

    add_settings_section('originstamp', __('OriginStamp API'), 'settings_section', 'originstamp');
    add_settings_field('originstamp_api_token', __('API Key'), 'api_token', 'originstamp', 'originstamp');
    add_settings_field('originstamp_sender_email', __('Sender Email'), 'sender_email', 'originstamp', 'originstamp');
    add_options_page(__('OriginStamp'), __('OriginStamp'), 'manage_options', 'originstamp', 'originstamp_admin_page');
}

add_action('save_post', 'create_originstamp');
add_action('admin_menu', 'originstamp_admin_menu');
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'originstamp_action_links');

function validate_options($options)
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
    // $options = wp_parse_args ( $options );
    return $options;
}

function originstamp_admin_page()
{
    ?>
    <div class="wrap">
        <h2><?php _e('OriginStamp'); ?></h2>
        <?php if (!empty($options['invalid_emails']) && $_GET['settings-updated']) : ?>
            <div class="error">
                <p><?php // printf( _n( 'Invalid Email: %s', 'Invalid Emails: %s', count( $options['invalid_emails'] ) ), '<kbd>' . join( '</kbd>, <kbd>', array_map( 'esc_html', $options['invalid_emails'] ) ) ); ?></p>
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

function api_token()
{
    $options = get_options();
    ?>
    <input type="text" name="originstamp[api_token]" size="40" value="<?php echo $options['api_token'] ?>"/>
    <p class="description"><?php _e('An API key is required to create timestamps. Receive your personal key here:') ?>
        https://www.originstamp.org/dev</p>
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

?>
