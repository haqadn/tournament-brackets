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
	 * Used to store temporary data for each leaderboard column.
	 */
	private $leaderboard_row = false;
	
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
		add_action('wp_ajax_tournament-registration', [$this, 'register_player']);
		add_action('wp_ajax_get-match-result-modal', [$this, 'match_result_modal_content']);
		add_action('wp_ajax_update-match-result', [$this, 'update_match_result']);
		add_action('save_post', [$this, 'update_tournament_meta']);
		add_action('finish_round', [$this, 'process_round_result'], 10, 2);
		add_action('pre_get_posts', [$this, 'regionaly_restrict_tournament_view']);


		add_filter('the_content', [$this, 'modify_tournament_page'], 100);
		add_filter( 'no_texturize_shortcodes', [$this, 'exempt_wptexturize'] );

		register_activation_hook(__FILE__, [$this, 'plugin_activated']);

		add_shortcode('leaderboard', [$this, 'repeated_leaderboard_shortcode']);


		// Deal with the annoying wpautop on shortcode
		remove_filter( 'the_content', 'wpautop' );
		add_filter( 'the_content', 'wpautop' , 99);
		add_filter( 'the_content', 'shortcode_unautop',100 );

	}

	/**
	 * Shortcode output for leaderboard
	 */
	public function repeated_leaderboard_shortcode( $atts, $content ){
		global $wpdb;

		extract( shortcode_atts( [
		    'game'       => false,
		    'platform'   => false,
		    'start_time' => false,
		    'end_time'   => false
		], $atts ) );

		// In case there is no condition, we don't want a sql syntax error
		$conditions = ["1"];

		if( $game )
			$conditions[] = $wpdb->prepare( "`game`=%d", $game );
		if( $platform )
			$conditions[] = $wpdb->prepare( "`platform`=%d", $platform );
		if( $start_time )
			$conditions[] = $wpdb->prepare( "`timestamp`>=%s", date("Y-m-d H:i:s", strtotime( $start_time ) ) );
		if( $end_time )
			$conditions[] = $wpdb->prepare( "`timestamp`<=%s", date("Y-m-d H:i:s", strtotime( $end_time ) ) );

		$conditions = implode( " AND ", $conditions );

		$rows = $wpdb->get_results( "SELECT `player`, SUM(`point`) as total_points, COUNT(DISTINCT `tournament`, `round`) as matches_played, COUNT(DISTINCT `tournament`) as tournaments_played
			FROM {$wpdb->prefix}tournament_leaderboard
			WHERE $conditions
			GROUP BY `player`
			ORDER BY total_points DESC;" );

		ob_start();
		?>
		<table class="table table-striped leaderboard">
			<?php add_shortcode('col', [$this, 'leaderboard_column_names']); ?>
			<thead>
				<tr><?php echo do_shortcode(trim($content)); ?></tr>
			</thead>
			<?php remove_shortcode('col'); ?>
			<?php add_shortcode('col', [$this, 'leaderboard_column_values']); ?>
			<tbody>
				<?php
				$rank = 0; $prev_points = 0;
				foreach ($rows as $row){
					$row->userdata = get_userdata( $row->player );
					$row->rank = $prev_points != $row->total_points ? ++$rank : $rank;
					$this->leaderboard_row = $row;
					$prev_points = $row->total_points;

					echo "<tr>".do_shortcode(trim($content))."</tr>";
				}
				?>
			</tbody>
			<?php remove_shortcode('col'); ?>
		</table>
		<?php
		return ob_get_clean();
	}

	/**
	 * Prevent wptexturize from messing up the column shortcode tags.
	 */
	public function exempt_wptexturize( $shortcodes ){
		$shortcodes[] = 'leaderboard';

		return $shortcodes;
	}

	/**
	 * Shortcode that is used to output each of the leaderboard column values.
	 */
	public function leaderboard_column_values( $atts ){
		extract( shortcode_atts( [
			'field' => false
			], $atts ) );

		// return print_r( $this->leaderboard_row, 1 );

		if( !$field ) return '';

		switch ( $field ) {
			case 'rank':
				return "<td>".$this->leaderboard_row->rank."</td>";
				break;
			case 'name':
				return "<td>".$this->leaderboard_row->userdata->data->display_name."</td>";
				break;
			case 'id':
				return "<td>".$this->leaderboard_row->userdata->ID."</td>";
				break;
			case 'points':
				return "<td>".$this->leaderboard_row->total_points."</td>";
				break;
			case 'matches':
				return "<td>".$this->leaderboard_row->matches_played."</td>";
				break;
			case 'tournaments':
				return "<td>".$this->leaderboard_row->tournaments_played."</td>";
				break;
			case 'username':
			case 'userlogin':
			case 'gamertag':
				return "<td>".$this->leaderboard_row->userdata->data->user_login."</td>";
				break;
		}
	}

	/**
	 * Shortcode that is used to output each of the leaderboard column names.
	 */
	public function leaderboard_column_names( $atts ){
		extract( shortcode_atts( [
			'field' => false,
			'label' => false
			], $atts ) );

		if( !$field ) return '';
		if( $label ) return "<th>$label</th>";

		// If a level is not provided, try to get it from the field name.
		switch ( $field ) {
			case 'rank':
				return "<th>".__( "Rank", 'gt' )."</th>";
				break;
			case 'name':
				return "<th>".__( "Name", 'gt' )."</th>";
				break;
			case 'id':
				return "<th>".__( "ID", 'gt' )."</th>";
				break;
			case 'points':
				return "<th>".__( "Points", 'gt' )."</th>";
				break;
			case 'matches':
				return "<th>".__( "Matches Played", 'gt' )."</th>";
				break;
			case 'tournaments':
				return "<th>".__( "Tournaments Participated", 'gt' )."</th>";
				break;
			case 'username':
			case 'userlogin':
			case 'gamertag':
				return "<th>".__( "Gamer Tag", 'gt' )."</th>";
				break;
		}
	}

	/**
	 * Handle plugin installation.
	 *
	 * Prepare database for the plugin on activation.
	 */
	public function plugin_activated(){
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}tournament_leaderboard` (
			`ID` int(11) unsigned NOT NULL AUTO_INCREMENT,
			`player` int(11) DEFAULT NULL,
			`tournament` int(11) DEFAULT NULL,
			`round` int(11) DEFAULT NULL,
			`match` int(11) DEFAULT NULL,
			`game` int(11) DEFAULT NULL,
			`platform` int(11) DEFAULT NULL,
			`point` double DEFAULT NULL,
			`timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`ID`)
		) $charset_collate;";

		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

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
				$tournament_region       = get_post_meta( $post->ID, '_tournament_setting_region', true );
				$registration_deadline   = get_post_meta( $post->ID, '_tournament_setting_registration_deadline', true );
				$rounds                  = (array) get_post_meta( $post->ID, '_tournament_setting_rounds', true );
				?>
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row"><label><?php _e( 'Region', 'gt' ); ?></label></th>
							<td>
								<label>
									<input type="radio" name="tournament_settings[region]" value="us" <?php echo 'us' == $tournament_region ? 'checked="checked"' : '';?>> 
									<?php _e( 'United States', 'gt' ); ?>
								</label> 
								<label>
									<input type="radio" name="tournament_settings[region]" value="others" <?php echo 'others' == $tournament_region ? 'checked="checked"' : '';?>> 
									<?php _e( 'Others', 'gt' ); ?>
								</label>
								<label>
									<input type="radio" name="tournament_settings[region]" value="all" <?php echo 'all' == $tournament_region ? 'checked="checked"' : '';?>> 
									<?php _e( 'All', 'gt' ); ?>
								</label>
							</td>
						</tr>
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
											<th scope="col"><?php _e( 'Match Per Pair', 'gt' ); ?></th>
											<th scope="col"><?php _e( 'Total Players', 'gt' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td><?php _e( '1', 'gt' ); ?></td>
											<td><input type="number" name="tournament_settings[rounds][1][points]" class="form-field" value="<?php echo $rounds[1]['points']; ?>"></td>
											<td><input type="text" name="tournament_settings[rounds][1][end_date]" class="form-field time-date-picker" value="<?php echo $rounds[1]['end_date']; ?>"></td>
											<td><input type="text" name="tournament_settings[rounds][1][times]" value="<?php echo $rounds[1]['times']; ?>" class="form-field"></td>
											<td><?php _e( '2', 'gt' ); ?></td>
										</tr>
										<tr>
											<td><?php _e( '2', 'gt' ); ?></td>
											<td><input type="number" name="tournament_settings[rounds][2][points]" class="form-field" value="<?php echo $rounds[2]['points']; ?>"></td>
											<td><input type="text" name="tournament_settings[rounds][2][end_date]" class="form-field time-date-picker" value="<?php echo $rounds[2]['end_date']; ?>"></td>
											<td><input type="text" name="tournament_settings[rounds][2][times]" value="<?php echo $rounds[2]['times']; ?>" class="form-field"></td>
											<td><?php _e( '4', 'gt' ); ?></td>
										</tr>
										<tr>
											<td><?php _e( '3', 'gt' ); ?></td>
											<td><input type="number" name="tournament_settings[rounds][3][points]" class="form-field" value="<?php echo $rounds[3]['points']; ?>"></td>
											<td><input type="text" name="tournament_settings[rounds][3][end_date]" class="form-field time-date-picker" value="<?php echo $rounds[3]['end_date']; ?>"></td>
											<td><input type="text" name="tournament_settings[rounds][3][times]" value="<?php echo $rounds[3]['times']; ?>" class="form-field"></td>
											<td><?php _e( '8', 'gt' ); ?></td>
										</tr>
										<tr>
											<td><?php _e( '4', 'gt' ); ?></td>
											<td><input type="number" name="tournament_settings[rounds][4][points]" class="form-field" value="<?php echo $rounds[4]['points']; ?>"></td>
											<td><input type="text" name="tournament_settings[rounds][4][end_date]" class="form-field time-date-picker" value="<?php echo $rounds[4]['end_date']; ?>"></td>
											<td><input type="text" name="tournament_settings[rounds][4][times]" value="<?php echo $rounds[4]['times']; ?>" class="form-field"></td>
											<td><?php _e( '16', 'gt' ); ?></td>
										</tr>
										<tr>
											<td><?php _e( '5', 'gt' ); ?></td>
											<td><input type="number" name="tournament_settings[rounds][5][points]" class="form-field" value="<?php echo $rounds[5]['points']; ?>"></td>
											<td><input type="text" name="tournament_settings[rounds][5][end_date]" class="form-field time-date-picker" value="<?php echo $rounds[5]['end_date']; ?>"></td>
											<td><input type="text" name="tournament_settings[rounds][5][times]" value="<?php echo $rounds[5]['times']; ?>" class="form-field"></td>
											<td><?php _e( '32', 'gt' ); ?></td>
										</tr>
										<tr>
											<td><?php _e( '6', 'gt' ); ?></td>
											<td><input type="number" name="tournament_settings[rounds][6][points]" class="form-field" value="<?php echo $rounds[6]['points']; ?>"></td>
											<td><input type="text" name="tournament_settings[rounds][6][end_date]" class="form-field time-date-picker" value="<?php echo $rounds[6]['end_date']; ?>"></td>
											<td><input type="text" name="tournament_settings[rounds][6][times]" value="<?php echo $rounds[6]['times']; ?>" class="form-field"></td>
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


		wp_register_style( 'jquery-ui', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css' );
		wp_enqueue_style( 'jquery-ui' ); 

		wp_enqueue_style( 'jquery-modal', plugins_url( 'css/jquery.modal.min.css', __FILE__ ), [], '0.7.0' );
		wp_enqueue_script( 'jquery-modal', plugins_url( 'js/jquery.modal.min.js', __FILE__ ), ['jquery'], '0.7.0' );

		wp_enqueue_script( 'jquery-countdown', plugins_url( 'js/jquery.countdown.min.js', __FILE__ ), ['jquery'], '0.7.0' );
		wp_enqueue_script( 'jquery-ui-autocomplete' );

		wp_register_script( 'gaming-tournament', plugins_url( 'js/plugin.js', __FILE__ ), ['jquery', 'jquery-modal', 'jquery-countdown', 'jquery-ui-autocomplete'] );
		wp_enqueue_style( 'gaming-tournament', plugins_url( 'css/plugin.css', __FILE__ ) );
		
		$plugin_data = [
			'ajaxurl' => admin_url( 'admin-ajax.php' )
		];
		if( is_singular( 'tournament' ) ) {

			global $post;
			$plugin_data['tournament_id'] = $post->ID;
			$plugin_data['tournament_info'] = self::get_tournament_info( $post->ID );
			$plugin_data['current_user'] = get_current_user_id();
			$plugin_data['can_edit'] = current_user_can( 'edit_post', $post->ID );
			
		}

		wp_localize_script( 'gaming-tournament', 'gt_info', $plugin_data );
		wp_enqueue_script( 'gaming-tournament' );

	}

	/**
	 * Return json array of usernames to autocomplete.
	 */
	public function autocomplete_username(){

		$user_query = new WP_User_Query( ['search' => $_GET['term']."*"] );

		echo json_encode( array_map( function( $user ){
			return [ 'id' => $user->ID, 'label' => $user->data->user_login, 'value' => $user->data->user_login ];
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
		$old_rounds = (array) get_post_meta( $post_id, '_tournament_setting_rounds', true );

		if( !wp_verify_nonce( $ts['nonce'], 'tournament_settings' ) ) return;

		if( '' != trim( $ts['registration_deadline'] ) ) update_post_meta( $post_id, '_tournament_setting_registration_deadline', $ts['registration_deadline'] );

		if( in_array( $ts['registration'], ['public', 'private'] ) ) update_post_meta( $post_id, '_tournament_setting_registration', $ts['registration'] );

		if( in_array( $ts['region'], ['us', 'others', 'all'] ) ) update_post_meta( $post_id, '_tournament_setting_region', $ts['region'] );

		if( is_array( $ts['rounds'] ) ){
			$count = 0;
			foreach( $ts['rounds'] as $round ){
				if( '' == trim( $round['points'] ) || '' == trim( $round['end_date'] ) ) break;

				$count++;
			}

			for( $i = 1; $i <= $count; $i++ ){
				if( isset( $old_rounds[$i] ) ){
					$ts['rounds'][$i]['matches'] = (array) $old_rounds[$i]['matches'];
				}
				else {
					$ts['rounds'][$i]['matches'] = [];
				}

				
				$expected_matches = pow(2, $count-$i);
				if( $i == $count ) $expected_matches++;
				$matches_filled = count( $ts['rounds'][$i]['matches'] );

				if( $matches_filled < $expected_matches ) {

					$ts['rounds'][$i]['matches'] = array_merge( $ts['rounds'][$i]['matches'], array_fill( $matches_filled, $expected_matches - $matches_filled, [ 'p1' => false, 'p2' => false ] ) );

				}

				if( $matches_filled > $expected_matches ) {
					$ts['rounds'][$i]['matches'] = array_slice( $ts['rounds'][$i]['matches'], 0, $expected_matches );
				}
			}

			$ts['rounds']['count'] = $count;

			update_post_meta( $post_id, '_tournament_setting_rounds', $ts['rounds'] );
		}
		
		for( $i = 1; $i <= 6; $i++ ){
			wp_clear_scheduled_hook( 'finish_round', [ $post_id, $i ] );

			if( $i > $count ) continue;
			wp_schedule_single_event( strtotime( $ts['rounds'][$i]['end_date'] ), 'finish_round', [ $post_id, $i ] );
		}

	}

	/**
	 * Get information of a tournament.
	 * 
	 * @var int $tournament_id ID of the tournament.
	 */
	public static function get_tournament_info( $tournament_id ){

		$round_labels = [
			__( "First Round", "gt" ),
			__( "Round of 32", "gt" ),
			__( "Round of 16", "gt" ),
			__( "Quarter Final", "gt" ),
			__( "Semi Final", "gt" ),
			__( "Final", "gt" )
		];

		$tournament_registration = get_post_meta( $tournament_id, '_tournament_setting_registration', true );
		$registration_deadline   = get_post_meta( $tournament_id, '_tournament_setting_registration_deadline', true );
		$registered_players      = get_post_meta( $tournament_id, '_tournament_setting_registered_players', true );
		$registration_count      = get_post_meta( $tournament_id, '_tournament_setting_registration_count', true );
		$rounds                  = (array) get_post_meta( $tournament_id, '_tournament_setting_rounds', true );
		$matches                 = (array) get_post_meta( $tournament_id, '_tournament_setting_matches', true );
		$region                  = get_post_meta( $tournament_id, '_tournament_setting_region', true );

		for( $l = 6 - $rounds['count'], $r = 1; $r <= $rounds['count']; $l++, $r++ ){
			$rounds[$r]['label'] = 1 === $r ? $round_labels[0] : $round_labels[$l];
			$rounds[$r]['end_date'] = strtotime( $rounds[$r]['end_date'] );
		}

		return [
			'rounds' => $rounds,
			'registration_deadline' => strtotime( $registration_deadline ),
			'public_registration' => 'public' == $tournament_registration,
			'current_round' => self::get_current_round( $rounds ),
			'registration_count' => $registration_count,
			'registered_players' => $registered_players,
			'region' => $region
		];

	}

	/**
	 * Get the currently ongoing round for this tournament.
	 *
	 * @var mixed[] $rounds Array containing the round information.
	 */
	public static function get_current_round( $rounds ){

		$current_round = $rounds['count'] + 1;
		for( $l = 6 - $rounds['count'], $r = 1; $r <= $rounds['count']; $l++, $r++ ){
			if( $current_round != $rounds['count'] + 1 ) continue;

			if( $rounds[$r]['end_date'] > time() )
				$current_round = $r;
		}
		
		return strtotime( $registration_deadline ) > time() ? 0 : $current_round;
	}

	/**
	 * Modify the tournament page to inject plugin codes.
	 *
	 * @var string $help_text Content given in wp_editor as help text.
	 */
	public function modify_tournament_page( $help_text ){

		global $post;

		if( 'tournament' != $post->post_type ) return $help_text; // Not a tournament page.

		ob_start();

		$t_info = self::get_tournament_info( $post->ID );

		$registration_deadline = $t_info['registration_deadline'];
		$is_player = isset( $t_info['registered_players'][get_current_user_id()] );
		$can_edit = current_user_can( 'edit_post', $post->ID );
		$in_brackets_screen = 'brackets' == $_GET['screen'];
		$reg_open = ( time() < $t_info['registration_deadline'] && ( $can_edit || $t_info['public_registration'] ) );

		if( !is_single() ){
			?>
			
			<p>
				<strong><?php _e( 'Tournament Status:', 'gt' ); ?></strong>
				<?php 
					if( $reg_open ) {
						echo "<span class='tournament-status-text tournament-registering'>";
						_e( 'Open for registration', 'gt' );
						echo "</span>";
					}
					elseif( $t_info['current_round'] <= $t_info['rounds']['count'] || -1 == $t_info['current_round'] ) {
						echo "<span class='tournament-status-text tournament-running'>";
						_e( 'Running', 'gt' );
						echo "</span>";
					}
					else {
						echo "<span class='tournament-status-text tournament-finished'>";
						_e( 'Finished', 'gt' );
						echo "</span>";
					}
				?>

				<br>

				<?php if( $reg_open ): ?>
					<strong><?php _e( 'Number of Registrations:', 'gt' ); ?></strong>
					<?php echo $t_info['registration_count']; ?>
				<?php endif ?>

			</p>
			<?php
			if( $t_info['current_round'] > $t_info['round']['count'] ){
				$final_round = $t_info['rounds'][$t_info['rounds']['count']]['matches'];
				$champion = $final_round[0]['winner'];
				if( $final_round[0]['p1'] == $champion )
					$runner_up = $final_round[0]['p2'];
				elseif( null != $champion )
					$runner_up = $final_round[0]['p1'];

				$second_runner_up = $final_round[1]['winner'];
				$champion = get_userdata( $champion );
				$runner_up = get_userdata( $runner_up );
				$second_runner_up = get_userdata( $second_runner_up );
				
				if( $champion || $runner_up || $second_runner_up ): ?>
					<h2><?php _e( 'Results', 'gt' ); ?></h2>
					<p>
						<?php if( $champion ){ ?>
							<strong><?php _e( 'Champion:', 'gt' ); ?></strong>
							<?php
							echo "<span class='tournament-champion'>";
							echo $champion->data->user_login;
							echo "</span>";
							?>
						<?php } ?>
						<br>
						<?php if( $runner_up ){ ?>
							<strong><?php _e( 'Runner Up:', 'gt' ); ?></strong>
							<?php
							echo "<span class='tournament-runner_up'>";
							echo $runner_up->data->user_login;
							echo "</span>";
							?>
						<?php } ?>
						<br>
						<?php if( $second_runner_up ){ ?>
							<strong><?php _e( 'Second Runner Up:', 'gt' ); ?></strong>
							<?php
							echo "<span class='tournament-second_runner_up'>";
							echo $second_runner_up->data->user_login;
							echo "</span>";
							?>
						<?php } ?>
					</p>
				<?php endif;
			}
			?>

			<?php
			return;
		}

		if( !$can_edit && !$is_player && !( $can_edit && $in_brackets_screen ) && $reg_open ){
			?>
			<div class="tournament-status">
				
				<p class="text-center"><?php _e( 'Registration ends in:', 'gt' ); ?></p>
				<div class="countdown text-center" data-end-time="<?php echo date( 'Y-m-d H:i:s O', $t_info['registration_deadline'] ); ?>"></div>
				<br>

				<p class="text-center">
					<?php if( current_user_can( 'edit_post', $post->ID ) ): ?>
						<?php if( $t_info['registration_count'] < pow(2, $t_info['rounds']['count']) ): ?>
							<a class="register btn btn-primary" rel="modal:open" href="#tournament-registration-form"><?php _e( 'Add Player', 'gt' ); ?></a>
							 | 
						<?php endif; ?>
						<a href="<?php echo add_query_arg( 'screen', 'brackets' ); ?>" class="register btn btn-default"><?php _e( 'Show Brackets', 'gt' ); ?></a>
					<?php elseif( is_user_logged_in() && $t_info['registration_count'] < pow(2, $t_info['rounds']['count']) ): ?>
						<a class="register btn btn-primary" rel="modal:open" href="#instructions"><?php _e( 'Register', 'gt' ); ?></a>
					<?php else : ?>
						<?php printf( __( 'You must be <a href="%s">logged in</a> to perticipate.', 'gt' ), wp_login_url( get_permalink() ) ); ?>
					<?php endif; ?>
				</p>

				<div id="instructions" style="display:none">
					<?php echo $help_text; ?>
					<hr>
					<a class="register btn btn-primary" rel="modal:open" href="#tournament-registration-form"><?php _e( 'Continue', 'gt' ); ?></a>
				</div>

				<form id="tournament-registration-form" method="GET" action="<?php echo admin_url('admin-ajax.php'); ?>" style="display:none">
					<p>
						<label for="team-url"><?php _e( 'My Team URL', 'gt' ); ?></label>
						<input type="url" name="team_url" id="team-url" placeholder="http://example.com" />
					</p>
					<?php if( current_user_can( 'edit_post', $post->ID ) ): ?>
					<p>
						<label for="username"><?php _e( 'Username', 'gt' ); ?></label>
						<input type="text" name="username" id="username" />
					</p>
					<?php endif; ?>
					<p class="text-center">
						<button type="submit"><?php _e( 'Submit', 'gt' ); ?></button>
					</p>

					<p class="text-center result hidden"></p>
					
					<input type="hidden" name="action" value="tournament-registration">
					<input type="hidden" name="tournament_id" value="<?php echo $post->ID; ?>">
					<?php wp_nonce_field( 'tournament-registration', 'registration_nonce' ); ?>
				</form>

			</div>
			<?php
		}
		else {
			if( $t_info['current_round'] <= $t_info['rounds']['count'] && $t_info ){
				if( $t_info['current_round'] == 0 ){
					$timeup = $t_info['rounds'][1]['end_date'];
					$round_name = $t_info['rounds'][1]['label'];
				}
				else {
					$timeup = $t_info['rounds'][$t_info['current_round']]['end_date'];
					$round_name = $t_info['rounds'][$t_info['current_round']]['label'];
				}
				?>

					<p class="text-center"><?php printf( _x( '%s ends in:', 'round name ends in', 'gt' ), $round_name ); ?></p>
					<p class="countdown text-center" data-end-time="<?php echo date( 'Y-m-d H:i:s O', $timeup ); ?>"></p>

				<?php
			}
			self::show_brackets( $post->ID );
		}
		?>
			
		<?php

		return ob_get_clean();

	}

	/**
	 * Show brackets of a tournament.
	 *
	 * @var int $tid ID of the tournament post.
	 */
	public static function show_brackets( $tid ){

		$t_info = self::get_tournament_info( $tid );

		?>

		<div class="brackets_container">
			<table class="t_of_<?php echo pow(2, $t_info['rounds']['count']);?>">
				<thead>
					<tr>
						<?php for( $i = 1; $i <= $t_info['rounds']['count']; $i++ ): ?>
						<th>
							<span><?php echo $t_info['rounds'][$i]['label']; ?></span>
						</th>
						<?php endfor; ?>
						<?php for( $i = $t_info['rounds']['count'] - 1; $i >= 1; $i-- ): ?>
						<th>
							<span><?php echo $t_info['rounds'][$i]['label']; ?></span>
						</th>
						<?php endfor; ?>
					</tr>
				</thead>
				<tbody>
					<tr class="playground">
						<?php

						for( $i = 1; $i < $t_info['rounds']['count']; $i++ ){
							$matches_in_round = count( $t_info['rounds'][$i]['matches'] );
							self::show_bracket_column( $t_info['rounds'], $i, 0, $matches_in_round/2 - 1 );
						}

						self::show_bracket_column( $t_info['rounds'], $t_info['rounds']['count'], 0, 1 );

						for( $i = $t_info['rounds']['count'] - 1; $i >= 1; $i-- ){
							$matches_in_round = count( $t_info['rounds'][$i]['matches'] );
							self::show_bracket_column( $t_info['rounds'], $i, $matches_in_round/2, $matches_in_round - 1, true );
						}
						?>
					</tr>
				</tbody>
			</table>

		</div>

		<?php
	}

	/**
	 *	Output a column in the tournament brackets.
	 */
	public static function show_bracket_column( $rounds, $current_round, $start = 0, $end = 0, $reversed = false ){
		$round_of = pow( 2, $rounds['count'] - ($current_round - 1) );
		?>
		<td class="round_column r_<?php echo $round_of;?> <?php if( $reversed ) echo ' reversed '; if( 2 == $round_of ) echo ' final '; ?>" data-round="<?php echo $current_round; ?>">

			<?php if( 2 == $round_of && isset( $rounds[$current_round]['matches'][0]['winner'] ) && null != $rounds[$current_round]['matches'][0]['winner'] ): ?>
				<?php $winner_player = get_userdata( $rounds[$current_round]['matches'][0]['winner'] ) ?>
				<div class="winner_team">
					<span>
						<?php _e( 'WINNER', 'gt' ); ?>
						<a href="<?php echo $t_info['registered_players'][$player_1->ID]['team_url']; ?>">
							<span><?php echo $winner_player->get('user_login'); ?></span>
						</a>
					</span>
				</div>
			<?php endif; ?>
			
			<?php for( $j = $start; $j <= $end; $j++ ): ?>
				<?php $class = $round_of == 2 && $j == $end ? 'third_position' : ''; ?>
				<?php self::show_match( $rounds[$current_round]['matches'][$j], $current_round, $j, $class ); ?>
			<?php endfor; ?>
			
		</td>
		<?php
	}

	/**
	 * Output a match bracket.
	 *
	 * @var mixed[] $players Array of all the players.
	 * @var int $round Round number of the round this match belongs to.
	 * @var int $j index of the match.
	 * @var string $container_class class for the match container.
	 */
	public static function show_match( $match, $round, $j, $container_class = '' ){
		global $post;
		$t_info = self::get_tournament_info( $post->ID );

		if( isset( $match['p1'] ) && $match['p1'] ){
			$player_1 = get_userdata( $match['p1'] );
			$p1_name = $player_1->get('user_login');
			$p1_class = [];
			if( isset( $match['winner'] ) && $match['winner'] != null ){
				$p1_class[] = $match['winner'] == $player_1->ID ? 'winner' : 'looser';

				if( $t_info['rounds']['count'] == $round ){
					// Final round
					if( 0 == $j ){
						// Final match
						$p1_class[] = $match['winner'] == $player_1->ID ? 'first' : 'second';
					}
					elseif( 1 == $j ){
						// Third place match
						$p1_class[] = $match['winner'] == $player_1->ID ? 'third' : 'fourth';
					}
				}
			}

			$p1_class = implode( ' ', $p1_class );

			$p1_goals = isset( $match['report']['visible'] ) ? $match['report']['visible']['p1'] : '';

		}
		else {
			$player_1 = false;
			$p1_name = $t_info['current_round'] < $round ? _x( 'TBD', 'To be decided', 'gt' ) : '';
			$p1_class = '';
		}

		if( isset( $match['p2'] ) && $match['p2'] ){
			$player_2 = get_userdata( $match['p2'] );
			$p2_name = $player_2->get('user_login');
			$p2_class = [];
			if( isset( $match['winner'] ) && $match['winner'] != null ){
				$p2_class[] = $match['winner'] == $player_2->ID ? 'winner' : 'looser';

				if( $t_info['rounds']['count'] == $round ){
					// Final round
					if( 0 == $j ){
						// Final match
						$p2_class[] = $match['winner'] == $player_2->ID ? 'first' : 'second';
					}
					elseif( 1 == $j ){
						// Third place match
						$p2_class[] = $match['winner'] == $player_2->ID ? 'third' : 'fourth';
					}
				}
			}

			$p2_class = implode( ' ', $p2_class );

			$p2_goals = isset( $match['report']['visible'] ) ? $match['report']['visible']['p2'] : '';
		}
		else {
			$player_2 = false;
			$p2_name = $t_info['current_round'] < $round ? _x( 'TBD', 'To be decided', 'gt' ) : '';
			$p2_class = '';
		}
		?>
		<div class="mtch_container <?php echo $container_class; ?>" data-match-index="<?php echo $j; ?>">
			<div class="match_unit">
				<!--Match unite consist of top(.m_top) and bottom(.m_botm) teams with class (.winner) or (.loser) added-->
				<div class="m_segment m_top <?php echo $p1_class; ?>" <?php echo $player_1 ? 'data-team-id="'.$player_1->ID.'"' : '' ?>>
					<span>
						<a <?php echo $player_1 ? 'href="'.$t_info['registered_players'][$player_1->ID]['team_url'].'" target="_blank"' : 'href="#"'; ?>>
							<span><?php echo $p1_name; ?></span>
						</a>
						<strong><?php echo $p1_goals; ?></strong>
					</span>
				</div>
				<div class="m_segment m_botm <?php echo $p2_class; ?>" <?php echo $player_2 ? 'data-team-id="'.$player_2->ID.'"' : '' ?>>
					<span>
						<a <?php echo $player_2 ? 'href="'.$t_info['registered_players'][$player_2->ID]['team_url'].'" target="_blank"' : 'href="#"'; ?>>
							<span><?php echo $p2_name; ?></span>
						</a>
						<strong><?php echo $p2_goals; ?></strong>
					</span>
				</div>
				<!-- <div class="m_dtls">
					Match date and time
					<span></span>
				</div> -->
			</div>
		</div>
		<?php
	}

	/**
	 * Find the next sequence to register.
	 *
	 * @var int $s Number to start from.
	 * @var int $e Number to end on.
	 */
	private function get_registration_sequence( $s, $e ){
		if( $s == $e ) return [$s]; // If only one, just return it.

		// Divide the sequence in two.
		$s1 = $s;
		$e2 = $e;
		$e1 = floor(($e+$s)/2);
		$s2 = $e1+1;

		$seq1 = $this->get_registration_sequence( $s1, $e1 );
		$seq2 = $this->get_registration_sequence( $s2, $e2 );

		//Combine the sequences
		$combo_sequence = [];
		for( $i = 0; $i < ($s+$e)/2; $i++ ){
			$combo_sequence[] = $seq1[$i];
			$combo_sequence[] = $seq2[$i];
		}

		return $combo_sequence;
	}

	private function ajax_response( $message, $success = false ){
		echo json_encode( ['success' => $success, 'message' => $message] );
		exit;
	}

	/**
	 * Handle the request to register a player on a tournament.
	 */
	public function register_player(){

		if( !wp_verify_nonce( $_GET['registration_nonce'], 'tournament-registration') )
			$this->ajax_response( __( 'Invalid request.', 'gt' ) );

		if( !is_user_logged_in() )
			$this->ajax_response( __( 'You must be a logged in user to register.', 'gt' ) );

		$t_info = self::get_tournament_info( $_GET['tournament_id'] );
		if( time() > $t_info['registration_deadline'] )
			$this->ajax_response( __( 'Registration has been closed.', 'gt' ) );

		if( $t_info['registration_count'] >= pow(2, $t_info['rounds']['count']) )
			$this->ajax_response( __( 'Registration quote over.', 'gt') );

		$sequence = $this->get_registration_sequence( 0, pow(2, $t_info['rounds']['count'])-1 );
		$rounds = get_post_meta( $_GET['tournament_id'], '_tournament_setting_rounds', true );
		$match_index = $sequence[$t_info['registration_count']]/2;

		$p = floor($match_index) == $match_index ? 'p1' : 'p2'; // Player one or two of the match
		$match_index = floor($match_index);

		if( current_user_can( 'edit_post', $_GET['tournament_id'] ) ){
			$player = get_user_by( 'login', $_GET['username'] );

			if( !$player )
				$this->ajax_response( __( 'User does not exist.', 'gt' ) );

			$player_id = $player->ID;
		}
		else {
			$player_id = get_current_user_id();
		}

		if( isset( $t_info['registered_players'][$player_id] ) ){
			$this->ajax_response( __( 'Player already registered.', 'gt' ) );
		}

		$rounds[1]['matches'][$match_index][$p] = $player_id;
		$t_info['registration_count']++;
		$t_info['registered_players'][$player_id] = ['ID' => $player_id, 'team_url' => $_GET['team_url']];

		update_post_meta( $_GET['tournament_id'], '_tournament_setting_registration_count', $t_info['registration_count'] );
		update_post_meta( $_GET['tournament_id'], '_tournament_setting_registered_players', $t_info['registered_players'] );
		update_post_meta( $_GET['tournament_id'], '_tournament_setting_rounds', $rounds );

		$this->ajax_response( __( 'Player registered', 'gt' ), true );
	}

	/**
	 * Update match result.
	 * Update result of a match through ajax request.
	 */
	public function update_match_result() {
		extract( $_POST );

		if( !wp_verify_nonce( $nonce, "match-$tournament_id-$round-$match_index-update" ) )
			$this->ajax_response( __( 'Invalid request.', 'gt' ) );

		$user = get_current_user_id();

		// $t_info = self::get_tournament_info( $tournament_id );
		$rounds = get_post_meta( $tournament_id, '_tournament_setting_rounds', true );
		$match = $rounds[$round]['matches'][$match_index];
		$times = (int) $rounds[$round]['times'];

		if( $match['p1'] != $user && $match['p2'] != $user && !current_user_can( 'edit_post', $tournament_id ) )
			$this->ajax_response( __( 'You are not allowed to update the report of this match.', 'gt' ) );

		if( $match['p1'] == $user ){
			$p = 'p1';
		}

		if( $match['p2'] == $user ){
			$p = 'p2';
		}

		if( current_user_can( 'edit_post', $tournament_id ) )
			$p = 'admin';
		
		if( isset( $match['report']['admin'] ) && !current_user_can( 'edit_post', $tournament_id ) )
			$this->ajax_response( __( 'Match report fixed by admin.', 'gt' ) );

		if( $this->get_current_round( $rounds ) > $round && !current_user_can( 'edit_post', $tournament_id ) )
			$this->ajax_response( __( 'Past round results cannot be changed.', 'gt' ) );

		$score = ['p1' => 0, 'p2' => 0];
		for( $i = 1; $i <= $times; $i++ ){
			if( current_user_can( 'edit_post', $tournament_id ) ){

				if( $match_result['p1'][$i] > $match_result['p2'][$i] )
					$score['p1']++;
				else if( $match_result['p1'][$i] < $match_result['p2'][$i] )
					$score['p2']++;
			}
		}


		if( $score['p1'] == $score['p2'] ) {
			$this->ajax_response( __( 'There must be a winner.', 'gt' ) );
		}

		$rounds[$round]['matches'][$match_index]['report'][$p] = $match_result;
		if( isset( $score ) ){
			$rounds[$round]['matches'][$match_index]['report']['score'] = $score;
		}

		update_post_meta( $tournament_id, '_tournament_setting_rounds', $rounds );

		$this->process_match_result( $tournament_id, $round, $match_index );

		$this->ajax_response( __( 'Report saved.', 'gt' ), true );
	}

	/**
	 * Process all the matches of a round.
	 * This should be called after a round has ended. Usually through a cron job.
	 */
	public static function process_round_result( $tournament_id, $round ){
		$rounds = get_post_meta( $tournament_id, '_tournament_setting_rounds', true );
		$count = $rounds['count'];
		$matches_in_round = pow( 2, $count - $round );

		for( $i = 0; $i < $matches_in_round; $i++ ){
			self::process_match_result( $tournament_id, $round, $i );	
		}
		if( $round == $count ){
			// Final round
			self::process_match_result( $tournament_id, $round, 1 );
		}
	}

	/**
	 * Process a match result and promotes the winner to next level.
	 */
	public static function process_match_result( $tournament_id, $round, $match ){
		
		if( get_post_type( $tournament_id ) != 'tournament' ) return;

		$rounds = get_post_meta( $tournament_id, '_tournament_setting_rounds', true );
		$count = $rounds['count'];
		$match_ar = &$rounds[$round]['matches'][$match];
		$times = $rounds[$round]['times'];

		// If admin has set any report, that is final.
		if( isset( $match_ar['report']['admin'] ) ){

			$score = ['p1' => 0, 'p2' => 0];
			for( $i = 1; $i <= $times; $i++ ){
				if( $match_ar['report']['admin']['p1'][$i] > $match_ar['report']['admin']['p2'][$i] ) $score['p1']++;
				elseif( $match_ar['report']['admin']['p1'][$i] < $match_ar['report']['admin']['p2'][$i] ) $score['p2']++;
			}

			$match_ar['report']['score'] = $score;
			$match_ar['report']['visible'] = 1 == $times ? [ 'p1' => $match_ar['report']['admin']['p1'][1], 'p2' => $match_ar['report']['admin']['p2'][1] ] : $match_ar['report']['score'];
			
			if( $score['p1'] > $score['p2'] ) $winner = $match_ar['p1'];
			else if( $score['p2'] > $score['p1'] ) $winner = $match_ar['p2'];

		}
		else {
			// If a player is matched against an empty space.
			if( $match_ar['p1'] && !$match_ar['p2'] ){
				$winner = $match_ar['p1'];
			}
			elseif( !$match_ar['p1'] && $match_ar['p2'] ){
				$winner = $match_ar['p2'];
			}
			else {
				// Player 1 reported and player 2 did not.
				if( isset( $match_ar['report']['p1'] ) && !isset( $match_ar['report']['p2'] ) ){

					$score = ['p1' => 0, 'p2' => 0];
					for( $i = 1; $i <= $times; $i++ ){
						if( $match_ar['report']['p1']['p1'][$i] > $match_ar['report']['p1']['p2'][$i] ) $score['p1']++;
						elseif( $match_ar['report']['p1']['p1'][$i] < $match_ar['report']['p1']['p2'][$i] ) $score['p2']++;
					}

					$match_ar['report']['score'] = $score;
					$match_ar['report']['visible'] = 1 == $times ? [ 'p1' => $match_ar['report']['p1']['p1'][1], 'p2' => $match_ar['report']['p1']['p2'][1] ] : $match_ar['report']['score'];
					if( $score['p1'] > $score['p2'] ) $winner = $match_ar['p1'];
					else $winner = null;
				}
				// Player 2 reported and player 1 did not.
				elseif( !isset( $match_ar['report']['p1'] ) && isset( $match_ar['report']['p2'] ) ){

					$score = ['p1' => 0, 'p2' => 0];
					for( $i = 1; $i <= $times; $i++ ){
						if( $match_ar['report']['p2']['p1'][$i] > $match_ar['report']['p2']['p2'][$i] ) $score['p1']++;
						elseif( $match_ar['report']['p2']['p1'][$i] < $match_ar['report']['p2']['p2'][$i] ) $score['p2']++;
					}

					$match_ar['report']['score'] = $score;
					$match_ar['report']['visible'] = 1 == $times ? [ 'p1' => $match_ar['report']['p2']['p1'][1], 'p2' => $match_ar['report']['p2']['p2'][1] ] : $match_ar['report']['score'];
					if( $score['p2'] > $score['p1'] ) $winner = $match_ar['p2'];
					else $winner = null;
				}
				// Both players reported.
				elseif( isset( $match_ar['report']['p1'] ) && isset( $match_ar['report']['p2'] ) ){
					if( $match_ar['report']['p1'] != $match_ar['report']['p2'] ) {
						$winner = null;
					}
					else {
						$score = ['p1' => 0, 'p2' => 0];
						for( $i = 1; $i <= $times; $i++ ){
							if( $match_ar['report']['p1']['p1'][$i] > $match_ar['report']['p1']['p2'][$i] ) $score['p1']++;
							elseif( $match_ar['report']['p1']['p1'][$i] < $match_ar['report']['p1']['p2'][$i] ) $score['p2']++;
						}

						$match_ar['report']['score'] = $score;
						$match_ar['report']['visible'] = 1 == $times ? [ 'p1' => $match_ar['report']['p1']['p1'][1], 'p2' => $match_ar['report']['p1']['p2'][1] ] : $match_ar['report']['score'];
						if( $score['p1'] > $score['p2'] ) $winner = $match_ar['p1'];
						else if( $score['p2'] > $score['p1'] ) $winner = $match_ar['p2'];
					}
				}
			}
		}

		// Remove any previous point for this match
		global $wpdb;
		$wpdb->delete( $wpdb->prefix.'tournament_leaderboard', [
				'tournament' => $tournament_id,
				'round'      => $round,
				'match'      => $match
			],
			[
				'%d',
				'%d',
				'%d'
			] );

		if( isset( $winner ) ){
			$match_ar['winner'] = $winner;
			if( $winner == $match_ar['p1'] && $match_ar['p1'] ) $looser = $match_ar['p2'];
			elseif( $winner == $match_ar['p2'] && $match_ar['p2'] ) $looser = $match_ar['p1'];

			if( $round != $count ){
				// Not final round
				$next_match_index = $match/2;
				$next_match = &$rounds[$round+1]['matches'][floor( $next_match_index )];
				
				if( $next_match_index == floor( $next_match_index ) ) {
					$next_match['p1'] = $winner;	
				}
				else {
					$next_match['p2'] = $winner;
				}
			}

			if( $count - 1 == $round ){
				// Semi Final

				$third_position_match = &$rounds[$round+1]['matches'][1];
				
				if( $match%2 == 0 ) {
					$third_position_match['p1'] = $looser;	
				}
				else {
					$third_position_match['p2'] = $looser;
				}
			}


			$games = wp_get_post_terms( $tournament_id, 'game', ['fields' => 'ids'] );
			$platforms = wp_get_post_terms( $tournament_id, 'platform', ['fields' => 'ids'] );

			if( !empty( $games ) )
				$game = $games[0];
			else
				$game = 0;

			if( !empty( $platforms ) )
				$platform = $platforms[0];
			else
				$platform = 0;

			if( null != $winner && $winner ){

				// Give points to the winner
				$wpdb->insert( $wpdb->prefix.'tournament_leaderboard', [
					'player'     => $winner,
					'tournament' => $tournament_id,
					'round'      => $round,
					'match'      => $match,
					'game'       => $game,
					'platform'   => $platform,
					'point'      => $rounds[$round]['points'],
					'timestamp'  => current_time( 'mysql' )
					] );
			}
			if( isset( $looser ) && $looser ){
				// Keep and entry for the looser as well just for the records
				$wpdb->insert( $wpdb->prefix.'tournament_leaderboard', [
					'player'     => $looser,
					'tournament' => $tournament_id,
					'round'      => $round,
					'match'      => $match,
					'game'       => $game,
					'platform'   => $platform,
					'point'      => 0,
					'timestamp'  => current_time( 'mysql' )
					] );
			}

		}

		update_post_meta( $tournament_id, '_tournament_setting_rounds', $rounds );

	}

	/**
	 * Outputs the content for match result modal.
	 */
	public function match_result_modal_content() {
		extract( $_GET );

		$t_info = self::get_tournament_info( $tournament_id );
		$times = (int) $t_info['rounds'][$round]['times'];
		$match = $t_info['rounds'][$round]['matches'][$match_index];
		$p1 = get_userdata( $match['p1'] );
		$p2 = get_userdata( $match['p2'] );

		if( $p1->ID == get_current_user_id() ){
			$p = 'p1';
			$o = 'p2';
		}
		elseif( $p2->ID == get_current_user_id() ){
			$p = 'p2';
			$o = 'p1';
		}
		elseif( !current_user_can( 'edit_post', $tournament_id ) ){
			$this->ajax_response( __( 'You are not welcome.', 'gt' ) );
		}

		if( isset( $match['report']['admin'] ) )
			$report = $match['report']['admin'];
		elseif( isset( $match['report'][$p] ) )
			$report = $match['report'][$p];
		elseif( isset( $match['report'][$o] ) )
			$report = $match['report'][$o];
		else
			$report = ['p1' => array_fill( 1, $times, '' ), 'p2' => array_fill( 1, $times, '' )];

		?>
		<form class="match-result text-center" action="<?php admin_url('admin-ajax.php'); ?>" method="POST">
			<?php for( $i = 1; $i <= $times; $i++ ): ?>
			<div class="match">
				<?php if( 1 != $times): ?>
					<h1><?php printf( __( 'Match: %d', 'gt' ), $i ); ?></h1>
				<?php endif; ?>
			
				<label class="username">
					<?php echo $p1->data->user_login; ?>
					<input type="number" name="match_result[p1][<?php echo $i;?>]" <?php echo '' != $report['p1'][$i] ? 'value="'.$report['p1'][$i].'"' : '';?>>
				</label>
				<div>vs.</div>
				<label class="username">
					<?php echo $p2->data->user_login; ?>
					<input type="number" name="match_result[p2][<?php echo $i;?>]" <?php echo '' != $report['p2'][$i] ? 'value="'.$report['p2'][$i].'"' : '';?>>
				</label>
			</div>
			<?php endfor; ?>


			<?php if( !isset( $match['report']['admin'] ) || current_user_can( 'edit_post', $tournament_id ) ): ?>
			<button type="submit"><?php _e( 'Submit', 'gt' ); ?></button>
			<p class="response hidden"></p>
			<input type="hidden" name="action" value="update-match-result">
			<input type="hidden" name="tournament_id" value="<?php echo $tournament_id; ?>">
			<input type="hidden" name="round" value="<?php echo $round; ?>">
			<input type="hidden" name="match_index" value="<?php echo $match_index; ?>">
			<?php wp_nonce_field( "match-$tournament_id-$round-$match_index-update", 'nonce' ); ?>
			<?php endif; ?>
		</form>
		<?php
		exit;
	}

	/**
	 * Filter tournaments on archive pages based on which region it is being viewed through
	 */
	public function regionaly_restrict_tournament_view( $query ){
		if( get_query_var( 'post_type' ) != 'tournament' ) return;
		if( current_user_can( 'edit_others_posts') ) return;
		include_once("geoip.inc");


		$gi = geoip_open(__DIR__."/GeoIP.dat", GEOIP_STANDARD);
		$is_us = geoip_country_code_by_addr($gi, $this->get_the_user_ip()) == 'US';

		if( $is_us ){
			$exclude_location = 'others';
		}
		else {
			$exclude_location = 'us';
		}

		$query->set('meta_query', [
			[
				'key'     => '_tournament_setting_region',
				'value'   => $exclude_location,
				'compare' => '!='
			]]);
	}

	/**
	 * Retuns current user ip.
	 */
	function get_the_user_ip() {
		if ( !empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			//check ip from share internet
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			//to check ip is pass from proxy
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		return $ip;
	}
}
new Gaming_Tournament;