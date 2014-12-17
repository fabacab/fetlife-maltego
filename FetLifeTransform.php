<?php
/**
 * FetLife Maltego
 *
 * A package of transforms for the Maltego OSINT tool that act on FetLife.com.
 *
 * @author maymay bitetheappleback+maltego@gmail.com
 */

require_once dirname(__FILE__) . '/lib/MaltegoTransform-PHP/MaltegoTransform.php';
require_once dirname(__FILE__) . '/lib/FetLife/FetLife.php';

abstract class MaltegoFetLifeEntity {
    public $entity; //< The MaltegoEntity object.

    abstract public function __construct ($entity_value);

    public function getEntity () {
        return $this->entity;
    }
    public function getEntityXML () {
        return $this->entity->returnEntity();
    }
}

/**
 * Takes a FetLifeProfile object constructed by libFetLife and
 * constructs a MaltegoEntity object with it suitable for output.
 *
 * @param FetLifeProfile $fl_profile
 *
 * @return void
 */
class MaltegoFetLifeAffiliation extends MaltegoFetLifeEntity {
    public function __construct ($fl_profile) {
        $this->entity = new MaltegoEntity('maltego.Affiliation.FetLife', $fl_profile->nickname);
        $this->entity->addAdditionalFields('affiliation.uid', 'UID', 'loose', $fl_profile->nickname);
        $this->entity->addAdditionalFields('affiliation.profile-url', 'Profile URL', 'loose', $fl_profile->getPermalink());
        $this->entity->addAdditionalFields('affiliation.network', 'Network', 'loose', 'FetLife');
        $this->entity->addAdditionalFields('fetlife.nickname', 'Nickname', 'strict', $fl_profile->nickname);
        $this->entity->addAdditionalFields('fetlife.id', 'ID', 'strict', $fl_profile->id);
        $this->entity->addAdditionalFields('fetlife.age', 'Age', 'loose', $fl_profile->age);
        $this->entity->addAdditionalFields('fetlife.gender', 'Gender', 'loose', $fl_profile->gender);
        $this->entity->addAdditionalFields('fetlife.role', 'Role', 'loose', $fl_profile->role);
        $this->entity->addAdditionalFields('fetlife.friendcount', 'Friend Count', 'loose', $fl_profile->num_friends);
        $this->entity->addAdditionalFields('country', 'Country', 'loose', $fl_profile->adr['country-name']);
        $this->entity->addAdditionalFields('city', 'City', 'loose', $fl_profile->adr['locality']);
        $this->entity->addAdditionalFields('location.area', 'Area', 'loose', $fl_profile->adr['region']);
        if ($fl_profile->getEvents()) {
            $event_ids = array();
            foreach ($fl_profile->getEvents() as $event) {
                $event_ids[] = $event->id;
            }
            $this->entity->addAdditionalFields('fetlife.upcoming-events', 'Upcoming Events', 'loose', implode(',', $event_ids));
        }
        $this->entity->setIconURL($fl_profile->getAvatarURL());
        $this->entity->setDisplayInformation('<a href="' . $fl_profile->getPermalink() . '">View profile</a> on FetLife');
    }
}

abstract class MaltegoFetLifeObject extends MaltegoFetLifeEntity {
    public function __construct ($entity_value) {
        $this->entity = new MaltegoEntity('maltego.FetLifeObject', $entity_value);
    }
}

class MaltegoFetLifeEventObject extends MaltegoFetLifeObject {
    public function __construct ($entity_value, $event) {
        parent::__construct($entity_value);
        $this->entity->addAdditionalFields('fetlife.type', 'Type', 'loose', 'event');
        $this->entity->addAdditionalFields('fetlife.id', 'ID', 'strict', $event->id);
        $this->entity->addAdditionalFields('country', 'Country', 'loose', $event->adr['country-name']);
        $this->entity->addAdditionalFields('city', 'City', 'loose', $event->adr['locality']);
        $this->entity->addAdditionalFields('location.area', 'Area', 'loose', $event->adr['region']);
        $this->entity->setDisplayInformation('<a href="' . $event->getPermalink() . '">View event</a> on FetLife');
    }
}

class MaltegoTransformFetLife {
    public $transform; //< Which transform to do.
    public $mt;        //< Maltego Transform object

    private $fl_mt_config; // Configuration file that has FetLife connection info.
    private $entity_value; // Selected EntityValue passed in from the Maltego GUI.
    private $parsed_input; // Additional input, after parsing, passed in from GUI.

    public function __construct () {
        global $argc, $argv;
        $this->mt = new MaltegoTransform();

        $this->parseAndShiftOptions($argv, $argc);

        $this->entity_value = $argv[1]; // required by Maltego
        if ($argv[2]) {
            $this->parsed_input = $this->parseFields($argv[2]);
        }

        $this->doTransform($this->transform, $this->entity_value, $this->parsed_input);
    }

    /**
     * Parses options from the command line, shifts them as Maltego expects.
     */
    private function parseAndShiftOptions (&$argv, $argc) {
        $options = getopt('t:', array('transform:'));
        if (!empty($options)) {
            $this->transform = end($options);
            array_splice($argv, 1, $argc - 3); // always leave entity_value and parsed_input
        } else if (0 < strpos($argv[0], '-')) {
            $this->transform = basename(explode('-', $argv[0])[1], '.php');
        } else {
            $this->mt->addException('Unknown transform');
        }
    }

    private function loginToFetLife () {
        $cnf = dirname(__FILE__) . '/fl-mt-config.ini.php';
        $this->fl_mt_config = parse_ini_file($cnf);
        $this->mt->debug("Loaded configuration file: $cnf");
        $FL = new FetLifeUser(
            $this->fl_mt_config['username'], $this->fl_mt_config['password']
        );
        if (!empty($this->fl_mt_config['proxy'])) {
            if ('auto' === $this->fl_mt_config['proxy']) {
                $FL->connection->setProxy('auto');
            } else {
                $p = parse_url($this->fl_mt_config['proxy']);
                $FL->connection->setProxy(
                    "{$p['host']}:{$p['port']}",
                    ('socks' === $p['scheme']) ? CURLPROXY_SOCKS5 : CURLPROXY_HTTP
                );
            }
            $proxy_type = (CURLPROXY_SOCKS5) ? 'SOCKS5' : 'HTTP';
            $this->mt->debug("Using {$proxy_type} proxy at {$FL->connection->proxy_url}");
        }
        if (!$FL->logIn()) {
            $this->mt->addUIMessage("Failed to login to FetLife.com. Have you checked your username and password?", 'FatalError');
            $this->mt->debug('Failed to login to FetLife.com');
            $this->mt->debug("Last HTML received:\n{$FL->connection->cur_page}");
            die();
        } else {
            $this->mt->debug("Logged in to FetLife as {$FL->nickname} with ID {$FL->id}");
        }
        return $FL;
    }

    private function parseFields ($str_input) {
        $arr_fields = explode('#', $str_input);
        $parsed_fields = array();
        foreach ($arr_fields as $field) {
            $x = explode('=', $field);
            $parsed_fields[$x[0]] = $x[1];
        }
        return $parsed_fields;
    }

    private function doTransform ($transform, $entity_value, $parsed_input) {
        $this->mt->debug('Starting transform for ' . $transform);
        switch ($transform) {
            case 'person':
                // TODO: Create the person transform
                break;
            case 'friends':
                // Don't populate(), keeps runtime short.
                $this->transformToFriends($entity_value);
                break;
            case 'location':
                $this->transformToLocation($entity_value, $parsed_input);
                break;
            case 'urls':
                $this->transformToUrls($parsed_input);
                break;
            case 'entitiesbycrawl':
                $this->transformToEntitiesByCrawl($entity_value, $parsed_input);
                break;
            case 'upcomingevents':
                $this->transformToUpcomingEvents($parsed_input['fetlife.upcoming-events']);
                break;
            case 'alias':
                $this->transformAlias($entity_value);
            default:
                $this->mt->addException('Not a recognized transform.');
                break;
        }
        $this->mt->progress(100);
        $this->mt->returnOutput();
    }

    private function isAffiliationEntity ($parsed_input) {
        return ('FetLife' === $parsed_input['affiliation.network']) ? true : false;
    }

    private function transformToUrls ($parsed_input) {
        $url = ($this->isAffiliationEntity($parsed_input))
            ? "/{$parsed_input['affiliation.uid']}"
            : "/{$parsed_input['fetlife.type']}s/{$parsed_input['fetlife.id']}";
        $FL  = $this->loginToFetLife();
        $r   = $FL->connection->doHttpGet($url);
        $dom = new DOMDocument();
        @$dom->loadHTML($r['body']);
        $links = $this->findExternalLinks($dom);
        foreach ($links as $link) {
            $this->mt->addEntityToMessage($this->toURL($link));
        }
    }

    private function transformToLocation ($entity_value, $parsed_input) {
        if ($this->isAffiliationEntity($parsed_input)) {
            $fl_profile = $this->transformAlias($entity_value);
        }
        $this->mt->addEntityToMessage($this->toLocation(array(
            'country-name' => ($fl_profile) ? $fl_profile->adr['country-name'] : $parsed_input['country'],
            'locality' => ($fl_profile) ? $fl_profile->adr['locality'] : $parsed_input['city'],
            'region' => ($fl_profile) ? $fl_profile->adr['region'] : $parsed_input['location.area']
        )));
    }

    private function transformToFriends ($entity_value) {
        $FL = $this->loginToFetLife();
        $friends = $FL->getFriendsOf($entity_value);
        foreach ($friends as $friend) {
            $entity = new MaltegoFetLifeAffiliation($friend);
            $this->mt->addEntityToMessage($entity->getEntity());
        }
    }

    private function transformToUpcomingEvents ($input) {
        if (empty($input)) {
            $this->mt->addException('No available upcoming FetLife event data.');
        } else {
            $ids = explode(',', $input);
            $FL = $this->loginToFetLife();
            foreach ($ids as $id) {
                $event = $FL->getEventById($id);
                $entity = new MaltegoFetLifeEventObject($event->title, $event);
                $this->mt->addEntityToMessage($entity->getEntity());
            }
        }
    }

    /**
     * Given a fetlife.object entity, extracts as much data from the live object as it can.
     */
    private function transformToEntitiesByCrawl ($entity_value, $parsed_input) {
        $FL = $this->loginToFetLife();
        $obj = 'FetLife' . ucfirst($parsed_input['fetlife.type']);
        $obj = new $obj(array(
            'usr' => $FL,
            'id' => $parsed_input['fetlife.id']
        ));

        $obj->populate(true);
        foreach ($obj->getParticipants() as $participant) {
            $entity = new MaltegoFetLifeAffiliation($participant);
            $this->mt->addEntityToMessage($entity->getEntity());
        }

        // TODO:
        // Crawl for email addresses, phone numbers, or other recognizable text
    }

    /**
     * @param string $entity_value An expected alias.
     * @return object $fl_profile A FetLifeProfile object for the alias, or false if none exists.
     */
    private function transformAlias ($entity_value) {
        $FL = $this->loginToFetLife();
        $fl_profile = $FL->getUserProfile($entity_value);
        if ($fl_profile) {
            $entity = new MaltegoFetLifeAffiliation($fl_profile);
            $this->mt->addEntityToMessage($entity->getEntity());
        } else {
            $this->mt->addUIMessage("Could not get any information for {$this->entity_value} from FetLife. Try again later.", 'PartialError');
        }
        return $fl_profile;
    }

    private function toURL ($a_element) {
        // TODO: This is actually XML output, so, y'know....
        $href = htmlentities($a_element->getAttribute('href'), ENT_QUOTES, 'UTF-8');
        $entity = new MaltegoEntity('maltego.URL', $href);
        $entity->addAdditionalFields('url', 'URL', 'strict', $href);
        $entity->addAdditionalFields('short-title', 'Short title', 'loose', $href);
        if ($a_element->getAttribute('title')) {
            $entity->addAdditionalFields('title', 'Title', 'loose', $a_element->getAttribute('title'));
        }
        return $entity;
    }

    private function toLocation ($adr) {
        $entity = new MaltegoEntity('maltego.Location', false);
        $entity->addAdditionalFields('country', 'Country', 'loose', $adr['country-name']);
        $entity->addAdditionalFields('city', 'City', 'strict', $adr['locality']);
        $entity->addAdditionalFields('location.area', 'Area', 'strict', $adr['region']);
        return $entity;
    }

    private function findExternalLinks ($dom) {
        $r = array();
        foreach ($dom->getElementsByTagName('a') as $link) {
            // if href does not start with a fetlife.com address or fragment...
            if (0 === preg_match('/^(?:https?:\/\/fetlife\.com|\/|#)/', $link->getAttribute('href'))) {
                $r[] = $link;
            }
        }
        return $r;
    }
    private function findContentLinks ($dom) {
        $r = array();
        foreach ($dom->getElementsByTagName('a') as $link) {
            // if href links to an internal FetLife object type
            if (1 === preg_match("/^(?:https?:\/\/fetlife\.com)?\//", $link->getAttribute('href'))) {
                $r[] = $link;
            }
        }
        return $r;
    }
}

$fetlife_transform = new MaltegoTransformFetLife();
