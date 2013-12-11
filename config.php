<?php  // Moodle configuration file

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'localhost';
$CFG->dbname    = 'astmlms_moodle';
$CFG->dbuser    = 'astmlms';
$CFG->dbpass    = 'P4radi50!';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbsocket' => 0,
);

$CFG->wwwroot   = 'http://astmlms.com';
$CFG->dataroot  = '/home/astmlms/moodledata';
$CFG->admin     = 'admin';

$CFG->directorypermissions = 0777;

$CFG->passwordsaltmain = '.o{u!FW)!>7g0UkuU`Ie80{Z^';

//$CFG->defaultblocks_override = 'participants:completionstatus';
//$CFG->defaultblocks_site = 'site_main_menu,course_list:course_summary,calendar_month,completionstatus';
$CFG->defaultblocks_override = 'participants,activity_modules,search_forums,course_list:news_items,calendar_upcoming,recent_activity,completionstatus';

//$THEME->rendererfactory = 'theme_overridden_renderer_factory';

require_once(dirname(__FILE__) . '/lib/setup.php');
// There is no php closing tag in this file,
// it is intentional because it prevents trailing whitespace problems!


