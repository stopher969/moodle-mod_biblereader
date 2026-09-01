<?php

namespace mod_biblereader\external;

// use external_api;
// TODO Moodle 4.3+
// \core_external\external_function_parameters
// \core_external\external_multiple_structure
// \core_external\external_single_structure
// \core_external\external_value

use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

// TODO Moodle 4.2+
// create another file my_custom_func.php
// class my_custom_func extends \core_external\external_api

// TODO Moodle 4.2+
// class custom_function_getsomething extends \core_external\external_api
class external_api extends \external_api {

    protected static function generate_warning(int $assignmentid, string $warningcode, string $detail): array {
        $warningmessages = [
            'useridnotfound' => 'Unable to save preferences for user.',
        ];

        $message = $warningmessages[$warningcode];
        if (empty($message)) {
            $message = 'Unknown warning type.';
        }

        return [
            'item' => s($detail),
            'itemid' => $assignmentid,
            'warningcode' => $warningcode,
            'message' => $message,
        ];
    }


    // note: webservice function default is execute()
    // function execute_parameters(): external_function_parameters
    public static function execute_parameters() { 
        return new external_function_parameters([
		'prefs' => new external_value(
        		PARAM_TEXT,
        		'Passage completion'
        ));
    }


    // function execute(string $userid): string
    public static function execute(string $userid) {
        // place logic here
    }


    public static function execute_returns() {
	    // place logic here
	    return new external_single_structure([
            'prefs' => new external_value(PARAM_TEXT, 'Status string')
        ]);
    }

}
