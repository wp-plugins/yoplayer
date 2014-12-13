<?php
/**
 * @package yoplayer
 * @version 2.1.38.3
 *
 * Copyright (C) 2013 - 2014 Yospace Technologies Ltd. All rights reserved
 */
/*
Plugin Name: Yoplayer
Plugin URI: http://www.yospace.com/index.php/hls-sdk-for-flash-overview.html
Description: Yospace, the leader in n-screen delivery, present Yoplayer based on their world beating Flash HLS-SDK technology allowing playback of the widest range of video formats in a single player. Coupled with this is out of the box support for Google IMA for content monetization. Yoplayer for Wordpress allows you to provide a Yospace Media Item ID and Feed ID from your Yospace account and it takes care of selecting the appropriate video format, metadata (title, description etc) with any further user configuration.
Author: Yospace Technologies Ltd
Author URI: http://www.yospace.com
Version: 2.1.38.3
Licence: GPLv2 or later
*/

global $wpdb, $yoplayer_table_name;
$yoplayer_table_name = $wpdb->prefix . "yoplayer_metadata";

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
    echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
    exit;
}

define('YOPLAYER_VERSION', '2.1');
define('YOPLAYER_PLUGIN_URL', plugin_dir_url( __FILE__ ));

/*===========================================================================*
 * Include admin.php only if we are in the admin interface
 *===========================================================================*/
if ( is_admin() )
    require_once dirname( __FILE__ ) . '/admin.php';

/*===========================================================================*
 * Render the tag
 *===========================================================================*/
add_action('wp_enqueue_scripts','yoplayerScripts',0);
function yoplayerScripts() {
    wp_enqueue_script("yoplayer", plugins_url('js/yoplayer-' . YOPLAYER_VERSION . '.js', __FILE__));
}

$players = array();
add_shortcode('yoplayer', 'yoplayerEmbed');
function yoplayerEmbed($atts) {
    global $players, $wpdb, $yoplayer_table_name;
    $id = 1;
    $values = shortcode_atts(array(
        'miid' => '',
        'live' => '',
        'pp' => 'm3u8',
        'bsid' => '',
        'url' => '',
        'fid' => get_option('yoplayer-fid', ''),
        'skin' => '',
        'width' => get_option('yoplayer-width', '640'),
        'height' => get_option('yoplayer-height', '352'),
        'buffer' => get_option('yoplayer-buffer', '30'),
        'lwm' => get_option('yoplayer-lwm', '5'),
        'lss' => get_option('yoplayer-lss', '3'),
        'panning' => get_option('yoplayer-panning', false),
        'enablecc' => get_option('yoplayer-enablecc', false),
        'debug' => get_option('yoplayer-debug', false),
        'autoplay' => get_option('yoplayer-autoplay', false),
        'startlevel' => null,
        'metadata' => get_option('yoplayer-metadata', '')
    ), $atts);
    foreach (array('panning', 'enablecc', 'debug', 'autoplay') as $key) {
        if (($values[$key] == 1) ||
            ($values[$key] == '1') ||
            ($values[$key] == true) ||
            ($values[$key] == 'true') ||
            ($values[$key] == 'yes')) {
            $values[$key] = 'true';
        } else {
            $values[$key] = 'false';
        }
    }
    if ($values['skin'] == "") {
        $skin_setting = get_option('yoplayer-skin', '');
        if ($skin_setting == 'custom') {
            $values['skin'] = get_option('yoplayer-custom-skin', '');
        } else if ($skin_setting == "") {
        } else {
            $values['skin'] = plugins_url('skins/' . $skin_setting, __FILE__);
        }
    }
    if ($values['miid'] == '') {
        $values['miid'] = $values['bsid'];
    }
    while (array_key_exists($values['miid'] . '_' . $id, $players)) {
        $id++;
    }
    $thisID = $values['miid'] . '_' . $id;
    $retval = '<script type="text/javascript">
$YOPLAYERHERE(
    "yoplayer_' . $thisID . '",
    {
        "player": "' . plugins_url('player/yoplayer-' . YOPLAYER_VERSION . '.swf', __FILE__) . '",
        "width": ' . $values['width'] .',
        "height": ' . $values['height'] .',
        "buffer": ' . $values['buffer'] . ',
        "lwm": ' . $values['lwm'] . ',
        "lss": ' . $values['lss'] . ',
        "panning": ' . $values['panning'] . ',
        "enablecc": ' . $values['enablecc'] . ',
        "debug": ' . $values['debug'] . ',
        "autoplay": ' . $values['autoplay'];
    if ($values['startlevel']) {
        $retval .= ',
        "startlevel": ' . $values['startlevel'];
    }
    if ($values['url']) {
        if ($values['type']) {
            $retval .= ',
        "type": "' . $values['type'] . '"';
        }
        $retval .= ',
        "file": "' . $values['url'] . '"';
    } else if ($values['bsid']) {
        $retval .= ',
        "live": true,
        "miid": "' . $values['bsid'] . '",
        "pp": "m3u8"';
    } else {
        $retval .= ',
        "miid": "' . $values['miid'] . '"';
        if ($values['live']) {
            $retval .= ',
        "live": true,
        "pp": "' . $values['pp'] . '"';
        } else {
            $retval .= ',
        "fid": "' . $values['fid'] . '"';
        }
    }
    if ($values['skin']) {
        $retval .= ',
        "skin": "' . $values['skin'] . '"';
    }
    $retval .= '
    }
);
</script>
';
    $players[$thisID] = 1;
    if (!$values['miid'] && !$values['url']) {
        return "<div>No video supplied to <code>yoplayer</code> tag</div>";
    }
    $metakeys = explode(",", $values['metadata']);
    if ((count($metakeys) > 1) || (strlen($metakeys[0]) > 0)) {
        foreach($metakeys as $key) {
            $value = $wpdb->get_var("SELECT value FROM $yoplayer_table_name WHERE miid='" . $values['miid'] . "' AND field='" . trim($key) . "'");
            $retval .= '<div class="yoplayer_' . str_replace(' ', '_', trim($key)) . '" id="yoplayer' . $thisID .'_' . str_replace(' ', '_', trim($key)) . '">' . $value . "</div>\n";
        }
    }
    return $retval;
}

/*===========================================================================*
 * Plugin Activation
 *===========================================================================*/
register_activation_hook(__FILE__, 'yoplayerActivate');
function yoplayerActivate() {
    global $yoplayer_table_name;
    $sql = "CREATE TABLE $yoplayer_table_name (
  id BIGINT(20) NOT NULL AUTO_INCREMENT,
  miid VARCHAR(255) NOT NULL,
  field VARCHAR(255) NOT NULL,
  value VARCHAR(4096) DEFAULT '',
  PRIMARY KEY  (id)
);";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
