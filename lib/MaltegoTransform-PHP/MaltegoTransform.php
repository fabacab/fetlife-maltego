<?php
class MaltegoEntity
{
	var $value = "";
	var $weight = 100;
	var $displayInformation = "";
	var $additionalFields = array();
	var $iconURL = "";
	var $type = "Phrase";


	function MaltegoEntity($type,$value)
	{
		$this->type = $type;
		$this->value = $value;
	}

	/*
 		SETTER FUNCTIONS
 	*/
 	function setType($t)
 	{
 		$this->type = $t;
 	}
 	function setValue($v)
 	{
 		$this->value = $v;
 	}

 	function setWeight($w)
 	{
 		$this->weight = $w;
 	}

 	function setDisplayInformation($di)
 	{
 		$this->displayInformation = $di;
 	}

 	function addAdditionalFields($fieldName,$displayName,$isKey="false",$value)
 	{
 		$this->additionalFields[] = array($fieldName,$displayName,$isKey,$value);
 	}

 	function setIconURL($iu)
 	{
 		$this->iconURL = $iu;
 	}

 	function returnEntity()
 	{
 		$output  = "<Entity Type='" . $this->type . "'>";
 		$output .= "<Value>" . $this->value . "</Value>";
 		$output .= "<Weight>" . $this->weight . "</Weight>";
 		if ($this->displayInformation <> "")
 		{
 			$output .= "<DisplayInformation><Label Name=\"\" Type=\"text/html\"><![CDATA[" . $this->displayInformation . "]]>
</Label></DisplayInformation>";
 		}

 		if (count($this->additionalFields) > 0)
 		{
 			$output .= "<AdditionalFields>";
 			foreach($this->additionalFields as $af)
 			{
 				if ($af[2] <> "strict")
 				{
 					$output .= "<Field Name=\"" . $af[0] . "\" DisplayName=\"" . $af[1] . "\" >" . $af[3] . "</Field>";
 				}
 				else
 				{
 					$output .= "<Field MatchingRule=\"" . $af[2] . "\" Name=\"" . $af[0] . "\" DisplayName=\"" . $af[1] . "\" >" . $af[3] . "</Field>";
 				}
 			}
 			$output .= "</AdditionalFields>";
 		}

 		if ($this->iconURL <> "")
 		{
 			$output .="<IconURL>" . $this->iconURL . "</IconURL>";
 		}
 		$output .= "</Entity>\n";
 		return $output;
 	}
}
class MaltegoTransform
{
	var $entities = array();
	var $exceptions = array();
	var $UIMessages = array();

 	function MaltegoTransform()
 	{

 	}

 	function addEntity($type,$value)
 	{
 		$e = new MaltegoEntity($type,$value);
 		$this->addEntitytoMessage($e);
 		return $this->entities[count($this->entities) - 1];

 	}


 	function addEntitytoMessage($e)
 	{
 		$this->entities[] = $e;
 	}


 	function addUIMessage($message,$type = "Inform")
 	{
 		$this->UIMessages[] = array($type,$message);
 	}

 	function addException($exception)
 	{
 		$this->exceptions[] = $exception;
 	}


 	/*
 		OUTPUT FUNCTIONS
 	*/
 	function throwExceptions()
 	{
 		$output = "<MaltegoMessage>\n";
 		$output .= "<MaltegoTransformExceptionMessage>\n";
 		$output .= "<Exceptions>\n";
 		foreach($this->exceptions as $x)
 		{
 			$output .="<Exception>$x</Exception>\n";
 		}
 		$output .= "</Exceptions>\n";
 		$output .= "</MaltegoTransformExceptionMessage>\n";
 		$output .= "</MaltegoMessage>\n";
 		echo $output;
 	}

 	function returnOutput()
 	{
 		$output = "<MaltegoMessage>\n";
 		$output .= "<MaltegoTransformResponseMessage>\n";
 		$output .="<Entities>\n";
 		foreach($this->entities as $e)
 		{
 			$output .= $e->returnEntity();
 		}
 		$output .="</Entities>\n";

 		$output .="<UIMessages>\n";
 		foreach($this->UIMessages as $ui)
 		{
 			$output .= "<UIMessage MessageType='" . $ui[0] . "'>" . $ui[1] . "</UIMessage>\n";
 		}
 		$output .="</UIMessages>\n";

 		$output .= "</MaltegoTransformResponseMessage>\n";
 		$output .= "</MaltegoMessage>\n";
 		echo $output;
 	}

 	function writeSTDERR($msg)
 	{
 		//$stderr = fopen('php://stderr', 'w');
    		fwrite(STDERR,$msg . "\n\r");
    		//fclose($stderr);

 	}
 	function heartBeat()
 	{
 		$this->writeSTDERR("+");
 	}

 	function progress($percent)
 	{
 		$this->writeSTDERR("%" . $percent);
 	}

 	function debug($msg)
 	{
 		$this->writeSTDERR("D:" . $msg);
 	}
 }
 ?>
