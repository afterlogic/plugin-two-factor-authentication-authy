<?php

/* -AFTERLOGIC LICENSE HEADER- */

class_exists('CApi') or die();
CApi::Inc('common.plugins.two-factor-auth');

include_once 'libs/autoload.php';
include_once 'libs/React/Promise/functions_include.php';

class TwoFactorAuthenticationAuthyPlugin extends AApiTwoFactorAuthPlugin
{
    protected $key;
    protected $logs = false;
    protected $force_2f_auth = true;
    protected $force_send_sms = false;

    /**
     * @param string $sText
     */
    private function _writeLogs($sText)
    {
        if ($this->logs === true)
        {
            $this->Log($sText);
        }
    }

    /**
     * @param CApiPluginManager $oPluginManager
     */
    public function __construct(CApiPluginManager $oPluginManager)
    {
        parent::__construct('1.0', $oPluginManager);

        $this->AddHook('api-integrator-login-to-account-result', 'GetUserApplicationKey');

        $this->AddJsonHook('AjaxVerifyToken', 'AjaxVerifyUserToken');
    }

    public function Init()
    {
        parent::Init();

        $this->SetI18N(true);

        $this->AddJsFile('js/include.js');
        $this->AddCssFile('css/style.css');

        $this->AddJsFile('js/VerifyTokenPopup.js');
        $this->AddTemplate('VerifyTokenPopup', 'templates/VerifyTokenPopup.html', 'Layout', 'Screens-Middle', 'popup');

        $mConfig = \CApi::GetConf('plugins.two-factor-authentication-authy.config', false);

        if ($mConfig)
        {
            $this->key = $mConfig['key'];
            $this->force_send_sms = $mConfig['force_send_sms'];
            $this->logs = $mConfig['logs'];
            $this->force_2f_auth = $mConfig['force_2f_auth'];
        }
    }

    /**
     * @param $sAction
     * @param $aResult
     */
    public function AjaxResponseResult($sAction, &$aResult)
    {
        if ($this->force_2f_auth === true)
        {
            $aResult['ContinueAuth'] = true;
        } 
		else if ($sAction === 'SystemLogin')
		{
			$aResult['Result'] = false;
			$aResult['ErrorMessage'] = $this->I18N('2F_AUTHY_PLUGIN/NOT_LINKED');
		}
    }

    /**
     * @param $oServer
     * @return mixed
     */
    public function AjaxVerifyUserToken($oServer)
    {
        $sEmail = trim(stripcslashes($oServer->getParamValue('Email', null)));
        $sCode = intval(trim(stripcslashes($oServer->getParamValue('Code', null))));

        try 
		{
            $oApiUsers = /* @var $oApiUsers \CApiUsersManager */ \CApi::Manager('users');
            $oAccount = $oApiUsers->getAccountByEmail($sEmail);

            $sDataValue = $this->getCode($oAccount);
            $oStatus = $this->verifyCode($sDataValue, $sCode);

            if ($oStatus->ok())
            {
                $this->_writeLogs($sDataValue. ' is valid');

                $oApiIntegratorManager = /* @var $oApiIntegratorManager \CApiIntegratorManager */ \CApi::Manager('integrator');
                $oApiIntegratorManager->SetAccountAsLoggedIn($oAccount);

                $aResult['Result'] = true;
            } 
			else 
			{
                $this->_writeLogs($sDataValue. ' is not valid');

                $aResult['Result'] = false;
                $aResult['ErrorMessage'] = $oStatus->errors();
            }

        } 
		catch (Exception $oEx) 
		{
            $aResult['Result'] = false;
            $aResult['ErrorMessage'] = $oEx->getMessage();
        }

        return $aResult;
    }

    /**
     * @param $oAccount
     */
    public function getUserApplicationKey(&$oAccount)
    {
        $iAccountExists = $this->isAccountExists($oAccount);
        if ($this->force_2f_auth === true && $iAccountExists === false)
        {
            $this->AddHook('ajax.response-result', 'AjaxResponseResult');

            $oApiIntegratorManager = /* @var $oApiIntegratorManager \CApiIntegratorManager */ \CApi::Manager('integrator');
            $oApiIntegratorManager->SetAccountAsLoggedIn($oAccount);
        }
        else
        {
            $sDataValue = $this->getCode($oAccount);
            if (is_null($sDataValue) || !isset($sDataValue) || empty($sDataValue))
            {
                $this->AddHook('ajax.response-result', 'AjaxResponseResult');
            }
            else if (isset($sDataValue) && !is_null($sDataValue))
            {
                $oAuthy = new Authy\AuthyApi($this->key);
                $oAuthy->requestSms($sDataValue, array('force' => $this->force_send_sms ? 'true' : 'false'));

                $this->_writeLogs('account id: ' . $oAccount->IdAccount . ' sms request code: ' . $sDataValue);
            }
        }
    }

    /**
     * @param string $sDataValue
     * @param string $sCode
     * @return \Authy\AuthyResponse
     */
    public function verifyCode($sDataValue, $sCode)
    {
        $oAuthy = new Authy\AuthyApi($this->key);

        return $oAuthy->verifyToken($sDataValue, $sCode);
    }

    /**
     * @param CAccount $oAccount
     * @return null
     */
    public function getCode($oAccount)
    {
        $sDataValue = null;
        /* @var $oApiManager \CApiTwofactorauthManager */
        $oApiManager = $this->getTwofactorauthManager();

        $oResult = $oApiManager->getAccountById($oAccount->IdAccount, ETwofaType::AUTH_TYPE_AUTHY);
		if ($oResult)
		{
			$aResult = $oResult->ToArray();

			if (is_array($aResult) && isset($aResult['DataValue']))
			{
				$sDataValue = $aResult['DataValue'];
			}
		}

        return $sDataValue;
    }

    /**
     * @param CAccount | null $oAccount = null
     * @param int $iDataType = 0
     * @param string $sDataValue = ''
     * @param bool $bAllowUpdate = true
     * @return bool
     * @throws \ProjectCore\Exceptions\ClientException
     */
    public function createDataValue($oAccount = null, $iDataType = 0, $sDataValue = '', $bAllowUpdate = true)
    {
        /* @var $oApiManager \CApiTwofactorauthManager */
        $oApiManager = $this->getTwofactorauthManager();

        $iAccountId = $oAccount->IdAccount;
        $iAccountExists = $this->isAccountExists($oAccount);
        $bResponse = false;

        if ($iAccountExists === true)
        {
            if ($bAllowUpdate === false)
            {
                $this->_writeLogs('account is exists: ' . $iAccountId);
                throw new \ProjectCore\Exceptions\ClientException(\ProjectCore\Notifications::AccountExists);
            } 
			else 
			{
                $this->_writeLogs('update ' . $iAccountId);

                $oApiManager->updateAccount($oAccount, ETwofaType::AUTH_TYPE_AUTHY, $iDataType, $sDataValue);
                $bResponse = true;
            }
        } 
		else 
		{
            $this->_writeLogs('insert ' . $iAccountId);

            $oApiManager->createAccount($oAccount, ETwofaType::AUTH_TYPE_AUTHY, $iDataType, $sDataValue);
            $bResponse = true;
        }

        return $bResponse;
    }

    /**
     * @param CAccount | null $oAccount = null
     * @return bool
     */
    public function removeDataValue($oAccount = null)
    {
        /* @var $oApiManager \CApiTwofactorauthManager */
        $oApiManager = $this->getTwofactorauthManager();

        $bResponse = false;

        $sDataValue = $this->getCode($oAccount);

        $oAuthy = new Authy\AuthyApi($this->key);
        $bStatus = $oAuthy->deleteUser($sDataValue);

        if ($bStatus->ok())
        {
            $iAccountId = $oAccount->IdAccount;
            $oApiManager->deleteAccountByAccountId($iAccountId);

            $this->_writeLogs('delete account_id: '. $iAccountId . ' success');

            $bResponse = true;
        }

        return $bResponse;
    }

    /**
     * @param CAccount $oAccount
     * @return bool
     */
    public function isAccountExists($oAccount)
    {
        $bResult = false;
		
		/* @var $oApiManager \CApiTwofactorauthManager */
        $oApiManager = $this->getTwofactorauthManager();

        if ($oAccount instanceof \CAccount)
		{
			$bResult = $oApiManager->isAccountExists(ETwofaType::AUTH_TYPE_AUTHY, $oAccount->IdAccount);
		}

		return $bResult;
    }
}

return new TwoFactorAuthenticationAuthyPlugin($this);
