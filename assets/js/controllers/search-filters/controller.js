/**
 * Search Filters Controller - Gmail-style search with popover filters
 *
 * Features:
 * - Query string parsing (field:value format)
 * - Popover filter form
 * - Active filter chips with remove action
 * - Keyboard navigation (F to open, Enter to apply, Escape to close)
 */

import { Controller } from '@hotwired/stimulus'
import hotkeys from '../../hotkeys.js'

export default class extends Controller {
    static targets = ['input', 'popover', 'toggleBtn', 'field']

    isOpen = false

    connect() {
        // Sync initial query to filter fields
        if (this.inputTarget.value) {
            this.syncQueryToFields()
        }

        // Escape handling (behavioral, not element-bound)
        hotkeys('escape', () => {
            if (this.isOpen) this.close()
            this.inputTarget.blur()
        })

        // Close on click outside
        this.boundClickOutside = this.handleClickOutside.bind(this)
        document.addEventListener('click', this.boundClickOutside)

        // Listen for remove filter clicks at form level (chips are outside controller)
        this.form = this.element.closest('form')
        if (this.form) {
            this.boundRemoveFilter = this.handleRemoveFilterClick.bind(this)
            this.form.addEventListener('click', this.boundRemoveFilter)
        }
    }

    disconnect() {
        // Note: escape is shared across features, don't unbind globally
        document.removeEventListener('click', this.boundClickOutside)

        if (this.form && this.boundRemoveFilter) {
            this.form.removeEventListener('click', this.boundRemoveFilter)
        }
    }

    // ==================
    // Popover Management
    // ==================

    toggle(event) {
        event?.preventDefault()
        event?.stopPropagation()

        if (this.isOpen) {
            this.close()
        } else {
            this.open()
        }
    }

    open() {
        if (!this.hasPopoverTarget) return

        this.popoverTarget.classList.remove('hidden')
        this.isOpen = true
        window.createIcons?.()

        // Focus first filter field
        const firstField = this.popoverTarget.querySelector('input, select, textarea')
        firstField?.focus()
    }

    close() {
        if (!this.hasPopoverTarget) return

        this.popoverTarget.classList.add('hidden')
        this.isOpen = false
    }

    // ==================
    // Query Building
    // ==================

    /**
     * Build query string from filter fields
     * @returns {string} Query in "field:value field2:value2" format
     */
    buildQuery() {
        const parts = []
        const checkboxGroups = {}

        this.fieldTargets.forEach(field => {
            const fieldName = field.dataset.filterField
            const filterType = field.dataset.filterType
            let value = ''

            if (filterType === 'checkboxes') {
                // Collect checked values for checkbox groups
                if (field.checked) {
                    if (!checkboxGroups[fieldName]) {
                        checkboxGroups[fieldName] = []
                    }
                    checkboxGroups[fieldName].push(field.value)
                }
                return // Handle after loop
            } else if (field.type === 'checkbox') {
                if (field.checked) value = 'true'
            } else {
                value = field.value.trim()
            }

            if (value) {
                // Quote values with spaces
                if (value.includes(' ')) {
                    value = `"${value}"`
                }
                parts.push(`${fieldName}:${value}`)
            }
        })

        // Add checkbox groups (comma-separated)
        for (const [fieldName, values] of Object.entries(checkboxGroups)) {
            if (values.length > 0) {
                parts.push(`${fieldName}:${values.join(',')}`)
            }
        }

        return parts.join(' ')
    }

    /**
     * Parse query string into field values
     * @param {string} query - Query in "field:value" format
     * @returns {Object} Map of field names to values
     */
    parseQuery(query) {
        const filters = {}
        // Match field:value or field:"value with spaces"
        const regex = /(\w+):(?:"([^"]+)"|(\S+))/g
        let match

        while ((match = regex.exec(query)) !== null) {
            const field = match[1]
            const value = match[2] || match[3]
            filters[field] = value
        }

        return filters
    }

    /**
     * Sync query input to filter fields
     */
    syncQueryToFields() {
        const filters = this.parseQuery(this.inputTarget.value)

        this.fieldTargets.forEach(field => {
            const fieldName = field.dataset.filterField
            const filterType = field.dataset.filterType
            const value = filters[fieldName] || ''

            if (filterType === 'checkboxes') {
                // Multi-value checkboxes (comma-separated)
                const values = value ? value.split(',') : []
                field.checked = values.includes(field.value)
            } else if (field.type === 'checkbox') {
                field.checked = value === 'true' || value === '1'
            } else {
                field.value = value
            }
        })
    }

    // ==================
    // Actions
    // ==================

    /**
     * Apply filters from popover form — submits form directly with filter fields.
     */
    applyFilters(event) {
        event?.preventDefault()
        this.close()
        this.submitForm()
    }

    /**
     * Reset all filter fields
     * data-action="click->search-filters#reset"
     */
    reset(event) {
        event?.preventDefault()

        this.fieldTargets.forEach(field => {
            if (field.type === 'checkbox') {
                field.checked = false
            } else {
                field.value = ''
            }
        })

        this.inputTarget.value = ''
    }

    /**
     * Remove a single filter by field name.
     * Clears the matching form field(s) and resubmits.
     */
    removeFilter(event) {
        event?.preventDefault()

        const fieldToRemove = event.currentTarget.dataset.filterField
        if (!fieldToRemove) return

        // Clear q parameter if it contains this field
        const filters = this.parseQuery(this.inputTarget.value)
        if (filters[fieldToRemove]) {
            delete filters[fieldToRemove]
            const parts = Object.entries(filters).map(([k, v]) => {
                if (v.includes(' ')) v = `"${v}"`
                return `${k}:${v}`
            })
            this.inputTarget.value = parts.join(' ')
        }

        // Clear matching form field(s)
        const form = this.element.closest('form')
        if (form) {
            form.querySelectorAll(`[name="${fieldToRemove}"], [name^="${fieldToRemove}["]`).forEach(field => {
                if (field.type === 'checkbox' || field.type === 'radio') {
                    field.checked = false
                } else {
                    field.value = ''
                }
            })
        }

        this.submitForm()
    }

    /**
     * Handle Enter key in search input
     * data-action="keydown.enter->search-filters#submitOnEnter"
     */
    submitOnEnter(event) {
        event.preventDefault()
        this.submit()
    }

    /**
     * Handle Enter key in filter fields
     */
    applyOnEnter(event) {
        event.preventDefault()
        this.applyFilters(event)
    }

    /**
     * Submit via q parameter: disables filter fields so only q is sent.
     */
    submit() {
        this.fieldTargets.forEach(field => field.disabled = true)

        const form = this.element.closest('form')
        form?.submit()
    }

    /**
     * Submit the form directly with all fields.
     */
    submitForm() {
        // Clear q so filters are submitted as individual params
        this.inputTarget.value = ''

        const form = this.element.closest('form')
        form?.submit()
    }

    // ==================
    // Event Handlers
    // ==================

    /**
     * Handle clicks outside popover
     */
    handleClickOutside(event) {
        if (this.isOpen && !this.element.contains(event.target)) {
            this.close()
        }
    }

    /**
     * Handle remove filter clicks (delegated from form level).
     * Delegates to removeFilter().
     */
    handleRemoveFilterClick(event) {
        const btn = event.target.closest('[data-action*="removeFilter"]')
        if (!btn) return

        this.removeFilter({ preventDefault: () => event.preventDefault(), currentTarget: btn })
    }
}
