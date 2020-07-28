<?php
namespace hl\mssync;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'restricted access' );
}

/**
 * Destination Configuration Class
 */
class Destination {
    /**
     * @var array
     */
    public $site_ids;
    
    /**
     * @example [ 'post' => 'subsite_post', 'page' => 'page' ] 
     * @var array
     */
    public $post_type_mapping;
    
    /**
     * @var array
     */
    public $add_terms;

    /**
     * @var array
     */
    public $remove_terms;

    /**
     * @var array
     */
    public $add_metadata;

    /**
     * @var array
     */
    public $remove_metadata;
    
    /**
     * @var boolean|int
     */
    public $post_author;
    
    /**
     * @var string
     */
    public $new_post_status;
    
    /**
     * @var array
     */
    public $publish_updates;
    
    /**
     * @var array
     */
    public $sync_terms;
    
    /**
     * @var boolean
     */
    public $sync_metadata;

    /**
     * @var boolean
     */
    public $sync_attachments;

    /**
     * @var boolean|callable
     */
    public $mapping_fn;
    
    function __construct($args) {
        $args += [
            'site_ids' => [],
            'post_type_mapping' => [],
            'add_terms' => [],
            'remove_terms' => [],
            'add_metadata' => [],
            'remove_metadata' => [],
            'post_author' => false,
            'new_post_status' => 'pending',
            'publish_updates' => false,  // by default will be created an auto draft
            'sync_terms' => [ 'category', 'post_tag' ],
            'sync_metadata' => true,
            'sync_attachments' => true,
            'mapping_fn' => false
        ];

        foreach($args as $key => $val){
            $this->$key = $val;
        }
    }
}