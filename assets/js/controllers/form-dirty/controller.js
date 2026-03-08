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
        this.disableSubmit()

        // Auto-listen for changes on all form fields
        this.element.addEventListener('input', this.boundMarkDirty = () => this.markDirty())
        this.element.addEventListener('change', this.boundMarkDirty)
    }

    disconnect() {
        this.element.removeEventListener('input', this.boundMarkDirty)
        this.element.removeEventListener('change', this.boundMarkDirty)
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
            this.submitTarget.classList.remove('border', 'border-primary', 'text-primary', 'hover:bg-primary', 'hover:text-white', 'cursor-not-allowed', 'opacity-50')
            this.submitTarget.classList.add('bg-primary', 'text-white', 'hover:bg-primary/90', 'cursor-pointer')
        }
    }

    /**
     * Disable submit button
     */
    disableSubmit() {
        if (this.hasSubmitTarget) {
            this.submitTarget.disabled = true
            this.submitTarget.classList.add('border', 'border-primary', 'text-primary', 'cursor-not-allowed', 'opacity-50')
            this.submitTarget.classList.remove('bg-primary', 'text-white', 'hover:bg-primary/90', 'hover:bg-primary', 'hover:text-white', 'cursor-pointer')
        }
    }
}
