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

require_once($CFG->dirroot.'/mod/medicalimageviewer/lib.php');

function xmldb_medicalimageviewer_upgrade($oldversion=0) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    // No DB changes since 1.9.0.

    // Add medicalimageviewer instances to the gradebook.
    if ($oldversion < 2010120300) {

        medicalimageviewer_update_grades();

        upgrade_mod_savepoint(true, 2010120300, 'medicalimageviewer');
    }

    // Change assessed field for grade.
    if ($oldversion < 2011040600) {

        // Rename field assessed on table medicalimageviewer to grade.
        $table = new xmldb_table('medicalimageviewer');
        $field = new xmldb_field('assessed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'days');

        // Launch rename field grade.
        $dbman->rename_field($table, $field, 'grade');

        // medicalimageviewer savepoint reached.
        upgrade_mod_savepoint(true, 2011040600, 'medicalimageviewer');
    }

    if ($oldversion < 2012032001) {

        // Changing the default of field rating on table medicalimageviewer_entries to drop it.
        $table = new xmldb_table('medicalimageviewer_entries');
        $field = new xmldb_field('rating', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'format');

        // Launch change of default for field rating.
        $dbman->change_field_default($table, $field);

        // Updating the non-marked entries with rating = NULL.
        $entries = $DB->get_records('medicalimageviewer_entries', array('timemarked' => 0));
        if ($entries) {
            foreach ($entries as $entry) {
                $entry->rating = null;
                $DB->update_record('medicalimageviewer_entries', $entry);
            }
        }

        // medicalimageviewer savepoint reached.
        upgrade_mod_savepoint(true, 2012032001, 'medicalimageviewer');
    }

    return true;
}
