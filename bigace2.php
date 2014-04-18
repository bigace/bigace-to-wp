<?php
/*
Plugin Name: Bigace to Wordpress importer (v2)
Plugin URI: https://github.com/bigace/bigace-to-wp
Description: Import content from a Bigace v2 powered site into WordPress
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
    require_once __DIR__ . '/bigace.class.php';
}

class Bigace_Importer_V2 extends BigaceImporter
{
    public function __construct() {
        parent::__construct();
    }

    public function getImporterName() {
        return 'bigacev2';
    }

    public function getBigaceVersion() {
        return '2.7.8';
    }

    public function buildCidPath($baseDir, $cid, $itemtype) {
        return $baseDir . '/consumer/cid'.$cid.'/items/'.$itemtype.'/';
    }

    public function getBodyForPost($post) {
        $cid = get_option('bigacecommunityid');
        $baseDir = get_option('bigacepath');

        /**
         * @var $id
         * @var $filename
         */
        extract($post);

        $itemFile = $this->buildCidPath($baseDir, $cid, 'html') . '/' . $filename;
        $body = '';
        if (file_exists($itemFile)) {
            return file_get_contents($itemFile);
        }

        return false;
    }

    public function getBigaceConfig($baseDir)
    {
        $configPath = $baseDir . '/system/config/config.system.php';

        if (file_exists($baseDir) && file_exists($configPath)) {
            $_BIGACE = array();
            include $configPath;
            if (isset($_BIGACE['db'])) {
                return array(
                    'bigaceuser'    => $_BIGACE['db']['user'],
                    'bigacepass'    => $_BIGACE['db']['pass'],
                    'bigacename'    => $_BIGACE['db']['name'],
                    'bigacehost'    => $_BIGACE['db']['host'],
                    'spre'          => $_BIGACE['db']['prefix'],
                    'bigacecharset' => $_BIGACE['db']['character-set']
                );
            }
        }

        throw new \Exception('Could not find Bigace v2 configuration at ' .$configPath);
    }

    public function getConsumerIniPath()
    {
        $baseDir = get_option('bigacepath');
        return $baseDir . '/system/config/consumer.ini';
    }

}

$bigace_import_v2 = new Bigace_Importer_V2();
register_importer('bigacev2', 'Bigace v2', __('Import data from a Bigace v2.7.8 site'), array ($bigace_import_v2, 'dispatch'));
