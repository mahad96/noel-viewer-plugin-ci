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
 * Data provider.
 *
 * @package    mod_medicalimageviewer
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_medicalimageviewer\privacy;
defined('MOODLE_INTERNAL') || die();

use context;
use context_helper;
use stdClass;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

require_once($CFG->dirroot . '/mod/medicalimageviewer/lib.php');

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Returns metadata.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_database_table(
            'medicalimageviewer_entries',
             [
                'userid' => 'privacy:metadata:medicalimageviewer_entries:userid',
                'modified' => 'privacy:metadata:medicalimageviewer_entries:modified',
                'text' => 'privacy:metadata:medicalimageviewer_entries:text',
                'rating' => 'privacy:metadata:medicalimageviewer_entries:rating',
                'entrycomment' => 'privacy:metadata:medicalimageviewer_entries:entrycomment',
             ],
            'privacy:metadata:medicalimageviewer_entries'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {

        $sql = "
            SELECT DISTINCT ctx.id
              FROM {%s} fc
              JOIN {modules} m
                ON m.name = :medicalimageviewer
              JOIN {course_modules} cm
                ON cm.instance = fc.medicalimageviewer
               AND cm.module = m.id
              JOIN {context} ctx
                ON ctx.instanceid = cm.id
               AND ctx.contextlevel = :modlevel
             WHERE fc.userid = :userid";

        $params = ['medicalimageviewer' => 'medicalimageviewer', 'modlevel' => CONTEXT_MODULE, 'userid' => $userid];
        $contextlist = new contextlist();
        $contextlist->add_from_sql(sprintf($sql, 'medicalimageviewer_entries'), $params);
        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     *
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        // Find users with medicalimageviewer entries.
        $sql = "
            SELECT fc.userid
              FROM {%s} fc
              JOIN {modules} m
                ON m.name = :medicalimageviewer
              JOIN {course_modules} cm
                ON cm.instance = fc.medicalimageviewer
               AND cm.module = m.id
              JOIN {context} ctx
                ON ctx.instanceid = cm.id
               AND ctx.contextlevel = :modlevel
             WHERE ctx.id = :contextid";
        $params = ['medicalimageviewer' => 'medicalimageviewer', 'modlevel' => CONTEXT_MODULE, 'contextid' => $context->id];

        $userlist->add_from_sql('userid', sprintf($sql, 'medicalimageviewer_entries'), $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (!count($contextlist)) {
            return;
        }

        $user = $contextlist->get_user();
        $userid = $user->id;
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT
                    jen.id,
                    jen.userid,
                    jen.modified,
                    jen.text,
                    jen.rating,
                    jen.entrycomment
                    FROM {context} c
                    INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                    INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                    INNER JOIN {medicalimageviewer} j ON j.id = cm.instance
                    LEFT JOIN {medicalimageviewer_entries} as jen ON jen.medicalimageviewer = j.id
                    WHERE jen.userid = :userid AND c.id {$contextsql}";
        $params = [
            'contextlevel'      => CONTEXT_MODULE,
            'modname'           => 'medicalimageviewer',
            'userid'          => $userid,
        ];
        $params += $contextparams;

        // Fetch the individual medicalimageviewers entries.
        $medicalimageviewers = $DB->get_recordset_sql($sql, $params);
        foreach ($medicalimageviewers as $medicalimageviewer) {
            list($course, $cm) = get_course_and_cm_from_cmid($medicalimageviewer->cmid, 'medicalimageviewer');
            $medicalimageviewerobj = new \entry($medicalimageviewer, $cm, $course);
            $context = $medicalimageviewerobj->get_context();

            $medicalimageviewerentry = \core_privacy\local\request\helper::get_context_data($context, $contextlist->get_user());
            \core_privacy\local\request\helper::export_context_files($context, $contextlist->get_user());

            if (!empty($medicalimageviewerentry->modified)) {
                $medicalimageviewerentry->modified = transform::datetime($medicalimageviewer->modified);
            }
            if (!empty($medicalimageviewerentry->text)) {
                $medicalimageviewerentry->text = $medicalimageviewer->text;
            }
            if (!empty($medicalimageviewerentry->rating)) {
                $medicalimageviewerentry->rating = $medicalimageviewer->rating;
            }
            if (!empty($medicalimageviewerentry->entrycomment)) {
                $medicalimageviewerentry->entrycomment = $medicalimageviewer->entrycomment;
            }

            writer::with_context($context)->export_data([], $medicalimageviewerentry);
        }
        $medicalimageviewers->close();
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        // This should not happen, but just in case.
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        // Prepare SQL to gather all completed IDs.

        $completedsql = "
            SELECT fc.id
              FROM {%s} fc
              JOIN {modules} m
                ON m.name = :medicalimageviewer
              JOIN {course_modules} cm
                ON cm.instance = fc.medicalimageviewer
               AND cm.module = m.id
             WHERE cm.id = :cmid";
        $completedparams = ['cmid' => $context->instanceid, 'medicalimageviewer' => 'medicalimageviewer'];

        // Delete medicalimageviewer entries.
        $completedtmpids = $DB->get_fieldset_sql(sprintf($completedsql, 'medicalimageviewer_entries'), $completedparams);
        if (!empty($completedtmpids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($completedtmpids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('medicalimageviewer_entries', "id $insql", $inparams);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;

        // Ensure that we only act on module contexts.
        $contextids = array_map(function($context) {
            return $context->instanceid;
        }, array_filter($contextlist->get_contexts(), function($context) {
            return $context->contextlevel == CONTEXT_MODULE;
        }));

        // Prepare SQL to gather all completed IDs.
        list($insql, $inparams) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);
        $completedsql = "
            SELECT fc.id
              FROM {%s} fc
              JOIN {modules} m
                ON m.name = :medicalimageviewer
              JOIN {course_modules} cm
                ON cm.instance = fc.medicalimageviewer
               AND cm.module = m.id
             WHERE fc.userid = :userid
               AND cm.id $insql";
        $completedparams = array_merge($inparams, ['userid' => $userid, 'medicalimageviewer' => 'medicalimageviewer']);

        // Delete medicalimageviewer entries.
        $completedtmpids = $DB->get_fieldset_sql(sprintf($completedsql, 'medicalimageviewer_entries'), $completedparams);
        if (!empty($completedtmpids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($completedtmpids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('medicalimageviewer_entries', "id $insql", $inparams);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist    $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        $userids = $userlist->get_userids();

        // Prepare SQL to gather all completed IDs.
        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $completedsql = "
            SELECT fc.id
              FROM {%s} fc
              JOIN {modules} m
                ON m.name = :medicalimageviewer
              JOIN {course_modules} cm
                ON cm.instance = fc.medicalimageviewer
               AND cm.module = m.id
             WHERE cm.id = :instanceid
               AND fc.userid $insql";
        $completedparams = array_merge($inparams, ['instanceid' => $context->instanceid, 'medicalimageviewer' => 'medicalimageviewer']);

        // Delete all medicalimageviewer entries.
        $completedtmpids = $DB->get_fieldset_sql(sprintf($completedsql, 'medicalimageviewer_entries'), $completedparams);
        if (!empty($completedtmpids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($completedtmpids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('medicalimageviewer_entries', "id $insql", $inparams);
        }
    }
}
