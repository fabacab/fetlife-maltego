<?php
/**
 * FetLife Maltego
 *
 * A package of transforms for the Maltego OSINT tool that act on FetLife.com.
 *
 * @author maymay bitetheappleback+maltego@gmail.com
 */

class FetLifeTransform {
    public $transform; //< Which transform to do.
    public $mt;        //< Maltego Transform object

    private $fl_mt_config; // Configuration file that has FetLife connection info.
    private $entity_value; // Selected EntityValue passed in from the Maltego GUI.
    private $parsed_input; // Additional input, after parsing, passed in from GUI.

    public function __construct ($argv) {
        require_once dirname(__FILE__) . '/lib/MaltegoTransform-PHP/MaltegoTransform.php';
        require_once dirname(__FILE__) . '/lib/FetLife/FetLife.php';

        $this->mt = new MaltegoTransform();

        $this->fl_mt_config = parse_ini_file(dirname(__FILE__) . '/fl-mt-config.ini.php');

        $this->FL = new FetLifeUser(
            $this->fl_mt_config['username'], $this->fl_mt_config['password']
        );
        if (!empty($this->fl_mt_config['proxy'])) {
            if ('auto' === $this->fl_mt_config['proxy']) {
                $this->FL->connection->setProxy('auto');
            } else {
                $p = parse_url($this->fl_mt_config['proxy']);
                $this->FL->connection->setProxy(
                    "{$p['host']}:{$p['port']}",
                    ('socks' === $p['scheme']) ? CURLPROXY_SOCKS5 : CURLPROXY_HTTP
                );
            }
        }
        if (!$this->FL->logIn()) {
            $this->mt->addUIMessage("Failed to login to FetLife.com. Have you checked your username and password?", 'FatalError');
            $this->mt->debug("Last HTML received:\n{$this->FL->connection->cur_page}");
            die();
        } else {
            $this->mt->addUIMessage("Logged in to FetLife as {$this->FL->nickname} with ID {$this->FL->id}");
            $this->mt->progress(10);
        }

        $this->transform = basename(explode('-', $argv[0])[1], '.php');
        if (empty($this->transform)) {
            $this->mt->addException('Unknown Input Entity Type');
        } else {
            $this->mt->debug('Starting transform for ' . $this->transform);
        }
        $this->entity_value = $argv[1]; // required by Maltego
        if ($argv[2]) {
            $this->parsed_input = $this->parseFields($argv[2]);
        }
        // TODO: Figure out a more reasonable way to estimate progress.
        $this->mt->progress(15);
        $this->doTransform($this->transform, $this->entity_value);
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

    private function doTransform ($transform, $entity_value) {
        switch ($transform) {
            case 'person':
                // TODO: Create the person transform
                break;
            case 'friends':
                // Don't populate(), keeps runtime short.
                $this->transformToFriends($entity_value);
                break;
            case 'location':
                $this->transformToLocation($entity_value, $this->parsed_input);
                break;
            case 'urls':
                $this->transformToUrls($entity_value);
                break;
            case 'entitiesbycrawl':
                $this->transformToEntitiesByCrawl($entity_value, $this->parsed_input);
                break;
            case 'upcomingevents':
                $this->transformToUpcomingEvents($this->parsed_input['fetlife.upcoming-events']);
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

    private function transformToUrls ($entity_value) {
        $r = $this->FL->connection->doHttpGet("/$entity_value");
        $dom = new DOMDocument();
        @$dom->loadHTML($r['body']);
        $links = $this->findExternalLinks($dom);
        foreach ($links as $link) {
            $this->mt->addEntityToMessage($this->toURL($link));
        }
    }

    private function transformToLocation ($entity_value, $parsed_input) {
        if (!empty($parsed_input['country'])) {
            $this->mt->addEntityToMessage($this->toLocation(array(
                'country-name' => $parsed_input['country'],
                'locality'     => $parsed_input['city'],
                'region'       => $parsed_input['location.area']
            )));
        }
    }

    private function transformToFriends ($entity_value) {
        $friends = $this->FL->getFriendsOf($entity_value);
        foreach ($friends as $friend) {
            $this->mt->addEntityToMessage($this->toFetLifeAffiliation($friend));
        }
    }

    private function transformToUpcomingEvents ($input) {
        if (empty($input)) {
            $this->mt->addException('No available upcoming FetLife event data.');
        } else {
            $ids = explode(',', $input);
            foreach ($ids as $id) {
                $event = $this->FL->getEventById($id);
                $entity = new MaltegoEntity('maltego.FetLifeObject', $event->title);
                $entity->addAdditionalFields('fetlife.type', 'Type', 'loose', 'event');
                $entity->addAdditionalFields('fetlife.id', 'ID', 'strict', $event->id);
                $entity->addAdditionalFields('country', 'Country', 'loose', $event->adr['country-name']);
                $entity->addAdditionalFields('city', 'City', 'loose', $event->adr['locality']);
                $entity->addAdditionalFields('location.area', 'Area', 'loose', $event->adr['region']);
                $entity->setDisplayInformation('<a href="' . $event->getPermalink() . '">View event</a> on FetLife');
                $this->mt->addEntityToMessage($entity);
            }
        }
    }

    /**
     * Given a fetlife.object entity, extracts as much data from the live object as it can.
     */
    private function transformToEntitiesByCrawl ($entity_value, $parsed_input) {
        $obj = 'FetLife' . ucfirst($parsed_input['fetlife.type']);
        $obj = new $obj(array(
            'usr' => $this->FL,
            'id' => $parsed_input['fetlife.id']
        ));

        $obj->populate(true);
        foreach ($obj->getParticipants() as $participant) {
            $this->mt->addEntityToMessage($this->toFetLifeAffiliation($participant));
        }
        if ($obj->adr) {
            $this->mt->addEntityToMessage($this->toLocation($obj->adr));
        }

        // TODO:
        // Crawl for email addresses, phone numbers, or other recognizable text
    }

    private function transformAlias ($entity_value) {
        $fl_profile = $this->FL->getUserProfile($entity_value);
        if ($fl_profile) {
            $mt_entity = $this->toFetLifeAffiliation($fl_profile);
            $this->mt->addEntityToMessage($mt_entity);
        } else {
            $this->mt->addUIMessage("Could not get any information for {$type} {$this->entity_value} from FetLife. Try again later.");
        }
    }

    /**
     * Takes a FetLifeProfile object constructed by libFetLife and
     * transforms it into a MaltegoEntity object suitable for output.
     *
     * @param FetLifeProfile $fl_profile
     *
     * @return MaltegoEntity
     */
    private function toFetLifeAffiliation ($fl_profile) {
        $entity = new MaltegoEntity('maltego.Affiliation.FetLife', $fl_profile->nickname);
        $entity->addAdditionalFields('affiliation.uid', 'UID', 'loose', $fl_profile->nickname);
        $entity->addAdditionalFields('affiliation.profile-url', 'Profile URL', 'loose', $fl_profile->getPermalink());
        $entity->addAdditionalFields('affiliation.network', 'Network', 'loose', 'FetLife');
        $entity->addAdditionalFields('fetlife.nickname', 'Nickname', 'strict', $fl_profile->nickname);
        $entity->addAdditionalFields('fetlife.id', 'ID', 'strict', $fl_profile->id);
        $entity->addAdditionalFields('fetlife.age', 'Age', 'loose', $fl_profile->age);
        $entity->addAdditionalFields('fetlife.gender', 'Gender', 'loose', $fl_profile->gender);
        $entity->addAdditionalFields('fetlife.role', 'Role', 'loose', $fl_profile->role);
        $entity->addAdditionalFields('fetlife.friendcount', 'Friend Count', 'loose', $fl_profile->num_friends);
        if ($fl_profile->getEvents()) {
            $event_ids = array();
            foreach ($fl_profile->getEvents() as $event) {
                $event_ids[] = $event->id;
            }
            $entity->addAdditionalFields('fetlife.upcoming-events', 'Upcoming Events', 'loose', implode(',', $event_ids));
        }
        $entity->setIconURL($fl_profile->getAvatarURL());
        $entity->setDisplayInformation('<a href="' . $fl_profile->getPermalink() . '">View profile</a> on FetLife');
        return $entity;
    }

    private function toURL ($a_element) {
        $href = $a_element->getAttribute('href');
        $entity = new MaltegoEntity('maltego.URL', $href);
        $entity->addAdditionalFields('url', 'URL', 'strict', $href);
        $entity->addAdditionalFields('short-title', 'Short title', 'loose', $href);
        if ($a_element->getAttribute('title')) {
            $entity->addAdditionalFields('title', 'Title', 'loose', $a_element->getAttribute('title'));
        }
        return $entity;
    }

    private function toLocation ($adr, $name = false) {
        if (!$name) {
            $name = "{$adr['locality']}, {$adr['country-name']}";
        }
        $entity = new MaltegoEntity('maltego.Location', $name);
        $entity->addAdditionalFields('country', 'Country', 'strict', $adr['country-name']);
        $entity->addAdditionalFields('city', 'City', 'strict', $adr['locality']);
        $entity->addAdditionalFields('location.area', 'Area', 'strict', $adr['region']);
        return $entity;
    }

    // TODO
//    private function toFetLifeObject () {
//        $entity = new MaltegoEntity('maltego.FetLifeObject', );
//    }

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

    private function var_dump ($x) {
        ob_start();
        var_dump($x);
        $str = ob_get_contents();
        ob_end_clean();
        $this->mt->debug($str);
    }
}

$fetlife_transform = new FetLifeTransform($argv);
