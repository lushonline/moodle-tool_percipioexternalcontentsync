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
 * Helper functions for creating External Content activities from a Percipio Asset.
 *
 * @package    tool_percipioexternalcontentsync
 * @copyright  2019-2022 LushOnline
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_percipioexternalcontentsync;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/completion/criteria/completion_criteria_activity.php');
require_once($CFG->libdir . '/phpunit/classes/util.php');

/**
 * Class containing a set of helpers.
 *
 * @package   tool_percipioexternalcontentsync
 * @copyright 2019-2022 LushOnline
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Get the Mustache Template used to format the Percipio data.
     *
     * @return string The Mustache Template uploaded in settings, the default from templates folder or false if not found
     */
    private static function get_template() {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $syscontext = \context_system::instance();

        $fs = get_file_storage();
        // Templates are those configured as a site administration setting to be available for new uses.
        $currenttemplates = $fs->get_area_files($syscontext->id, 
                        'tool_percipioexternalcontentsync', 'templatefiles',
                        0, 'filename', false);

        if (count($currenttemplates) > 0) {
            $template = array_shift($currenttemplates);
            return $template->get_content();
        }

        $template = \core\output\mustache_template_finder::get_template_filepath('tool_percipioexternalcontentsync/content');
        return file_get_contents($template);
    }

    /**
     * Process the Percipio data and return the formatted HTML. This uses either the default template or the one
     * uploaded in the settings.
     *
     * @param  mixed $asset
     * @param  mixed $showthumbnail
     * @param  mixed $showlaunch
     * @return string The formatted HTML
     */
    private static function get_percipio_description($asset, $showthumbnail = false, $showlaunch = false) {
        global $OUTPUT;

        // Create the pre-processed values.
        $percipioformatted = new \stdClass;
        $percipioformatted->duration = '';
        $percipioformatted->language = '';
        $percipioformatted->region = '';

        // Convert ISO8601 duration to human readable format in HH:MM:SS.
        if (isset($asset->duration) === true && $asset->duration !== '') {
            try {
                $durationinterval = new \DateInterval($asset->duration);
                $percipioformatted->duration = $durationinterval->format('%H:%I:%S');
            } catch (\Exception $e) {
                $percipioformatted->duration = '';
            }
        }

        // Convert RFC5646 locale to human readable values of language and region.
        if (isset($asset->localeCodes[0]) === true && $asset->localeCodes[0] !== '') {
            try {
                $percipioformatted->language = locale_get_display_language($asset->localeCodes[0], $asset->localeCodes[0]);
                $percipioformatted->region = locale_get_display_region($asset->localeCodes[0], $asset->localeCodes[0]);
            } catch (\Exception $e) {
                $percipioformatted->language = '';
                $percipioformatted->region = '';
            }
        }

        $data = new \stdClass;
        $data->percipio = $asset;
        $data->percipioformatted = $percipioformatted;
        $data->showthumbnail = $showthumbnail;
        $data->showlaunch = $showlaunch;

        $data->hasby = isset($asset->by) && count($asset->by) > 0;
        $data->hasobjectives = isset($asset->learningObjectives) && count($asset->learningObjectives) > 0;

        if ($contents = self::get_template()) {
            $mustache = new \core\output\mustache_engine();
            $result = $mustache->render($contents, $data);
        }

        return $result;
    }


    /**
     * Convert the Percipio Asset details to a Category Object.
     *
     * @param object $asset The asset we recieved from Percipio
     * @return object The asset converted to the Catagory information for External Content
     */
    private static function get_percio_asset_category($asset) {
        $result = new \stdClass();

        $idlookup = array(
            'audiobook' => isset($asset->associations->channels[0]->id) ? $asset->associations->channels[0]->id : '',
            'book' => isset($asset->associations->channels[0]->id) ? $asset->associations->channels[0]->id : '',
            'channel' => $asset->id,
            'course' => isset($asset->associations->channels[0]->id) ? $asset->associations->channels[0]->id : '',
            'linked_content' => isset($asset->associations->channels[0]->id) ? $asset->associations->channels[0]->id : '',
            'video' => isset($asset->associations->channels[0]->id) ? $asset->associations->channels[0]->id : '',
            'journey' => $asset->id,
        );

        $namelookup = array(
            'audiobook' => isset($asset->associations->channels[0]->title) ? $asset->associations->channels[0]->title : '',
            'book' => isset($asset->associations->channels[0]->title) ? $asset->associations->channels[0]->title : '',
            'channel' => $asset->localizedMetadata[0]->title,
            'course' => isset($asset->associations->channels[0]->title) ? $asset->associations->channels[0]->title : '',
            'linked_content' => isset($asset->associations->channels[0]->title) ? $asset->associations->channels[0]->title : '',
            'video' => isset($asset->associations->channels[0]->title) ? $asset->associations->channels[0]->title : '',
            'journey' => $asset->localizedMetadata[0]->title,
        );

        $idnumber = $idlookup[strtolower($asset->contentType->percipioType)] ?? null;
        $name = $namelookup[strtolower($asset->contentType->percipioType)] ?? null;

        $result->categoryidnumber = !empty($idnumber) ? $idnumber . '_' . $asset->localeCodes[0] : '';
        $result->categoryname = !empty($name) ? $name . ' [' . $asset->localeCodes[0] . ']' : '';
        return $result;
    }

    /**
     * Convert the Percipio Asset details to a delimited string of tags, maximum 10.
     *
     * @param object $asset The asset we recieved from Percipio
     * @return string The asset converted to a pipe delimited list of tags for External Content
     */
    private static function get_percio_asset_tags($asset) {
        $result = '';

        $tagsarry = array();
        $tagsarry[] = $asset->contentType->displayLabel;

        if (
            strtolower($asset->contentType->percipioType) == 'channel'
            || strtolower($asset->contentType->percipioType) == 'journey'
        ) {
            $tagsarry[] = $asset->localizedMetadata[0]->title;
        } else {
            if (isset($asset->associations->channels) && count($asset->associations->channels) > 0) {
                $tagsarry = array_merge($tagsarry, array_column($asset->associations->channels, 'title'));
            }
            if (isset($asset->associations->journeys) && count($asset->associations->journeys) > 0) {
                $tagsarry = array_merge($tagsarry, array_column($asset->associations->journeys, 'title'));
            }
        }

        if (count($asset->keywords) > 0) {
            $tagsarry = array_merge($tagsarry, $asset->keywords);
        }

        if (isset($asset->associations->areas) && count($asset->associations->areas) > 0) {
            $tagsarry = array_merge($tagsarry, $asset->associations->areas);
        }

        if (isset($asset->associations->subjects) && count($asset->associations->subjects) > 0) {
            $tagsarry = array_merge($tagsarry, $asset->associations->subjects);
        }

        $result = implode('|', array_map('trim', $tagsarry));
        return $result;
    }

    /**
     * Convert the Percipio Asset to a External Content Activity Import record.
     *
     * @param object $asset The asset we recieved from Percipio
     * @param string $parentcategory The parentcategory name or id
     * @return object The asset converted to an import record for External Content
     */
    public static function percio_asset_to_externalcontentimport($asset, $parentcategory = null) {
        // Retrieve the External Content defaults.
        $extcontdefaults = get_config('externalcontent');

        $record = new \stdClass();

        // Set defaults.
        if (property_exists($extcontdefaults, 'printheading')) {
            $record->external_printheading = $extcontdefaults->printheading;
        }
        if (property_exists($extcontdefaults, 'printintro')) {
            $record->external_printintro = $extcontdefaults->printintro;
        }
        if (property_exists($extcontdefaults, 'printlastmodified')) {
            $record->external_printlastmodified = $extcontdefaults->printlastmodified;
        }

        // Lookup the parent category information.
        $record->category = self::resolve_category_by_id_or_idnumber($parentcategory);

        $categoryinfo = self::get_percio_asset_category($asset);
        $description = self::get_percipio_description($asset);
        $externalcontent = self::get_percipio_description($asset, true, true);
        $tags = self::get_percio_asset_tags($asset);

        $record->course_idnumber = $asset->xapiActivityId;
        $record->course_shortname = substr($asset->localizedMetadata[0]->title, 0, 215) . ' (' . $asset->id . ')';
        $record->course_fullname = substr($asset->localizedMetadata[0]->title, 0, 255);
        $record->course_summary = $description;
        $record->course_tags = $tags;
        $record->course_visible = strcasecmp($asset->lifecycle->status, 'ACTIVE') == 0 ? 1 : 0;
        $record->course_thumbnail = $asset->imageUrl;
        $record->course_categoryidnumber = $categoryinfo->categoryidnumber;
        $record->course_categoryname = $categoryinfo->categoryname;
        $record->external_name = substr($asset->localizedMetadata[0]->title, 0, 255);
        $record->external_intro = $description;
        $record->external_content = $externalcontent;
        $record->external_markcompleteexternally = strcasecmp($asset->contentType->percipioType, 'CHANNEL') == 0 ? 0 : 1;

        return $record;
    }


    /**
     * Convert the Percipio Asset to a External Content Activity and import.
     *
     * @param object $asset The asset we receeved from Percipio
     * @param string $parentcategory The parentcategory name or id
     * @param bool $coursethumbnail If true, then the thumbnail for the course will downloaded and added.
     * @return object Processing information for the asset
     */
    public static function import_percio_asset($asset, $parentcategory = null, $coursethumbnail = true) {
        global $DB;

        $record = self::percio_asset_to_externalcontentimport($asset, $parentcategory);

        $result = new \stdClass();
        $result->success = true;
        $result->warn = false;
        $result->courseshortname = $record->course_shortname;
        $result->error = null;
        $result->coursestatus = null;
        $result->externalcontentstatus = null;
        $result->thumbnailstatus = null;
        $result->courseid = null;
        $result->activityid = null;

        $coursenotupdatedmsg = get_string('statuscoursenotupdated', 'tool_percipioexternalcontentsync');
        $extcreatedmsg = get_string('statusextcreated', 'tool_percipioexternalcontentsync');
        $extupdatedmsg = get_string('statusextupdated', 'tool_percipioexternalcontentsync');
        $extnotupdatedmsg = get_string('statusextnotupdated', 'tool_percipioexternalcontentsync');
        $invalidrecordmsg = get_string('invalidimportrecord', 'tool_percipioexternalcontentsync');
        $thumbnailskipped = get_string('thumbnailskipped', 'tool_percipioexternalcontentsync');

        $generator = \phpunit_util::get_data_generator();

        if (self::validate_import_record($record)) {
            // Set default message to course not updated.
            $result->coursestatus = $coursenotupdatedmsg;
            $course = self::create_course_from_imported($record);
            $activity = self::create_externalcontent_from_imported($record);

            if ($existing = self::get_course_by_idnumber($course->idnumber)) {
                $result->courseid = $existing->id;
                $updatecourse = true;
                if (!$mergedcourse = self::update_course_with_imported($existing, $course)) {
                    $updatecourse = false;
                    $mergedcourse = $existing;
                }

                if ($record->course_thumbnail != '') {
                    if ($coursethumbnail) {
                        $response = self::add_course_thumbnail(
                            $mergedcourse->id,
                            $record->course_thumbnail
                        );
                        if (!$response->success) {
                            $result->warn = true;
                        }
                        $result->thumbnailstatus = $response->status;
                    } else {
                        $result->thumbnailstatus = $thumbnailskipped;
                    }
                }

                // Now check the externalcontent.
                $addactivity = $updateactivity = false;
                $existingactivity = self::get_externalcontent_by_idnumber(
                    $mergedcourse->idnumber,
                    $mergedcourse->id
                );

                if ($existingactivity) {
                    $result->activityid = $existingactivity->id;
                    $addactivity = false;
                    $updateactivity = true;

                    $mergedactivity = self::update_externalcontent_with_imported(
                        $existingactivity,
                        $activity
                    );
                    if ($mergedactivity === false) {
                        $updateactivity = false;
                        $addactivity = false;
                        $mergedactivity = $activity;
                        $result->externalcontentstatus = $extnotupdatedmsg;
                    }
                } else {
                    $activity->course = $existing->id;
                    $addactivity = true;
                    $updateactivity = false;
                    $mergedactivity = $activity;
                }

                if ($updatecourse === false && $addactivity === false && $updateactivity === false) {
                    // Course data not changed.
                    $result->coursestatus = $coursenotupdatedmsg;
                } else {
                    // Course or external content differs so we need to update.
                    if ($updatecourse) {
                        update_course($mergedcourse);
                        $result->coursestatus = get_string(
                            'statuscourseupdated',
                            'tool_percipioexternalcontentsync',
                            $mergedcourse->visible
                        );
                    }

                    if ($addactivity) {
                        $activityresponse = $generator->create_module('externalcontent',  $mergedactivity);
                        $mergedactivity->id = $activityresponse->id;

                        $cm = get_coursemodule_from_instance('externalcontent',  $mergedactivity->id);
                        $cm->idnumber = $mergedcourse->idnumber;
                        $DB->update_record('course_modules', $cm);
                        self::update_course_completion_criteria($mergedcourse, $cm);

                        $result->activityid = $mergedactivity->id;
                        $result->externalcontentstatus = $extcreatedmsg;
                    }

                    if ($updateactivity) {
                        $DB->update_record('externalcontent',  $mergedactivity);
                        $cm = get_coursemodule_from_instance('externalcontent',  $mergedactivity->id);
                        $cm->idnumber = $course->idnumber;
                        $DB->update_record('course_modules', $cm);
                        self::update_course_completion_criteria($mergedcourse, $cm);

                        $result->activityid = $mergedactivity->id;
                        $result->externalcontentstatus = $extupdatedmsg;
                    }
                }
            } else {
                $newcourse = create_course($course);
                $result->courseid = $newcourse->id;
                $activity->course = $newcourse->id;
                $result->coursestatus = get_string(
                    'statuscoursecreated',
                    'tool_percipioexternalcontentsync',
                    $newcourse->visible
                );

                if ($record->course_thumbnail != '') {
                    if ($coursethumbnail) {
                        $response = self::add_course_thumbnail(
                            $newcourse->id,
                            $record->course_thumbnail
                        );
                        if ($response->thumbnailfile) {
                            $newcourse->overviewfiles_filemanager = $response->thumbnailfile->get_itemid();
                        }
                        if (!$response->success) {
                            $result->warn = true;
                        }
                        $result->thumbnailstatus = $response->status;
                        update_course($newcourse);
                    } else {
                        $result->thumbnailstatus = $thumbnailskipped;
                    }
                }

                // Now we need to add a External content.
                $activityrecord = $generator->create_module('externalcontent', $activity);

                $cm = get_coursemodule_from_instance('externalcontent', $activityrecord->id);
                $cm->idnumber = $course->idnumber;
                $DB->update_record('course_modules', $cm);

                $result->activityid = $activityrecord->id;
                $result->externalcontentstatus = $extcreatedmsg;
                self::update_course_completion_criteria($newcourse, $cm);
            }
        } else {
            $result->success = false;
            $result->error = $invalidrecordmsg;
            $result->coursestatus = null;
            $result->externalcontentstatus = null;
            $result->thumbnailstatus = null;
            $result->courseid = null;
            $result->activityid = null;
        }
        return $result;
    }



    /**
     * Validate we have the minimum info to create/update course
     *
     * @param object $record The record we imported
     * @return bool true if validated
     */
    public static function validate_import_record($record) {
        // As a minimum we need.
        // course idnumber.
        // course shortname.
        // course longname.
        // external name.
        // external intro.
        // external content.

        $isvalid = true;
        $isvalid = $isvalid && !empty($record->course_idnumber);
        $isvalid = $isvalid && !empty($record->course_shortname);
        $isvalid = $isvalid && !empty($record->course_fullname);
        $isvalid = $isvalid && !empty($record->external_name);
        $isvalid = $isvalid && !empty($record->external_intro);
        $isvalid = $isvalid && !empty($record->external_content);

        return $isvalid;
    }

    /**
     * Resolve a category by IDnumber.
     *
     * @param string $idnumber category IDnumber.
     * @return int category ID.
     */
    public static function resolve_category_by_idnumber($idnumber) {
        global $DB;

        $params = array('idnumber' => $idnumber);
        $id = $DB->get_field_select('course_categories', 'id', 'idnumber = :idnumber', $params, IGNORE_MISSING);
        return $id;
    }

    /**
     * Resolve a category by ID
     *
     * @param string $id category ID.
     * @return int category ID.
     */
    public static function resolve_category_by_id_or_idnumber($id) {
        global $CFG, $DB;

        // Handle null id by selecting the first non zero category id.
        if (is_null($id)) {
            if (method_exists('\core_course_category', 'create')) {
                $id = \core_course_category::get_default()->id;
                return $id;
            } else {
                require_once($CFG->libdir . '/coursecatlib.php');
                $id = \coursecat::get_default()->id;
                return $id;
            }
            return null;
        }

        // Handle numeric id by confirming it exists.
        $params = array('id' => $id);
        if (is_numeric($id)) {
            if ($DB->record_exists('course_categories', $params)) {
                return $id;
            }
            return null;
        }

        // Handle any other id format by treating as a string idnumber value.
        $params = array('idnumber' => $id);
        if ($id = $DB->get_field_select('course_categories', 'id', 'idnumber = :idnumber', $params, MUST_EXIST)) {
            return $id;
        }
        return null;
    }

    /**
     * Return the category id, creating the category if necessary from the import record.
     *
     * @param object $record Validated Imported Record
     * @return int The category id
     */
    public static function get_or_create_category_from_import_record($record) {
        global $CFG;
        $categoryid = $record->category;

        if (!empty($record->course_categoryidnumber)) {
            if (!$categoryid = self::resolve_category_by_idnumber($record->course_categoryidnumber)) {
                if (!empty($record->course_categoryname)) {
                    // Category not found and we have a name so we need to create.
                    $category = new \stdClass();
                    $category->parent = $record->category;
                    $category->name = $record->course_categoryname;
                    $category->idnumber = $record->course_categoryidnumber;

                    if (method_exists('\core_course_category', 'create')) {
                        $createdcategory = \core_course_category::create($category);
                    } else {
                        require_once($CFG->libdir . '/coursecatlib.php');
                        $createdcategory = \coursecat::create($category);
                    }
                    $categoryid = $createdcategory->id;
                }
            }
        }
        return $categoryid;
    }

    /**
     * Retrieve a course by its idnumber.
     *
     * @param string $courseidnumber course idnumber
     * @return object course or null
     */
    public static function get_course_by_idnumber($courseidnumber) {
        global $DB;

        $params = array('idnumber' => $courseidnumber);
        if ($course = $DB->get_record('course', $params)) {
            $tags = \core_tag_tag::get_item_tags_array(
                'core',
                'course',
                $course->id,
                \core_tag_tag::BOTH_STANDARD_AND_NOT,
                0,
                false
            );
            $course->tags = array();
            foreach ($tags as $value) {
                array_push($course->tags, $value);
            }
        }
        return $course;
    }

    /**
     * Create a course from the import record.
     *
     * @param object $record Validated Imported Record
     * @param string $tagdelimiter The value to use to split the delimited $record->course_tags string
     * @return object course or null
     */
    public static function create_course_from_imported($record, $tagdelimiter = "|") {
        $course = new \stdClass();
        $course->idnumber = $record->course_idnumber;
        $course->shortname = $record->course_shortname;
        $course->fullname = $record->course_fullname;
        $course->summary = $record->course_summary;
        $course->summaryformat = 1; // FORMAT_HTML.
        $course->visible = $record->course_visible;

        $course->tags = array();
        // Split the tag string into an array.
        if (!empty($record->course_tags)) {
            $course->tags = explode($tagdelimiter, $record->course_tags);
        }

        // Fixed default values.
        $course->format = "singleactivity";
        $course->numsections = 0;
        $course->newsitems = 0;
        $course->showgrades = 0;
        $course->showreports = 0;
        $course->startdate = time();
        $course->activitytype = "externalcontent";

        $course->category = self::get_or_create_category_from_import_record($record);

        // Add completion flags.
        $course->enablecompletion = 1;

        return $course;
    }

    /**
     * Merge changes from $imported course into $existing course
     *
     * @param object $existing Course Record for existing course
     * @param object $imported  Course Record for imported course
     * @return object course or FALSE if no changes
     */
    public static function update_course_with_imported($existing, $imported) {
        // Sort the tags arrays.
        sort($existing->tags);
        sort($imported->tags);

        $result = clone $existing;
        $result->fullname = $imported->fullname;
        $result->shortname = $imported->shortname;
        $result->idnumber = $imported->idnumber;
        $result->visible = $imported->visible;
        $result->tags = $imported->tags;
        $result->category = $imported->category;

        // We need to apply Moodle FORMAT_HTML conversion as this is how summary would have been stored.
        if ($existing->summary !== format_text($imported->summary, FORMAT_HTML, array('filter' => false))) {
            $result->summary = $imported->summary;
        }

        if ($result != $existing) {
            return $result;
        }
        return false;
    }

    /**
     * Retrieve a externalcontent by its name.
     *
     * @param string $name externalcontent name
     * @param string $courseid course identifier
     * @return object externalcontent.
     */
    public static function get_externalcontent_by_name($name, $courseid) {
        global $DB;

        $params = array('name' => $name, 'course' => $courseid);
        return $DB->get_record('externalcontent', $params);
    }

    /**
     * Retrieve a externalcontent by its idnumber.
     *
     * @param string $idnumber externalcontent name
     * @param string $courseid course identifier
     * @return object externalcontent.
     */
    public static function get_externalcontent_by_idnumber($idnumber, $courseid) {
        global $DB;

        $params = array('idnumber' => $idnumber, 'course' => $courseid);
        $cm = $DB->get_record('course_modules', $params);

        if (!$cm) {
            return null;
        }

        $params = array('id' => $cm->instance, 'course' => $courseid);
        return $DB->get_record('externalcontent', $params);
    }

    /**
     * Create a externalcontent from the import record.
     *
     * @param object $record Validated Imported Record
     * @return object course or null
     */
    public static function create_externalcontent_from_imported($record) {
        // All data provided by the data generator.
        $externalcontent = new \stdClass();
        $externalcontent->name = $record->external_name;
        $externalcontent->intro = $record->external_intro;
        $externalcontent->introformat = 1; // FORMAT_HTML.
        $externalcontent->content = $record->external_content;
        $externalcontent->contentformat = 1; // FORMAT_HTML.

        $externalcontent->completion = 2;
        $externalcontent->completionview = 1;
        $externalcontent->completionexternally = $record->external_markcompleteexternally;

        // Set display option defaults.
        $displayoptions = array();
        if (property_exists($record, 'external_printheading')) {
            $displayoptions['printheading'] = $record->external_printheading;
        }
        if (property_exists($record, 'external_printintro')) {
            $displayoptions['printintro']   = $record->external_printintro;
        }
        if (property_exists($record, 'external_printlastmodified')) {
            $displayoptions['printlastmodified'] = $record->external_printintro;
        }
        $externalcontent->displayoptions = serialize($displayoptions);

        return $externalcontent;
    }

    /**
     * Merge changes from $imported into $existing
     *
     * @param object $existing Page Record for existing page
     * @param object $imported  page Record for imported page
     * @return object page or FALSE if no changes
     */
    public static function update_externalcontent_with_imported($existing, $imported) {
        $result = clone $existing;

        $result->name = $imported->name;
        $result->intro = $imported->intro;
        $result->content = $imported->content;
        $result->completionexternally = clean_param($imported->completionexternally, PARAM_BOOL);

        // Set display option defaults.
        $result->displayoptions = $imported->displayoptions;

        if ($result != $existing) {
            return $result;
        }
        return false;
    }

    /**
     * Update the course completion criteria to use the Activity Completion
     *
     * @param object $course Course Object
     * @param object $cm Course Module Object for the Single Page
     * @return void
     */
    public static function update_course_completion_criteria($course, $cm) {
        $criterion = new \completion_criteria_activity();

        $params = array('id' => $course->id, 'criteria_activity' => array($cm->id => 1));
        if ($criterion->fetch($params)) {
            return;
        }

        // Criteria for course.
        $criteriadata = new \stdClass();
        $criteriadata->id = $course->id;
        $criteriadata->criteria_activity = array($cm->id => 1);
        $criterion->update_config($criteriadata);

        // Handle overall aggregation.
        $aggdata = array(
            'course'        => $course->id,
            'criteriatype'  => null,
            'method' => COMPLETION_AGGREGATION_ALL
        );

        $aggregation = new \completion_aggregation($aggdata);
        $aggregation->save();

        $aggdata['criteriatype'] = COMPLETION_CRITERIA_TYPE_ACTIVITY;
        $aggregation = new \completion_aggregation($aggdata);
        $aggregation->save();

        $aggdata['criteriatype'] = COMPLETION_CRITERIA_TYPE_COURSE;
        $aggregation = new \completion_aggregation($aggdata);
        $aggregation->save();

        $aggdata['criteriatype'] = COMPLETION_CRITERIA_TYPE_ROLE;
        $aggregation = new \completion_aggregation($aggdata);
        $aggregation->save();
    }


    /**
     * Add_course_thumbnail
     *
     * @param  object $courseid
     * @param  string $url
     * @return object Object containing a status string and a stored_file object or null
     */
    public static function add_course_thumbnail($courseid, $url) {
        global $CFG;

        $response = new \stdClass();
        $response->success = true;
        $response->status = null;
        $response->thumbnailfile = null;

        require_once($CFG->libdir . '/filelib.php');
        $fs = get_file_storage();

        $overviewfilesoptions = course_overviewfiles_options($courseid);
        $filetypesutil = new \core_form\filetypes_util();
        $whitelist = $filetypesutil->normalize_file_types($overviewfilesoptions['accepted_types']);

        $parsedurl = new \moodle_url($url);

        $ext = pathinfo($parsedurl->get_path(), PATHINFO_EXTENSION);
        $filename = 'thumbnail.' . $ext;

        // Check the extension is valid.
        if (!$filetypesutil->is_allowed_file_type($filename, $whitelist)) {
            $response->success = false;
            $response->status = get_string('thumbnailinvalidext', 'tool_percipioexternalcontentsync', $ext);
            return $response;
        }

        $coursecontext = \context_course::instance($courseid);

        // Get the file if it already exists.
        $response->thumbnailfile = $fs->get_file($coursecontext->id, 'course', 'overviewfiles', 0, '/', $filename);

        if ($response->thumbnailfile) {
            // Check the file is from same source as url.
            $source = $response->thumbnailfile->get_source();
            if ($source == $url) {
                // It is the same so return this file.
                $response->status = get_string('thumbnailsamesource', 'tool_percipioexternalcontentsync');
                return $response;
            } else {
                // Delete files and continue with download.
                $fs->delete_area_files($coursecontext->id, 'course', 'overviewfiles');
                $response->thumbnailfile = null;
            }
        }

        $thumbnailfilerecord = array(
            'contextid' => $coursecontext->id,
            'component' => 'course',
            'filearea' => 'overviewfiles',
            'itemid' => '0',
            'filepath' => '/',
            'filename' => $filename,
        );

        $urlparams = array(
            'calctimeout' => false,
            'timeout' => 5,
            'skipcertverify' => true,
            'connecttimeout' => 5,
        );

        try {
            $response->thumbnailfile = $fs->create_file_from_url($thumbnailfilerecord, $url, $urlparams);
            // Check if Moodle recognises as a valid image file.
            if (!$response->thumbnailfile->is_valid_image()) {
                $fs->delete_area_files($coursecontext->id, 'course', 'overviewfiles');
                $response->success = false;
                $response->thumbnailfile = null;
                $response->status = get_string('thumbnailinvalidtype', 'tool_percipioexternalcontentsync');
            } else {
                $response->status = get_string('thumbnaildownloaded', 'tool_percipioexternalcontentsync');
            }
            return $response;
        } catch (\file_exception $e) {
            $fs->delete_area_files($coursecontext->id, 'course', 'overviewfiles');
            $response->success = false;
            $response->thumbnailfile = null;
            $response->status = get_string('thumbnaildownloaderror', 'tool_percipioexternalcontentsync', $e->getMessage());
            return $response;
        }
    }
}
