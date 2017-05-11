<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true)
	die();

$arExt = array();
if($arParams["POPUP"])
	$arExt[] = "window";
CUtil::InitJSCore($arExt);
$GLOBALS["APPLICATION"]->SetAdditionalCSS("/bitrix/js/emercoinid/css/ss.css");
$GLOBALS["APPLICATION"]->AddHeadScript("/bitrix/js/emercoinid/ss.js");
?>