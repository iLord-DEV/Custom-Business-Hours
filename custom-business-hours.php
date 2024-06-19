<?php

/**
 * Plugin Name: Custom Business Hours
 * Description: Benutzerdefiniert Öffnungszeiten
 * Version: 1.0
 * Author: Christoph Heim
 */
if (!defined('ABSPATH')) {
    exit;  // Exit if accessed directly
}

// Include ACF Pro if it's not already included
if (!class_exists('ACF')) {
    include_once plugin_dir_path(__FILE__) . 'acf/acf.php';
}

// Register ACF fields for business hours and special closing times
function custom_business_hours_acf_fields()
{
    if (function_exists('acf_add_local_field_group')):
        $days_of_week = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
        $fields = array();

        foreach ($days_of_week as $day) {
            $fields[] = array(
                'key' => 'field_' . $day . '_hours',
                'label' => ucfirst($day),
                'name' => $day . '_hours',
                'type' => 'text',
                'instructions' => 'Enter opening hours for ' . ucfirst($day) . ' (e.g., 09:00-17:00)',
                'conditional_logic' => array(
                    array(
                        array(
                            'field'    => 'field_' . $day . '_closed',
                            'operator' => '!=',
                            'value'    => '1',
                        ),
                    ),
                ),
            );
            $fields[] = array(
                'key'          => 'field_' . $day . '_closed',
                'label'        => ucfirst($day) . ' Closed',
                'name'         => $day . '_closed',
                'type'         => 'true_false',
                'instructions' => 'Check if ' . ucfirst($day) . ' is permanently closed',
                'ui'           => 1,
            );
        }

        $fields[] = array(
            'key' => 'field_special_closures',
            'label' => 'Special Closures',
            'name' => 'special_closures',
            'type' => 'repeater',
            'instructions' => 'Add special closure times',
            'sub_fields' => array(
                array(
                    'key'           => 'field_closure_start_date',
                    'label'         => 'Closure Start Date',
                    'name'          => 'closure_start_date',
                    'type'          => 'date_picker',
                    'return_format' => 'Ymd',
                ),
                array(
                    'key'           => 'field_closure_end_date',
                    'label'         => 'Closure End Date',
                    'name'          => 'closure_end_date',
                    'type'          => 'date_picker',
                    'return_format' => 'Ymd',
                ),
                array(
                    'key'          => 'field_closure_message',
                    'label'        => 'Message',
                    'name'         => 'closure_message',
                    'type'         => 'text',
                    'instructions' => 'Enter the closure message',
                ),
            ),
        );

        acf_add_local_field_group(array(
                                      'key' => 'group_1',
                                      'title' => 'Business Hours',
                                      'fields' => $fields,
                                      'location' => array(
                                          array(
                                              array(
                                                  'param'    => 'options_page',
                                                  'operator' => '==',
                                                  'value'    => 'acf-options-business-hours',
                                              ),
                                          ),
                                      ),
                                  ));
    endif;
}

add_action('acf/init', 'custom_business_hours_acf_fields');

// Add options page for business hours
if (function_exists('acf_add_options_page')) {
    acf_add_options_page(array(
                             'page_title' => 'Business Hours',
                             'menu_title' => 'Business Hours',
                             'menu_slug'  => 'acf-options-business-hours',
                             'capability' => 'edit_posts',
                             'redirect'   => false
                         ));
}

// Display current day's business hours and check for special closures
function display_current_business_hours()
{
    $today            = strtolower(date('l'));
    $is_closed        = get_field($today . '_closed', 'option');
    $current_hours    = get_field($today . '_hours', 'option');
    $special_closures = get_field('special_closures', 'option');
    $today_date       = date('Ymd');

    ob_start();

    if ($special_closures) {
        foreach ($special_closures as $closure) {
            if ($today_date >= $closure['closure_start_date'] && $today_date <= $closure['closure_end_date']) {
                echo '<p>' . $closure['closure_message'] . '</p>';
                return ob_get_clean();
            }
        }
    }

    if ($is_closed) {
        echo '<p>Today is closed.</p>';
    } else {
        echo "<p>Today's Hours: " . $current_hours . '</p>';
    }

    return ob_get_clean();
}

// Shortcode to display the business hours
add_shortcode('current_business_hours', 'display_current_business_hours');

// Add a page for the uninstall prompt
function custom_business_hours_add_uninstall_page()
{
    add_submenu_page(
        'plugins.php',
        'Uninstall Custom Business Hours',
        'Uninstall Custom Business Hours',
        'manage_options',
        'uninstall-custom-business-hours',
        'custom_business_hours_uninstall_page'
    );
}

add_action('admin_menu', 'custom_business_hours_add_uninstall_page');

function custom_business_hours_uninstall_page()
{
    ?>
    <div class="wrap">
        <h1>Uninstall Custom Business Hours</h1>
        <form method="post" action="">
            <?php wp_nonce_field('custom_business_hours_uninstall', 'custom_business_hours_uninstall_nonce'); ?>
            <p>
                <input type="radio" id="delete" name="custom_business_hours_uninstall_option" value="delete">
                <label for="delete">Delete all data</label>
            </p>
            <p>
                <input type="radio" id="keep" name="custom_business_hours_uninstall_option" value="keep" checked>
                <label for="keep">Keep all data</label>
            </p>
            <p>
                <input type="submit" class="button button-primary" value="Proceed with Uninstall">
            </p>
        </form>
    </div>
    <?php

    if (isset($_POST['custom_business_hours_uninstall_nonce']) && wp_verify_nonce($_POST['custom_business_hours_uninstall_nonce'], 'custom_business_hours_uninstall')) {
        $option = $_POST['custom_business_hours_uninstall_option'];
        update_option('custom_business_hours_uninstall_option', $option);
        deactivate_plugins(plugin_basename(__FILE__));
        wp_redirect(admin_url('plugins.php'));
        exit;
    }
}

// Function to delete ACF options when the plugin is uninstalled
function custom_business_hours_uninstall()
{
    $option = get_option('custom_business_hours_uninstall_option');

    if ($option === 'delete') {
        if (function_exists('acf_delete_field_group')) {
            acf_delete_field_group('group_1');
        }
        $days_of_week = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
        foreach ($days_of_week as $day) {
            delete_option('options_' . $day . '_hours');
            delete_option('options_' . $day . '_closed');
        }
        delete_option('options_special_closures');
    }

    delete_option('custom_business_hours_uninstall_option');
}

register_uninstall_hook(__FILE__, 'custom_business_hours_uninstall');
