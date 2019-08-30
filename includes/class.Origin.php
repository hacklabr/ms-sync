<?php
namespace hl\mssync;

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'restricted access' );
}

/**
 * Origin configuration class
 */
class Origin {
    /**
     * @var array|callable(int):bool 
     */
    public $site_ids;

    /**
     * @var array
     */
    public $post_types;
    
    /**
     * @var array
     */
    public $post_status;
   
    /** 
     * @var boolean|array
     */
    public $post_author;

    /**
     * @example [ 'category' => ['news', 'featured']]
     * 
     * @var array
     */
    public $terms = [];

    function __construct(array $args){
        $args += [
            'site_ids' => [],
            'post_types' => [ 'post' ],
            'post_status' => [ 'publish' ],
            'post_author' => false, 
            'terms' => []
        ];

        foreach($args as $key => $val){
            $this->$key = $val;
        }
    }
}