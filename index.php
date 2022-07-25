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
 * Trigger the synchronisation manually.
 *
 * @package    tool_percipioexternalcontentsync
 * @copyright  2019-2022 LushOnline
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require_once(__DIR__ . '/../../../config.php');
// Protect the page.
require_login();
if (!is_siteadmin()) { // Only site admins.
    throw new moodle_exception('nopermissions', 'core');
}

// Set up the page for display.
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$PAGE->set_heading($SITE->fullname);
$PAGE->set_title($SITE->fullname . ': ' . get_string('pluginname', 'tool_percipioexternalcontentsync'));
$PAGE->set_url(new moodle_url('/admin/tool/percipioexternalcontentsync/index.php'));
$PAGE->set_pagetype('admin-' . $PAGE->pagetype);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'tool_percipioexternalcontentsync'));

$progress = new \html_progress_trace();

$task = new tool_percipioexternalcontentsync\task\percipiosync();
$task->settrace($progress);
echo html_writer::tag('div', html_writer::tag('p', $task->get_name()));

try {
    $task->execute();
} catch (Exception $e) {
    echo $OUTPUT->notification($e->getMessage(), 'error');
    echo $OUTPUT->footer();
    die;
}
echo $OUTPUT->notification('Successfully executed.', 'success');
echo $OUTPUT->footer();
