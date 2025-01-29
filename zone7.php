<?php
/**
 * Plugin Name: Custom Profile Form
 * Plugin URI:  https://github.com/armondal/custom-profile-form
 * Description: A custom WordPress plugin to gather user profile info via a front-end form, storing results in the WP database and displaying them in the admin.
 * Version:     1.0
 * Author:      Arnab
 * License:     GPL2
 * Text Domain: custom-profile-form
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Load plugin text domain for translations
 */
add_action('plugins_loaded', 'cpf_load_textdomain');
function cpf_load_textdomain() {
    load_plugin_textdomain('custom-profile-form', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
/**
 * 1. PLUGIN ACTIVATION: Create custom DB table on activation
 */
register_activation_hook( __FILE__, 'cpf_create_table' );
function cpf_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cpf_submissions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        first_name varchar(100) NOT NULL,
        last_name varchar(100) NOT NULL,
        email varchar(200) NOT NULL,
        phone varchar(50) NOT NULL,
        birth_date varchar(20) NOT NULL,
        gender varchar(10) NOT NULL,
        address_line1 varchar(255) NOT NULL,
        address_line2 varchar(255) NOT NULL,
        city varchar(100) NOT NULL,
        country varchar(100) NOT NULL,
        bio text NOT NULL,
        accepted_terms tinyint(1) NOT NULL,
        submitted_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

/**
 * 2. ENQUEUE PLUGIN SCRIPTS & STYLES
 *    – We’ll enqueue a small JS for AJAX handling.
 */
add_action( 'wp_enqueue_scripts', 'cpf_enqueue_assets' );
function cpf_enqueue_assets() {
    // Only load on pages where the shortcode [custom_profile_form] is used
    // For simplicity, we load always. (You can optimize in production.)
    // Enqueue Google Forms Iframe styles
    wp_register_style('google-forms-style', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    wp_enqueue_style('google-forms-style');
    wp_enqueue_style( 'cpf-styles', plugin_dir_url(__FILE__) . 'css/cpf-styles.css' );

    wp_enqueue_script( 'cpf-script', plugin_dir_url(__FILE__) . 'js/cpf-script.js', array('jquery'), '1.0', true );

    // Set up AJAX object so our JS can do AJAX requests
    wp_localize_script( 'cpf-script', 'cpf_ajax_obj', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'cpf_ajax_nonce' ),
    ) );
}

/**
 * 3. CREATE THE SHORTCODE FOR THE FORM
 *    – This outputs the HTML form, which we’ll submit via AJAX or fallback POST.
 */
add_shortcode( 'custom_profile_form', 'cpf_render_form' );
function cpf_render_form() {
    // The form's HTML
    ob_start(); 
    ?>
    <div class="form-wrapper">
        <form id="custom-profile-form" method="post" action="">
        <!-- Full Name -->
        <div class="form-row">
            <label><?php _e('Full Name*', 'custom-profile-form'); ?></label>
            <div class="two-col">
                <div class="col">
                    <input type="text" id="first_name" name="first_name" placeholder="<?php esc_attr_e('First Name', 'custom-profile-form'); ?>" required>
                </div>
                <div class="col">
                    <input type="text" id="last_name" name="last_name" placeholder="<?php esc_attr_e('Last Name', 'custom-profile-form'); ?>" required>
                </div>
            </div>
        </div>
        <div class="form-row two-col">
            <!-- Email -->
            <div class="col">
                <label><?php _e('Email*', 'custom-profile-form'); ?></label>
                <input type="email" name="email" placeholder="<?php esc_attr_e('example@example.com', 'custom-profile-form'); ?>" required />
            </div>
            <!-- Phone -->
            <div class="col">
                <label><?php _e('Phone Number', 'custom-profile-form'); ?></label>
                <input type="text" name="phone" placeholder="<?php esc_attr_e('(000) 000-0000', 'custom-profile-form'); ?>" />
            </div>
        </div>

        <div class="form-row two-col">
            <!-- Birth -->
            <div class="col">
                <label><?php _e('Birth Date', 'custom-profile-form'); ?></label>
                <input type="text" name="birth_date" placeholder="<?php esc_attr_e('MM-DD-YYYY', 'custom-profile-form'); ?>" />
            </div>
            <!-- Gender -->
            <div class="col">
                <label><?php _e('Gender*', 'custom-profile-form'); ?></label>
                <div class="gender">
                    <label class="radio-item">
                        <input type="radio" name="gender" value="Male" required /> <?php _e('Male', 'custom-profile-form'); ?>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="gender" value="Female" required /> <?php _e('Female', 'custom-profile-form'); ?>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="gender" value="Others" required /> <?php _e('Others', 'custom-profile-form'); ?>
                    </label>
                </div>
            </div>
        </div>

        <!-- Address -->
        <div class="form-row one-col mb-24">
            <label><?php _e('Address*', 'custom-profile-form'); ?></label>
            <input type="text" name="address_line1" placeholder="<?php esc_attr_e('Street Address', 'custom-profile-form'); ?>" required />
            <input type="text" name="address_line2" placeholder="<?php esc_attr_e('Street Address line2', 'custom-profile-form'); ?>" />
        </div>

        <div class="form-row two-col ">
            <!-- City -->
            <div class="col">
                <input type="text" name="city" placeholder="<?php esc_attr_e('City', 'custom-profile-form'); ?>" />
            </div>
            <!-- Country -->
            <div class="col">
                <select name="country">
                    <option value=""><?php _e('Select Country', 'custom-profile-form'); ?></option>
                    <option value="USA"><?php _e('USA', 'custom-profile-form'); ?></option>
                    <option value="UK"><?php _e('UK', 'custom-profile-form'); ?></option>
                    <option value="Canada"><?php _e('Canada', 'custom-profile-form'); ?></option>
                </select>
            </div>
        </div>

        <!-- Short Bio -->
        <div class="form-row one-col mb-24">
            <label><?php _e('Short bio or description of your interests', 'custom-profile-form'); ?></label>
            <textarea name="bio" placeholder="<?php esc_attr_e('Write description...', 'custom-profile-form'); ?>"></textarea>
        </div>
        <div class="form-row one-col mb-24">
            <span class="terms"><?php _e('Terms', 'custom-profile-form'); ?></span>
        </div>
        <!-- Terms -->
        <div class="form-row terms-container">
            <input type="checkbox" id="terms-checkbox" name="terms" required style="vertical-align: middle;" />
            <label for="terms-checkbox" style="vertical-align: middle; margin-left: 4px;">
                <?php _e('I agree to the', 'custom-profile-form'); ?> 
                <a href="#" target="_blank"><?php _e('terms and conditions', 'custom-profile-form'); ?></a>.
            </label>
        </div>

        <!-- Submit -->
        <div class="form-row">
            <button type="submit" id="cpf-submit-btn"><?php _e('Submit form', 'custom-profile-form'); ?></button>
        </div>
        </form>
    </div>

    <div id="cpf-result"></div>
    <?php
    return ob_get_clean();
}

/**
 * 4. AJAX HANDLER FOR FORM SUBMISSION (FRONTEND)
 *    – This function processes the form data and saves it to the DB.
 */
add_action( 'wp_ajax_cpf_submit_form', 'cpf_submit_form' );
add_action( 'wp_ajax_nopriv_cpf_submit_form', 'cpf_submit_form' );
function cpf_submit_form() {
    check_ajax_referer( 'cpf_ajax_nonce', 'nonce' );

    // Sanitize and gather form data
    $first_name    = sanitize_text_field( $_POST['first_name'] ?? '' );
    $last_name     = sanitize_text_field( $_POST['last_name'] ?? '' );
    $email         = sanitize_email( $_POST['email'] ?? '' );
    $phone         = sanitize_text_field( $_POST['phone'] ?? '' );
    $birth_date    = sanitize_text_field( $_POST['birth_date'] ?? '' );
    $gender        = sanitize_text_field( $_POST['gender'] ?? '' );
    $address_line1 = sanitize_text_field( $_POST['address_line1'] ?? '' );
    $address_line2 = sanitize_text_field( $_POST['address_line2'] ?? '' );
    $city          = sanitize_text_field( $_POST['city'] ?? '' );
    $country       = sanitize_text_field( $_POST['country'] ?? '' );
    $bio           = sanitize_textarea_field( $_POST['bio'] ?? '' );
    $terms         = isset( $_POST['terms'] ) ? 1 : 0;

    // Validate required fields
    if ( empty($first_name) || empty($last_name) || empty($email) || empty($gender) || empty($address_line1) ) {
        wp_send_json_error( array( 'message' => 'Please fill all required fields.' ) );
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'cpf_submissions';
    $data_inserted = $wpdb->insert(
        $table_name,
        array(
            'first_name'     => $first_name,
            'last_name'      => $last_name,
            'email'          => $email,
            'phone'          => $phone,
            'birth_date'     => $birth_date,
            'gender'         => $gender,
            'address_line1'  => $address_line1,
            'address_line2'  => $address_line2,
            'city'           => $city,
            'country'        => $country,
            'bio'            => $bio,
            'accepted_terms' => $terms,
        )
    );

    if ( $data_inserted ) {
        wp_send_json_success( array( 'message' => 'Form submitted successfully!' ) );
    } else {
        wp_send_json_error( array( 'message' => 'An error occurred. Please try again.' ) );
    }
}

/**
 * 5. ADMIN MENU & PAGE TO DISPLAY FORM SUBMISSIONS
 */
add_action( 'admin_menu', 'cpf_create_admin_menu' );
function cpf_create_admin_menu() {
    add_menu_page(
        'CPF Submissions',
        'CPF Submissions',
        'manage_options',
        'cpf-submissions',
        'cpf_render_submissions_page',
        'dashicons-feedback',
        26
    );
}

function cpf_render_submissions_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'cpf_submissions';
    $results = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY submitted_at DESC" );

    echo '<div class="wrap"><h1>Custom Profile Form Submissions</h1>';
    if ( $results ) {
        echo '<table class="widefat fixed" cellspacing="0">';
        echo '<thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Birth Date</th><th>Gender</th><th>Submitted</th></tr></thead><tbody>';
        foreach ( $results as $row ) {
            echo '<tr>';
            echo '<td>' . esc_html( $row->id ) . '</td>';
            echo '<td>' . esc_html( $row->first_name . ' ' . $row->last_name ) . '</td>';
            echo '<td>' . esc_html( $row->email ) . '</td>';
            echo '<td>' . esc_html( $row->phone ) . '</td>';
            echo '<td>' . esc_html( $row->birth_date ) . '</td>';
            echo '<td>' . esc_html( $row->gender ) . '</td>';
            echo '<td>' . esc_html( $row->submitted_at ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No submissions found.</p>';
    }
    echo '</div>';
}
