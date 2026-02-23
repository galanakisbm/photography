/**
 * Event Image Manager – Messaging
 *
 * Handles conversation list, message thread, composing, and auto-refresh.
 *
 * Expects localised object `eimMessaging` with:
 *   - ajaxUrl
 *   - nonce
 *   - currentUserId
 *   - i18n  { loading, noConversations, noMessages, sending, error }
 */
( function ( $ ) {
    'use strict';

    if ( typeof eimMessaging === 'undefined' ) {
        return;
    }

    var currentThreadId    = null;
    var refreshInterval    = null;
    var REFRESH_MS         = 30000;

    var $conversationList  = $( '#eim-conversations' );
    var $messages          = $( '#eim-messages' );
    var $threadTitle       = $( '#eim-thread-title' );
    var $replyForm         = $( '#eim-reply-form' );
    var $replyThreadId     = $( '#eim-reply-thread-id' );
    var $replyText         = $( '#eim-reply-text' );
    var $replyStatus       = $( '.eim-reply-status' );
    var $newMsgBtn         = $( '#eim-new-message-btn' );
    var $composeModal      = $( '#eim-compose-modal' );
    var $composeForm       = $( '#eim-compose-form' );
    var $composeStatus     = $( '.eim-compose-status' );

    // ── Load conversations ────────────────────────────────────────────────────
    function loadConversations() {
        $conversationList.html( '<li class="eim-conversations-loading">' + eimMessaging.i18n.loading + '</li>' );

        $.post(
            eimMessaging.ajaxUrl,
            { action: 'eim_get_conversations', nonce: eimMessaging.nonce },
            function ( res ) {
                $conversationList.empty();
                if ( res.success && res.data && res.data.length ) {
                    $.each( res.data, function ( i, convo ) {
                        var $li = buildConversationItem( convo );
                        if ( convo.thread_id === currentThreadId ) {
                            $li.addClass( 'eim-conversation--active' );
                        }
                        $conversationList.append( $li );
                    } );
                } else {
                    $conversationList.html( '<li class="eim-conversations-empty">' + eimMessaging.i18n.noConversations + '</li>' );
                }
            }
        ).fail( function () {
            $conversationList.html( '<li class="eim-conversations-error">' + eimMessaging.i18n.error + '</li>' );
        } );
    }

    function buildConversationItem( convo ) {
        var $li = $( '<li>' ).addClass( 'eim-conversation-item' )
            .attr( 'data-thread-id', convo.thread_id )
            .attr( 'role', 'button' )
            .attr( 'tabindex', '0' );

        $li.html(
            '<span class="eim-convo-name">' + $( '<span>' ).text( convo.other_user_name ).html() + '</span>' +
            '<span class="eim-convo-preview">' + $( '<span>' ).text( convo.last_message_preview ).html() + '</span>' +
            ( convo.unread_count > 0
                ? '<span class="eim-convo-unread" aria-label="' + convo.unread_count + ' unread">' + convo.unread_count + '</span>'
                : '' )
        );

        return $li;
    }

    // ── Load messages for a thread ────────────────────────────────────────────
    function loadMessages( threadId ) {
        currentThreadId = threadId;
        $messages.html( '<p class="eim-messages-loading">' + eimMessaging.i18n.loading + '</p>' );

        $.post(
            eimMessaging.ajaxUrl,
            { action: 'eim_get_messages', nonce: eimMessaging.nonce, thread_id: threadId },
            function ( res ) {
                $messages.empty();
                if ( res.success && res.data ) {
                    if ( res.data.messages && res.data.messages.length ) {
                        $.each( res.data.messages, function ( i, msg ) {
                            $messages.append( buildMessageBubble( msg ) );
                        } );
                        $messages.scrollTop( $messages[ 0 ].scrollHeight );
                    } else {
                        $messages.html( '<p class="eim-messages-empty">' + eimMessaging.i18n.noMessages + '</p>' );
                    }

                    if ( res.data.thread_title ) {
                        $threadTitle.text( res.data.thread_title );
                    }
                }
                markThreadRead( threadId );
            }
        ).fail( function () {
            $messages.html( '<p class="eim-messages-error">' + eimMessaging.i18n.error + '</p>' );
        } );

        $replyThreadId.val( threadId );
        $replyForm.show();
        $( '#eim-conversations .eim-conversation-item' ).removeClass( 'eim-conversation--active' );
        $( '#eim-conversations [data-thread-id="' + threadId + '"]' ).addClass( 'eim-conversation--active' );
    }

    function buildMessageBubble( msg ) {
        var isMine = String( msg.sender_id ) === String( eimMessaging.currentUserId );
        var $div   = $( '<div>' ).addClass( 'eim-message-bubble' )
            .addClass( isMine ? 'eim-message-bubble--mine' : 'eim-message-bubble--theirs' );

        $div.html(
            '<span class="eim-message-author">' + $( '<span>' ).text( msg.sender_name ).html() + '</span>' +
            '<p class="eim-message-body">' + $( '<p>' ).text( msg.message ).html() + '</p>' +
            '<time class="eim-message-time">' + $( '<time>' ).text( msg.created_at ).html() + '</time>'
        );
        return $div;
    }

    // ── Mark messages as read ─────────────────────────────────────────────────
    function markThreadRead( threadId ) {
        $.post( eimMessaging.ajaxUrl, {
            action:    'eim_mark_thread_read',
            nonce:     eimMessaging.nonce,
            thread_id: threadId,
        } );
        $( '[data-thread-id="' + threadId + '"] .eim-convo-unread' ).remove();
    }

    // ── Conversation click/keyboard ───────────────────────────────────────────
    $( document ).on( 'click keydown', '.eim-conversation-item', function ( e ) {
        if ( 'keydown' === e.type && 13 !== e.which && 32 !== e.which ) {
            return;
        }
        loadMessages( $( this ).data( 'thread-id' ) );
    } );

    // ── Reply form ────────────────────────────────────────────────────────────
    $replyForm.on( 'submit', function ( e ) {
        e.preventDefault();
        var message  = $replyText.val().trim();
        var threadId = $replyThreadId.val();

        if ( ! message || ! threadId ) {
            return;
        }

        $replyStatus.text( eimMessaging.i18n.sending );

        $.post(
            eimMessaging.ajaxUrl,
            {
                action:    'eim_send_message',
                nonce:     eimMessaging.nonce,
                thread_id: threadId,
                message:   message,
            },
            function ( res ) {
                $replyStatus.text( '' );
                if ( res.success ) {
                    $replyText.val( '' );
                    loadMessages( threadId );
                } else {
                    $replyStatus.css( 'color', '#c00' ).text( res.data && res.data.message ? res.data.message : eimMessaging.i18n.error );
                }
            }
        ).fail( function () {
            $replyStatus.css( 'color', '#c00' ).text( eimMessaging.i18n.error );
        } );
    } );

    // ── New message modal ─────────────────────────────────────────────────────
    $newMsgBtn.on( 'click', function () {
        $composeModal.show();
    } );

    $( document ).on( 'click', '#eim-compose-modal .eim-modal-close, #eim-compose-modal .eim-modal-overlay', function () {
        $composeModal.hide();
    } );

    $composeForm.on( 'submit', function ( e ) {
        e.preventDefault();
        var recipient = $( '#eim-compose-recipient' ).val().trim();
        var subject   = $( '#eim-compose-subject' ).val().trim();
        var body      = $( '#eim-compose-body' ).val().trim();

        if ( ! recipient || ! body ) {
            return;
        }

        $composeStatus.text( eimMessaging.i18n.sending );

        $.post(
            eimMessaging.ajaxUrl,
            {
                action:    'eim_send_message',
                nonce:     $( '#eim_compose_nonce' ).val(),
                recipient: recipient,
                subject:   subject,
                message:   body,
            },
            function ( res ) {
                if ( res.success ) {
                    $composeModal.hide();
                    $composeForm[ 0 ].reset();
                    $composeStatus.text( '' );
                    loadConversations();
                } else {
                    $composeStatus.css( 'color', '#c00' ).text( res.data && res.data.message ? res.data.message : eimMessaging.i18n.error );
                }
            }
        ).fail( function () {
            $composeStatus.css( 'color', '#c00' ).text( eimMessaging.i18n.error );
        } );
    } );

    // ── Auto-refresh ──────────────────────────────────────────────────────────
    refreshInterval = setInterval( function () {
        loadConversations();
        if ( currentThreadId ) {
            loadMessages( currentThreadId );
        }
    }, REFRESH_MS );

    // ── Init ──────────────────────────────────────────────────────────────────
    loadConversations();

}( jQuery ) );
