<?php
/**
 * FetLife Maltego
 *
 * A package of transforms for the Maltego OSINT tool that act on FetLife.com.
 *
 * @author maymay bitetheappleback+maltego@gmail.com
 */

class FetLifeTransform {
    public $input_entity_type;
    public $mt; // Maltego Transform object

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
        if ($this->fl_mt_config['proxy']) {
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

        $this->input_entity_type = basename(explode('-', $argv[0])[1], '.php');
        if (empty($this->input_entity_type)) {
            $this->mt->addException('Unknown Input Entity Type');
        } else {
            $this->mt->debug('Starting transform for ' . $this->input_entity_type);
        }
        $this->entity_value = $argv[1]; // required by Maltego
        if ($argv[2]) {
            $this->parsed_input = $this->parseFields($argv[2]);
        }
        // TODO: Figure out a more reasonable way to estimate progress.
        $this->mt->progress(15);
        $this->doTransform($this->input_entity_type, $this->entity_value);
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

    private function doTransform ($type, $entity_value) {
        switch ($type) {
            case 'person':
                // TODO: Create the person transform
                break;
            case 'friends':
                // Don't populate(), keeps runtime short.
                $this->transformToFriends($entity_value, false);
                break;
            case 'alias':
            default:
                $this->transformAlias($entity_value);
                break;
        }
        $this->mt->progress(100);
        $this->mt->returnOutput();
    }

    private function transformToFriends ($entity_value) {
        $friends = $this->FL->getFriendsOf($entity_value);
        foreach ($friends as $friend) {
            $this->mt->addEntityToMessage($this->toFetLifeAffiliation($friend));
        }
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
        $e = new MaltegoEntity('maltego.Affiliation.FetLife', $fl_profile->nickname);
        $e->addAdditionalFields('affiliation.uid', 'UID', 'loose', $fl_profile->nickname);
        $e->addAdditionalFields('affiliation.profile-url', 'Profile URL', 'loose', $fl_profile->getPermalink());
        $e->addAdditionalFields('affiliation.network', 'Network', 'loose', 'FetLife');
        $e->addAdditionalFields('fetlife.nickname', 'Nickname', 'strict', $fl_profile->nickname);
        $e->addAdditionalFields('fetlife.id', 'ID', 'strict', $fl_profile->id);
        $e->addAdditionalFields('fetlife.age', 'Age', 'loose', $fl_profile->age);
        $e->addAdditionalFields('fetlife.gender', 'Gender', 'loose', $fl_profile->gender);
        $e->addAdditionalFields('fetlife.role', 'Role', 'loose', $fl_profile->role);
        $e->addAdditionalFields('fetlife.friendcount', 'Friend Count', 'loose', $fl_profile->num_friends);
        $e->setIconURL($fl_profile->getAvatarURL());
        $e->setDisplayInformation('<a href="' . $fl_profile->getPermalink() . '">View profile</a> on FetLife');
        return $e;
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
