# tool_percipioexternalcontentsync

[![Moodle Plugin CI](https://github.com/lushonline/moodle-tool_percipioexternalcontentsync/actions/workflows/ci.yml/badge.svg)](https://github.com/lushonline/moodle-tool_percipioexternalcontentsync/actions/workflows/ci.yml)

A tool to allow sync of assets from a Skillsoft Percipio site as External content activities using Percipio Content Discovery API.

A Percipio site could contain **40,000 or more** assets, this tool allows all these to be synched easily to Moodle. Working with the Skillsoft team you can limit the assets that are included in the sync using the [Content Selection Process](https://documentation.skillsoft.com/en_us/percipio/Content/A_Administrator/System_Integration_Self_Service/adm-int-content-selection-select.htm).

The schedule task runs the sync process which will create/update or deactivate a Moodle Course, consisting of a Single External content activity for each asset synched from Percipio, more details can be found in [How it works](#how-it-works).

The External content Activity and Course are setup to support Moodle Completion based on completion information returned from Percipio using xAPI if EXTERNAL_MARKCOMPLETEEXTERNALLY column if Percipio supports sending a completed xAPI statement to the [External content activity module](https://github.com/lushonline/moodle-mod_externalcontent#setup-activity-provider) LRS.

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [How it works](#how-it-works)
- [More details](#more-details)

## Requirements

To use this plugin you will need:

1. Your Moodle site must accessible over the Internet, using HTTPS and a SSL Certificate from a recognised Public Certificate Authority to be able to get results for the Percipio content via the basic LRS functionality provdied by [External content activity module](https://github.com/lushonline/moodle-mod_externalcontent#setup-activity-provider).
2. A Skillsoft [Percipio](https://www.skillsoft.com/platform-solution/percipio/) Site
3. A [Percipio Service Account](https://documentation.skillsoft.com/en_us/pes/Integration/Understanding-Percipio/rest-api/pes_authentication.htm) with permission for accessing the [CONTENT DISCOVERY API](https://documentation.skillsoft.com/en_us/pes/Integration/Understanding-Percipio/rest-api/pes_rest_api.htm)
4. A [LRS Connection Configured by Skillsoft](https://documentation.skillsoft.com/en_us/pes/Integration/int_xapi.htm) to connect to the basic LRS functionality provdied by [External content activity module](https://github.com/lushonline/moodle-mod_externalcontent#setup-activity-provider). You will need to share the information from Moodle with Skillsoft.

**IMPORTANT** : Skillsoft may require you to pay fees to enable the LRS connection and utilise the APIs. Please contact Skillsoft for information.

## Installation

---

1. Install the External content activity module:

   ```sh
   git clone https://github.com/lushonline/moodle-mod_externalcontent.git mod/externalcontent
   ```

   Or install via the Moodle plugin directory:

   https://moodle.org/plugins/mod_externalcontent

2. Install the plugin the same as any standard moodle plugin either via the
   Moodle plugin directory, or you can use git to clone it into your source:

   ```sh
   git clone https://github.com/lushonline/moodle-tool_percipioexternalcontentsync.git admin/tool/percipioexternalcontentsync
   ```

   Or install via the Moodle plugin directory:

   https://moodle.org/plugins/tool_percipioexternalcontentsync

3. Configure the plugin.

## Configuration

The following configuration settings need to be set in Moodle.

| Name                        | Setting         | Description                                                                                                                                                                                                                                                                                                                                                              |
| --------------------------- | --------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Percipio Base URL           | baseurl         | This is the base URL for accessing the Percipio API. See [Skillsoft Documentation](https://documentation.skillsoft.com/en_us/pes/Integration/int_api_overview.htm), this should be either https://api.percipio.com or https://dew1-api.percipio.com. The Skillsoft Team will provide this information.                                                                   |
| Percipio OrgId              | orgid           | This is a UUID that is unique to the Percipio Site. The Skillsoft Team will provide this information.                                                                                                                                                                                                                                                                    |
| Percipio Bearer             | bearer          | The Percipio API uses a Bearer token for authentication. This should be the unique bearer token created for you to use. The Skillsoft Team will provide this information.                                                                                                                                                                                                |
| Return assets updated since | updatedsice     | This ISO8601 date time (yyyy-mm-ddTHH:MM:SSZ) is passed to the API to determine the assets to return based on when they were updated on Percipio. For example to get all the assets since December 20th 2021 at 10:00:00 UTC you would enter 2022-12-20T10:00:00Z. If left blank all items are returned. After successful sync the value is set to the time of the sync. |
| Max assets per request      | max             | The Percipio API returns a paged data set of assets. This controls the number returned for each page. The default and maximum is 1000.                                                                                                                                                                                                                                   |
| Parent Category             | category        | When the assets are added to Moodle, sub-categories will be created automatically. This configures the parent category to create them under.                                                                                                                                                                                                                             |
| Course thumbnail            | coursethumbnail | The Percipio asset contains a URL to a thumbnail image. This setting controls whether this thumbnail is downloaded and added to the Moodle Course. If this is not selected the time taken to sync a lot of assets is reduced.                                                                                                                                            |
| Mustache teamplate          | templatefile    | The Percipio asset is formatted using a Mustache template to produce the course and external content description in HTML, and the HTML for the _content_ property. There is a default template configured in `templates/content.mustache`, you can copy and edit this template and then upload it here to change the HTML produced.                                      |

The scheduled task is configured to run at 03am every day.

## How it works

When the task runs the process is:

1. Send Percipio API request using **updatedSince** value.
1. Percipio API will return a JSON response with **max** assets, and headers to indicate total assets to be returned.
1. Plugin will process the returned JSON array of assets and take following actions for each:

   1. Process the asset metadata using the selected Mustache template, either default of the custom uploaded to create a Course and External Content Activity.

   1. Check Category for the asset is not null.

      1. Category exists under the Parent Category configured, skip.
      1. Category does not exist under the Parent Category configured, create a new category under the parent.

   1. Lookup up Course using Moodle Course `ID Number` matches xapiActivityID from Percipio
      1. Course does not exist create it
      1. Course exists and the properties in Moodle are the same as from Percipio, skip.
      1. Course exists and the properties in Moodle are not the same as from Percipio, update the course overwriting values in Moodle.
         1. If the Percipio assets is no longer active the course is hidden.
      1. If the category is not empty, move the course to the category. If it is empty move the course to the parent category.
   1. Lookup Moodle External Content Activity where activity `ID Number` matches xapiActivityID from Percipio
      1. External Content Activity does not exist create it
      1. External Content Activity exists and the properties in Moodle are the same as from Percipio, skip.
      1. External Content Activity exists and the properties in Moodle are not the same as from Percipio, update the activity overwriting values in Moodle.
   1. Check Course Thumbnail, if **coursethumbail** setting is enabled.
      1. Course does not have thumbnail image, add the thumbnail URL and Moodle will download the image.
      1. Course has thumbnail image, and the thumbnail URL in Moodle is the same as from Percipio, skip.
      1. Course has thumbnail image, and the thumbnail URL in Moodle is not the same as from Percipio add the new thumbnail URL and Moodle will download the image.

1. If the JSON response indicates there are more assets to download go back to Step 2.

**IMPORTANT: The first time the task runs it can take a number of hours, as Percipio can have upwards of 40,000 assets.**

## More Details

For more information for how Percipio asset data is mapped to Course and External Content Activities see the [MAPPING](MAPPING.md)

## Mustache Template

The `templates/content.mustache` is used to populate:

- Course and External Content Descriptions - the template is passed `showthumbnail=false` and `showlaunch=false`
- External Content Content - the template is passed `showthumbnail=true` and `showlaunch=true`, this ensures a hyperlinked image and launch button are included.

## License

2019-2022 LushOnline

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program. If not, see <http://www.gnu.org/licenses/>.
