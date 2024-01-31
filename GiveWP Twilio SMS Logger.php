<?php
/*
Plugin Name: GiveWP Twilio SMS Logger
Description: Connect GiveWP with Twilio for SMS donations notifications and log the messages.
Version: 1.0
Author: Abacies
*/

// Include the Twilio PHP library
require_once(plugin_dir_path(__FILE__) . 'twilio-php-main/src/Twilio/autoload.php');

// Define your Twilio credentials
define('TWILIO_ACCOUNT_SID', 'your_twilio_account_sid');
define('TWILIO_AUTH_TOKEN', 'your_twilio_auth_token');
define('TWILIO_PHONE_NUMBER', 'your_twilio_phone_number');

// Enqueue scripts only on GiveWP pages
function give_twilio_sms_enqueue_scripts() {
    if (is_singular('give_forms')) {
        wp_enqueue_script('give-twilio-sms', plugin_dir_url(__FILE__) . 'assets/js/GiveWP Twilio SMS Logger.js', array('jquery'), '1.0', true);
    }
}
add_action('wp_enqueue_scripts', 'give_twilio_sms_enqueue_scripts');

// Hook into GiveWP donation complete event
function give_twilio_sms_send_notification($payment_id) {
    // Get donation details
    $payment_data = give_get_payment_meta($payment_id);

    // Get donor information
    $donor_name = $payment_data['user_info']['first_name'] . ' ' . $payment_data['user_info']['last_name'];
    $donor_phone = $payment_data['user_info']['phone'];

    // Customize your SMS message
    $sms_message = "Thank you, $donor_name, for your donation of $" . $payment_data['price'] . " to our cause!";

    // Send SMS using Twilio
    send_sms_via_twilio($donor_phone, $sms_message);
}
add_action('give_payment_complete', 'give_twilio_sms_send_notification');

// Function to send SMS via Twilio and log the messages
function send_sms_via_twilio($to, $body) {
    $twilio_mode = 'test';  // Set this to 'live' for production

    // Use test credentials in test mode
    if ($twilio_mode == 'test') {
        // Your test credentials here...
    } else {
        // Use live credentials in live mode
        $account_sid = get_option('twilio_account_sid');
        $auth_token = get_option('twilio_auth_token');
        $twilio_number = get_option('twilio_phone_number');
    }

    $twilio = new Twilio\Rest\Client($account_sid, $auth_token);

    // Send the SMS
    try {
        $twilio->messages->create(
            $to,
            [
                'from' => $twilio_number,
                'body' => $body,
            ]
        );

        // Log successful message sending
        insert_db_data($body, $twilio_mode, false);
    } catch (Exception $e) {
        // Log failure
        insert_db_data($body, $twilio_mode, true);
    }
}

// Register activation hook for creating database table
register_activation_hook(__FILE__, 'give_twilio_sms_activation');

// Add AJAX action for log deletion
add_action('wp_ajax_delete_twilio_sms_log', 'delete_twilio_sms_log');

// Function to create database table on activation
function give_twilio_sms_activation() {
    global $wpdb;
    $db_table_name = 'give_twilio_sms_log';  // table name
    $charset_collate = $wpdb->get_charset_collate();

    // Check to see if the table exists already, if not, then create it
    if ($wpdb->get_var("show tables like '$db_table_name'") != $db_table_name) {
        $sql = "CREATE TABLE $db_table_name (
            `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
            `log` text NOT NULL,
            `mode` enum('Test','Live') NOT NULL,
            `date` datetime DEFAULT NULL,
            `status` varchar(50)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Function to insert log data into the database
function insert_db_data($data, $mode, $error) {
    global $wpdb;
    $today = date("Y-m-d H:i:s");
    $status_message = $error ? 'Failure' : 'Success';

    if ($mode == 'test') {
        $mode = 'Test';
    } else {
        $mode = 'Live';
    }

    $wpdb->insert('give_twilio_sms_log', [
        'log' => $data,
        'mode' => $mode,
        'date' => $today,
        'status' => $status_message,
    ]);
}

// Function to delete log entry via AJAX
function delete_twilio_sms_log() {
    global $wpdb;
    $id = $_POST['id'];
    $deleted = $wpdb->delete('give_twilio_sms_log', ['id' => $id]);

    if ($deleted == false) {
        $response = ["status" => 401, "message" => "Error on deletion"];
    } else {
        $response = ["status" => 200, "message" => "Successfully Deleted"];
    }

    echo json_encode($response);
    wp_die();
}

// Add admin menu item
function give_twilio_sms_admin_menu() {
    add_menu_page(
        'Twilio SMS Log',
        'Twilio SMS Log',
        'manage_options',
        'give_twilio_sms_log',
        'give_twilio_sms_log_page'
    );
}
add_action('admin_menu', 'give_twilio_sms_admin_menu');

// Log page content
function give_twilio_sms_log_page() {
    ?>
    <div class="wrap">
        <h2>Twilio SMS Log</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
            <tr>
                <th>ID</th>
                <th>Log Message</th>
                <th>Mode</th>
                <th>Date</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php
            global $wpdb;
            $logs = $wpdb->get_results("SELECT * FROM give_twilio_sms_log");

            foreach ($logs as $log) {
                echo '<tr>';
                echo '<td>' . $log->id . '</td>';
                echo '<td>' . $log->log . '</td>';
                echo '<td>' . $log->mode . '</td>';
                echo '<td>' . $log->date . '</td>';
                echo '<td>' . $log->status . '</td>';
                echo '<td><button class="delete-log" data-id="' . $log->id . '">Delete</button></td>';
                echo '</tr>';
            }
            ?>
            </tbody>
        </table>
    </div>
    
    <?php
}
?>
