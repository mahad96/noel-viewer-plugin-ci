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

require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_medicalimageviewer_mod_form extends moodleform_mod {

    public function definition() {
        global $COURSE;

        $mform = & $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('medicalimageviewername', 'medicalimageviewer'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements(get_string('medicalimageviewerquestion', 'medicalimageviewer'));

        $options = array();
        $options[0] = get_string('alwaysopen', 'medicalimageviewer');
        for ($i = 1; $i <= 13; $i++) {
            $options[$i] = get_string('numdays', '', $i);
        }
        for ($i = 2; $i <= 16; $i++) {
            $days = $i * 7;
            $options[$days] = get_string('numweeks', '', $i);
        }
        $options[365] = get_string('numweeks', '', 52);
        $mform->addElement('select', 'days', get_string('daysavailable', 'medicalimageviewer'), $options);
        if ($COURSE->format == 'weeks') {
            $mform->setDefault('days', '7');
        } else {
            $mform->setDefault('days', '0');
        }
		
		//  ====================== Added for image upload: Start ======================
        // Adding the file picker to upload VTK File - amir 24.04.2019
       
		$mform->addElement('filepicker', 'mivimage', 'Upload Medical Image', null,
                  array('maxbytes' => $maxbytes, 'accepted_types' => '*'));

        /*$mform->addElement('filemanager', 'mivimage', 'Upload Medical Image', null,
                   array('subdirs' => 0, 'maxbytes' => $maxbytes, 'areamaxbytes' => 10485760, 'maxfiles' => 1,
                         'accepted_types' => '*', 'return_types'=> FILE_INTERNAL | FILE_EXTERNAL));*/
        //  ====================== Added for image upload: End ======================

        $this->standard_grading_coursemodule_elements();

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

}
