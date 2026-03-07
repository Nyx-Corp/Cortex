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

export default class extends Controller {
    static targets = ['input', 'popover', 'toggleBtn', 'field']

    isOpen = false

    connect() {
        // Sync initial query to filter fields
        if (this.inputTarget.value) {
            this.syncQueryToFields()
        }

        // Listen for keyboard shortcuts at document level
        this.boundKeyHandler = this.handleGlobalKey.bind(this)
        document.addEventListener('keydown', this.boundKeyHandler)

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
        document.removeEventListener('keydown', this.boundKeyHandler)
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
     * Apply filters from popover form
     * data-action="click->search-filters#applyFilters"
     */
    applyFilters(event) {
        event?.preventDefault()

        this.inputTarget.value = this.buildQuery()
        this.close()
        this.submit()
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
     * Remove a single filter by field name
     * data-action="click->search-filters#removeFilter"
     * data-filter-field="fieldName"
     */
    removeFilter(event) {
        event?.preventDefault()

        const fieldToRemove = event.currentTarget.dataset.filterField
        const filters = this.parseQuery(this.inputTarget.value)
        delete filters[fieldToRemove]

        // Rebuild query
        const parts = Object.entries(filters).map(([k, v]) => {
            if (v.includes(' ')) v = `"${v}"`
            return `${k}:${v}`
        })

        this.inputTarget.value = parts.join(' ')
        this.submit()
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
     * data-action="keydown.enter->search-filters#applyOnEnter"
     */
    applyOnEnter(event) {
        event.preventDefault()
        this.applyFilters()
    }

    /**
     * Submit the form
     */
    submit() {
        // Disable filter fields so they don't get submitted (only q param)
        this.fieldTargets.forEach(field => field.disabled = true)

        const form = this.element.closest('form')
        form?.submit()
    }

    // ==================
    // Event Handlers
    // ==================

    /**
     * Handle global keyboard shortcuts
     */
    handleGlobalKey(event) {
        // Escape closes popover
        if (event.key === 'Escape' && this.isOpen) {
            this.close()
            return
        }

        // F opens filters (only if not in input)
        if (event.key === 'f' && !event.metaKey && !event.ctrlKey && !event.altKey) {
            if (!event.target.matches('input, textarea, select, [contenteditable]')) {
                event.preventDefault()
                this.toggle()
            }
        }
    }

    /**
     * Handle clicks outside popover
     */
    handleClickOutside(event) {
        if (this.isOpen && !this.element.contains(event.target)) {
            this.close()
        }
    }

    /**
     * Handle remove filter clicks (delegated from form level)
     * Looks for elements with data-action containing "removeFilter"
     */
    handleRemoveFilterClick(event) {
        const btn = event.target.closest('[data-action*="removeFilter"]')
        if (!btn) return

        event.preventDefault()

        const fieldToRemove = btn.dataset.filterField
        const filters = this.parseQuery(this.inputTarget.value)
        delete filters[fieldToRemove]

        // Rebuild query
        const parts = Object.entries(filters).map(([k, v]) => {
            if (v.includes(' ')) v = `"${v}"`
            return `${k}:${v}`
        })

        this.inputTarget.value = parts.join(' ')
        this.submit()
    }
}
