<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * The scheduled task class file.
 *
 * @package   tool_percipioexternalcontentsync
 * @copyright 2019 - 2022 LushOnline
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_percipioexternalcontentsync\task;

use \tool_percipioexternalcontentsync\helper;
use \tool_percipioexternalcontentsync\apiclient;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/externalcontent/lib.php');

/**
 * Task tool_percipioexternalcontentsync implementing Percipio Sync.
 *
 * @copyright  2019-2023 LushOnline
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class percipiosync extends \core\task\scheduled_task {

    /**
     * The \tool_percipioexternalcontentsync\apiclient instance used to query for data.
     * @var \tool_percipioexternalcontentsync\apiclient
     */
    protected $service = null;

    /**
     * The trace logger
     * @var progress_trace
     */
    protected $trace = null;


    /**
     * Get Name of Task
     *
     * @return string The name of the task
     */
    public function get_name() {
        return get_string('taskname', 'tool_percipioexternalcontentsync');
    }

    /**
     * Set the progress tracer
     * @param \progress_trace|null $progress Optional class for tracking progress
     *
     */
    public function settrace($progress) {
        $this->trace = $progress;
    }

    /**
     * Get Percipio Metadata and process it
     *
     * @param string $parentcategory The parentcategory name or id
     * @param string $updatedsince If passed, will find content since this date. Format is ISO8601.
     * @param string $max If passed, will set the number of records per request.
     * @param string $pagingrequestid If passed, will set the pagingRequestId for request.
     * @param bool $thumbnail If true, then the thumbnail will be processed.
     * @return array
     */
    public function get_all_metadata($parentcategory, $updatedsince=null, $max=null, $pagingrequestid=null, $thumbnail=true) {
        $this->trace->output(get_string('startingtask', 'tool_percipioexternalcontentsync'));
        if (function_exists('memory_get_usage')) {
            $this->trace->output(get_string('memoryusage',
                                            'tool_percipioexternalcontentsync',
                                            display_size(memory_get_usage())
                                            )
                                );
        }
        $this->trace->output('');

        $processed = array();
        $processed['downloaded'] = 0;
        $processed['totalcount'] = 0;
        $processed['success'] = 0;
        $processed['failed'] = 0;

        $data = array();
        $data['offset'] = 0;
        $data['max'] = 1000;
        $data['updatedSince'] = null;
        $data['pagingRequestId'] = null;

        if (!empty($updatedsince)) {
            $data['updatedSince'] = $updatedsince;
        }

        if (!empty($max)) {
            $data['max'] = $max;
        }

        if (!empty($pagingrequestid)) {
            $data['pagingRequestId'] = $pagingrequestid;
        }

        $assetlist = null;
        do {
            $callresult = null;
            $moredata = false;
            $pagingrequestid = $data['pagingRequestId'] ?? null;
            $this->trace->output(get_string('requestparams',
                                            'tool_percipioexternalcontentsync',
                                            $data
                                            )
                                );
            $callresult = $this->service->get_catalog_content($data);
            if ($callresult) {
                $assetlist = $callresult->data;
                $processed['downloaded'] += count($assetlist);
                $processed['totalcount'] = intval($callresult->headers['x-total-count']);

                if ($processed['downloaded'] < $processed['totalcount']) {  // We need to download more data.
                    if (empty($data['pagingRequestId'])) {
                        $data['pagingRequestId'] = $callresult->headers['x-paging-request-id'];
                    }
                    $data['offset'] = $data['offset'] + $data['max'];
                    $moredata = true;
                } else {
                    $moredata = false;
                }
                unset($callresult);
                $this->trace->output(get_string('responsedata',
                                            'tool_percipioexternalcontentsync',
                                            $processed
                                            )
                                    );
                $this->trace->output(get_string('startprocessing', 'tool_percipioexternalcontentsync'));
                foreach ($assetlist as $asset) {
                    $importresult = helper::import_percio_asset($asset,
                                                                $parentcategory,
                                                                $thumbnail);
                    if ($importresult->success) {
                        $this->trace->output(get_string('successprocessing',
                                                        'tool_percipioexternalcontentsync', $importresult));
                        $processed['success'] += 1;
                    }

                    if (!$importresult->success) {
                        $this->trace->output(get_string('failprocessing',
                                                        'tool_percipioexternalcontentsync', $importresult));
                        $processed['failed'] += 1;
                    }

                }
                unset($importresult);
                unset($assetlist);
                $this->trace->output(get_string('endprocessing', 'tool_percipioexternalcontentsync'));
                $this->trace->output('');
            }
        } while ($moredata);

        $this->trace->output(get_string('taskresults',
                                        'tool_percipioexternalcontentsync', $processed));
        if (function_exists('memory_get_usage')) {
            $this->trace->output(get_string('memoryusage',
                                            'tool_percipioexternalcontentsync',
                                            display_size(memory_get_usage())
                                            )
                                );
        }
        $this->trace->output('');
        return true;
    }


    /**
     * Run the task
     *
     * @return boolean The success of the task
     */
    public function execute() {
        $config = get_config('tool_percipioexternalcontentsync');

        if (!isset($this->trace)) {
            $this->trace = new \text_progress_trace();
        }

        $percipiometadata = null;

        if (empty($config->category)) {
            $this->trace->output(get_string('errornocategory', 'tool_percipioexternalcontentsync'));
            return false;
        } else if (empty($config->baseurl)) {
            $this->trace->output(get_string('errornobaseurl', 'tool_percipioexternalcontentsync'));
            return false;
        } else if (empty($config->orgid)) {
            $this->trace->output(get_string('errornoorgid', 'tool_percipioexternalcontentsync'));
            return false;
        } else if (empty($config->bearer)) {
            $this->trace->output(get_string('errornobearer', 'tool_percipioexternalcontentsync'));
            return false;
        }

        if (is_null($this->service)) {
            $this->service = new \tool_percipioexternalcontentsync\apiclient($this->trace, false);
        }

        // We will most certainly need extra time and memory to process big files.
        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_EXTRA);

        try {
            // Capture current time as an ISO8601 formatted value using ZULU time.
            $starttimestamp = date('c', time());
            if ($success = self::get_all_metadata($config->category,
                                                   $config->updatedsince,
                                                   intval($config->max),
                                                   null,
                                                   $config->coursethumbnail)) {
                set_config('updatedsince', $starttimestamp, 'tool_percipioexternalcontentsync');
                $this->trace->output(get_string('updateconfigdate',
                                                'tool_percipioexternalcontentsync', $starttimestamp));
            };
        } catch (\Exception $e) {
            $this->trace->output($e->getMessage());
            return false;
        }

        return true;
    }

}
