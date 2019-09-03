<?php

namespace hl\mssync;

if (!defined('ABSPATH')) {
    exit('restricted access');
}

/**
 * Synchronization Rule Class
 */
class Rule
{
    static $inside_sync_post = false;

    static $updated_posts = [];

    /**
     * Undocumented variable
     *
     * @var Origin
     */
    public $origin;

    /**
     * Undocumented variable
     *
     * @var Destination
     */
    public $destination;

    public $current_blog_id;

    function __construct(Origin $origin, Destination $destination) {
        $this->origin = clone $origin;
        $this->destination = clone $destination;

        $this->current_blog_id = get_current_blog_id();

        add_action('save_post', [$this, 'sync_post'], 0, 3);
    }

    /**
     * Is the post elegible to be synced?
     * 
     * @param \WP_Post $post
     * @return boolean
     */
    protected function is_eligible($post) {
        $origin = $this->origin;

        // verify if the current blog id is in the list of sites that must be synced
        if (!in_array($this->current_blog_id, $this->getOriginSiteIds())) {
            return false;
        }

        // verify if the post type is in the list of post types that must be synced
        if (!in_array($post->post_type, $origin->post_types)) {
            return false;
        }

        // verify if the post_status is in the list of post statuses that must be synced
        if (!in_array($post->post_status, $origin->post_status)) {
            return false;
        }

        // verify if the post author is in the list of post authors that must be synced
        if ($origin->post_author && !in_array($post->post_author, $origin->post_author)) {
            return false;
        }

        // verify if the post has at last one term for each configured taxonomy terms
        $has_term = true;
        foreach ($origin->terms as $taxonomy => $terms) {
            if (!has_term($terms, $taxonomy)) {
                $has_term = false;
            }
        }
        if (!$has_term) {
            return false;
        }

        return true;
    }

    protected function _getSiteIds($config) {
        if (is_array($config)) {
            return $config;
        } else if (is_callable($config)) {
            $sites = [];
            foreach (get_sites() as $site) {
                if ($config($site->blog_id)) {
                    $sites[] = $site->blog_id;
                }
            }

            return $sites;
        } else {
            return array_map(function ($site) {
                return $site->blog_id;
            }, get_sites());
        }
    }

    /**
     * Prepare and return an array with metadata to be synced
     *
     * @param integer $post_id
     * @return array
     */
    function prepare_metadata(int $post_id) {
        $dest = $this->destination;

        $metadata = [];

        // get origin post metadata
        if ($dest->sync_metadata) {
            $metadata = get_post_meta($post_id);
        }

        // includes the metadata of add_metadata configuration
        foreach ($dest->add_metadata as $key => $values) {
            if (!is_array($values)) {
                $values = [$values];
            }

            if (isset($metadata[$key])) {
                $metadata[$key] = array_merge($metadata[$key], $values);
            } else {
                $metadata[$key] = $values;
            }
        }

        // removes metadata of remove_metadata configuration
        foreach ($dest->remove_metadata as $key) {
            unset($metadata[$key]);
        }

        return $metadata;
    }

    /**
     * Prepare and returns an array with terms to be synced
     * 
     * @var integer $post_id
     * 
     * @return array
     */
    function prepare_taxonomy_terms(int $post_id) {
        $dest = $this->destination;

        $taxonomy_terms = [];

        // includes the terms of origin post
        foreach ($dest->sync_terms as $taxonomy) {
            $terms = get_the_terms($post_id, $taxonomy);

            if (is_array($terms)) {
                $taxonomy_terms[$taxonomy] = array_map(function ($term) {
                    return $term->name;
                }, $terms);
            }
        }

        // includes the terms of the add_terms configuration
        foreach ($dest->add_terms as $taxonomy => $terms) {
            if (isset($taxonomy_terms[$taxonomy])) {
                $taxonomy_terms[$taxonomy] = array_unique(array_merge($terms, $taxonomy_terms[$taxonomy]));
            } else {
                $taxonomy_terms[$taxonomy] = $terms;
            }
        }

        // removes the terms of the remove_terms configuration
        foreach ($dest->remove_terms as $taxonomy => $terms_to_remove) {
            if (isset($taxonomy_terms[$taxonomy])) {
                $taxonomy_terms[$taxonomy] = array_filter($taxonomy_terms[$taxonomy], function ($term) use ($terms_to_remove) {
                    if (!in_array($term, $terms_to_remove)) {
                        return $term;
                    }
                });
            }
        }

        return $taxonomy_terms;
    }

    function getOriginSiteIds() {
        return $this->_getSiteIds($this->origin->site_ids);
    }

    function getDestinationSiteIds() {
        return $this->_getSiteIds($this->destination->site_ids);
    }

    /**
     * Syncronize the post
     *
     * @todo sync attachments
     * 
     * @param int $post_id
     * @return void
     */
    function sync_post($post_id, $post, $update) {
        // to prevent loop
        if (self::$inside_sync_post) {
            return;
        }

        // don't sync revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($post_id)) {
            return;
        }
        if (wp_is_post_autosave($post_id)) {
            return;
        }

        // if new post
        if (!$update) {
            return;
        }

        // verify if the post is eligible to be synced
        if (!$this->is_eligible($post)) {
            return;
        }

        // to prevent recursions
        self::$inside_sync_post = true;

        // metadata to be synced
        $metadata = $this->prepare_metadata($post_id);

        // terms to be synced
        $taxonomy_terms = $this->prepare_taxonomy_terms($post_id);

        // ids of the destination sites
        $site_ids = $this->getDestinationSiteIds();

        foreach ($site_ids as $site_id) {
            if ($site_id == $this->current_blog_id) {
                continue;
            }

            // synchronize the post to site
            $this->sync_post_to_site($site_id, $post, $metadata, $taxonomy_terms);
        }

        // to prevent loop
        self::$inside_sync_post = false;
    }

    /**
     * Synchronizes the post to site
     *
     * @param int $site_id
     * @param object $post
     * @param array $metadata
     * @param array $taxonomy_terms
     * 
     * @return integer id of the synchronized post 
     */
        global $wpdb;
    protected function sync_post_to_site(int $site_id, $post, array $metadata, array $taxonomy_terms) {
        $dest = $this->destination;
        
        // metadata that represents the relation between original post and their copies
        $relation_meta_key = 'SYNC:origin';
        $relation_meta_value = $this->current_blog_id . ':' . $post->ID;
        switch_to_blog($site_id);

        $_post = clone $post;
        foreach(['ID', 'guid', 'post_date', 'post_date_gmt', 'post_modified', 'post_'] as $prop){
            unset($_post->$prop);
        }

        $destination_id = $wpdb->get_var("
            SELECT post_id 
            FROM $wpdb->postmeta 
            WHERE meta_key = '$relation_meta_key' AND 
                  meta_value = '$relation_meta_value'");
        
                  
        if ($destination_id) {
            $new_post = false;
            if ($dest->publish_updates) {
                $_post->ID = $destination_id;
                $_post->post_status = 'publish';
            } else {
                $old_autosave = wp_get_post_autosave($destination_id);
                if ($old_autosave) {
                    wp_delete_post_revision($old_autosave->ID);
                }
                $_post->ID = $destination_id;
                $_post = (object) _wp_post_revision_data((array) $_post, true);
            }
        } else {
            $new_post = true;
            $_post->post_status = $dest->new_post_status;
        }

        if ($new_post) {
            $destination_id = wp_insert_post($_post);
            $_post->ID = $destination_id;    
            add_post_meta($destination_id, $relation_meta_key, $relation_meta_value);
        } else {
            if ($_post->ID) {
                wp_update_post($_post);
            } else {
                $_post->ID = wp_insert_post($_post);
            }
        }

        foreach ($taxonomy_terms as $taxonomy => $terms) {
            wp_set_object_terms($destination_id, $terms, $taxonomy);
        }

        foreach($metadata as $meta_key => $meta_values){
            delete_post_meta($destination_id, $meta_key);
            foreach ($meta_values as $value) {
                add_post_meta($destination_id, $meta_key, $value);
            }
        }

        switch_to_blog($this->current_blog_id);

        return $_post->ID;
    }
}
