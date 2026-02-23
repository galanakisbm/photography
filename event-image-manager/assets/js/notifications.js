/**
 * Event Image Manager – Notifications
 *
 * Handles loading, displaying, and marking notifications as read.
 * Updates the admin bar badge count.
 * Auto-refreshes every 60 seconds.
 *
 * Expects localised object `eimNotifications` with:
 *   - ajaxUrl
 *   - nonce
 *   - i18n  { loading, noNotifications, error, markRead, allRead }
 */
( function ( $ ) {
    'use strict';

    if ( typeof eimNotifications === 'undefined' ) {
        return;
    }

    var REFRESH_MS  = 60000;
    var $tabAll      = $( '#eim-notifications-all' );
    var $tabUnread   = $( '#eim-notifications-unread' );
    var $tabMessages = $( '#eim-notifications-messages' );
    var $markAllBtn  = $( '#eim-mark-all-read' );
    var $tmpl        = $( '#eim-notification-tmpl' );
    var $emptyTmpl   = $( '#eim-notification-empty-tmpl' );

    // ── Load notifications ────────────────────────────────────────────────────
    function loadNotifications() {
        $.post(
            eimNotifications.ajaxUrl,
            { action: 'eim_get_notifications', nonce: eimNotifications.nonce },
            function ( res ) {
                if ( ! res.success || ! res.data ) {
                    renderError( $tabAll );
                    return;
                }

                var all      = res.data.notifications || [];
                var unread   = all.filter( function ( n ) { return ! n.is_read; } );
                var messages = all.filter( function ( n ) { return 'message' === n.type; } );

                renderList( $tabAll,      all );
                renderList( $tabUnread,   unread );
                renderList( $tabMessages, messages );

                $( '#eim-count-all' ).text( all.length );
                $( '#eim-count-unread' ).text( unread.length );
                $( '#eim-count-messages' ).text( messages.length );

                updateAdminBarBadge( unread.length );
            }
        ).fail( function () {
            renderError( $tabAll );
        } );
    }

    function renderList( $list, notifications ) {
        $list.empty();
        if ( ! notifications.length ) {
            if ( $emptyTmpl.length ) {
                $list.append( $( $emptyTmpl.html() ) );
            } else {
                $list.html( '<li class="eim-notification-empty">' + eimNotifications.i18n.noNotifications + '</li>' );
            }
            return;
        }
        $.each( notifications, function ( i, n ) {
            $list.append( buildNotificationItem( n ) );
        } );
    }

    function buildNotificationItem( n ) {
        var $li = $( '<li>' ).addClass( 'eim-notification-item' )
            .attr( 'data-id', n.id )
            .addClass( n.is_read ? 'eim-notification-item--read' : 'eim-notification-item--unread' );

        var iconMap = {
            message:  '&#x2709;',
            download: '&#x1F4E5;',
            payment:  '&#x1F4B3;',
            system:   '&#x2139;',
        };
        var icon = iconMap[ n.type ] || '&#x1F514;';

        $li.html(
            '<span class="eim-notification-icon" aria-hidden="true">' + icon + '</span>' +
            '<div class="eim-notification-body">' +
                '<p class="eim-notification-message">' + $( '<p>' ).text( n.message ).html() + '</p>' +
                '<time class="eim-notification-time">' + $( '<time>' ).text( n.time_ago ).html() + '</time>' +
            '</div>' +
            ( ! n.is_read
                ? '<button type="button" class="eim-notification-mark-read button button-small"' +
                  ' aria-label="' + eimNotifications.i18n.markRead + '">' +
                  eimNotifications.i18n.markRead + '</button>'
                : '' )
        );
        return $li;
    }

    function renderError( $list ) {
        $list.html( '<li class="eim-notification-error">' + eimNotifications.i18n.error + '</li>' );
    }

    // ── Mark single notification as read ─────────────────────────────────────
    $( document ).on( 'click', '.eim-notification-mark-read', function () {
        var $btn = $( this );
        var $li  = $btn.closest( '.eim-notification-item' );
        var id   = $li.data( 'id' );

        $.post(
            eimNotifications.ajaxUrl,
            { action: 'eim_mark_notification_read', nonce: eimNotifications.nonce, notification_id: id },
            function ( res ) {
                if ( res.success ) {
                    $li.addClass( 'eim-notification-item--read' ).removeClass( 'eim-notification-item--unread' );
                    $btn.remove();
                    decrementBadge();
                    decrementCount( '#eim-count-unread' );
                }
            }
        );
    } );

    // ── Mark all as read ──────────────────────────────────────────────────────
    $markAllBtn.on( 'click', function () {
        $.post(
            eimNotifications.ajaxUrl,
            { action: 'eim_mark_all_notifications_read', nonce: eimNotifications.nonce },
            function ( res ) {
                if ( res.success ) {
                    $( '.eim-notification-item' )
                        .addClass( 'eim-notification-item--read' )
                        .removeClass( 'eim-notification-item--unread' );
                    $( '.eim-notification-mark-read' ).remove();
                    updateAdminBarBadge( 0 );
                    $( '#eim-count-unread' ).text( 0 );
                }
            }
        );
    } );

    // ── Tab switching ─────────────────────────────────────────────────────────
    $( document ).on( 'click', '.eim-tab-btn', function () {
        var tab = $( this ).data( 'tab' );

        $( '.eim-tab-btn' ).attr( 'aria-selected', 'false' ).removeClass( 'eim-tab-btn--active' );
        $( this ).attr( 'aria-selected', 'true' ).addClass( 'eim-tab-btn--active' );

        $( '.eim-tab-panel' ).hide();
        $( '#eim-tab-' + tab ).show();
    } );

    // ── Admin bar badge ───────────────────────────────────────────────────────
    function updateAdminBarBadge( count ) {
        var $badge = $( '#wp-admin-bar-eim-notifications .eim-badge' );
        if ( $badge.length ) {
            $badge.text( count > 0 ? count : '' ).toggle( count > 0 );
        }
    }

    function decrementBadge() {
        var $badge = $( '#wp-admin-bar-eim-notifications .eim-badge' );
        if ( $badge.length ) {
            var current = parseInt( $badge.text(), 10 ) || 0;
            var next    = Math.max( 0, current - 1 );
            $badge.text( next > 0 ? next : '' ).toggle( next > 0 );
        }
    }

    function decrementCount( selector ) {
        var $el  = $( selector );
        var curr = parseInt( $el.text(), 10 ) || 0;
        $el.text( Math.max( 0, curr - 1 ) );
    }

    // ── Auto-refresh ──────────────────────────────────────────────────────────
    setInterval( loadNotifications, REFRESH_MS );

    // ── Init ──────────────────────────────────────────────────────────────────
    loadNotifications();

}( jQuery ) );
