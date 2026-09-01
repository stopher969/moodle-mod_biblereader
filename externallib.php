<?php

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/user/externallib.php");
require_once("$CFG->dirroot/mod/biblereader/locallib.php");
require_once("$CFG->dirroot/lib/completionlib.php");


// TODO Moodle 4.3: move functions outs of externallib.php and into autoloaded PHP classes in "classes/external/my_custom_functions.php"
// don't forget to update db/services.php
// a) classnames from 'mod_biblereader_external' to 'mod_biblereader_external\external\my_custom_functions'
// b) don't forget to update db/services.php remove methodnames

// TODO Moodle 4.3: class mod_biblereader_external extends \mod_biblereader\external\external_api {
// This file will only be provided for fallback support for Moodle 4.1 and earlier. Moodle 4.2 and later versions will ignore this file.
class mod_biblereader_external extends external_api {

   /**
    * Describes the form for submit_user_preferences webservice.
    * @return external_function_parameters
    * @since  Moodle 3.1
    */
    public static function submit_user_preferences($prefs){
      global $CFG, $USER, $DB;

      $params = self::validate_parameters(self::submit_user_preferences_parameters(),
        array(
           'prefs'  => $prefs,
        ));

      $warnings = array();
      $validateddata = true;

      if ($validateddata) {
          // TODO: something here
          // error_log('TODO: save data');
          // $assignment->save_grade($params['userid'], $validateddata);
          self::savePreferences($params);
      } else {
          $warnings[] = self::generate_warning($params['prefs'],
                                               'prefsnotfound',
                                               'Unable to save preferences for user.');
      }

      return $warnings;
    }

    /**
     * Describes the parameters for submit_user_preferences webservice.
     * @return external_function_parameters
     * @since  Moodle 3.1
     */
    public static function submit_user_preferences_parameters() {
        return new external_function_parameters(
            array(
                'prefs' => new external_value(PARAM_TEXT, 'The user preferences'),
            )
        );
    }

    /**
     * Describes the return for submit_user_preferences
     * @return external_function_parameters
     * @since  Moodle 3.1
     */
    public static function submit_user_preferences_returns() {
        return new external_warnings();
    }

    private static function savePreferences($params){
        global $USER, $DB;

        if($DB->record_exists('biblereader_prefs',array('userid'=>$USER->id))){
          // get existing row id...
          $result = $DB->get_record('biblereader_prefs', array('userid'=>$USER->id), 'id');
          // ... and update!
          $row = new stdClass();
          $row->id = $result->id;
          $row->userid = $USER->id;
          $row->prefs = $params['prefs'];
          $DB->update_record('biblereader_prefs', $row);
        } elseif($USER->id > 2) {
          $row = new stdClass();
          $row->userid = $USER->id;
          $row->prefs = $params['prefs'];
          $DB->insert_record('biblereader_prefs', $row);
        }
    }



    /**
    * Describes the form for passage_completed webservice.
    * @return external_function_parameters
    * @since  Moodle 3.1
    */
    public static function passage_completed($prefs){
      global $CFG, $USER, $DB;

      $params = self::validate_parameters(self::passage_completed_parameters(),
        array(
           'prefs'  => $prefs,
        ));

      $warnings = array();
      $validateddata = true;

      if ($validateddata) {
          // TODO: something here
          // error_log('TODO: save data');
          // $assignment->save_grade($params['userid'], $validateddata);
          self::savePassageCompleted($params);
      } else {
          $warnings[] = self::generate_warning($params['prefs'],
                                               'prefsnotset',
                                               'Preferences for passage completion was not set.');
      }

      return $warnings;
    }

    /**
     * Describes the parameters for passage_completed webservice.
     * @return external_function_parameters
     * @since  Moodle 3.1
     */
    public static function passage_completed_parameters() {
        return new external_function_parameters(
            array(
                'prefs' => new external_value(PARAM_TEXT, 'Passage completion'),
            )
        );
    }

    /**
     * Describes the return for passage_completed
     * @return external_function_parameters
     * @since  Moodle 3.1
     */
    public static function passage_completed_returns() {
        return new external_warnings();
    }

    private static function savePassageCompleted($params){
        global $USER, $DB;

        // sanity check #1
        if(!isset($params['prefs']))
            return false;

        // decode $param for values
        $data = json_decode($params['prefs'], true);

        // sanity check #2
        if(!isset($data['pageid']))
          return false;

        if(!isset($data['id']))
          return false;

        // insert new records only for users
        if(!$DB->record_exists('biblereader_completions',array('userid'=>$USER->id,'pageid'=>$data['pageid'])) && $USER->id > 2){
          $row = new stdClass();
          $row->userid = $USER->id;
          $row->pageid = $data['pageid'];
          $row->timecompleted = time();
          $DB->insert_record('biblereader_completions', $row);
        }

        // get course module information
        $cm = $DB->get_record('course_modules', ['id' => $data['id']], '*', MUST_EXIST);
        $biblereader = $DB->get_record('biblereader', ['id' => $cm->instance], '*', MUST_EXIST);

        [$course, $cm] = get_course_and_cm_from_cmid($cm->id, 'biblereader');

        #error_log(print_r($data['id'],true));
        #error_log(print_r($biblereader, true));
        #error_log(var_export(self::biblereader_get_completion_state($biblereader->course, $cm, $USER->id, null)));
        #error_log(print_r($biblereader->course, true));
        #error_log(print_r($cm,true));
        #error_log(print_r($USER->id, true));

        // check: activity completed?
        if(self::biblereader_get_completion_state($biblereader->course, $cm, $USER->id, null)){
          // Update completion state
          $completion = new completion_info($course);
          if ($completion->is_enabled($cm) && $USER->id > 2) // && $biblereader->minimumpercentage &&
              $completion->update_state($cm, COMPLETION_COMPLETE); // , $USER->id
        }
    }

    /**
     * Obtains the automatic completion state for this biblereader based on any conditions
     * in biblereader settings.
     *
     * @param object $course Course
     * @param object $cm Course-module
     * @param int $userid User ID
     * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
     * @return bool True if completed, false if not, $type if conditions not set.
     */
    private static function biblereader_get_completion_state($course, $cm, $userid, $type) {
        global $CFG,$DB;

        // Get biblereader details
        $biblereader = $DB->get_record('biblereader', ['id' => $cm->instance], '*', MUST_EXIST);

        // If completion option is enabled, evaluate it and return true/false
        if ($biblereader->minimumpercentage) {
             $total = $DB->get_field_sql("
    SELECT
        COUNT(plans.id) AS `total`
    FROM
        {biblereader_plans} plans,
        {biblereader} biblereader
    WHERE plans.program = biblereader.program AND
          plans.semester = biblereader.semester AND
          plans.semester = {$biblereader->semester}
   ",      [
             'id' => $biblereader->id,
           ]);

           $read = $DB->get_field_sql("
   SELECT
      COUNT(plans.id) AS `read`
   FROM
      {biblereader_plans} plans
      LEFT OUTER JOIN ({biblereader_completions} completions)
        ON (plans.id = completions.pageid),
      {biblereader} biblereader
   WHERE plans.program = biblereader.program AND
        plans.semester = biblereader.semester AND
        completions.userid = {$userid} AND
        plans.semester = {$biblereader->semester}
   ",      [
             'id' => $biblereader->id,
           ]);

           // DEBUG:
           # $biblereader->minimumpercentage = 4;

           // return true/false
           #error_log("breakpoint");
           #error_log(round(($read / $total) * 100));
           return (round(($read / $total) * 100) >= $biblereader->minimumpercentage ? true : false );

        } else {
            // Completion option is not enabled so just return $type
            return $type;
        }
    }

}
