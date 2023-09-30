<?php
/**
 * Plugin Name: Event Registration Validation
 * Description: Prevent multiple registrations for events.
 * Author: Ibrar Ayyub
 * Version: 1.0
 */

class EventRegistrationValidationPlugin {
    public function __construct() {
        // Register actions and filters
        add_action('wp_footer', array($this, 'preventMultipleRegistrationScriptFooter'));
        add_action('wp_enqueue_scripts', array($this, 'enqueueScripts'));
        add_action('wp_ajax_ee_prevent_multiple_ajax_action', array($this, 'preventMultipleAjaxFunction'));
        add_action('wp_ajax_nopriv_ee_prevent_multiple_ajax_action', array($this, 'preventMultipleAjaxFunction'));
    }

    public function preventMultipleRegistrationScriptFooter() {
        global $wp;
        if (str_contains(home_url($wp->request), "event-registration")) {
            $eventURL = explode('/', $_SERVER['HTTP_REFERER']);
            $eventslug = $eventURL[4];
            $post = get_page_by_path($eventslug, OBJECT, 'espresso_events');
            $eventId = $post->ID;
            ?>
            <script>
                (function ($) {
                    $(".ee-reg-qstn-email").on('keydown paste blur input', function () {
                        var email = $('.ee-reg-qstn-email').val();
                        if (isValidEmail(email)) {
                            $.ajax({
                                url: ee_prevent_multiple_reg_ajax_object.ee_prevent_multiple_reg_ajax_url,
                                type: 'POST',
                                data: {
                                    action: 'ee_prevent_multiple_ajax_action',
                                    email: email,
                                    eventID: '<?php echo $eventId; ?>'
                                },
                                success: function (response) {
                                    if (response) {
                                        if ($('#multiple_reg_error').length == 0) {
                                            $('<label id="multiple_reg_error" class="ee-required-text">This email is already registered in the same event.</label>').insertAfter(".ee-reg-qstn-email");
                                            $('#spco-go-to-step-payment_options-submit').css('pointer-events', 'none');
                                        }
                                    } else {
                                        $('#multiple_reg_error').remove();
                                        $('#spco-go-to-step-payment_options-submit').css('pointer-events', 'all');
                                    }
                                },
                                error: function () {
                                    console.log('AJAX request failed');
                                },
                            });
                        }
                    });

                    function isValidEmail(email) {
                        var emailPattern = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/;
                        return emailPattern.test(email);
                    }
                })(jQuery);
            </script>
            <?php
        }
    }

    public function enqueueScripts() {
        wp_localize_script('jquery', 'ee_prevent_multiple_reg_ajax_object', array(
            'ee_prevent_multiple_reg_ajax_url' => admin_url('admin-ajax.php'),
        ));
    }

    public function preventMultipleAjaxFunction() {
        $ATT_ID_DS = [];
        $ATT_emails = [];
        global $wpdb;
        $query = "SELECT ATT_ID FROM {$wpdb->prefix}esp_registration WHERE EVT_ID =" . $_POST['eventID'];

        // Execute the query
        $results = $wpdb->get_results($query);
        if (!is_null($results)) {
            foreach ($results as $result) {
                $ATT_ID_DS[] = $result->ATT_ID;
            }

            $placeholders = implode(', ', array_fill(0, count($ATT_ID_DS), '%s'));

            $query = $wpdb->prepare(
                "SELECT ATT_email FROM {$wpdb->prefix}esp_attendee_meta WHERE ATT_ID IN ($placeholders)",
                $ATT_ID_DS
            );
            $results = $wpdb->get_results($query);
            if (!is_null($results)) {
                foreach ($results as $result) {
                    $ATT_emails[] = $result->ATT_email;
                }
            }
        }
        if (in_array($_POST['email'], $ATT_emails)) {
            $response = 1;
        } else {
            $response = 0;
        }

        wp_send_json($response);
    }
}

$eventRegistrationValidationPlugin = new EventRegistrationValidationPlugin();
