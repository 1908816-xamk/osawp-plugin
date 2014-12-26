<?php

define ("ORIGINSTAMP_SETTINGS", serialize (array(
  "api_token" => "",
  "sender" => "",
  "send_back" => 0
)));

/**
 * Plugin Name: OriginStamp
 * Plugin URI: http://www.originstamp.org
 * Description: Posts your new/updated contents to our API and generates a timestamp-hash paris which is saved in the Bitcoin blockchain.
 * Version: 0.1
 * Author: André Gernandt
 * Author URI: https://github.com/ager/
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
 * @TODO create README
 *  http://wordpress.org/plugins/about/readme.txt, http://generatewp.com/plugin-readme/
 */

function create_originstamp( $post_id ) {
  if ( wp_is_post_revision( $post_id ) )
    return;

  global $user_email;
  global $blog_id;
  get_currentuserinfo();

  // TODO recipients could be possible other authors which are somehow exisiting in the db record for the post
  $recipients = false;
  $response = true;

  $body = array(
    'sender' => $user_email,
    'recipients' => $recipients
  );
  
  if ( $response ) {
    // include_once( ABSPATH . WPINC . '/class-IXR.php' );
    // include_once( ABSPATH . WPINC . '/class-wp-http-ixr-client.php' );
    // $result = new WP_HTTP_IXR_CLIENT( 'http://localhost/wordpress/xmlrpc.php' );
    // $result->query( 'wp.getPost', $blog_id, "user", "password", $post_id );
    $result = get_post( $post_id );

    $body['raw_content'] = serialize([$result->post_title, $result->post_content]);
  } else {
    $body['hash_sha256'] = hash( 'sha256', $content );
  }

  send_to_originstamp_api( $body );
}

function send_to_originstamp_api( $body ) {
  $options = get_options();
  $body['send_back'] = $options['send_back'];
  $body['sender'] = $options['sender'];

  $response = wp_remote_post( 'http://www.originstamp.org/api/stamps', array(
    'method' => "POST",
    'timeout' => 45,
    'redirection' => 5,
    'httpversion' => "1.0",
    'blocking' => true,
    'headers' => array(
      'content-type' => "application/json",
      'Authorization' => "Token token=" . $options['api_token']
    ),
    'body' => json_encode( $body ),
    'cookies' => array()

  ) );

  if ( is_wp_error( $response ) ) {
    $error_message = $response->get_error_message();
    // TODO
  } else {
    // TODO success 
  }
}

function originstamp_action_links ( $links ) {
  array_unshift( $links, '<a href="' . admin_url( 'options-general.php?page=originstamp' ) . '">Settings</a>' );
  return $links;
}

function originstamp_admin_menu() {
  register_setting( 'originstamp', 'originstamp' );
  // apply_filters( 'originstamp_default_options', array(
  //   'api_token'  => "",
  //   'sender'     => get_option( 'admin_email' ),
  //   'send_back'  => 0
  // ) );

  add_settings_section( 'originstamp', __( 'OriginStamp API' ), 'settings_section', 'originstamp' );
  add_settings_field( 'originstamp_api_token', __( 'API Key' ), 'api_token', 'originstamp', 'originstamp' );
  add_settings_field( 'originstamp_send_back', __( 'Send back' ), 'send_back', 'originstamp', 'originstamp' );
  add_settings_field( 'originstamp_sender_email', __( 'Sender Email' ), 'sender_email', 'originstamp', 'originstamp' );

  add_options_page( __( 'OriginStamp' ), __( 'OriginStamp' ), 'manage_options', 'originstamp', 'originstamp_admin_page' );
}

add_action( 'save_post', 'create_originstamp' );
add_action( 'admin_menu', 'originstamp_admin_menu' );
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'originstamp_action_links' );

function validate_options( $options ) { 
  $options = unserialize(ORIGINSTAMP_SETTINGS);
  return $options;
}

function settings_section() {} // stub

function get_options() {
  $options = (array) get_option( 'originstamp' );
  // $options = wp_parse_args ( $options );
  return $options;
}

function originstamp_admin_page() {
?>
  <div class="wrap">
    <h2><?php _e( 'OriginStamp' ); ?></h2>
  <?php if ( !empty( $options['invalid_emails'] ) && $_GET['settings-updated'] ) : ?>
    <div class="error">
      <p><?php // printf( _n( 'Invalid Email: %s', 'Invalid Emails: %s', count( $options['invalid_emails'] ) ), '<kbd>' . join( '</kbd>, <kbd>', array_map( 'esc_html', $options['invalid_emails'] ) ) ); ?></p>
    </div>
  <?php endif; ?>

    <form action="options.php" method="post">
      <?php settings_fields( 'originstamp' ); ?>
      <?php do_settings_sections( 'originstamp' ); ?>
      <p class="submit">
        <input type="submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes' ); ?>" />
      </p>
    </form>
  </div>
<?php

}

function api_token() {
  $options = get_options();
?>
  <input type="text" name="originstamp[api_token]" size="40" value="<?php echo $options['api_token'] ?>" />
  <p class="description"><?php _e( 'An API key is required to create timestamps. Receive your personal key here:' ) ?> http://www.originstamp.org/developer</p>
<?php
  }

function sender_email() {
  $options = get_options();
?>
  <input type="email" name="originstamp[sender]" size="40" value="<?php echo $options['sender'] ?>" />
  <p class="description"><?php _e( 'Please note that this only works if \'Send Back\' is check. Input a valid email address.' ); ?></p>
<?php
  }

function send_back() {
  $options = get_options();
?>
  <p><label><input type="checkbox" name="originstamp[send_back]" value="1"<?php checked( $options['send_back'], 1 ); ?> /> <?php _e( 'This plugin timestamps a serialized string of your complete post. If you want us to send you back a copy of the content which is timestamped, please check this field. In addition please provide an email address in the \'Sender\' field.' ); ?></label></p>
<?php
  }
?>
