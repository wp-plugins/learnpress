<?php


if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


if ( !class_exists( 'LPR_Profile' ) ) {
	class LPR_Profile {
		/**
		 *  Constructor
		 */
		public function __construct() {
			add_filter( 'learn_press_profile_methods', array( $this, 'learn_press_profile_method' ) );
			add_action( 'wp_loaded', array( $this, 'learn_press_process_profile' ) );
			add_action( 'learn_press_before_profile_content', array( $this, 'learn_press_add_tabs_scripts' ) );
			add_action( 'learn_press_add_profile_tab', array( $this, 'learn_press_add_profile_tab' ) );
			add_filter( 'learn_press_user_info_tab_content', array( $this, 'learn_press_user_info_tab_content' ), 10, 2 );
			add_filter( 'learn_press_user_courses_tab_content', array( $this, 'learn_press_user_courses_tab_content' ), 10, 2 );
			add_filter( 'learn_press_user_quizzes_tab_content', array( $this, 'learn_press_user_quizzes_tab_content' ), 10, 2 );
            add_action( 'learn_press_enrolled_course_after_title', array( $this, 'end_title_content' ), 10, 2 );
		}

		/**
		 * Process profile
		 */
		public function learn_press_process_profile() {
			if ( learn_press_has_profile_method() ) {
				if ( learn_press_get_profile_page_id() == 0 ) {
					$profile         = array(
						'post_title'   => 'Profile',
						'post_content' => '[learn_press_profile]',
						'post_type'    => 'page',
						'post_status'  => 'publish',
					);
					$profile_page_id = wp_insert_post( $profile );
					update_post_meta( $profile_page_id, '_lpr_is_profile_page', 1 );
				}
			} else {
				wp_delete_post( learn_press_get_profile_page_id(), true );
			}
		}

		/*
		 * Profile methods
		 */
		public function learn_press_profile_method( $methods ) {
			$methods['lpr_profile'] = __( 'LearnPress Profile', 'learn_press' );

			return $methods;
		}

		/*
		 * Enqueue jquery ui scripts
		 */
		public function learn_press_add_tabs_scripts() {
			wp_enqueue_style( 'lpr-jquery-ui-css', LPR_CSS_URL . 'jquery-ui.css' );
			wp_enqueue_script( 'lpr-jquery-ui-js', LPR_JS_URL . 'jquery-ui.js', array( 'jquery' ), '', false );
		}

		/*
		 * Add profile tab
		 */
		public function learn_press_add_profile_tab( $user ) {
			$content = '';
			$tabs    = apply_filters(
				'learn_press_profile_tabs',
				array(
					10 => array(
						'tab_id'      => 'user_info',
						'tab_name'    => __( 'User Information', 'learn_press' ),
						'tab_content' => apply_filters( 'learn_press_user_info_tab_content', $content, $user )
					),
					20 => array(
						'tab_id'      => 'user_courses',
						'tab_name'    => __( 'Courses', 'learn_press' ),
						'tab_content' => apply_filters( 'learn_press_user_courses_tab_content', $content, $user )
					),
					30 => array(
						'tab_id'      => 'user_quizzes',
						'tab_name'    => __( 'Quiz Results', 'learn_press' ),
						'tab_content' => apply_filters( 'learn_press_user_quizzes_tab_content', $content, $user )
					)
				),
				$user
			);
			ksort( $tabs );
			echo '<ul>';
			foreach ( $tabs as $tab ) {
				echo '<li><a href="#' . $tab['tab_id'] . '">' . $tab['tab_name'] . '</a></li>';
			}
			echo '</ul>';
			foreach ( $tabs as $tab ) {
				echo '<div id="' . $tab['tab_id'] . '">' . $tab['tab_content'] . '</div>';
			}
		}

		/*
		 * Add content for user information tab
		 */
		public function learn_press_user_info_tab_content( $content, $user ) {
			$content .= sprintf(
				'%s
				<strong>%s</strong>
				<p>%s</p>',
				get_avatar( $user->ID ),
				$user->data->user_nicename,
				get_user_meta( $user->ID, 'description', true )
			);

			return $content;
		}

		/*
		 * Add content for user courses tab
		 */
		public function learn_press_user_courses_tab_content( $content, $user ) {
            global $post;
			$my_query = learn_press_get_enrolled_courses( $user->ID );
			// div #course_taken
			$content .= sprintf(
				'<div id="course_taken">
					<lable>%s</lable>
					<span>(%d)</span>
				',
				__( 'All Enrolled Courses', 'learn_press' ),
				$my_query->post_count
			);
			if ( $my_query->post_count != 0 ) {
				$content .= '<ul class="course">';
				while ( $my_query->have_posts() ):
					$my_query->the_post();
                    ob_start();
					?>
                    <li>
                        <?php do_action( 'learn_press_enrolled_course_before_title', $post, $user );?>
					    <a href="<?php echo esc_url( get_permalink() );?>">
                            <?php do_action( 'learn_press_enrolled_course_begin_title', $post, $user );?>
                            <?php echo get_the_title();?>
                            <?php do_action( 'learn_press_enrolled_course_end_title', $post, $user );?>
                        </a>
                        <?php do_action( 'learn_press_enrolled_course_after_title', $post, $user );?>
					</li>
                    <?php
                    $content .= ob_get_clean();
				endwhile;
				$content .= '</ul>';
			} else {
				$content .= '<p>' . __( 'You have not taken any courses yet!', 'learn_press' ) . '</p>';
			}
			$content .= '</div>';
            wp_reset_postdata();
			// close div #course_taken

			//
			if ( in_array( 'administrator', $user->roles ) || in_array( 'lpr_teacher', $user->roles ) ) {
				// query courses

				$arr_query     = array(
					'post_type'   => 'lpr_course',
					'author'      => $user->ID,
					'post_status' => 'publish',
				);
				$my_query      = new WP_Query( $arr_query );
				$student_taken = 0;
				$student_pass  = 0;
				// end query
				// div #course_made
				$content .= sprintf(
					'<div id="course_made">
						<lable>%s</lable>
						<span>(%d)</span>',
					__( 'All Own Courses', 'learn_press' ),
					$my_query->post_count
				);
				if ( $my_query->post_count != 0 ) {
					$content .= '<ul class="course">';
					while ( $my_query->have_posts() ):
						$my_query->the_post();
						$student_taken += ( get_post_meta( get_the_ID(), '_lpr_course_user', true ) ? count( get_post_meta( get_the_ID(), '_lpr_course_user', true ) ) : 0 );
						$student_pass += get_post_meta( get_the_ID(), '_lpr_total_pass', true );
						$content .= sprintf(
							'<li>
								<a href="%s">%s</a>
								<ul class="course_stats">
									<li>%s: %d</li>
									<li>%s: %d</li>
									<li>%s: %s</li>
								</ul>
							</li>',
							esc_url( get_permalink() ),
							get_the_title(),
							__( 'Student taken', 'learn_press' ),
							$student_taken,
							__( 'Student passed', 'learn_press' ),
							$student_pass,
							__( 'Price', 'learn_press' ),
							learn_press_get_course_price( get_the_ID(), true )

						);
						$student_taken = 0;
						$student_pass  = 0;
					endwhile;
					$content .= '</ul>';
				} else {
					$content .= '<p>' . __( 'You don\'t have got any published courses yet!', 'learn_press' ) . '</p>';
				}
				$content .= '</div>';
			}
			// close div #course_made
			return $content;
		}


		/*
		 * Add content for user quiz results tab
		 */
		public function learn_press_user_quizzes_tab_content( $content, $user ) {
			$query_courses = learn_press_get_enrolled_courses( $user->ID );
			$content .= '<div id="quiz-accordion">';
			if ( $query_courses->post_count != 0 ) {
				while ( $query_courses->have_posts() ):
					$query_courses->the_post();
					$quiz_list = '';
					$quizzes   = learn_press_get_quizzes( get_the_ID() );
					$quiz_list .= sprintf(
						'<table>
							<thead>
								<tr>
									<td>%s</td>
									<td>%s</td>
									<td>%s</td>
									<td>%s</td>
								</tr>
							</thead>
							<tbody>',
						__( 'Quiz', 'learn_press' ),
						__( 'Questions', 'learn_press' ),
						__( 'Result', 'learn_press' ),
						__( 'Time', 'learn_press' )
					);
					foreach ( $quizzes as $quiz ) {
						$quiz_result = learn_press_get_quiz_result( $user->ID, $quiz );
						$quiz_list .= sprintf(
							'<tr>
								<td><a href="%s">%s</a></td>
								<td>%s</td>
								<td>%s</td>
								<td>%s</td>
							</tr>',
							esc_url( get_permalink( $quiz ) ),
							get_the_title( $quiz ),
							empty ($quiz_result['questions_count']) ? __('Empty', 'learn_press'): $quiz_result['questions_count'],
							$quiz_result['correct_percent'] . '%',
							$quiz_result['quiz_time'] . 's'
						);
					}
					$quiz_list .= '</tbody></table>';
					$content .= sprintf(
						'<h3><a href="%s">%s</a></h3>
						<div>%s</div>',
						esc_url( get_permalink() ),
						get_the_title(),
						$quiz_list
					);
				endwhile;
			} else {
				$content .= '<p>' . __( 'You have not taken any courses yet!', 'learn_press' ) . '</p>';
			}
			$content .= '</div>';
			return $content;
		}

        public function end_title_content( $course, $user ){
            if( learn_press_user_has_passed_course( $course->ID, $user->ID ) ){
                _e( '<span class="course-status passed">Passed</span>', 'learn_press');
            }else{

            }
        }
	}

	new LPR_Profile;
}