<?php
//moodle 2.x
require_once('config.php');
require_once('lib/blocklib.php');
$courses = get_courses();//can be feed categoryid to just effect one category
var_dump($courses);
//foreach($courses as $course) {
//	var_dump($course);
   /*
    * watchout this could reset the layout of the course	
   //$context = get_context_instance(CONTEXT_COURSE,$course->id);
   //blocks_delete_all_for_context($context->id);
   //blocks_add_default_course_blocks($course);
    * 
    */
//} 
?>