<?php
/**
 * LearnPress Course Functions
 *
 * Common functions to manipulate with course, lesson, quiz, questions, etc...
 * Author foobla
 * Created Mar 18 2015
 */

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Get number of lesson in one course
 *
 * @param $course_id
 *
 * @return int
 */
function lpr_get_number_lesson( $course_id ) {
	$number_lesson     = 0;
	$course_curriculum = get_post_meta( $course_id, '_lpr_course_lesson_quiz', true );
	if ( $course_curriculum ) {
		foreach ( $course_curriculum as $section ) {
			$number_lesson += sizeof( $section['lesson_quiz'] );
		}
	}
	return $number_lesson;
}

/**
 * Get final quiz for the course using final quiz assessment
 * [Modified by TuNguyen on May 18 2015]
 *
 * @param  int $course_id
 *
 * @return int
 */
function lpr_get_final_quiz( $course_id ) {
	$final = false;
	if ( get_post_meta( $course_id, '_lpr_course_final', true ) == 'yes' ) {
		$course_curriculum = get_post_meta( $course_id, '_lpr_course_lesson_quiz', true );
		if ( $course_curriculum ) {
			$last_section = end( $course_curriculum );
			if ( $last_section && !empty( $last_section['lesson_quiz'] ) && $lesson_quiz = $last_section['lesson_quiz'] ) {
				$final = end( $lesson_quiz );
				if ( 'lpr_quiz' != get_post_type( $final ) ) {
					$final = false;
				}
			}
		}
	}
	return $final;
}

/**
 * Calculate the progress of a student in a course
 * [Modified by TuNguyen on May 18 2015]
 *
 * @param $course_id
 *
 * @return float|int
 */
function lpr_course_evaluation( $course_id ) {
	$user_id          = get_current_user_id();
	$lesson_completed = get_user_meta( $user_id, '_lpr_lesson_completed', true );

	$number_lesson = sizeof( learn_press_get_lessons_in_course( $course_id ) );//lpr_get_number_lesson( $course_id );
	if ( $lesson_completed && !empty( $lesson_completed[$course_id] ) && $number_lesson != 0 ) {
		$course_result = sizeof( $lesson_completed[$course_id] ) / $number_lesson;
	} else {
		$course_result = 0;
	}
	return $course_result * 100;
}

function learn_press_quiz_evaluation( $quiz_id, $user_id = null ) {
	if ( !$user_id ) $user_id = get_current_user_id();

	$result = learn_press_get_quiz_result( $user_id, $quiz_id );
	//$passing_condition = learn_press_get_course_passing_condition( get_post_meta( $quiz_id, '_lpr_course', true ) );
	return $result['mark_percent'] * 100;// >$passing_condition;
}

/**
 *
 * @param $course_id
 *
 * @return float|int
 */
function lpr_course_auto_evaluation( $course_id ) {
	$result            = - 1;
	$current           = time();
	$user_id           = get_current_user_id();
	$start_date_course = get_user_meta( $user_id, '_lpr_user_course_start_time', true );
	$start_date        = $start_date_course[$course_id];
	$course_duration   = get_post_meta( $course_id, '_lpr_course_duration', true );
	if ( ( $current - $start_date ) / ( 7 * 24 * 3600 ) > $course_duration ) {
		$result = lpr_course_evaluation( $course_id );
	}
	return $result;
}

/**
 * Check to see if user can preview the lesson
 *
 * @param $lesson_id
 *
 * @return bool
 */
function learn_press_is_lesson_preview( $lesson_id ) {
	$lesson_preview = get_post_meta( $lesson_id, '_lpr_lesson_preview', true );
	return $lesson_preview == 'preview';
}

///////////////////////////// Copied from functions-tunn.php ////////////////////////////////////
function learn_press_add_row_action_link( $actions ) {
	global $post;
	if ( 'lpr_course' == $post->post_type ) {
		$duplicate_link = admin_url( 'edit.php?post_type=lpr_course&action=lpr-duplicate-course&post=' . $post->ID );
		$duplicate_link = array(
			array(
				'link'  => $duplicate_link,
				'title' => __( 'Duplicate this course', 'learn_press' ),
				'class' => ''
			)
		);
		$links          = apply_filters( 'learn_press_row_action_links', $duplicate_link );
		if ( count( $links ) > 1 ) {
			$drop_down = array( '<ul class="lpr-row-action-dropdown">' );
			foreach ( $links as $link ) {
				$drop_down[] = '<li>' . sprintf( '<a href="%s" class="%s">%s</a>', $link['link'], $link['class'], $link['title'] ) . '</li>';
			};
			$drop_down[] = '</ul>';
			$link        = sprintf( '<div class="lpr-row-actions"><a href="%s">%s</a>%s</div>', 'javascript: void(0);', __( 'Course', 'learn_press' ), join( "\n", $drop_down ) );
		} else {
			$link = array_shift( $links );
			$link = sprintf( '<a href="%s" class="%s">%s</a>', $link['link'], $link['class'], $link['title'] );
		}
		$actions['lpr-course-row-action'] = $link;
	}
	return $actions;
}

add_filter( 'page_row_actions', 'learn_press_add_row_action_link' );

/**
 * Duplicate a course when user hit "Duplicate" button
 *
 * @author  TuNN
 */
function learn_press_process_duplicate_action() {

	$wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
	$action        = $wp_list_table->current_action();

	if ( isset( $_REQUEST['action'] ) && ( $action = $_REQUEST['action'] ) == 'lpr-duplicate-course' ) {
		$post_id = isset( $_REQUEST['post'] ) ? $_REQUEST['post'] : 0;
		if ( $post_id && is_array( $post_id ) ) {
			$post_id = array_shift( $post_id );
		}
		// check for post is exists
		if ( !( $post_id && $post = get_post( $post_id ) ) ) {
			wp_die( __( 'Op! The course does not exists', 'learn_press' ) );
		}
		// ensure that user can create course
		if ( !current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'Sorry! You have not permission to duplicate this course', 'learn_press' ) );
		}

		// assign course to current user
		$current_user      = wp_get_current_user();
		$new_course_author = $current_user->ID;

		// setup course data
		$new_course_title = $post->post_title . ' - Copy';
		$args             = array(
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'post_author'    => $new_course_author,
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_name'      => $post->post_name,
			'post_parent'    => $post->post_parent,
			'post_password'  => $post->post_password,
			'post_status'    => 'draft',
			'post_title'     => $new_course_title,
			'post_type'      => $post->post_type,
			'to_ping'        => $post->to_ping,
			'menu_order'     => $post->menu_order
		);

		// insert new course and get it ID
		$new_post_id = wp_insert_post( $args );

		// assign related tags/categories to new course
		$taxonomies = get_object_taxonomies( $post->post_type );
		foreach ( $taxonomies as $taxonomy ) {
			$post_terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
			wp_set_object_terms( $new_post_id, $post_terms, $taxonomy, false );
		}

		// duplicate course data
		global $wpdb;
		$course_meta = $wpdb->get_results( "SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$post_id" );
		if ( count( $course_meta ) != 0 ) {
			$sql_query     = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";
			$sql_query_sel = array();

			foreach ( $course_meta as $meta ) {
				$meta_key   = $meta->meta_key;
				$meta_value = addslashes( $meta->meta_value );

				$sql_query_sel[] = "SELECT $new_post_id, '$meta_key', '$meta_value'";
			}

			$sql_query .= implode( " UNION ALL ", $sql_query_sel );
			$wpdb->query( $sql_query );
		}
		wp_redirect( admin_url( 'edit.php?post_type=lpr_course' ) );
		die();
	}
}

add_action( 'load-edit.php', 'learn_press_process_duplicate_action' );

/**
 * Returns the name of folder contains template files in theme
 */
function learn_press_template_path() {
	return apply_filters( 'learn_press_template_path', 'learnpress' );
}

/**
 * Prevent user access directly by calling the file from URL
 *
 * @author  TuNN
 */
function learn_press_prevent_access_directly() {
	if ( !defined( 'ABSPATH' ) ) {
		exit; // Exit if accessed directly
	}
}

/**
 * get template part
 *
 * @param   string $slug
 * @param   string $name
 *
 * @return  string
 */
function learn_press_get_template_part( $slug, $name = '' ) {
	$template = '';

	// Look in yourtheme/slug-name.php and yourtheme/learnpress/slug-name.php
	if ( $name ) {
		$template = locate_template( array( "{$slug}-{$name}.php", learn_press_template_path() . "/{$slug}-{$name}.php" ) );
	}

	// Get default slug-name.php
	if ( !$template && $name && file_exists( LPR_PLUGIN_PATH . "/templates/{$slug}-{$name}.php" ) ) {
		$template = LPR_PLUGIN_PATH . "/templates/{$slug}-{$name}.php";
	}

	// If template file doesn't exist, look in yourtheme/slug.php and yourtheme/learnpress/slug.php
	if ( !$template ) {
		$template = locate_template( array( "{$slug}.php", learn_press_template_path() . "{$slug}.php" ) );
	}

	// Allow 3rd party plugin filter template file from their plugin
	if ( $template ) {
		$template = apply_filters( 'learn_press_get_template_part', $template, $slug, $name );
	}
	if ( $template && file_exists( $template ) ) {
		load_template( $template, false );
	}

	return $template;
}

/**
 * Get other templates (e.g. product attributes) passing attributes and including the file.
 *
 * @param string $template_name
 * @param array  $args          (default: array())
 * @param string $template_path (default: '')
 * @param string $default_path  (default: '')
 *
 * @return void
 */
function learn_press_get_template( $template_name, $args = array(), $template_path = '', $default_path = '' ) {
	if ( $args && is_array( $args ) ) {
		extract( $args );
	}

	$located = learn_press_locate_template( $template_name, $template_path, $default_path );

	if ( !file_exists( $located ) ) {
		_doing_it_wrong( __FUNCTION__, sprintf( '<code>%s</code> does not exist.', $located ), '2.1' );
		return;
	}
	// Allow 3rd party plugin filter template file from their plugin
	$located = apply_filters( 'learn_press_get_template', $located, $template_name, $args, $template_path, $default_path );

	do_action( 'learn_press_before_template_part', $template_name, $template_path, $located, $args );

	include( $located );

	do_action( 'learn_press_after_template_part', $template_name, $template_path, $located, $args );
}

/**
 * Locate a template and return the path for inclusion.
 *
 * This is the load order:
 *
 *        yourtheme        /    $template_path    /    $template_name
 *        yourtheme        /    $template_name
 *        $default_path    /    $template_name
 *
 * @access public
 *
 * @param string $template_name
 * @param string $template_path (default: '')
 * @param string $default_path  (default: '')
 *
 * @return string
 */
function learn_press_locate_template( $template_name, $template_path = '', $default_path = '' ) {
	if ( !$template_path ) {
		$template_path = learn_press_template_path();
	}

	if ( !$default_path ) {
		$default_path = LPR_PLUGIN_PATH . '/templates/';
	}

	// Look within passed path within the theme - this is priority
	$template = locate_template(
		array(
			trailingslashit( $template_path ) . $template_name,
			$template_name
		)
	);

	// Get default template
	if ( !$template ) {
		$template = $default_path . $template_name;
	}

	// Return what we found
	return apply_filters( 'learn_press_locate_template', $template, $template_name, $template_path );
}

/**
 * Check if a user has permission to view a quiz
 * If not then redirect user to 404 page
 *
 * @author  TuNN
 *
 * @param   int $user_id The ID of user to check
 * @param   int $quiz_id The ID of a quiz to check
 *
 * @return  void
 */
function learn_press_redirect_quiz_auth( $user_id = null, $quiz_id = null ) {
	// if the user_id not passed then try to get it from current user
	if ( !$user_id ) {
		$user_id = get_current_user_id();

		// check again to ensure to ensure the user is already existing
		if ( !$user_id ) {
			wp_die( __( 'You have not permission to view this page', 'learn_press' ) );
		}
	}

	// if the quiz_id is not passed then try to get it from current post
	if ( !$quiz_id ) {
		global $post;
		$quiz_id = $post->ID;

		// check again to ensure the quiz is already existing
		if ( !$quiz_id ) {
			wp_die( __( 'You have not permission to view this page', 'learn_press' ) );
		}
	}

	// get permission for viewing quiz
	$preview_quiz = get_post_meta( $quiz_id, '_lpr_preview_quiz', true );
	if ( $preview_quiz == 'not_preview' ) {
		$course_take = get_user_meta( $user_id, '_lpr_user_course', true );
		$access      = false;
		if ( $course_take )
			foreach ( $course_take as $course ) {
				$quiz = get_post_meta( $course, '_lpr_course_lesson_quiz', true );
				if ( $quiz && in_array( $post->ID, $quiz ) ) {
					$access = true;
					break;
				}
			}
		// redirect if user has not permission to view quiz
		if ( !$access ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			get_template_part( 404 );
			exit();
		}
	}
}

/**
 * Check if user has completed a quiz or not
 *
 * @author  TuNN
 *
 * @param   int $user_id The ID of user need to check
 * @param   int $quiz_id The ID of quiz need to check
 *
 * @return  boolean
 */
function learn_press_user_has_completed_quiz( $user_id = null, $quiz_id = null ) {
	$completed = false;
	// if $user_id is not passed, try to get it from current user
	if ( !$user_id ) {
		$user_id = get_current_user_id();
		if ( !$user_id ) $completed = false;
	}

	// if $quiz_id is not passed, try to get it from current quiz
	$quiz_id = learn_press_get_quiz_id( $quiz_id );

	$quiz_completed = get_user_meta( $user_id, '_lpr_quiz_completed', true );
	$retake         = get_user_meta( $user_id, '_lpr_quiz_retake', true );

	// if user can not retake a quiz or has already completed a quiz
	if ( ( !$retake || !in_array( $quiz_id, $retake ) ) && $quiz_completed && array_key_exists( $quiz_id, $quiz_completed ) ) {
		$completed = true;
	}

	return apply_filters( 'learn_press_user_has_completed_quiz', $completed, $user_id, $quiz_id );
}

/**
 * Get all questions of a quiz
 *
 * @author  TuNN
 *
 * @param   int     $quiz_id  The ID of a quiz to get all questions
 * @param   boolean $only_ids return an array of questions with IDs only or as post objects
 *
 * @return  array|null
 */
function learn_press_get_quiz_questions( $quiz_id = null, $only_ids = true ) {
	static $quiz_questions;
	if ( !$quiz_questions ) $quiz_questions = array();
	$quiz_id = learn_press_get_quiz_id( $quiz_id );
	if ( empty( $quiz_questions[$quiz_id] ) ) {
		$questions = get_post_meta( $quiz_id, '_lpr_quiz_questions', true );

		if ( is_array( $questions ) && count( $questions ) > 0 ) {
			$question_ids = array_keys( $questions );
			$query_args   = array(
				'posts_per_page' => - 999,
				'include'        => $question_ids,
				'post_type'      => 'lpr_question',
				'post_status'    => 'publish'
			);
			if ( $only_ids ) {
				$query_args['fields'] = 'ids';
			}

			$questions = array();
			// reorder as stored in database
			if ( $_questions = get_posts( $query_args ) ):
				$questions = array_flip( $question_ids );
				foreach ( $_questions as $q ) {
					$questions[$only_ids ? $q : $q->ID] = $q;
				}
			endif;
		}
		$quiz_questions[$quiz_id] = $questions;
	}
	return apply_filters( 'learn_press_get_quiz_questions', $quiz_questions[$quiz_id], $quiz_id );
}

/**
 * Check if a quiz have any question or not
 */
function learn_press_quiz_has_questions( $quiz_id = null ) {
	$questions = learn_press_get_quiz_questions( $quiz_id );
	return is_array( $questions ) ? count( $questions ) : false;
}

/**
 * redirect to plugin's template if needed
 *
 * @author  TuNN
 * @return  void
 */
function learn_press_template_redirect() {
	global $post_type;
	do_action( 'learn_press_before_template_redirect', $post_type );
	//echo __FUNCTION__;
	if ( !empty( $post_type ) ) {
		if ( false !== strpos( $post_type, 'lpr_' ) ) {
			$lpr_post_type = str_replace( 'lpr_', '', $post_type );
			$template      = '';
			if ( is_archive() ) {
				$template = learn_press_get_template_part( 'archive', $lpr_post_type );
			} else {
				$template = learn_press_get_template_part( 'single', $lpr_post_type );
			}
			// ensure the template loaded otherwise load default template

			if ( $template && file_exists( $template ) ) exit();
		}
	}
}

/**
 * get the answers of a quiz
 *
 * @param null $user_id
 * @param null $quiz_id
 *
 * @return mixed
 */
function learn_press_get_question_answers( $user_id = null, $quiz_id = null ) {
	global $quiz;
	if ( !$user_id ) {
		$user_id = get_current_user_id();
	}
	$answers = false;
	$quiz_id = $quiz_id ? $quiz_id : ( $quiz ? $quiz->ID : 0 );
	$quizzes = get_user_meta( $user_id, '_lpr_quiz_question_answer', true );
	if ( is_array( $quizzes ) && isset( $quizzes[$quiz_id] ) ) {
		$answers = $quizzes[$quiz_id];
	}
	return apply_filters( 'learn_press_get_question_answers', $answers, $quiz_id, $user_id );
}

function learn_press_get_quiz_id( $id ) {
	if ( !$id ) {
		global $quiz;
		$id = $quiz ? $quiz->ID : $id;
	}
	return $id;
}

/**
 *
 */
function learn_press_save_question_answer( $user_id = null, $quiz_id = null, $question_id, $question_answer ) {

	if ( !$user_id ) {
		$user_id = get_current_user_id();
	}
	$quiz_id = learn_press_get_quiz_id( $quiz_id );
	$quizzes = get_user_meta( $user_id, '_lpr_quiz_question_answer', true );
	if ( !is_array( $quizzes ) ) $quizzes = array();
	if ( !isset( $quizzes[$quiz_id] ) || !is_array( $quizzes[$quiz_id] ) ) {
		$quizzes[$quiz_id] = array();
	}
	$quizzes[$quiz_id][$question_id] = $question_answer;
	update_user_meta( $user_id, '_lpr_quiz_question_answer', $quizzes );
}

/**
 * Get quiz data stored in database of an user
 *
 * @param      $meta
 * @param null $user_id
 * @param null $quiz_id
 *
 * @return bool
 */
function learn_press_get_user_quiz_data( $meta_key, $user_id = null, $quiz_id = null ) {
	global $quiz;
	if ( !$user_id ) {
		$user_id = get_current_user_id();
	}
	$quiz_id = learn_press_get_quiz_id( $quiz_id );

	$meta = get_user_meta( $user_id, $meta_key, true );
	return !empty( $meta[$quiz_id] ) ? $meta[$quiz_id] : false;
}

/**
 * Check if user has started a quiz or not
 *
 * @param int $user_id
 * @param int $quiz_id
 *
 * @return boolean
 */
function learn_press_user_has_started_quiz( $user_id = null, $quiz_id = null ) {
	$start_time = learn_press_get_user_quiz_data( '_lpr_quiz_start_time', $user_id, $quiz_id );
	return $start_time ? true : false;
}

if ( !function_exists( 'learn_press_setup_quiz_data' ) ) {
	/**
	 * setup quiz data if we see an ID or slug of a quiz in request params
	 *
	 * @author  TuNN
	 *
	 * @param   int|string $quiz_id_variable ID or slug of a quiz
	 * @param              string            The name of global variable to set
	 *
	 * @return  object|null
	 */
	function learn_press_setup_quiz_data( $quiz_id_variable = 'quiz_id', $global_variable = 'quiz' ) {
		global $post, $post_type;
		$quiz = false;
		// set quiz variable to a post if we are in a single quiz

		if ( is_single() && 'lpr_quiz' == $post_type ) {
			$quiz = $post;
		} else {
			if ( !empty( $_REQUEST[$quiz_id_variable] ) ) {

				if ( isset( $GLOBALS[$global_variable] ) ) unset( $GLOBALS[$global_variable] );
				$quiz_id = $_REQUEST[$quiz_id_variable];

				// if the variable is a numeric we consider it is an ID
				if ( is_numeric( $quiz_id ) ) {
					$quiz = get_post( $quiz_id );

				} else { // otherwise it is a slug
					$quiz = get_posts(
						array(
							'name'      => $quiz_id,
							'post_type' => 'lpr_quiz'
						)
					);
					if ( is_array( $quiz ) ) {
						$quiz = array_shift( $quiz );
					}
				}
			}
		}
		if ( $quiz ) {
			$GLOBALS[$global_variable] = $quiz;
		}
		return $quiz;
	}
}

if ( !function_exists( 'learn_press_setup_question_data' ) ) {
	/**
	 * setup question data if we see an ID or slug of a question in request params
	 *
	 * @author  TuNN
	 *
	 * @param   int|string $question_id_variable ID or slug of a quiz
	 * @param              string                The name of global variable to set
	 *
	 * @return  object|null
	 */
	function learn_press_setup_question_data( $question_id_variable = 'quiz_id', $global_variable = 'question' ) {
		global $post, $post_type;
		$question = false;
		// set question to post if we in a single page of a question
		if ( is_single() && 'lpr_question' == $post_type ) {
			$question = $post;
		} else {
			if ( !empty( $_REQUEST[$question_id_variable] ) ) {

				if ( isset( $GLOBALS[$global_variable] ) ) unset( $GLOBALS[$global_variable] );
				$question_id = $_REQUEST[$question_id_variable];

				// if the variable is a numeric we consider it is an ID
				if ( is_numeric( $question_id ) ) {
					$question = get_post( $question_id );
					if ( $question ) {
						$GLOBALS[$global_variable] = $question;
					}
				} else { // otherwise it is a slug
					$question = get_posts(
						array(
							'name'      => $question_id,
							'post_type' => 'lpr_quiz'
						)
					);
					if ( is_array( $question ) ) {
						$GLOBALS[$global_variable] = array_shift( $question );
					}
				}
			}
		}
		return $question;
	}
}

//if( !function_exists( 'learn_press_process_frontend_action' ) ) {
/**
 * initial some task before display our page
 */
function learn_press_process_frontend_action() {

	learn_press_setup_quiz_data( 'quiz_id' );
	learn_press_setup_question_data( 'question_id' );

	$action = !empty( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
	if ( $action ) {
		$action = preg_replace( '!^learn_press_!', '', $action );
	}
	do_action( 'learn_press_frontend_action' );

	if ( $action ) {
		do_action( 'learn_press_frontend_action_' . $action );
	}
}

//}

add_action( 'template_redirect', 'learn_press_process_frontend_action' );

/**
 * retrieve the point of a question
 * @author  TuNN
 *
 * @param   $question_id
 *
 * @return  int
 */
function learn_press_get_question_mark( $question_id ) {
	$mark = intval( get_post_meta( $question_id, '_lpr_question_mark', true ) );
	$mark = max( $mark, 1 );
	return apply_filters( 'learn_press_get_question_mark', $mark, $question_id );
}

/**
 * get the total mark of a quiz
 *
 * @author  TuNN
 *
 * @param   int $quiz_id
 *
 * @return  int
 */
function learn_press_get_quiz_mark( $quiz_id = null ) {
	$quiz_id   = learn_press_get_quiz_id( $quiz_id );
	$questions = learn_press_get_quiz_questions( $quiz_id );
	$mark      = 0;
	if ( $questions ) foreach ( $questions as $question_id => $opts ) {
		$mark += learn_press_get_question_mark( $question_id );
	}
	return apply_filters( 'learn_press_get_quiz_mark', $mark, $quiz_id );
}

/**
 * get the time remaining of a quiz has started by an user
 *
 * @param null $user_id
 * @param null $quiz_id
 *
 * @return int
 */
function learn_press_get_quiz_time_remaining( $user_id = null, $quiz_id = null ) {
	global $quiz;
	if ( !$user_id ) $user_id = get_current_user_id();
	$quiz_id = $quiz_id ? $quiz_id : ( $quiz ? $quiz->ID : 0 );
	if ( !$quiz_id ) return 0;
	$meta           = get_user_meta( $user_id, '_lpr_quiz_start_time', true );
	$quiz_duration  = get_post_meta( $quiz_id, '_lpr_duration', true );
	$time_remaining = $quiz_duration * 60;
	if ( !empty( $meta[$quiz_id] ) ) {
		$quiz_start_time = $meta[$quiz_id];

		if ( $quiz_duration ) {
			$quiz_duration *= 60;
			$now = time();

			if ( $now < $quiz_start_time + $quiz_duration ) {
				$time_remaining = $quiz_start_time + $quiz_duration - $now;
			} else {
				$time_remaining = 0;
			}

		}
	}
	return apply_filters( 'learn_press_get_quiz_time_remaining', $time_remaining, $user_id, $quiz_id );
}

/**
 *
 */
function learn_press_get_quiz_result( $user_id = null, $quiz_id = null ) {
	global $quiz;
	if ( !$user_id ) $user_id = get_current_user_id();
	$quiz_id   = $quiz_id ? $quiz_id : ( $quiz ? $quiz->ID : 0 );
	$questions = learn_press_get_quiz_questions( $quiz_id );
	$answers   = learn_press_get_question_answers( $user_id, $quiz_id );

	$mark              = 0;
	$correct_questions = 0;
	$wrong_questions   = 0;
	$empty_questions   = 0;

	$quiz_start = learn_press_get_user_quiz_data( '_lpr_quiz_start_time', $user_id, $quiz_id );
	$quiz_end   = learn_press_get_user_quiz_data( '_lpr_quiz_completed', $user_id, $quiz_id );
	$mark_total = learn_press_get_quiz_mark( $quiz_id );
	$quiz_time  = ( $quiz_end ? $quiz_end - $quiz_start : 0 );
	$info       = false;

	if ( $questions ) {
		foreach ( $questions as $question_id => $options ) {
			$ques_object = LPR_Question_Type::instance( $question_id );

			if ( $ques_object && isset( $answers[$question_id] ) ) {
				$check = $ques_object->check( array( 'answer' => $answers[$question_id] ) );
				if ( $check['correct'] ) {
					$mark += $check['mark'];
					$correct_questions ++;
				} else {
					$wrong_questions ++;
				}

			} else {
				$empty_questions ++;
			}
		}
		$question_count = count( $questions );
		$info           = array(
			'mark'            => $mark,
			'correct'         => $correct_questions,
			'wrong'           => $wrong_questions,
			'empty'           => $empty_questions,
			'questions_count' => $question_count,
			'mark_total'      => $mark_total,
			'mark_percent'    => round( $mark / $mark_total, 2 ),
			'correct_percent' => round( $correct_questions / $question_count * 100, 2 ),
			'wrong_percent'   => round( $wrong_questions / $question_count * 100, 2 ),
			'empty_percent'   => round( $empty_questions / $question_count * 100, 2 ),
			'quiz_time'       => $quiz_time
		);
	}

	return apply_filters( 'learn_press_get_quiz_result', $info, $user_id, $quiz_id );
}

/**
 * call this function when user hit "Start Quiz" and stores some
 * meta_key to mark that user has started this quiz
 */
function learn_press_frontend_action_start_quiz() {
	global $quiz;


	// should check user permission here to ensure user can start quiz
	$user_id = get_current_user_id();
	$quiz_id = $quiz->ID;

	// update start time, this is the time user begin the quiz
	$meta = get_user_meta( $user_id, '_lpr_quiz_start_time', true );
	if ( !is_array( $meta ) ) $meta = array( $quiz_id => time() );
	else $meta[$quiz_id] = time();
	update_user_meta( $user_id, '_lpr_quiz_start_time', $meta );

	// update questions
	if ( $questions = learn_press_get_quiz_questions( $quiz_id ) ) {

		// stores the questions
		$question_ids = array_keys( $questions );
		$meta         = get_user_meta( $user_id, '_lpr_quiz_questions', true );
		if ( !is_array( $meta ) ) $meta = array( $quiz_id => $question_ids );
		else $meta[$quiz_id] = $question_ids;
		update_user_meta( $user_id, '_lpr_quiz_questions', $meta );

		// stores current question
		$meta = get_user_meta( $user_id, '_lpr_quiz_current_question', true );
		if ( !is_array( $meta ) ) $meta = array( $quiz_id => $question_ids[0] );
		else $meta[$quiz_id] = $question_ids[0];
		update_user_meta( $user_id, '_lpr_quiz_current_question', $meta );

	}

	// update answers
	$quizzes = get_user_meta( $user_id, '_lpr_quiz_question_answer', true );
	if ( !is_array( $quizzes ) ) $quizzes = array();
	$quizzes[$quiz_id] = array();
	update_user_meta( $user_id, '_lpr_quiz_question_answer', $quizzes );

}

add_action( 'learn_press_frontend_action_start_quiz', 'learn_press_frontend_action_start_quiz' );


add_action( 'learn_press_frontend_action', 'learn_press_update_quiz_time' );

/**
 * get position of current question is displaying in the quiz for user
 *
 * @param null $user_id
 * @param null $quiz_id
 * @param null $question_id
 *
 * @return int|mixed
 */
function learn_press_get_question_position( $user_id = null, $quiz_id = null, &$question_id = null ) {
	if ( !$user_id ) $user_id = get_current_user_id();
	if ( !$quiz_id ) {
		global $quiz;
		$quiz_id = $quiz ? $quiz->ID : 0;
	}

	if ( !$user_id || !$quiz_id ) return - 1;

	if ( !$question_id ) {
		$current_questions = get_user_meta( $user_id, '_lpr_quiz_current_question', true );
		if ( empty ( $current_questions[$quiz_id] ) ) return - 1;

		$question_id = $current_questions[$quiz_id];
	}

	$questions = get_user_meta( $user_id, '_lpr_quiz_questions', true );
	if ( empty( $questions[$quiz_id] ) ) return - 1;

	$pos = array_search( $question_id, $questions[$quiz_id] );
	return false !== $pos ? $pos : - 1;
}

/**
 * class for quiz body
 *
 * @param null $class
 */
function learn_press_quiz_class( $class = null ) {
	$class .= " single-quiz clearfix";
	if ( learn_press_user_has_completed_quiz() ) {
		$class .= " quiz-completed";
	} elseif ( learn_press_user_has_started_quiz() ) {
		$class .= " quiz-started";
	}
	post_class( $class );
}

/**
 * display the seconds in time format h:i:s
 *
 * @param        $seconds
 * @param string $separator
 *
 * @return string
 */
function learn_press_seconds_to_time( $seconds, $separator = ':' ) {
	return sprintf( "%02d%s%02d%s%02d", floor( $seconds / 3600 ), $separator, ( $seconds / 60 ) % 60, $separator, $seconds % 60 );
}

/**
 * create a global variable $quiz if we found a request variable such as quiz_id
 */
function learn_press_init_quiz() {
	if ( !empty( $_REQUEST['quiz_id'] ) ) {
		$quiz = get_post( $_REQUEST['quiz_id'] );
		if ( $quiz ) $GLOBALS['quiz'] = $quiz;
	}
}

add_action( 'wp', 'learn_press_init_quiz' );

function learn_press_send_json( $response ) {
	echo '<!--LPR_START-->';
	@header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
	echo wp_json_encode( $response );
	echo '<!--LPR_END-->';
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX )
		wp_die();
	else
		die;
}

/**
 * create a global variable $course if we found a request variable such as course_id
 */
function learn_press_init_course() {
	global $post_type;
	if ( 'lpr_course' == $post_type ) {
		global $post;
		$GLOBALS['course'] = $post;
	} else if ( !empty( $_REQUEST['course_id'] ) ) {
		$course = get_post( $_REQUEST['course_id'] );
		if ( $course ) $GLOBALS['course'] = $course;
	}

	// for test only
	if ( !empty( $_REQUEST['reset'] ) && ( $type = $_REQUEST['reset'] ) ) {

		switch ( $type ) {
			// remove all orders of current user
			case 'order':
				$user_id = get_current_user_id();
				$orders  = get_user_meta( $user_id, '_lpr_order_id' );
				learn_press_reset_user_quiz( $user_id );
				if ( $orders ) foreach ( $orders as $order_id ) {
					$order = new LPR_Order( $order_id );
					$items = $order->get_items();
					if ( !empty( $items->products ) && $products = $items->products ) {
						foreach ( $products as $item ) {
							$quizzes = learn_press_get_quizzes( $item['id'] );
							if ( $quizzes ) foreach ( $quizzes as $quiz_id ) {
								learn_press_reset_user_quiz( $order->user_id, $quiz_id );
							}
						}
					}
					wp_delete_post( $order_id );
				}
				delete_user_meta( $user_id, '_lpr_order_id' );
				delete_user_meta( $user_id, '_lpr_user_course' );
				//learn_press
				if ( !empty( $_REQUEST['return'] ) ) wp_redirect( $_REQUEST['return'] );
		}
	}
}

add_action( 'wp', 'learn_press_init_course' );
add_action( 'the_post', 'learn_press_init_course' );

function learn_press_head() {
	if ( is_single() && 'lpr_course' == get_post_type() ) {
		wp_enqueue_script( 'tojson', LPR_PLUGIN_URL . '/assets/js/toJSON.js' );
		ob_start();
		?>
		<script>


		</script>
		<?php
		learn_press_enqueue_script( preg_replace( '!</?script>!', '', ob_get_clean() ) );
	}
}

add_action( 'wp_head', 'learn_press_head' );

function learn_press_enqueue_script( $code, $script_tag = false ) {
	global $learn_press_queued_js, $learn_press_queued_js_tag;

	if ( $script_tag ) {
		if ( empty( $learn_press_queued_js_tag ) ) {
			$learn_press_queued_js_tag = '';
		}
		$learn_press_queued_js_tag .= "\n" . $code . "\n";
	} else {
		if ( empty( $learn_press_queued_js ) ) {
			$learn_press_queued_js = '';
		}

		$learn_press_queued_js .= "\n" . $code . "\n";
	}
}

function learn_press_print_script() {
	global $learn_press_queued_js, $learn_press_queued_js_tag;
	if ( !empty( $learn_press_queued_js ) ) {
		echo "<!-- LearnPress JavaScript -->\n<script type=\"text/javascript\">\njQuery(function($) {\n";

		// Sanitize
		$learn_press_queued_js = wp_check_invalid_utf8( $learn_press_queued_js );
		$learn_press_queued_js = preg_replace( '/&#(x)?0*(?(1)27|39);?/i', "'", $learn_press_queued_js );
		$learn_press_queued_js = str_replace( "\r", '', $learn_press_queued_js );

		echo $learn_press_queued_js . "\n});\n</script>\n";

		unset( $learn_press_queued_js );
	}

	if ( !empty( $learn_press_queued_js_tag ) ) {
		echo $learn_press_queued_js_tag;
	}
}

add_action( 'wp_head', 'learn_press_head' );
/*
function learn_press_enqueue_script( $code ) {
	global $learn_press_queued_js;

	if ( empty( $learn_press_queued_js ) ) {
		$learn_press_queued_js = '';
	}

	$learn_press_queued_js .= "\n" . $code . "\n";
}

function learn_press_print_script() {
	global $learn_press_queued_js;
	if ( !empty( $learn_press_queued_js ) ) {
		echo "<!-- LearnPress JavaScript -->\n<script type=\"text/javascript\">\njQuery(function($) {\n";

		// Sanitize
		$learn_press_queued_js = wp_check_invalid_utf8( $learn_press_queued_js );
		$learn_press_queued_js = preg_replace( '/&#(x)?0*(?(1)27|39);?/i', "'", $learn_press_queued_js );
		$learn_press_queued_js = str_replace( "\r", '', $learn_press_queued_js );

		echo $learn_press_queued_js . "\n});\n</script>\n";

		unset( $learn_press_queued_js );
	}
}
*/

add_action( 'wp_footer', 'learn_press_print_script' );
add_action( 'admin_footer', 'learn_press_print_script' );

/**
 * Gets duration of a quiz
 *
 * @param null $quiz_id
 *
 * @return mixed
 */
function learn_press_get_quiz_duration( $quiz_id = null ) {
	global $quiz;
	if ( !$quiz_id ) $quiz_id = $quiz ? $quiz->ID : 0;
	$duration = intval( get_post_meta( $quiz_id, '_lpr_duration', true ) );
	return apply_filters( 'learn_press_get_quiz_duration', $duration, $quiz_id );
}

///////////////


/**
 * Get the price of a course
 *
 * @author  Tunn
 *
 * @param   null $course_id
 *
 * @return  int
 */
function learn_press_get_course_price( $course_id = null, $with_currency = false ) {
	if ( !$course_id ) {
		global $course;
		$course_id = $course ? $course->ID : 0;
	}
	if ( !learn_press_is_free_course( $course_id ) ) {
		$price = floatval( get_post_meta( $course_id, '_lpr_course_price', true ) );
		if ( $with_currency ) {
			$price = learn_press_format_price( $price, true );
		}
	} else {
		$price = 0;
	}
	return apply_filters( 'learn_press_get_course_price', $price, $course_id );
}

/**
 * Detect if a course is free or not
 *
 * @param null $course_id
 *
 * @return bool
 */
function learn_press_is_free_course( $course_id = null ) {
	if ( !$course_id ) {
		global $course;
		$course_id = $course ? $course->ID : 0;
	}
	//echo "[" . get_post_meta( $course_id, '_lpr_course_payment', true ) . "]";
	return 'free' == get_post_meta( $course_id, '_lpr_course_payment', true );
}


function learn_press_take_course( $course_id, $payment_method = '' ) {
	$user            = learn_press_get_current_user();
	$can_take_course = apply_filters( 'learn_press_before_take_course', true, $user->ID, $course_id, $payment_method );
	if ( $can_take_course ) {
		if ( learn_press_is_free_course( $course_id ) ) {
			if ( $order_id = learn_press_add_transaction(
				array(
					'method'             => 'free',
					'method_id'          => '',
					'status'             => '',
					'user_id'            => $user->ID,
					'transaction_object' => learn_press_generate_transaction_object()
				)
			)
			) {
				learn_press_update_order_status( $order_id, 'Completed' );
				learn_press_add_message( 'message', __( 'Congratulations! You have enrolled this course' ) );
				$json = array(
					'result'   => 'success',
					'redirect' => ( ( $confirm_page_id = learn_press_get_page_id( 'taken_course_confirm' ) ) && get_post( $confirm_page_id ) ) ? learn_press_get_order_confirm_url( $order_id ) : get_permalink( $course_id )
				);
				learn_press_send_json( $json );
			}
		} else {
			if ( has_filter( 'learn_press_take_course_' . $payment_method ) ) {
				$order  = null;
				$result = apply_filters( 'learn_press_take_course_' . $payment_method, $order );
				$result = apply_filters( 'learn_press_payment_result', $result, $order );
				if ( is_ajax() ) {
					learn_press_send_json( $result );
					exit;
				} else {
					wp_redirect( $result['redirect'] );
					exit;
				}
			} else {
				wp_die( __( 'Invalid payment method.', 'learn_press' ) );
			}

		}
	} else {
		learn_press_add_message( 'error', __( 'Sorry! You can not enroll to this course' ) );
		$json = array(
			'result'   => 'error',
			'redirect' => get_permalink( $course_id )
		);
		echo '<!--LPR_START-->' . json_encode( $json ) . '<!--LPR_END-->';
	}
}

add_filter( 'learn_press_take_course', 'learn_press_take_course', 5, 2 );

if ( !function_exists( 'is_ajax' ) ) {

	/**
	 * is_ajax - Returns true when the page is loaded via ajax.
	 *
	 * @access public
	 * @return bool
	 */
	function is_ajax() {
		return defined( 'DOING_AJAX' );
	}
}
function learn_press_require_login_to_take_course( $can_take, $user_id, $course_id, $payment_method ) {
	if ( !is_user_logged_in() ) {
		$login_url = learn_press_get_login_url( get_permalink( $course_id ) );
		learn_press_send_json(
			array(
				'result'   => 'success',
				'redirect' => $login_url
			)
		);
	}
}

add_filter( 'learn_press_before_take_course', 'learn_press_require_login_to_take_course', 5, 4 );

function learn_press_get_login_url( $redirect = null ) {
	return apply_filters( 'learn_press_login_url', wp_login_url( $redirect ) );
}

function learn_press_before_take_course( $can_take, $user_id, $course_id, $payment_method ) {
	// only one course in time
	LPR_Cart::instance()->empty_cart()->add_to_cart( $course_id );
	return $can_take;
}

add_filter( 'learn_press_before_take_course', 'learn_press_before_take_course', 5, 4 );

function learn_press_get_transition_products( $order_id ) {
	$order_items = get_post_meta( $order_id, '_learn_press_order_items', true );
	$products    = false;
	if ( $order_items ) {
		if ( !empty( $order_items->products ) ) {
			$products = array();
			foreach ( $order_items->products as $pro ) {
				$product = get_post( $pro['id'] );
				if ( $product ) {
					$product->price    = $pro['price'];
					$product->quantity = $pro['quantity'];
					$product->amount   = learn_press_is_free_course( $pro['id'] ) ? 0 : ( $product->price * $product->quantity );
					$products[]        = $product;
				}
			}
		}
	}
	return $products;
}

function learn_press_get_order_items( $order_id ) {
	return get_post_meta( $order_id, '_learn_press_order_items', true );
}

function learn_press_format_price( $price, $with_currency = false ) {
	if ( !is_numeric( $price ) )
		$price = 0;
	$settings = learn_press_settings( 'general' );
	$before   = $after = '';
	if ( $with_currency ) {
		if ( gettype( $with_currency ) != 'string' ) {
			$currency = learn_press_get_currency_symbol();
		} else {
			$currency = $with_currency;
		}

		switch ( $settings->get( 'currency_pos' ) ) {
			default:
				$before = $currency;
				break;
			case 'left_with_space':
				$before = $currency . ' ';
				break;
			case 'right':
				$after = $currency;
				break;
			case 'right_with_space':
				$after = ' ' . $currency;
		}
	}

	$price =
		$before
		. number_format(
			$price,
			$settings->get( 'number_of_decimals', 2 ),
			$settings->get( 'decimals_separator', '.' ),
			$settings->get( 'thousands_separator', ',' )
		) . $after;

	return $price;
}

function learn_press_transaction_order_number( $order_number ) {
	return '#' . sprintf( "%'.010d", $order_number );
}

function learn_press_transaction_order_date( $date, $format = null ) {
	$format = empty( $format ) ? get_option( 'date_format' ) : $format;
	return date( $format, strtotime( $date ) );
}

/**
 * @param $take
 * @param $user_id
 * @param $course_id
 *
 * @return bool
 */
function learn_press_check_user_pass_prerequisite( $user_id = null, $course_id = null ) {
	$prerequisite = learn_press_user_prerequisite_courses( $user_id, $course_id );
	return $prerequisite ? false : true;
}

add_filter( 'learn_press_before_take_course', 'learn_press_check_user_pass_prerequisite', 105, 3 );


function learn_press_get_course_id( $course_id = null ) {
	if ( !$course_id ) {
		global $course;
		$course_id = $course ? $course->ID : 0;
	}
	return $course_id;
}

/**
 * count the number of students has enrolled a course
 *
 * @author  TuNN
 *
 * @param   int $course_id
 *
 * @return  int
 */
function learn_press_count_students_enrolled( $course_id = null ) {
	$course_id = learn_press_get_course_id( $course_id );
	//$count = intval( get_post_meta( $course_id, '_lpr_course_number_student', true ) );
	$count = ( $users = get_post_meta( $course_id, '_lpr_course_user', true ) ) ? sizeof( $users ) : 0;
	return apply_filters( 'learn_press_count_student_enrolled_course', $count, $course_id );
}

/**
 * get current status of user's course
 *
 * @author  Tunn
 *
 * @param   int $user_id
 * @param   int $course_id
 *
 * @return  string
 */
function learn_press_get_user_course_status( $user_id = null, $course_id = null ) {
	$status = null;
	// try to get current user if not passed
	if ( !$user_id ) $user_id = get_current_user_id();

	// try to get course id if not passed
	if ( !$course_id ) {
		global $course;
		$course_id = $course ? $course->ID : 0;
	}

	if ( $course_id && $user_id ) {
		//add_user_meta(  $user_id, '_lpr_order_id', 40 );
		$orders = get_user_meta( $user_id, '_lpr_order_id' );
		$orders = array_unique( $orders );
		if ( $orders ) {
			$order_id = 0;
			foreach ( $orders as $order ) {
				$order_items = get_post_meta( $order, '_learn_press_order_items', true );
				if ( $order_items && $order_items->products ) {
					if ( !empty( $order_items->products[$course_id] ) ) {
						$order_id = max( $order_id, $order );
					}
				}
			}

			if ( ( $order = get_post( $order_id ) ) && $order->post_status != 'lpr-draft' )
				$status = get_post_meta( $order_id, '_learn_press_transaction_status', true );
		}
	}
	return $status;
}

function learn_press_count_student_enrolled_course( $course_id = null ) {
	return learn_press_count_students_enrolled( $course_id );
}

function learn_press_get_limit_student_enroll_course( $course_id = null ) {
	$course_id = learn_press_get_course_id( $course_id );
	$count     = intval( get_post_meta( $course_id, '_lpr_max_course_number_student', true ) );
	return apply_filters( 'learn_press_get_limit_student_enroll_course', $count, $course_id );
}

function learn_press_increment_user_enrolled( $course_id = null, $count = false ) {
	return;
	$course_id = learn_press_get_course_id( $course_id );

	if ( is_bool( $count ) && !$count ) {
		$count = learn_press_count_student_enrolled_course( $course_id );
		$count ++;
	} else {
		$count = intval( $count );
	}
	$max_enroll = learn_press_get_limit_student_enroll_course( $course_id );
	if ( $max_enroll && $count > $max_enroll ) {
		$count = $max_enroll;
	}
	update_post_meta( $course_id, '_lpr_course_number_student', $count );
}

function learn_press_decrement_user_enrolled( $course_id = null, $count = false ) {
	return;
	$course_id = learn_press_get_course_id( $course_id );

	if ( is_bool( $count ) && !$count ) {
		$count = learn_press_count_student_enrolled_course( $course_id );
		$count --;
	} else {
		$count = intval( $count );
	}
	if ( $count < 0 ) {
		$count = 0;
	}
	update_post_meta( $course_id, '_lpr_course_number_student', $count );
}

////////////////////// payment//////////////////////////
// woocommerce
function learn_press_is_woo_activate() {
	if ( !function_exists( 'is_plugin_active' ) ) {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}
	return class_exists( 'WC_Install' ) && is_plugin_active( 'woocommerce/woocommerce.php' );
}

function learn_press_user_can_retake_quiz( $quiz_id = null, $user_id = null ) {
	$quiz_id = learn_press_get_quiz_id( $quiz_id );
	if ( !$user_id ) {
		$user_id = get_current_user_id();
	}
	if ( !$quiz_id || !$user_id ) return false;

	if ( !learn_press_user_has_completed_quiz( $user_id, $quiz_id ) ) return false;

	$available = get_post_meta( $quiz_id, '_lpr_retake_quiz', true );//learn_press_settings( 'pages', 'quiz.retake_quiz' );

	if ( !$available ) return false;

	global $wpdb;
	$query = $wpdb->prepare( "
        SELECT count(meta_key)
        FROM {$wpdb->usermeta}
        WHERE user_id = %d
        AND meta_key = %s
        AND meta_value = %d
    ", $user_id, '_lpr_quiz_taken', $quiz_id );
	$taken = $wpdb->get_var( $query );
	return $taken < $available;
}

function learn_press_user_can_retake_course( $course_id = null, $user_id = null ) {
	$course_id = learn_press_get_course_id( $course_id );
	if ( !$user_id ) {
		$user_id = get_current_user_id();
	}
	if ( !$course_id || !$user_id ) return false;

	if ( !learn_press_user_has_finished_course( $course_id, $user_id ) ) return false;

	$available = get_post_meta( $course_id, '_lpr_retake_course', true );//learn_press_settings( 'pages', 'course.retake_course' );
	if ( !$available ) return false;

	global $wpdb;
	$query = $wpdb->prepare( "
        SELECT count(meta_key)
        FROM {$wpdb->usermeta}
        WHERE user_id = %d
        AND meta_key = %s
        AND meta_value = %d
    ", $user_id, '_lpr_course_taken', $course_id );
	$taken = $wpdb->get_var( $query );
	//if( $taken == 0 ) update_user_meta( $user_id, '_lpr_course_taken', $course_id );
	return $taken < $available;
}

function learn_press_add_message( $type, $message ) {
	$messages = get_transient( 'learn_press_message' );
	if ( !$messages ) $messages = array();
	if ( empty( $messages[$type] ) ) $messages[$type] = array();
	$messages[$type][] = $message;
	set_transient( 'learn_press_message', $messages, HOUR_IN_SECONDS );

}

function learn_press_show_message() {
	$messages = get_transient( 'learn_press_message' );
	if ( $messages ) foreach ( $messages as $type => $message ) {
		foreach ( $message as $mess ) {
			echo '<div class="lp-message ' . $type . '">';
			echo $mess;
			echo '</div>';
		}
	}
	delete_transient( 'learn_press_message' );
}

add_action( 'learn_press_before_main_content', 'learn_press_show_message', 50 );

/**
 * Check to see if user can view a quiz or not
 *
 * @param int $user_id
 * @param int $quiz_id
 *
 * @return boolean
 */
function learn_press_user_can_view_quiz( $user_id = null, $quiz_id = null ) {
	if ( !$user_id ) {
		$user_id = get_current_user_id();
	}
	if ( !$quiz_id ) {
		global $quiz;
		$quiz_id = $quiz ? $quiz->ID : 0;
	}

	if ( !$quiz_id ) return false;
	$course_id = get_post_meta( $quiz_id, '_lpr_course', true );
	//return true;
	return learn_press_is_enrolled_course( $course_id, $user_id );
	//
	$status = strtolower( learn_press_get_user_course_status( $user_id, $course_id ) );
	return 'completed' == $status;
}

/**
 * Short function to check if a lesson id is not passed to a function
 * then try to get it from $_REQUEST
 *
 * @param null $lesson_id
 *
 * @return int|null
 */
function learn_press_get_lesson_id( $lesson_id = null ) {
	if ( !$lesson_id ) {
		$lesson_id = !empty( $_REQUEST['lesson'] ) ? $_REQUEST['lesson'] : 0;
	}
	return $lesson_id;
}

/**
 * Get page id from admin settings page
 *
 * @param string $name
 *
 * @return int
 */
function learn_press_get_page_id( $name ) {
	$settings = LPR_Settings::instance( 'pages' );
	return $settings->get( "general.{$name}_page_id", false );
}

function learn_press_plugin_path( $sub = null ) {
	return $sub ? LPR_PLUGIN_PATH . '/' . untrailingslashit( $sub ) . '/' : LPR_PLUGIN_PATH;
}

/**
 * @param null $quiz_id
 *
 * @return bool|int
 */
function learn_press_get_current_question( $quiz_id = null ) {

	$quiz_id = learn_press_get_quiz_id( $quiz_id );

	if ( !$quiz_id ) return false;

	$questions = get_user_meta( get_current_user_id(), '_lpr_quiz_current_question', true );

	$question_id = ( !empty( $questions ) && !empty( $questions[$quiz_id] ) ) ? $questions[$quiz_id] : 0;
	return $question_id;
}

/**
 * get the course of a lesson
 *
 * @author TuNguyen
 *
 * @param int     $lesson_id
 * @param boolean $id_only
 *
 * @return mixed
 */
function learn_press_get_course_by_lesson( $lesson_id, $id_only = true ) {
	$course = get_post_meta( $lesson_id, '_lpr_course' );
	if ( $course ) $course = end( $course );
	if ( !$id_only ) {
		$course = get_post( $course );
	}
	return $course;
}

function learn_press_get_course_by_quiz( $quiz_id, $id_only = true ) {
	$course = get_post_meta( $quiz_id, '_lpr_course', true );
	if ( !$id_only ) {
		$course = get_post( $course );
	}
	return $course;
}

/**
 * mark a lesson is completed for a user
 *
 * @author TuNguyen
 *
 * @param int $lesson_id
 * @param int $user_id
 *
 * @return boolean
 */
function learn_press_mark_lesson_complete( $lesson_id, $user_id = null ) {
	if ( !$user_id ) $user_id = get_current_user_id();
	if ( !$lesson_id ) return false;
	$lesson_completed = get_user_meta( $user_id, '_lpr_lesson_completed', true );
	if ( !$lesson_completed ) {
		$lesson_completed = array();
	}

	$course_id = learn_press_get_course_by_lesson( $lesson_id );
	if ( !isset( $lesson_completed[$course_id] ) || !is_array( $lesson_completed[$course_id] ) ) {
		$lesson_completed[$course_id] = array();
	}
	$lesson_completed[$course_id][] = $lesson_id;
	// ensure that doesn't store duplicate values
	$lesson_completed[$course_id] = array_unique( $lesson_completed[$course_id] );
	update_user_meta( $user_id, '_lpr_lesson_completed', $lesson_completed );

	if ( !learn_press_user_has_finished_course( $course_id ) ) {
		if ( learn_press_user_has_completed_all_parts( $course_id, $user_id ) ) {
			learn_press_finish_course( $course_id, $user_id );
		}
	}
	return true;
}

function learn_press_user_can_finish_course( $course_id = null, $user_id = null, $passing_condition = 0 ) {
	$course_id = learn_press_get_course_id( $course_id );
	if ( !$user_id ) $user_id = get_current_user_id();

	$passing_condition = $passing_condition ? $passing_condition : learn_press_get_course_passing_condition( $course_id );
	if ( get_post_meta( $course_id, '_lpr_course_final', true ) == 'yes' ) {
		$final_quiz = lpr_get_final_quiz( $course_id );
		$passed     = learn_press_quiz_evaluation( $final_quiz, $user_id );
		return $passed && ( $passed >= $passing_condition );
	} else {
		$passed = lpr_course_evaluation( $course_id );
		return $passed && ( $passed >= $passing_condition );
	}
	return false;
}

/**
 * Check to see if user already learned all lessons or completed final quiz
 *
 * @param null $course_id
 * @param null $user_id
 *
 * @return bool
 */
function learn_press_user_has_completed_all_parts( $course_id = null, $user_id = null ) {
	$course_id = learn_press_get_course_id( $course_id );
	if ( !$user_id ) $user_id = get_current_user_id();
	if ( get_post_meta( $course_id, '_lpr_course_final', true ) == 'yes' ) {
		$final_quiz = lpr_get_final_quiz( $course_id );
		return learn_press_user_has_completed_quiz( $user_id, $final_quiz );
	} else {
		return lpr_course_evaluation( $course_id ) == 100;
	}
}

/**
 * Checks to see that an user has finished a lesson or not yet
 * Function return the ID of a course if the user has completed a lesson
 * Otherwise, return false
 *
 * @author TuNguyen
 *
 * @param null $lesson_id
 * @param null $user_id
 *
 * @return mixed
 */
function learn_press_user_has_completed_lesson( $lesson_id = null, $user_id = null ) {
	$lesson_id = learn_press_get_lesson_id( $lesson_id );
	if ( !$user_id ) $user_id = get_current_user_id();

	$completed_lessons = get_user_meta( $user_id, '_lpr_lesson_completed', true );

	if ( !$completed_lessons ) return false;
	foreach ( $completed_lessons as $courses ) {
		if ( is_array( $courses ) && in_array( $lesson_id, $courses ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Get all lessons in a course by ID
 *
 * @param null $course_id
 */
function learn_press_get_lessons_in_course( $course_id = null ) {
	static $lessons = array();
	if ( empty( $lessons[$course_id] ) ) {
		$course_lessons = array();
		$curriculum     = get_post_meta( $course_id, '_lpr_course_lesson_quiz', true );
		if ( $curriculum ) foreach ( $curriculum as $lesson_quiz ) {
			if ( array_key_exists( 'lesson_quiz', $lesson_quiz ) && is_array( $lesson_quiz['lesson_quiz'] ) ) {
				$posts = get_posts(
					array(
						'post_type'   => 'lpr_lesson',
						'include'     => $lesson_quiz['lesson_quiz'],
						'post_status' => 'publish',
						'fields'      => 'ids',
						'numberposts' => - 1
					)
				);
				if ( $posts ) {
					// sorting as in the curriculum section
					foreach ( $lesson_quiz['lesson_quiz'] as $pid ) {
						if ( in_array( $pid, $posts ) ) {
							$course_lessons[] = $pid;
						}
					}
				}
			}
		}
		$lessons[$course_id] = array_unique( $course_lessons );
	}

	return $lessons[$course_id];
}

/**
 * Get all lessons and quizzes of a course
 *
 * @param null $course_id
 * @param bool $id_only
 *
 * @return array
 */
function learn_press_get_lessons_quizzes( $course_id = null, $id_only = true ) {
	$course_id = learn_press_get_course_id( $course_id );
	$sections  = get_post_meta( $course_id, '_lpr_course_lesson_quiz', true );
	$posts     = array();
	if ( $sections ) {
		foreach ( $sections as $section ) {
			if ( !empty( $section['lesson_quiz'] ) && is_array( $section['lesson_quiz'] ) ) {
				$posts = array_merge( $posts, $section['lesson_quiz'] );
			}
		}
	}
	$posts = array_unique( $posts );
	if ( !$id_only ) {
		$posts = get_posts(
			array(
				'post_type' => array( 'lpr_lesson', 'lpr_quiz' ),
				'include'   => $posts
			)
		);
	}
	return $posts;
}

function learn_press_mark_quiz_complete( $quiz_id = null, $user_id = null ) {
	$quiz_id = learn_press_get_quiz_id( $quiz_id );
	if ( !learn_press_user_has_completed_quiz( $quiz_id ) ) {
		if ( !$user_id ) $user_id = get_current_user_id();

		// update quiz start time if not set
		$quiz_start_time = get_user_meta( $user_id, '_lpr_quiz_start_time', true );
		if ( empty( $quiz_start_time[$quiz_id] ) ) {
			$quiz_start_time[$quiz_id] = time();
			update_user_meta( $user_id, '_lpr_quiz_start_time', $quiz_start_time );
		}

		// update questions
		if ( $questions = learn_press_get_quiz_questions( $quiz_id ) ) {

			// stores the questions
			$question_ids = array_keys( $questions );
			$meta         = get_user_meta( $user_id, '_lpr_quiz_questions', true );
			if ( !is_array( $meta ) ) {
				$meta = array( $quiz_id => $question_ids );
			}

			if ( empty( $meta[$quiz_id] ) ) {
				$meta[$quiz_id] = $question_ids;
			}
			update_user_meta( $user_id, '_lpr_quiz_questions', $meta );

			// stores current question
			$meta = get_user_meta( $user_id, '_lpr_quiz_current_question', true );
			if ( !is_array( $meta ) ) $meta = array( $quiz_id => $question_ids[0] );
			if ( empty( $meta[$quiz_id] ) ) $meta[$quiz_id] = end( $question_ids );
			update_user_meta( $user_id, '_lpr_quiz_current_question', $meta );

		}

		// update answers
		$quizzes = get_user_meta( $user_id, '_lpr_quiz_question_answer', true );
		if ( !is_array( $quizzes ) ) {
			$quizzes = array();
		}
		if ( empty( $quizzes[$quiz_id] ) ) {
			$quizzes[$quiz_id] = array();
			update_user_meta( $user_id, '_lpr_quiz_question_answer', $quizzes );
		}
		// update the quiz's ID to the completed list
		$quiz_completed = get_user_meta( $user_id, '_lpr_quiz_completed', true );
		if ( !$quiz_completed ) {
			$quiz_completed = array();
		}
		if ( empty( $quiz_completed[$quiz_id] ) ) {
			$quiz_completed[$quiz_id] = time();
			update_user_meta( $user_id, '_lpr_quiz_completed', $quiz_completed );
		}
		// count
		//add_user_meta($user_id, '_lpr_quiz_taken', $quiz_id);
	}
}

/**
 * Finish a course by ID of an user
 * When a course marked is finished then also mark all lessons, quizzes as completed
 *
 * @param int $course_id
 * @param int $user_id
 *
 * @return array
 */
function learn_press_finish_course( $course_id = null, $user_id = null ) {
	$course_id = learn_press_get_course_id( $course_id );
	if ( !$user_id ) $user_id = get_current_user_id();

	$course_finished = get_user_meta( $user_id, '_lpr_course_finished', true );
	if ( !$course_finished ) $course_finished = array();
	$course_finished[] = $course_id;
	$course_finished   = array_unique( $course_finished );
	update_user_meta( $user_id, '_lpr_course_finished', $course_finished );

	$lesson_quiz = learn_press_get_lessons_quizzes( $course_id, false );

	if ( $lesson_quiz ) foreach ( $lesson_quiz as $post ) {
		if ( 'lpr_lesson' == $post->post_type ) {
			learn_press_mark_lesson_complete( $post->ID );
		} else {
			learn_press_mark_quiz_complete( $post->ID );
		}
	}
	do_action( 'learn_press_user_finished_course', $course_id, $user_id );
	return array(
		'finish'  => true,
		'message' => ''
	);
}

/**
 * Check to see if an user has finish course
 *
 * @author TuNguyen
 *
 * @param null $course_id
 * @param null $user_id
 *
 * @return bool
 */
function learn_press_user_has_finished_course( $course_id = null, $user_id = null ) {
	$course_id = learn_press_get_course_id( $course_id );
	if ( !$user_id ) $user_id = get_current_user_id();

	if ( !$user_id || !$course_id ) return false;
	$courses  = get_user_meta( $user_id, '_lpr_course_finished', true );
	$finished = is_array( $courses ) && in_array( $course_id, $courses );

	return $finished;
}

function learn_press_get_course_passing_condition( $course_id = null ) {
	$course_id = learn_press_get_course_id( $course_id );

	return intval( get_post_meta( $course_id, '_lpr_course_condition', true ) );
}

function learn_press_user_has_enrolled_course( $course_id = null, $user_id = null ){
    $course_id = learn_press_get_course_id( $course_id );
    if( ! $user_id ) $user_id = get_current_user_id();

    $courses = learn_press_get_user_courses( $user_id );
    return is_array( $courses ) && in_array( $course_id, $courses );
}

function learn_press_get_user_courses( $user_id ) {
	$courses = get_user_meta( $user_id, '_lpr_user_course', true );
	return $courses;
}

function learn_press_auto_evaluation_course() {
	$user_id = get_current_user_id();
	$courses = learn_press_get_user_courses( $user_id );

	if ( !$courses ) return;
	$now = time();
	foreach ( $courses as $course_id ) {
		if ( learn_press_user_has_finished_course( $course_id ) ) continue;
		$course_duration = intval( get_post_meta( $course_id, '_lpr_course_duration', true ) ) * 7 * 24 * 3600;
		$course_time     = get_user_meta( $user_id, '_lpr_course_time', true );
		if ( empty( $course_time[$course_id] ) ) {
			$course_time[$course_id] = array(
				'start' => time(),
				'end'   => null
			);
			update_user_meta( $user_id, '_lpr_course_time', $course_time );

		}

		$course_time = $course_time[$course_id];
		$start_time  = intval( $course_time['start'] );

		if ( $course_duration && ( $start_time + $course_duration <= $now ) ) {
			learn_press_finish_course( $course_id, $user_id );
		} else {
			//echo "Time to finish: " . ( ( $start_time + $course_duration - $now ) / ( 7 * 24 * 3600 ) );
		}
	}
}

add_action( 'learn_press_frontend_action', 'learn_press_auto_evaluation_course' );

function learn_press_active_user_course( $status, $order_id ) {
	$order            = new LPR_Order( $order_id );
	$user             = $order->get_user();
	$course_id        = learn_press_get_course_by_order( $order_id );
	$user_course_time = get_user_meta( $user->ID, '_lpr_course_time', true );

	if ( strtolower( $status ) == 'completed' ) {
		if ( empty( $user_course_time[$course_id] ) ) {
			$user_course_time[$course_id] = array(
				'start' => time(),
				'end'   => null
			);
		}
	} else {
		if ( !empty( $user_course_time[$course_id] ) ) {
			unset( $user_course_time[$course_id] );
		}
	}
	if ( $user_course_time ) {
		update_user_meta( $user->ID, '_lpr_course_time', $user_course_time );
	} else {
		delete_user_meta( $user->ID, '_lpr_course_time' );
	}

}

add_action( 'learn_press_update_order_status', 'learn_press_active_user_course', 10, 2 );

function learn_press_get_course_remaining_time( $course_id = null, $user_id = null ) {
	$course_id = learn_press_get_course_id( $course_id );
	if ( !$user_id ) $user_id = get_current_user_id();

	$course_duration = intval( get_post_meta( $course_id, '_lpr_course_duration', true ) ) * 7 * 24 * 3600;
	$course_time     = get_user_meta( $user_id, '_lpr_course_time', true );
	$remain          = false;
	if ( !empty( $course_time[$course_id] ) ) {
		$now         = time();
		$course_time = $course_time[$course_id];
		$start_time  = intval( $course_time['start'] );

		if ( $start_time + $course_duration <= $now ) {

		} else {
			$remain = $start_time + $course_duration - $now;
			$remain = learn_press_seconds_to_weeks( $remain );
		}
	}
	return $remain;
}

/**
 * Get questions from quiz for user
 *
 * @param  int $quiz_id
 * @param  int $user_id
 *
 * @return array
 */
function learn_press_get_user_quiz_questions( $quiz_id = null, $user_id = null ) {
	$quiz_id = learn_press_get_quiz_id( $quiz_id );
	if ( !$user_id ) $user_id = get_current_user_id();

	$questions = get_user_meta( $user_id, '_lpr_quiz_questions', true );


	if ( $questions && !empty( $questions[$quiz_id] ) ) {
		$user_quiz_questions = $questions[$quiz_id];
		$quiz_questions      = get_post_meta( $quiz_id, '_lpr_quiz_questions', true );
		if ( $quiz_questions ) $quiz_questions = array_keys( $quiz_questions );
		else $quiz_questions = array();

		return array_unique( array_merge( $user_quiz_questions, $quiz_questions ) );
	}
	return null;
}

/**
 * Check if user has passed the passing condition or not
 *
 * @param  int $course_id
 * @param  int $user_id
 *
 * @return boolean
 */
function learn_press_user_has_passed_conditional( $course_id = null, $user_id = null ) {
	$course_id = learn_press_get_course_id( $course_id );
	if ( !$user_id ) $user_id = get_current_user_id();
	if ( !$course_id || !$user_id ) return false;

	$has_finished = learn_press_user_has_finished_course( $course_id, $user_id );
	if ( get_post_meta( $course_id, '_lpr_course_final', true ) == 'yes' && $quiz = lpr_get_final_quiz( $course_id ) ) {
		$passed            = learn_press_quiz_evaluation( $quiz, $user_id );
		$passing_condition = learn_press_get_course_passing_condition( $course_id );
	} else {
		$passed            = lpr_course_evaluation( $course_id );
		$passing_condition = 100;
	}
	$return = ( $passed >= $passing_condition ) && ( !$has_finished && $passing_condition < 100 );
	return apply_filters( 'learn_press_user_passed_conditional', $return, $course_id, $user_id, $passed );
}

/**
 * Return if a student passes course or not
 *
 * @param  $course_id int
 * @param  $user_id   int
 *
 * @return boolean
 */
function learn_press_user_has_passed_course( $course_id = null, $user_id = null ) {
	$course_id = learn_press_get_course_id( $course_id );
	if ( !$user_id ) $user_id = get_current_user_id();
	if ( !$course_id || !$user_id ) return 0;

	$has_finished = learn_press_user_has_finished_course( $course_id, $user_id );

	if ( ( get_post_meta( $course_id, '_lpr_course_final', true ) == 'yes' ) && ( $quiz = lpr_get_final_quiz( $course_id ) ) ) {
		$passed            = learn_press_quiz_evaluation( $quiz, $user_id );
		$passing_condition = learn_press_get_course_passing_condition( $course_id );
	} else {
		$passed            = lpr_course_evaluation( $course_id );
		$passing_condition = 0;
	}
	return $passing_condition ? ( $passed >= $passing_condition ? $passed : 0 ) : ( $passed == 100 );
}

function learn_press_get_course_result( $course_id = null, $user_id = null ){
    $course_id = learn_press_get_course_id( $course_id );
    if ( !$user_id ) $user_id = get_current_user_id();
    if ( !$course_id || !$user_id ) return 0;

    if ( ( get_post_meta( $course_id, '_lpr_course_final', true ) == 'yes' ) && ( $quiz = lpr_get_final_quiz( $course_id ) ) ) {
        $passed            = learn_press_quiz_evaluation( $quiz, $user_id );
        //$passing_condition = learn_press_get_course_passing_condition( $course_id );
    } else {
        $passed            = lpr_course_evaluation( $course_id );
        //$passing_condition = 0;
    }
    return $passed;
}

/**
 * Add script on frontend
 */
function learn_press_frontent_script() {
	if ( defined( 'DOING_AJAX' ) || is_admin() ) return;
	$translate = array(
		'confirm_retake_course' => __( 'Be sure you want to retake this course! All your data will be deleted.', 'learn_press' ),
		'confirm_retake_quiz'   => __( 'Be sure you want to retake this quiz! All your data will be deleted.', 'learn_press' ),
		'confirm_finish_quiz'   => __( 'Are you sure you want to finish this quiz?', 'learn_press' )
	);
	LPR_Assets::add_localize( $translate );
}

add_action( 'wp', 'learn_press_frontent_script' );
if ( !empty( $_REQUEST['payment_method'] ) ) {
	add_action( 'learn_press_frontend_action', array( 'LPR_AJAX', 'take_course' ) );
}
////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////

function learn_press_admin_js_template() {
    if( 'lpr_lesson' == get_post_type() ) {
        require_once LPR_PLUGIN_PATH . '/inc/lpr-js-template.php';
    }
}

add_action( 'admin_print_scripts', 'learn_press_admin_js_template' );

/**
 * tinymce option get the keys user pressed
 *
 * @param  array
 *
 * @return array
 */
function learn_press_tiny_mce_before_init( $initArray ) {
	global $post_type;
	if ( !in_array( $post_type, array( 'lpr_lesson' ) ) ) return $initArray;

	$initArray['setup'] = <<<JS
[function(ed) {
    ed.on('keyup', function(e) {
        var ed = tinymce.activeEditor;

        var c = window.char_code,
            ed = tinymce.activeEditor;
        if( c == undefined ) c = [];

        if( e.keyCode == 76 || e.keyCode == 50 ){
            c.push(e.keyCode);
            if(e.keyCode == 50){
                //ed.execCommand('mceInsertContent', false,'<span id="quick_add_link_bookmark"></span>');
            }else if( e.keyCode == 76 ){
                var a = c.pop(), b = c.pop();
                if( b != 50 ){
                    do{
                        b = c.pop();
                    }while( b == 16 )
                }
                if( b == 50 && a == 76 ){
                    FormPress.showLessonQuiz(null, ed);
                }
                c = [];
            }
        }
        window.char_code = c;
    });

}][0]
JS;
	return $initArray;
}

add_filter( 'tiny_mce_before_init', 'learn_press_tiny_mce_before_init' );

