<?php
//TODO: this file will likely go away. This is me trying to reproduce the undefined variable in use statements when the variable is from the IOC container
namespace BambooHR\Command\PayrollImplementation;

use BambooHR\Command\StandardCronCommand;
use BambooHR\Config\AppConfig;
use BambooHR\Exception\InvalidDataException;
use BambooHR\LoggingFactory;
use BambooHR\Silo\Payroll\PayrollService;
use BambooHR\Silo\PayrollEngine\Exception\PayrollServicesClientErrorException;
use BambooHR\Silo\PayrollEngine\Exception\PayrollServicesServerErrorException;
use BambooHR\Silo\PayrollEngine\PayrollEngineSystemService;
use BambooHR\Silo\PayrollImplementation\PayrollImplementationService;
use BambooHR\Utility\Timer\Timer;
use CompanyMaster;
use \Illuminate\Container\Container as App;
use CoreLibs\Utility\Db;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CustomerAccountValidationReviewCommand {

	const ALLOWED_STATUS_TYPES = ['Subscriber', 'In Trial', 'Freemium'];
	const OPTION_COMPANY_ID  = 'company-id';

	/**
	 * See parent docblock for more info.
	 * This cron has heavy logic related to loading the company to tell if it should run.
	 * We do no pre-check for efficiency.
	 * @var bool
	 */
	protected bool $preCheckCompaniesForHost = false;

	/**
	 * A way for children classes to add to the default configuration.
	 * @return void
	 */
	protected function setConfiguration() {
		$this->setName('payroll-imp:customer-account-validation-review')
		     ->setDescription('Determine if a company needs to begin the Account Validation Review process');
	}

	/**
	 * @param App $app
	 * @return bool
	 * @throws BindingResolutionException
	 */
	protected function isPayrollEnabled(App $app): bool {
		return Timer::wrap('isPayrollEnabled', [], function() use ($app) {
			$payrollService = $app->make(PayrollService::class);
			return $payrollService->isTraxPayrollAppEnabled();
		});
	}

	/**
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 * @param App             $app
	 * @return void
	 * @guardrail-ignore Standard.Exception.Base
	 */
	protected function doWork(InputInterface $input, OutputInterface $output, App $app) {
		try {
			// Secondary check here because the bamboohr.company_details gets synced with a delay.
			// We may get false positives from the query in getHostCompanyIdsToInclude.
			if (!$this->isPayrollEnabled($app)) {
				return;
			}

			$clientIds = $this->getClientIds($app);
			if (empty($clientIds)) {
				return;
			}

			/** @var PayrollImplementationService $payrollImplementationService */
			$payrollImplementationService = $app->make(PayrollImplementationService::class);
			foreach ($clientIds as $clientId) {
				$shouldNotifyCustomer = Timer::wrap('shouldNotifyCustomerOfAccountValidationReview', [], function() use ($payrollImplementationService, $clientId) {
					return $payrollImplementationService->shouldNotifyCustomerOfAccountValidationReview($clientId);
				});
				if (!$shouldNotifyCustomer) {
					continue;
				}

				$this->logger('Send the customer AVR notification', ['clientId' => $clientId, 'companyId' => $this->companyId]);
				$payrollImplementationService->sendCustomerAccountValidationReviewNotificationForClient($clientId);
			}
		} catch (Exception $exception) {
			$this->logger("Customer account validation review command is failing in doWork.", [
				'companyId' => $this->companyId,
				'method' => 'doWork',
				'exception' => $exception->getMessage()
			]);
		}
		return;  //move on to next company
	}

	/**
	 * @param App $app
	 * @return array
	 * @throws InvalidDataException
	 * @throws PayrollServicesClientErrorException
	 * @throws PayrollServicesServerErrorException
	 */
	protected function getClientIds(App $app): array {
		return Timer::wrap('getClientIds', [], function() use ($app) {
			/** @var PayrollEngineSystemService $payrollEngineSystemService */
			$payrollEngineSystemService = $app->make(PayrollEngineSystemService::class);
			return $payrollEngineSystemService->getActiveClientIdsByCompanyId($this->companyId);
		});
	}

	/**
	 * get the list of companies to run for, for a specific host.
	 *
	 * @param InputInterface $input
	 * @param string         $host
	 * @return CompanyMaster[]
	 */
	protected function getCompaniesToRunForOnHost(InputInterface $input, $host) {
		$dataCenterId = AppConfig::getStaticValue(AppConfig::CURRENT_DATACENTER_ID);
		$hostPayrollCompanyIds = $this->getHostCompanyIdsToInclude($host, $dataCenterId);

		$companyIdOverride = $input->getOption(self::OPTION_COMPANY_ID);
		if (!empty($companyIdOverride)) {
			$companyIds = $this->getCompanyIdsFromCsvList($companyIdOverride);
			$hostPayrollCompanyIds = $this->getCompanyIdsInBothArrays($companyIds, $hostPayrollCompanyIds);
		}

		if (empty($hostPayrollCompanyIds)) {
			$companyMasters = [];
		} else {
			$companyIdPlaceholders = Db::getArrayPlaceholders($hostPayrollCompanyIds);
			$companyMasters = CompanyMaster::gimmeAllWhere($this->mainDb,
				"`id` IN($companyIdPlaceholders)",
				$hostPayrollCompanyIds)->toArray();
		}

		return $companyMasters;
	}

	/**
	 * @param string $companyIdCsvList
	 * @return array|null
	 * @covered-by CustomerAccountValidationReviewCommandTest::testGetCompanyIdsFromCsvList
	 */
	protected function getCompanyIdsFromCsvList(string $companyIdCsvList): ?array {
		return array_map('trim', explode(',', $companyIdCsvList));
	}

	/**
	 * @param array $companyIds1
	 * @param array $companyIds2
	 * @return array
	 * @covered-by CustomerAccountValidationReviewCommandTest::testGetCompanyIdsInBothArrays
	 */
	protected function getCompanyIdsInBothArrays(array $companyIds1, array $companyIds2): array {
		return array_intersect($companyIds1, $companyIds2);
	}

	/**
	 * @param string $message
	 * @param array  $data
	 * @return void
	 */
	private function logger(string $message, array $data): void {
		LoggingFactory::PAYROLL_IMPLEMENTATION()->info("CustomerAccountValidationReviewCommand: $message", array_merge([
				'class' => 'CustomerAccountValidationReviewCommand',
			], $data)
		);
	}

	/**
	 * @param string $host
	 * @param int    $dataCenterId
	 * @return array
	 * @throws \CoreLibs\Core\Database\DbException
	 */
	protected function getHostCompanyIdsToInclude($host, int $dataCenterId): array {
		$bambooHrPayrollNamedTool = 'BAMBOOHR_PAYROLL';
		$statusPlaceholders = Db::getArrayPlaceholders(self::ALLOWED_STATUS_TYPES);
		$query = "SELECT company_master.id
			FROM company_details
			JOIN company_master ON company_master.id = company_details.company_id
			WHERE company_master.db_name IS NOT NULL
			AND FIND_IN_SET(
				CAST((SELECT id FROM tools WHERE named_constant = ? limit 1) AS CHAR),
				CAST(enabled_tools AS CHAR)
			) > 0
			AND company_master.data_center_id = ?
			AND company_master.status IN($statusPlaceholders)";
		$params = array_merge([$bambooHrPayrollNamedTool, $dataCenterId], self::ALLOWED_STATUS_TYPES);

		if ($host) {
			$query .= " AND company_master.db_host = ?";
			array_push($params, $host);
		}

		return $this->mainDb->queryOneColumn($query, $params);
	}
}