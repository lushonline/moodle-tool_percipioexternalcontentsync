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
 * Links and settings
 *
 * This file contains links and settings used by tool_percipioexternalcontentsync
 *
 * @package   tool_percipioexternalcontentsync
 * @copyright 2019-2022 LushOnline
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    // Our new settings.
    $settings = new admin_settingpage(
        'tool_percipioexternalcontentsync',
        get_string('settingstitle', 'tool_percipioexternalcontentsync')
    );

    $baseurl = new admin_setting_configtext(
        'tool_percipioexternalcontentsync/baseurl',
        get_string('baseurl', 'tool_percipioexternalcontentsync'),
        get_string('baseurldesc', 'tool_percipioexternalcontentsync'),
        '',
        PARAM_URL
    );
    $settings->add($baseurl);

    $orgid = new admin_setting_configtext(
        'tool_percipioexternalcontentsync/orgid',
        get_string('orgid', 'tool_percipioexternalcontentsync'),
        get_string('orgiddesc', 'tool_percipioexternalcontentsync'),
        '',
        PARAM_TEXT
    );
    $settings->add($orgid);

    $bearer = new admin_setting_configtext(
        'tool_percipioexternalcontentsync/bearer',
        get_string('bearer', 'tool_percipioexternalcontentsync'),
        get_string('bearerdesc', 'tool_percipioexternalcontentsync'),
        '',
        PARAM_TEXT
    );
    $settings->add($bearer);

    $updatedsince = new admin_setting_configtext(
        'tool_percipioexternalcontentsync/updatedsince',
        get_string('updatedsince', 'tool_percipioexternalcontentsync'),
        get_string('updatedsincedesc', 'tool_percipioexternalcontentsync'),
        '',
        PARAM_TEXT
    );
    $settings->add($updatedsince);

    for ($i = 250; $i <= 1000; $i += 250) {
        $pagesize[$i] = "$i";
    }

    $max = new admin_setting_configselect(
        'tool_percipioexternalcontentsync/max',
        get_string('max', 'tool_percipioexternalcontentsync'),
        get_string('maxdesc', 'tool_percipioexternalcontentsync'),
        1000,
        $pagesize
    );
    $settings->add($max);

    // Course Category list for the drop-down.
    if (method_exists('\core_course_category', 'make_categories_list')) {
        $displaylist = core_course_category::make_categories_list('moodle/course:create');
    } else {
        $displaylist = coursecat::make_categories_list('moodle/course:create');
    }

    $category = new admin_setting_configselect(
        /* We define the settings name as tool_percipioexternalcontentsync/category, so we can later
        * retrieve this value by calling get_config(‘tool_percipioexternalcontentsync’, ‘category’) */
        'tool_percipioexternalcontentsync/category',
        get_string('coursecategory', 'tool_percipioexternalcontentsync'),
        get_string('coursecategorydesc', 'tool_percipioexternalcontentsync'),
        1,
        $displaylist
    );

    $settings->add($category);

    $coursethumbnail = new admin_setting_configcheckbox(
        'tool_percipioexternalcontentsync/coursethumbnail',
        get_string('coursethumbnail', 'tool_percipioexternalcontentsync'),
        get_string('coursethumbnaildesc', 'tool_percipioexternalcontentsync'),
        1
    );
    $settings->add($coursethumbnail);

    $settings->add(new admin_setting_heading(
        'tool_percipioexternalcontentsync/templatefileheader',
        get_string('templatefileheader', 'tool_percipioexternalcontentsync'),
        ''
    ));

    $settings->add(
        new admin_setting_configstoredfile(
            'tool_percipioexternalcontentsync/templatefile',
            get_string('templatefile', 'tool_percipioexternalcontentsync'),
            get_string('templatefileexplain', 'tool_percipioexternalcontentsync'),
            'templatefiles'
        ),
        0,
        array('maxfiles' => 1, 'accepted_types' => '*')
    );


    // Add to the admin settings for localplugins.
    $ADMIN->add('courses', $settings);
}
