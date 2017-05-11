<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true)
	die();
global $USER;
if(!$USER->IsAuthorized()):
$arAuthServices = array();
$_SESSION['cur_page'] = $_SERVER['REQUEST_URI'];

if(is_array($arResult["~AUTH_SERVICES"]))
{
	$arAuthServices = $arResult["~AUTH_SERVICES"];
}
?>
<?if(isset($_GET['error']) && isset($_GET['error_description'])):?>
	<p><font class="errortext"><?=$_GET['error_description']?><br></font></p>
<?endif?>
<?
if($arParams["~FOR_SPLIT"] == 'Y'):?>
<style type="text/css">
.css-emcl {
    background: url(/bitrix/js/emercoinid/css/emc-logo.png) no-repeat 10px center;
    background-color: #9267A8;
    background-size: 28px 25px, cover;
    line-height: 1;
    display: block;
    padding-left: 50px;
    color: #fff !important;
    text-decoration: none !important;
    transition: opacity .3s ease-in;
    text-shadow: 1px 1px 0px #000;
    opacity: .90;
    position: relative;
    min-width: 175px;
    max-width: 300px;
    margin: 0 auto 16px;
}
a.css-emcl:hover {
    background-color: #9D67A8;
    background-size: 28px 25px, cover;
    color: #FDFDFD;
    opacity: 1;
}
a.css-emcl div {
    height: 45px;
    display: table-cell;
    vertical-align: middle;
}
</style>
<div class="bx-auth-serv-icons">
	<?foreach($arAuthServices as $service):?>
	<?
	if(($arParams["~FOR_SPLIT"] == 'Y') && (is_array($service["FORM_HTML"])))
		$onClickEvent = $service["FORM_HTML"]["ON_CLICK"];
	else
		$onClickEvent = "onclick=\"BxShowAuthService('".$service['ID']."', '".$arParams['SUFFIX']."')\"";
	?>
	<a title="<?=htmlspecialcharsbx($service["NAME"])?>" href="javascript:void(0)" <?=$onClickEvent?> class="css-emcl js-emcl" id="bx_auth_href_<?=$arParams["SUFFIX"]?><?=$service["ID"]?>">
		<div>
			Sign in with Emercoin ID
		</div>
	</a>
	<?endforeach?>
</div>
<?endif;?>
<?endif?>