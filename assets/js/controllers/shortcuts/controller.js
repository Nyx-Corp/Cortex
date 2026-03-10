/**
 * Shortcuts Controller — Declarative keyboard shortcuts
 *
 * Scans its subtree for [data-shortcut="key"] elements and auto-binds hotkeys:
 * - <a>, <button>          → .click()
 * - <input>, <textarea>    → .focus()
 *
 * Usage:
 *   <body data-controller="shortcuts">
 *       <a href="/new" data-shortcut="n">Nouveau</a>
 *       <button data-shortcut="f">Filtres</button>
 *       <input data-shortcut="/" placeholder="Rechercher...">
 *   </body>
 */

import { Controller } from '@hotwired/stimulus'
import hotkeys from '../../hotkeys.js'

export default class extends Controller {
    boundKeys = []

    connect() {
        this.scan()
    }

    disconnect() {
        this.boundKeys.forEach(key => hotkeys.unbind(key))
        this.boundKeys = []
    }

    scan() {
        this.element.querySelectorAll('[data-shortcut]').forEach(el => {
            const key = el.dataset.shortcut
            if (!key) return

            hotkeys(key, (e) => {
                e.preventDefault()

                const tag = el.tagName
                if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
                    el.focus()
                } else {
                    el.click()
                }
            })

            this.boundKeys.push(key)
        })
    }
}
