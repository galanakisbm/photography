/**
 * Event Image Manager – Dark Mode
 *
 * Toggles dark mode by adding/removing `eim-dark-mode` on <body>.
 * Persists preference in localStorage under key `eim_dark_mode`.
 * Dispatches custom `eim:darkmodechange` event on toggle.
 *
 * No external dependencies.
 */
( function () {
    'use strict';

    var STORAGE_KEY = 'eim_dark_mode';
    var BODY_CLASS  = 'eim-dark-mode';

    // ── Apply saved preference on load ────────────────────────────────────────
    function getPreference() {
        var stored = localStorage.getItem( STORAGE_KEY );
        if ( null !== stored ) {
            return '1' === stored;
        }
        // Fall back to OS preference.
        return window.matchMedia && window.matchMedia( '(prefers-color-scheme: dark)' ).matches;
    }

    function applyTheme( isDark ) {
        if ( isDark ) {
            document.body.classList.add( BODY_CLASS );
        } else {
            document.body.classList.remove( BODY_CLASS );
        }
        updateToggleButtons( isDark );
    }

    function savePreference( isDark ) {
        try {
            localStorage.setItem( STORAGE_KEY, isDark ? '1' : '0' );
        } catch ( e ) {
            // localStorage not available; ignore.
        }
    }

    function dispatchEvent( isDark ) {
        var event;
        if ( typeof CustomEvent === 'function' ) {
            event = new CustomEvent( 'eim:darkmodechange', { detail: { darkMode: isDark }, bubbles: true } );
        } else {
            event = document.createEvent( 'CustomEvent' );
            event.initCustomEvent( 'eim:darkmodechange', true, false, { darkMode: isDark } );
        }
        document.dispatchEvent( event );
    }

    function updateToggleButtons( isDark ) {
        var buttons = document.querySelectorAll( '.eim-dark-mode-toggle' );
        for ( var i = 0; i < buttons.length; i++ ) {
            buttons[ i ].setAttribute( 'aria-pressed', isDark ? 'true' : 'false' );
            var iconEl  = buttons[ i ].querySelector( '.eim-dark-mode-icon' );
            var labelEl = buttons[ i ].querySelector( '.eim-dark-mode-label' );
            if ( iconEl ) {
                iconEl.textContent = isDark ? '\u2600\uFE0F' : '\uD83C\uDF19';
            }
            if ( labelEl ) {
                labelEl.textContent = isDark ? 'Light Mode' : 'Dark Mode';
            }
        }
    }

    function toggle() {
        var isDark = document.body.classList.contains( BODY_CLASS );
        var next   = ! isDark;
        applyTheme( next );
        savePreference( next );
        dispatchEvent( next );
    }

    // ── Wire up toggle buttons (delegated to handle dynamic buttons) ──────────
    document.addEventListener( 'click', function ( e ) {
        var btn = e.target.closest( '.eim-dark-mode-toggle' );
        if ( btn ) {
            toggle();
        }
    } );

    // ── Listen for OS preference changes ─────────────────────────────────────
    if ( window.matchMedia ) {
        window.matchMedia( '(prefers-color-scheme: dark)' ).addEventListener( 'change', function ( e ) {
            // Only honour OS changes if the user has not explicitly set a preference.
            if ( null === localStorage.getItem( STORAGE_KEY ) ) {
                applyTheme( e.matches );
            }
        } );
    }

    // ── Init ──────────────────────────────────────────────────────────────────
    applyTheme( getPreference() );

}() );
