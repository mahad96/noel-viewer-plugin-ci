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

require_once($CFG->dirroot.'/mod/medicalimageviewer/backup/moodle2/restore_medicalimageviewer_stepslib.php');

class restore_medicalimageviewer_activity_task extends restore_activity_task {

    protected function define_my_settings() {
    }

    protected function define_my_steps() {
        $this->add_step(new restore_medicalimageviewer_activity_structure_step('medicalimageviewer_structure', 'medicalimageviewer.xml'));
    }

    static public function define_decode_contents() {

        $contents = array();
        $contents[] = new restore_decode_content('medicalimageviewer', array('intro'), 'medicalimageviewer');
        $contents[] = new restore_decode_content('medicalimageviewer_entries', array('text', 'entrycomment'), 'medicalimageviewer_entry');

        return $contents;
    }

    static public function define_decode_rules() {

        $rules = array();
        $rules[] = new restore_decode_rule('medicalimageviewerINDEX', '/mod/medicalimageviewer/index.php?id=$1', 'course');
        $rules[] = new restore_decode_rule('medicalimageviewerVIEWBYID', '/mod/medicalimageviewer/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('medicalimageviewerREPORT', '/mod/medicalimageviewer/report.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('medicalimageviewerEDIT', '/mod/medicalimageviewer/edit.php?id=$1', 'course_module');

        return $rules;

    }

    public static function define_restore_log_rules() {

        $rules = array();
        $rules[] = new restore_log_rule('medicalimageviewer', 'view', 'view.php?id={course_module}', '{medicalimageviewer}');
        $rules[] = new restore_log_rule('medicalimageviewer', 'view responses', 'report.php?id={course_module}', '{medicalimageviewer}');
        $rules[] = new restore_log_rule('medicalimageviewer', 'add entry', 'edit.php?id={course_module}', '{medicalimageviewer}');
        $rules[] = new restore_log_rule('medicalimageviewer', 'update entry', 'edit.php?id={course_module}', '{medicalimageviewer}');
        $rules[] = new restore_log_rule('medicalimageviewer', 'update feedback', 'report.php?id={course_module}', '{medicalimageviewer}');

        return $rules;
    }

    public static function define_restore_log_rules_for_course() {

        $rules = array();
        $rules[] = new restore_log_rule('medicalimageviewer', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}
