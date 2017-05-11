<?
IncludeModuleLangFile(__FILE__);

class emercoinid extends CModule
{
	var $MODULE_ID = "emercoinid";
	var $MODULE_VERSION;
	var $MODULE_VERSION_DATE;
	var $MODULE_NAME;
	var $MODULE_DESCRIPTION;

	function emercoinid()
	{
		$arModuleVersion = array();

		include(substr(__FILE__, 0,  -10)."/version.php");

		$this->MODULE_VERSION = $arModuleVersion["VERSION"];
		$this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];

		$this->MODULE_NAME = GetMessage("emercoinid_install_name");
		$this->MODULE_DESCRIPTION = GetMessage("emercoinid_install_desc");
	}

	function InstallDB($arParams = array())
	{
		global $DB, $DBType, $APPLICATION;
		$errors = false;
		if(!$DB->Query("SELECT 'x' FROM b_emercoinid_user", true))
		{
			$errors = $DB->RunSQLBatch($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/emercoinid/install/db/".$DBType."/install.sql");
		}

		if ($errors !== false)
		{
			$APPLICATION->ThrowException(implode("", $errors));
			return false;
		}

		RegisterModule("emercoinid");

		RegisterModuleDependences("main", "OnUserDelete", "emercoinid", "CEmcAuthDB", "OnUserDelete");
		RegisterModuleDependences('timeman', 'OnAfterTMReportDailyAdd', 'emercoinid', 'CEmcAuthDB', 'OnAfterTMReportDailyAdd');
		RegisterModuleDependences('timeman', 'OnAfterTMDayStart', 'emercoinid', 'CEmcAuthDB', 'OnAfterTMDayStart');
		RegisterModuleDependences('timeman', 'OnTimeManShow', 'emercoinid', 'CEmcEventHandlers', 'OnTimeManShow');
		RegisterModuleDependences('main', 'OnFindExternalUser', 'emercoinid', 'CEmcAuthDB', 'OnFindExternalUser');

		return true;
	}

	function UnInstallDB($arParams = array())
	{
		global $APPLICATION, $DB, $DOCUMENT_ROOT;

		if(!array_key_exists("savedata", $arParams) || $arParams["savedata"] != "Y")
		{
			$errors = $DB->RunSQLBatch($DOCUMENT_ROOT."/bitrix/modules/emercoinid/install/db/".strtolower($DB->type)."/uninstall.sql");
			if (!empty($errors))
			{
				$APPLICATION->ThrowException(implode("", $errors));
				return false;
			}
		}
		UnRegisterModuleDependences("main", "OnUserDelete", "emercoinid", "CEmcAuthDB", "OnUserDelete");
		UnRegisterModuleDependences('socialnetwork', 'OnFillSocNetLogEvents', 'emercoinid', 'CEmcEventHandlers', 'OnFillSocNetLogEvents');
		UnRegisterModuleDependences('timeman', 'OnAfterTMReportDailyAdd', 'emercoinid', 'CEmcAuthDB', 'OnAfterTMReportDailyAdd');
		UnRegisterModuleDependences('timeman', 'OnAfterTMDayStart', 'emercoinid', 'CEmcAuthDB', 'OnAfterTMDayStart');
		UnRegisterModuleDependences('timeman', 'OnTimeManShow', 'emercoinid', 'CEmcEventHandlers', 'OnTimeManShow');
		UnRegisterModuleDependences('main', 'OnFindExternalUser', 'emercoinid', 'CEmcAuthDB', 'OnFindExternalUser');

		$dbSites = CSite::GetList(($b="sort"), ($o="asc"), array("ACTIVE" => "Y"));
		while ($arSite = $dbSites->Fetch())
		{
			$siteId = $arSite['ID'];
			CAgent::RemoveAgent("CEmcAuthManager::GetTwitMessages($siteId);", "emercoinid");
		}
		CAgent::RemoveAgent("CEmcAuthManager::SendSocialservicesMessages();", "emercoinid");

		UnRegisterModule("emercoinid");

		return true;
	}

	function InstallEvents()
	{
		return true;
	}

	function UnInstallEvents()
	{
		return true;
	}

	function InstallFiles($arParams = array())
	{
		if($_ENV["COMPUTERNAME"]!='BX')
		{
			CopyDirFiles($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/emercoinid/install/components", $_SERVER["DOCUMENT_ROOT"]."/bitrix/components", true, true);
			CopyDirFiles($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/emercoinid/install/js", $_SERVER["DOCUMENT_ROOT"]."/bitrix/js", true, true);
			CopyDirFiles($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/emercoinid/install/images", $_SERVER["DOCUMENT_ROOT"]."/bitrix/images", true, true);
			CopyDirFiles($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/emercoinid/install/tools", $_SERVER["DOCUMENT_ROOT"]."/bitrix/tools", true, true);
			CopyDirFiles($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/emercoinid/install/gadgets", $_SERVER["DOCUMENT_ROOT"]."/bitrix/gadgets", true, true);
		}
		return true;
	}

	function UnInstallFiles()
	{
		if($_ENV["COMPUTERNAME"]!='BX')
		{
			DeleteDirFilesEx("/bitrix/components/emcid/");
			DeleteDirFilesEx("/bitrix/js/emercoinid/");
			DeleteDirFilesEx("/bitrix/images/emercoinid/");
			DeleteDirFilesEx("/bitrix/tools/oauth/emercoinid.php");
		}
		return true;
	}

	function DoInstall()
	{
		global $DOCUMENT_ROOT, $APPLICATION, $step;
		$step = IntVal($step);
		if($step<2)
		{
			$APPLICATION->IncludeAdminFile(GetMessage("emercoinid_install_title_inst"), $DOCUMENT_ROOT."/bitrix/modules/emercoinid/install/step1.php");
		}
		else
		{
			$this->InstallFiles();
			$this->InstallDB();
			$APPLICATION->IncludeAdminFile(GetMessage("emercoinid_install_title_inst"), $DOCUMENT_ROOT."/bitrix/modules/emercoinid/install/step2.php");
		}
	}

	function DoUninstall()
	{
		global $DOCUMENT_ROOT, $APPLICATION, $step, $errors;
		$step = IntVal($step);
		if($step<2)
		{
			$APPLICATION->IncludeAdminFile(GetMessage("emercoinid_install_title_inst"), $DOCUMENT_ROOT."/bitrix/modules/emercoinid/install/unstep1.php");
		}
		elseif($step==2)
		{
			$errors = false;

			$this->UnInstallDB(array(
				"savedata" => $_REQUEST["savedata"],
			));

			$this->UnInstallFiles();

			$APPLICATION->IncludeAdminFile(GetMessage("emercoinid_install_title_inst"), $DOCUMENT_ROOT."/bitrix/modules/emercoinid/install/unstep2.php");
		}
	}
}
?>