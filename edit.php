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

require_once("../../config.php");
require_once('./edit_form.php');

$id = required_param('id', PARAM_INT);    // Course Module ID.

if (!$cm = get_coursemodule_from_id('medicalimageviewer', $id)) {
    print_error("Course Module ID was incorrect");
}

if (!$course = $DB->get_record("course", array("id" => $cm->course))) {
    print_error("Course is misconfigured");
}

$context = context_module::instance($cm->id);

require_login($course, false, $cm);

require_capability('mod/medicalimageviewer:addentries', $context);

if (! $medicalimageviewer = $DB->get_record("medicalimageviewer", array("id" => $cm->instance))) {
    print_error("Course module is incorrect");
}

// Header.
$PAGE->set_url('/mod/medicalimageviewer/edit.php', array('id' => $id));
$PAGE->navbar->add(get_string('edit'));
$PAGE->set_title(format_string($medicalimageviewer->name));
$PAGE->set_heading($course->fullname);

$data = new stdClass();

$entry = $DB->get_record("medicalimageviewer_entries", array("userid" => $USER->id, "medicalimageviewer" => $medicalimageviewer->id));
if ($entry) {
    $data->entryid = $entry->id;
    $data->text = $entry->text;
    $data->textformat = $entry->format;
} else {
    $data->entryid = null;
    $data->text = '';
    $data->textformat = FORMAT_HTML;
}

$data->id = $cm->id;

$editoroptions = array(
    'maxfiles' => EDITOR_UNLIMITED_FILES,
    'context' => $context,
    'subdirs' => false,
    'enable_filemanagement' => true
);

$data = file_prepare_standard_editor($data, 'text', $editoroptions, $context, 'mod_medicalimageviewer', 'entry', $data->entryid);

$form = new mod_medicalimageviewer_entry_form(null, array('entryid' => $data->entryid, 'editoroptions' => $editoroptions));
$form->set_data($data);

if ($form->is_cancelled()) {
    redirect($CFG->wwwroot . '/mod/medicalimageviewer/view.php?id=' . $cm->id);
} else if ($fromform = $form->get_data()) {
    // If data submitted, then process and store.

    // Prevent CSFR.
    confirm_sesskey();
    $timenow = time();

    // This will be overwriten after being we have the entryid.
    $newentry = new stdClass();
    $newentry->text = $fromform->text_editor['text'];
    $newentry->format = $fromform->text_editor['format'];
    $newentry->modified = $timenow;

    if ($entry) {
        $newentry->id = $entry->id;
        if (!$DB->update_record("medicalimageviewer_entries", $newentry)) {
            print_error("Could not update your medicalimageviewer");
        }
    } else {
        $newentry->userid = $USER->id;
        $newentry->medicalimageviewer = $medicalimageviewer->id;
        if (!$newentry->id = $DB->insert_record("medicalimageviewer_entries", $newentry)) {
            print_error("Could not insert a new medicalimageviewer entry");
        }
    }

    // Relink using the proper entryid.
    // We need to do this as draft area didn't have an itemid associated when creating the entry.
    $fromform = file_postupdate_standard_editor($fromform, 'text', $editoroptions,
        $editoroptions['context'], 'mod_medicalimageviewer', 'entry', $newentry->id);
    $newentry->text = $fromform->text;
    $newentry->format = $fromform->textformat;

    $DB->update_record('medicalimageviewer_entries', $newentry);

    if ($entry) {
        // Trigger module entry updated event.
        $event = \mod_medicalimageviewer\event\entry_updated::create(array(
            'objectid' => $medicalimageviewer->id,
            'context' => $context
        ));
    } else {
        // Trigger module entry created event.
        $event = \mod_medicalimageviewer\event\entry_created::create(array(
            'objectid' => $medicalimageviewer->id,
            'context' => $context
        ));

    }
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('medicalimageviewer', $medicalimageviewer);
    $event->trigger();

    redirect(new moodle_url('/mod/medicalimageviewer/view.php?id='.$cm->id));
    die;
}


echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($medicalimageviewer->name));

$intro = format_module_intro('medicalimageviewer', $medicalimageviewer, $cm->id);
echo $OUTPUT->box($intro);

// Otherwise fill and print the form.
$form->display();

echo $OUTPUT->footer();
