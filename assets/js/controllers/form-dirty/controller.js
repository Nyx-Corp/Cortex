/**
 * Form Dirty Controller - Stimulus controller for form change tracking
 *
 * Enables submit button only when form has changes.
 * Warns before navigating away with unsaved changes (beforeunload + link intercept).
 */

import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['submit', 'cancel']
    static values = {
        warn: { type: Boolean, default: true },
        message: { type: String, default: 'Vous avez des modifications non enregistrées. Quitter sans sauvegarder ?' },
    }

    hasChanges = false

    connect() {
        // Store initial form values for comparison
        this.initialValues = this.getFormValues()
        this.disableSubmit()

        // Auto-listen for changes on all form fields
        this.boundMarkDirty = () => this.markDirty()
        this.element.addEventListener('input', this.boundMarkDirty)
        this.element.addEventListener('change', this.boundMarkDirty)

        // Warn on page unload
        this.#boundBeforeUnload = (e) => this.#onBeforeUnload(e)
        window.addEventListener('beforeunload', this.#boundBeforeUnload)

        // Intercept link clicks
        this.#boundLinkClick = (e) => this.#onLinkClick(e)
        document.addEventListener('click', this.#boundLinkClick, true)

        // Reset on form submit so beforeunload doesn't fire
        this.#boundSubmit = () => { this.hasChanges = false }
        this.element.addEventListener('submit', this.#boundSubmit)
    }

    disconnect() {
        this.element.removeEventListener('input', this.boundMarkDirty)
        this.element.removeEventListener('change', this.boundMarkDirty)
        window.removeEventListener('beforeunload', this.#boundBeforeUnload)
        document.removeEventListener('click', this.#boundLinkClick, true)
        this.element.removeEventListener('submit', this.#boundSubmit)
    }

    /**
     * Get current form values as a serialized string
     */
    getFormValues() {
        const formData = new FormData(this.element)
        const values = []
        for (const [key, value] of formData.entries()) {
            values.push(`${key}=${value}`)
        }
        return values.sort().join('&')
    }

    /**
     * Mark form as dirty when input changes
     */
    markDirty() {
        const currentValues = this.getFormValues()
        const isDirty = currentValues !== this.initialValues

        if (isDirty && !this.hasChanges) {
            this.hasChanges = true
            this.enableSubmit()
        } else if (!isDirty && this.hasChanges) {
            this.hasChanges = false
            this.disableSubmit()
        }
    }

    /**
     * Reset dirty state (call after successful save)
     */
    reset() {
        this.initialValues = this.getFormValues()
        this.hasChanges = false
        this.disableSubmit()
    }

    /**
     * Enable submit button
     */
    enableSubmit() {
        if (this.hasSubmitTarget) {
            this.submitTarget.disabled = false
            this.submitTarget.classList.remove('cursor-not-allowed', 'opacity-50', 'bg-muted')
            this.submitTarget.classList.add('cursor-pointer', 'bg-primary', 'text-white')
        }
    }

    /**
     * Disable submit button
     */
    disableSubmit() {
        if (this.hasSubmitTarget) {
            this.submitTarget.disabled = true
            this.submitTarget.classList.add('cursor-not-allowed', 'opacity-50', 'bg-muted')
            this.submitTarget.classList.remove('cursor-pointer', 'bg-primary', 'text-white')
        }
    }

    // ── Private ──

    #boundBeforeUnload
    #boundLinkClick
    #boundSubmit

    #onBeforeUnload(e) {
        if (!this.hasChanges || !this.warnValue) return
        e.preventDefault()
    }

    #onLinkClick(e) {
        if (!this.hasChanges || !this.warnValue) return

        const link = e.target.closest('a[href]')
        if (!link) return

        // Ignore links that open in new tabs, anchors, or javascript:
        const href = link.getAttribute('href')
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || link.target === '_blank') return

        // Ignore links inside this form (e.g. cancel button with data-turbo-confirm)
        if (this.element.contains(link)) return

        if (!window.confirm(this.messageValue)) {
            e.preventDefault()
            e.stopPropagation()
        }
    }
}
