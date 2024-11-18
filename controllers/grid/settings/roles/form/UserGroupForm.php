<?php

/**
 * @file controllers/grid/settings/roles/form/UserGroupForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserGroupForm
 *
 * @ingroup controllers_grid_settings_roles_form
 *
 * @brief Form to add/edit user group.
 */

namespace PKP\controllers\grid\settings\roles\form;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\core\JSONMessage;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\form\Form;
use PKP\security\Role;
use PKP\security\RoleDAO;
use PKP\stageAssignment\StageAssignment;
use PKP\userGroup\relationships\UserGroupStage;
use PKP\workflow\WorkflowStageDAO;

class UserGroupForm extends Form
{
    /** @var int Id of the user group being edited */
    public $_userGroupId;

    /** @var int The context of the user group being edited */
    public $_contextId;


    /**
     * Constructor.
     *
     * @param int $contextId id.
     * @param int $userGroupId group id.
     */
    public function __construct(int $contextId, $userGroupId = null)
    {
        parent::__construct('controllers/grid/settings/roles/form/userGroupForm.tpl');
        $this->_contextId = $contextId;
        $this->_userGroupId = $userGroupId;

        // Validation checks for this form
        $this->addCheck(new \PKP\form\validation\FormValidatorLocale($this, 'name', 'required', 'settings.roles.nameRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorLocale($this, 'abbrev', 'required', 'settings.roles.abbrevRequired'));
        if ($this->getUserGroupId() == null) {
            $this->addCheck(new \PKP\form\validation\FormValidator($this, 'roleId', 'required', 'settings.roles.roleIdRequired'));
        }
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    //
    // Getters and Setters
    //
    /**
     * Get the user group id.
     *
     * @return int userGroupId
     */
    public function getUserGroupId()
    {
        return $this->_userGroupId;
    }

    /**
     * Get the context id.
     *
     * @return int contextId
     */
    public function getContextId()
    {
        return $this->_contextId;
    }

    //
    // Implement template methods from Form.
    //
    /**
     * Get all locale field names
     */
    public function getLocaleFieldNames(): array
    {
        return ['name', 'abbrev'];
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData()
    {
        $userGroup = Repo::userGroup()->get($this->getUserGroupId());
        $stages = WorkflowStageDAO::getWorkflowStageTranslationKeys();
        $this->setData('stages', $stages);
        $this->setData('assignedStages', []); // sensible default

        $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */
        $jsonMessage = new JSONMessage();
        $jsonMessage->setContent($roleDao->getForbiddenStages());
        $this->setData('roleForbiddenStagesJSON', $jsonMessage->getString());

        if ($userGroup) {
            $assignedStages = Repo::userGroup()->getAssignedStagesByUserGroupId($this->getContextId(), $userGroup->getId())->toArray();
            // Get a list of all settings-accessible user groups for the current user in
            // order to prevent them from locking themselves out by disabling the only one.
            $mySettingsAccessUserGroupIds = Repo::userGroup()->getCollector()
                ->filterByContextIds([$this->getContextId()])
                ->filterByUserIds([Application::get()->getRequest()->getUser()->getId()])
                ->getMany()
                ->filter(fn ($userGroup) => $userGroup->getPermitSettings())
                ->map(fn ($userGroup) => $userGroup->getId())
                ->toArray();

            $data = [
                'userGroupId' => $userGroup->getId(),
                'roleId' => $userGroup->getRoleId(),
                'name' => $userGroup->getName(null), //Localized
                'abbrev' => $userGroup->getAbbrev(null), //Localized
                'assignedStages' => $assignedStages,
                'showTitle' => $userGroup->getShowTitle(),
                'mySettingsAccessUserGroupIds' => array_values($mySettingsAccessUserGroupIds),
                'permitSelfRegistration' => $userGroup->getPermitSelfRegistration(),
                'permitMetadataEdit' => $userGroup->getPermitMetadataEdit(),
                'permitSettings' => $userGroup->getPermitSettings(),
                'recommendOnly' => $userGroup->getRecommendOnly(),
                'masthead' => $userGroup->getMasthead(),
            ];

            foreach ($data as $field => $value) {
                $this->setData($field, $value);
            }
        }
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['roleId', 'name', 'abbrev', 'assignedStages', 'showTitle', 'permitSelfRegistration', 'recommendOnly', 'permitMetadataEdit', 'permitSettings', 'masthead']);
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);

        $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */
        $templateMgr->assign('roleOptions', Application::getRoleNames(true));

        // Users can't edit the role once user group is created.
        // userGroupId is 0 for new User Groups because it is cast to int in UserGroupGridHandler.
        $disableRoleSelect = ($this->getUserGroupId() > 0) ? true : false;
        $templateMgr->assign('disableRoleSelect', $disableRoleSelect);
        $templateMgr->assign('selfRegistrationRoleIds', $this->getPermitSelfRegistrationRoles());
        $templateMgr->assign('permitSettingsRoleIds', $this->getPermitSettingsRoles());
        $templateMgr->assign('recommendOnlyRoleIds', $this->getRecommendOnlyRoles());
        $templateMgr->assign('notChangeMetadataEditPermissionRoles', Repo::userGroup()::NOT_CHANGE_METADATA_EDIT_PERMISSION_ROLES);

        return parent::fetch($request, $template, $display);
    }

    /**
     * Get a list of roles optionally permitting user self-registration.
     */
    public function getPermitSelfRegistrationRoles(): array
    {
        return [Role::ROLE_ID_REVIEWER, Role::ROLE_ID_AUTHOR, Role::ROLE_ID_READER];
    }

    /**
     * Get a list of roles optionally permitting settings access.
     */
    public function getPermitSettingsRoles(): array
    {
        return [Role::ROLE_ID_MANAGER];
    }

    /**
     * Get a list of roles optionally permitting recommendOnly option.
     */
    public function getRecommendOnlyRoles(): array
    {
        return [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR];
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionParams)
    {
        parent::execute(...$functionParams);

        $request = Application::get()->getRequest();
        $userGroupId = $this->getUserGroupId();

        $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */

        // Check if we are editing an existing user group or creating another one.
        if ($userGroupId == null) {
            $userGroup = Repo::userGroup()->newDataObject();

            $roleId = $this->getData('roleId');
            if ($roleId == Role::ROLE_ID_SITE_ADMIN) {
                throw new \Exception('Site administrator roles cannot be created here.');
            }
            $userGroup->setRoleId($roleId);

            $userGroup->setContextId($this->getContextId());
            $userGroup->setDefault(false);
            $userGroup->setShowTitle(is_null($this->getData('showTitle')) ? false : $this->getData('showTitle'));
            $userGroup->setPermitSelfRegistration($this->getData('permitSelfRegistration') && in_array($userGroup->getRoleId(), $this->getPermitSelfRegistrationRoles()));
            $userGroup->setPermitMetadataEdit($this->getData('permitMetadataEdit') && !in_array($this->getData('roleId'), Repo::userGroup()::NOT_CHANGE_METADATA_EDIT_PERMISSION_ROLES));
            $userGroup->setPermitSettings($this->getData('permitSettings') && $userGroup->getRoleId() == Role::ROLE_ID_MANAGER);
            if (in_array($this->getData('roleId'), Repo::userGroup()::NOT_CHANGE_METADATA_EDIT_PERMISSION_ROLES)) {
                $userGroup->setPermitMetadataEdit(true);
            }

            $userGroup->setRecommendOnly($this->getData('recommendOnly') && in_array($userGroup->getRoleId(), $this->getRecommendOnlyRoles()));
            $userGroup = $this->_setUserGroupLocaleFields($userGroup, $request);
            $userGroup->setMasthead($this->getData('masthead') ?? false);
            $userGroupId = Repo::userGroup()->add($userGroup);
        } else {
            $userGroup = Repo::userGroup()->get($userGroupId);
            $userGroup = $this->_setUserGroupLocaleFields($userGroup, $request);
            $userGroup->setShowTitle(is_null($this->getData('showTitle')) ? false : $this->getData('showTitle'));
            $userGroup->setPermitSelfRegistration($this->getData('permitSelfRegistration') && in_array($userGroup->getRoleId(), $this->getPermitSelfRegistrationRoles()));
            $userGroup->setPermitMetadataEdit($this->getData('permitMetadataEdit') && !in_array($userGroup->getRoleId(), Repo::userGroup()::NOT_CHANGE_METADATA_EDIT_PERMISSION_ROLES));
            $userGroup->setPermitSettings($this->getData('permitSettings') && $userGroup->getRoleId() == Role::ROLE_ID_MANAGER);
            if (in_array($userGroup->getRoleId(), Repo::userGroup()::NOT_CHANGE_METADATA_EDIT_PERMISSION_ROLES)) {
                $userGroup->setPermitMetadataEdit(true);
            } else {
                $permitMetadataEdit = $userGroup->getPermitMetadataEdit();

                $stageAssignments = StageAssignment::withUserGroupId($userGroupId)
                    ->withContextId($this->getContextId())
                    ->get();

                foreach ($stageAssignments as $stageAssignment) {
                    $stageAssignment->update(['canChangeMetadata' => $permitMetadataEdit]);
                }
            }

            $userGroup->setRecommendOnly($this->getData('recommendOnly') && in_array($userGroup->getRoleId(), $this->getRecommendOnlyRoles()));
            $userGroup->setMasthead($this->getData('masthead') ?? false);
            Repo::userGroup()->edit($userGroup, []);
        }

        // After we have created/edited the user group, we assign/update its stages.
        $assignedStages = $this->getData('assignedStages');
        // Always set all stages active for some permission levels.
        if (in_array($userGroup->getRoleId(), $roleDao->getAlwaysActiveStages())) {
            $assignedStages = array_keys(WorkflowStageDAO::getWorkflowStageTranslationKeys());
        }
        if ($assignedStages) {
            $this->_assignStagesToUserGroup($userGroupId, $assignedStages);
        }
    }


    //
    // Private helper methods
    //
    /**
     * Setup the stages assignments to a user group in database.
     *
     * @param int $userGroupId User group id that will receive the stages.
     * @param array $userAssignedStages of stages currently assigned to a user.
     */
    public function _assignStagesToUserGroup($userGroupId, $userAssignedStages)
    {
        $contextId = $this->getContextId();

        // Current existing workflow stages.
        $stages = WorkflowStageDAO::getWorkflowStageTranslationKeys();

        foreach (array_keys($stages) as $stageId) {
            Repo::userGroup()->removeGroupFromStage($contextId, $userGroupId, $stageId);
        }

        foreach ($userAssignedStages as $stageId) {
            // Make sure we don't assign forbidden stages based on
            // user groups role id. Override in case of some permission levels.
            $roleId = $this->getData('roleId');
            $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */
            $forbiddenStages = $roleDao->getForbiddenStages($roleId);
            if (in_array($stageId, $forbiddenStages) && !in_array($roleId, $roleDao->getAlwaysActiveStages())) {
                continue;
            }

            // Check if is a valid stage.
            if (in_array($stageId, array_keys($stages))) {
                UserGroupStage::create([
                    'contextId' => $contextId,
                    'userGroupId' => $userGroupId,
                    'stageId' => $stageId
                ]);
            } else {
                throw new \Exception('Invalid stage id');
            }
        }
    }

    /**
     * Set locale fields on a User Group object.
     *
     * @param \PKP\userGroup\UserGroup $userGroup
     * @param Request $request
     *
     */
    public function _setUserGroupLocaleFields($userGroup, $request): \PKP\userGroup\UserGroup
    {
        $router = $request->getRouter();
        $context = $router->getContext($request);
        $supportedLocales = $context->getSupportedLocaleNames();

        if (!empty($supportedLocales)) {
            foreach ($context->getSupportedLocaleNames() as $localeKey => $localeName) {
                $name = $this->getData('name');
                $abbrev = $this->getData('abbrev');
                if (isset($name[$localeKey])) {
                    $userGroup->setName($name[$localeKey], $localeKey);
                }
                if (isset($abbrev[$localeKey])) {
                    $userGroup->setAbbrev($abbrev[$localeKey], $localeKey);
                }
            }
        } else {
            $localeKey = Locale::getLocale();
            $userGroup->setName($this->getData('name'), $localeKey);
            $userGroup->setAbbrev($this->getData('abbrev'), $localeKey);
        }

        return $userGroup;
    }
}
