<?php
defined('ABSPATH') OR exit;
/*
 * Plugin Name: OriginStamp for Wordpress
 * Plugin URI: http://www.ca1ee1698_originstamp.org
 * description: Creates a tamper-proof timestamp of your content each time it is modified. The timestamp is created with the Bitcoin blockchain.
 * Version: 0.0.5
 * Author: Thomas Hepp, André Gernandt, Eugen Stroh
 * Author URI: https://github.com/thhepp/, https://github.com/ager, https://github.com/eustro
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
register_activation_hook(__FILE__, array('ca1ee1698OriginStampPLForWP', 'on_activation'));
register_uninstall_hook(__FILE__, array('ca1ee1698OriginStampPLForWP', 'on_uninstall'));

if (!class_exists('ca1ee1698OriginStampPLForWP')) :

    add_action('plugins_loaded', array('ca1ee1698OriginStampPLForWP', 'init'));
    class ca1ee1698OriginStampPLForWP{

        protected static $instance;

        public static function init()
        {
            is_null( self::$instance ) AND self::$instance = new self;
            return self::$instance;
        }

        public function __construct()
        {
            define('ca1ee1698_originstamp', plugins_url(__FILE__));

            add_action('admin_head', array($this, 'admin_register_head'));
            add_action('save_post', array($this, 'create_originstamp'));
            add_action('admin_menu', array($this, 'originstamp_admin_menu'));
            add_action('wp_head', array($this, 'hashes_for_api_key'));
            add_action('init', array($this, 'download_hash_data'));
            add_action('template_redirect', array($this, 'download_hash_data'));

            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'originstamp_action_links'));
        }

        // Add font-awesome styles to Originstamp settings page.
        public function admin_register_head()
        {
            // font-awesome repo at cdnjs
            // register style
            wp_register_style('originstamp_wp_admin_css', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css', false, '1.0.0');
            // set
            wp_enqueue_style('originstamp_wp_admin_css');
        }

        public static function on_activation()
        {
            // Create data table for local storage at activation.
            if (!current_user_can('activate_plugins'))
                return;

            global $wpdb;

            $charset_collate = $wpdb->get_charset_collate();
            $options = self::get_options();
            $options['db_table_name'] = 'ca1ee1698_hash_data';
            update_option('ca1ee1698_originstamp', $options);
            $table_name = $wpdb->prefix . $options['db_table_name'];

            $sql = "CREATE TABLE $table_name (
                        sha256 varchar(64) UNIQUE NOT NULL,
                        time datetime DEFAULT CURRENT_TIMESTAMP,
                        post_title tinytext NOT NULL,
                        post_content longtext NOT NULL,
                        PRIMARY KEY (sha256)
                    ) $charset_collate;";

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

        public static function on_uninstall()
        {
            if (!current_user_can('delete_plugins'))
                return;

            global $wpdb;
            $options = self::get_options();
            $table_name = $wpdb->prefix . $options['db_table_name'];
            $sql = $wpdb->prepare('DROP TABLE IF EXISTS ' . $table_name, $table_name);
            $wpdb->query($sql);

            delete_option('ca1ee1698_originstamp');
            delete_site_option('ca1ee1698_originstamp');
        }

        public function originstamp_admin_menu()
        {
            register_setting('ca1ee1698_originstamp', 'ca1ee1698_originstamp');

            add_options_page(__('OriginStamp'), __('OriginStamp'), 'manage_options', 'ca1ee1698_originstamp', array($this, 'originstamp_admin_page'));

            add_settings_section('ca1ee1698_originstamp', __('Settings'), array($this, 'settings_section'), 'ca1ee1698_originstamp');
            add_settings_field('originstamp_description', __('Description'), array($this, 'description'), 'ca1ee1698_originstamp', 'ca1ee1698_originstamp');
            add_settings_field('originstamp_api_key', __('API Key'), array($this, 'api_key'), 'ca1ee1698_originstamp', 'ca1ee1698_originstamp');
            add_settings_field('originstamp_sender_email', __('Sender Email'), array($this, 'sender_email'), 'ca1ee1698_originstamp', 'ca1ee1698_originstamp');
            add_settings_field('originstamp_db_status', __('DB status'), array($this, 'check_db_status'), 'ca1ee1698_originstamp', 'ca1ee1698_originstamp');
            add_settings_field('oroginstamp_hash_table', __('Hash table'), array($this, 'hashes_for_api_key'), 'ca1ee1698_originstamp', 'ca1ee1698_originstamp');
            add_settings_field('originstamp_dev', __('Developers'), array($this, 'dev_info'), 'ca1ee1698_originstamp', 'ca1ee1698_originstamp');
        }

        public function originstamp_action_links($links)
        {
            array_unshift($links, '<a href="' . admin_url('options-general.php?page=ca1ee1698_originstamp') . '">Settings</a>');
            return $links;
        }

        public function settings_section()
        {
            // do nothing
            ;
        }

        private static function get_options()
        {
            $options = (array)get_option('ca1ee1698_originstamp');
            return $options;
        }

        public function originstamp_admin_page()
        {
            ?>
            <div class="wrap">
                <h2><?php _e('OriginStamp'); ?></h2>
                <?php if (!empty($options['invalid_emails']) && $_GET['settings-updated']) : ?>
                    <div class="error">
                        <p><?php ?></p>
                    </div>
                <?php endif; ?>
                <form action="options.php" method="post">
                    <?php settings_fields('ca1ee1698_originstamp'); ?>
                    <?php do_settings_sections('ca1ee1698_originstamp'); ?>
                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes'); ?>"/>
                    </p>
                </form>
            </div>
            <?php
        }

        function download_hash_data()
        {
            // Download a data set from plugin data table.
            if (isset($_GET['d'])) {
                $hash_string = $_GET['d'];
                if (!ctype_xdigit($hash_string))
                    exit('Hash string invalid');
                $data = $this->retrieve_hash_from_table($hash_string);
                if (!$data)
                    exit('Hash string not found in database.');
                header("Content-type: application/x-msdownload", true, 200);
                header("Content-Disposition: attachment; filename=$hash_string.txt");
                header("Pragma: no-cache");
                header("Expires: 0");
                echo $data;
                exit();
            }
        }

        private function insert_hash_in_table($hash_string, $post_title, $post_content)
        {
            global $wpdb;
            $options = self::get_options();
            $table_name = $wpdb->prefix . $options['db_table_name'];
            $wpdb->insert($table_name,
                array('sha256' => $hash_string, 'post_title' => $post_title, 'post_content' => $post_content),
                array());
        }

        private function retrieve_hash_from_table($hash_string)
        {
            global $wpdb;
            $options = self::get_options();
            $table_name = $wpdb->prefix . $options['db_table_name'];
            $sql = "SELECT * FROM $table_name WHERE sha256 = \"$hash_string\"";
            $result = $wpdb->get_row($wpdb->prepare($sql, $hash_string));
            $data = $result->post_title . $result->post_content;

            return $data;
        }

        public function create_originstamp($post_id)
        {
            // Create a SHA256 value from WP post or edit.
            if (wp_is_post_revision($post_id))
                return;
            $title = preg_replace(
                "/[\n\r ]+/",//Also more than one space
                " ",//space
                wp_strip_all_tags(get_the_title($post_id)));
            $content = preg_replace(
                "/[\n\r ]+/",//Also more than one space
                " ",//space
                wp_strip_all_tags(get_post_field('post_content', $post_id)));

            $data = $title . $content;
            $hash_string = hash('sha256', $data);
            $body['hash_string'] = $hash_string;

            $this->insert_hash_in_table($hash_string, $title, $content);

            $this->send_to_originstamp_api($body, $hash_string);
            $this->send_confirm_email($data, $hash_string);
        }

        private function send_to_originstamp_api($body, $hashString)
        {
            // Send computed hash value to OriginStamp.
            $options = self::get_options();
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

        private function send_confirm_email($data, $hash_string)
        {
            // Send confirmation Email to user.
            // I no Email address provided, nothing will be sent.
            $instructions = "Please store this Email. You need to hash following value with a SHA256:\n\n";
            $header = "================ START TEXT =================\n";
            $footer = "\n================ END TEXT ===================";
            $options = self::get_options();
            if (!$options['email']) {
                return '';
            }
            $msg = $instructions . $header . $data . $footer;
            $headers = "Content-type: text/plain";
            $temp = fopen('php://temp', 'w+');
            fwrite($temp, $data);
            rewind($temp);
            fpassthru($temp);
            $response = wp_mail($options['email'], "OriginStamp " . $hash_string, $msg, $headers, array($temp));
            fclose($temp);

            return $response;
        }

        private function get_hashes_for_api_key($offset, $records)
        {
            // Get hash table for API key.
            /*POST fields for table request.
             email
             hash_string
             comment
             date_created
             api_key
             offset
             records*/
            $options = self::get_options();
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

        public function dev_info()
        {
            ?>
            Visit us on <a href="https://app.originstamp.org/home">originstamp.org</a><br><br>
            Or contact us:<br>
            <table id="dev_info" style="display: inline-table;">
                <tr>
                    <td>
                        Thomas Hepp
                    </td>
                    <td>
                        <a target="_top" href="mailto:thomas.hepp@uni-konstanz.de?Subject=OriginStamp-wp%20plugin">contact</a>
                    </td>
                </tr>
                <tr>
                    <td>
                        André Gernandt
                    </td>
                    <td>
                        <a target="_top" href="mailto:andre.gernandt@gmail.com?Subject=OriginStamp-wp%20plugin">contact</a>
                    </td>
                </tr
                <tr>
                    <td>
                        Eugen Stroh
                    </td>
                    <td>
                        <a target="_top" href="mailto:eugen.stroh@uni-konstanz.de?Subject=OriginStamp-wp%20plugin">contact</a>
                    </td>
                </tr>
            </table>
            <?php
        }

        public function description()
        {
            ?>
            <p> This plugin saves and stores every single stage of your posts. Anytime you hit the save button while creating or
                editing a post, we will save the stage of your work in local data table. The timestamp is secure within the
                Bitcoin network and verifiable to anyone who is in possession of a coyp of the data.</p><br>
            <p><b>What content is saved?</b>
            <p class="description">We save the post title, the post body as plain text where all layout tags. Line breaks are
                converted to spaces.</p>
            <p><b>What content is timestamped?</b>
            <p class="description">We save the post title, the post body as plain text. To hash the post we concatenate the post
                title and the post body with a space in between. The sha256 of the generated string is being sent to originStamp
                to be timestamped.</p>
            <p><b>How to verify a timestamp?</b>';
            <p class="description">In order to verify the timestamp you would have to download the data, copy the string that is
                stored in the text file and then use any sha256 calculator of your choice to hash the string. After that go to
                OriginStamp and search for the hash. There you will also find further instructions and features.</p>
            <p><b>Where do I get more Information?</b></p>
            <p>Please visit <a target="_blank" href="https://app.ca1ee1698_originstamp.org/faq">OriginStamp FAQ.</a></p>
            <p><b>You still got questions?</b></p>
            <p>Fee free to contact us, our emails are provide in the develpoer information on the bottom of this page.</p>
            <?php
        }

        public function check_db_status()
        {
            global $wpdb;
            $options = self::get_options();
            $table_name = $wpdb->prefix . $options['db_table_name'];
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE '$table_name'", $table_name)) == $table_name) {
                echo '<p style="color: rgb(0, 150, 136)">Database table created: ' . $table_name . ' . </p>';
            } else {
                echo '<p style="color: rgb(255, 152, 0)">ERROR: Data table does not exist: ' . $table_name . '</p>';
            }
            echo '<p class="description">Here you can check status of the database that stores hashed post data.</p>';
        }

        public function api_key()
        {
            // Read in API key.
            $options = self::get_options();
            ?>
                <input title="API key" type="text" name="ca1ee1698_originstamp[api_key]" size="40" value="<?php echo $options['api_key'] ?>"/>
                <p class="description"><?php _e('An API key is required to create timestamps. Receive your personal key here:') ?>
                    <a href="https://ca1ee1698_originstamp.org/dev">
                        <i class="fa fa-sign-in" aria-hidden="true"></i>
                    </a></p>
                <input title="DB table name" type="hidden" name="ca1ee1698_originstamp[db_table_name]" value="<?php echo $options['db_table_name'] ?>"/>
            <?php
        }

        public function sender_email()
        {
            // Email address:
            $options = self::get_options();
            ?>
                <input title="Email" type="text" name="ca1ee1698_originstamp[email]" size="40" value="<?php echo $options['email'] ?>"/>
                <p class="description"><?php _e('Please provide an Email address so that we can send your data. You need to store your data to be able to verify it.') ?>
                <?php
        }

        public function db_table_name()
        {
            $options = self::get_options();
            ?>
                <input type="hidden" name="ca1ee1698_originstamp[db_table_name]" value="<?php echo $options['db_table_name'] ?>"/>
            <?php
        }

        private function parse_table($response_json_body)
        {
            echo '<table style="display: inline-table;">';
            echo '<tr><th>Date created</th><th>Hash string (SHA256)</th><th>Status</th><th>Data</th></tr>';
            foreach ($response_json_body->hashes as $hash) {
                // From milliseconds to seconds.
                $date_created = $hash->date_created / 1000;
                $submit_status = $hash->submit_status->multi_seed;
                $hash_string = $hash->hash_string;
                echo '<tr>';
                echo '<td>' . gmdate("Y-m-d H:i:s", $date_created) . '</td>';

                echo '<td>';
                echo '<a href="https://ca1ee1698_originstamp.org/s/'
                    . $hash_string
                    . '"'
                    . ' target="_blank"'
                    . '">'
                    . $hash_string
                    . '</a>';
                echo '</td>';
                echo '<td>';
                if ($submit_status == 3) {
                    try {
                        echo '<i style="color: rgb(0, 150, 136)" class="fa fa-check-circle-o" aria-hidden="true"></i>';
                    } catch (Exception $e) {
                        echo $submit_status;
                    }
                } else {
                    try {
                        echo '<i style="color: rgb(255, 152, 0)" class="fa fa-clock-o" aria-hidden="true"></i>';
                    } catch (Exception $e) {
                        echo $submit_status;
                    }
                }
                echo '</td>';
                echo '<td>';
                echo "<a href=\"?page=ca1ee1698_originstamp&d=$hash_string\" title=\"download\" target=\"_blank\">download</a>";
                echo '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }

        public function hashes_for_api_key()
        {
                // Maximum number of pages the API will return.
                $limit = 25;

                // Get first record, to determine, how many records there are overall.
                $get_page_info = $this->get_hashes_for_api_key(0, 1);
                if (!$get_page_info)
                    return;

                // Handle errors.
                if (is_wp_error($get_page_info)) {
                    $error_message = $get_page_info->get_error_message();
                    echo '<p style="color: rgb(255, 152, 0)">An error occurred while retrieving hash table:</p><br/>' . $error_message;
                    return;
                }

                // Extract bory from response.
                $page_info_json_obj = json_decode($get_page_info['body']);

                // Total number of records in the database.
                $total = $page_info_json_obj->total_records;

                // Overall number of pages
                $num_of_pages = ceil($page_info_json_obj->total_records / $limit);

                // GET current page
                $page = min($num_of_pages, filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT, array(
                    'options' => array(
                        'default' => 1,
                        'min_range' => 1,
                    ),
                )));
                // Calculate the offset for the query
                $offset = ($page - 1) * $limit;

                // Some information to display to the user
                $start = $offset + 1;
                $end = min(($offset + $limit), $total);

                // The "back" link
                $prevlink = ($page > 1) ? '<a href="?page=ca1ee1698_originstamp&p=1" title="First page">&laquo;</a> <a href="?page=ca1ee1698_originstamp' . '&p=' . ($page - 1) . '" title="Previous page">&lsaquo;</a>' : '<span class="disabled">&laquo;</span> <span class="disabled">&lsaquo;</span>';

                // The "forward" link
                $nextlink = ($page < $num_of_pages) ? '<a href="?page=ca1ee1698_originstamp' . '&p=' . ($page + 1) . '" title="Next page">&rsaquo;</a> <a href="?page=ca1ee1698_originstamp' . '&p=' . $num_of_pages . '" title="Last page">&raquo;</a>' : '<span class="disabled">&rsaquo;</span> <span class="disabled">&raquo;</span>';

                // Display the paging information
                echo '<p class="description">A list of all your hashes submitted with API key above: <br></p>';
                echo '<div id="paging"><p>', $prevlink, ' Page ', $page, ' of ', $num_of_pages, ' pages, displaying ', $start, '-', $end, ' of ', $total, ' results ', $nextlink, ' </p></div>';

                // Get data from API.
                $response = $this->get_hashes_for_api_key($offset, $limit);
                $response_json_body = json_decode($response['body']);

                // Parse response.
                $this->parse_table($response_json_body);

                return;
        }
    }
endif
    ?>
