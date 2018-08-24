<?php
/*
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// TODO: Really need to tidy this file up and organize it better.

namespace Revisionize;

if (is_admin() || is_cron()) {
	add_action('admin_menu', __NAMESPACE__.'\\settings_menu');
	add_filter('plugin_action_links_'.REVISIONIZE_BASE, __NAMESPACE__.'\\settings_link');
	add_filter('revisionize_keep_original_on_publish', __NAMESPACE__.'\\filter_keep_backup');
	add_filter('revisionize_preserve_post_date', __NAMESPACE__.'\\filter_preserve_date');
}

function settings_admin_init() {
	if (is_on_settings_page()) {
		set_setting('has_seen_settings', true);
	} else if (get_setting('has_seen_settings', false) === false) {
		add_action('admin_notices', __NAMESPACE__.'\\notify_new_settings');
	}
}

function settings_menu() {
	add_submenu_page (
		'options-general.php',
		'Revisionize Settings',
		'Revisionize',
		'manage_options',
		'revisionize',
		__NAMESPACE__.'\\settings_page'
	);

	register_setting('revisionize', 'revisionize_settings', array(
		"sanitize_callback" => __NAMESPACE__.'\\on_settings_saved'
	));

	setup_basic_settings();

}

function settings_page() {
	if (!current_user_can('manage_options')) {
		echo 'Not Allowed.';
		return;
	}
	?>
    <div class="wrap">
		<?php settings_css(); ?>
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" enctype="multipart/form-data" method="post" class="rvz-settings-form">
			<?php
			settings_fields('revisionize');

			do_fields_section('revisionize_section_basic');

			// settings from Addons
			do_action('revisionize_settings_fields');

			do_fields_section('revisionize_section_addons');

			submit_button('Save Settings');

			?>
        </form>
    </div>
	<?php
}

function do_fields_section($key, $group="revisionize") {
	echo '<table class="form-table">';
	do_settings_fields($group, $key);
	echo '</table>';
}

function setup_basic_settings() {
	add_settings_section('revisionize_section_basic', '', '__return_null', 'revisionize');

	input_setting('checkbox', 'Keep Backup', 'keep_backup', "After publishing the revision, the previously live post will be kept around and marked as a backup revision of the new version.", true, 'revisionize_section_basic');

	input_setting('checkbox', 'Preserve Date', 'preserve_date', "The date of the original post will be maintained even if the revisionized post date changes. In particular, a scheduled revision won't modify the post date once it's published.", true, 'revisionize_section_basic');
}

function settings_addon_file_html($args) {
	$id = esc_attr($args['label_for']);
	?>
    <div>
        <input id="<?php echo esc_attr( $id ); ?>" type="file" name="revisionize_addon_file" style="width:320px" accept=".rvz"/>
        <p>To install or update an addon, choose a <em>.rvz</em> file and click <em>Save Settings</em></p>
    </div>
	<?php
}

// access settings
function get_setting($key, $default='', $multisite=false) {
	$settings = get_option('revisionize_settings');
	return !empty($settings[$key]) ? $settings[$key] : $default;
}

function set_setting($key, $value) {
	$settings = get_option('revisionize_settings');
	$settings[$key] = $value;
	update_option('revisionize_settings', $settings);
}

function remove_setting($keys) {
	$settings = get_option('revisionize_settings');
	if (!is_array($keys)) {
		$keys = array($keys);
	}
	foreach ($keys as $key) {
		unset($settings[$key]);
	}
    update_option('revisionize_settings', $settings);
}

function is_on_settings_page() {
	global $pagenow;
	$page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_STRING );
	return $pagenow === 'options-general.php' && isset($page) && $page === 'revisionize';
}

function on_settings_saved($settings=null) {
	return $settings;
}

function remote_addons_valid($addons) {
	return !empty($addons) && count($addons) > 0 && all_keys_set($addons, "id") && all_keys_set($addons, "version");
}

function all_keys_set($arr, $key) {
	$s = implode('', array_map(function($obj) use ($key) { return empty($obj[$key]) ? "" : $obj[$key]; }, $arr));
	return !empty($s);
}


function filter_keep_backup($b) {
	return is_checkbox_checked('keep_backup', $b);
}

function filter_preserve_date($b) {
	return is_checkbox_checked('preserve_date', $b);
}

// basic inputs for now
// $type: text|email|number|checkbox
function input_setting($type, $name, $key, $description, $default, $section) {
	add_settings_field('revisionize_setting_'.$key, $name, __NAMESPACE__.'\\field_input', 'revisionize', $section, array(
		'type' => $type,
		'label_for' => 'revisionize_setting_'.$key,
		'key' => $key,
		'description' => $description,
		'default' => $default
	));
}

function field_input($args) {
	$type = $args['type'];
	$id = esc_attr($args['label_for']);
	$key = esc_attr($args['key']);
	$value = '';

	if ($type === 'checkbox') {
		if (is_checkbox_checked($key, $args['default'])) {
			$value = 'checked';
		}
	} else {
		$value = 'value="'.get_setting($key, $args['default']).'"';
	}
	?>
    <div>
		<?php if ($type==="checkbox"): ?>
            <input type="hidden" name="revisionize_settings[_<?php echo esc_attr($key); ?>_set]" value="1"/>
		<?php endif; ?>
        <label>
            <input id="<?php echo esc_attr($id); ?>" type="<?php echo esc_attr($type); ?>" name="revisionize_settings[<?php echo esc_attr($key); ?>]" <?php echo esc_attr($value); ?>/>
			<?php echo esc_html($args['description']); ?>
        </label>
    </div>
	<?php
}

function is_checkbox_checked($key, $default, $multisite=false) {
	return is_checkbox_set($key, $multisite) ? is_checkbox_on($key, $multisite) : $default;
}

function is_checkbox_on($key, $multisite=false) {
	return get_setting($key, '', $multisite) === "on";
}

function is_checkbox_set($key, $multisite=false) {
	return get_setting('_'.$key.'_set', '', $multisite) === "1";
}

function notify_new_settings() {
	echo '<div class="notice notice-info is-dismissible"><p><strong>Revisionize</strong> has a new settings panel. <strong><a href="'.esc_url( admin_url('options-general.php?page=revisionize')).'">Check it out!</a></strong></p></div>';
}

function notify_updated_settings() {
	echo '<div class="notice updated is-dismissible"><p><strong>Settings saved.</strong></p></div>';
}

function notify_needs_update() {
	if (!is_on_settings_page()) {
		$url = admin_url('options-general.php?page=revisionize');
		echo '<div class="notice updated is-dismissible"><p>Revisionize has 1 or more updates available for your installed addons. <a href="'.esc_url($url).'">View settings</a> for details.</p></div>';
	}
}

function settings_css() {
	?>
    <style type="text/css">
        .rvz-cf:after {
            content: "";
            display: table;
            clear: both;
        }
        .rvz-settings-form {
            margin-top: 15px;
        }
        .rvz-settings-form .form-table {
            margin-top: 0;
        }
        .rvz-settings-form .form-table th, .rvz-settings-form .form-table td {
            padding-top: 12px;
            padding-bottom: 12px;
        }
        .rvz-settings-form .form-table p {
            margin-top: 0;
        }
        .rvz-update-available {
            clear: both;
            margin-top: 8px;
            text-align: center;
        }
    </style>
	<?php
}

function settings_link($links) {
	return array_merge($links, array('<a href="'.admin_url('options-general.php?page=revisionize').'">Settings</a>'));
}
