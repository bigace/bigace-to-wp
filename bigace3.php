<?php
/*
Plugin Name: Bigace to Wordpress importer (v3)
Plugin URI: https://github.com/bigace/bigace-to-wp
Description: Import content from a Bigace v3 powered site into WordPress
Author: Kevin Papst
Author URI: http://www.kevinpapst.de/
License: Public Domain or CC0 ( http://creativecommons.org/publicdomain/zero/1.0/ )
Version: 1.2
Changelog: Please see README.md
Kudos: Code was forked from the s9y (serendipity) importer and adjusted to work with Bigace, see https://raw.github.com/ShakataGaNai/s9y-to-wp/
*/

/* borrowed from movable type importer plugin */
if ( !defined('WP_LOAD_IMPORTERS') ) {
	return;
}

if ( !class_exists('BigaceImporter') ) {
    require_once dirname(__FILE__) . '/bigace.class.php';
}

class Bigace_Importer_V3 extends BigaceImporter
{
    public function __construct() {
        parent::__construct();
    }

    public function getImporterName() {
        return 'bigacev3';
    }

    public function getBigaceVersion() {
        return '3';
    }

    public function buildCidPath($baseDir, $cid, $itemtype)
    {
        switch ($itemtype) {
            case 'image':
            case 'file':
                $itemtype = $itemtype . 's';
                break;
            default:
                break;
        }

        return $baseDir . '/sites/cid'.$cid.'/'.$itemtype.'/';
    }

    public function getBodyForPost($post) {

        /**
         * @var $id
         * @var $filename
         */
        extract($post);

        $bigacedb = $this->connect_bigacedb();
        $prefix = get_option('spre');
        $cid = get_option('bigacecommunityid');
        $baseDir = get_option('bigacepath');
        $body = '';

        $results = $bigacedb->get_results(
            'SELECT * FROM '.$prefix.'content WHERE cid='.$cid.' AND id='.$id
        );

        if ($results === false || empty($results)) {
            return false;
        }

        foreach ($results as $postEntry) {
            if (strlen(trim($postEntry->content)) == 0)
                continue;
            $body .= "\n<!-- [".$postEntry->language."] " . $postEntry->name . " --> \n";
            $body .= $postEntry->content;
        }

        return $body;
    }

    public function getBigaceConfig($baseDir)
    {
        $configPath = $baseDir . '/application/bigace/configs/bigace.php';

        if (file_exists($baseDir) && file_exists($configPath)) {
            $config = include $configPath;
            if (isset($config['database']) && !empty($config['database'])) {
                return array(
                    'bigaceuser'    => $config['database']['user'],
                    'bigacepass'    => $config['database']['pass'],
                    'bigacename'    => $config['database']['name'],
                    'bigacehost'    => $config['database']['host'],
                    'spre'          => $config['database']['prefix'],
                    'bigacecharset' => $config['database']['charset']
                );
            }
        }

        throw new \Exception('Could not find Bigace v3 configuration at ' . $configPath);
    }

    public function getConsumerIniPath()
    {
        $baseDir = get_option('bigacepath');
        return $baseDir . '/application/bigace/configs/consumer.ini';
    }

}

$bigace_import_v3 = new Bigace_Importer_V3();
register_importer('bigacev3', 'Bigace v3', __('Import data from a Bigace v3 site'), array ($bigace_import_v3, 'dispatch'));
