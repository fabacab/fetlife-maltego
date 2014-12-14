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
        $this->FL->logIn() OR die("Failed to login to FetLife.com. Last HTML received:\n{$this->FL->connection->cur_page}");
        $this->mt->addUIMessage('Logged in to FetLife as ' . $this->FL->nickname . ' (ID: ' . $this->FL->id . ')');

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

    public function doTransform () {
        switch ($this->input_entity_type) {
            case 'person':
                // TODO: Create the person transform
                break;
            case 'alias':
            default:
                $fl_profile = $this->FL->getUserProfile($this->entity_value);
                if ($fl_profile) {
                    $mt_entity = $this->profile2entity($fl_profile);
                    $this->mt->addEntityToMessage($mt_entity);
                }
                break;
        }
        $this->mt->returnOutput();
    }

    /**
     * Takes a FetLifeProfile object constructed by libFetLife and
     * transforms it into a MaltegoEntity object suitable for output.
     *
     * @param FetLifeProfile $fl_profile
     *
     * @return MaltegoEntity
     */
    private function profile2entity ($fl_profile) {
        $e = new MaltegoEntity('FetLifeKinskter', $fl_profile->nickname);
        $e->addAdditionalFields('fetlife_id', 'FetLife ID', 'strict', $fl_profile->id);
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
$fetlife_transform->doTransform();
