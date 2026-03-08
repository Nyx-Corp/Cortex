/**
 * Cortex — Hotkeys integration
 *
 * Thin wrapper around hotkeys-js for keyboard shortcuts.
 * Re-exports the library with a sensible default filter
 * that allows Escape in inputs but blocks other keys.
 *
 * Usage:
 *   import hotkeys from '@cortex/js/hotkeys.js'
 *
 *   hotkeys('n', () => { ... })                    // global shortcut, ignored in inputs
 *   hotkeys('command+k, ctrl+k', () => { ... })    // combo, works everywhere
 *   hotkeys('escape', () => { ... })               // escape, works everywhere
 */

import hotkeys from 'hotkeys-js'

// Allow Escape and modifier combos to fire inside inputs/textareas/selects.
// Single-char shortcuts (n, f, etc.) are blocked in inputs by default — this is expected.
hotkeys.filter = function (event) {
    const tag = event.target.tagName
    const isInput = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || event.target.isContentEditable

    // Always allow Escape and modifier combos (Ctrl/Cmd/Alt + key)
    if (event.key === 'Escape' || event.metaKey || event.ctrlKey || event.altKey) {
        return true
    }

    // Block single-char shortcuts in inputs
    return !isInput
}

export default hotkeys
