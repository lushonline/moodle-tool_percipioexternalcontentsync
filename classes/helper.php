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

use mod_externalcontent\instance;
use mod_externalcontent\importableinstance;
use mod_externalcontent\importrecord;

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
     * format_asset_tags to Moodle standard
     * Max length of 50 and trimmed.
     *
     * @param  string $tag
     * @return string
     */
    private static function format_asset_tags($tag) {
        $result = substr($tag, 0, 50);
        return trim($result);
    }


    /**
     * Convert the Percipio Asset details to a delimited string of tags.
     * Tags can be no longer than 50 characters in Moodle
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

        // Format for Moodle.
        $tagsarry = array_map('self::format_asset_tags', $tagsarry);
        // Normalize the tags.
        return \core_tag_tag::normalize($tagsarry, false);
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
     * Return the category id, creating the category if necessary.
     *
     * @param int $parentid Parent id
     * @param string $categoryname The category name
     * @param string $categoryidnumber The category idnumber
     * @return int The category id, or $parentid if empty
     */
    public static function get_or_create_category(int $parentid,
                                                  ?string $categoryname = null,
                                                  ?string $categoryidnumber = null): int {
        global $CFG;
        $categoryid = $parentid;

        if (!empty($categoryidnumber)) {
            if (!$categoryid = self::resolve_category_by_idnumber($categoryidnumber)) {
                if (!empty($categoryname)) {
                    // Category not found and we have a name so we need to create.
                    $category = new \stdClass();
                    $category->parent = $parentid;
                    $category->name = $categoryname;
                    $category->idnumber = $categoryidnumber;

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
     * sanitizeUrl
     *
     * @param  mixed $url
     * @return void
     */
    private static function sanitizeurl($url) {
        $parts = parse_url($url);

        // Optional but we only sanitize URLs with scheme and host defined.
        if ($parts === false || empty($parts["scheme"]) || empty($parts["host"])) {
            return $url;
        }

        $sanitizedpath = null;
        if (!empty($parts["path"])) {
            $pathparts = explode("/", $parts["path"]);
            foreach ($pathparts as $pathpart) {
                if (empty($pathpart)) {
                    continue;
                }
                // The Path part might already be urlencoded.
                $sanitizedpath .= "/" . rawurlencode(rawurldecode($pathpart));
            }
        }

        // Build the url.
        $targeturl = $parts["scheme"] . "://" .
            ((!empty($parts["user"]) && !empty($parts["pass"])) ? $parts["user"] . ":" . $parts["pass"] . "@" : "") .
            $parts["host"] .
            (!empty($parts["port"]) ? ":" . $parts["port"] : "") .
            (!empty($sanitizedpath) ? $sanitizedpath : "") .
            (!empty($parts["query"]) ? "?" . $parts["query"] : "") .
            (!empty($parts["fragment"]) ? "#" . $parts["fragment"] : "");

        return $targeturl;
    }

    /**
     * Convert the Percipio Asset to an External Content importrecord.
     *
     * @param object $asset The asset we recieved from Percipio
     * @param string|int $parentcategory The parentcategory name or id
     * @param bool $thumbnail If true, then the thumbnail for the course will be processed.
     * @return mod_externalcontent\importrecord|bool The asset converted to an importrecord, or false if not valid
     */
    public static function percio_asset_to_importrecord($asset, $parentcategory = null, $thumbnail = true) : importrecord {

        // Create/Retrieve categoryid.
        $parentcategoryid = self::resolve_category_by_id_or_idnumber($parentcategory);
        $categoryinfo = self::get_percio_asset_category($asset);
        $categoryid = self::get_or_create_category($parentcategoryid,
                                                   $categoryinfo->categoryname,
                                                   $categoryinfo->categoryidnumber);
        // Create courseimport class.
        $courseimport = new \stdClass();
        $courseimport->idnumber = $asset->xapiActivityId;;
        $courseimport->shortname = substr($asset->localizedMetadata[0]->title, 0, 215) . ' (' . $asset->id . ')';
        $courseimport->fullname = substr($asset->localizedMetadata[0]->title, 0, 255);
        $courseimport->summary = self::get_percipio_description($asset);
        $courseimport->tags = self::get_percio_asset_tags($asset);
        $courseimport->visible = strcasecmp($asset->lifecycle->status, 'ACTIVE') == 0 ? 1 : 0;
        $courseimport->thumbnail = $thumbnail ? self::sanitizeurl($asset->imageUrl) : null;
        $courseimport->category = $categoryid;

        // Create moduleimport class.
        $moduleimport = new \stdClass();
        $moduleimport->name = substr($asset->localizedMetadata[0]->title, 0, 255);
        $moduleimport->intro = self::get_percipio_description($asset);
        $moduleimport->content = self::get_percipio_description($asset, true, true);
        $moduleimport->markcompleteexternally = strcasecmp($asset->contentType->percipioType, 'CHANNEL') == 0 ? 0 : 1;

        // Get our importrecord.
        $importrecord = new importrecord($courseimport, $moduleimport);
        return $importrecord->validate() ? $importrecord : false;
    }


    /**
     * Import the Percipio Asset, creating or updating as needed.
     *
     * @param object $asset The asset we receeved from Percipio
     * @param string $parentcategory The parentcategory name or id
     * @param bool $thumbnail If true, then the thumbnail for the course will be processed.
     * @return object Processing information for the asset
     */
    public static function import_percio_asset($asset, $parentcategory = null, $thumbnail = true) {
        global $DB;

        $result = new \stdClass();
        $result->success = false;
        $result->message = null;
        $result->courseid = null;
        $result->moduleid = null;

        if ($importrecord = self::percio_asset_to_importrecord($asset, $parentcategory, $thumbnail)) {
            $instance = importableinstance::get_from_importrecord($importrecord);
            $result->success = true;
            $result->courseid = $instance->get_course_id();
            $result->moduleid = $instance->get_module_id();
            $result->message = implode(", ", $instance->get_messages());
        } else {
            $result->success = false;
            $result->message = get_string('invalidimportrecord', 'tool_percipioexternalcontentsync');

        };

        return $result;
    }
}
