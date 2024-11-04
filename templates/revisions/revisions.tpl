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
                    v-if="!isOnFirstStep"
                    :is-warnable="true"
                    @click="previousStep"
                >
                    {translate key="common.back"}
                </pkp-button>
            </template>
            <div class="previewButton">
                <span
                    role="status"
                    aria-live="polite"
                    class="submissionWizard__lastSaved"
                    :class="isDisconnected ? 'submissionWizard__lastSaved--disconnected' : ''"
                >
                    <spinner v-if="isAutosaving || isDisconnected"></spinner>

                    <template v-if="isAutosaving">
                        {translate key="common.saving"}
                    </template>

                    <template v-else-if="isDisconnected">
                        {translate key="common.reconnecting"}
                    </template>

                    <template v-else-if="lastAutosavedMessage">
                        {{ lastAutosavedMessage }}
                    </template>
                </span>
                <pkp-button
                    :is-disabled="isDisconnected"
                    @click="saveForLater"
                >
                    {translate key="reviewer.submission.saveReviewForLater"}
                </pkp-button>
                <pkp-button element="a" :is-primary="true" href="">
                    Submit
                </pkp-button>
            </div>
        </button-row>
    </div>
{/block}
