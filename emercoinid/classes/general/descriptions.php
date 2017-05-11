<?
IncludeModuleLangFile(__FILE__);

class CEmcDescription
{
	public function GetDescription()
	{
		return array(
			array(
				"ID" => "EmercoinID",
				"CLASS" => "CEmcEmercoinIDAuth",
				"NAME" => "Emercoin ID",
				"ICON" => "emercoinid",
			)
		);
	}
}

AddEventHandler("emercoinid", "OnAuthServicesBuildList", array("CEmcDescription", "GetDescription"));
?>