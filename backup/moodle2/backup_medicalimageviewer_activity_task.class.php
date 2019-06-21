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

require_once($CFG->dirroot.'/mod/medicalimageviewer/backup/moodle2/backup_medicalimageviewer_stepslib.php');

class backup_medicalimageviewer_activity_task extends backup_activity_task {

    protected function define_my_settings() {
    }

    protected function define_my_steps() {
        $this->add_step(new backup_medicalimageviewer_activity_structure_step('medicalimageviewer_structure', 'medicalimageviewer.xml'));
    }

    static public function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot.'/mod/medicalimageviewer', '#');

        $pattern = "#(".$base."\/index.php\?id\=)([0-9]+)#";
        $content = preg_replace($pattern, '$@medicalimageviewerINDEX*$2@$', $content);

        $pattern = "#(".$base."\/view.php\?id\=)([0-9]+)#";
        $content = preg_replace($pattern, '$@medicalimageviewerVIEWBYID*$2@$', $content);

        $pattern = "#(".$base."\/report.php\?id\=)([0-9]+)#";
        $content = preg_replace($pattern, '$@medicalimageviewerREPORT*$2@$', $content);

        $pattern = "#(".$base."\/edit.php\?id\=)([0-9]+)#";
        $content = preg_replace($pattern, '$@medicalimageviewerEDIT*$2@$', $content);

        return $content;
    }
}
