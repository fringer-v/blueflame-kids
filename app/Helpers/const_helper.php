<?php

// kid_registered
define('REG_NO', 0);
define('REG_YES', 1);
define('REG_BEING_FETCHED', 2);

// hst_action
define('CREATED', 0);
define('REGISTER', 1);
define('UNREGISTER', 2);
define('CALL', 3);
define('CANCELLED', 4);
define('ESCALATE', 5);
define('CALLED', 6);
define('CALL_ENDED', 7);
define('GO_TO_WC', 8);
define('BACK_FROM_WC', 9);
define('BEING_FETCHED', 10);
define('CHANGED_GROUP', 11);
define('FETCH_CANCELLED', 12);
define('NAME_CHANGED', 13);
define('BIRTHDAY_CHANGED', 14);
define('SUPERVISOR_CHANGED', 15);
define('CELLPHONE_CHANGED', 16);
define('NOTES_CHANGED', 17);
define('SUPERVISOR_SET', 18);

// hst_action && kid_call_status 
define('CALL_NOCALL', 0);
define('CALL_CALLED', 100);		// Operator has executed call
define('CALL_COMPLETED', 200);	// Call completed (if cancelled after called)
define('CALL_PENDING', 300);	// Call request pending
define('CALL_CANCELLED', 400);	// Call withdrawen (no longer required)

define('TEXT_CALLED', 'Gerufen');
define('TEXT_COMPLETED', 'Ruf beendet');
define('TEXT_PENDING', 'Ruf bevorstehend');
define('TEXT_CANCELLED', 'Ruf aufgehoben');
define('TEXT_ESCALATED', 'Ruf eskaliert');

define('CALL_ENDED_DISPLAY_TIME', '00:00:30'); // Call cancel/end state shown for 30 seconds

// Staff roles:
define('ROLE_NONE', 0);
define('ROLE_GROUP_LEADER', 1);
define('ROLE_OFFICIAL', 2);
define('ROLE_TECHNICIAN', 3);
define('ROLE_REGISTRATION', 4);
define('ROLE_MANAGEMENT', 5);
define('ROLE_OFFICE', 6);
define('ROLE_OTHER', 7);

// Extended roles
define('EXT_ROLE_TEAM_LEADER', 100);
define('EXT_ROLE_TEAM_COLEADER', 101);

// Periods:
define('PERIOD_FRIDAY', 0);
define('PERIOD_SAT_MORNING', 1);
define('PERIOD_SAT_AFTERNOON', 2);
define('PERIOD_SAT_EVENING', 3);
define('PERIOD_SUNDAY', 4);
define('PERIOD_COUNT', 5);

define('CURRENT_PERIOD', 0);

define('DEFAULT_GROUP_SIZE', 10);

$GLOBALS['period_names'] = array (
	PERIOD_FRIDAY => 'Freitag Abend',
	PERIOD_SAT_MORNING => 'Samstag Morgen',
	PERIOD_SAT_AFTERNOON => 'Samstag Nachmittag',
	PERIOD_SAT_EVENING => 'Samstag Abend',
	PERIOD_SUNDAY => 'Sontag Morgen');

// The session end times:
$GLOBALS['period_dates'] = array (
	PERIOD_FRIDAY => date_create_from_format('Y-m-d H:i:s', '2018-10-26 23:00:00'),
	PERIOD_SAT_MORNING => date_create_from_format('Y-m-d H:i:s', '2018-10-27 12:00:00'),
	PERIOD_SAT_AFTERNOON => date_create_from_format('Y-m-d H:i:s', '2018-10-27 16:00:00'),
	PERIOD_SAT_EVENING => date_create_from_format('Y-m-d H:i:s', '2018-10-27 23:00:00'),
	PERIOD_SUNDAY => date_create_from_format('Y-m-d H:i:s', '2018-10-28 14:00:00')
);

$GLOBALS['all_roles'] = array(
	ROLE_NONE => '',
	ROLE_REGISTRATION => 'An/Abmeldung',
	ROLE_OFFICE => 'Büro',
	ROLE_GROUP_LEADER => 'Gruppenleiter',
	ROLE_MANAGEMENT => 'Leitungsteam',
	ROLE_OTHER => 'Mitarbeiter',
	ROLE_OFFICIAL => 'Ordner',	
	ROLE_TECHNICIAN => 'Techniker'
);

$GLOBALS['extended_roles'] = $GLOBALS['all_roles'] + array(
	EXT_ROLE_TEAM_LEADER => 'Teamleiter',
	EXT_ROLE_TEAM_COLEADER => 'Teammitglieder'	
);

// Age levels:
define('AGE_LEVEL_0', 0);
define('AGE_LEVEL_1', 1);
define('AGE_LEVEL_2', 2);
define('AGE_LEVEL_COUNT', 3);

$GLOBALS['group_colors'] = [
	AGE_LEVEL_0 => 'Rot',
	AGE_LEVEL_1 => 'Blau',	
	AGE_LEVEL_2 => 'Gelb'	
];

$GLOBALS['age_level_from'] = array (AGE_LEVEL_0 => 4, AGE_LEVEL_1 => 6, AGE_LEVEL_2 => 9);
$GLOBALS['age_level_to'] = array (AGE_LEVEL_0 => 5, AGE_LEVEL_1 => 8, AGE_LEVEL_2 => 11);

define('MATCH_ALL', 0);
define('MATCH_DATE', 1);
define('MATCH_FULL_NAME', 2);
define('MATCH_GROUP', 3);
define('MATCH_KID_ID', 4);
define('MATCH_EMAIL', 5);
define('MATCH_NUMBER', 6);
