<?php
IncludeModuleLangFile(__FILE__);
use Bitrix\Emercoinid\UserTable;

class CEmcEmercoinIDAuth extends CEmcAuth
{
	const ID = "EmercoinID";
	const CONTROLLER_URL = "https://www.bitrix24.ru/controller";
	const LOGIN_PREFIX = "DB_";

	/** @var CEmercoinIDOAuthInterface null  */
	protected $entityOAuth = null;

	/**
	 * @param string $code=false
	 * @return CEmercoinIDOAuthInterface
	 */
	public function getEntityOAuth($code = false)
	{
		if(!$this->entityOAuth)
		{
			$this->entityOAuth = new CEmercoinIDOAuthInterface();
		}

		if($code !== false)
		{
			$this->entityOAuth->setCode($code);
		}

		return $this->entityOAuth;
	}

	public function GetSettings()
	{
		return array(
			array("emercoinid_appid", GetMessage("socserv_emercoinid_client_id"), "", array("text", 40)),
			array("emercoinid_appsecret", GetMessage("socserv_emercoinid_client_secret"), "", array("text", 40)),
			array("note"=>GetMessage("socserv_emercoinid_note", array('#URL#'=>CEmercoinIDOAuthInterface::GetRedirectURI()))),
		);
	}

	public function GetFormHtml($arParams)
	{
		$url = static::getUrl('opener', null, $arParams);

		$phrase = ($arParams["FOR_INTRANET"]) ? GetMessage("socserv_emercoinid_form_note_intranet") : GetMessage("socserv_emercoinid_form_note");

		if($arParams["FOR_INTRANET"])
		{
			return array("ON_CLICK" => 'onclick="BX.util.popup(\''.htmlspecialcharsbx(CUtil::JSEscape($url)).'\', 680, 600)"');
		}
		else
		{
			return '<a href="javascript:void(0)" onclick="BX.util.popup(\''.htmlspecialcharsbx(CUtil::JSEscape($url)).'\', 680, 600)" class="bx-ss-button emercoinid-button"></a><span class="bx-spacer"></span><span>'.$phrase.'</span>';
		}
	}

	public function GetOnClickJs($arParams)
	{
		$url = static::getUrl('opener', null, $arParams);
		return "BX.util.popup('".CUtil::JSEscape($url)."', 680, 600)";
	}


	public function getUrl($location = 'opener', $addScope = null, $arParams = array())
	{
		global $APPLICATION;

		$this->entityOAuth = $this->getEntityOAuth();
		CEmcAuthManager::SetUniqueKey();
		if(IsModuleInstalled('bitrix24') && defined('BX24_HOST_NAME'))
		{
			$redirect_uri = static::CONTROLLER_URL."/redirect.php";
			$state = CEmercoinIDOAuthInterface::GetRedirectURI()."?check_key=".$_SESSION["UNIQUE_KEY"]."&state=";
			$backurl = $APPLICATION->GetCurPageParam('', array("logout", "auth_service_error", "auth_service_id", "backurl"));
			$state .= urlencode("state=".urlencode("backurl=".urlencode($backurl).'&mode='.$location.(isset($arParams['BACKURL']) ? '&redirect_url='.urlencode($arParams['BACKURL']) : '')));
		}
		else
		{
			$state = 'site_id='.SITE_ID.'&backurl='.urlencode($APPLICATION->GetCurPageParam('check_key='.$_SESSION["UNIQUE_KEY"], array("logout", "auth_service_error", "auth_service_id", "backurl"))).'&mode='.$location.(isset($arParams['BACKURL']) ? '&redirect_url='.urlencode($arParams['BACKURL']) : '');
			$redirect_uri = CEmercoinIDOAuthInterface::GetRedirectURI();
		}

		return $this->entityOAuth->GetAuthUrl($redirect_uri, $state);
	}

	public function getStorageToken()
	{
		$accessToken = null;
		$userId = intval($this->userId);
		if($userId > 0)
		{
			$dbSocservUser = CEmcAuthDB::GetList(array(), array('USER_ID' => $userId, "EXTERNAL_AUTH_ID" => static::ID), false, false, array("OATOKEN", "REFRESH_TOKEN", "OATOKEN_EXPIRES"));
			if($arOauth = $dbSocservUser->Fetch())
			{
				$accessToken = $arOauth["OATOKEN"];
			}
		}

		return $accessToken;
	}

	public function prepareUser($arEmercoinIDUser, $short = false)
	{
		$first_name = "";
		$last_name = "";
		$noName = false;
		$noLastName = false;
		$noEmail = false;
		$uniqid = substr(uniqid(), 8, 13);
		if(is_array($arEmercoinIDUser['infocard']))
		{
			$first_name = $arEmercoinIDUser['infocard']['FirstName'];
			$last_name = $arEmercoinIDUser['infocard']['LastName'];
		}

		if(!$first_name) 
		{
			$noName = true;
		}

		if(!$last_name)
		{
			$noLastName = true;
		}

		if(!$arEmercoinIDUser['infocard']['Email'])
		{
			$noEmail = true;
		}

		if($noName && $noLastName)
		{
			$login = 'emcid_'.$uniqid;
		} else {
			$login = strtolower($first_name).'-'.strtolower($last_name);
		}

		if($noEmail)
		{
			$email = 'emcid_'.$uniqid.'@emercoinid.local';
		} else {
			$email = $arEmercoinIDUser['infocard']['Email'];
		}

		if($this->checkLogin($login)) {
			$login = strtolower($first_name).
					"-".
					strtolower($last_name).
					"-".
					$uniqid;
		}
		if($this->checkEmail($email)) {
			$email = $login
					 .
					 "@emercoinid.local";

			while ($this->checkEmail($email) === true) {
				$email = strtolower($first_name).
						 "-".
						 strtolower($last_name).
						 "-".
						 $uniqid.
						 "@emercoinid.local";
			}
		}

		$id = strtolower($arEmercoinIDUser['SSL_CLIENT_M_SERIAL']);

		$arFields = array(
			'EXTERNAL_AUTH_ID' => static::ID,
			'XML_ID' => $id,
			'LOGIN' => $login,
			'NAME'=> $first_name,
			'LAST_NAME'=> $last_name,
			'EMAIL' => $email,
			'OATOKEN' => $this->entityOAuth->getToken(),
			'OATOKEN_EXPIRES' => $this->entityOAuth->getAccessTokenExpires(),
		);

		if(strlen(SITE_ID) > 0)
		{
			$arFields["SITE_ID"] = SITE_ID;
		}

		return $arFields;
	}

	public function Authorize()
	{
		global $APPLICATION;
		$APPLICATION->RestartBuffer();

		$bSuccess = false;
		$bProcessState = false;
		$authError = SOCSERV_AUTHORISATION_ERROR;
		if(
			isset($_REQUEST["code"]) && $_REQUEST["code"] <> '' && CEmcAuthManager::CheckUniqueKey()
		)
		{
			$bProcessState = true;

			$this->entityOAuth = $this->getEntityOAuth($_REQUEST['code']);

			$redirect_uri = $this->getEntityOAuth()->GetRedirectURI();
			
			if($this->entityOAuth->GetAccessToken($redirect_uri) !== false)
			{
				$arEmercoinIDUser = $this->entityOAuth->GetCurrentUser();
				if(is_array($arEmercoinIDUser))
				{
					$arFields = self::prepareUser($arEmercoinIDUser);
					$authError = $this->AuthorizeUser($arFields);
					
					$bSuccess = $authError === true;
				}
			}
		}

		$url = ($APPLICATION->GetCurDir() == "/login/") ? "" : $APPLICATION->GetCurDir();

		$aRemove = array("login", "logout", "auth_service_error", "auth_service_id", "code", "error_reason", "error", "error_description", "check_key", "current_fieldset");

		if(isset($_REQUEST['error'])) {
			$arError = array();
			parse_str($_REQUEST["state"], $arError);

			if(isset($arError['backurl']) || isset($arError['redirect_url']))
			{
				$url = !empty($arError['redirect_url']) ? $arError['redirect_url'] : $arError['backurl'];
				if(substr($url, 0, 1) !== "#")
				{
					$parseUrl = parse_url($url);

					$urlPath = $parseUrl["path"];
					$arUrlQuery = explode('&', $parseUrl["query"]);

					foreach($arUrlQuery as $key => $value)
					{
						foreach($aRemove as $param)
						{
							if(strpos($value, $param."=") === 0)
							{
								unset($arUrlQuery[$key]);
								break;
							}
						}
					}
					$url = (!empty($arUrlQuery)) ? $urlPath.'?'.implode("&", $arUrlQuery) : $urlPath;
					$url .= "?error=".$_REQUEST['error']."&error_description=".$_REQUEST['error_description'];
				}
			}
			else
			{
				if(isset($_SESSION['cur_page'])){
					$parseUrl = parse_url($_SESSION['cur_page']);
					$urlPath = $parseUrl["path"];
					$_SESSION['cur_page'] = explode('&', $parseUrl["query"]);
					foreach($_SESSION['cur_page'] as $key => $value)
					{
						foreach($aRemove as $param)
						{
							if(strpos($value, $param."=") === 0)
							{
								unset($_SESSION['cur_page'][$key]);
								break;
							}
						}
					}
					$url = $urlPath."?error=".$_REQUEST['error']."&error_description=".$_REQUEST['error_description'];
				}
			}
		}

		if(!$bProcessState)
		{
			unset($_REQUEST["state"]);
		}

		$mode = 'opener';
		$addParams = true;
		if(isset($_REQUEST["state"]))
		{
			$arState = array();
			parse_str($_REQUEST["state"], $arState);
			if(isset($arState['backurl']) || isset($arState['redirect_url']))
			{
				$url = !empty($arState['redirect_url']) ? $arState['redirect_url'] : $arState['backurl'];
				if(substr($url, 0, 1) !== "#")
				{
					$parseUrl = parse_url($url);

					$urlPath = $parseUrl["path"];
					$arUrlQuery = explode('&', $parseUrl["query"]);

					foreach($arUrlQuery as $key => $value)
					{
						foreach($aRemove as $param)
						{
							if(strpos($value, $param."=") === 0)
							{
								unset($arUrlQuery[$key]);
								break;
							}
						}
					}

					$url = (!empty($arUrlQuery)) ? $urlPath.'?'.implode("&", $arUrlQuery) : $urlPath;
				}
				else
				{
					$addParams = false;
				}
			}

			if(isset($arState['mode']))
			{
				$mode = $arState['mode'];
			}
		}

		// if($authError === SOCSERV_REGISTRATION_DENY)
		// {
		// 	$url = (preg_match("/\?/", $url)) ? $url.'&' : $url.'?';
		// 	$url .= 'auth_service_id='.static::ID.'&auth_service_error='.SOCSERV_REGISTRATION_DENY;
		// }
		// elseif($bSuccess !== true)
		// {
		// 	$url = (isset($urlPath)) ? $urlPath.'?auth_service_id='.static::ID.'&auth_service_error='.$authError : $APPLICATION->GetCurPageParam(('auth_service_id='.static::ID.'&auth_service_error='.$authError), $aRemove);
		// }

		// if($addParams && CModule::IncludeModule("socialnetwork") && strpos($url, "current_fieldset=") === false)
		// {
		// 	$url = (preg_match("/\?/", $url)) ? $url."&current_fieldset=SOCSERV" : $url."?current_fieldset=SOCSERV";
		// }

		$url = CUtil::JSEscape($url);

		if($addParams)
		{
			$location = ($mode == "opener") ? 'if(window.opener) window.opener.location = \''.$url.'\'; window.close();' : ' window.location = \''.$url.'\';';
		}
		else
		{
			//fix for chrome
			$location = ($mode == "opener") ? 'if(window.opener) window.opener.location = window.opener.location.href + \''.$url.'\'; window.close();' : ' window.location = window.location.href + \''.$url.'\';';
		}

		
		$JSScript = '
		<script type="text/javascript">
		'.$location.'
		</script>
		';

		echo $JSScript;

		die();
	}

	public function checkEmail($email)
	{
		$usrEmail = UserTable::getList(array(
			'filter' => array(
				'EMAIL'=>$email
			),
			'select' => array("ID", "USER_ID", "LOGIN", "EMAIL", "ACTIVE" => "USER.ACTIVE"),
		));
		$emailCheck = $usrEmail->fetch();

		if($emailCheck) {
			return true;
		} else {
			return false;
		}
	}

	public function checkLogin($login)
	{
		$usrLogin = UserTable::getList(array(
			'filter' => array(
				'LOGIN'=>$login
			),
			'select' => array("ID", "USER_ID", "LOGIN", "EMAIL", "ACTIVE" => "USER.ACTIVE"),
		));
		$loginCheck = $usrLogin->fetch();

		if($loginCheck) {
			return true;
		} else {
			return false;
		}
	}
}

class CEmercoinIDOAuthInterface extends CEmcOAuthTransport
{
	const SERVICE_ID = "EmercoinID";

	const AUTH_URL = "https://oauth.authorizer.io/oauth/v2/auth";
	const TOKEN_URL = "https://oauth.authorizer.io/oauth/v2/token";

	const ACCOUNT_URL = "https://oauth.authorizer.io/infocard";

	protected $oauthResult;

	public function __construct($authUrl = false, $authToken = false, $accountUrl = false, $appID = false, $appSecret = false, $code = false)
	{
		if($authUrl === false)
		{
			$authUrl = trim(CEmcEmercoinIDAuth::GetOption("emercoinid_authurl"));
		}

		if($authToken === false)
		{
			$authToken = trim(CEmcEmercoinIDAuth::GetOption("emercoinid_tokenurl"));
		}

		if($accountUrl === false)
		{
			$accountUrl = trim(CEmcEmercoinIDAuth::GetOption("emercoinid_accounturl"));
		}

		if($appID === false)
		{
			$appID = trim(CEmcEmercoinIDAuth::GetOption("emercoinid_appid"));
		}

		if($appSecret === false)
		{
			$appSecret = trim(CEmcEmercoinIDAuth::GetOption("emercoinid_appsecret"));
		}

		parent::__construct($authUrl, $authToken, $accountUrl, $appID, $appSecret, $code);
	}

	public function GetRedirectURI()
	{
		return \CHTTP::URN2URI("/bitrix/tools/oauth/emercoinid.php");
	}

	public function GetAuthUrl($redirect_uri, $state = '')
	{
		return static::AUTH_URL.
		"?client_id=".urlencode($this->appID).
		"&redirect_uri=".urlencode($redirect_uri).
		"&response_type=code".
		($state <> '' ? '&state='.urlencode($state) : '');
	}

	public function GetAccessToken($redirect_uri)
	{
		$tokens = $this->getStorageTokens();

		if(is_array($tokens))
		{
			$this->access_token = $tokens["OATOKEN"];

			if(!$this->code)
			{
				return true;
			}

			$this->deleteStorageTokens();
		}

		if($this->code === false)
		{
			return false;
		}

		$h = new \Bitrix\Main\Web\HttpClient();
		$result = $h->post(static::TOKEN_URL, array(
			"code"=>$this->code,
			"client_id"=>$this->appID,
			"client_secret"=>$this->appSecret,
			"redirect_uri"=>$redirect_uri,
			"grant_type"=>"authorization_code",
		));

		$this->oauthResult = \Bitrix\Main\Web\Json::decode($result);

		if(isset($this->oauthResult["access_token"]) && $this->oauthResult["access_token"] <> '')
		{
			if(isset($this->oauthResult["refresh_token"]) && $this->oauthResult["refresh_token"] <> '')
			{
				$this->refresh_token = $this->oauthResult["refresh_token"];
			}
			$this->access_token = $this->oauthResult["access_token"];

			$_SESSION["OAUTH_DATA"] = array(
				"OATOKEN" => $this->access_token,
			);

			return true;
		}
		return false;
	}

	public function GetCurrentUser()
	{
		if($this->access_token === false)
			return false;

		$h = new \Bitrix\Main\Web\HttpClient();
		$h->setHeader("Authorization", "Bearer ".$this->access_token);

		$result = $h->get(static::TOKEN_URL.'/'.$this->access_token);

		$result = \Bitrix\Main\Web\Json::decode($result);

		if(is_array($result))
		{
			$result["access_token"] = $this->access_token;
		}

		return $result;
	}
}