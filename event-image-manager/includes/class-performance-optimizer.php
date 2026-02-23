<?php
/**
 * Class EIM_Performance_Optimizer
 *
 * Improves frontend performance through transient-based gallery caching,
 * lazy loading, and optional CDN URL rewriting.
 *
 * Options:
 *   - `eim_cdn_base_url` : CDN base URL (e.g. https://cdn.example.com).
 *
 * Transient key format: `eim_gallery_cache_{post_id}`.
 *
 * Hook: `save_post_event` to bust the cache when an event is updated.
 */
class EIM_Performance_Optimizer {

    /** Cache duration in seconds (default: 1 hour). */
    const CACHE_TTL = 3600;

    /** Transient key prefix. */
    const CACHE_PREFIX = 'eim_gallery_cache_';

    public function __construct() {
        add_action( 'save_post_event', array( $this, 'bust_cache' ) );
    }

    // -------------------------------------------------------------------------
    // Caching
    // -------------------------------------------------------------------------

    /**
     * Cache gallery data for a post using a WordPress transient.
     *
     * @param int   $post_id Event post ID.
     * @param mixed $data    Gallery data to cache.
     */
    public function cache_gallery_data( $post_id, $data ) {
        set_transient( self::CACHE_PREFIX . absint( $post_id ), $data, self::CACHE_TTL );
    }

    /**
     * Retrieve cached gallery data for a post.
     *
     * @param  int        $post_id Event post ID.
     * @return mixed|false         Cached data or false if not cached.
     */
    public function get_cached_gallery( $post_id ) {
        return get_transient( self::CACHE_PREFIX . absint( $post_id ) );
    }

    /**
     * Delete the gallery cache for a post.
     *
     * @param int $post_id Event post ID.
     */
    public function bust_cache( $post_id ) {
        delete_transient( self::CACHE_PREFIX . absint( $post_id ) );
    }

    // -------------------------------------------------------------------------
    // CDN URL rewriting
    // -------------------------------------------------------------------------

    /**
     * Return the CDN URL for a file path if a CDN is configured,
     * otherwise return the standard WordPress upload URL.
     *
     * @param  string $path Absolute server path or relative URL fragment.
     * @return string       Full URL to the image.
     */
    public function get_image_url( $path ) {
        $cdn_base = rtrim( get_option( 'eim_cdn_base_url', '' ), '/' );

        if ( $cdn_base ) {
            $upload_dir = wp_upload_dir();
            $relative   = str_replace( $upload_dir['basedir'], '', $path );
            return $cdn_base . '/' . ltrim( $relative, '/' );
        }

        // Fall back to the standard uploads URL.
        $upload_dir = wp_upload_dir();
        $relative   = str_replace( $upload_dir['basedir'], '', $path );
        return $upload_dir['baseurl'] . $relative;
    }
}
