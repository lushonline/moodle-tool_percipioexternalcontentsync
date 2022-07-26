# tool_percipioexternalcontentsync

When Percipio Assets are retrieved the properties need to be mapped to Moodle Categories, Courses and External Content Activities.

Details on the properties can be found in the Skillsoft OpenAPI definition [CatalogContent Schema](https://api.percipio.com/content-discovery/api-docs/)

The work to do this is in [classes/helper.php](classes/helper.php) and using the Mustache Template configured. The default template is [templates/content.mustache](templates/content.mustache)

The details below summarise how the Percipio metadata is mapped.

You can change the values used for the Course `summary`, and External Content `intro` and `content` by copying and creating a new mustache file that you upload in the Moodle Settings screen.

## Table of Contents

- [Category](#category)
- [Course](#course)
- [External Content](#external-content)
- [Mustache Template](#mustache-template)

## Category

<br>

| Moodle Property | Comment                                                                                                                                                                                                                                                                                                           | Example                                    |
| --------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------ |
| idnumber        | This is a composite value that is the UUID for the first channel association `associations.channels[0].id` for an asset, or the id if the Percipio type is a channel or journey `id` Followed by an \_ and then the primary locale code `localeCodes[0]`                                                          | 64b58d31-161a-11e7-bf96-7fcd66e560cd_en-US |
| name            | This is a composite value that is the title for the first channel association `associations.channels[0].title` for an asset, or the title in the primary locale if the Percipio type is a channel or journey `localizedMetadata[0].title` Followed by the primary locale code `localeCodes[0]` in square brackets | Microsoft Dynamics Administration [en-US]  |

<br>

## Course

<br>

| Moodle Property | Comment                                                                                                                                                     | Example                                                                                                                                                                                      |
| --------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| idnumber        | This is the xapiActivityId for the asset, this is so that the LRS functionality of the External Content activity can be used.                               | https://xapi.percipio.com/xapi/lc/ffb5d5a6-90f1-434e-8120-4a31e6a61e90                                                                                                                       |
| shortname       | This is a composite value that is the title in the primary locale shortened to 215 characters `localizedMetadata[0].title` and the ``id` in round brackets. | Cutover strategy for Dynamics 365 solutions (ffb5d5a6-90f1-434e-8120-4a31e6a61e90)                                                                                                           |
| longname        | This is the title in the primary locale shortened to 255 characters `localizedMetadata[0].title`,                                                           | Cutover strategy for Dynamics 365 solutions                                                                                                                                                  |
| summary         | This is the output of the Mustache template when `showthumbnail=false` and `showlaunch=false`                                                               | You can view the output of the built-in template in Moodle using the [Developer Tools - Template Library](https://docs.moodle.org/400/en/Template_library)                                   |
| visible         | This is the value of `lifecycle.status`, if true this is 1 or if false it is 0                                                                              | 1                                                                                                                                                                                            |
| thumbnail       | This is used to add the thumbnail image to the course if the `coursethumnail` setting is enabled. The `imageUrl`                                            | `https://cdn2.percipio.com/public/c/linked-content/images/saved/fe9a490b-f223-43ab-8326-029e1a13107b/134361c7-9ac0-461a-aa92-863058dfd01a/modality/134361c7-9ac0-461a-aa92-863058dfd01a.jpg` |
| tags            | This is an array of tags generated by the `get_percio_asset_tags($asset)` helper function                                                                   | `array('TAG1', 'TAG2', 'TAG3'`                                                                                                                                                               |

**HELPER FUNCTION**: `get_percio_asset_tags($asset)`
This function returns an array of tags, that are 50 characters or less to suit Moodle.

This includes if defined:

- The content type: `contentType.displayLabel`
- If the Percipio type is a channel or journey: `localizedMetadata[0].title`
- The keywords: `keywords`
- The areas from associations: `associations.areas`
- The subjects from associations: `associations.subjects`
  <br>
  <br>

## External Content

<br>

| Moodle Property        | Comment                                                                                                                       | Example                                                                                                                                                    |
| ---------------------- | ----------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| idnumber               | This is the xapiActivityId for the asset, this is so that the LRS functionality of the External Content activity can be used. | https://xapi.percipio.com/xapi/lc/ffb5d5a6-90f1-434e-8120-4a31e6a61e90                                                                                     |
| name                   | This is the title in the primary locale shortened to 255 characters `localizedMetadata[0].title`,                             | Cutover strategy for Dynamics 365 solutions                                                                                                                |
| intro                  | This is the output of the Mustache template when `showthumbnail=false` and `showlaunch=false`                                 | You can view the output of the built-in template in Moodle using the [Developer Tools - Template Library](https://docs.moodle.org/400/en/Template_library) |
| content                | This is the output of the Mustache template when `showthumbnail=true` and `showlaunch=true`                                   | You can view the output of the built-in template in Moodle using the [Developer Tools - Template Library](https://docs.moodle.org/400/en/Template_library) |
| markcompleteexternally | This is set to 1 for all types except Channels as channels do not generate a completion.                                      | 1                                                                                                                                                          |

<br>
<br>

# Mustache Template

<br>

## Calculated Fields

As well as passing Percipio JSON to the template, a number of extra data values are calculated that can be used:

| Mustache Data              | Comment                                                                                                                                                                                                                                                                                               | Example Value |
| -------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------- |
| percipioformatted.duration | Convert the ISO8601 duration to hh:mm:ss. An example Percipio `duration` is PT01H04M32S                                                                                                                                                                                                               | 01:04:32      |
| percipioformatted.language | Convert the RFC5646 locale to human readable values of language. An example Percipio `localeCodes[0]` is en_US                                                                                                                                                                                        | English       |
| percipioformatted.region   | Convert the RFC5646 locale to human readable values of region. An example Percipio `localeCodes[0]` is en_US                                                                                                                                                                                          | United States |
| showthumbnail              | Boolean value to indicate we should display a thumbnail in the description                                                                                                                                                                                                                            |               |
| showlaunch                 | Boolean value to indicate we should display a Launch button in the description                                                                                                                                                                                                                        |               |
| hasby                      | Boolean value to indicate the `by` array has elements so we can conditionally show the data. This is work around for this limitation [Test for non-empty array in Mustache templates](https://docs.moodle.org/dev/Templates#Test_for_non-empty_array_in_Mustache_templates)                           |               |
| hasobjectives              | Boolean value to indicate the `learningObjectives` array has non-empty elements so we can conditionally show the data. This is work around for this limitation [Test for non-empty array in Mustache templates](https://docs.moodle.org/dev/Templates#Test_for_non-empty_array_in_Mustache_templates) |               |

<br>

## Example Customisation

The below simple example shows how to include the `expertiseLevels` and `modalities`. To make it obvious these values are showing in their own row div.

Alsoas there are no strings defined for these, we have to set thetext for the labels, versus using the [str helper](https://docs.moodle.org/dev/Templates#str)

```
{{!
    This file is part of Moodle - https://moodle.org/

    Moodle is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Moodle is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
}}
{{!
    @template tool_percipioexternalcontentsync/content

    Formats the Percipio retrieved metadata for the content property.

    Classes required for JS:
    * none

    Data attributes required for JS:
    * none

    Context variables required for this template:
    * percipio - [object] Percipio metadata object
    * percipioformatted - [object] Percipio metadata formatted properties.
    * showthumbnail - [bool] Whether to show the thumbnail in description which is link to content.
    * showlaunch - [bool] Whether to show the launch button in description which is link to content.
    * hasby - [bool] Helper allow simpler processing, true if percipio.by not empty array.
    * hasobjectives - [bool] Helper allow simpler processing, true if percipio.learningObjectives not empty array.

    Example context (json):
    {
        "percipio": {
            "id": "0008d154-d70c-4d71-9aef-9b0a89162da6",
            "code": "it_feemds_04_enus",
            "xapiActivityId": "https://xapi.percipio.com/xapi/course/0008d154-d70c-4d71-9aef-9b0a89162da6",
            "xapiActivityTypeId": "http://adlnet.gov/expapi/activities/course",
            "characteristics": {
                "earnsBadge": true,
                "hasAssessment": true
            },
            "contentType": {
                "percipioType": "COURSE",
                "category": "COURSE",
                "displayLabel": "Course",
                "source": null
            },
            "localeCodes": ["en-US"],
            "localizedMetadata": [{
                    "localeCode": "en-US",
                    "title": "Final Exam: Advanced Math",
                    "description": "Final Exam: Advanced Math will test your knowledge and application of the topics presented throughout the Advanced Math track of the Skillsoft Aspire Essential Math for Data Science Journey."
                }
            ],
            "lifecycle": {
                "status": "ACTIVE",
                "publishDate": "2021-12-22T15:16:27Z",
                "lastUpdatedDate": "2021-12-22T15:16:27Z",
                "plannedRetirementDate": "2023-12-22T15:16:27Z",
                "retiredDate": null
            },
            "link": "https://share.percipio.com/cd/REDACTED",
            "aiccLaunch": {
                "url": "https://api.percipio.com/content-integration/v1/aicc/launch",
                "params": "contentId=0008d154-d70c-4d71-9aef-9b0a89162da6&entitlementKey=REDACTED&contentType=course"
            },
            "imageUrl": "https://cdn2.percipio.com/public/b/00acfa9d-27c6-4345-8697-6ea816f496a4/image002/modality/image002.jpg",
            "alternateImageUrl": "https://cdn2.percipio.com/public/b/00acfa9d-27c6-4345-8697-6ea816f496a4/image002.jpg",
            "keywords": [
                "Keyword1",
                "Keyword2"
            ],
            "duration": "PT1H4M32S",
            "by": ["Author1", "Author2"],
            "publication": {
                "copyrightYear": 2017,
                "isbn": "9780814436042",
                "publisher": "Skillsoft"
            },
            "credentials": {
                "cpeCredits": null,
                "nasbaReady": false,
                "pduCredits": null
            },
            "expertiseLevels": ["INTERMEDIATE"],
            "modalities": ["WATCH"],
            "technologies": [{
                    "title": "Math",
                    "version": ""
                }
            ],
            "associations": {
                "areas": ["Aspire Journeys for Technology & Developer"],
                "subjects": ["Data / ML / AI"],
                "channels": [],
                "parent": null,
                "translationGroupId": null,
                "journeys": [{
                        "id": "a6fffedb-2b07-4926-b34f-3a8e84f04a1f",
                        "title": "Essential Math for Data Science",
                        "link": "https://share.percipio.com/cd/REDACTED"
                    }
                ],
                "collections": [
                    "ITDevSkills",
                    "TechDeveloperNP"
                ]
            },
            "learningObjectives": [
                "recall the use of matrix operations to represent linear transformations",
                "define eigenvectors and eigenvalues", "define principal components and their uses",
                "recall the intuition behind principal component analysis",
                "define eigenvalues and eigenvectors",
                "mathematically compute principal components",
                "compute eigenvalues and eigenvectors",
                "perform principal component analysis",
                "build a baseline model using logistic regression",
                "build a logistic regression model using principal components",
                "summarize the use cases of recommendation systems and the different techniques applied to build such models, with emphasis on the content-based filtering approach",
                "describe the use cases of recommendation systems and the different techniques applied to build such models, with emphasis on the content-based filtering approach",
                "summarize the intuition behind collaborative filtering, its main advantages, and how ratings matrices, the nearest neighbor approach, and latent factor analysis are involved",
                "describe the intuition behind collaborative filtering, its main advantages, and how ratings matrices, the nearest neighbor approach, and latent factor analysis are involved",
                "decompose a ratings matrix into its latent factors",
                "apply gradient descent to compute the factors of a ratings matrix",
                "compute a penalty for a large number of latent factors when computing the factors of a ratings matrix",
                "use NumPy and Pandas to define a ratings matrix that can be fed into a recommendation system",
                "implement the gradient descent algorithm to decompose a ratings matrix",
                "compute the predicted ratings given by users for various items by using matrix decomposition"
            ]
        },
        "percipioformatted": {
            "duration": "01:04:32",
            "language": "English",
            "region": "United States"
        },
        "showthumbnail": true,
        "showlaunch": true,
        "hasby": true,
        "hasobjectives": true
    }
}}

<div id="{{percipio.id}}-percipio-content" class="container-fluid">
    <div class="row p-1">
        {{#showthumbnail}}
        <div class="col-6">
            <a href="{{percipio.link}}" target="_blank"><img src="{{percipio.imageUrl}}" alt="{{percipio.localizedMetadata.0.title}}" class="img-fluid mw-100"></a>
        </div>
        {{/showthumbnail}}
        <div class="col">
            <div><strong>{{#str}} template_type, tool_percipioexternalcontentsync {{/str}}: </strong>{{percipio.contentType.displayLabel}}</div>
            {{#percipioformatted.language}}<div><strong>{{#str}} template_locale, tool_percipioexternalcontentsync {{/str}}: </strong>{{percipioformatted.language}}{{#percipioformatted.region}} ({{percipioformatted.region}}){{/percipioformatted.region}}</div>{{/percipioformatted.language}}
            {{#percipioformatted.duration}}<div><strong>{{#str}} template_duration, tool_percipioexternalcontentsync {{/str}}: </strong>{{percipioformatted.duration}}</div>{{/percipioformatted.duration}}
            {{#hasby}}<div><strong>{{#str}} template_by, tool_percipioexternalcontentsync {{/str}}: </strong>{{#percipio.by}}{{.}}, {{/percipio.by}}</div>{{/hasby}}
            {{#percipio.publication}}<div><strong>{{#str}} template_publisher, tool_percipioexternalcontentsync {{/str}}: </strong>{{publisher}}{{#copyrightYear}} {{.}}{{/copyrightYear}}</div>{{/percipio.publication}}
            {{#percipio.publication.isbn}}<div><strong>{{#str}} template_isbn, tool_percipioexternalcontentsync {{/str}}: </strong>{{.}}</div>{{/percipio.publication.isbn}}
            {{#percipio.lifecycle}}<div><strong>{{#str}} template_updateddate, tool_percipioexternalcontentsync {{/str}}: </strong>{{lastUpdatedDate}}</div>{{/percipio.lifecycle}}
            {{^percipio.lifecycle.retiredDate}}{{#percipio.lifecycle.plannedRetirementDate}}<div><strong>{{#str}} template_retirementdate, tool_percipioexternalcontentsync {{/str}}: </strong>{{.}}</div>{{/percipio.lifecycle.plannedRetirementDate}}{{/percipio.lifecycle.retiredDate}}
            {{#percipio.lifecycle.retiredDate}}<div><strong>{{#str}} template_retireddate, tool_percipioexternalcontentsync {{/str}}: </strong>{{.}}</div>{{/percipio.lifecycle.retiredDate}}
        </div>
    </div>
    <div class="row p-1" id="this-is-our-added-data>
        <div class="col">
            <div class="pt-1"><strong>Modalities</strong></div>
            <div>
              <ul>
              {{#percipio.modalities}}<li>{{.}}</li>{{/percipio.modalities}}
              </ul>
            </div>
        </div>
        <div class="col">
            <div class="pt-1"><strong>Expertise Levels</strong></div>
            <div>
              <ul>
              {{#percipio.expertiseLevels}}<li>{{.}}</li>{{/percipio.expertiseLevels}}
              </ul>
            </div>
        </div>
    </div>
    <div class="row p-1">
        <div class="col">
            <div class="pt-1"><strong>{{#str}} template_description, tool_percipioexternalcontentsync {{/str}}</strong></div>
            <div>{{percipio.localizedMetadata.0.description}}</div>
            {{#hasobjectives}}
            <div class="pt-1"><strong>{{#str}} template_objectives, tool_percipioexternalcontentsync {{/str}}</strong></div>
            <div>
              <ul>
              {{#percipio.learningObjectives}}<li>{{.}}</li>{{/percipio.learningObjectives}}
              </ul>
            </div>
            {{/hasobjectives}}
        </div>
    </div>
     {{#showlaunch}}
    <div class="row p-1">
        <div class="col">
            <div><a href="{{percipio.link}}" target="_blank" class="btn btn-primary">{{#str}} template_launch, tool_percipioexternalcontentsync {{/str}}</a></div>
        </div>
    </div>
    {{/showlaunch}}
</div>

```
