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
 * Lists every Bible Reader in a course. Moodle links here from the course page
 * and the Activities block, always with a COURSE id.
 *
 * @package   mod_biblereader
 * @copyright 2024, Josh Jenney <josh@n2nministries.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

// N2NCU 2026-08-02: this page read `id` as a course-MODULE id and passed it to
// get_course_and_cm_from_cmid(). Moodle's convention for mod/*/index.php is a
// COURSE id, and a course id is what core actually links with - so following the
// site's own link threw a dml_missing_record_exception before rendering
// anything. On this site the course page links to index.php?id=113, course 113,
// and there is no course module 113.
//
// Found by tools/smoke-crawl.sh once it started following links: requesting the
// bare page only ever produced "missing parameter", which looked correct, so the
// broken path was never executed. It fails identically on 4.1, so this is a
// long-standing defect rather than anything the 4.5 upgrade caused.
$id = required_param('id', PARAM_INT); // Course id.

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

// require_course_login() rather than require_login($course, true, $cm): an index
// page has no single course module to enforce availability against.
require_course_login($course, true);

$strnameplural = get_string('modulenameplural', 'biblereader');

// N2NCU 2026-08-01, kept: context and URL are set BEFORE anything that builds
// navigation. Navigation initialisation reads $PAGE->url and $PAGE->context, so
// setting them afterwards produces a debugging notice and a guessed URL.
$PAGE->set_context(context_course::instance($course->id));
$PAGE->set_url(new moodle_url('/mod/biblereader/index.php', ['id' => $course->id]));
$PAGE->set_pagelayout('incourse');
$PAGE->add_body_class('limitedwidth');
$PAGE->navbar->add($strnameplural);
$PAGE->set_title($strnameplural);
$PAGE->set_heading($course->fullname);

// No course_module_instance_list_viewed event is triggered here. Core index
// pages do, but mod_biblereader ships only classes/event/course_module_viewed.php
// and referencing a class that does not exist would fatal. Adding that event
// class is worth doing separately; it is not part of fixing this crash.

$instances = get_all_instances_in_course('biblereader', $course);

echo $OUTPUT->header();
echo $OUTPUT->heading($strnameplural);

if (empty($instances)) {
    // notice() renders and exits.
    notice(get_string('thereareno', 'moodle', $strnameplural),
           new moodle_url('/course/view.php', ['id' => $course->id]));
}

$table = new html_table();
$table->head = [get_string('name'), get_string('moduleintro')];
$table->align = ['left', 'left'];

foreach ($instances as $instance) {
    $link = html_writer::link(
        new moodle_url('/mod/biblereader/view.php', ['id' => $instance->coursemodule]),
        format_string($instance->name),
        $instance->visible ? [] : ['class' => 'dimmed']
    );
    $table->data[] = [
        $link,
        format_module_intro('biblereader', $instance, $instance->coursemodule),
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();

// N2NCU 2026-08-02: the previous version ended in redirect() to view.php with
// the cmid it had been given. Everything it built above that - a semester
// category crumb and a self-referential index.php?id=<cmid> crumb - was
// unreachable, because redirect() calls exit(). Both are gone: they were never
// rendered, and the cmid crumb would point at the wrong thing now that this page
// takes a course id. reading.php carried the same pair and they were already
// commented out there.
