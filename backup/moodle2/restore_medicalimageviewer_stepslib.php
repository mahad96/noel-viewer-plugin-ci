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

class restore_medicalimageviewer_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $paths[] = new restore_path_element('medicalimageviewer', '/activity/medicalimageviewer');

        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('medicalimageviewer_entry', '/activity/medicalimageviewer/entries/entry');
        }

        return $this->prepare_activity_structure($paths);
    }

    protected function process_medicalimageviewer($data) {

        global $DB;

        $data = (Object)$data;

        $oldid = $data->id;
        unset($data->id);

        $data->course = $this->get_courseid();
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newid = $DB->insert_record('medicalimageviewer', $data);
        $this->apply_activity_instance($newid);
    }

    protected function process_medicalimageviewer_entry($data) {

        global $DB;

        $data = (Object)$data;

        $oldid = $data->id;
        unset($data->id);

        $data->medicalimageviewer = $this->get_new_parentid('medicalimageviewer');
        $data->modified = $this->apply_date_offset($data->modified);
        $data->timemarked = $this->apply_date_offset($data->timemarked);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->teacher = $this->get_mappingid('user', $data->teacher);

        $newid = $DB->insert_record('medicalimageviewer_entries', $data);
        $this->set_mapping('medicalimageviewer_entry', $oldid, $newid);
    }

    protected function after_execute() {
        $this->add_related_files('mod_medicalimageviewer', 'intro', null);
        $this->add_related_files('mod_medicalimageviewer_entries', 'text', null);
        $this->add_related_files('mod_medicalimageviewer_entries', 'entrycomment', null);
    }
}
