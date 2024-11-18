{**
 * templates/workflow/revisions-files.tpl
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The template in the workflow for revision files after they have been submitted.
 *}

{extends file="layouts/backend.tpl"}

{block name="page"}
    <div>
        <div class="pkpHeader -isOneLine pkpWorkflow__header">
            {{ submission.aid }}
            <template v-if="publication.authorsStringShort">
                <span class="app__breadcrumbsSeparator" aria-hidden="true">/</span>
                {{ publication.authorsStringShort }}
            </template>
            <template v-if="localize(publication.title)">
                <span class="app__breadcrumbsSeparator" aria-hidden="true">/</span>
                <span v-html="localize(publication.title)">
            </template>
        </div>

        <h1 class="app__pageHeading" ref="pageTitle">
            Revisions uploaded
        </h1>

        <panel>
            <panel-section>
                <template #header>
                    <legend class="pkpFormGroup__legend">Revisions Uploaded</legend>
                    <div class="pkpFormGroup__description">
                        Your revised files have been submitted. The editor has been notified and will contact you once they have been processed.
                        <br/><br/>
                        Should you need to change the files or contact the editor regarding the revisions process, please use the Discussions function on the previous page.
                    </div>
                </template>

                <div class="submissionWizard__reviewPanel">
                    <div class="submissionWizard__reviewPanel__header">
                        <h3 id="review{$step.id|escape}">
                            Revisions
                        </h3>
                    </div>
                    <div class="submissionWizard__reviewPanel__body submissionWizard__reviewPanel__body--{$step.id|escape}">
                        <ul class="submissionWizard__reviewPanel__list">
                            <li
                                v-for="file in components.submissionFiles.items"
                                :key="file.id"
                                class="submissionWizard__reviewPanel__item__value"
                            >
                                <a :href="file.url" class="submissionWizard__reviewPanel__fileLink">
                                    <file
                                        :document-type="file.documentType"
                                        :name="localize(file.name)"
                                    ></file>
                                </a>
                                <span class="submissionWizard__reviewPanel__list__actions">
                                    <badge v-if="file.genreId" :is-primary="!file.genreIsSupplementary">
                                        {{ localize(file.genreName) }}
                                    </badge>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </panel-section>
        </panel>
        <button-row class="submissionWizard__footer">
            <template #end>
                <pkp-button
                    element="a"
                    href="{$workflowUrl|escape}"
                >
                    {translate key="common.back"}
                </pkp-button>
            </template>
        </button-row>
    </div>
{/block}
