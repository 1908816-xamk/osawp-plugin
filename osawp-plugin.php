<?php
defined('ABSPATH') OR exit;
/*
 * Plugin Name: OriginStamp attachments for WordPress
 * Plugin URI: 
 * description: Creates a tamper-proof timestamp of your media attachment files using OriginStamp API. This is not an original plugin by OriginStamp.
 * Version: 1.0.1
 * Author: Henri Tikkanen
 * Author URI: http://www.henritikkanen.info
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
register_activation_hook(__FILE__, array('osawpPlugin', 'on_activation'));
register_uninstall_hook(__FILE__, array('osawpPlugin', 'on_uninstall'));

if (!class_exists('osawpPlugin')) {

    add_action('plugins_loaded', array('osawpPlugin', 'init'));
    class osawpPlugin{

        protected static $instance;

        public static function init() {
            is_null( self::$instance ) AND self::$instance = new self;
            return self::$instance;
        }

        public function __construct() {
        	define('osawp', plugins_url(__FILE__));
        	add_action('admin_head', array($this, 'osawp_admin_register_head'));
        	add_action('admin_menu', array($this, 'osawp_admin_menu'));
			add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'osawp_action_links'));	
			add_action('add_attachment', array($this, 'osawp_create_originstamp'));
			add_filter('attachment_fields_to_edit',  array($this, 'osawp_field_edit'),null, 2);
			add_filter('attachment_fields_to_edit',  array($this, 'osawp_get_blockchain_status'),null, 2);
			add_filter('attachment_fields_to_save', array($this,'osawp_field_save'), null, 2);	
			add_action('admin_post_nopriv_originstamp_approved', array($this, 'osawp_process_webhook'));
			add_action( 'rest_api_init', array($this, 'osawp_handle_proof_request'));
        }

        // Add font-awesome styles to Originstamp settings page.
        public function osawp_admin_register_head() {
            // font-awesome repo at cdnjs
            wp_register_style('osawp_wp_admin_css', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css', false, '1.0.0');
			wp_add_inline_style('osawp_wp_admin_css', '.media-types-required-info {display:none;}.compat-attachment-fields th{padding-top:0;}');
            wp_enqueue_style('osawp_wp_admin_css');
        }

        public static function on_activation() {
            // Create data table for local storage at activation.
            if (!current_user_can('activate_plugins'))
                return;

            global $wpdb;

            $charset_collate = $wpdb->get_charset_collate();
            $options = self::get_options();
            $options['api_version'] = 'v4';
            update_option('osawp_settings', $options);
            $table_name = $wpdb->prefix . 'osawp_hash_data';

            $sql = "CREATE TABLE $table_name (
            	sha256 varchar(64) UNIQUE NOT NULL,
                time datetime DEFAULT CURRENT_TIMESTAMP,
                post_title tinytext NOT NULL,
                post_content longtext NOT NULL,
                PRIMARY KEY (sha256)
                ) $charset_collate";

            if (is_admin())
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            try {
                $res = dbDelta($sql);
                if (!$res) {
                    return;
                }
            } catch (Exception $e) {
                return;
            }
        }

        public static function on_uninstall() {
            if (!current_user_can('delete_plugins'))
                return;

            global $wpdb;
            $options = self::get_options();
            $table_name = $wpdb->prefix . 'osawp_hash_data';
            $sql = $wpdb->prepare('DROP TABLE IF EXISTS ' . $table_name, $table_name);
            $wpdb->query($sql);

            delete_option('osawp_settings');
            delete_site_option('osawp_settings');
        }

        public function osawp_admin_menu() {
        	register_setting('osawp_settings', 'osawp_settings');
        	add_options_page(__('OriginStamp Attachments'), __('OriginStamp Attachments'), 'manage_options', 'osawp_settings', array($this, 'osawp_admin_page'));
        	add_settings_section('osawp_settings', __('Settings'), array($this, 'osawp_settings_section'), 'osawp_settings');
        	add_settings_field('osawp_description', __('Description'), array($this, 'osawp_description'), 'osawp_settings', 'osawp_settings');
        	add_settings_field('osawp_api_key', __('API key'), array($this, 'osawp_api_key'), 'osawp_settings', 'osawp_settings');
        	add_settings_field('osawp_api_version', __('API version'), array($this, 'osawp_api_version'), 'osawp_settings', 'osawp_settings');
			add_settings_field('osawp_stamp_all', __('Stamp new uploads automatically'), array($this, 'osawp_stamp_all'), 'osawp_settings', 'osawp_settings');
			add_settings_field('osawp_db_status', __('DB status'), array($this, 'osawp_check_db_status'), 'osawp_settings', 'osawp_settings');
			add_settings_field('osawp_dev', __('Developers'), array($this, 'osawp_dev_info'), 'osawp_settings', 'osawp_settings');
        }

        public function osawp_action_links($links) {
            array_unshift($links, '<a href="' . admin_url('options-general.php?page=osawp_settings') . '">Settings</a>');
            return $links;
        }
		
        // Show blockchain status	
		public function osawp_get_blockchain_status ( $form_fields, $post ) {
	
			global $pagenow;
			
			if ($pagenow == 'post.php') {

			$blockchain_status = get_post_meta( $post->ID, 'blockchain', true );
			$blockchain_status ? $proof = json_decode($this->osawp_download_proof($blockchain_status['hash_string'])) : $proof = null;
			$blockchain_status ? $status_html = '<b>This attachment is succesfully stamped by the following hash string: </b>' . 
            $blockchain_status['hash_string'] . '<br>' . 
			'<a href="' . $proof->data->download_url . '">Download Proof (PDF)</a>' : 
            $status_html = '<b>This attachment is not stamped yet!</b>';

			$form_fields['blockchain_status'] = array(
				'label' => 'Blockchain status:',
				'input' => 'html',
				'html' => $status_html,
				);
			}
			return $form_fields;
		}

		// Show and save custom checkbox attachment field	
		public function osawp_field_edit ( $form_fields, $post ) {

			global $pagenow;

			if ($pagenow == 'post.php') {
				
			$originstamp = (bool) get_post_meta($post->ID, 'originstamp', true);
			$blockchain_status = get_post_meta( $post->ID, 'blockchain', true );
				
			$form_fields['originstamp'] = array(
				'label' => 'Send to OriginStamp?',
				'input' => 'html',
				'html' => '<label for="attachments-'.$post->ID.'-originstamp"> '.
				'<input type="checkbox" id="attachments-'.$post->ID.'-originstamp" name="attachments['.$post->ID.'][originstamp]" value="1"'.($originstamp ? 'style="display:none;"' : '').'/>' . ($originstamp ? '<b>This attachment seems already sent to OriginStamp!</b>' : ''),
				);
			}					
			return $form_fields;
		}
		
		public function osawp_field_save ($post, $attachment) {  
			if( isset($attachment['originstamp']) ){  
				$this->osawp_create_originstamp($post['ID']);
			}
			return $post;  
		}

        private function osawp_insert_hash_in_table($hash_string, $post_title, $image_file) {
            global $wpdb;
            $options = self::get_options();
            $table_name = $wpdb->prefix . 'osawp_hash_data';
            $wpdb->insert($table_name,
                array('sha256' => $hash_string, 'post_title' => $post_title, 'post_content' => $image_file),
                array());
        }

        private function osawp_retrieve_hash_from_table($hash_string) {
            global $wpdb;
            $options = self::get_options();
            $table_name = $wpdb->prefix . 'osawp_hash_data';
            $sql = "SELECT * FROM $table_name WHERE sha256 = \"$hash_string\"";
            $result = $wpdb->get_row($wpdb->prepare($sql, $hash_string));
            if ($result) {
                return $result->sha256;
            }
            return null;
        }

        // Create hash value, save to database and prepare for sending
        public function osawp_create_originstamp($attachment_id) {
			global $pagenow;
			$options = self::get_options();
			
			!empty($options['stamp_all']) ? $stamp_all = true : $stamp_all = false;
			if ( $stamp_all && wp_is_post_revision($attachment_id) ) {
				return;
			} elseif ( $stamp_all || !$stamp_all && $pagenow == 'post.php' ) {
					
				$post_title = preg_replace(
					"/[\n\r ]+/",//Also more than one space
					" ",//space
					wp_strip_all_tags(get_the_title($attachment_id)));

				$image_file = wp_get_attachment_url( $attachment_id );
				$hash_string = hash_file('sha256', $image_file);

                if ($this->osawp_retrieve_hash_from_table($hash_string) === $hash_string) {
                    return;
                }

				$body['hash'] = $hash_string;
				$body['comment'] = $attachment_id;

				$body['notifications'] [] = Array(
					'currency' => 0,
					'notification_type' => 1,
					'target' => get_site_url() . '/wp-admin/admin-post.php?action=originstamp_approved'
				);
				
				$this->osawp_insert_hash_in_table($hash_string, $post_title, $image_file);
				update_post_meta($attachment_id, 'originstamp', true );
				$this->osawp_send_to_originstamp_api($body);
			}
        }

        // Send computed hash value to OriginStamp
        private function osawp_send_to_originstamp_api($body) {
            
            $options = self::get_options();
            $response = wp_remote_post('https://api.originstamp.com/' . $options['api_version'] . '/timestamp/create', array(
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
                return $error_message;
            }
            return $response;
        }
		
		// Request a proof from Origistamp
		private function osawp_download_proof ($hash_string) {
            
        	$options = self::get_options();
			 
			$body = array(
                    'currency' => 1,
                    'hash_string' => $hash_string,
					'proof_type' => 1
            );
			 
            $response = wp_remote_post('https://api.originstamp.com/v3/timestamp/proof/url', array(
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
                return $error_message;
            }
            return $response['body'];
        }
		
		// REST route for requesting a proof
		public function osawp_handle_proof_request() {
			register_rest_route( 'wp/v2', 'media/proof/(?P<id>[\d]+)', 	
				array(
				'methods' => 'GET',
				'callback' => function ($data) {
					$media_id = $data['id'];
					$blockchain_status = get_post_meta( $media_id, 'blockchain', true );
					if ($blockchain_status) {
						$hash_string = $blockchain_status['hash_string'];
						$proof = json_decode($this->osawp_download_proof($hash_string));
						return $proof->data->download_url;
					} else {
						return new WP_Error(
						'no_posts', 'No blockchain data was found for this attachment id!', array( 'status' => 404 ));
					}
				}
			));
		}
		
		// Process webhook 
		public function osawp_process_webhook() {

			$request = file_get_contents('php://input');
			$data = json_decode($request, true);
			$post_id = $data['comment'];
			update_post_meta( $post_id, 'blockchain', $data );
		}

        // Options section
        public function osawp_settings_section() {
            // do nothing
        }

        private static function get_options() {
            $options = (array)get_option('osawp_settings');
            return $options;
        }

        public function osawp_admin_page() {
            ?>
            <div class="wrap">
                <h2><?php _e('OriginStamp Attachments for WordPress'); ?></h2>
               
                <form action="options.php" method="post">
                    <?php settings_fields('osawp_settings'); ?>
                    <?php do_settings_sections('osawp_settings'); ?>
                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes'); ?>"/>
                    </p>
                </form>
            </div>
            <?php
        }

        public function osawp_description() {
            ?>
            <p><i> OriginStamp is a web-based, trusted timestamping service that uses the decentralized blockchain to store anonymous, 
                tamper-proof timestamps for any digital content. OriginStamp allows users to hash files, emails, or plain text, and subsequently
                store the created hashes in the blockchain as well as retrieve and verify timestamps that have been committed to the blockchain. 
                OriginStamp is free of charge and easy to use. It enables anyone, e.g., students, researchers, authors, journalists, or artists, 
                to prove that they were the originator of certain information at a given point in time.</i> 
                (Source: <a target="_blank" href="https://docs.originstamp.com/">docs.originstamp.com)</a>
                <br><br>
                This plugin sends a hash value of your media attachment files, like images and videos, to OriginStamp API. 
                Then they will be saved to multiple blockchains as SHA256 encoded format, to proof the originality of your media files.
                This proof is verifiable to anyone who have a copy of the original data and they also call these as timestamps. You can choose wether you like to send all new 
                uploads to OriginStamp or manually send just particular files in the Media Library. However, you can send the file 
                with same hash value only once. If you need to modify your original file and send a new version, you should create a new upload of it.
            </p><br>
            <p><b>What content will be sent to OriginStamp API?</b></p>
            <p class="description">
                In this version, only SHA256 value generated of the original file and attachment ID number in WordPress will be sent, nothing else.
            </p>
            <p><b>When the data will be sent to OriginStamp API?</b></p>
            <p class="description">
                By default, only when you check "Send to OriginStamp" option in the media editing view and update the post.
                Alternatively, you can also choose "Stamp new uploads automatically" here in the options, when all the new uploads will be send automatically.
                Only attachments, that haven't sent before in exactly the same form, can be sent.
            </p>
            <p><b>How I know, that my data is succesfully stamped?</b></p>
            <p class="description">
                You will see a hash code and a timestamp in media editing view, when OrigiStamp has sent the confirmation, that data has been succesfully saved to all three blockchains (Bitcoin, Ethereum and Aion). 
                This is done by using webhooks provided by OrigiStamp API. More detailed information will be also saved to post meta of the attchment in WordPress. 
                You are free to use this information in your own front-end implementations or with some other applications. You can always check statuses also from 
                your own account in OriginStamp: <a target="_blank" href="https://my.originstamp.com/sessions/signin"><i class="fa fa-sign-in" aria-hidden="true"></i></a></p>
            <p><b>Does stamping means that my files will be also minted as NFTs or what is the difference?</b></p>
            <p class="description">
                No, your files won't be minted as NFTs when they have been stamped. Saving a hash value of your files to a blockchain is providing only a proof of the originality, when the basic idea
                behind NFT is to provide a proof of the ownership of any digital content by using smart contracts.
            </p>
            <p><b>How to verify a timestamp?</b></p>
            <p class="description">In order to verify the timestamp you would have to download the data, copy the string that is
                stored in the text file and then use any sha256 calculator of your choice to hash the string. After that go to
                OriginStamp and search for the hash. There you will also find further instructions and features. Read more at: <a target="_blank" href="https://docs.originstamp.com/guide/originstamp.html"><i class="fa fa-sign-in" aria-hidden="true"></i></a></p>
            <p><b>Where do I get more Information?</b></p>
            <p>Please visit at OriginStamp FAQ: <a target="_blank" href="https://docs.originstamp.com/"><i class="fa fa-sign-in" aria-hidden="true"></i></a></p>
            <?php
        }

        public function osawp_api_key() {
            // Read in API key
            $options = self::get_options();
            isset($options['api_key']) ? $api_key = $options['api_key'] : $api_key = '';

            $valid_uuid = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
            if (isset($api_key) ) {
                ?><input title="API key" type="text" name="osawp_settings[api_key]" size="40"
                     value="<?php echo $api_key; ?>"/><?php
            } else {
                ?><input title="API key" type="text" name="osawp_settings[api_key]" size="40"
                     value=""/><?php
            }
            // Check, if API key is a valid uuid and show error message, if not.
            if (!preg_match($valid_uuid, $api_key) && !($api_key == '')) {
                ?><p class="description" style="color: rgb(255, 152, 0)"><?php _e('Error: API key is invalid! Please check that you copied all characters or contact OriginStamp.') ?></p><?php
            }
            ?><p class="description"><?php _e('An API key is required to create timestamps. Receive your personal key here:') ?>
            <a target="_blank" href="https://originstamp.org/dev">
                <i class="fa fa-sign-in" aria-hidden="true"></i>
            </a></p><?php             
        }

        public function osawp_api_version() {
            // Read in API version
            $options = self::get_options();
            $api_version = $options['api_version'];

            $valid_uuid = '/^v[0-9]$/i';
            if (isset($api_version) ) {
                ?><input title="API version" type="text" name="osawp_settings[api_version]" size="40"
                     value="<?php echo $api_version; ?>"/><?php
            } else {
                ?><input title="API version" type="text" name="osawp_settings[api_version]" size="40"
                     value=""/><?php
            }
            // Check, if API key is a valid uuid and show error message, if not.
            if (!preg_match($valid_uuid, $options['api_version']) && !($options['api_version'] == '')) {
                ?><p class="description" style="color: rgb(255, 152, 0)"><?php _e('Error: API version is invalid! Please check, that your input contains character "v" and number 1-9.') ?></p><?php
            }
            ?><p class="description"><?php _e('A current API version is required to creating a timestamp through the OriginStamp API. If default version is not working, please find the current version here:') ?>
            <a target="_blank" href="https://api.originstamp.com/swagger/swagger-ui.html">
                <i class="fa fa-sign-in" aria-hidden="true"></i>
            </a></p><?php
        }
		
		public function osawp_stamp_all() {
            // Stamp all
            $options = self::get_options();
			if ( !empty($options['stamp_all']))  {
				$stamp_all = $options['stamp_all'];
			} else {
				$stamp_all = false;
			}

            ?>
			<input type="checkbox" id="osawp_settings[stamp_all]" name="osawp_settings[stamp_all]" value="1" <?php if ($stamp_all) { echo 'checked="checked"'; } ?> />    
            <p class="description"><?php _e('Check this option if you want all new media files to be uploaded automatically.') ?> <p>				       
            <?php		
        }

        public function osawp_check_db_status() {
            global $wpdb;
            $options = self::get_options();
            $table_name = $wpdb->prefix . 'osawp_hash_data';
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE '%s'", $table_name)) === $table_name) {
                echo '<p style="color: rgb(0, 150, 136)">Database table created: ' . $table_name . ' . </p>';
            } else {
                echo '<p style="color: rgb(255, 152, 0)">ERROR: Data table does not exist: ' . $table_name . '</p>';
            }
            echo '<p class="description">Here you can check status of the database that stores hashed post data.</p>';
        }

        public function osawp_dev_info() {
            ?>
            This plugin version is published by <a target="_blank" href="https://henritikkanen.info">Henri Tikkanen</a>, 
            but it's based on an early development stage plugin created by guys from OriginStamp. Any support is not inculuded
            and you use this plugin by your own by your own risk.
            For getting more information about OriginStamp, please visit their site: 
            <a target="_blank" href="https://originstamp.com"><i class="fa fa-sign-in" aria-hidden="true"></i></a><br><br>
            Credits of the original plugin belongs to: <br><br>

			<p>Eugen Stroh</p>
			<p>Thomas Hepp</p>
			<p>Bela Gipp</p>
			<p>André Gernandt</p> 
            <?php
        }
    }
}
