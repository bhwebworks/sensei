<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Sensei Analysis Course List Table Class
 *
 * All functionality pertaining to the Admin Analysis Course Data Table in Sensei.
 *
 * @package WordPress
 * @subpackage Sensei
 * @category Core
 * @author WooThemes
 * @since 1.1.3
 *
 * TABLE OF CONTENTS
 *
 * - __construct()
 * - build_data_array()
 * - load_stats()
 * - stats_boxes()
 * - no_items()
 */
class WooThemes_Sensei_Analysis_Course_List_Table extends WooThemes_Sensei_List_Table {
	public $token;
	public $user_id;
	public $user_count;
	public $total_courses;
	public $total_lessons;
	public $total_average_grade;
	public $total_courses_started;
	public $total_courses_ended;
	public $average_courses_per_learner;

	/**
	 * Constructor
	 * @since  1.1.3
	 * @return  void
	 */
	public function __construct ( $course_id = 0, $user_id = 0 ) {
		$this->course_id = intval( $course_id );
		$this->user_id = intval( $user_id );
		// Load Parent token into constructor
		parent::__construct( 'analysis_course' );
		if ( isset( $_GET['user'] ) && -1 == intval( $_GET['user'] ) && isset( $_GET['course_id'] ) && 0 < intval( $_GET['course_id'] ) ) {
			// Default Columns
			$this->columns = array(
				'user_login' => __( 'Learner', 'woothemes-sensei' ),
				'user_course_date_started' => __( 'Date Started', 'woothemes-sensei' ),
				'user_course_date_completed' => __( 'Date Completed', 'woothemes-sensei' )
			);
			// Sortable Columns
			$this->sortable_columns = array(
				'user_login' => array( 'user_login', false ),
				'user_course_date_started' => array( 'user_course_date_started', false ),
				'user_course_date_completed' => array( 'user_course_date_completed', false )
			);
		} else {
			// Default Columns
			$this->columns = array(
				'lesson_title' => __( 'Lesson', 'woothemes-sensei' ),
				'lesson_started' => __( 'Date Started', 'woothemes-sensei' ),
				'lesson_completed' => __( 'Date Completed', 'woothemes-sensei' ),
				'lesson_status' => __( 'Status', 'woothemes-sensei' ),
				'lesson_grade' => __( 'Grade', 'woothemes-sensei' )
			);
			// Sortable Columns
			$this->sortable_columns = array(
				'lesson_title' => array( 'lesson_title', false ),
				'lesson_started' => array( 'lesson_started', false ),
				'lesson_completed' => array( 'lesson_completed', false ),
				'lesson_status' => array( 'lesson_status', false ),
				'lesson_grade' => array( 'lesson_grade', false )
			);
			// Handle Missing User ID
			if ( 0 == $this->user_id ) {
				$this->hidden_columns = array(
						'lesson_started',
						'lesson_completed',
						'lesson_status',
						'lesson_grade'
					);
				$this->columns['lesson_students'] = __( 'Learners', 'woothemes-sensei' );
				$this->columns['lesson_average_grade'] = __( 'Average Grade', 'woothemes-sensei' );
				$this->sortable_columns['lesson_students'] = array( 'lesson_students', false );
				$this->sortable_columns['lesson_average_grade'] = array( 'lesson_average_grade', false );
			} // End If Statement
		} // End If Statement
		// Actions
		add_action( 'sensei_before_list_table', array( &$this, 'course_data_table_header' ) );
		add_action( 'sensei_after_list_table', array( &$this, 'course_data_table_footer' ) );
	} // End __construct()

	/**
	 * build_data_array builds the data for use in the table
	 * Overloads the parent method
	 * @since  1.1.3
	 * @return array
	 */
	public function build_data_array() {

		global $woothemes_sensei;

		$return_array = array();

		// Course Students or Lessons
		if ( isset( $_GET['user'] ) && -1 == intval( $_GET['user'] ) && isset( $_GET['course_id'] ) && 0 < intval( $_GET['course_id'] ) ) {
			// Get the data required
			$args_array = array();
			if ( isset( $_POST['s'] ) && '' != esc_html( $_POST['s'] ) ) {
				$args_array['search'] = esc_html( $_POST['s'] );
			} // End If Statement
			$users = get_users( $args_array );
			$output_counter = 0;
			foreach ( $users as $user_key => $user_item ) {
				$output_counter++;
				$course_start_date =  WooThemes_Sensei_Utils::sensei_get_activity_value( array( 'post_id' => $this->course_id, 'user_id' => $user_item->ID, 'type' => 'sensei_course_start', 'field' => 'comment_date' ) );
				$course_end_date =  WooThemes_Sensei_Utils::sensei_get_activity_value( array( 'post_id' => $this->course_id, 'user_id' => $user_item->ID, 'type' => 'sensei_course_end', 'field' => 'comment_date' ) );
				// Output the users data
				if ( isset( $course_start_date ) && '' != $course_start_date ) {
					array_push( $return_array, array( 	'user_login' => '<a href="' . add_query_arg( array( 'page' => 'sensei_analysis', 'user' => $user_item->ID, 'course_id' => $this->course_id ), admin_url( 'edit.php?post_type=lesson' ) ) . '">'.$user_item->user_login.'</a>',
													'user_course_date_started' => $course_start_date,
													'user_course_date_completed' => $course_end_date

				 								)
							);
				} // End If Statement
			} // End For Loop
		} else {
			$lesson_start_date = '';

			$posts_array = $woothemes_sensei->post_types->course->course_lessons( $this->course_id );
			// MAIN LOOP
			foreach ($posts_array as $lesson_item) {
				$title_keyword_count = 1;
				if ( isset( $_POST['s'] ) && '' != $_POST['s'] ) {
				$title_keyword_count = substr_count( strtolower( sanitize_title( $lesson_item->post_title ) ) , strtolower( sanitize_title( $_POST['s'] ) ) );
				} // End If Statement
				if ( 0 < intval( $title_keyword_count ) ) {
					$lesson_status = __( 'In Progress', 'woothemes-sensei' );
					$lesson_end_date = '';
					// Check if Lesson is complete
			    	$user_lesson_end =  WooThemes_Sensei_Utils::sensei_get_activity_value( array( 'post_id' => $lesson_item->ID, 'user_id' => $this->user_id, 'type' => 'sensei_lesson_end', 'field' => 'comment_content' ) );
			    	// Get Quiz ID
			    	$lesson_quizzes = $woothemes_sensei->post_types->lesson->lesson_quizzes( $lesson_item->ID );
			    	$lesson_grade = __( 'No Grade', 'woothemes-sensei' );
			    	$lesson_quiz_id = 0;
			    	foreach ($lesson_quizzes as $quiz_item) {
			    		$lesson_start_date =  WooThemes_Sensei_Utils::sensei_get_activity_value( array( 'post_id' => $quiz_item->ID, 'user_id' => $this->user_id, 'type' => 'sensei_quiz_answers', 'field' => 'comment_date' ) );
			    		if ( 0 < count( $lesson_start_date ) ) {
			    			$quiz_grade =  WooThemes_Sensei_Utils::sensei_get_activity_value( array( 'post_id' => $quiz_item->ID, 'user_id' => $this->user_id, 'type' => 'sensei_quiz_grade', 'field' => 'comment_content' ) );
			    			if ( 0 < intval( $quiz_grade ) ) {
			    				$lesson_grade = $quiz_grade . '%';
			    			} // End If Statement
		    			} // End If Statement
		    			$lesson_quiz_id = $quiz_item->ID;
			    	} // End For Loop
			    	if ( isset( $user_lesson_end ) && '' != $user_lesson_end ) {
			    		$lesson_status = __( 'Complete', 'woothemes-sensei' );
			    		$lesson_end_date =  WooThemes_Sensei_Utils::sensei_get_activity_value( array( 'post_id' => $lesson_item->ID, 'user_id' => $this->user_id, 'type' => 'sensei_lesson_end', 'field' => 'comment_date' ) );
			    	} // End If Statement

			    	// Data to build table
			    	$data_array = array( 	'lesson_title' => '<a href="' . add_query_arg( array( 'page' => 'sensei_analysis', 'user' => $this->user_id, 'lesson_id' => $lesson_item->ID ), admin_url( 'edit.php?post_type=lesson' ) ) . '">'.$lesson_item->post_title.'</a>',
														'lesson_started' => $lesson_start_date,
														'lesson_completed' => $lesson_end_date,
														'lesson_status' => $lesson_status,
														'lesson_grade' => $lesson_grade
					 								);
			    	if ( 0 == $this->user_id ) {
			    		$lesson_students = WooThemes_Sensei_Utils::sensei_check_for_activity( array( 'post_id' => $lesson_quiz_id,'type' => 'sensei_quiz_answers' ), true );
			    		$total_quiz_grades = WooThemes_Sensei_Utils::sensei_check_for_activity( array( 'type' => 'sensei_quiz_grade' ), true );
			    		$total_grade_count = 0;
						$total_grade_total = 0.00;
			    		// Calculate the average quiz grade
			    		foreach ( $total_quiz_grades as $total_quiz_key => $total_quiz_value ) {
							if ( $lesson_quiz_id == $total_quiz_value->comment_post_ID ) {
								$total_grade_total = $total_grade_total + doubleval( $total_quiz_value->comment_content );
						    	$total_grade_count++;
							} // End If Statement
						} // End For Loop
						$total_average_grade = 0;
						if ( 0 < count( $lesson_students ) ) {
							$total_average_grade = abs( round( doubleval( $total_grade_total / $total_grade_count ), 2 ) );
						} // End If Statement
			    		$data_array['lesson_students'] = count( $lesson_students );
						$data_array['lesson_average_grade'] = $total_average_grade . '%';
			    	} // End If Statement

					array_push( $return_array, $data_array );
				} // End If Statement
			} // End For Loop
		} // End If Statement
		$return_array = $this->array_sort_reorder( $return_array );
		return $return_array;
	} // End build_data_array()

	/**
	 * load_stats loads stats into object
	 * @since  1.1.3
	 * @return void
	 */
	public function load_stats() {
		global $woothemes_sensei;
	} // End load_stats()

	/**
	 * stats_boxes loads which stats boxes to render
	 * @return $stats_to_render array of stats boxes and values
	 */
	public function stats_boxes () {
		$stats_to_render = array();
		return $stats_to_render;
	} // End stats_boxes

	/**
	 * no_items sets output when no items are found
	 * Overloads the parent method
	 * @since  1.1.3
	 * @return void
	 */
	public function no_items() {
		if ( isset( $_GET['user'] ) && -1 == intval( $_GET['user'] ) && isset( $_GET['course_id'] ) && 0 < intval( $_GET['course_id'] ) ) {
			_e( 'No learners found.', 'woothemes-sensei' );
		} else {
  			_e( 'No lessons found.', 'woothemes-sensei' );
  		} // End If Statement
	} // End no_items()

	public function course_data_table_header() {
		if ( isset( $_GET['user'] ) && -1 == intval( $_GET['user'] ) && isset( $_GET['course_id'] ) && 0 < intval( $_GET['course_id'] ) ) {
			echo '<strong>' . __( 'Learners taking this Course', 'woothemes-sensei' ) . '</strong>';
		} else {
			echo '<strong>' . __( 'Lessons in this Course', 'woothemes-sensei' ) . '</strong>';
		} // End If Statement
	}

	public function course_data_table_footer() {
		if ( isset( $_GET['user'] ) && -1 == intval( $_GET['user'] ) && isset( $_GET['course_id'] ) && 0 < intval( $_GET['course_id'] ) ) {
			// Nothing yet
			echo '<a href="' . add_query_arg( array( 'page' => 'sensei_analysis', 'course_id' => $this->course_id ), admin_url( 'edit.php?post_type=lesson' ) ) . '">' . __( 'View Lessons in this Course', 'woothemes-sensei' ) . '</a>';
		} else {
			// Nothing yet
			echo '<a href="' . add_query_arg( array( 'page' => 'sensei_analysis', 'user' => '-1', 'course_id' => $this->course_id ), admin_url( 'edit.php?post_type=lesson' ) ) . '">' . __( 'View Learners taking this Course', 'woothemes-sensei' ) . '</a>';
		} // End If Statement
	}

} // End Class
?>