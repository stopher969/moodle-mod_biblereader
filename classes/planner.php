<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Local library file for the mod_biblereader plugin. These are non-standard
 * functions that are used only by the mod_biblereader plugin.
 *
 * @package   mod_biblereader
 * @copyright 2024, Josh Jenney <josh@n2nministries.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_biblereader;

/** Make sure this isn't being directly accessed */
defined('MOODLE_INTERNAL') || die();

global $USER, $DB, $CFG;
require_once("{$CFG->libdir}/completionlib.php");

class planner {

 // properties
 private int $cmid;
 private int $program;
 private int $selected_plan = 1;
 private int $semester;
 private array $passages;
 private array $plans;
 private array $progress;
 private array $testament_progress;

 // methods
 public function __construct(){
   // DEBUG: arguments cannot be provided at instantiation from a global namespace
   // eg: $plan = new \mod_biblereader\planner;
 }

 public function set_cmid($cmid = 0){
   global $DB;

   $cmid = required_param('id', PARAM_INT);
   [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'biblereader');
   $instance = $DB->get_record('biblereader', ['id'=> $cm->instance], '*', MUST_EXIST);

   $this->cmid = $cmid;
   $this->program = $instance->program;
   $this->semester = $instance->semester;

   $this->progress();
   $this->passages();
 }

 private function update_grade($instance){
   global $CFG, $USER;

   require_once($CFG->dirroot.'/mod/biblereader/lib.php');
   if (!function_exists('grade_update'))
       require_once($CFG->libdir.'/gradelib.php');

   // calculate the new grade
   $instance->grade = (int) ceil((
     $this->progress['completion']['read'] / $this->progress['completion']['total']
     )*100);
   biblereader_update_grades($instance, $USER->id);
 }

 public function set_passage_read($instance, $pageid = 0){
   global $USER, $DB;
   if(!$DB->record_exists('biblereader_completions', array('pageid'=>$pageid,'userid'=>$USER->id))){
     $row = (object) array('pageid' => $pageid, 'userid' => $USER->id, 'timecompleted' => time());
     return $DB->insert_record('biblereader_completions', $row, true);
   } else return false;

 }

 private function progress() {
  // determine how many plans should be unlocked/available
  $plansUnlockedCount = $this->plansUnlockedCount($this->semester);
  #$plansUnlockedCount = 1; // sandbox data

  // determine how many plans have been completed
  function myFunc(int $plansUnlockedCount = 1, int $semester = 1){
    global $USER, $DB;
    $userid = $USER->id;

    // debug if($userid == 193) $userid = 413;

    // get sum
    $sql =
"SELECT COUNT({biblereader_plans}.`id`) AS `sum`
 FROM {biblereader_plans}
 WHERE {biblereader_plans}.`program` = ? AND {biblereader_plans}.`semester` = ?;
";
    $params = array('program' => 1, 'semester' => $semester);
    $total = $DB->get_record_sql($sql,$params);

    $sql = "SELECT {biblereader_plans}.`id`, {biblereader_plans}.`plan`, {biblereader_plans}.`sortorder`, {biblereader_completions}.`userid`, {biblereader_completions}.`timecompleted` FROM {biblereader_plans} LEFT OUTER JOIN {biblereader_completions} ON {biblereader_plans}.`id` = {biblereader_completions}.`pageid` AND {biblereader_completions}.`userid` = ? WHERE {biblereader_plans}.`program` = ? AND {biblereader_plans}.`semester` = ? ORDER BY {biblereader_plans}.`plan` ASC, {biblereader_plans}.`sortorder` ASC";
    $params = array('userid' => $userid, 'program' => 1, 'semester' => $semester);
    $result = $DB->get_records_sql($sql,$params);

    // result
    #error_log(print_r($result,true));

    $plan_collection = array();
    foreach($result as $row){
        if(!isset($plan_collection[$row->plan]))
            $plan_collection[$row->plan] = array('available' => 0, 'read' => 0);

        $plan_collection[$row->plan]['available']++;

        if($row->timecompleted > 0)
            $plan_collection[$row->plan]['read']++;
    }

    $sums = array('read' => 0, 'available' => 0, 'total' => 0);
    $sums['total'] = $total->sum;

    // sum
    #error_log(print_r($sums,true));

    // plan_completion
    # error_log(print_r($plan_collection,true));


    $plan_completion = array();
    foreach($plan_collection as $index => $plan){

      $sums['read'] = $sums['read'] + $plan['read'];
      $sums['available'] = $sums['available'] + $plan['available'];
      $plan_completion[$index] = (int) ceil(( $plan['read'] / $plan['available'] )*100);

      /*

    	if($userid == 506 && $semester == 6) { // HELP DESK TICKET #516
              $sums['read'] = $sums['read'] + $plan['read'];
              $sums['available'] = $sums['available'] + $plan['available'];
              $plan_completion[$index] = (int) ceil(( $plan['read'] / $plan['available'] )*100);

    	} else {
    	        // ignore plans not yet unlocked by user progress
              if($index <= $plansUnlockedCount) {
    	          $sums['read'] = $sums['read'] + $plan['read'];
                $sums['available'] = $sums['available'] + $plan['available'];
              }

              // lock all plans beyond the first plan
              // unless the previous plan has been completed
              if($index > 2 && $plan_collection[$index]['read'] < $plan_collection[$index]['available'])
              {
                $plan_completion[$index] = 0; // -1;
                // $plan_completion[$index] = -1;
              } else {
                $plan_completion[$index] = (int) ceil(( $plan['read'] / $plan['available'] )*100);
              }
    	}
      /**/

    }

    // sum
    #error_log(print_r($sums,true));

    return array($sums, $plan_completion);

    /*
    return array(
        array(
            'read' => 14 + 7 + 1,     //
            'available' => 14 * 4     // SELECT count(`id`) as 'available' from `biblereader_plans` WHERE `program` = 1 AND `semester` = 1;
        ),
        array(
            1 => (int) ceil(( 2 / 14 )*100),
      //    2 => (int) ceil(( 7 / 14 )*100),
      //    3 => (int) ceil(( 1 / 14 )*100),
      //    4 => (int) ceil(( 0 / 14 )*100)
      //    ...
      //    12 => (int) ceil(( 0 / 14 )*100)
        )
    );
    */
  }

  // determine how many passages and plans have been completed
  [$passages_count, $userPlanCompletion] = myFunc($plansUnlockedCount, $this->semester);

  // error_log(print_r($userPlanCompletion,true));

  $selected_plan = 1; $completed = 0; $pending = 0;
  foreach($userPlanCompletion as $index => $percentage)
    if($percentage == 100) {
      if($index < 12)
        $selected_plan++;
      $completed++;
    } else if ($index <= 12) { // $plansUnlockedCount
      $pending++;
    }
    // update selected plan
    $this->selected_plan = $selected_plan;


  for($i = 0; $i < 12; $i++){ //  $plansUnlockedCount
    $this->plans[] = [
      'plan'      => $i + 1,
      'completed' => (isset($userPlanCompletion[$i+1]) ? $userPlanCompletion[$i+1] : 0),
      'available' => ($userPlanCompletion[$i+1] > -1 ? 'available' : null),
      // -- implemented on php renderer
      // 'label'     => '100%',
      // 'color'     => 'gold',
      // 'dial'      => true,
      // 'selected'  => true
    ];
   }

  // progress bar
  # error_log((int) ceil(($passages_count['read']) / ($passages_count['total']) * 100));
  # error_log( $passages_count['read'] / $passages_count['total'] );

  // update progress
  $this->progress = [
     'label_values' => [
       'selected_plan'    => $this->selected_plan,  // selected plan
       'pending'          => $pending, // label
       'completed'        => $completed, // label
//       'progress_overall' => (int) ceil(($passages_count['read']) / ($passages_count['available']) * 100) // bar graph percentage
       'progress_overall' => (int) ceil(($passages_count['read']) / ($passages_count['total']) * 100) // bar graph percentage
     ],
     'details'  => $this->plans
  ];
 }

 public function get_progress() {
  return $this->progress;
 }

 private function plansUnlockedCount(int $semester = 1, int $userid = null) {

     global $USER, $DB;

     if(!isset($userid))
      $userid = $USER->id;

     $program = 100;
     if($result = $DB->get_record('pathfinder_users', array('id' => $userid), '*'))
         $program = $result->program;

     if($result = $DB->get_records('pathfinder_data', array('program' => $program, 'category' => $semester), 'sortorder', '*'))
         foreach($result as $row)
             $courses[] = $row->course;

     // DEBUG: array of course ids in semester
     # $courses = array(19,72,64,66,36);
     # $courses = array(19);
     # print_r($courses);

     $data = array();
     foreach($courses as $course)
         $data[$course] = $this->getCourseCompletions($course, $userid);

     // DEBUG: course completions
     # echo '<pre>'; print_r($data); echo '</pre>';

     // Counters start at 0 so the ratio below is arithmetically correct. The
     // DIV by 0 that this initialisation used to cause is now handled where it
     // actually occurs - at the division - rather than by offsetting the inputs.
     // See the commit message: this line has flipped between 0 and 1 four times.
     $unlocked = 0; $sum = 0;
     foreach($data as $course)
         foreach($course as $activity){
             if($activity == 1)
                 $unlocked++;
             $sum++;
         }

     // DEBUG: activity completion
     # echo '<pre>'; print_r(array('unlocked'=>$unlocked,'sum'=>$sum)); echo '</pre>';

     // provide count of plans ready for viewing
     // $sum is 0 when the user has no activities at all - that is the DIV by 0
     // case. Fall back to 1, matching the floor applied just below.
     $result = ($sum > 0) ? round( $unlocked / ($sum / 12)) : 1;
     if ($result < 1)
        $result = 1;

     return $result;
 }

 private function getCourseCompletions(int $id = 0, int $userid = NULL){

     global $USER, $DB;

     if(!isset($userid))
      $userid = $USER->id;

     $course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

     $info = new \completion_info($course);

     $params = array(
         'userid' => $userid,
         'course' => $course->id,
     );

     // Save row data.
     $rows = array();

     // Load criteria to display.
     $completions = $info->get_completions($userid);

     // Loop through course criteria.
     foreach ($completions as $completion) {
         $criteria = $completion->get_criteria();

         if($criteria->module == 'lesson'){
             $row = array();
             # $row['type'] = $criteria->criteriatype;
             # $row['title'] = $criteria->get_title();
             # $row['status'] = $completion->get_status();
             $row = ($completion->get_status() == "Yes" ? 1 : 0);
             # $row = $completion->get_status();
             # $row['complete'] = $completion->is_complete();
             # $row['timecompleted'] = $completion->timecompleted;
             # $row['details'] = $criteria->get_details($completion);
             $rows[] = $row;
         }
     }

     // debug
     # echo '<pre>'; print_r($rows); echo '</pre>';

     return $rows;
 }

 public function _JS_CONVERT_ME_get_testament_progress(){
   $this->testament_progress = [
     'ot' => [
       'completed' => 99,
       'total'     => 99,
       'visible'   => false,
     ],

     'nt' => [
       'completed' => 99,
       'total'     => 99,
       'visible'   => false,
     ]
   ];

   return $this->testament_progress;
 }

 public function get_reader_controls(int $pageid = 0){

 global $USER, $DB;
 $userPrefs = $DB->get_record('biblereader_prefs', array('userid'=>$USER->id), 'prefs');
 #error_log(print_r(json_decode($userPrefs->prefs)));
 #error_log($userPrefs->prefs);

  // if $page is in_array $this->passages then provide output
  foreach($this->passages as $plan_collection){

    foreach($plan_collection['ot'] as $index => $plan_details){
      if($plan_details['pageid'] == $pageid) {
        return array(
           'prefs'  => (isset($userPrefs->prefs) ? json_decode($userPrefs->prefs, JSON_OBJECT_AS_ARRAY) : [] ),
           'passage_index'  => $index + 1,
           'passage_length' => count($plan_collection['ot']),
           'current_chapter_title' => $plan_details['passage'],
           'prev_chapter_title' => (isset($plan_collection['ot'][$index-1]['passage']) ? $plan_collection['ot'][$index-1]['passage'] : NULL),
           'next_chapter_title' => (isset($plan_collection['ot'][$index+1]['passage']) ? $plan_collection['ot'][$index+1]['passage'] : NULL),
           'prev_chapter_href' => (isset($plan_collection['ot'][$index-1]['pageid']) ?
              '?id='. $plan_collection['ot'][$index-1]['id'] .'&pageid=' .$plan_collection['ot'][$index-1]['pageid'] .'&ref=' .$plan_collection['ot'][$index-1]['passage'] : NULL ),
           'next_chapter_href' => (isset($plan_collection['ot'][$index+1]['pageid']) ?
              '?id='. $plan_collection['ot'][$index+1]['id'] .'&pageid=' .$plan_collection['ot'][$index+1]['pageid'] .'&ref=' .$plan_collection['ot'][$index+1]['passage'] :
              'completion.php?id='. $plan_collection['ot'][$index  ]['id'] .'&planid=' .$plan_collection['plan']
            ),
           'planner_href' => $plan_collection['ot'][$index]['id'],
           // N2NCU 2026-08-01: was '?id' with no '=', producing ?id6222&planid=1 -
           // the cmid silently lost and the page landing on a missing required
           // param. Reachable: reading.php line ~149 uses completed_href as the
           // "next" target on the last passage of a plan.
           'completed_href' => '?id='. $plan_collection['ot'][$index]['id'] .'&planid=' .$this->progress['label_values']['selected_plan'],
         );
       }
     }

     foreach($plan_collection['nt'] as $index => $plan_details){
       if($plan_details['pageid'] == $pageid) {
         return array(
           'prefs'  => (isset($userPrefs->prefs) ? json_decode($userPrefs->prefs, JSON_OBJECT_AS_ARRAY) : [] ),
           'passage_index'  => $index + 1,
           'passage_length' => count($plan_collection['nt']),
           'current_chapter_title' => $plan_details['passage'],
           'prev_chapter_title' => (isset($plan_collection['nt'][$index-1]['passage']) ? $plan_collection['nt'][$index-1]['passage'] : NULL),
           'next_chapter_title' => (isset($plan_collection['nt'][$index+1]['passage']) ? $plan_collection['nt'][$index+1]['passage'] : NULL),
           'prev_chapter_href' => (isset($plan_collection['nt'][$index-1]['pageid']) ?
              '?id='. $plan_collection['nt'][$index-1]['id'] .'&pageid=' .$plan_collection['nt'][$index-1]['pageid'] .'&ref=' .$plan_collection['nt'][$index-1]['passage'] : NULL ),
           'next_chapter_href' => (isset($plan_collection['nt'][$index+1]['pageid']) ?
              '?id='. $plan_collection['nt'][$index+1]['id'] .'&pageid=' .$plan_collection['nt'][$index+1]['pageid'] .'&ref=' .$plan_collection['nt'][$index+1]['passage'] :
              'completion.php?id='. $plan_collection['nt'][$index  ]['id'] .'&planid=' .$plan_collection['plan']
            ),
           'planner_href' => $plan_collection['nt'][$index]['id'],
         );
       }
     }

   }
 }

 private function passages(){

   global $USER, $DB;
   $completions = $DB->get_records_menu('biblereader_completions', array('userid' => $USER->id), 'pageid', '*');

   $result = $DB->get_records('biblereader_plans', array('program' => $this->program, 'semester' => $this->semester), 'plan,sortorder', '*');

   $collection = array();
   foreach($result as $row){
        // init plan
        if(!isset($collection[$row->plan]))
            $collection[$row->plan] = array(
                'plan'    => $row->plan,
                'hidden'     => ($row->plan == $this->selected_plan ? false : true), // ($row->plan == $this->progress['label_values']['selected_plan'] ? false : true),
                'ot'         => [],
                'nt'         => [],
            );

        // save row
        $collection[$row->plan][$row->testament][] = array(
            'pageid'    => $row->id,
            'id'        => $this->cmid,
            'passage'   => $row->passage,
            'checked'   => (in_array($row->id,$completions) ? true : false),
        );

   }

   // Empty...
   $this->passages = array();

   // ...and fill!
   foreach($collection as $plan)
        $this->passages[] = $plan;
 }

 public function get_passages(){
   // return results
   return $this->passages;
 }

}
