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
 * Activity index for the mod_biblereader plugin.
 *
 * @package   mod_biblereader
 * @copyright 2024, Josh Jenney <josh@n2nministries.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$cmid = required_param('id', PARAM_INT);
[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'biblereader');
$instance = $DB->get_record('biblereader', ['id'=> $cm->instance], '*', MUST_EXIST);

// N2NCU 2026-08-01: no access check existed here. Exposure was bounded, because
// the page redirects to view.php before rendering and view.php now requires
// login - but an unauthenticated request could still tell a valid cmid from an
// invalid one by whether it redirected or threw.
require_login($course, true, $cm);

// N2NCU 2026-08-01: context and URL set BEFORE the navbar calls below.
// make_active() builds navigation, which reads $PAGE->url and $PAGE->context, so
// setting them afterwards produced a debugging notice and used a guessed URL.
$PAGE->set_context(context_module::instance($cmid));
$PAGE->set_url(new moodle_url('/mod/biblereader/index.php', array('id' => $cmid)));

// navbar
$data = $cm->get_course();
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

$PAGE->set_pagelayout('incourse');
$PAGE->add_body_class('limitedwidth');

// N2NCU 2026-08-01: the set_context() here was context_system::instance(), which
// is the wrong context for a course activity - it is the module context. Both it
// and set_url() have moved above the navbar calls; see the note there.
$url = new moodle_url('/mod/biblereader/view.php', array('id' => $cmid));

redirect($url);

// N2NCU 2026-08-01: three lines removed from here. They followed redirect(),
// which calls exit(), so they were unreachable - and one of them rendered
// $content, a variable this file never defines.
