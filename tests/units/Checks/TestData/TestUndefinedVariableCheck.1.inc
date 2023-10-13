<?php
//TODO: this file will likely go away. This is me trying to reproduce the undefined variable in use statements when the variable is from the IOC container

class EmployeeMasterController {

	/**
	 * @var EmployeeMasterService
	 */
	protected $employeeMasterService;

	/** @var QuickActionDropDownHelper */
	private static $quickActionDropDown;

	/**
	 * @var CompanyService
	 */
	protected $companyService;

	/**
	 * EmployeeMasterController constructor.
	 * @param EmployeeMasterService $employeeMasterService
	 * @param CompanyService        $companyService
	 */
	public function __construct(EmployeeMasterService $employeeMasterService, CompanyService $companyService) {
		$this->employeeMasterService = $employeeMasterService;
		$this->companyService = $companyService;
	}


	/**
	 * @param array $data
	 * @return void
	 */
	public function setupLooping(array &$data) {
		// should be handled in the controller
		// set vars for employee_looping
		if ($_GET['clearLooping'] == 'true') {
			unset($_SESSION['employee_looping_source']);
			unset($_SESSION['employee_looping_ids']);
		}
		$hasEmployeeLooping = is_array($_SESSION['employee_looping_ids']);
		$hasMoreThanOneEmployee = ($hasEmployeeLooping && count($_SESSION['employee_looping_ids']) > 1);/* there are employee ids stored in the session */
		$hasEmployeeInLoop = $hasEmployeeLooping ? in_array($data['id'], $_SESSION['employee_looping_ids']) : false;/* the employee we're looking at is in the array */
		$data['looping'] = new \stdClass();
		$data['looping']->canLoop = ($hasEmployeeLooping && $hasMoreThanOneEmployee && $hasEmployeeInLoop);
		if (isset($_SESSION['employee_looping_source']['reportKey'])) {
			$reportKey = $_SESSION['employee_looping_source']['reportKey'];
		} else {
			$reportKey = '';
		}
		$data['loopingSource'] = getEmployeeLoopingSource($_SESSION['employee_looping_source']['id'], $_SESSION['employee_looping_source']['queryString'], $_SESSION['employee_looping_source']['name'], $reportKey);
		$data['looping']->isReportLoop = $data['loopingSource']['isReportLoop'];
		$data['looping']->prevEmpId = getPrevEmployeeId($data['id']);
		$data['looping']->nextEmpId = getNextEmployeeId($data['id']);
		$data['looping']->loopTotalCount = $hasEmployeeLooping ? count($_SESSION['employee_looping_ids']) : 0;
		$data['looping']->currentLoopedEmployeeCount = __("%d of %d ", intval(getCurrentLoopedEmployeeCount($data['id'])), $data['looping']->loopTotalCount);
		$data['header']['bodyClass'] = " content-tight-top employeeSection";
	}

	/**
	 * @param array    $data
	 * @param int|null $employeeId
	 * @return string|null
	 * @throws \Exception
	 */
	public function setupHeaderVars(array &$data, ?int $employeeId) {
		$companyDate = $this->companyService->getCompanyCurrentLocalDate()->toSql();
		$redirect = $this->employeeMasterService->setupVars($data, $employeeId, $companyDate);
		$this->setupEmployeeInfoColumnAndDropDown($data);
		$this->setupLooping($data);
		return $redirect;
	}

	/**
	 * @param array $data
	 * @return void
	 * @throws \Exception
	 */
	public function setupEmployeeInfoColumnAndDropDown(array &$data) {
		if ($data['employee']) {

			$data['quickActionDropDown'] = self::quickActionDropDownSetup($data['employee']);
			if (!isset($data['info_column'])) {
				// this is for the info column on the left
				$data['info_column'] = app(InfoColumn::class, ["employee" => $data['employee']]);
			}
		}
	}
	/**
	 * @param \Employee $employee
	 * @return mixed
	 * @throws \Exception
	 */
	public static function quickActionDropDownSetup(\Employee $employee) {
		if (!self::$quickActionDropDown) {
			$helper = app(QuickActionDropDownHelper::class);
			self::$quickActionDropDown = Timer::wrap("QuickActionDropDown->getVars()", [], function() use ($helper, $employee) {
				return $helper->getVars($employee);
			});
		}
		return self::$quickActionDropDown;
	}
}

function app($make = null, $parameters = []) {
	$domainName = App::getDomain();
	if (empty($domainName) && !empty($_SERVER['HTTP_HOST'])) {
		$domainName = $_SERVER['HTTP_HOST'];
		require_once "bootstrap/app.php";
		buildApp();
	}
	if (empty($domainName)) {
		throw new Exception('The Core App does not have an instance for that domain');
	}
	if (is_null($make)) {
		return App::getInstance($domainName);
	}


	$ret = null;
	try {
		$instance = Timer::getSingleton();
		$instance->start("app", ["class"=>$make]);
		$ret = App::getInstance($domainName)->make($make, $parameters);
	}
	finally {
		$instance->stop();
	}
	return $ret;
}

class Timer {
	/**
	 * @var TimerInterface
	 */
	protected static $instance = null;

	/**
	 * @return TimerInterface
	 */
	public static function getInstance() {
		if (!self::$instance instanceof TimerInterface) {
			self::$instance = new NullTimer();
		}
		return self::$instance;
	}

	/**
	 * @param TimerInterface $timer Instance of TimerInterface
	 * @return void
	 */
	public static function registerTimer(TimerInterface $timer) {
		self::$instance = $timer;
	}

	/**
	 * @param string   $name       The name of the span
	 * @param array    $attributes Any attributes to tag it with
	 * @param callable $method     A call back that you want to time.
	 * @return mixed
	 */
	public static function wrap(string $name, array $attributes, callable $method) {
		$timer = self::getInstance();
		try {
			$timer->start($name, $attributes);
			$retValue = call_user_func($method);
		} finally {
			$timer->stop();
		}
		return $retValue;
	}
}


use BambooHR\Silo\Company\CompanyApplicationService;
use BambooHR\Common\DataObjects\NamedFieldAccessPermissionDataObject;
use BambooHR\Common\DataObjects\Payroll\EmployeePayScheduleDataObject;
use BambooHR\Common\DataObjects\ResourcePermissionDataObject;
use BambooHR\Common\DataObjects\UserDataObject;
use BambooHR\Common\Definitions\AppDefinitions;
use BambooHR\Common\Definitions\FieldMetaDefinitions;
use BambooHR\Common\Definitions\PermissionActionDefinitions;
use BambooHR\Common\Definitions\ResourceAccessDefinitions;
use BambooHR\Common\Definitions\ResourceTypeDefinitions;
use BambooHR\Common\Definitions\ToolDefinitions;
use BambooHR\Model\Service\Esignature\PermissionChecker;
use BambooHR\Repository\Cache\CompanyEphemeralCache;
use BambooHR\Silo\Approval\Service\CustomApprovalService;
use BambooHR\Silo\Benefits\BenefitsAdministrationService;
use BambooHR\Silo\Benefits\BenefitsConfigurationService;
use BambooHR\Silo\EmployeeTab\EmployeeTabService;
use BambooHR\Silo\MultiFactorAuthentication\Service\MultiFactorAuthenticationService;
use BambooHR\Silo\MultiFactorAuthentication\Shared\MultiFactorAuthenticationStatusTypeDefinitions;
use BambooHR\Silo\Payroll\PayrollService;
use BambooHR\Silo\PaySchedule\PayScheduleApplicationService;
use BambooHR\Silo\Permission\PermissionApplicationService;
use BambooHR\Silo\Permission\PermissionMetaApplicationService;
use BambooHR\Silo\ReleaseToggle\Common\Definition\ReleaseToggleDefinitions;
use BambooHR\Silo\ReleaseToggle\ReleaseToggleService;
use BambooHR\Silo\TimeTracking\Shared\DataObject\Timesheet\EmployeeEnabledData;
use BambooHR\Silo\TimeTracking\Shared\Helper\TimeTrackingToggleHelper;
use BambooHR\Silo\TimeTracking\TimesheetEmployeeTabHelperApplicationService;
use BambooHR\Silo\User\UserApplicationService;
use BambooHR\Utility\DateImmutable;
use BambooHR\Utility\Toggle\PrehireAccessLevelToggleReader;
use CompanyMaster;
use DateTimeImmutable;
use DateTimeZone;
use DBTable;
use Employee;
use InvalidIdException;
use stdClass;
use Throwable;
use User;

class QuickActionDropDownHelper {
	/**
	 * @param CompanyApplicationService                    $companyApplicationService
	 * @param CompanyMaster                                $companyMaster
	 * @param PermissionApplicationService                 $permissionApplicationService
	 * @param EmployeeTabService                           $employeeTabService
	 * @param PayScheduleApplicationService                $payScheduleApplicationService
	 * @param PayrollService                               $payrollService
	 * @param PermissionChecker                            $permissionChecker
	 * @param UserApplicationService                       $userApplicationService
	 * @param PermissionMetaApplicationService             $permissionMetaApplicationService
	 * @param CustomApprovalService                        $customApprovalService
	 * @param TimesheetEmployeeTabHelperApplicationService $timesheetEmployeeTabHelperApplicationService
	 * @param CompanyEphemeralCache                        $companyEphemeralCache
	 * @param MultiFactorAuthenticationService             $multiFactorAuthenticationService
	 * @param BenefitsConfigurationService                 $benefitsConfigurationService
	 * @param BenefitsAdministrationService                $benefitsAdministrationService
	 * @param ReleaseToggleService                         $releaseToggleService
	 */
	public function __construct(
		private ?CompanyApplicationService                    $companyApplicationService = null,
		private ?CompanyMaster                                $companyMaster = null,
		private ?PermissionApplicationService                 $permissionApplicationService = null,
		private ?EmployeeTabService                           $employeeTabService = null,
		private ?PayScheduleApplicationService                $payScheduleApplicationService = null,
		private ?PayrollService                               $payrollService = null,
		private ?PermissionChecker                            $permissionChecker = null,
		private ?UserApplicationService                       $userApplicationService = null,
		private ?PermissionMetaApplicationService             $permissionMetaApplicationService = null,
		private ?CustomApprovalService                        $customApprovalService = null,
		private ?TimesheetEmployeeTabHelperApplicationService $timesheetEmployeeTabHelperApplicationService = null,
		private ?CompanyEphemeralCache                        $companyEphemeralCache = null,
		private ?MultiFactorAuthenticationService             $multiFactorAuthenticationService = null,
		private ?BenefitsConfigurationService                 $benefitsConfigurationService = null,
		private ?BenefitsAdministrationService                $benefitsAdministrationService = null,
		private ?ReleaseToggleService                         $releaseToggleService = null,
	) {
	}

	/**
	 * @param string $toolDefinition A constant identifier for the tool
	 * @param int    $employeeId     The employee id to check with regard to.
	 *
	 * @return bool
	 */
	private function hasToolAndPermission(string $toolDefinition, int $employeeId): bool {
		$res = new ResourcePermissionDataObject();
		$res->resourceType = ResourceTypeDefinitions::TYPE_TOOL;
		$res->resourceAccessLevel = ResourceAccessDefinitions::VIEW;
		$res->resourceDefinition = $toolDefinition;

		if ($employeeId != -1) {
			$res->employeeId = $employeeId;
			$resource = $this->permissionApplicationService->hasAccessToResourceForEmployeeId($res);
		} else {
			$resource = $this->permissionApplicationService->hasAccessToResource($res);
		}

		return $this->companyApplicationService->companyHasTool($toolDefinition) && $resource;
	}

	/**
	 * Setup dropdown for actions
	 * on 6-8-16 changed this function to include ALL variables needed for add_links.html.twig
	 *
	 * @param Employee $employee The employee currently being viewed. May be a new employee.
	 *
	 * @return mixed
	 */
	function getVars(Employee $employee) {
		$key = 'employee_' . $employee->id;
		$cacheResult = $this->companyEphemeralCache->get($key, self::class);
		if ($cacheResult) {
			return $cacheResult;
		}

		try {
			$employeeUser = $this->userApplicationService->getUserDataByEmployeeId($employee->id);
		} catch (InvalidIdException $ex) {
			$employeeUser = new UserDataObject();
		}
		$userIsOwnerAdmin = $this->permissionApplicationService->isOwnerAdmin();
		$userIsOwner = $this->permissionApplicationService->isOwner();
		$employeeUserIsOwner = $this->companyApplicationService->getOwnerUser()->id == $employeeUser->id;

		$isUser = ($employeeUser->id > 0);

		list($companyUserGroups, $userGroupsByName, $quickActionUserGroups) = $this->loadGroups($employeeUser);

		//checking for users with access to the site
		$showChangePassword = true;
		//treat hidden users the same as if the employee had no user
		if ($employeeUser->status == User::STATUS_DISABLED) {
			$showChangePassword = false;
		}
		if ($employeeUser->status == User::STATUS_HIDDEN) {
			$isOnboardingUser = $employeeUser->type != User::getOnboardingTypeConstant();
			if (PrehireAccessLevelToggleReader::isToggleEnabled()) {
				$isOnboardingUser = $this->permissionApplicationService->canPerformAction(PermissionActionDefinitions::ACTION_CAN_COMPLETE_NHP);
			}
			if ($isOnboardingUser) {
				$showChangePassword = false;
			}
			$employeeUser = new UserDataObject();
			$userGroupsByName = [];
			$quickActionUserGroups = [];
		}

		if (count($this->permissionMetaApplicationService->getUserGroupsByUserId($employeeUser->id)->items) == 0) {
			$showChangePassword = false;
		}

		if (!$isUser) {
			$showChangePassword = false;
		}

		$disabledAccess = $isUser && $employeeUser->status != User::STATUS_ENABLED && count($userGroupsByName) !== 0;

		$res = new ResourcePermissionDataObject();
		$res->resourceType = ResourceTypeDefinitions::TYPE_APP;
		$res->resourceAccessLevel = ResourceAccessDefinitions::VIEW;
		$res->resourceDefinition = AppDefinitions::APP_RIGHTSIGNATURE;

		$rightSignatureAccess = false;
		if ($this->companyApplicationService->companyHasApp(AppDefinitions::APP_RIGHTSIGNATURE)) {
			if ($employee->id) {
				$res->employeeId = $employee->id;
				$rightSignatureAccess = $this->permissionApplicationService->hasAccessToResourceForEmployeeId($res);
			} else {
				$rightSignatureAccess = $this->permissionApplicationService->hasAccessToResource($res);
			}
		}

		$printFormsAccess = $this->hasToolAndPermission(ToolDefinitions::TOOL_PRINT_FORMS, -1);
		$canSeeTopSection = $printFormsAccess || $rightSignatureAccess;
		$hasPermAddUser = $this->permissionApplicationService->canPerformAction(PermissionActionDefinitions::ACTION_ADD_USER);
		$canChangeAccess = $this->canChangeAccess($employee, intval($employeeUser->id), $hasPermAddUser, $employeeUserIsOwner);

		$payrollStatus = $this->payrollService->getEmployeePayrollStatus($employee->id);

		//we have to check canTerminateEmployee here AND check for PERM_TERM_EMPLOYEE. Because canTerminateEmployee won't check PERM_TERM_EMPLOYEE if the term/rehire flag is off
		$yesterday = new DateTimeImmutable('yesterday', new DateTimeZone($this->companyApplicationService->getCompany()->timezone));
		//Checking terminated status for yesterday so they still have the reset password link on their termination date
		$isEmployeeTerminatedYesterday = $this->employeeTabService->isEmployeeTerminated($employee, $yesterday);
		$isEmployeeTerminatedToday = $this->employeeTabService->isEmployeeTerminatedNow($employee);
		$isNotRecentlyTerminated = !$isEmployeeTerminatedYesterday || !$isEmployeeTerminatedToday;
		$canTerminateEmployee = !$isEmployeeTerminatedToday && $this->employeeTabService->canTerminateEmployee($employee) && $this->permissionApplicationService->canPerformActionForEmployeeId(PermissionActionDefinitions::ACTION_TERM_EMPLOYEE, $employee->id);
		$canRehireEmployee = $this->employeeTabService->canRehireEmployeeFromModal($employee);
		$isEmployeeFutureRehire = $this->employeeTabService->isEmployeeFutureRehire($employee->id);

		$canDeleteEmployee = $userIsOwner && !$employeeUserIsOwner;
		$canSeePreviewAsThisUser = $this->canShowPreviewAsUser($userIsOwnerAdmin, $employee, $employeeUserIsOwner, $disabledAccess);

		$isOnboardingUser = $employeeUser->type == User::getOnboardingTypeConstant();
		if (PrehireAccessLevelToggleReader::isToggleEnabled()) {
			$isOnboardingUser = $this->permissionApplicationService->canPerformAction(PermissionActionDefinitions::ACTION_CAN_COMPLETE_NHP);
		}
		$eSignatureAccess = $this->permissionChecker->check() &&
			(
				($employee->status == Employee::STATUS_ACTIVE && $employeeUser->status == User::STATUS_ENABLED) ||
				($isOnboardingUser && ($employeeUser->status == User::STATUS_ENABLED || $employeeUser->status == User::STATUS_HIDDEN))
			);

		$customApprovals = [];
		$canSeeCustomApprovals = false;
		if ($this->companyApplicationService->companyHasTool(ToolDefinitions::TOOL_CUSTOM_APPROVALS)) {
			$customApprovals = $this->customApprovalService->getCustomWorkflows($employee->id);
			$canSeeCustomApprovals = count($customApprovals) > 0 && $this->permissionApplicationService->isNotSelfService();
		}

		//Check for Time Tracking permissions
		$isTimeTrackingEnabled = $this->timesheetEmployeeTabHelperApplicationService->isTimeTrackingEnabled();
		$canToggleTimeTracking = ($isTimeTrackingEnabled && $this->permissionApplicationService->canPerformAction(PermissionActionDefinitions::ACTION_TOGGLE_TIME_TRACKING) && $employee->isActive() && $isUser && $employeeUser->status === User::STATUS_ENABLED);

		$canSeeAddLinks = $canSeePreviewAsThisUser || $canSeeTopSection || $canChangeAccess || $canTerminateEmployee || $canRehireEmployee || $canDeleteEmployee || $eSignatureAccess || $canToggleTimeTracking;

		if (
			$this->multiFactorAuthenticationService->isMfaSetup() &&
			$employeeUser->id != null
		) {
			try {
				$userStatus = $this->multiFactorAuthenticationService->getMfaUserStatus($employeeUser->id);
				$hasMfaLogin = $userStatus == MultiFactorAuthenticationStatusTypeDefinitions::LOGIN_REQUIRED;
				$twoStepEnabled = $this->permissionApplicationService->isAdmin() && $hasMfaLogin;
			} catch (Throwable $exception) {
			}
		}

		$quickActionDropDown = new stdClass();
		$quickActionDropDown->twoStepEnabled = $twoStepEnabled;
		$quickActionDropDown->canSeeAddLinks = $canSeeAddLinks;
		$quickActionDropDown->canAccessPayroll = false;
		$quickActionDropDown->employeePayrollSynced = false;
		$quickActionDropDown->payrollStatus = $payrollStatus;
		$quickActionDropDown->rightSignatureAccess = $rightSignatureAccess;
		$quickActionDropDown->isAssumedUserId = $this->permissionApplicationService->isPreviewingAsUser();
		$quickActionDropDown->employee = $employee;
		$quickActionDropDown->eSignatureAccess = $eSignatureAccess;
		$quickActionDropDown->printFormsAccess = $printFormsAccess;
		$quickActionDropDown->canSeeTopSection = $canSeeTopSection;
		$quickActionDropDown->canSeeMiddleSection = $canChangeAccess || $canSeePreviewAsThisUser;
		$quickActionDropDown->canSeeBottomSection = $canTerminateEmployee || $canDeleteEmployee || $canRehireEmployee;
		$quickActionDropDown->canChangeAccess = $canChangeAccess;
		$quickActionDropDown->quickActionUserGroups = $quickActionUserGroups;
		$quickActionDropDown->userId = $employeeUser->id;
		$quickActionDropDown->isUserOwner = $employeeUserIsOwner;
		$quickActionDropDown->disabledAccess = $disabledAccess;
		$quickActionDropDown->userGroupsByName = $userGroupsByName;
		$quickActionDropDown->companyUserGroups = $companyUserGroups;
		$quickActionDropDown->hasPermAddUser = $hasPermAddUser;
		$quickActionDropDown->forms = $this->companyApplicationService->getCustomPdfForms();
		$quickActionDropDown->showPreviewAsUser = $canSeePreviewAsThisUser;

		$quickActionDropDown->showResetPassword = $userIsOwnerAdmin && ($isNotRecentlyTerminated || $isEmployeeFutureRehire) && $showChangePassword;
		$quickActionDropDown->isOwnerAdmin = $userIsOwnerAdmin;
		$quickActionDropDown->canTerminate = $canTerminateEmployee;
		$quickActionDropDown->canRehire = $canRehireEmployee;
		$quickActionDropDown->canDelete = $canDeleteEmployee;
		$quickActionDropDown->userEmail = ($employeeUser->employeeId ? $employee->getBestEmail() : $employeeUser->email);
		//these two vars are not used in add_links.html.twig, but in header.html.twig
		$quickActionDropDown->customApprovals = $customApprovals;
		$quickActionDropDown->canSeeCustomApprovals = $canSeeCustomApprovals;

		// Time Tracking access
		/** @var EmployeeEnabledData $employeeEnabledData */
		$employeeEnabledData = $this->timesheetEmployeeTabHelperApplicationService->getEmployeeEnabledData($employee->id)->first();
		$quickActionDropDown->canToggleTimeTracking = $canToggleTimeTracking;
		$quickActionDropDown->isPreviewing = $this->permissionApplicationService->isPreviewingAsUser();
		$quickActionDropDown->timeTrackingEnabled = $employeeEnabledData->isEnabled();
		$quickActionDropDown->timeTrackingEnabledOn = $employeeEnabledData->getStartsOnString();
		$quickActionDropDown->canChangeTimeTrackingGroup = $this->releaseToggleService->toggleIsEnabled(
				ReleaseToggleDefinitions::TIME_TRACKING_GROUPS,
				$this->companyApplicationService->getCompany()->id
			) &&
			$this->timesheetEmployeeTabHelperApplicationService->multipleTimeTrackingGroupsExist() && $employeeEnabledData->isEnabled();

		if (TimeTrackingToggleHelper::isFutureSchedulingEnabled($this->companyMaster)) {
			/** @var EmployeePayScheduleDataObject $employeePaySchedule */
			$employeePaySchedule = $this->payScheduleApplicationService->getCurrentOrFuturePaySchedulesByEmployeeIds($employee->id)->get($employee->id);
			$showNoPayScheduleModal = empty($employeePaySchedule->id);
			$today = DateImmutable::fromToday(new DateTimeZone($this->companyApplicationService->getCompany()->timezone));
			$onDate = (!is_null($employeePaySchedule) && !is_null($employeePaySchedule->effectiveDate)) ? DateImmutable::max($today, $employeePaySchedule->effectiveDate) : null;
			$overtimeModalData = $this->timesheetEmployeeTabHelperApplicationService->getOvertimeNoLocationOrStateData($employee->id, $onDate);
		} else {
			$showNoPayScheduleModal = empty($this->payrollService->getCurrentEmployeePositionHistoryItemByEmployeeId($employee->id)->payScheduleId);
			$overtimeModalData = $this->timesheetEmployeeTabHelperApplicationService->getOvertimeNoLocationOrStateData($employee->id);
		}
		$quickActionDropDown->showNoPayScheduleModal = $showNoPayScheduleModal;
		$quickActionDropDown->showNoLocationModal = $overtimeModalData->showNoLocationModal;
		$quickActionDropDown->showNoStateModal = $overtimeModalData->showNoStateModal;
		$quickActionDropDown->showNoStateRemoteModal = $overtimeModalData->showNoStateRemoteModal;
		$quickActionDropDown->showNonOvertimeCountryModal = $overtimeModalData->showNonOvertimeCountryModal;

		//Benefits
		$this->setCanToggleBenefitsAdministration($quickActionDropDown, $employee);
		$quickActionDropDown->isBenefitsBillingEnabled = $this->benefitsAdministrationService->isBenefitsBillingEnabled($employee->id);

		$this->companyEphemeralCache->set($key, $quickActionDropDown, self::class);

		return $quickActionDropDown;
	}

	/**
	 * @param stdClass $quickActionDropDown
	 * @param Employee $employee
	 *
	 * @return void
	 */
	private function setCanToggleBenefitsAdministration(stdClass $quickActionDropDown, Employee $employee): void {
		$quickActionDropDown->canToggleBenefitsAdministration = $this->benefitsConfigurationService->isBenefitsEligibilityBasedBillingToggleEnabled() ? false : $this->benefitsConfigurationService->hasFeatureBenefitsEmployeeEnrollment() && $this->benefitsConfigurationService->hasBenefitSettingsPermissions() && $employee->isActive();
	}

	/**
	 * @param bool     $userIsOwnerAdmin
	 * @param Employee $employee
	 * @param bool     $employeeUserIsOwner
	 * @param bool     $userHasDisabledAccess
	 *
	 * @return bool
	 */
	protected function canShowPreviewAsUser(bool $userIsOwnerAdmin, Employee $employee, bool $employeeUserIsOwner, bool $userHasDisabledAccess): bool {
		try {
			$inactiveEmployee = $employee->status == Employee::STATUS_INACTIVE;
			$employeeIsSameAsAssumedUser = $this->permissionApplicationService->getAssumedUser()->employeeId == $employee->id;

			return $userIsOwnerAdmin &&
				!$inactiveEmployee &&
				!$employeeIsSameAsAssumedUser &&
				!$employeeUserIsOwner &&
				!$userHasDisabledAccess;
		} catch (InvalidIdException $exception) {
			return false;
		}
	}

	/**
	 * can change access levels for this employee if:
	 * Employee's status is active
	 * they aren't editing the owner
	 * they are an admin or have add user perms
	 * they are CAL with manage ESS perms and they're editing an employee without access or in an ESS group
	 *
	 * @param Employee $employee            -
	 * @param int      $employeeUserId      -
	 * @param bool     $hasPermAddUser      -
	 * @param bool     $employeeUserIsOwner -
	 *
	 * @return bool
	 */
	protected function canChangeAccess(Employee $employee, int $employeeUserId, bool $hasPermAddUser, bool $employeeUserIsOwner) {
		if ($employeeUserIsOwner || $employee->status != Employee::STATUS_ACTIVE) {
			return false;
		}
		if ($this->permissionApplicationService->isAdmin() || $hasPermAddUser) {
			return true;
		} else { //not an admin, can't add users
			$requestObject = new NamedFieldAccessPermissionDataObject(
				[
					'fieldName' => FieldMetaDefinitions::USER_STATUS,
					'employeeId' => $employee->id,
					'levelName' => ResourceAccessDefinitions::VIEW_EDIT
				]
			);
			//is a CAL with manage ess perms && the employee is in an ess group (if they're a user)
			if (
				$this->permissionApplicationService->canAccessNamedFieldForEmployeeId($requestObject)
				&& $this->permissionApplicationService->isNotSelfService()
				&& (!$employeeUserId > 0 || $this->userApplicationService->inEssGroupOrDisabled($employeeUserId))
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param User $employeeUser
	 *
	 * @return array
	 */
	private function loadGroups($employeeUser): array {
		$companyUserGroupsFlatArray = $this->permissionMetaApplicationService->getAllGroupsOrdered()->items;
		$companyUserGroups = [];

		$allowAllGroups = $this->companyApplicationService->companyHasTool(ToolDefinitions::TOOL_CUSTOM_PERMISSION_GROUPS);

		// Do a little bit of cleanup on the object format.
		foreach ($companyUserGroupsFlatArray as $group) {
			if ($allowAllGroups || $group->type == 'admin' || ($group->type == 'self-service' && $group->assignable == 'yes')) {
				$companyUserGroups[$group->type][$group->id] = $this->snakeCaseKeys($group);
			}
		}
		$userGroupsByName = array();

		$quickActionUserGroups = [];

		if ($employeeUser->id) {
			$quickActionUserGroups = $this->permissionMetaApplicationService->getUserGroupsByUserId($employeeUser->id)->items;
			foreach ($companyUserGroupsFlatArray as $group1) {
				if (isset($quickActionUserGroups[$group1->id])) {
					$userGroupsByName[$group1->id] = $group1->name;
				}
			}
			$quickActionUserGroups = array_keys($quickActionUserGroups);
		}

		return array($companyUserGroups, $userGroupsByName, $quickActionUserGroups);
	}

	/**
	 * @param stdClass $group
	 *
	 * @return array
	 */
	private function snakeCaseKeys($group): array {
		$trueVars = [];
		foreach (get_object_vars($group) as $key => $value) {
			$key = DBTable::getDefaultColumnMapping($key);
			$trueVars[$key] = $value;
		}

		return $trueVars;
	}
}


