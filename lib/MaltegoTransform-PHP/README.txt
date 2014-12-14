So here is a small PHP lib for creating your own transforms, just takes out some of the hassle Cheesy

Basic Example: (will create a Person Entity with a value of "Andrew MacPherson"

<?php

include_once("MaltegoTransform.php");
$mt = new MaltegoTransform();
$mt->debug("Starting Transform");
$mt->addEntity("Person","Andrew MacPherson");
$mt->returnOutput();


More Advanced Example:
<?php

include_once("MaltegoTransform.php");
$mt = new MaltegoTransform();
$mt->debug("Starting Transform");
$NewEnt = $mt->addEntity("Person","Andrew MacPherson");
$NewEnt->setWeight(300); #Set the Weight of the entity
$NewEnt->addAdditionalFields("Age","Age Of Person","sastrict","24");
$mt->returnOutput();


Available Functions:

Maltego Transform:
==============
function addEntity($type,$value)
$type: Entity Type
$value: Entity Value

addEntityToMessage(maltegoEntity):
maltegoEntity: MaltegoEntity Object to be added to the outputted "message"

addUIMessage(message,messageType="Inform"):
message: The Message to be displayed
messageType: FatalError/PartialError/Inform/Debug - note this defaults to "Inform" see documentation for additional information

addException(exceptionString):
exceptionString: Exception message to be thrown (eg "Error! Could not connect to 10.4.0.1")

throwExceptions():
Simply return exception XML to the application

returnOutput():
Function to return all the added entities as well as the UI Messages

writeSTDERR(msg):
Function to write msg to STDErr

heartbeat():
Function to produce a "heartbeat"

progress(percent):
Function to output progress, eg MaltegoTransform.progress(20); #20% done

debug(msg)
msg: Debug message to be sent out


Maltego Entity
===========

MaltegoEntity(eT,v)
eT: Entity Type (eg. Person,IPAddress)
v: Value for this entity

setType(type)
Setter for the entity Type property

setValue(value)
Setter for the entity Value property

setWeight(weight)
Setter for the entity Weight property

setDisplayInformation(displayinformation)
Setter for the entity Display Information property

addAdditionalFields(fieldName=None,displayName=None,matchingRule=False,value=None)
Set additional fields for the entity
fieldName: Name used on the code side, eg displayName may be "Age of Person", but the app and your transform will see it as the fieldName variable
displayName: display name of the field shown within the entity properties
matchingRule: either "strict" for strict matching on this specific field or false
value: The additional fields value

setIconURL(iconURL)
Setter for the entity Icon URL (entity Icon) property

returnEntity()
Prints the entity with the correct XML formatting


If you have any issues feel free to contact me -- andrewmohawk@gmail.com