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
require_once($CFG->dirroot.'/mod/biblereader/lib.php');
# require_once($CFG->dirroot.'/mod/biblereader/locallib.php'); // depreciated
# require_once($CFG->libdir.'/completionlib.php');

$cmid = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'biblereader');
$instance = $DB->get_record('biblereader', ['id'=> $cm->instance], '*', MUST_EXIST);

// TODO: use ref (if valid) or use pageid instead
$ref = optional_param('ref', null, PARAM_TEXT);
$version = optional_param('ver', null, PARAM_TEXT);

// reading plan
$pageid = required_param('pageid', PARAM_INT);

// N2NCU 2026-08-01: no access check existed on this page. See view.php for the
// full reasoning; this is the same fix.
require_login($course, true, $cm);

// N2NCU 2026-08-01: set_url() moved ahead of anything that builds navigation.
// It was at line 66, after the navigation call below, which produced "This page
// did not call $PAGE->set_url()" on every load.
$PAGE->set_url(new moodle_url('/mod/biblereader/reading.php', ['id' => $cmid, 'pageid' => $pageid]));

// navbar
$PAGE->set_cm($cm, $course); // sets up global $COURSE
$data = $cm->get_course();
// N2NCU 2026-08-01: removed a dead assignment that was also a PHP 8 fatal:
//     $coursenode = $PAGE->navigation->find($course, navigation_node::TYPE_COURSE);
// find() takes a string|int key and does array_key_exists($key, ...), but was
// passed the $course OBJECT - "Illegal offset type" as a TypeError on PHP 8,
// merely a warning on PHP 7. $coursenode was never read. Note $data above IS
// live: line ~118 sets $data->biblereader on that course object.
/*
$PAGE->navbar->add(
  get_string('semester_category', 'biblereader') ." {$data->category}",
  new moodle_url("/course/index.php?categoryid={$data->category}"),
  navigation_node::TYPE_CONTAINER
);

$PAGE->navbar->add(
  get_string("modulename", "biblereader"),
  new moodle_url("/mod/biblereader/index.php?id={$cmid}"),
  navigation_node::TYPE_CONTAINER
);
$PAGE->navigation->make_active();
*/
$PAGE->set_pagelayout('incourse');
$PAGE->add_body_class('limitedwidth');

// update moodle data
# $PAGE->set_context(context_system::instance());
$url = new moodle_url('/mod/biblereader/reading.php', array('id'=>$cmid));
$PAGE->set_url($url);

// log page view
/*
$event = \mod_biblereader\event\course_module_viewed::create(array(
    'objectid' => $PAGE->cm->instance,
    'context' => $PAGE->context,
));
$event->trigger();
*/

// update grades
# global $USER;
# $userid = $USER->id;
# $instance->grade = 96;
# biblereader_update_grades($instance, $userid);

// debug
# error_log( biblereader_submit_grading_form('example') );

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

// new method #1
$plan = new \mod_biblereader\planner;
$reader = new \mod_biblereader\reader;

$plan->set_cmid($cmid);
$plan->get_progress();
$plan->get_passages();

$data->biblereader = array();
if($details = $plan->get_reader_controls($pageid)){

  #error_log($details['prefs']['translation']);

  $data->biblereader['version']               = (isset($version) ? $version :
                                                  (isset($details['prefs']['translation']) ? $details['prefs']['translation'] : 'KJV'));
  $data->biblereader['prefs']                 = (isset($details['prefs']) ? $details['prefs'] : []);
  $data->biblereader['passage_index']         = $details['passage_index']; // 1
  $data->biblereader['passage_length']        = $details['passage_length']; // 7
  $data->biblereader['book_chapter']          = $details['current_chapter_title']; // Genesis 1

  $data->biblereader['prev_book_chapter']     = (!is_null($details['prev_chapter_title']) ? $details['prev_chapter_title'] : false); // Genesis 1
  $data->biblereader['next_book_chapter']     = (!is_null($details['next_chapter_title']) ? $details['next_chapter_title'] : get_string('last_passage', 'biblereader')); // Genesis 2

  $data->biblereader['prev_chapter_href']     = (!is_null($details['prev_chapter_href']) ? $details['prev_chapter_href']
                                                  .(isset($version) ? '&ver='.$version : '') : false);
  $data->biblereader['next_chapter_href']     = (!is_null($details['next_chapter_href']) ? $details['next_chapter_href']
                                                  .(isset($version) ? '&ver='.$version : '') : $details['completed_href']);

  $data->biblereader['planner_href']          = (!is_null($details['planner_href']) ? $details['planner_href'] : false);

                                                $reader->set_version($data->biblereader['version']);

  $data->biblereader['translations']          = $reader->get_versions();
  # error_log(print_r($data->biblereader['translations']));

                                                $reader->curl_api_bible($data->biblereader['book_chapter']);
                // /* REMOVE FROM PRODUCTION */   $reader->curl_example_data(); // DELETE ME
  $data->biblereader['passage']               = $reader->fetch_passage();

  // update user activity status -- mark chapter as read by student
  // $plan->set_passage_read($instance, $pageid);

  // update grade -- TODO: integrate as set_passage_read()
  # $plan->update_grade();
  # $plan->update_grade($instance, $userid);
  # error_log(print_r($instance,true));

}

// new method #2
# $data->biblereader['passage'] = (new \mod_biblereader\reader)->fetch_passage();

// TODO: activity completion events w/ timestamps

// Prevent JS caching
# $CFG->cachejs = false;

// $PAGE->requires->js_call_amd('mod_biblereader/biblereader', 'init');
// $PAGE->requires->js_call_amd('mod_biblereader/repository', 'submitGradingForm');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_biblereader/biblereader', $data->biblereader);
echo $OUTPUT->footer();
