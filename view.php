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
 * This page is the entry page into the StudentQuiz UI.
 *
 * Displays information about the questions to students and teachers,
 * and lets students to generate new quizzes or add questions.
 *
 * @package    mod_studentquiz
 * @copyright  2017 HSR (http://www.hsr.ch)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
Global $COURSE, $PAGE;
$your_variable = $COURSE->id;

$id = required_param('id', PARAM_INT);    // Course Module ID.

if (! $cm = get_coursemodule_from_id('medicalimageviewer', $id)) {
    print_error("Course Module ID was incorrect");
}




$PAGE->set_context(get_system_context());
$PAGE->set_pagelayout('admin');
$PAGE->set_title("Image Viewer");
$PAGE->set_heading($COURSE->fullname);
$PAGE->set_url($CFG->wwwroot.'/mod/medicalimageviewer/view.php');

echo $OUTPUT->header();
$course    = $cm->course;
$cmid = $_GET['id'];
$sql  = "SELECT itemid, image FROM {medicalimageviewer_images} WHERE course = $course AND coursemodule = $cmid ORDER BY time_created DESC LIMIT 1";
$currentimageSQL = $DB->get_record_sql($sql);

/*echo "<pre>";
print_r($currentimageSQL);die;*/

$currentitemid = $currentimageSQL->itemid;
$currentitemid = isset($currentitemid) ? $currentitemid : 'NA';

$currentimage = $currentimageSQL->image;
$currentimage = isset($currentimage) ? $currentimage : 'NA';

$actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    list($baselink, $otherlink) = explode('/mod', $actual_link);
$currenturl = 'NA';
if($currentimage != 'NA') {
    
    $currenturl = $baselink . "/draftfile.php/5/user/draft/$currentitemid/$currentimage";
    // if image is of nii type or DICOM type chose ami medical image uploader
    $imagetype = explode(".", $currenturl);
    // $imagedimension = "regular";
    $imagedimension = "rotation";
    if(in_array('nii', $imagetype) || in_array('dcm', $imagetype)) {
        if($imagedimension == "regular") {
            include_once 'viewimagenii.php';
        }
        if($imagedimension == "rotation") {
            // include_once 'viewimagenii.php';
            include_once 'viewimagenii2D.php';
        }
    } 
    
    // otherwise open VTX type image
    else {
        include_once 'viewimages.php';
    }

    // echo "<img src=$currenturl width='40%' />";

    // for testing
    /*echo "<img src='http://localhost/moodle_aman/draftfile.php/5/user/draft/755471004/medicalimageviewer.jpg' width='40%' />";die;*/
} 
else {
    include_once 'validation.php';
}
// include_once 'viewimages.php';
/*==========================VIEW PAGE: END ======================*/
echo $OUTPUT->footer();
?>