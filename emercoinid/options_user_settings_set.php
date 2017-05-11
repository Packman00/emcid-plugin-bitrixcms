<?php
/**
 * @global int $ID - Edited user id
 * @global string $strError - Save error
 * @global \CUser $USER
 * @global CMain $APPLICATION
 */

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Emercoinid\UserTable;

$ID = intval($ID);
$emercoinid_res = true;

if(
	$ID > 0
	&& isset($_REQUEST["SS_REMOVE_NETWORK"])
	&& $_REQUEST["SS_REMOVE_NETWORK"] == "Y"
	&& Option::get("emercoinid", "bitrix24net_id", "") != ""
	&& Loader::includeModule('emercoinid')
	&& check_bitrix_sessid()
)
{
	$dbRes = UserTable::getList(array(
		'filter' => array(
			'=USER_ID' => $ID,
			'=EXTERNAL_AUTH_ID' => CEmcEmercoinIDAuth::ID
		),
		'select' => array('ID')
	));

	$profileInfo = $dbRes->fetch();
	if($profileInfo)
	{
		$deleteResult = UserTable::delete($profileInfo["ID"]);
		$emercoinid_res = $deleteResult->isSuccess();

		if($emercoinid_res)
		{
			\Bitrix\Emercoinid\Network::clearAdminPopupSession($ID);
		}
	}
}
