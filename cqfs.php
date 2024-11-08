<?php
/*
Plugin Name: Classic Quiz Feedback Survey
Plugin URI: https://github.com/amitbiswas06/classic-quiz-feedback-survey
Description: It's a classic plugin for quiz, feedback and survey.
Version: 1.0.1
Author: Amit Biswas
Author URI: https://templateartist.com/
License: GPLv2 and later
Text Domain: cqfs
Domain Path: /languages/
*/

//define namespace
namespace CQFS\ROOT;

define( 'CQFS_PATH', plugin_dir_path( __FILE__ ) );
define( 'CQFS_RESULT', 'cqfs-result');

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Main Classic Quiz Feedback Survey Class
 *
 * The main class that initiates and runs the plugin.
 *
 * @since 1.0.0
 */
class CQFS {

	/**
	 * Plugin Version
	 *
	 * @since 1.0.0
	 *
	 * @var string The plugin version.
	 */
	const CQFS_VERSION = '1.0.1';

	/**
	 * Minimum PHP Version
	 *
	 * @since 1.0.0
	 *
	 * @var string Minimum PHP version required to run the plugin.
	 */
	const MINIMUM_PHP_VERSION = '7.1.0';

	/**
	 * Instance
	 *
	 * @since 1.0.0
	 *
	 * @access private
	 * @static
	 *
	 * @var CQFS The single instance of the class.
	 */
	private static $_instance = null;

	/**
	 * Instance
	 *
	 * Ensures only one instance of the class is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 * @static
	 *
	 * @return CQFS An instance of the class.
	 */
	public static function instance() {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;

	}

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 */
	public function __construct() {

		add_action( 'init', [ $this, 'i18n' ] );
		add_action( 'plugins_loaded', [ $this, 'init' ] );

		// register custom post type
		require CQFS_PATH . 'inc/cpt.php';

		// add custom post type capabilities to admin
		require CQFS_PATH . 'inc/roles.php';

		// create a cqfs result page on plugin activation
		register_activation_hook( __FILE__, array( 'Cqfs_Roles', 'cqfs_result_page' ) );

		// set custom page templates
		add_filter( 'template_include', array( 'Cqfs_Roles', 'cqfs_set_custom_templates' ), 99 );

		// add custom capabilities to the admin on plugin activation
		register_activation_hook( __FILE__, array( 'Cqfs_Roles', 'add_caps_admin' ) );
		
		// remove custom capabilities of the admin on plugin deactivation
		register_deactivation_hook( __FILE__, array( 'Cqfs_Roles', 'remove_caps_admin' ) );

		// set logged in cookie immediately after ajax login
		add_action( 'set_logged_in_cookie', [$this, 'cqfs_update_logged_in_cookie'] );

	}

	/**
	 * Set logged in cookie immediately after login
	 * this helps to return wp nonce created after ajax login
	 */
	public function cqfs_update_logged_in_cookie( $logged_in_cookie ){
		$_COOKIE[LOGGED_IN_COOKIE] = $logged_in_cookie;
	}

	/**
	 * Load Textdomain
	 *
	 * Load plugin localization files.
	 *
	 * Fired by `init` action hook.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 */
	public function i18n() {

		load_plugin_textdomain( 'cqfs' );

	}

	/**
	 * Initialize the plugin
	 *
	 * Load the plugin only after checking.
	 * Checks for basic plugin requirements, if one check fail don't continue,
	 * if all check have passed load the files required to run the plugin.
	 *
	 * Fired by `plugins_loaded` action hook.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 */
	public function init() {

		// Check for required PHP version
		if ( version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '<' ) ) {
			add_action( 'admin_notices', [ $this, 'admin_notice_minimum_php_version' ] );
			return;
		}

		// admin menu pages
		require CQFS_PATH . 'admin/menu-pages.php';

		// cqfs_question metaboxes
		require CQFS_PATH . 'admin/meta-boxes/metabox-question.php';

		// cqfs_build metaboxes
		require CQFS_PATH . 'admin/meta-boxes/metabox-build.php';

		// cqfs_entry metaboxes
		require CQFS_PATH . 'admin/meta-boxes/metabox-entry.php';

		// admin columns
		require CQFS_PATH . 'inc/admin-columns.php';

		// admin scripts
		require CQFS_PATH . 'admin/admin-scripts.php';

		// utility class object
		require CQFS_PATH . 'inc/utilities.php';

		// build shortcode
		require CQFS_PATH . 'inc/shortcode.php';

		// enqueue scripts to front
		add_action('wp_enqueue_scripts', [$this, 'cqfs_enqueue_scripts']);

		// add login form for CQFS use
		add_action( 'wp_footer', [ 'CQFS\INC\UTIL\Utilities', 'cqfs_login_submit_form'] );

		// add send email form for cqfs_entry admin footer
		add_action('admin_footer', ['CQFS\INC\UTIL\Utilities', 'cqfs_entry_send_email_html']);

	}


	/**
	 * Admin notice
	 *
	 * Warning when the site doesn't have a minimum required PHP version.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 */
	public function admin_notice_minimum_php_version() {

		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}

		$message = sprintf(
			/* translators: 1: Plugin name 2: PHP 3: Required PHP version */
			esc_html__( '"%1$s" requires "%2$s" version %3$s or greater.', 'cqfs' ),
			'<strong>' . esc_html__( 'Classic Quiz Feedback Survey', 'cqfs' ) . '</strong>',
			'<strong>' . esc_html__( 'PHP', 'cqfs' ) . '</strong>',
			 self::MINIMUM_PHP_VERSION
		);

		printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );

	}


	public function cqfs_enqueue_scripts(){
		
		//for all types of CQFS form
		wp_enqueue_script(
			'cqfs-multi', 
			esc_url( plugin_dir_url(__FILE__) . 'assets/js/cqfs-multi.js'),
			'NULL',
			self::CQFS_VERSION,
			true
		);

		//localize script for front end
		wp_localize_script( 'cqfs-multi', '_cqfs',
			array( 
				'ajaxurl'		=> esc_url( admin_url( 'admin-ajax.php' ) ),
				'login_status'	=> is_user_logged_in(),
			)
		);

		//for localization of string in JS use in front
		$cqfs_thank_msg_feedback = apply_filters('cqfs_thankyou_msg_feedback', esc_html__('Thank you for your feedback.', 'cqfs'));
		$cqfs_thank_msg_survey = apply_filters('cqfs_thankyou_msg_survey', esc_html__('Thank you for your participation in the survey.', 'cqfs'));
		$you_ans = apply_filters('cqfs_result_you_answered', esc_html__('You answered&#58; ', 'cqfs'));
		$status = apply_filters('cqfs_result_ans_status', esc_html__('Status&#58; ', 'cqfs'));
		$note = apply_filters('cqfs_result_ans_note', esc_html__('Note&#58; ', 'cqfs'));

		//localize script for JS strings
		wp_localize_script( 'cqfs-multi', '_cqfs_lang',
			array( 
				'thank_msg_feedback'=> esc_html( $cqfs_thank_msg_feedback ),
				'thank_msg_survey'	=> esc_html( $cqfs_thank_msg_survey ),
				'invalid_result'	=> esc_html__('Invalid Result','cqfs'),
				'you_ans'			=> esc_html($you_ans),
				'status'			=> esc_html($status),
				'note'				=> esc_html($note),
			)
		);


		//style css enqueue for front end
		wp_enqueue_style(
			'cqfs-style',
			esc_url( plugin_dir_url(__FILE__) . 'assets/css/cqfs-styles.css'),
			NULL,
			self::CQFS_VERSION
		);

	}



}//class CQFS end

CQFS::instance();