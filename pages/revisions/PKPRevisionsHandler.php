<?php

/**
 * @file pages/submission/PKPSubmissionHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionHandler
 *
 * @ingroup pages_submission
 *
 * @brief Handles page requests to the submission wizard
 */

namespace PKP\pages\revisions;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\publication\Publication;
use APP\section\Section;
use APP\submission\Submission;
use APP\template\TemplateManager;
use Illuminate\Support\LazyCollection;
use PKP\components\forms\FormComponent;
use PKP\components\forms\publication\PKPCitationsForm;
use PKP\components\forms\publication\TitleAbstractForm;
use PKP\components\forms\submission\CommentsForTheEditors;
use PKP\components\forms\submission\ConfirmSubmission;
use PKP\components\forms\submission\PKPSubmissionFileForm;
use PKP\components\listPanels\ContributorsListPanel;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRequiredPolicy;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\GenreDAO;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;

abstract class PKPRevisionsHandler extends Handler
{
    public const SECTION_TYPE_CONFIRM = 'confirm';
    public const SECTION_TYPE_CONTRIBUTORS = 'contributors';
    public const SECTION_TYPE_FILES = 'files';
    public const SECTION_TYPE_FORM = 'form';
    public const SECTION_TYPE_TEMPLATE = 'template';
    public const SECTION_TYPE_REVIEW = 'review';

    public $_isBackendPage = true;

    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [
                Role::ROLE_ID_AUTHOR,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SITE_ADMIN,
            ],
            [
                'index',
                'saved',
            ]
        );
    }

    /**
     * @param Request $request
     */
    public function authorize($request, &$args, $roleAssignments): bool
    {
        $submissionId = (int) $request->getUserVar('submissionId');

        // Creating a new submission
        if ($submissionId === 0) {
            $this->addPolicy(new UserRequiredPolicy($request));
            $this->markRoleAssignmentsChecked();
        } else {
            $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments, 'submissionId'));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Route the request to the correct page based
     * on whether they are starting a new submission,
     * working on a submission in progress, or viewing
     * a submission that has been submitted.
     *
     * @param array $args
     * @param Request $request
     */
    public function index($args, $request): void
    {
        $submissionId = $request->getUserVar('submissionId');
        $roundId = $request->getUserVar('roundId');

        $this->setupTemplate($request);

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $this->view($roundId, $request, $submission);
    }

    /**
     * Display the submission wizard
     */
    protected function view(int $roundId, Request $request, Submission $submission): void
    {
        $context = $request->getContext();

        /** @var Publication $publication */
        $publication = $submission->getCurrentPublication();

        $supportedLocales = $context->getSupportedSubmissionMetadataLocaleNames() + $submission->getPublicationLanguageNames();
        $formLocales = collect($supportedLocales)
            ->map(fn (string $name, string $locale) => ['key' => $locale, 'label' => $name])
            ->sortBy('key')
            ->values()
            ->toArray();

        // Order locales with submission locale first
        $orderedLocales = $supportedLocales;
        uksort($orderedLocales, fn ($a, $b) => $b === $submission->getData('locale') ? 1 : -1);

        $userGroups = Repo::userGroup()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->getMany();

        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getEnabledByContextId($context->getId())->toArray();

        $submissionFilesListPanel = $this->getSubmissionFilesListPanel($request, $submission, $genres, $roundId);

        $currentWorkflowUrl = Application::get()->getRequest()->url(
            $context->getPath(),
            'dashboard',
            'editorial',
            null,
            [
                'currentViewId' => $currentViewId,
                'workflowSubmissionId' => $submission->getId()
            ]
        );

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->setState([
            //'categories' => Repo::category()->getBreadcrumbs($categories),
            'components' => [
                $submissionFilesListPanel['id'] => $submissionFilesListPanel,
            ],
            'i18nConfirmSubmit' => $this->getConfirmSubmitMessage($submission, $context),
            'i18nDiscardChanges' => __('common.discardChanges'),
            'i18nDisconnected' => __('common.disconnected'),
            'i18nLastAutosaved' => __('common.lastSaved'),
            'i18nPageTitle' => __('submission.wizard.titleWithStep'),
            'i18nSubmit' => __('form.submit'),
            'i18nTitleSeparator' => __('common.titleSeparator'),
            'i18nUnableToSave' => __('submission.wizard.unableToSave'),
            'i18nUnsavedChanges' => __('common.unsavedChanges'),
            'i18nUnsavedChangesMessage' => __('common.unsavedChangesMessage'),
            'submissionSavedUrl' => $this->getSubmissionSavedUrl($request, $submission->getId()),
            'submissionWizardUrl' => $currentWorkflowUrl,
            'submitApiUrl' => $this->getSubmitApiUrl($request, $submission->getId()),
            'ReviewRoundId' => $roundId,
        ]);

        $templateMgr->assign([
            'isCategoriesEnabled' => $context->getData('submitWithCategories') && $categories->count(),
            'locales' => $orderedLocales,
            'pageComponent' => 'SubmissionWizardPage',
            'pageTitle' => __('submission.wizard.title'),
            'submission' => $submission,
            'workflowUrl' => $currentWorkflowUrl,
        ]);

        $revisionsFilesSubmitted = $publication->getData('revisionsFilesSubmitted');

        if ($revisionsFilesSubmitted > 0) {
            $templateMgr->display('/workflow/revisions-files.tpl');
        } else {
            $templateMgr->display('revisions/revisions.tpl');
        }
    }

    /**
     * Display the submission completed screen
     */
    protected function complete(array $args, Request $request, Submission $submission): void
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'pageTitle' => __('submission.submit.submissionComplete'),
            'pageWidth' => TemplateManager::PAGE_WIDTH_NARROW,
            'submission' => $submission,
            'workflowUrl' => $this->getWorkflowUrl($submission, $request->getUser()),
        ]);
        $templateMgr->display('submission/complete.tpl');
    }

    /**
     * Display the saved for later screen
     */
    public function saved(array $args, Request $request): void
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        if (!$submission) {
            $request->getDispatcher()->handle404();
        }

        $this->setupTemplate($request);

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'email' => $request->getUser()->getEmail(),
            'pageTitle' => __('submission.wizard.saved'),
            'pageWidth' => TemplateManager::PAGE_WIDTH_NARROW,
            'submission' => $submission,
            'submissionWizardUrl' => Repo::submission()->getUrlSubmissionWizard($request->getContext(), $submission->getId()),
        ]);
        $templateMgr->display('submission/saved.tpl');
    }

    /**
     * Get all steps of the submission wizard
     */
    protected function getSteps(Request $request, Submission $submission, Publication $publication, array $locales, array $sections, LazyCollection $categories): array
    {
        $publicationApiUrl = $this->getPublicationApiUrl($request, $submission->getId(), $publication->getId());
        $controlledVocabUrl = $this->getControlledVocabBaseUrl($request, $submission->getId());

        $steps = [];
        $steps[] = $this->getDetailsStep($request, $submission, $publication, $locales, $publicationApiUrl, $sections, $controlledVocabUrl);
        $steps[] = $this->getFilesStep($request, $submission, $publication, $locales, $publicationApiUrl);

        return $steps;
    }

    /**
     * Get the url to the API endpoint to submit this submission
     */
    protected function getSubmitApiUrl(Request $request, int $submissionId): string
    {
        return $request
            ->getDispatcher()
            ->url(
                $request,
                Application::ROUTE_API,
                $request->getContext()->getPath(),
                'submissions/' . $submissionId . '/submitRevisions'
            );
    }

    /**
     * Get the url to the publication's API endpoint
     */
    protected function getPublicationApiUrl(Request $request, int $submissionId, int $publicationId): string
    {
        return $request
            ->getDispatcher()
            ->url(
                $request,
                Application::ROUTE_API,
                $request->getContext()->getPath(),
                'submissions/' . $submissionId . '/publications/' . $publicationId
            );
    }

    /**
     * Get the URL to the page that shows the submission
     * has been saved
     */
    protected function getSubmissionSavedUrl(Request $request, int $submissionId): string
    {
        return $request
            ->getDispatcher()
            ->url(
                $request,
                Application::ROUTE_PAGE,
                $request->getContext()->getPath(),
                'submission',
                'saved',
                null,
                [
                    'id' => $submissionId,
                ]
            );
    }

    /**
     * Get the url to the submission's files API endpoint
     */
    protected function getSubmissionFilesApiUrl(Request $request, int $submissionId): string
    {
        return $request
            ->getDispatcher()
            ->url(
                $request,
                Application::ROUTE_API,
                $request->getContext()->getPath(),
                'submissions/' . $submissionId . '/files'
            );
    }

    /**
     * Get the base url to the controlled vocab suggestions API endpoint
     *
     * The entry `__vocab__` will be replaced with the user's search phrase.
     */
    protected function getControlledVocabBaseUrl(Request $request, int $submissionId): string
    {
        return $request->getDispatcher()->url(
            $request,
            Application::ROUTE_API,
            $request->getContext()->getData('urlPath'),
            'vocabs',
            null,
            null,
            ['vocab' => '__vocab__', 'submissionId' => $submissionId]
        );
    }

    /**
     * Get the state needed for the SubmissionFilesListPanel component
     */
    protected function getSubmissionFilesListPanel(Request $request, Submission $submission, array $genres, int $roundId): array
    {
        $stageId = SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION;
        $submissionFiles = Repo::submissionFile()
            ->getCollector()
            ->filterBySubmissionIds([$submission->getId()])
            ->filterByReviewRoundIds([$roundId])
            ->filterByFileStages([$stageId])->getMany();

        $form = new PKPSubmissionFileForm(
            $this->getSubmissionFilesApiUrl($request, $submission->getId()),
            $genres
        );
        return [
            'addFileLabel' => __('common.addFile'),
            'apiUrl' => $this->getSubmissionFilesApiUrl($request, $submission->getId()),
            'cancelUploadLabel' => __('form.dropzone.dictCancelUpload'),
            'genrePromptLabel' => __('submission.submit.genre.label'),
            'emptyLabel' => __('submission.upload.instructions'),
            'emptyAddLabel' => __('common.upload.addFile'),
            'fileStage' => SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION,
            'form' => $form->getConfig(),
            'genres' => array_map(
                fn ($genre) => [
                    'id' => (int) $genre->getId(),
                    'name' => $genre->getLocalizedName(),
                    'isPrimary' => !$genre->getSupplementary() && !$genre->getDependent(),
                ],
                $genres
            ),
            'id' => 'submissionFiles',
            'items' => Repo::submissionFile()
                ->getSchemaMap()
                ->summarizeMany($submissionFiles, $genres)
                ->values(),
            'options' => [
                'maxFilesize' => Application::getIntMaxFileMBs(),
                'timeout' => ini_get('max_execution_time') ? ini_get('max_execution_time') * 1000 : 0,
                'dropzoneDictDefaultMessage' => __('form.dropzone.dictDefaultMessage'),
                'dropzoneDictFallbackMessage' => __('form.dropzone.dictFallbackMessage'),
                'dropzoneDictFallbackText' => __('form.dropzone.dictFallbackText'),
                'dropzoneDictFileTooBig' => __('form.dropzone.dictFileTooBig'),
                'dropzoneDictInvalidFileType' => __('form.dropzone.dictInvalidFileType'),
                'dropzoneDictResponseError' => __('form.dropzone.dictResponseError'),
                'dropzoneDictCancelUpload' => __('form.dropzone.dictCancelUpload'),
                'dropzoneDictUploadCanceled' => __('form.dropzone.dictUploadCanceled'),
                'dropzoneDictCancelUploadConfirmation' => __('form.dropzone.dictCancelUploadConfirmation'),
                'dropzoneDictRemoveFile' => __('form.dropzone.dictRemoveFile'),
                'dropzoneDictMaxFilesExceeded' => __('form.dropzone.dictMaxFilesExceeded'),
            ],
            'otherLabel' => __('about.other'),
            'primaryLocale' => $submission->getData('locale'),
            'removeConfirmLabel' => __('submission.submit.removeConfirm'),
            'stageId' => WORKFLOW_STAGE_ID_SUBMISSION,
            'title' => __('editor.submission.revisions'),
            'uploadProgressLabel' => __('submission.upload.percentComplete'),
            'assocType' => Application::ASSOC_TYPE_REVIEW_ROUND,  // Adding assocType
            'assocId' => $roundId,  // Adding assocId (review round ID)
        ];
    }

    /**
     * Get an instance of the ContributorsListPanel component
     */
    protected function getContributorsListPanel(Request $request, Submission $submission, Publication $publication, array $locales): ContributorsListPanel
    {
        return new ContributorsListPanel(
            'contributors',
            __('publication.contributors'),
            $submission,
            $request->getContext(),
            $locales,
            [], // Populated by publication state
            true
        );
    }

    /**
     * Get the user groups that a user can submit in
     */
    protected function getSubmitUserGroups(Context $context, User $user): LazyCollection
    {
        $userGroups = Repo::userGroup()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByUserIds([$user->getId()])
            ->filterByRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_AUTHOR])
            ->getMany();

        // Users without a submitting role can submit as an
        // author role that allows self registration
        if (!$userGroups->count()) {
            $defaultUserGroup = Repo::userGroup()->getFirstSubmitAsAuthorUserGroup($context->getId());
            return LazyCollection::make(function () use ($defaultUserGroup) {
                if ($defaultUserGroup) {
                    yield $defaultUserGroup->getId() => $defaultUserGroup;
                }
            });
        }

        return $userGroups;
    }

    /**
     * Get the state for the files step
     */
    protected function getFilesStep(Request $request, Submission $submission, Publication $publication, array $locales, string $publicationApiUrl): array
    {
        return [
            'id' => 'files',
            'name' => __('submission.upload.uploadFiles'),
            'reviewName' => __('submission.files'),
            'sections' => [
                [
                    'id' => 'files',
                    'name' => __('submission.upload.uploadFiles'),
                    'type' => self::SECTION_TYPE_FILES,
                    'description' => $request->getContext()->getLocalizedData('uploadFilesHelp'),
                ],
            ],
            'reviewTemplate' => '/workflow/revisions-files.tpl',
        ];
    }

    /**
     * Get the state for the contributors step
     */
    protected function getContributorsStep(Request $request, Submission $submission, Publication $publication, array $locales, string $publicationApiUrl): array
    {
        return [
            'id' => 'contributors',
            'name' => __('publication.contributors'),
            'reviewName' => __('publication.contributors'),
            'sections' => [
                [
                    'id' => 'contributors',
                    'name' => __('publication.contributors'),
                    'type' => self::SECTION_TYPE_CONTRIBUTORS,
                    'description' => $request->getContext()->getLocalizedData('contributorsHelp'),
                ],
            ],
            'reviewTemplate' => '/submission/review-contributors.tpl',
        ];
    }

    /**
     * Submit the revisions files
     */
    protected function Submit(int $publicationId, int $roundId)
    {
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $settingName = 'revisionsSubmited';
        $settingValue = $roundId;
        if ($publication) {
            // Add or update the custom setting for the publication
            $publicationDao->updateSetting($publicationId, $settingName, $settingValue, '');
        }
    }

    /**
     * Get the state for the details step
     */
    protected function getDetailsStep(Request $request, Submission $submission, Publication $publication, array $locales, string $publicationApiUrl, array $sections, string $controlledVocabUrl): array
    {
        $titleAbstractForm = $this->getDetailsForm(
            $publicationApiUrl,
            $locales,
            $publication,
            $request->getContext(),
            $sections,
            $controlledVocabUrl
        );
        $this->removeButtonFromForm($titleAbstractForm);

        $sections = [
            [
                'id' => $titleAbstractForm->id,
                'name' => __('submission.details'),
                'type' => self::SECTION_TYPE_FORM,
                'description' => $request->getContext()->getLocalizedData('detailsHelp'),
                'form' => $this->getLocalizedForm($titleAbstractForm, $submission->getData('locale'), $locales),
            ],
        ];

        if (in_array($request->getContext()->getData('citations'), [Context::METADATA_REQUEST, Context::METADATA_REQUIRE])) {
            $citationsForm = new PKPCitationsForm(
                $publicationApiUrl,
                $publication,
                $request->getContext()->getData('citations') === Context::METADATA_REQUIRE
            );
            $this->removeButtonFromForm($citationsForm);
            $sections[] = [
                'id' => $citationsForm->id,
                'name' => '',
                'type' => self::SECTION_TYPE_FORM,
                'description' => '',
                'form' => $citationsForm->getConfig(),
            ];
        }

        return [
            'id' => 'details',
            'name' => __('common.details'),
            'reviewName' => __('common.details'),
            'sections' => $sections,
            'reviewTemplate' => '/submission/review-details.tpl',
        ];
    }

    /**
     * Get the state for the For the Editors step
     *
     * If no metadata is enabled during submission, the metadata
     * form is not shown.
     */
    protected function getEditorsStep(Request $request, Submission $submission, Publication $publication, array $locales, string $publicationApiUrl, LazyCollection $categories): array
    {
        $metadataForm = $this->getForTheEditorsForm(
            $publicationApiUrl,
            $locales,
            $publication,
            $submission,
            $request->getContext(),
            $request->getDispatcher()->url(
                $request,
                Application::ROUTE_API,
                $request->getContext()->getData('urlPath'),
                'vocabs',
                null,
                null,
                ['vocab' => '__vocab__', 'submissionId' => $submission->getId()]
            ),
            $categories
        );
        $this->removeButtonFromForm($metadataForm);

        $commentsForm = new CommentsForTheEditors(
            Repo::submission()->getUrlApi($request->getContext(), $submission->getId()),
            $submission
        );
        $this->removeButtonFromForm($commentsForm);

        $hasMetadataForm = count($metadataForm->fields);

        $metadataFormData = $this->getLocalizedForm($metadataForm, $submission->getData('locale'), $locales);
        $commentsFormData = $this->getLocalizedForm($commentsForm, $submission->getData('locale'), $locales);

        $sections = [
            [
                'id' => $hasMetadataForm ? $metadataForm->id : $commentsForm->id,
                'name' => __('submission.forTheEditors'),
                'type' => self::SECTION_TYPE_FORM,
                'description' => $request->getContext()->getLocalizedData('forTheEditorsHelp'),
                'form' => $hasMetadataForm ? $metadataFormData : $commentsFormData,
            ],
        ];

        if ($hasMetadataForm) {
            $sections[] = [
                'id' => $commentsForm->id,
                'name' => '',
                'type' => self::SECTION_TYPE_FORM,
                'description' => '',
                'form' => $commentsFormData,
            ];
        }

        return [
            'id' => 'editors',
            'name' => __('submission.forTheEditors'),
            'reviewName' => __('submission.forTheEditors'),
            'sections' => $sections,
            'reviewTemplate' => '/submission/review-editors.tpl',
        ];
    }

    /**
     * Get the state for the Confirm step
     */
    protected function getConfirmStep(Request $request, Submission $submission, Publication $publication, array $locales, string $publicationApiUrl): array
    {
        $sections = [
            [
                'id' => 'review',
                'name' => __('submission.reviewAndSubmit'),
                'type' => self::SECTION_TYPE_REVIEW,
                'description' => $request->getContext()->getLocalizedData('reviewHelp'),
            ]
        ];

        $confirmForm = new ConfirmSubmission(
            FormComponent::ACTION_EMIT,
            $request->getContext()
        );

        if (!empty($confirmForm->fields)) {
            $this->removeButtonFromForm($confirmForm);
            $sections[] = [
                'id' => $confirmForm->id,
                'name' => __('author.submit.confirmation'),
                'type' => self::SECTION_TYPE_CONFIRM,
                'description' => '<p>' . __('submission.wizard.confirm') . '</p>',
                'form' => $confirmForm->getConfig(),
            ];
        }

        return [
            'id' => 'review',
            'name' => __('submission.review'),
            'sections' => $sections,
        ];
    }

    /**
     * A helper function to remove the save button forms in the wizard
     *
     * This creates a default group/page for each form and assigns each #
     * field and group to that page.
     */
    protected function removeButtonFromForm(FormComponent $form): void
    {
        $form->addPage([
            'id' => 'default',
        ])
            ->addGroup([
                'id' => 'default',
                'pageId' => 'default'
            ]);

        foreach ($form->fields as $field) {
            $field->groupId = 'default';
        }
    }

    /**
     * Get details about the steps that are required by the smarty template
     */
    protected function getReviewStepsForSmarty(array $steps): array
    {
        $reviewSteps = [];
        foreach ($steps as $step) {
            if ($step['id'] === 'review') {
                continue;
            }
            $reviewSteps[] = [
                'id' => $step['id'],
                'reviewTemplate' => $step['reviewTemplate'],
                'reviewName' => $step['reviewName'],
            ];
        }
        return $reviewSteps;
    }

    /**
     * Show an error page
     */
    protected function showErrorPage(string $titleLocaleKey, string $message): void
    {
        $this->_isBackendPage = false;
        $templateMgr = TemplateManager::getManager(Application::get()->getRequest());
        $templateMgr->assign([
            'pageTitle' => $titleLocaleKey,
            'messageTranslated' => $message,
        ]);
        $templateMgr->display('frontend/pages/message.tpl');
    }

    /**
     * Get the appropriate workflow URL for the current user
     *
     * Returns the author dashboard if the user has an author assignment
     * and the editorial workflow if not.
     */
    protected function getWorkflowUrl(Submission $submission, User $user): string
    {
        $request = Application::get()->getRequest();

        // Replaces StageAssignmentDAO::getBySubmissionAndRoleIds
        $hasStageAssignments = StageAssignment::withSubmissionIds([$submission->getId()])
            ->withRoleIds([Role::ROLE_ID_AUTHOR])
            ->withStageIds([WORKFLOW_STAGE_ID_SUBMISSION])
            ->withUserId($user->getId())
            ->exists();

        if ($hasStageAssignments) {
            return Repo::submission()->getUrlAuthorWorkflow($request->getContext(), $submission->getId());
        }

        return Repo::submission()->getUrlEditorialWorkflow($request->getContext(), $submission->getId());
    }

    /**
     * Get the sections that this user can submit to
     */
    protected function getSubmitSections(Context $context): array
    {
        $allSections = Repo::section()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->excludeInactive()
            ->getMany();

        $submitSections = [];
        /** @var Section $section */
        foreach ($allSections as $section) {
            if ($section->getEditorRestricted() && !$this->isEditor()) {
                continue;
            }
            $submitSections[] = $section;
        }

        return $submitSections;
    }

    /**
     * Get the "are you sure?" message shown to the user
     * before they complete their submission
     */
    protected function getConfirmSubmitMessage(Submission $submission, Context $context): string
    {
        return 'Are you sure you want to submit these revisions? The editors will be notified that your revisions are ready for review';
        //return __('revisions.confirmSubmit');
    }

    /**
     * Is the current user an editor
     */
    protected function isEditor(): bool
    {
        return !empty(
            array_intersect(
                Section::getEditorRestrictedRoles(),
                $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES)
            )
        );
    }

    /**
     * Get the form configuration data with the correct
     * locale settings based on the submission's locale
     *
     * Uses the submission locale as the primary and
     * visible locale, and puts that locale first in the
     * list of supported and publication's locales.
     *
     * Call this instead of $form->getConfig() to display
     * a form with the correct submission locales
     */
    protected function getLocalizedForm(FormComponent $form, string $submissionLocale, array $locales): array
    {
        $config = $form->getConfig();

        $config['primaryLocale'] = $submissionLocale;
        $config['visibleLocales'] = [$submissionLocale];
        $config['supportedFormLocales'] = collect($locales)
            ->sortBy([fn (array $a, array $b) => $b['key'] === $submissionLocale ? 1 : -1])
            ->values()
            ->toArray();

        return $config;
    }

    /**
     * Get the form for entering the title/abstract details
     */
    abstract protected function getDetailsForm(string $publicationApiUrl, array $locales, Publication $publication, Context $context, array $sections, string $suggestionUrlBase): TitleAbstractForm;

}
