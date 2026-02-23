/**
 * Event Image Manager – Search & Filter
 *
 * Powers the search bar, date-range filter, category filter and sort
 * selector in the extended gallery template (gallery-with-filters.php).
 *
 * Works in two modes:
 *  1. Client-side: filters the already-rendered .eim-gallery-item elements.
 *  2. Server-side (optional): calls the eim_search_images AJAX action.
 *
 * Mode is selected based on whether eimSearch.useAjax is truthy.
 */
( function ( $ ) {
    'use strict';

    if ( ! $( '#eim-gallery-grid' ).length ) {
        return;
    }

    // ── State ────────────────────────────────────────────────────────────────
    var state = {
        search:   '',
        dateFrom: '',
        dateTo:   '',
        category: '',
        orderby:  'date_desc',
    };

    // ── Cache selectors ───────────────────────────────────────────────────────
    var $grid       = $( '#eim-gallery-grid' );
    var $search     = $( '#eim-search-input' );
    var $dateFrom   = $( '#eim-filter-date-from' );
    var $dateTo     = $( '#eim-filter-date-to' );
    var $category   = $( '#eim-filter-category' );
    var $sort       = $( '#eim-sort-order' );
    var $apply      = $( '#eim-apply-filters' );
    var $reset      = $( '#eim-reset-filters' );
    var $noResults  = $( '.eim-no-results' );

    // ── Load category options via AJAX ────────────────────────────────────────
    if ( typeof eimSearch !== 'undefined' && eimSearch.postId ) {
        $.get(
            eimSearch.ajaxUrl,
            {
                action:   'eim_list_categories',
                nonce:    eimSearch.nonce,
                post_id:  eimSearch.postId,
            },
            function ( res ) {
                if ( res.success && res.data.length ) {
                    $.each( res.data, function ( i, cat ) {
                        $category.append(
                            $( '<option>' ).val( cat ).text( cat )
                        );
                    } );
                }
            }
        );
    }

    // ── Apply filters ─────────────────────────────────────────────────────────

    function applyFilters() {
        state.search   = $search.val().trim().toLowerCase();
        state.dateFrom = $dateFrom.val();
        state.dateTo   = $dateTo.val();
        state.category = $category.val();
        state.orderby  = $sort.val();

        if ( typeof eimSearch !== 'undefined' && eimSearch.useAjax ) {
            applyAjax();
        } else {
            applyClientSide();
        }
    }

    // ── Client-side filtering ─────────────────────────────────────────────────

    function applyClientSide() {
        var $items  = $grid.find( '.eim-gallery-item' );
        var visible = 0;

        $items.each( function () {
            var $item      = $( this );
            var imgAlt     = $item.find( 'img' ).attr( 'alt' ) || '';
            var tags       = parseJson( $item.data( 'tags' ), [] );
            var categories = parseJson( $item.data( 'categories' ), [] );

            var show = true;

            // Search filter.
            if ( state.search ) {
                var haystack = ( imgAlt + ' ' + tags.join( ' ' ) ).toLowerCase();
                if ( haystack.indexOf( state.search ) === -1 ) {
                    show = false;
                }
            }

            // Category filter.
            if ( show && state.category ) {
                if ( categories.indexOf( state.category ) === -1 ) {
                    show = false;
                }
            }

            $item.toggle( show );
            if ( show ) { visible++; }
        } );

        $noResults.toggle( visible === 0 );
    }

    // ── AJAX filtering ────────────────────────────────────────────────────────

    function applyAjax() {
        $.get(
            eimSearch.ajaxUrl,
            {
                action:    'eim_search_images',
                nonce:     eimSearch.nonce,
                post_id:   eimSearch.postId,
                search:    state.search,
                date_from: state.dateFrom,
                date_to:   state.dateTo,
                category:  state.category,
                orderby:   state.orderby,
            },
            function ( res ) {
                if ( ! res.success ) { return; }

                var visibleIndices = {};
                $.each( res.data, function ( i, img ) {
                    visibleIndices[ img.index ] = true;
                } );

                var $items   = $grid.find( '.eim-gallery-item' );
                var visible  = 0;

                $items.each( function () {
                    var idx  = parseInt( $( this ).data( 'index' ), 10 );
                    var show = !! visibleIndices[ idx ];
                    $( this ).toggle( show );
                    if ( show ) { visible++; }
                } );

                $noResults.toggle( visible === 0 );
            }
        );
    }

    // ── Reset ─────────────────────────────────────────────────────────────────

    function resetFilters() {
        $search.val( '' );
        $dateFrom.val( '' );
        $dateTo.val( '' );
        $category.val( '' );
        $sort.val( 'date_desc' );
        $grid.find( '.eim-gallery-item' ).show();
        $noResults.hide();
    }

    // ── Event bindings ────────────────────────────────────────────────────────

    $apply.on( 'click', applyFilters );
    $reset.on( 'click', resetFilters );

    // Live search on input (debounced).
    var debounceTimer;
    $search.on( 'input', function () {
        clearTimeout( debounceTimer );
        debounceTimer = setTimeout( applyFilters, 300 );
    } );

    // ── Helpers ───────────────────────────────────────────────────────────────

    function parseJson( str, fallback ) {
        try {
            return JSON.parse( str );
        } catch ( e ) {
            return fallback;
        }
    }

}( jQuery ) );
