<?php
/*
Plugin Name: ARYO Activity Log Exporter
Plugin URI: http://wordpress.org/plugins/aryo-exporter/
Description: Adds the ability to export logs from ARYO Activity Log
Author: Rokusek Design
Author URI: http://www.rokusek.com
Version: 0.1
License: MIT
*/

AryoExport::init();

if(isset($_POST['aryo-export-range']))
{
    AryoExport::doExport();
}

class AryoExport {

    /**
     * Triggers a check to see if ARYO is active or not
     *
     * @return void
     */
    public static function init()
    {
        add_action('admin_menu', array(__CLASS__, 'registerExportSubmenuPage'), 60);
    }

    /**
     * Register's the export menu
     *
     * @return void
     */
    public static function registerExportSubmenuPage()
    {
        // Make sure the plugin is active before continuing
        if(!is_plugin_active('aryo-activity-log/aryo-activity-log.php'))
        {
            return;
        }

        // Register admin page
        add_submenu_page('activity_log_page', 'Export', 'Export', 'manage_options', 'aryo-export', array(__CLASS__, 'displayExportPage'));

        // Add jQuery UI calendar
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('aryo-export-jquery-ui-css', plugins_url('assets/jquery-ui-smoothness/jquery-ui-1.10.4.custom.min.css', __FILE__));
    }

    /**
     * Displays the export page
     *
     * @return void
     */
    public static function displayExportPage()
    {
    ?>
    <div class="wrap">
        <h2>Export</h2>
        <p>Choose from the settings below to export Activity Log data.</p>
        <form method="post">
            <div class="aryo-export-range-radio">
                <p><strong>Export Date Range:</strong></p>
                <label for="aryo-export-range-today"><input type="radio" name="aryo-export-range" id="aryo-export-range-today" value="today"> Today</label>
                <br>
                <label for="aryo-export-range-week"><input type="radio" name="aryo-export-range" id="aryo-export-range-week" value="week"> This Week</label>
                <br>
                <label for="aryo-export-range-all"><input type="radio" name="aryo-export-range" id="aryo-export-range-all" value="all"> Forever (may take a while)</label>
                <br>
                <label for="aryo-export-range-custom"><input type="radio" name="aryo-export-range" id="aryo-export-range-custom" value="custom"> Custom Range</label>
            </div><!-- /.aryo-exporter-range-radio -->
            <div class="aryo-export-range-select" style="display: none">
                <br>
                <label for="aryo-export-date-from" class="aryo-export-date-label">From: </label>
                <input type="text" class="aryo-export-date-input" name="aryo-export-date-from" id="aryo-export-date-from">
                <br>
                <label for="aryo-export-date-to" class="aryo-export-date-label">To: </label>
                <input type="text" class="aryo-export-date-input" name="aryo-export-date-to" id="aryo-export-date-to">
            </div><!-- /.aryo-exporter-range-select -->
            <br>
            <button type="submit" class="button button-primary button-large">Export</button>
        </form>
    </div><!-- /.wrap -->
    <style>
    .aryo-export-date-label {
        width: 40px;
        display: inline-block;
    }
    .aryo-export-date-input {
        display: inline-block;
        width: 100px;
    }
    </style>
    <script>
    jQuery(document).ready(function($)
    {
        // Add calendar pickers
        $('#aryo-export-date-from').datepicker({
            defaultDate: "-1w",
            changeMonth: true,
            onClose: function(selectedDate)
            {
                $('#aryo-export-date-to').datepicker('option', 'minDate', selectedDate);
            }
        });

        $('#aryo-export-date-to').datepicker({
            changeMonth: true,
            maxDate: 0,
            onClose: function(selectedDate)
            {
                $('#aryo-export-date-from').datepicker('option', 'maxDate', selectedDate);
            }
        });

        // Range change bindings
        $('input[name=aryo-export-range]').change(function()
        {
            if($('#aryo-export-range-custom').is(':checked'))
            {
                $('.aryo-export-range-select').show();
            }
            else
            {
                $('.aryo-export-range-select').hide();
            }
        });
    });
    </script>
    <?php
    }

    /**
     * Generate & download a CSV
     *
     * @return void
     */
    public static function doExport()
    {
        global $wpdb;

        switch($_POST['aryo-export-range'])
        {
            case 'today':
                $start = new DateTime;
                $start->sub(new DateInterval('P1D'));
                $end = new DateTime;
                break;
            case 'week';
                $start = new DateTime;
                $start->sub(new DateInterval('P1W'));
                $end = new DateTime;
                break;
            case 'all':
                $start = new DateTime('0000-00-00');
                $end = new DateTime;
                break;
            case 'custom':
                $start = new DateTime($_POST['aryo-export-date-from']);
                $end = new DateTime($_POST['aryo-export-date-to']);
                break;
        }

        $start->setTime(0, 0, 0);
        $end->setTime(23, 59, 59);

        $entries = $wpdb->get_results($wpdb->prepare("
            SELECT FROM_UNIXTIME(`hist_time`) AS `timestamp`,
                   CONCAT(`user_nicename`, ' (', `user_id`, ')') AS `person`,
                   `hist_ip` AS `ip_address`,
                   CONCAT_WS(' ',
                             IF(LENGTH(`action`), `action`, NULL),
                             IF(LENGTH(`object_type`), `object_type`, NULL),
                             IF(LENGTH(`object_subtype`), `object_subtype`, NULL),
                             IF(LENGTH(`object_name`), `object_name`, NULL),
                             CONCAT('(', `object_id`, ')')
                            ) AS `entry`
              FROM $wpdb->activity_log
         LEFT JOIN $wpdb->users
                ON `$wpdb->activity_log`.`user_id` = `$wpdb->users`.`ID`
             WHERE `hist_time` > %d
               AND `hist_time` < %d
        ", $start->getTimestamp(), $end->getTimestamp()));

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename=aryo_export.csv');
        $csv = fopen('php://output', 'w');

        $headers = false;
        foreach($entries as $entry)
        {
            if($headers == false)
            {
                $columnHeaders = array_keys(get_object_vars($entry));
                fputcsv($csv, $columnHeaders);

                $headers = true;
            }

            fputcsv($csv, array_values(get_object_vars($entry)));
        }

        fclose($csv);
        exit;
    }

}
