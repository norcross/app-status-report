<?php
/*
Plugin Name: App Status Report
Plugin URI: http://andrewnorcross.com/plugins/app-status-report/
Description: A plugin to provide a CPT and JSON file for your web app
Author: Andrew Norcross
Version: 1.0
Requires at least: 3.5
Author URI: http://andrewnorcross.com
*/
/*  Copyright 2013 Andrew Norcross

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; version 2 of the License (GPL v2) only.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
/*
	based on the downtime JSON API method by Thomas Fuchs
	information can be found here: https://github.com/madrobby/downtime
*/

if(!defined('APR_BASE'))
	define('APR_BASE', plugin_basename(__FILE__) );

if(!defined('APR_VER'))
	define('APR_VER', '1.0');

class App_Status_Report
{

	/**
	 * This is our constructor
	 *
	 * @return App_Status_Report
	 */
	public function __construct() {
		add_action					( 'plugins_loaded', 				array( $this, 'textdomain'			) 			);
		add_action					( 'admin_enqueue_scripts',			array( $this, 'scripts_styles'		), 10		);
		add_action					( 'admin_init', 					array( $this, 'reg_settings'		) 			);
		add_action					( 'admin_menu',						array( $this, 'admin_pages'			) 			);
		add_action					( 'init', 							array( $this, '_register_status'	) 			);
		add_action					( 'init', 							array( $this, 'json_endpoint'		) 			);
		add_action					( 'template_redirect', 				array( $this, 'redirect'			),	1		);
		add_action					( 'add_meta_boxes',					array( $this, 'create_metaboxes'	)			);
		add_action					( 'save_post',						array( $this, 'save_status_meta'	),	1		);
		add_action					( 'save_post',						array( $this, 'save_status_logs'	),	1		);

		register_activation_hook	( __FILE__,							array( $this, 'activate'			)			);
		register_deactivation_hook	( __FILE__,							array( $this, 'deactivate'			)			);

	}


	/**
	 * load textdomain
	 *
	 * @return App_Status_Report
	 */

	public function textdomain() {

		load_plugin_textdomain( 'apr', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * create our endpoints
	 *
	 * @return App_Status_Report
	 */

	public function json_endpoint() {

		// register our "json" endpoint
		add_rewrite_endpoint( '.json', EP_PERMALINK );
	}

	/**
	 * Scripts and stylesheets
	 *
	 * @return App_Status_Report
	 */

	public function scripts_styles($hook) {

		if ( $hook == 'post-new.php' || $hook == 'post.php' ) :

			wp_enqueue_style( 'app-status', plugins_url('/lib/css/app-status.css', __FILE__), array(), APR_VER, 'all' );
			wp_enqueue_script( 'pickdate', plugins_url('/lib/js/pickdate.js', __FILE__) , array('jquery'), APR_VER, true );
			wp_enqueue_script( 'mousewheel', plugins_url('/lib/js/jquery.mousewheel.js', __FILE__) , array('jquery'), APR_VER, true );
			wp_enqueue_script( 'app-status', plugins_url('/lib/js/appstatus.init.js', __FILE__) , array('jquery'), APR_VER, true );

		endif;

		if ( $hook == 'status_page_app-status-options' ) :

			wp_enqueue_style( 'app-status', plugins_url('/lib/css/app-status.css', __FILE__), array(), APR_VER, 'all' );

		endif;

	}

	/**
	 * redirect the downtime file call
	 *
	 * @return App_Status_Report
	 */

	public function redirect() {

		global $wp_query;

		// bail if 'name' is missing
		if ( ! isset( $wp_query->query['name'] ) )
			return;

		// bail if 'name' isnt our JSON file
		if ( $wp_query->query['name'] !== 'downtime.json' )
			return;

		// output the JSON status report
		$this->status_output();
		exit;
	}

	/**
	 * helper function to query log entries
	 *
	 * @return App_Status_Report
	 *
	 * @TODO include false return for no posts
	 */

	public function status_log_query($post_id) {

		$log = array();

		// set args for status items
		$args = array(
			'fields'		=> 'ids',
			'post_type'		=> 'status',
			'post_status'	=> 'publish',
			'include'		=> $post_id,
			'numberposts'	=> -1,
			'order'			=> 'ASC',
			'orderby'		=> 'modified',
			'meta_key'		=> '_apr_logs',
			'nopaging'		=> true,
			'no_found_rows'	=> true

			);

		$status_logs = get_posts($args);

		if (!$status_logs)
			return $log;

		// build array of status logs
		foreach ($status_logs as $logs) :
			// get optional log entries
			$log_array		= get_post_meta($logs, '_apr_logs', true);

			if ( $log_array ) :
				foreach ( $log_array as $log_item ) :

					$log['time']	= !empty($log_item['time']) && $log_item['time'] !== 0 ? date('c', $log_item['time'] )	: '';
					$log['desc']	= !empty($log_item['desc']) && $log_item['desc'] !== 0 ? $log_item['desc']	: '';

				endforeach;
			else:

					$log['time']	= '';
					$log['desc']	= '';

			endif;

		endforeach;

//		echo preprint($log);

		return $log;

	}

	/**
	 * helper function to query statuses
	 *
	 * @return App_Status_Report
	 *
	 * @TODO include false return for no posts
	 */

	public function status_meta_query() {

		// set args for status items
		$args = array(
			'fields'		=> 'ids',
			'post_type'		=> 'status',
			'post_status'	=> 'publish',
			'numberposts'	=> -1,
			'order'			=> 'ASC',
			'orderby'		=> 'modified',
			'nopaging'		=> true,
			'no_found_rows'	=> true
		);

		$status_query = get_posts($args);

		// build array of status items
		foreach ($status_query as $status) :

			// get post variables
			$status_data	= get_post($status);

			// parse out data pieces
			$status_id		= $status_data->ID;
			$status_url		= get_permalink( $status_id );
			$status_title	= $status_data->post_title;
			$status_text	= $status_data->post_content;
			$status_time	= $status_data->post_modified;

			// parse serialized meta array
			$status_meta	= get_post_meta($status_id, '_apr_meta', true);
			$status_type	= $status_meta['schedule'];
			$status_avail	= $status_meta['availability'];
			$status_start	= $status_meta['start'];
			$status_end		= $status_meta['end'];

			// get affected URLs and split
			$status_urls	= $status_meta['affected-urls'];
			$status_affect	= !empty($status_urls)	? explode( ',', $status_urls ) : 'all';

			// convert our timestamps and check for nulls
			$start_disp		= !empty($status_start) ? date('c', $status_start ) : 'unknown';
			$end_disp		= !empty($status_end)	? date('c', $status_end )	: 'unknown';

			// get optional status logs
			$status_logs	= $this->status_log_query($status_id);

			// create array of all items
			$status_info[]	= array(
				'title'			=> $status_title,
				'description'	=> $status_text,
				'urls'			=> $status_affect,
				'info_url'		=> $status_url,
				'type'			=> $status_type,
				'availability'	=> $status_avail,
				'starts_at'		=> $start_disp,
				'ends_at'		=> $end_disp,
				'updated_at'	=> date('c', strtotime($status_time) ),
				'log'			=> $status_logs
				);

		endforeach;

		// send it back
		return $status_info;

	}

	/**
	 * generate JSON data
	 *
	 * @return App_Status_Report
	 */

	public function status_data() {

		// get top level settings
		$apr_settings	= get_option('apr_settings');

		$apr_app_name	= $apr_settings['name'];
		// get URL and sanitize it
		$apr_base_url	= $apr_settings['url'];
		$apr_app_url	= trim($apr_base_url, '/');

		// get status posts
		$status_info	= $this->status_meta_query();

		// set array of statuses
		$downtime = array(
			'service'		=> $apr_app_name,
			'url'			=> $apr_app_url,
			'updated_at'	=> date('c', time() ),
			'downtime'		=> $status_info // this is an array fed from the status_query function
		);

		// send it back
		return $downtime;
	}

	/**
	 * output JSON data
	 *
	 * @return App_Status_Report
	 */

	public function status_output() {

		header( 'Content-Type: application/json' );

		$data = $this->status_data();
		echo json_encode( $data );

	}

	/**
	 * our activation and deactivation hooks
	 *
	 * @return App_Status_Report
	 */

	public function activate() {

		$this->json_endpoint();

		flush_rewrite_rules();
	}

	public function deactivate() {

		flush_rewrite_rules();
	}

	/**
	 * build out post type
	 *
	 * @return App_Status_Report
	 */

	public function _register_status() {

		register_post_type( 'status',
			array(
				'labels'	=> array(
					'name' 					=> __( 'Status', 'apr' ),
					'singular_name' 		=> __( 'Status', 'apr' ),
					'add_new'				=> __( 'Add New Status', 'apr' ),
					'add_new_item'			=> __( 'Add New Status', 'apr' ),
					'edit'					=> __( 'Edit', 'apr' ),
					'edit_item'				=> __( 'Edit Status', 'apr' ),
					'new_item'				=> __( 'New Status', 'apr' ),
					'view'					=> __( 'View Status', 'apr' ),
					'view_item'				=> __( 'View Status', 'apr' ),
					'search_items'			=> __( 'Search Statuses', 'apr' ),
					'not_found'				=> __( 'No Statuses found', 'apr' ),
					'not_found_in_trash'	=> __( 'No Statuses found in Trash', 'apr' ),
				),
				'public'	=> true,
					'show_in_nav_menus'		=> true,
					'show_ui'				=> true,
					'publicly_queryable'	=> true,
					'exclude_from_search'	=> false,
				'hierarchical'		=> false,
				'menu_position'		=> null,
				'capability_type'	=> 'post',
				'menu_icon'			=> plugins_url( '/lib/img/apr-icon-16.png', __FILE__ ),
				'query_var'			=> true,
				'rewrite'			=> array( 'slug' => 'status', 'with_front' => false ),
				'has_archive'		=> 'status',
				'supports'			=> array('title', 'editor'),
			)
		);

	}

	/**
	 * call metabox
	 *
	 * @return App_Status_Report
	 */

	public function create_metaboxes() {

		add_meta_box('app-status-details',	__('Status Data', 'apr'), array(&$this, 'status_meta'), 'status', 'normal', 'high');
		add_meta_box('app-status-logs',		__('Status Logs', 'apr'), array(&$this, 'status_logs'), 'status', 'normal', 'high');

	}

	/**
	 * build metabox for status meta
	 *
	 * @return App_Status_Report
	 */

	public function status_meta( $post ) {

		// Use nonce for verification
		wp_nonce_field( 'apr_meta_nonce', 'apr_meta_nonce' );

		// get array of all values
		$apr_meta	= get_post_meta($post->ID, '_apr_meta', true);

		// get some base values
		$scheduled		= !empty($apr_meta['schedule'])			? $apr_meta['schedule']		: '';
		$availability	= !empty($apr_meta['availability'])		? $apr_meta['availability']	: '';
		$affected_urls	= !empty($apr_meta['affected-urls'])	? $apr_meta['affected-urls']: '';

		// convert some timestamps
		$starttime	= !empty($apr_meta['start'])	&& $apr_meta['start']	!== 0	? $apr_meta['start']	: '';
		$endtime	= !empty($apr_meta['end'])		&& $apr_meta['end']		!== 0	? $apr_meta['end']		: '';

		// build table
		echo '<table id="apr-meta-table" class="form-table apr-data-table">';
		echo '<tbody>';

		// setup each field

		echo '<tr>';
			echo '<th><label for="apr-meta-schedule">'.__('Status Type', 'apr' ).'</label></th>';
			echo '<td>';
			echo '<select name="apr-meta[schedule]" id="apr-meta-schedule">';
			echo '<option value="scheduled" '.selected( $scheduled, 'scheduled' ).'>'.__('Scheduled', 'apr').'</option>';
			echo '<option value="unscheduled" '.selected( $scheduled, 'unscheduled' ).'>'.__('Unscheduled', 'apr').'</option>';
			echo '</select>';
			echo '</td>';
		echo '</tr>';

		echo '<tr>';
			echo '<th><label for="apr-meta-availability">'.__('Service Availability', 'apr' ).'</label></th>';
			echo '<td>';
			echo '<select name="apr-meta[availability]" id="apr-meta-availability">';
			echo '<option value="up" '.selected( $availability, 'up' ).'>'.__('Up', 'apr').'</option>';
			echo '<option value="partial" '.selected( $availability, 'partial' ).'>'.__('Partial', 'apr').'</option>';
			echo '<option value="down" '.selected( $availability, 'down' ).'>'.__('Down', 'apr').'</option>';
			echo '</select>';
			echo '</td>';
		echo '</tr>';

		echo '<tr>';
			echo '<th><label for="apr-meta-start">'.__('Start Time', 'apr' ).'</label></th>';
			echo '<td>';
			echo '<input type="text" name="apr-meta[start]" id="apr-meta-start" class="time-select" value="'.$starttime.'">';
			echo '</td>';
		echo '</tr>';

		echo '<tr>';
			echo '<th><label for="apr-meta-end">'.__('End Time', 'apr' ).'</label></th>';
			echo '<td>';
			echo '<input type="text" name="apr-meta[end]" id="apr-meta-end" class="time-select" value="'.$endtime.'">';
			echo '</td>';
		echo '</tr>';

		echo '<tr>';
			echo '<th><label for="apr-affected-urls">'.__('Affected URL(s)', 'apr' ).'</label></th>';
			echo '<td>';
			echo '<input type="text" name="apr-meta[affected-urls]" class="regular-text" id="apr-affected-urls" value="'.$affected_urls.'">';
			echo '<p class="description">Enter one or more URLs. Separate by comma</span></p>';
			echo '</td>';
		echo '</tr>';

		echo '</tbody>';
		echo '</table>';

	}

	/**
	 * build metabox for status log updates
	 *
	 * @return App_Status_Report
	 */

	public function status_logs( $post ) {

		// Use nonce for verification
		wp_nonce_field( 'apr_logs_nonce', 'apr_logs_nonce' );

		// get array of all values
		$apr_logs	= get_post_meta($post->ID, '_apr_logs', true);

//		echo preprint($apr_logs);

		// build table
		echo '<table id="apr-logs-table" class="form-table apr-data-table">';

		echo '<thead>';
		echo '<tr class="log-table-headers">';
			echo '<th class="log-time">'.__('Log Time', 'apr' ).'</th>';
			echo '<th class="log-text">'.__('Log Description', 'apr' ).'</th>';
			echo '<th></th>'; // empty to match the rows
		echo '</tr>';
		echo '</thead>';

		echo '<tbody>';

		// setup each field
		if ( !empty( $apr_logs ) ) :
			// build all log rows
			foreach ( $apr_logs as $log ) {

				$logtime	= !empty($log['time']) && $log['time'] !== 0 ? $log['time']	: '';
				$logdesc	= !empty($log['desc']) && $log['desc'] !== 0 ? $log['desc']	: '';

				echo '<tr class="log-update">';
					echo '<td class="log-time">';
					echo '<input id="log-time" type="text" name="apr-time[]" class="time-select" value="'.$logtime.'">';
					echo '</td>';

					echo '<td class="log-text">';
					echo '<input type="text" name="apr-desc[]" class="regular-text" value="'.$logdesc.'">';
					echo '</td>';

					echo '<td class="log-remove">';
					echo '<span class="remove-log button button-secondary">Remove</span>';
					echo '</td>';

				echo '</tr>';

			}

		else :
			// show an empty one
			echo '<tr class="log-update">';
				echo '<td class="log-time">';
				echo '<input id="log-time" type="text" name="apr-time[]" class="time-select" value="">';
				echo '</td>';

				echo '<td class="log-text">';
				echo '<input type="text" name="apr-desc[]" class="regular-text" value="">';
				echo '</td>';

				echo '<td class="log-remove">';
				// enpty because we don't need to remove an empty row
				echo '</td>';

			echo '</tr>';

		endif;

		// empty row for repeating
		echo '<tr class="empty-row screen-reader-text">';
				echo '<td class="log-time">';
				echo '<input id="log-time" type="text" name="apr-time[]" class="time-select" value="">';
				echo '</td>';

				echo '<td class="log-text">';
				echo '<input type="text" name="apr-desc[]" class="regular-text" value="">';
				echo '</td>';

				echo '<td class="log-remove">';
				echo '<span class="remove-log button button-secondary">Remove</span>';
				echo '</td>';

		echo '</tr>';

		echo '<tr class="log-button">';
			echo '<td>';
			echo '<input type="button" id="add-status" class="button button-primary" value="Add Log Entry">';
			echo '</td>';
		echo '</tr>';

		echo '</tbody>';
		echo '</table>';

	}

	/**
	 * save status metadata
	 *
	 * @return App_Status_Report
	 */


	function save_status_meta( $post_id ) {

		// run various checks to make sure we aren't doing anything weird
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;

		if ( ! isset( $_POST['apr_meta_nonce'] ) ||
		! wp_verify_nonce( $_POST['apr_meta_nonce'], 'apr_meta_nonce' ) )
			return $post_id;

		if ( 'status' !== $_POST['post_type'] )
			return $post_id;

	    if ( !current_user_can( 'edit_post', $post_id ) )
	        return $post_id;


	    // get data via $_POST and store it
		$apr_meta	= $_POST['apr-meta'];

		update_post_meta($post_id, '_apr_meta', $apr_meta);

	}

	/**
	 * save status log files
	 *
	 * @return App_Status_Report
	 */


	function save_status_logs( $post_id ) {

		// run various checks to make sure we aren't doing anything weird
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;

		if ( ! isset( $_POST['apr_logs_nonce'] ) ||
		! wp_verify_nonce( $_POST['apr_logs_nonce'], 'apr_logs_nonce' ) )
			return $post_id;

		if ( 'status' !== $_POST['post_type'] )
			return $post_id;

	    if ( !current_user_can( 'edit_post', $post_id ) )
	        return $post_id;

		$current = get_post_meta($post_id, '_apr_meta', true);
		$updates = array();

	    // get data via $_POST and store it
		$log_desc	= $_POST['apr-desc'];
		$log_time	= !empty( $_POST['apr-time'] ) ? $_POST['apr-time'] : time();

		$update_num	= count( $log_desc );

		for ( $i = 0; $i < $update_num; $i++ ) {

			if ( $log_desc[$i] != '' )
				$updates[$i]['desc'] = $log_desc[$i];

				if ( $log_time[$i] != '' ) :
					$updates[$i]['time'] = $log_time[$i];

			endif;

		}

		// process array
		if ( !empty( $updates ) && $updates != $current )
			update_post_meta($post_id, '_apr_logs', $updates);

		elseif ( empty($updates) && $current )
			delete_post_meta($post_id, '_apr_logs', $updates);

	}

	/**
	 * Register settings
	 *
	 * @return App_Status_Report
	 */


	public function reg_settings() {
		register_setting( 'apr_settings', 'apr_settings');

	}

	/**
	 * build out settings page
	 *
	 * @return App_Status_Report
	 */

	public function admin_pages() {

		add_submenu_page('edit.php?post_type=status', __('Settings', 'apr'), __('Settings', 'apr'), 'manage_options', 'app-status-options', array( &$this, 'settings_page' ));
	}

	/**
	 * Display main options page structure
	 *
	 * @return App_Status_Report
	 */

	public function settings_page() {
		if (!current_user_can('manage_options') )
			return;
		?>

        <div class="wrap">
        	<div id="icon-apr-admin" class="icon32"><br /></div>
        	<h2><?php _e('App Status Settings', 'apr') ?></h2>

			<?php
			if ( isset( $_GET['settings-updated'] ) )
    			echo '<div id="message" class="updated below-h2"><p>'. __('Settings updated successfully.', 'apr').'</p></div>';
			?>


			<div id="poststuff" class="metabox-holder has-right-sidebar">

			<?php
			echo $this->settings_side();
			echo $this->settings_open();
			?>

	            <form method="post" action="options.php">
			    <?php
                settings_fields( 'apr_settings' );

				$options	= get_option('apr_settings');

				$apr_name	= (isset($options['name'])		? $options['name']	: ''		);
				$apr_url	= (isset($options['url'])		? $options['url']	: ''		);
				?>

				<table class="form-table apr-table">
				<tbody>

					<tr>
						<th><label for="apr-name"><?php _e('App name', 'apr') ?></label></th>
						<td>
						<input type="text" class="regular-text" value="<?php echo $apr_name; ?>" id="apr-name" name="apr_settings[name]">
						<span class="description"><?php _e('Enter the name of your web application', 'apr') ?></span>
						</td>
					</tr>

					<tr>
						<th><label for="apr-url"><?php _e('App URL', 'apr') ?></label></th>
						<td>
						<input type="text" class="regular-text" value="<?php echo $apr_url; ?>" id="apr-url" name="apr_settings[url]">
						<span class="description"><?php _e('Enter the URL of your web application', 'apr') ?></span>
						</td>
					</tr>


				</tbody>
				</table>

				<p><input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" /></p>
				</form>

	<?php echo $this->settings_close(); ?>

	</div>
	</div>


	<?php }

    /**
     * Some extra stuff for the settings page
     *
     * this is just to keep the area cleaner
     *
     * @return App_Status_Report
     */

    public function settings_side() { ?>

		<div id="side-info-column" class="inner-sidebar">
			<div class="meta-box-sortables">
				<div id="faq-admin-about" class="postbox">
					<h3 class="hndle" id="about-sidebar"><?php _e('About the Plugin', 'apr'); ?></h3>
					<div class="inside">
						<p><?php _e('Talk to') ?> <a href="http://twitter.com/norcross" target="_blank">@norcross</a> <?php _e('on twitter or visit the', 'apr'); ?> <a href="http://wordpress.org/support/plugin/URL/" target="_blank"><?php _e('plugin support form') ?></a> <?php _e('for bugs or feature requests.', 'apr'); ?></p>
						<p><?php _e('<strong>Enjoy the plugin?</strong>', 'apr'); ?><br />
						<a href="http://twitter.com/?status=I'm using @norcross's WordPress FAQ Manager plugin - check it out! http://l.norc.co/wpfaq/" target="_blank"><?php _e('Tweet about it', 'apr'); ?></a> <?php _e('and consider donating.', 'apr'); ?></p>
						<p><?php _e('<strong>Donate:</strong> A lot of hard work goes into building plugins - support your open source developers. Include your twitter username and I\'ll send you a shout out for your generosity. Thank you!', 'apr'); ?><br />
						<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">
						<input type="hidden" name="cmd" value="_s-xclick">
						<input type="hidden" name="hosted_button_id" value="11085100">
						<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
						<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
						</form></p>
					</div>
				</div>
			</div>

			<div class="meta-box-sortables">
				<div id="faq-admin-more" class="postbox">
					<h3 class="hndle" id="about-sidebar"><?php _e('Links', 'apr'); ?></h3>
					<div class="inside">
						<ul>
						<li><a href="http://wordpress.org/extend/plugins/URL/" target="_blank"><?php _e('Plugin on WP.org', 'apr'); ?></a></li>
						<li><a href="https://github.com/norcross/URL" target="_blank"><?php _e('Plugin on GitHub', 'apr'); ?></a></li>
						<li><a href="http://wordpress.org/support/plugin/URL" target="_blank"><?php _e('Support Forum', 'apr'); ?></a><li>
            			</ul>
					</div>
				</div>
			</div>
		</div> <!-- // #side-info-column .inner-sidebar -->

    <?php }

	public function settings_open() { ?>

		<div id="post-body" class="has-sidebar">
			<div id="post-body-content" class="has-sidebar-content">
				<div id="normal-sortables" class="meta-box-sortables">
					<div id="about" class="postbox">
						<div class="inside">

    <?php }

	public function settings_close() { ?>

						<br class="clear" />
						</div>
					</div>
				</div>
			</div>
		</div>

    <?php }

/// end class
}


// Instantiate our class
$App_Status_Report = new App_Status_Report();
