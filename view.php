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
 * Activity view page for the mod_biblereader plugin.
 *
 * @package   mod_biblereader
 * @copyright 2024, Josh Jenney <josh@n2nministries.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
# require_once($CFG->dirroot.'/mod/biblereader/lib.php');
# require_once($CFG->dirroot.'/mod/biblereader/locallib.php'); // depreciated
# require_once($CFG->libdir.'/completionlib.php');

$cmid = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'biblereader');
$instance = $DB->get_record('biblereader', ['id'=> $cm->instance], '*', MUST_EXIST);

// N2NCU 2026-08-01: this page had NO access check of any kind. Every Moodle
// activity view page needs one: without it the page renders for anyone who knows
// a cmid, and set_module_viewed() below writes a completion record for whatever
// $USER happens to be. require_login() with the cm also enforces course
// visibility, activity availability and group restrictions, none of which were
// being applied here.
require_login($course, true, $cm);

// N2NCU 2026-08-01: set_url() moved ahead of anything that builds navigation.
// Navigation initialisation reads $PAGE->url, so setting it afterwards produced
// "This page did not call $PAGE->set_url()" on every load. Same class of problem
// as the subscription pages - see TECH_DEBT 23d.
$url = new moodle_url('/mod/biblereader/view.php', array('id' => $cmid));
$PAGE->set_url($url);

// navbar
$PAGE->set_cm($cm, $course); // sets up global $COURSE

// N2NCU 2026-08-01: two dead assignments removed from here:
//     $data = $cm->get_course();
//     $coursenode = $PAGE->navigation->find($course, navigation_node::TYPE_COURSE);
// $data was overwritten before ever being read, and $coursenode was never read at
// all. The find() call was also a hard failure on PHP 8: find() is declared
// find($key, $type) taking a string|int key and does array_key_exists($key, ...),
// but was handed the $course OBJECT. PHP 7 raised a warning, returned false and
// carried on; PHP 8 raises "Illegal offset type" as a TypeError and the page
// dies before rendering. Deleting the line fixes the crash and the set_url notice
// together, since it was the call forcing navigation to build early.
$PAGE->set_pagelayout('incourse');
$PAGE->add_body_class('limitedwidth');

// update moodle data
# $PAGE->set_context(context_system::instance());
# $PAGE->set_context(context_system::instance($cmid));

// activity view completions
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// log page view
$event = \mod_biblereader\event\course_module_viewed::create(array(
    'objectid' => $PAGE->cm->instance,
    'context' => $PAGE->context,
));
$event->trigger();

# $content = '';
# $content .= '<h1>Planner</h1>';
# $content .= '<h1>Reader</h1>';
# $content .= '<h1>Completion</h1>';

# echo html_writer::table($table);
# $renderable = new \tool_demo\output\index_page('Some text');
# echo $output->render($renderable);

/*
$description = 'Example description';
$data = [
    'name' => 'Lorem ipsum',
    'description' => format_text($description, FORMAT_HTML),
];
*/

// populate data for templates
$data = new stdClass();
$data->planner = array();

$plan = new \mod_biblereader\planner;
$plan->set_cmid($cmid);
$data->planner['progress']     = $plan->get_progress();
$data->planner['passages']     = $plan->get_passages();


// labels - fetch label values
$data->planner['progress_completed_label'] = $data->planner['progress']['label_values']['completed'] .' ' .get_string("completed", "biblereader");
$data->planner['progress_pending_label']   = $data->planner['progress']['label_values']['pending'] .' ' .get_string("remaining", "biblereader");
$data->planner['progress_overall']         = $data->planner['progress']['label_values']['progress_overall'];

// mobile - update testament completion
$data->planner['testament_progress']  = $plan->_JS_CONVERT_ME_get_testament_progress(); // $data->planner['progress']['label_values']['testament_progress'];

// circle - fetch completion details
$data->planner['plans']               = $data->planner['progress']['details'];


// circle - toggle the selected plan
$data->planner['plans'][$data->planner['progress']['label_values']['selected_plan']-1]['selected'] = true;

// TODO: js-scroll to selected plan

// circle - list plans
foreach($data->planner['plans'] as $index => $key) {

  // circle - display percentage?
  $data->planner['plans'][$index]['dial'] = ($key['completed'] > 0 ? true : false);

  // circle - update foreground dial color
  switch ($key['completed']) {
    case 100:
      $data->planner['plans'][$index]['color'] = 'gold';
      $data->planner['plans'][$index]['label'] = $data->planner['plans'][$index]['completed'] . '%';
      break;
    case 0:
      $data->planner['plans'][$index]['color'] = 'lightgray';
      $data->planner['plans'][$index]['label'] = '-'; // 🔒
      break;
    case -1:
      $data->planner['plans'][$index]['color'] = 'gray';
      $data->planner['plans'][$index]['label'] = '🔒';
      break;
    default:
      $data->planner['plans'][$index]['color'] = 'lightgray';
      $data->planner['plans'][$index]['label'] = $data->planner['plans'][$index]['completed'] . '%';
  }

  // circle - update background dial color (gold)
  $data->planner['plans'][$index]['dial'] = ($key['completed'] > 0 ? true : false);
}


// TODO: use bible api services
//       1) server fetches api.biblelabs.com and forward to client
//       2) client fetches api.biblelabs.com and
//          client js-promise acknowledges fullfilled promise or
//          server provides fallback via ajax
//       2) self-host bible api
//       3) client js for clients

// Trigger biblereader reader started event.
# $event = \mod_biblereader\event\reader_started::create(array());
# $event->trigger();

/*
$params = array(
    'context' => context_course::instance($cmid)
);
$event = new \mod_biblereader\event\reader::create($params);
$event->fetch_passage('KJV');
$event->trigger();
*/

// old method
# $data->biblereader['passage'] = \mod_biblereader\reader::fetch_passage();

// new method #1 (current)
/*
$reader = new \mod_biblereader\reader;
$data->biblereader = array();
$data->biblereader['translation'] = [
  ['version' => 'KJV'],
  ['version' => 'NIV'],
  ['version' => 'NLT'],
];
$data->biblereader['passage_index'] = '1';
$data->biblereader['passage_length'] = '7';
$data->biblereader['book_chapter'] = 'Genesis 1';
$data->biblereader['next_book_chapter'] = 'Genesis 2';
$data->biblereader['passage'] =
  $reader->fetch_passage($data->biblereader['book_chapter']);

// new method #2 (not yet used)
# $data->biblereader['passage'] = (new \mod_biblereader\reader)->fetch_passage();

// var_dump($data->biblereader['passage']);

$data->completion = array();

// $CFG->cachejs = false;
*/

echo $OUTPUT->header();

// Just add this after the heading in view.php. Make sure that $cminfo is a cm_info object. (e.g. by calling cm_info::create())
#$cminfo = cm_info::create($cm, $USER->id);
#$completiondetails = \core_completion\cm_completion_details::get_instance($cminfo, $USER->id); // Fetch completion information.
#$activitydates = \core\activity_dates::get_dates_for_module($cminfo, $USER->id); // Fetch activity dates.
#echo $OUTPUT->activity_information($cminfo, $completiondetails, $activitydates);

# echo $OUTPUT->box($content, "generalbox center clearfix");
echo $OUTPUT->render_from_template('mod_biblereader/planner', $data->planner); //TODO: move JS into amd/source!
#echo $OUTPUT->render_from_template('mod_biblereader/biblereader', $data->biblereader);
#echo $OUTPUT->render_from_template('mod_biblereader/completion', $data->completion);
echo $OUTPUT->footer();
