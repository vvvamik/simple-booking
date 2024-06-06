<?php
/*
Plugin Name: Simple Booking
Description: A simple booking plugin for WordPress.
Version: 1.0
Author: vvvamik
*/

// Aktivace pluginu
function simple_booking_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . "bookings";
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        arrival_date date NOT NULL,
        departure_date date NOT NULL,
        name tinytext NOT NULL,
        email varchar(100) NOT NULL,
        phone varchar(15) DEFAULT '' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'simple_booking_install');

// Skripty a styly
function simple_booking_enqueue_scripts() {
    wp_enqueue_style('jquery-ui-css', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css');
    wp_enqueue_style('simple-booking-style', plugin_dir_url(__FILE__) . 'css/style.css');
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_script('simple-booking-script', plugin_dir_url(__FILE__) . 'js/script.js', array('jquery', 'jquery-ui-datepicker'), null, true);
    wp_localize_script('simple-booking-script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts', 'simple_booking_enqueue_scripts');

// Shortcode pro rezervační formulář
function simple_booking_form() {
    ob_start();
    ?>
    <form id="booking-form" method="post">
        <label for="arrival_date">Datum příjezdu:</label>
        <input type="text" id="arrival_date" name="arrival_date" required>

        <label for="departure_date">Datum odjezdu:</label>
        <input type="text" id="departure_date" name="departure_date" required>

        <label for="name">Jméno:</label>
        <input type="text" id="name" name="name" required>

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>

        <label for="phone">Telefon:</label>
        <input type="text" id="phone" name="phone">

        <input type="submit" value="Rezervovat">
        <div id="booking-result"></div>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('simple_booking_form', 'simple_booking_form');

// Ajax pro kontrolu dostupnosti a uložení rezervace
function simple_booking_ajax_handler() {
    global $wpdb;
    $table_name = $wpdb->prefix . "bookings";

    $arrival_date = sanitize_text_field($_POST['arrival_date']);
    $departure_date = sanitize_text_field($_POST['departure_date']);
    $name = sanitize_text_field($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $phone = sanitize_text_field($_POST['phone']);

    // Kontrola dostupnosti
    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE (arrival_date <= %s AND departure_date >= %s) OR (arrival_date <= %s AND departure_date >= %s)",
        $departure_date, $arrival_date, $departure_date, $arrival_date
    ));

    if (count($bookings) > 0) {
        echo 'Termín je obsazený. Vyberte prosím jiný termín.';
    } else {
        // Uložení rezervace
        $wpdb->insert($table_name, array(
            'arrival_date' => $arrival_date,
            'departure_date' => $departure_date,
            'name' => $name,
            'email' => $email,
            'phone' => $phone
        ));

        // Odeslání emailu uživateli
        $user_subject = 'Potvrzení rezervace';
        $user_message = "Dobrý den $name,\n\n" .
                        "Vaše rezervace byla potvrzena.\n\n" .
                        "Podrobnosti o rezervaci:\n" .
                        "Datum příjezdu: $arrival_date\n" .
                        "Datum odjezdu: $departure_date\n" .
                        "Jméno: $name\n" .
                        "Email: $email\n" .
                        "Telefon: $phone\n\n" .
                        "Děkujeme za vaši rezervaci.";

        wp_mail($email, $user_subject, $user_message);

        // Odeslání emailu administrátorovi
        $admin_email = get_option('admin_email');
        $admin_subject = 'Nová rezervace';
        $admin_message = "Byla vytvořena nová rezervace.\n\n" .
                         "Datum příjezdu: $arrival_date\n" .
                         "Datum odjezdu: $departure_date\n" .
                         "Jméno: $name\n" .
                         "Email: $email\n" .
                         "Telefon: $phone";

        wp_mail($admin_email, $admin_subject, $admin_message);

        echo 'Rezervace byla úspěšně vytvořena.';
    }

    wp_die();
}
add_action('wp_ajax_nopriv_simple_booking', 'simple_booking_ajax_handler');
add_action('wp_ajax_simple_booking', 'simple_booking_ajax_handler');

// Ajax pro načtení obsazených termínů
function simple_booking_get_booked_dates() {
    global $wpdb;
    $table_name = $wpdb->prefix . "bookings";
    
    $bookings = $wpdb->get_results("SELECT arrival_date, departure_date FROM $table_name");

    $booked_dates = array();

    foreach ($bookings as $booking) {
        $current_date = $booking->arrival_date;
        while (strtotime($current_date) <= strtotime($booking->departure_date)) {
            $booked_dates[] = $current_date;
            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
        }
    }

    echo json_encode($booked_dates);
    wp_die();
}
add_action('wp_ajax_nopriv_simple_booking_get_booked_dates', 'simple_booking_get_booked_dates');
add_action('wp_ajax_simple_booking_get_booked_dates', 'simple_booking_get_booked_dates');
?>
