<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true)
	die();

if (!CModule::IncludeModule("emercoinid"))
	return;

$oAuthManager = new CEmcAuthManager();
if(isset($arParams['BACKURL']))
{
	$arResult['BACKURL'] = trim($arParams['BACKURL']);
}

$arResult["FOR_INTRANET"] = true;

$arServices = $oAuthManager->GetActiveAuthServices($arResult);

if(!is_array($arResult["~AUTH_SERVICES"]))
	$arResult["~AUTH_SERVICES"] = $arServices;

if(!is_array($arParams["~SERVICES"]))
	$arParams["~SERVICES"] = $arServices;

if(!isset($arParams["~FOR_SPLIT"]))
	$arParams["~FOR_SPLIT"] = 'Y';

$arParams["FORIE"] = false;
if(isset($_SERVER['HTTP_USER_AGENT']) && (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false))
		$arParams["FORIE"] = true;


$this->IncludeComponentTemplate();
?>