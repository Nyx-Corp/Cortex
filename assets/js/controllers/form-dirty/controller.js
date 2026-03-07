/**
 * Form Dirty Controller - Stimulus controller for form change tracking
 *
 * Enables submit button only when form has changes.
 */

import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['submit', 'cancel']

    hasChanges = false

    connect() {
        // Store initial form values for comparison
        this.initialValues = this.getFormValues()
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
     * Enable submit button with full color
     */
    enableSubmit() {
        if (this.hasSubmitTarget) {
            this.submitTarget.disabled = false
            this.submitTarget.classList.remove('bg-muted', 'text-primary', 'border', 'border-primary', 'cursor-not-allowed')
            this.submitTarget.classList.add('bg-primary', 'text-white', 'hover:bg-primary/90', 'cursor-pointer')
        }

        if (this.hasCancelTarget) {
            this.cancelTarget.classList.remove('hidden')
        }
    }

    /**
     * Disable submit button
     */
    disableSubmit() {
        if (this.hasSubmitTarget) {
            this.submitTarget.disabled = true
            this.submitTarget.classList.add('bg-muted', 'text-primary', 'border', 'border-primary', 'cursor-not-allowed')
            this.submitTarget.classList.remove('bg-primary', 'text-white', 'hover:bg-primary/90', 'cursor-pointer')
        }

        if (this.hasCancelTarget) {
            this.cancelTarget.classList.add('hidden')
        }
    }
}
