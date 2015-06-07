<?php

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( !class_exists( 'LPR_Admin' ) ) {
	class LPR_Admin {
		/**
		 *  Constructor
		 */
		public function __construct() {

			add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
            require_once( dirname( __FILE__ ) . '/lpr-admin-functions.php' );
            $page           = isset( $_GET['page'] ) ? $_GET['page'] : '';
            if( 'learn_press_settings' == $page ) {
                $current_tab = isset($_GET['tab']) ? $_GET['tab'] : '';
                $tabs = learn_press_settings_tabs_array();


                if (!$current_tab || ($tabs && empty($tabs[$current_tab]))) {
                    if ($tabs) {
                        $tab_keys = array_keys($tabs);
                        $current_tab = reset($tab_keys);
                        wp_redirect(admin_url('options-general.php?page=learn_press_settings&tab=' . $current_tab));
                        exit();
                    }
                }
            }

		}

		/**
		 * Include any classes, functions we need within admin
		 */
		public function includes() {
			//Ajax Class
			include_once( 'class-admin-ajax.php' );
		}

		/**
		 * Enqueue admin scripts
		 */
		public function admin_scripts() {
            //LPR_Assets::enqueue_style( 'lpr-learnpress-css', LPR_CSS_URL . 'admin.css' );
            LPR_Admin_Assets::enqueue_style('jquery-tipsy', LPR_CSS_URL . 'tipsy.css' );
            LPR_Admin_Assets::enqueue_script('jquery-tipsy', LPR_JS_URL . 'jquery.tipsy.js' );
            return;
			wp_enqueue_style( 'lpr-learnpress-css', LPR_CSS_URL . 'admin.css' );
			wp_enqueue_style( 'lpr-fancy-box-css', LPR_CSS_URL . 'jquery.fancybox.css' );

			wp_enqueue_script( 'lpr-admin-js', LPR_JS_URL . 'admin.js', array( 'jquery' ) );
			wp_enqueue_script( 'lpr-admin-jquery-mousewheel-js', LPR_JS_URL . 'jquery.mousewheel-3.0.6.pack.js', array( 'jquery' ) );
			wp_enqueue_script( 'lpr-admin-fancy-box-js', LPR_JS_URL . 'jquery.fancybox.js', array( 'jquery' ) );
			wp_enqueue_script( 'lpr-admin-fancy-pack-js', LPR_JS_URL . 'jquery.fancybox.pack.js', array( 'jquery' ) );
		}

	}

	new LPR_Admin;
}