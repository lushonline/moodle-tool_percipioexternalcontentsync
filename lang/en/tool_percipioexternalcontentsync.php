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
 * Strings for component 'tool_percipioexternalcontentsync', language 'en'
 *
 * @package    tool_percipioexternalcontentsync
 * @copyright  2019-2022 LushOnline
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Percipio External Content Sync';
$string['taskname'] = 'Percipio External Content Sync';

// Settings.
$string['settingstitle'] = 'Percipio External Content Sync Settings';

$string['baseurl'] = 'Percipio Base URL';
$string['baseurldesc'] = 'The base url of the Percipio site we are connecting to for syncing.';

$string['orgid'] = 'Percipio OrgId';
$string['orgiddesc'] = 'The orgid of the Percipio site we are connecting to for syncing.';

$string['bearer'] = 'Percipio Bearer';
$string['bearerdesc'] = 'The bearer token for teh service account of the Percipio site we are connecting to for syncing.';

$string['updatedsince'] = 'Return assets updated since';
$string['updatedsincedesc'] = 'The ISO8601 formatted date used to filter the assets returned. Leave empty to retrieve all assets. This will be automatically set by the task on last successful run. You can set this to any date manually.';

$string['max'] = 'Max assets per request';
$string['maxdesc'] = 'Maximum number of assets to retrieve per request.';

$string['coursethumbnail'] = 'Course thumbnail';
$string['coursethumbnaildesc'] = 'Download the thumbnail image for the course?';

$string['coursecategory'] = 'Parent Category';
$string['coursecategorydesc'] = 'This setting determines the parent category under which any new categories are created.';

// APIClient.
$string['errornocategory'] = 'No Category has been set, please configure plugin';
$string['errornobaseurl'] = 'No Base URL has been set, please configure plugin';
$string['errornoorgid'] = 'No OrgId has been set, please configure plugin';
$string['errornobearer'] = 'No Bearer has been set, please configure plugin';

$string['errorjsonparse'] = 'Could not parse response: JSON parsing error: "{$a}"';
$string['errorapicall'] = 'Catalog API Call failed:"{$a}"';
$string['errorservererror'] = 'Catalog API Call failed.  Server sent : "{$a}"';
$string['errorjsonparse'] = 'Could not parse response: JSON parsing error: "{$a}"';
$string['errorapicall'] = 'Catalog API Call failed:"{$a}"';
$string['errorservererror'] = 'Catalog API Call failed.  Server sent : "{$a}"';

// Cache.
$string['cachedef_helper'] = 'Upload page helper caching';

// Helper.
$string['thumbnailinvalidext'] = 'Invalid type. {$a}.';
$string['thumbnailinvalidtype'] = 'Invalid type.';
$string['thumbnailsamesource'] = 'Not updated.';
$string['thumbnaildownloaded'] = 'Created.';
$string['thumbnaildownloaderror'] = 'Error. {$a}.';

$string['thumbnailupdated'] = 'Updated.';
$string['thumbnailcreated'] = 'Created.';
$string['thumbnailnotupdated'] = 'Not updated.';
$string['thumbnailskipped'] = 'Skipped.';

// Importer.
$string['invalidparentcategoryid'] = 'Parent category is invalid.';
$string['invalidimportrecord'] = 'Invalid Import Record.';
$string['statuscoursecreated'] = 'Created.';
$string['statuscourseupdated'] = 'Updated.';
$string['statuscoursenotupdated'] = 'Not Updated.';
$string['statusextcreated'] = 'Created.';
$string['statusextupdated'] = 'Updated.';
$string['statusextnotupdated'] = 'Not Updated.';
