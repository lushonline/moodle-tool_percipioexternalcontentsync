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
 * The Percipio API Client file.
 *
 * @package    tool_percipioexternalcontentsync
 * @copyright  2019-2022 LushOnline
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_percipioexternalcontentsync;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/externalcontent/lib.php');
require_once($CFG->dirroot.'/lib/filelib.php');

/**
 * Web service class.
 */
class apiclient {

    /**
     * Organization ID.
     * @var string
     */
    protected $orgid;

    /**
     * Bearer Token
     * @var string
     */
    protected $bearer;

    /**
     * API base URL.
     * @var string
     */
    protected $baseurl;

    /**
     * Curl instance
     * @var curl
     */
    protected $curlinstance;

    /**
     * Tracing method
     * @var progress_trace
     */
    protected $trace = null;

    /**
     * Debugging
     * @var boolean
     */
    protected $debug = false;

    /**
     * The constructor for the webservice class.
     * @param \progress_trace|null $progress Optional class for tracking progress
     * @param noolean $debug If enabled, information about the requests will be outputted.
     * @throws moodle_exception Moodle exception is thrown for missing config settings.
     */
    public function __construct(\progress_trace $progress = null, $debug = false) {
        $config = get_config('tool_percipioexternalcontentsync');

        $this->debug = $debug;
        $this->trace = $progress;

        if (!$this->trace) {
            $this->trace = new \null_progress_trace();
        }

        // Get and remember the API key.
        if (!empty($config->orgid)) {
            $this->orgid = $config->orgid;
        } else {
            throw new \moodle_exception('errorwebservice', 'tool_percipioexternalcontentsync', '',
            get_string('errornoorgid', 'tool_percipioexternalcontentsync'));
        }

        // Get and remember the API secret.
        if (!empty($config->bearer)) {
            $this->bearer = $config->bearer;
        } else {
            throw new \moodle_exception('errorwebservice', 'tool_percipioexternalcontentsync', '',
            get_string('errornobearer', 'tool_percipioexternalcontentsync'));
        }

        // Get and remember the API URL.
        if (!empty($config->baseurl)) {
            $this->baseurl = $config->baseurl;
        } else {
            throw new \moodle_exception('errorwebservice', 'tool_percipioexternalcontentsync', '',
            get_string('errornobaseurl', 'tool_percipioexternalcontentsync'));
        }
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
     * Set the debug
     * @param boolean $debug Output debugging trace information
     *
     */
    public function setdebug($debug) {
        $this->debug = $debug;
    }

    /**
     * Makes the call to curl using the specified method, url, and parameter data.
     * This has been moved out of make_call to make unit testing possible.
     *
     * @param \curl $curl The curl object used to make the request.
     * @param string $method The HTTP method to use.
     * @param string $url The URL to append to the API URL
     * @param array|string $data The data to attach to the call.
     * @return stdClass The call's result.
     */
    protected function make_curl_call(&$curl, $method, $url, $data) {
        return $curl->$method($url, $data);
    }

    /**
     * Gets a curl object in order to make API calls. This function was created
     * to enable unit testing for the webservice class.
     * @return curl The curl object used to make the API calls
     */
    protected function get_curl_object() {
        if (empty($this->curlinstance)) {
            $this->curlinstance = new \curl();
        }

        return $this->curlinstance;
    }

    /**
     * Makes a REST call.
     *
     * @param string $path The path to append to the API URL
     * @param array|string $data The data to attach to the call.
     * @param string $method The HTTP method to use.
     * @return stdClass The call's result in JSON format.
     * @throws moodle_exception Moodle exception is thrown for curl errors.
     */
    private function make_call($path, $data = array(), $method = 'get') {
        global $CFG;
        $url = $this->baseurl . $path;
        $method = strtolower($method);
        $curl = $this->get_curl_object();
        $curl->setHeader('Authorization: Bearer ' . $this->bearer);

        // Set the body.
        if ($method != 'get') {
            $curl->setHeader('Content-Type: application/json');
            $data = is_array($data) ? json_encode($data) : $data;
        }

        $response = $this->make_curl_call($curl, $method, $url, $data);

        if ($curl->get_errno()) {
            throw new \moodle_exception('errorwebservice', 'tool_percipioexternalcontentsync', '', $curl->error);
        }

        $jsonresponse = json_decode($response);

        $headers = $curl->getResponse();

        $httpstatus = $curl->get_info()['http_code'];
        $message = '';

        if ($httpstatus >= 400) {
            if ($response) {
                $message .= 'HTTP Status: '.$httpstatus.'.';
                if (isset($jsonresponse->error)) {
                    $message .= ' Parsed Error: '.$response->error.'.';
                }

                if (isset($jsonresponse->errors)) {
                    $message .= ' Parsed Errors.';
                    foreach ($jsonresponse->errors as $error) {
                        $message .= ' Code: '.$error->code;
                        if (property_exists($error, 'additionalInfo')) {
                            $message .= ' Message: ';
                            $message .= $error->additionalInfo;
                        }
                    }
                }

                throw new \moodle_exception('errorwebservice', 'tool_percipioexternalcontentsync',
                '', $message);
            } else {
                throw new \moodle_exception('errorwebservice', 'tool_percipioexternalcontentsync',
                                '', 'HTTP Status: '.$httpstatus);
            }
        }

        $results = new \stdClass();
        $results->status = $httpstatus;
        $results->data = $jsonresponse;
        $results->headers = $headers;

        return $results;
    }

    /**
     * Get catalog-content call
     *
     * @param array|string $data The data to use for the call.
     * @return array An array of assets.
     */
    public function get_catalog_content($data = array()) {
        if (empty($data['offset'])) {
            $data['offset'] = 0;
        }

        if (empty($data['max'])) {
            $data['max'] = 1000;
        }

        if (empty($data['pagingRequestId'])) {
            $data['pagingRequestId'] = null;
        }

        if (empty($data['updatedSince'])) {
            $data['updatedSince'] = null;
        }

        $result = $this->make_call('/content-discovery/v2/organizations/'.$this->orgid.'/catalog-content', $data);
        $result->data = $result->data ?? [];

        return $result;
    }

    /**
     * Makes a paginated call to get all the catalog content
     * Makes a call like make_call() but specifically for GETs with paginated results.
     *
     * @param array|string $data The data to use for the call.
     * @return array The retrieved data.
     * @see make_call()
     */
    public function get_all_catalog_content($data) {
        $aggregatedata = array();
        $requestcounter = 0;
        $totalcount = 0;

        if (empty($data['offset'])) {
            $data['offset'] = 0;
        }

        if (empty($data['max'])) {
            $data['max'] = 1000;
        }

        if (empty($data['pagingRequestId'])) {
            $data['pagingRequestId'] = null;
        }

        if (empty($data['updatedSince'])) {
            $data['updatedSince'] = null;
        }

        do {
            $requestcounter += 1;
            $callresult = null;
            $moredata = false;
            $this->trace->output('Request: '.$requestcounter.' pagingRequestId: '.$data['pagingRequestId']);
            $callresult = $this->get_catalog_content($data);
            if ($callresult) {
                if (isset($callresult->data)) {
                    $aggregatedata = array_merge($aggregatedata, $callresult->data);
                }
                $downloaded = count($aggregatedata);
                $totalcount = intval($callresult->headers['x-total-count']);

                if ($downloaded < $totalcount) {  // We need to download more data.
                    if (empty($data['pagingRequestId'])) {
                        $data['pagingRequestId'] = $callresult->headers['x-paging-request-id'];
                    }
                    $data['offset'] = $data['offset'] + $data['max'];
                    $moredata = true;
                } else {
                    $moredata = false;
                }
                $this->trace->output('Response: '.$requestcounter.' Downloaded: '.$downloaded.' of '.$totalcount);
            }
        } while ($moredata);

        return $aggregatedata;
    }
}
