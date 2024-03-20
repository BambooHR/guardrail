<?php

class A {}

class B {}

function C(A | B $param): A | B {

}

class D {}

$a = new A();
C($a);
$b = new B();
C($b);
$d = new D();
C($d);


class TestClass {
	public function approvePayroll(Request $request, UserContext $userContext): Response {
		$params = array_merge($request->route()->parameters(), $request->all());
		$hasPermission = $userContext->hasPermission(PermissionsDefinitions::PAYROLL_APPROVE, $params['clientId']);
		// Disallow user to approve payroll if they are logged in as a Support Admin
		$hasPermission = $userContext->getPerson()->isSupportAdmin ? false : $hasPermission;

		if (!$hasPermission) {
			LoggingFactory::log(LoggingFactory::PAYROLL_SERVICES_PERMISSIONS)->error('Permission Denied', [
				'clientId' => $params['clientId'],
				'permission' => PermissionsDefinitions::PAYROLL_APPROVE,
				'isSupportAdmin' => $userContext->getPerson()->isSupportAdmin,
				'function' => 'approvePayroll',
			]);

			return $this->respondPermissions("Unable to approve payroll: Missing Required Permissions");
		}
		try {
			$clientId = (int)$params['clientId'];
			$traxLoginId = $this->clientService->getLoginIdByExternalLoginId($clientId, $userContext->getId());
			if (is_null($traxLoginId)) {
				$traxLoginId = $this->clientService->getLoginIdByExternalEmployeeId($clientId, $userContext->getPerson()->employeeId);
			}
			if (is_null($traxLoginId)) {
				LoggingFactory::log(LoggingFactory::PAYROLL_SERVICES_PERMISSIONS)->error('Trax Login Not Found', [
					'clientId' => $params['clientId'],
					'function' => 'approvePayroll',
				]);

				return $this->respondPermissions("Unable to approve payroll: Trax Login Not Found");
			}

			if ($this->payCycleHelper->isPayCycleStatusInList($request, ['Pending', 'Paid'])) {
				LoggingFactory::log(LoggingFactory::PAYROLL_SERVICES_PRECONDITION_FAILURE)->error('Pay cycle Already Approved', [
					'clientId' => $params['clientId'],
					'function' => 'approvePayroll',
				]);

				return $this->buildActionSuccessResponse();
			}

			if (!$this->payCycleHelper->isPayCycleStatusInList($request, ['Active'], true)) {
				$this->logInvalidStatusError($params['clientId'], 'approvePayroll');

				return $this->respondPreconditionFailed('Precondition Failed', 'INVALID_STATUS');
			}

			$approvePayrollRequest = new ApprovePayrollRequestDataObject([
				'clientId' => $clientId,
				'payGroupId' => $params['payGroupId'] ?? 0,
				'payCycleId' => (int)$params['payCycleId'],
				'currentUser' => $userContext->getPerson()->getFullNameForDatabaseStorage(),
				'traxLoginId' => $traxLoginId,
				'sendAdminEmail' => $params['sendAdminEmail'] ?? 1,
			]);
			$approvePayrollRequest->validateData();
			$warnings = $this->payCycleService->approvePayroll($approvePayrollRequest);

			return $this->buildActionSuccessResponse(new WarningsCollectionTransformer($warnings))->setStatusCode(SymfonyResponse::HTTP_CREATED);
		} catch (PayrollValidationException $exception) {
			return $this->buildConflictResponse($exception->getErrorCode(), new PayrollValidationErrorTransformer($exception->getErrorObject()));
		} catch (ValidationException $exception) {
			$this->logValidationError($params['clientId'], 'approvePayroll', $exception);

			return $this->respondInvalidArgument(implode(', ', $exception->getErrors()), 'INVALID_DATA_PROVIDED');
		}
	}
}