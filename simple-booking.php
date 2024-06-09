<?php
/*
Plugin Name: Simple Booking
Description: A simple booking plugin for WordPress.
Version: 1.2
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
        $user_message = "
        <html>
        <head>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 0;
                background-color: #f4f4f4;
            }

            .email-container {
                max-width: 600px;
                margin: 20px auto;
                background-color: #ffffff;
                border: 1px solid #dddddd;
                border-radius: 8px;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                overflow: hidden;
            }

            .email-header {
                background-color: #4CAF50;
                color: #ffffff;
                padding: 20px;
                text-align: center;
            }

            .email-header h1 {
                margin: 0;
            }

            .email-body {
                padding: 20px;
            }

            .email-body p {
                font-size: 16px;
                line-height: 1.5;
                color: #333333;
            }

            .email-footer {
                background-color: #f4f4f4;
                padding: 10px;
                text-align: center;
                color: #888888;
                font-size: 12px;
            }

            .button {
                display: inline-block;
                padding: 10px 20px;
                margin: 10px 0;
                background-color: #4CAF50;
                color: #ffffff;
                text-decoration: none;
                border-radius: 5px;
            }

            .button:hover {
                background-color: #45a049;
            }
        </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='email-header'>
                    <h1>Potvrzení rezervace</h1>
                </div>
                <div class='email-body'>
                    <p>Dobrý den $name,</p>
                    <p>Vaše rezervace byla přijata.</p>
                    <p>Podrobnosti o rezervaci:</p>
                    <p><strong>Datum příjezdu:</strong> $arrival_date</p>
                    <p><strong>Datum odjezdu:</strong> $departure_date</p>
                    <p><strong>Jméno:</strong> $name</p>
                    <p><strong>Email:</strong> $email</p>
                    <p><strong>Telefon:</strong> $phone</p>
                    <p>Děkujeme za vaši rezervaci a brzy se vám ozveme.</p>
                </div>
                <div class='email-footer'>
                    <p>Tento e-mail byl vygenerován automaticky, prosím neodpovídejte na něj.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        $headers = array('Content-Type: text/html; charset=UTF-8');

        wp_mail($email, $user_subject, $user_message, $headers);

        // Odeslání emailu administrátorovi
        $admin_email = get_option('admin_email');
        $admin_subject = 'Nová rezervace';
        $admin_message = "
        <html>
        <head>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 0;
                background-color: #f4f4f4;
            }

            .email-container {
                max-width: 600px;
                margin: 20px auto;
                background-color: #ffffff;
                border: 1px solid #dddddd;
                border-radius: 8px;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                overflow: hidden;
            }

            .email-header {
                background-color: #4CAF50;
                color: #ffffff;
                padding: 20px;
                text-align: center;
            }

            .email-header h1 {
                margin: 0;
            }

            .email-body {
                padding: 20px;
            }

            .email-body p {
                font-size: 16px;
                line-height: 1.5;
                color: #333333;
            }

            .email-footer {
                background-color: #f4f4f4;
                padding: 10px;
                text-align: center;
                color: #888888;
                font-size: 12px;
            }

            .button {
                display: inline-block;
                padding: 10px 20px;
                margin: 10px 0;
                background-color: #4CAF50;
                color: #ffffff;
                text-decoration: none;
                border-radius: 5px;
            }

            .button:hover {
                background-color: #45a049;
            }
        </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='email-header'>
                    <h1>Nová rezervace</h1>
                </div>
                <div class='email-body'>
                    <p>Byla vytvořena nová rezervace.</p>
                    <p><strong>Datum příjezdu:</strong> $arrival_date</p>
                    <p><strong>Datum odjezdu:</strong> $departure_date</p>
                    <p><strong>Jméno:</strong> $name</p>
                    <p><strong>Email:</strong> $email</p>
                    <p><strong>Telefon:</strong> $phone</p>
                </div>
                <div class='email-footer'>
                    <p>Tento e-mail byl vygenerován automaticky, prosím neodpovídejte na něj.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        wp_mail($admin_email, $admin_subject, $admin_message, $headers);

        echo 'Rezervace byla úspěšně vytvořena.<br>Brzy se vám ozveme. Děkujeme.';
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

// Přidání administrátorského menu
function simple_booking_admin_menu() {
    add_menu_page(
        'Rezervace',
        'Rezervace',
        'manage_options',
        'simple-booking',
        'simple_booking_admin_page',
        'dashicons-calendar',
        6
    );
}
add_action('admin_menu', 'simple_booking_admin_menu');

// Funkce pro zobrazení stránky s rezervacemi
function simple_booking_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . "bookings";
    $bookings = $wpdb->get_results("SELECT * FROM $table_name");

    echo '<div class="wrap">';
    echo '<h1>Seznam rezervací</h1>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>ID</th><th>Datum příjezdu</th><th>Datum odjezdu</th><th>Jméno</th><th>Email</th><th>Telefon</th><th>Akce</th></tr></thead>';
    echo '<tbody>';
    foreach ($bookings as $booking) {
        echo '<tr>';
        echo '<td>' . esc_html($booking->id) . '</td>';
        echo '<td>' . esc_html($booking->arrival_date) . '</td>';
        echo '<td>' . esc_html($booking->departure_date) . '</td>';
        echo '<td>' . esc_html($booking->name) . '</td>';
        echo '<td>' . esc_html($booking->email) . '</td>';
        echo '<td>' . esc_html($booking->phone) . '</td>';
        echo '<td><a href="' . admin_url('admin.php?page=simple-booking&action=edit&id=' . $booking->id) . '">Editovat</a> | <a href="' . wp_nonce_url(admin_url('admin.php?page=simple-booking&action=delete&id=' . $booking->id), 'simple_booking_delete_' . $booking->id) . '">Smazat</a></td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    echo '</div>';

    if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
        simple_booking_edit_booking(intval($_GET['id']));
    }
}

// Funkce pro zobrazení a zpracování formuláře pro editaci rezervace
function simple_booking_edit_booking($id) {
    global $wpdb;
    $table_name = $wpdb->prefix . "bookings";
    $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));

    if (!$booking) {
        echo '<div class="error"><p>Rezervace nenalezena.</p></div>';
        return;
    }

    if (isset($_POST['simple_booking_update'])) {
        $arrival_date = sanitize_text_field($_POST['arrival_date']);
        $departure_date = sanitize_text_field($_POST['departure_date']);
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);

        $wpdb->update(
            $table_name,
            array(
                'arrival_date' => $arrival_date,
                'departure_date' => $departure_date,
                'name' => $name,
                'email' => $email,
                'phone' => $phone
            ),
            array('id' => $id)
        );

        echo '<div class="updated"><p>Rezervace byla aktualizována.</p></div>';
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    }

    ?>
    <div class="wrap">
        <h1>Editace rezervace</h1>
        <form method="post">
            <label for="arrival_date">Datum příjezdu:</label>
            <input type="text" id="arrival_date" name="arrival_date" value="<?php echo esc_attr($booking->arrival_date); ?>" required>

            <label for="departure_date">Datum odjezdu:</label>
            <input type="text" id="departure_date" name="departure_date" value="<?php echo esc_attr($booking->departure_date); ?>" required>

            <label for="name">Jméno:</label>
            <input type="text" id="name" name="name" value="<?php echo esc_attr($booking->name); ?>" required>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo esc_attr($booking->email); ?>" required>

            <label for="phone">Telefon:</label>
            <input type="text" id="phone" name="phone" value="<?php echo esc_attr($booking->phone); ?>">

            <input type="submit" name="simple_booking_update" value="Aktualizovat">
        </form>
    </div>
    <?php
}

// Funkce pro zpracování mazání rezervace
function simple_booking_delete_booking() {
    if (!isset($_GET['id']) || !isset($_GET['_wpnonce'])) {
        return;
    }

    $id = intval($_GET['id']);
    if (!wp_verify_nonce($_GET['_wpnonce'], 'simple_booking_delete_' . $id)) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . "bookings";
    $wpdb->delete($table_name, array('id' => $id));

    wp_redirect(admin_url('admin.php?page=simple-booking'));
    exit;
}
add_action('admin_init', 'simple_booking_delete_booking');
?>
