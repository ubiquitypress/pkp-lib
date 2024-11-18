{extends file="layouts/backend.tpl"}

{block name="page"}
    <div>
        <div class="pkpHeader -isOneLine pkpWorkflow__header">
            {{ submission.id }}
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
            Upload revisions
        </h1>

        <panel>
            <panel-section>
                <template #header>
                    <legend class="pkpFormGroup__legend">Upload revisions</legend>
                    <div class="pkpFormGroup__description">
                        Prepare your revision files to be assessed by our editorial team. Upload revised versions of your original submission files where appropriate, as well as any new files that may have been requested.
                    </div>
                </template>

                <submission-files-list-panel 
                    v-bind="components.submissionFiles" 
                    @set="set">
                </submission-files-list-panel>
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
            <pkp-button
                element="a"
                href="{$workflowUrl|escape}"
            >
                {translate key="reviewer.submission.saveReviewForLater"}
            </pkp-button>
            <pkp-button
                :is-primary="true"
                :is-disabled="isOnLastStep && !canSubmit"
                @click="nextStep"
            >
                <template v-if="isOnLastStep">
                    {translate key="form.submit"}
                </template>
                <template v-else>
                    {translate key="common.continue"}
                </template>
            </pkp-button>
        </button-row>
    </div>
{/block}
