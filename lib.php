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


defined('MOODLE_INTERNAL') || die();


/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will create a new instance and return the id number
 * of the new instance.
 * @param object $medicalimageviewer Object containing required medicalimageviewer properties
 * @return int medicalimageviewer ID
 */
function medicalimageviewer_add_instance($medicalimageviewer) {
    global $DB;

    $medicalimageviewer->timemodified = time();
    $medicalimageviewer->id = $DB->insert_record("medicalimageviewer", $medicalimageviewer);

    medicalimageviewer_grade_item_update($medicalimageviewer);

    return $medicalimageviewer->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will update an existing instance with new data.
 * @param object $medicalimageviewer Object containing required medicalimageviewer properties
 * @return boolean True if successful
 */
function medicalimageviewer_update_instance($medicalimageviewer) {
    global $DB;

    $medicalimageviewer->timemodified = time();
    $medicalimageviewer->id = $medicalimageviewer->instance;

    $result = $DB->update_record("medicalimageviewer", $medicalimageviewer);

    medicalimageviewer_grade_item_update($medicalimageviewer);

    medicalimageviewer_update_grades($medicalimageviewer, 0, false);

    return $result;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * nd any data that depends on it.
 * @param int $id medicalimageviewer ID
 * @return boolean True if successful
 */
function medicalimageviewer_delete_instance($id) {
    global $DB;

    $result = true;

    if (! $medicalimageviewer = $DB->get_record("medicalimageviewer", array("id" => $id))) {
        return false;
    }

    if (! $DB->delete_records("medicalimageviewer_entries", array("medicalimageviewer" => $medicalimageviewer->id))) {
        $result = false;
    }

    if (! $DB->delete_records("medicalimageviewer", array("id" => $medicalimageviewer->id))) {
        $result = false;
    }

    return $result;
}


function medicalimageviewer_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_RATE:
            return false;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GROUPMEMBERSONLY:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}


function medicalimageviewer_get_view_actions() {
    return array('view', 'view all', 'view responses');
}


function medicalimageviewer_get_post_actions() {
    return array('add entry', 'update entry', 'update feedback');
}


function medicalimageviewer_user_outline($course, $user, $mod, $medicalimageviewer) {

    global $DB;

    if ($entry = $DB->get_record("medicalimageviewer_entries", array("userid" => $user->id, "medicalimageviewer" => $medicalimageviewer->id))) {

        $numwords = count(preg_split("/\w\b/", $entry->text)) - 1;

        $result = new stdClass();
        $result->info = get_string("numwords", "", $numwords);
        $result->time = $entry->modified;
        return $result;
    }
    return null;
}


function medicalimageviewer_user_complete($course, $user, $mod, $medicalimageviewer) {

    global $DB, $OUTPUT;

    if ($entry = $DB->get_record("medicalimageviewer_entries", array("userid" => $user->id, "medicalimageviewer" => $medicalimageviewer->id))) {

        echo $OUTPUT->box_start();

        if ($entry->modified) {
            echo "<p><font size=\"1\">".get_string("lastedited").": ".userdate($entry->modified)."</font></p>";
        }
        if ($entry->text) {
            echo medicalimageviewer_format_entry_text($entry, $course, $mod);
        }
        if ($entry->teacher) {
            $grades = make_grades_menu($medicalimageviewer->grade);
            medicalimageviewer_print_feedback($course, $entry, $grades);
        }

        echo $OUTPUT->box_end();

    } else {
        print_string("noentry", "medicalimageviewer");
    }
}

/**
 * Function to be run periodically according to the moodle cron.
 * Finds all medicalimageviewer notifications that have yet to be mailed out, and mails them.
 */
function medicalimageviewer_cron () {
    global $CFG, $USER, $DB;

    $cutofftime = time() - $CFG->maxeditingtime;

    if ($entries = medicalimageviewer_get_unmailed_graded($cutofftime)) {
        $timenow = time();

        $usernamefields = get_all_user_name_fields();
        $requireduserfields = 'id, auth, mnethostid, email, mailformat, maildisplay, lang, deleted, suspended, '
                .implode(', ', $usernamefields);

        // To save some db queries.
        $users = array();
        $courses = array();

        foreach ($entries as $entry) {

            echo "Processing medicalimageviewer entry $entry->id\n";

            if (!empty($users[$entry->userid])) {
                $user = $users[$entry->userid];
            } else {
                if (!$user = $DB->get_record("user", array("id" => $entry->userid), $requireduserfields)) {
                    echo "Could not find user $entry->userid\n";
                    continue;
                }
                $users[$entry->userid] = $user;
            }

            $USER->lang = $user->lang;

            if (!empty($courses[$entry->course])) {
                $course = $courses[$entry->course];
            } else {
                if (!$course = $DB->get_record('course', array('id' => $entry->course), 'id, shortname')) {
                    echo "Could not find course $entry->course\n";
                    continue;
                }
                $courses[$entry->course] = $course;
            }

            if (!empty($users[$entry->teacher])) {
                $teacher = $users[$entry->teacher];
            } else {
                if (!$teacher = $DB->get_record("user", array("id" => $entry->teacher), $requireduserfields)) {
                    echo "Could not find teacher $entry->teacher\n";
                    continue;
                }
                $users[$entry->teacher] = $teacher;
            }

            // All cached.
            $coursemedicalimageviewers = get_fast_modinfo($course)->get_instances_of('medicalimageviewer');
            if (empty($coursemedicalimageviewers) || empty($coursemedicalimageviewers[$entry->medicalimageviewer])) {
                echo "Could not find course module for medicalimageviewer id $entry->medicalimageviewer\n";
                continue;
            }
            $mod = $coursemedicalimageviewers[$entry->medicalimageviewer];

            // This is already cached internally.
            $context = context_module::instance($mod->id);
            $canadd = has_capability('mod/medicalimageviewer:addentries', $context, $user);
            $entriesmanager = has_capability('mod/medicalimageviewer:manageentries', $context, $user);

            if (!$canadd and $entriesmanager) {
                continue;  // Not an active participant.
            }

            $medicalimageviewerinfo = new stdClass();
            $medicalimageviewerinfo->teacher = fullname($teacher);
            $medicalimageviewerinfo->medicalimageviewer = format_string($entry->name, true);
            $medicalimageviewerinfo->url = "$CFG->wwwroot/mod/medicalimageviewer/view.php?id=$mod->id";
            $modnamepl = get_string( 'modulenameplural', 'medicalimageviewer' );
            $msubject = get_string( 'mailsubject', 'medicalimageviewer' );

            $postsubject = "$course->shortname: $msubject: ".format_string($entry->name, true);
            $posttext  = "$course->shortname -> $modnamepl -> ".format_string($entry->name, true)."\n";
            $posttext .= "---------------------------------------------------------------------\n";
            $posttext .= get_string("medicalimageviewermail", "medicalimageviewer", $medicalimageviewerinfo)."\n";
            $posttext .= "---------------------------------------------------------------------\n";
            if ($user->mailformat == 1) {  // HTML.
                $posthtml = "<p><font face=\"sans-serif\">".
                "<a href=\"$CFG->wwwroot/course/view.php?id=$course->id\">$course->shortname</a> ->".
                "<a href=\"$CFG->wwwroot/mod/medicalimageviewer/index.php?id=$course->id\">medicalimageviewers</a> ->".
                "<a href=\"$CFG->wwwroot/mod/medicalimageviewer/view.php?id=$mod->id\">".format_string($entry->name, true)."</a></font></p>";
                $posthtml .= "<hr /><font face=\"sans-serif\">";
                $posthtml .= "<p>".get_string("medicalimageviewermailhtml", "medicalimageviewer", $medicalimageviewerinfo)."</p>";
                $posthtml .= "</font><hr />";
            } else {
                $posthtml = "";
            }

            if (! email_to_user($user, $teacher, $postsubject, $posttext, $posthtml)) {
                echo "Error: medicalimageviewer cron: Could not send out mail for id $entry->id to user $user->id ($user->email)\n";
            }
            if (!$DB->set_field("medicalimageviewer_entries", "mailed", "1", array("id" => $entry->id))) {
                echo "Could not update the mailed field for id $entry->id\n";
            }
        }
    }

    return true;
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in medicalimageviewer activities and print it out.
 * Return true if there was output, or false if there was none.
 *
 * @global stdClass $DB
 * @global stdClass $OUTPUT
 * @param stdClass $course
 * @param bool $viewfullnames
 * @param int $timestart
 * @return bool
 */
function medicalimageviewer_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $USER, $DB, $OUTPUT;

    if (!get_config('medicalimageviewer', 'showrecentactivity')) {
        return false;
    }

    $dbparams = array($timestart, $course->id, 'medicalimageviewer');
    $namefields = user_picture::fields('u', null, 'userid');
    $sql = "SELECT je.id, je.modified, cm.id AS cmid, $namefields
         FROM {medicalimageviewer_entries} je
              JOIN {medicalimageviewer} j         ON j.id = je.medicalimageviewer
              JOIN {course_modules} cm ON cm.instance = j.id
              JOIN {modules} md        ON md.id = cm.module
              JOIN {user} u            ON u.id = je.userid
         WHERE je.modified > ? AND
               j.course = ? AND
               md.name = ?
         ORDER BY je.modified ASC
    ";

    $newentries = $DB->get_records_sql($sql, $dbparams);

    $modinfo = get_fast_modinfo($course);
    $show    = array();

    foreach ($newentries as $anentry) {

        if (!array_key_exists($anentry->cmid, $modinfo->get_cms())) {
            continue;
        }
        $cm = $modinfo->get_cm($anentry->cmid);

        if (!$cm->uservisible) {
            continue;
        }
        if ($anentry->userid == $USER->id) {
            $show[] = $anentry;
            continue;
        }
        $context = context_module::instance($anentry->cmid);

        // Only teachers can see other students entries.
        if (!has_capability('mod/medicalimageviewer:manageentries', $context)) {
            continue;
        }

        $groupmode = groups_get_activity_groupmode($cm, $course);

        if ($groupmode == SEPARATEGROUPS &&
                !has_capability('moodle/site:accessallgroups',  $context)) {
            if (isguestuser()) {
                // Shortcut - guest user does not belong into any group.
                continue;
            }

            // This will be slow - show only users that share group with me in this cm.
            if (!$modinfo->get_groups($cm->groupingid)) {
                continue;
            }
            $usersgroups = groups_get_all_groups($course->id, $anentry->userid, $cm->groupingid);
            if (is_array($usersgroups)) {
                $usersgroups = array_keys($usersgroups);
                $intersect = array_intersect($usersgroups, $modinfo->get_groups($cm->groupingid));
                if (empty($intersect)) {
                    continue;
                }
            }
        }
        $show[] = $anentry;
    }

    if (empty($show)) {
        return false;
    }

    echo $OUTPUT->heading(get_string('newmedicalimageviewerentries', 'medicalimageviewer').':', 3);

    foreach ($show as $submission) {
        $cm = $modinfo->get_cm($submission->cmid);
        $context = context_module::instance($submission->cmid);
        if (has_capability('mod/medicalimageviewer:manageentries', $context)) {
            $link = $CFG->wwwroot.'/mod/medicalimageviewer/report.php?id='.$cm->id;
        } else {
            $link = $CFG->wwwroot.'/mod/medicalimageviewer/view.php?id='.$cm->id;
        }
        print_recent_activity_note($submission->modified,
                                   $submission,
                                   $cm->name,
                                   $link,
                                   false,
                                   $viewfullnames);
    }
    return true;
}

/**
 * Returns the users with data in one medicalimageviewer
 * (users with records in medicalimageviewer_entries, students and teachers)
 * @param int $medicalimageviewerid medicalimageviewer ID
 * @return array Array of user ids
 */
function medicalimageviewer_get_participants($medicalimageviewerid) {
    global $DB;

    // Get students.
    $students = $DB->get_records_sql("SELECT DISTINCT u.id
                                      FROM {user} u,
                                      {medicalimageviewer_entries} j
                                      WHERE j.medicalimageviewer=? and
                                      u.id = j.userid", array($medicalimageviewerid));
    // Get teachers.
    $teachers = $DB->get_records_sql("SELECT DISTINCT u.id
                                      FROM {user} u,
                                      {medicalimageviewer_entries} j
                                      WHERE j.medicalimageviewer=? and
                                      u.id = j.teacher", array($medicalimageviewerid));

    // Add teachers to students.
    if ($teachers) {
        foreach ($teachers as $teacher) {
            $students[$teacher->id] = $teacher;
        }
    }
    // Return students array (it contains an array of unique users).
    return $students;
}

/**
 * This function returns true if a scale is being used by one medicalimageviewer
 * @param int $medicalimageviewerid medicalimageviewer ID
 * @param int $scaleid Scale ID
 * @return boolean True if a scale is being used by one medicalimageviewer
 */
function medicalimageviewer_scale_used ($medicalimageviewerid, $scaleid) {

    global $DB;
    $return = false;

    $rec = $DB->get_record("medicalimageviewer", array("id" => $medicalimageviewerid, "grade" => -$scaleid));

    if (!empty($rec) && !empty($scaleid)) {
        $return = true;
    }

    return $return;
}

/**
 * Checks if scale is being used by any instance of medicalimageviewer
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any medicalimageviewer
 */
function medicalimageviewer_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->get_records('medicalimageviewer', array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the medicalimageviewer.
 *
 * @param object $mform form passed by reference
 */
function medicalimageviewer_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'medicalimageviewerheader', get_string('modulenameplural', 'medicalimageviewer'));
    $mform->addElement('advcheckbox', 'reset_medicalimageviewer', get_string('removemessages', 'medicalimageviewer'));
}

/**
 * Course reset form defaults.
 *
 * @param object $course
 * @return array
 */
function medicalimageviewer_reset_course_form_defaults($course) {
    return array('reset_medicalimageviewer' => 1);
}

/**
 * Removes all entries
 *
 * @param object $data
 */
function medicalimageviewer_reset_userdata($data) {

    global $CFG, $DB;

    $status = array();
    if (!empty($data->reset_medicalimageviewer)) {

        $sql = "SELECT j.id
                FROM {medicalimageviewer} j
                WHERE j.course = ?";
        $params = array($data->courseid);

        $DB->delete_records_select('medicalimageviewer_entries', "medicalimageviewer IN ($sql)", $params);

        $status[] = array('component' => get_string('modulenameplural', 'medicalimageviewer'),
                          'item' => get_string('removeentries', 'medicalimageviewer'),
                          'error' => false);
    }

    return $status;
}

function medicalimageviewer_print_overview($courses, &$htmlarray) {

    global $USER, $CFG, $DB;

    if (!get_config('medicalimageviewer', 'overview')) {
        return array();
    }

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return array();
    }

    if (!$medicalimageviewers = get_all_instances_in_courses('medicalimageviewer', $courses)) {
        return array();
    }

    $strmedicalimageviewer = get_string('modulename', 'medicalimageviewer');

    $timenow = time();
    foreach ($medicalimageviewers as $medicalimageviewer) {

        if (empty($courses[$medicalimageviewer->course]->format)) {
            $courses[$medicalimageviewer->course]->format = $DB->get_field('course', 'format', array('id' => $medicalimageviewer->course));
        }

        if ($courses[$medicalimageviewer->course]->format == 'weeks' AND $medicalimageviewer->days) {

            $coursestartdate = $courses[$medicalimageviewer->course]->startdate;

            $medicalimageviewer->timestart  = $coursestartdate + (($medicalimageviewer->section - 1) * 608400);
            if (!empty($medicalimageviewer->days)) {
                $medicalimageviewer->timefinish = $medicalimageviewer->timestart + (3600 * 24 * $medicalimageviewer->days);
            } else {
                $medicalimageviewer->timefinish = 9999999999;
            }
            $medicalimagevieweropen = ($medicalimageviewer->timestart < $timenow && $timenow < $medicalimageviewer->timefinish);

        } else {
            $medicalimagevieweropen = true;
        }

        if ($medicalimagevieweropen) {
            $str = '<div class="medicalimageviewer overview"><div class="name">'.
                   $strmedicalimageviewer.': <a '.($medicalimageviewer->visible ? '' : ' class="dimmed"').
                   ' href="'.$CFG->wwwroot.'/mod/medicalimageviewer/view.php?id='.$medicalimageviewer->coursemodule.'">'.
                   $medicalimageviewer->name.'</a></div></div>';

            if (empty($htmlarray[$medicalimageviewer->course]['medicalimageviewer'])) {
                $htmlarray[$medicalimageviewer->course]['medicalimageviewer'] = $str;
            } else {
                $htmlarray[$medicalimageviewer->course]['medicalimageviewer'] .= $str;
            }
        }
    }
}

function medicalimageviewer_get_user_grades($medicalimageviewer, $userid=0) {
    global $DB;

    $params = array();

    if ($userid) {
        $userstr = 'AND userid = :uid';
        $params['uid'] = $userid;
    } else {
        $userstr = '';
    }

    if (!$medicalimageviewer) {
        return false;

    } else {

        $sql = "SELECT userid, modified as datesubmitted, format as feedbackformat,
                rating as rawgrade, entrycomment as feedback, teacher as usermodifier, timemarked as dategraded
                FROM {medicalimageviewer_entries}
                WHERE medicalimageviewer = :jid ".$userstr;
        $params['jid'] = $medicalimageviewer->id;

        $grades = $DB->get_records_sql($sql, $params);

        if ($grades) {
            foreach ($grades as $key => $grade) {
                $grades[$key]->id = $grade->userid;
            }
        } else {
            return false;
        }

        return $grades;
    }

}


/**
 * Update medicalimageviewer grades in 1.9 gradebook
 *
 * @param object   $medicalimageviewer      if is null, all medicalimageviewers
 * @param int      $userid       if is false al users
 * @param boolean  $nullifnone   return null if grade does not exist
 */
function medicalimageviewer_update_grades($medicalimageviewer=null, $userid=0, $nullifnone=true) {

    global $CFG, $DB;

    if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
        require_once($CFG->libdir.'/gradelib.php');
    }

    if ($medicalimageviewer != null) {
        if ($grades = medicalimageviewer_get_user_grades($medicalimageviewer, $userid)) {
            medicalimageviewer_grade_item_update($medicalimageviewer, $grades);
        } else if ($userid && $nullifnone) {
            $grade = new stdClass();
            $grade->userid   = $userid;
            $grade->rawgrade = null;
            medicalimageviewer_grade_item_update($medicalimageviewer, $grade);
        } else {
            medicalimageviewer_grade_item_update($medicalimageviewer);
        }
    } else {
        $sql = "SELECT j.*, cm.idnumber as cmidnumber
                FROM {course_modules} cm
                JOIN {modules} m ON m.id = cm.module
                JOIN {medicalimageviewer} j ON cm.instance = j.id
                WHERE m.name = 'medicalimageviewer'";
        if ($recordset = $DB->get_records_sql($sql)) {
            foreach ($recordset as $medicalimageviewer) {
                if ($medicalimageviewer->grade != false) {
                    medicalimageviewer_update_grades($medicalimageviewer);
                } else {
                    medicalimageviewer_grade_item_update($medicalimageviewer);
                }
            }
        }
    }
}


/**
 * Create grade item for given medicalimageviewer
 *
 * @param object $medicalimageviewer object with extra cmidnumber
 * @param mixed optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function medicalimageviewer_grade_item_update($medicalimageviewer, $grades=null) {
    global $CFG;
    if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
        require_once($CFG->libdir.'/gradelib.php');
    }

    if (array_key_exists('cmidnumber', $medicalimageviewer)) {
        $params = array('itemname' => $medicalimageviewer->name, 'idnumber' => $medicalimageviewer->cmidnumber);
    } else {
        $params = array('itemname' => $medicalimageviewer->name);
    }

    if ($medicalimageviewer->grade > 0) {
        $params['gradetype']  = GRADE_TYPE_VALUE;
        $params['grademax']   = $medicalimageviewer->grade;
        $params['grademin']   = 0;
        $params['multfactor'] = 1.0;

    } else if ($medicalimageviewer->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$medicalimageviewer->grade;

    } else {
        $params['gradetype']  = GRADE_TYPE_NONE;
        $params['multfactor'] = 1.0;
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/medicalimageviewer', $medicalimageviewer->course, 'mod', 'medicalimageviewer', $medicalimageviewer->id, 0, $grades, $params);
}


/**
 * Delete grade item for given medicalimageviewer
 *
 * @param   object   $medicalimageviewer
 * @return  object   grade_item
 */
function medicalimageviewer_grade_item_delete($medicalimageviewer) {
    global $CFG;

    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/medicalimageviewer', $medicalimageviewer->course, 'mod', 'medicalimageviewer', $medicalimageviewer->id, 0, null, array('deleted' => 1));
}



function medicalimageviewer_get_users_done($medicalimageviewer, $currentgroup) {
    global $DB;

    $params = array();

    $sql = "SELECT u.* FROM {medicalimageviewer_entries} j
            JOIN {user} u ON j.userid = u.id ";

    // Group users.
    if ($currentgroup != 0) {
        $sql .= "JOIN {groups_members} gm ON gm.userid = u.id AND gm.groupid = ?";
        $params[] = $currentgroup;
    }

    $sql .= " WHERE j.medicalimageviewer=? ORDER BY j.modified DESC";
    $params[] = $medicalimageviewer->id;
    $medicalimageviewers = $DB->get_records_sql($sql, $params);

    $cm = medicalimageviewer_get_coursemodule($medicalimageviewer->id);
    if (!$medicalimageviewers || !$cm) {
        return null;
    }

    // Remove unenrolled participants.
    foreach ($medicalimageviewers as $key => $user) {

        $context = context_module::instance($cm->id);

        $canadd = has_capability('mod/medicalimageviewer:addentries', $context, $user);
        $entriesmanager = has_capability('mod/medicalimageviewer:manageentries', $context, $user);

        if (!$entriesmanager and !$canadd) {
            unset($medicalimageviewers[$key]);
        }
    }

    return $medicalimageviewers;
}

/**
 * Counts all the medicalimageviewer entries (optionally in a given group)
 */
function medicalimageviewer_count_entries($medicalimageviewer, $groupid = 0) {
    global $DB;

    $cm = medicalimageviewer_get_coursemodule($medicalimageviewer->id);
    $context = context_module::instance($cm->id);

    if ($groupid) {     // How many in a particular group?

        $sql = "SELECT DISTINCT u.id FROM {medicalimageviewer_entries} j
                JOIN {groups_members} g ON g.userid = j.userid
                JOIN {user} u ON u.id = g.userid
                WHERE j.medicalimageviewer = ? AND g.groupid = ?";
        $medicalimageviewers = $DB->get_records_sql($sql, array($medicalimageviewer->id, $groupid));

    } else { // Count all the entries from the whole course.

        $sql = "SELECT DISTINCT u.id FROM {medicalimageviewer_entries} j
                JOIN {user} u ON u.id = j.userid
                WHERE j.medicalimageviewer = ?";
        $medicalimageviewers = $DB->get_records_sql($sql, array($medicalimageviewer->id));
    }

    if (!$medicalimageviewers) {
        return 0;
    }

    $canadd = get_users_by_capability($context, 'mod/medicalimageviewer:addentries', 'u.id');
    $entriesmanager = get_users_by_capability($context, 'mod/medicalimageviewer:manageentries', 'u.id');

    // Remove unenrolled participants.
    foreach ($medicalimageviewers as $userid => $notused) {

        if (!isset($entriesmanager[$userid]) && !isset($canadd[$userid])) {
            unset($medicalimageviewers[$userid]);
        }
    }

    return count($medicalimageviewers);
}

function medicalimageviewer_get_unmailed_graded($cutofftime) {
    global $DB;

    $sql = "SELECT je.*, j.course, j.name FROM {medicalimageviewer_entries} je
            JOIN {medicalimageviewer} j ON je.medicalimageviewer = j.id
            WHERE je.mailed = '0' AND je.timemarked < ? AND je.timemarked > 0";
    return $DB->get_records_sql($sql, array($cutofftime));
}

function medicalimageviewer_log_info($log) {
    global $DB;

    $sql = "SELECT j.*, u.firstname, u.lastname
            FROM {medicalimageviewer} j
            JOIN {medicalimageviewer_entries} je ON je.medicalimageviewer = j.id
            JOIN {user} u ON u.id = je.userid
            WHERE je.id = ?";
    return $DB->get_record_sql($sql, array($log->info));
}

/**
 * Returns the medicalimageviewer instance course_module id
 *
 * @param integer $medicalimageviewer
 * @return object
 */
function medicalimageviewer_get_coursemodule($medicalimageviewerid) {

    global $DB;

    return $DB->get_record_sql("SELECT cm.id FROM {course_modules} cm
                                JOIN {modules} m ON m.id = cm.module
                                WHERE cm.instance = ? AND m.name = 'medicalimageviewer'", array($medicalimageviewerid));
}



function medicalimageviewer_print_user_entry($course, $user, $entry, $teachers, $grades) {

    global $USER, $OUTPUT, $DB, $CFG;

    require_once($CFG->dirroot.'/lib/gradelib.php');

    echo "\n<table class=\"medicalimagevieweruserentry\" id=\"entry-" . $user->id . "\">";

    echo "\n<tr>";
    echo "\n<td class=\"userpix\" rowspan=\"2\">";
    echo $OUTPUT->user_picture($user, array('courseid' => $course->id, 'alttext' => true));
    echo "</td>";
    echo "<td class=\"userfullname\">".fullname($user);
    if ($entry) {
        echo " <span class=\"lastedit\">".get_string("lastedited").": ".userdate($entry->modified)."</span>";
    }
    echo "</td>";
    echo "</tr>";

    echo "\n<tr><td>";
    if ($entry) {
        echo medicalimageviewer_format_entry_text($entry, $course);
    } else {
        print_string("noentry", "medicalimageviewer");
    }
    echo "</td></tr>";

    if ($entry) {
        echo "\n<tr>";
        echo "<td class=\"userpix\">";
        if (!$entry->teacher) {
            $entry->teacher = $USER->id;
        }
        if (empty($teachers[$entry->teacher])) {
            $teachers[$entry->teacher] = $DB->get_record('user', array('id' => $entry->teacher));
        }
        echo $OUTPUT->user_picture($teachers[$entry->teacher], array('courseid' => $course->id, 'alttext' => true));
        echo "</td>";
        echo "<td>".get_string("feedback").":";

        $attrs = array();
        $hiddengradestr = '';
        $gradebookgradestr = '';
        $feedbackdisabledstr = '';
        $feedbacktext = $entry->entrycomment;

        // If the grade was modified from the gradebook disable edition also skip if medicalimageviewer is not graded.
        $gradinginfo = grade_get_grades($course->id, 'mod', 'medicalimageviewer', $entry->medicalimageviewer, array($user->id));
        if (!empty($gradinginfo->items[0]->grades[$entry->userid]->str_long_grade)) {
            if ($gradingdisabled = $gradinginfo->items[0]->grades[$user->id]->locked
                    || $gradinginfo->items[0]->grades[$user->id]->overridden) {
                $attrs['disabled'] = 'disabled';
                $hiddengradestr = '<input type="hidden" name="r'.$entry->id.'" value="'.$entry->rating.'"/>';
                $gradebooklink = '<a href="'.$CFG->wwwroot.'/grade/report/grader/index.php?id='.$course->id.'">';
                $gradebooklink .= $gradinginfo->items[0]->grades[$user->id]->str_long_grade.'</a>';
                $gradebookgradestr = '<br/>'.get_string("gradeingradebook", "medicalimageviewer").':&nbsp;'.$gradebooklink;

                $feedbackdisabledstr = 'disabled="disabled"';
                $feedbacktext = $gradinginfo->items[0]->grades[$user->id]->str_feedback;
            }
        }

        // Grade selector.
        $attrs['id'] = 'r' . $entry->id;
        echo html_writer::label(fullname($user)." ".get_string('grade'), 'r'.$entry->id, true, array('class' => 'accesshide'));
        echo html_writer::select($grades, 'r'.$entry->id, $entry->rating, get_string("nograde").'...', $attrs);
        echo $hiddengradestr;
        // Rewrote next three lines to show entry needs to be regraded due to resubmission.
        if (!empty($entry->timemarked) && $entry->modified > $entry->timemarked) {
            echo " <span class=\"lastedit\">".get_string("needsregrade", "medicalimageviewer"). "</span>";
        } else if ($entry->timemarked) {
            echo " <span class=\"lastedit\">".userdate($entry->timemarked)."</span>";
        }
        echo $gradebookgradestr;

        // Feedback text.
        echo html_writer::label(fullname($user)." ".get_string('feedback'), 'c'.$entry->id, true, array('class' => 'accesshide'));
        echo "<p><textarea id=\"c$entry->id\" name=\"c$entry->id\" rows=\"12\" cols=\"60\" $feedbackdisabledstr>";
        p($feedbacktext);
        echo "</textarea></p>";

        if ($feedbackdisabledstr != '') {
            echo '<input type="hidden" name="c'.$entry->id.'" value="'.$feedbacktext.'"/>';
        }
        echo "</td></tr>";
    }
    echo "</table>\n";

}

function medicalimageviewer_print_feedback($course, $entry, $grades) {

    global $CFG, $DB, $OUTPUT;

    require_once($CFG->dirroot.'/lib/gradelib.php');

    if (! $teacher = $DB->get_record('user', array('id' => $entry->teacher))) {
        print_error('Weird medicalimageviewer error');
    }

    echo '<table class="feedbackbox">';

    echo '<tr>';
    echo '<td class="left picture">';
    echo $OUTPUT->user_picture($teacher, array('courseid' => $course->id, 'alttext' => true));
    echo '</td>';
    echo '<td class="entryheader">';
    echo '<span class="author">'.fullname($teacher).'</span>';
    echo '&nbsp;&nbsp;<span class="time">'.userdate($entry->timemarked).'</span>';
    echo '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td class="left side">&nbsp;</td>';
    echo '<td class="entrycontent">';

    echo '<div class="grade">';

    // Gradebook preference.
    $gradinginfo = grade_get_grades($course->id, 'mod', 'medicalimageviewer', $entry->medicalimageviewer, array($entry->userid));
    if (!empty($gradinginfo->items[0]->grades[$entry->userid]->str_long_grade)) {
        echo get_string('grade').': ';
        echo $gradinginfo->items[0]->grades[$entry->userid]->str_long_grade;
    } else {
        print_string('nograde');
    }
    echo '</div>';

    // Feedback text.
    echo format_text($entry->entrycomment, FORMAT_PLAIN);
    echo '</td></tr></table>';
}

/**
 * Serves the medicalimageviewer files.
 *
 * @package  mod_medicalimageviewer
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - just send the file
 */
function medicalimageviewer_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $DB, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);

    if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context)) {
        return false;
    }

    // Args[0] should be the entry id.
    $entryid = intval(array_shift($args));
    $entry = $DB->get_record('medicalimageviewer_entries', array('id' => $entryid), 'id, userid', MUST_EXIST);

    $canmanage = has_capability('mod/medicalimageviewer:manageentries', $context);
    if (!$canmanage && !has_capability('mod/medicalimageviewer:addentries', $context)) {
        // Even if it is your own entry.
        return false;
    }

    // Students can only see their own entry.
    if (!$canmanage && $USER->id !== $entry->userid) {
        return false;
    }

    if ($filearea !== 'entry') {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_medicalimageviewer/$filearea/$entryid/$relativepath";
    $file = $fs->get_file_by_hash(sha1($fullpath));

    // Finally send the file.
    send_stored_file($file, null, 0, $forcedownload, $options);
}

function medicalimageviewer_format_entry_text($entry, $course = false, $cm = false) {

    if (!$cm) {
        if ($course) {
            $courseid = $course->id;
        } else {
            $courseid = 0;
        }
        $cm = get_coursemodule_from_instance('medicalimageviewer', $entry->medicalimageviewer, $courseid);
    }

    $context = context_module::instance($cm->id);
    $entrytext = file_rewrite_pluginfile_urls($entry->text, 'pluginfile.php', $context->id, 'mod_medicalimageviewer', 'entry', $entry->id);

    $formatoptions = array(
        'context' => $context,
        'noclean' => false,
        'trusted' => false
    );
    return format_text($entrytext, $entry->format, $formatoptions);
}

