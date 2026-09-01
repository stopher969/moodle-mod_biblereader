<?php

$functions = [
    // The name of your web service function, as discussed above.
    'mod_biblereader_submit_user_preferences' => [
        // The name of the namespaced class that the function is located in.
        'classname'   => 'mod_biblereader_external',                            // TODO Moodle 4.2+ 'mod_biblereader_external\external\my_custom_func'
    
        // default function called is execute(), so change if different
        'methodname'  => 'submit_user_preferences', 				            // TODO Moodle 4.2+ change to execute() or remove completely, will rely on classname autoloaded function above
    
        // A brief, human-readable, description of the web service function.
        'description' => 'Submit user preferences (form)',

        // Options include read, and write.
        'type'        => 'write',

        // Whether the service is available for use in AJAX calls from the web.
        'ajax'        => true,

        // An optional list of services where the function will be included.
        'services'    => [
            // A standard Moodle install includes one default service:
            // - MOODLE_OFFICIAL_MOBILE_SERVICE.
            // Specifying this service means that your function will be available for
            // use in the Moodle Mobile App.
            MOODLE_OFFICIAL_MOBILE_SERVICE,
        ],
    ],
    // The name of your web service function, as discussed above.
    'mod_biblereader_passage_completed' => [
        // The name of the namespaced class that the function is located in.
        'classname'   => 'mod_biblereader_external',                            // TODO Moodle 4.2+ 'mod_biblereader_external\external\my_custom_func'

        // default function called is execute(), so change if different
        'methodname'  => 'submit_user_preferences', 				            // TODO Moodle 4.2+ change to execute() or remove completely, will rely on classname autoloaded function above
    
        // A brief, human-readable, description of the web service function.
        'description' => 'Store passage as read (+ timestamp) per account.',

        // Options include read, and write.
        'type'        => 'write',

        // Whether the service is available for use in AJAX calls from the web.
        'ajax'        => true,

        // An optional list of services where the function will be included.
        'services'    => [
            // A standard Moodle install includes one default service:
            // - MOODLE_OFFICIAL_MOBILE_SERVICE.
            // Specifying this service means that your function will be available for
            // use in the Moodle Mobile App.
            MOODLE_OFFICIAL_MOBILE_SERVICE,
        ],
    ],
];
