<?php
/*
Plugin Name: MultiSite Sync
Description: WordPress Multisite Content Syncronizer
Version:     0.1.0
Author:      hacklab/
Author URI:  https://hacklab.com.br/
License:     GPL2
*/
namespace hl\mssync;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'restricted access' );
}

require_once __DIR__ . '/includes/class.Destination.php';
require_once __DIR__ . '/includes/class.Origin.php';
require_once __DIR__ . '/includes/class.Rule.php';