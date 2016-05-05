<?php
/**
 * @package Gaming Tournaments
 * @version 1.0
 */
/*
Plugin Name: Gaming Tournaments
Description: A WordPress plugin that enables you to host virtual gaming tournaments in a knock-out style.
Author: Mohaimenul Adnan
Version: 1.0
Author URI: http://eadnan.com/
*/

/**
 * Plugin class to include all plugin code.
 */
Class Gaming_Tournament {
	
	/**
	 * Run plugin initialization codes.
	 * 
	 * Implement all hook calls and any necessery code that is required in plugin installation.
	 */
	function __construct(){

		add_action('init', [$this, 'register_post_types']);
		add_action('init', [$this, 'register_taxonomies']);
		add_action('add_meta_boxes_tournament', [$this, 'add_tournament_meta_boxes']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
		add_action('wp_enqueue_scripts', [$this, 'enqueue_front_end_scripts']);
		add_action('wp_ajax_autocomplete-username', [$this, 'autocomplete_username']);
		add_action('save_post', [$this, 'update_tournament_meta']);


		add_filter('the_content', [$this, 'modify_tournament_page']);

	}

	/**
	 * Register custom post types related to this plugin.
	 */
	public function register_post_types(){

		// Register tournament post type
		register_post_type( 'tournament', [
			'labels' => [
				'name'               => _x( 'Tournaments', 'post type general name', 'gt' ),
				'singular_name'      => _x( 'Tournament', 'post type singular name', 'gt' ),
				'menu_name'          => _x( 'Tournaments', 'admin menu', 'gt' ),
				'name_admin_bar'     => _x( 'Tournament', 'add new on admin bar', 'gt' ),
				'add_new'            => _x( 'Add New', 'tournament', 'gt' ),
				'add_new_item'       => __( 'Add New Tournament', 'gt' ),
				'new_item'           => __( 'New Tournament', 'gt' ),
				'edit_item'          => __( 'Edit Tournament', 'gt' ),
				'view_item'          => __( 'View Tournament', 'gt' ),
				'all_items'          => __( 'All Tournaments', 'gt' ),
				'search_items'       => __( 'Search Tournaments', 'gt' ),
				'parent_item_colon'  => __( 'Parent Tournaments:', 'gt' ),
				'not_found'          => __( 'No tournaments found.', 'gt' ),
				'not_found_in_trash' => __( 'No tournaments found in Trash.', 'gt' )
			],
			'description'     => __( 'Individual gaming tournaments.', 'gt' ),
			'public'          => true,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'query_var'       => true,
			'rewrite'         => [ 'slug' => 'tournament' ],
			'capability_type' => 'post',
			'has_archive'     => false,
			'hierarchical'    => false,
			'menu_position'   => null,
			'supports'        => [ 'title', 'editor' ]
		]);

	}

	/**
	 * Register custom taxonomies related to this plugin.
	 */
	public function register_taxonomies(){
		$labels = array(
			'name'              => _x( 'Games', 'taxonomy general name', 'gt' ),
			'singular_name'     => _x( 'Game', 'taxonomy singular name', 'gt' ),
			'search_items'      => __( 'Search Games', 'gt' ),
			'all_items'         => __( 'All Games', 'gt' ),
			'parent_item'       => __( 'Parent Game', 'gt' ),
			'parent_item_colon' => __( 'Parent Game:', 'gt' ),
			'edit_item'         => __( 'Edit Game', 'gt' ),
			'update_item'       => __( 'Update Game', 'gt' ),
			'add_new_item'      => __( 'Add New Game', 'gt' ),
			'new_item_name'     => __( 'New Game Name', 'gt' ),
			'menu_name'         => __( 'Game', 'gt' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'game' ),
		);
		register_taxonomy( 'game', array( 'tournament' ), $args );

		$labels = array(
			'name'              => _x( 'Platforms', 'taxonomy general name', 'gt' ),
			'singular_name'     => _x( 'Platform', 'taxonomy singular name', 'gt' ),
			'search_items'      => __( 'Search Platforms', 'gt' ),
			'all_items'         => __( 'All Platforms', 'gt' ),
			'parent_item'       => __( 'Parent Platform', 'gt' ),
			'parent_item_colon' => __( 'Parent Platform:', 'gt' ),
			'edit_item'         => __( 'Edit Platform', 'gt' ),
			'update_item'       => __( 'Update Platform', 'gt' ),
			'add_new_item'      => __( 'Add New Platform', 'gt' ),
			'new_item_name'     => __( 'New Platform Name', 'gt' ),
			'menu_name'         => __( 'Platform', 'gt' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'platform' ),
		);
		register_taxonomy( 'platform', array( 'tournament' ), $args );
	}

	/**
	 * Add metaboxes on tournament edit screens
	 */
	public function add_tournament_meta_boxes(){

		add_meta_box(
			'tournament-settings',
			__( 'Tournament Settings', 'gt' ),
			function(){
				wp_nonce_field( 'tournament_settings', 'tournament_settings[nonce]' );

				global $post;

				// Existing data
				$tournament_registration = get_post_meta( $post->ID, '_tournament_setting_registration', true );
				$registration_deadline   = get_post_meta( $post->ID, '_tournament_setting_registration_deadline', true );
				$rounds                  = (array) get_post_meta( $post->ID, '_tournament_setting_rounds', true );
				?>
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row"><label><?php _e( 'Tournament Registrations', 'gt' ); ?></label></th>
							<td>
								<label>
									<input type="radio" name="tournament_settings[registration]" value="public" <?php echo 'public' == $tournament_registration ? 'checked="checked"' : '';?>> 
									<?php _e( 'Public', 'gt' ); ?>
								</label> 
								<label>
									<input type="radio" name="tournament_settings[registration]" value="private" <?php echo 'private' == $tournament_registration ? 'checked="checked"' : '';?>> 
									<?php _e( 'Private', 'gt' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="tournament-settings-deadline">
									<?php _e( 'Registration Deadline', 'gt' ); ?>
								</label>
							</th>
							<td>
								<input type="text" name="tournament_settings[registration_deadline]" value="<?php echo $registration_deadline;?>" id="tournament-settings-deadline" class="regular-text time-date-picker">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label><?php _e( 'Rounds', 'gt' ); ?></label>
							</th>
							<td>
								<table class="form-table rounds">
									<thead>
										<tr>
											<th scope="col">#</th>
											<th scope="col"><?php _e( 'Points', 'gt' );?></th>
											<th scope="col"><?php _e( 'End Date', 'gt' ); ?></th>
											<th scope="col"><?php _e( 'Total Players', 'gt' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td><?php _e( '1', 'gt' ); ?></td>
											<td><input type="number" name="tournament_settings[rounds][1][points]" class="form-field" value="<?php echo $rounds[1]['points']; ?>"></td>
											<td><input type="text" name="tournament_settings[rounds][1][end_date]" class="form-field time-date-picker" value="<?php echo $rounds[1]['end_date']; ?>"></td>
											<td><?php _e( '2', 'gt' ); ?></td>
										</tr>
										<tr>
											<td><?php _e( '2', 'gt' ); ?></td>
											<td><input type="number" name="tournament_settings[rounds][2][points]" class="form-field" value="<?php echo $rounds[2]['points']; ?>"></td>
											<td><input type="text" name="tournament_settings[rounds][2][end_date]" class="form-field time-date-picker" value="<?php echo $rounds[2]['end_date']; ?>"></td>
											<td><?php _e( '4', 'gt' ); ?></td>
										</tr>
										<tr>
											<td><?php _e( '3', 'gt' ); ?></td>
											<td><input type="number" name="tournament_settings[rounds][3][points]" class="form-field" value="<?php echo $rounds[3]['points']; ?>"></td>
											<td><input type="text" name="tournament_settings[rounds][3][end_date]" class="form-field time-date-picker" value="<?php echo $rounds[3]['end_date']; ?>"></td>
											<td><?php _e( '8', 'gt' ); ?></td>
										</tr>
										<tr>
											<td><?php _e( '4', 'gt' ); ?></td>
											<td><input type="number" name="tournament_settings[rounds][4][points]" class="form-field" value="<?php echo $rounds[4]['points']; ?>"></td>
											<td><input type="text" name="tournament_settings[rounds][4][end_date]" class="form-field time-date-picker" value="<?php echo $rounds[4]['end_date']; ?>"></td>
											<td><?php _e( '16', 'gt' ); ?></td>
										</tr>
										<tr>
											<td><?php _e( '5', 'gt' ); ?></td>
											<td><input type="number" name="tournament_settings[rounds][5][points]" class="form-field" value="<?php echo $rounds[5]['points']; ?>"></td>
											<td><input type="text" name="tournament_settings[rounds][5][end_date]" class="form-field time-date-picker" value="<?php echo $rounds[5]['end_date']; ?>"></td>
											<td><?php _e( '32', 'gt' ); ?></td>
										</tr>
										<tr>
											<td><?php _e( '6', 'gt' ); ?></td>
											<td><input type="number" name="tournament_settings[rounds][6][points]" class="form-field" value="<?php echo $rounds[6]['points']; ?>"></td>
											<td><input type="text" name="tournament_settings[rounds][6][end_date]" class="form-field time-date-picker" value="<?php echo $rounds[6]['end_date']; ?>"></td>
											<td><?php _e( '64', 'gt' ); ?></td>
										</tr>
									</tbody>
								</table>
								<small><?php _e( 'Only input values in the rounds that will be played. If end date or points left empty, the round will be considered invalid.' ); ?></small>
							</td>
						</tr>
					</tbody>
				</table>
				<?php
			},
			'tournament',
			'normal',
			'default'
		);

	}

	/**
	 * Enqueue scripts in back end.
	 */
	public function enqueue_admin_scripts(){

		wp_register_style( 'jquery-ui', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css' );
		wp_enqueue_style( 'jquery-ui' ); 

		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_script( 'jquery-ui-slider' );

		wp_enqueue_style( 'jquery-timepicker-addon', plugins_url( 'css/jquery-ui-timepicker-addon.css', __FILE__ ), [], '1.6.3' );
		wp_enqueue_script( 'jquery-timepicker-addon', plugins_url( 'js/jquery-ui-timepicker-addon.js', __FILE__ ), ['jquery', 'jquery-ui-datepicker', 'jquery-ui-slider'], '1.6.3');

		wp_enqueue_script( 'gaming-tournament', plugins_url( 'js/plugin.js', __FILE__ ), ['jquery', 'jquery-ui-datepicker', 'jquery-timepicker-addon'] );
		wp_enqueue_style( 'gaming-tournament', plugins_url( 'css/plugin.css', __FILE__ ) );

	}

	/**
	 * Enqueue scripts in front-end.
	 */
	public function enqueue_front_end_scripts(){

		wp_enqueue_style( 'jquery-modal', plugins_url( 'css/jquery.modal.min.css', __FILE__ ), [], '0.7.0' );
		wp_enqueue_script( 'jquery-modal', plugins_url( 'js/jquery.modal.min.js', __FILE__ ), ['jquery'], '0.7.0' );

		wp_enqueue_script( 'jquery-countdown', plugins_url( 'js/jquery.countdown.min.js', __FILE__ ), ['jquery'], '0.7.0' );

		wp_enqueue_script( 'gaming-tournament', plugins_url( 'js/plugin.js', __FILE__ ), ['jquery', 'jquery-modal', 'jquery-countdown'] );
		wp_enqueue_style( 'gaming-tournament', plugins_url( 'css/plugin.css', __FILE__ ) );

	}

	/**
	 * Return json array of usernames to autocomplete.
	 */
	public function autocomplete_username(){

		$user_query = new WP_User_Query( ['search' => $_GET['query']."*"] );

		echo json_encode( array_map( function( $user ){
			return $user->data->user_login;
		}, $user_query->get_results() ) );

		exit;

	}

	/**
	 * Update metadata of a tournament.
	 */
	public function update_tournament_meta( $post_id ){

		// Not saving the metadata.
		if( !isset( $_POST['tournament_settings'] ) ) return;

		$ts = $_POST['tournament_settings'];
		if( !wp_verify_nonce( $ts['nonce'], 'tournament_settings' ) ) return;

		if( '' != trim( $ts['registration_deadline'] ) ) update_post_meta( $post_id, '_tournament_setting_registration_deadline', $ts['registration_deadline'] );

		if( in_array( $ts['registration'], ['public', 'private'] ) ) update_post_meta( $post_id, '_tournament_setting_registration', $ts['registration'] );

		if( is_array( $ts['rounds'] ) ) update_post_meta( $post_id, '_tournament_setting_rounds', $ts['rounds'] );
		/**
		 Update cron jobs
		 */

	}

	/**
	 * Modify the tournament page to inject plugin codes.
	 *
	 * @var string $help_text Content given in wp_editor as help text.
	 */
	public function modify_tournament_page( $help_text ){

		global $post;

		$registration_deadline = strtotime( get_post_meta( $post->ID, '_tournament_setting_registration_deadline', true ) );
		

		ob_start();
		?>
			<div class="tournament-status">
				
				<p class="text-center">Some text to define what happens after countdown.</p>
				<div class="countdown" data-end-time="2016/06/21 14:27:28 +0600"></div>

			</div>
		<?php

		return ob_get_clean();

	}
}
new Gaming_Tournament;