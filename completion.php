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
 * Activity completion page for the mod_biblereader plugin.
 *
 * @package   mod_biblereader
 * @copyright 2024, Josh Jenney <josh@n2nministries.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$cmid = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'biblereader');
$instance = $DB->get_record('biblereader', ['id'=> $cm->instance], '*', MUST_EXIST);

$planid = required_param('planid', PARAM_INT);

// N2NCU 2026-08-01: no access check existed on this page. See view.php for the
// full reasoning; this is the same fix.
require_login($course, true, $cm);

// N2NCU 2026-08-01: set_url() moved ahead of anything that builds navigation.
$url = new moodle_url('/mod/biblereader/completion.php', array('id' => $cmid, 'planid' => $planid));
$PAGE->set_url($url);

// navbar
$PAGE->set_cm($cm, $course); // sets up global $COURSE
// N2NCU 2026-08-01: two dead assignments removed, one of them a PHP 8 fatal:
//     $data = $cm->get_course();
//     $coursenode = $PAGE->navigation->find($course, navigation_node::TYPE_COURSE);
// $data is reassigned below before it is ever read, and $coursenode was never
// read. The find() call passed the $course OBJECT where a string|int key is
// required - "Illegal offset type" as a TypeError on PHP 8.

$PAGE->set_pagelayout('incourse');
$PAGE->add_body_class('limitedwidth');

// update moodle data
# $PAGE->set_context(context_system::instance());

// log page view
/*
$event = \mod_biblereader\event\course_module_viewed::create(array(
    'objectid' => $PAGE->cm->instance,
    'context' => $PAGE->context,
));
$event->trigger();
*/

// populate data for templates
$data = new stdClass();
$data->completion = array();
$data->completion['biblereader_href'] = new moodle_url('/mod/biblereader/view.php', array('id'=>$cmid));

$plan = new \mod_biblereader\planner;
$plan->set_cmid($cmid);
$plan->get_progress();
$passage_collection = $plan->get_passages($planid);
foreach($passage_collection as $passages){
  if($passages['plan'] == $planid){
    foreach($passages['ot'] as $passage){
      // if($passage['checked'])
        $data->completion['completed'][] = ['chapter' => $passage['passage'], 'checked' => $passage['checked']];
    }
    foreach($passages['nt'] as $passage){
      // if($passage['checked'])
        $data->completion['completed'][] = ['chapter' => $passage['passage'], 'checked' => $passage['checked']];
    }
  }
}

// display content
echo $OUTPUT->header();
// echo '<pre>'; print_r($data->completion['completed']); echo '</pre>';
echo $OUTPUT->render_from_template('mod_biblereader/completion', $data->completion);
echo $OUTPUT->footer();
