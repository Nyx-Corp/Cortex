/**
 * Cortex Admin — Bootstrap entrypoint
 *
 * Initializes Stimulus, registers generic controllers,
 * sets up Lucide icons & vanilla JS admin features.
 *
 * Usage in project:
 *   import { initAdmin } from '@cortex/js/admin.js'
 *   const stimulus = initAdmin({
 *       controllers: { 'my-ctrl': MyController },
 *       onReady() { ... }
 *   })
 */

import { createIcons, icons } from 'lucide'
import { Application } from '@hotwired/stimulus'
import hotkeys from './hotkeys.js'
import { AlertsController } from './controllers/alerts/index.js'
import { SearchFiltersController } from './controllers/search-filters/index.js'
import { PopoverController } from './controllers/popover/index.js'
import { FormDirtyController } from './controllers/form-dirty/index.js'
import { ThemeToggleController } from './controllers/theme-toggle/index.js'
import { ShortcutsController } from './controllers/shortcuts/index.js'

/**
 * Initialize the admin application
 * @param {Object} options
 * @param {Object} options.controllers - Extra Stimulus controllers to register { name: Controller }
 * @param {Function} options.onReady - Callback after DOMContentLoaded initialization
 * @returns {Application} Stimulus application instance
 */
export function initAdmin(options = {}) {
    const stimulus = Application.start()

    // Register generic controllers
    stimulus.register('alerts', AlertsController)
    stimulus.register('search-filters', SearchFiltersController)
    stimulus.register('popover', PopoverController)
    stimulus.register('form-dirty', FormDirtyController)
    stimulus.register('theme-toggle', ThemeToggleController)
    stimulus.register('shortcuts', ShortcutsController)

    // Register project-specific controllers
    if (options.controllers) {
        for (const [name, controller] of Object.entries(options.controllers)) {
            stimulus.register(name, controller)
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Initialize Lucide icons
        window.createIcons = () => createIcons({ icons })
        window.createIcons()

        // Core admin features
        initSidebar()
        initSearchModal()
        initAutosubmit()
        initDropdowns()
        initBatchSelection()

        // Project-specific initialization
        options.onReady?.()
    })

    return stimulus
}

/**
 * Sidebar toggle functionality
 */
function initSidebar() {
    const sidebarButton = document.querySelector('#sidebar-toggle')
    const pageContainer = document.querySelector('#container')

    if (sidebarButton && pageContainer) {
        const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true'
        if (isCollapsed) {
            pageContainer.classList.add('closed')
        }

        sidebarButton.addEventListener('click', () => {
            const isClosed = pageContainer.classList.toggle('closed')
            localStorage.setItem('sidebar-collapsed', isClosed)
        })
    }
}

/**
 * Search modal (Cmd+K / Ctrl+K)
 */
function initSearchModal() {
    const modal = document.querySelector('#search-modal')
    const trigger = document.querySelector('#search-trigger')
    const backdrop = modal?.querySelector('[data-search-backdrop]')
    const input = modal?.querySelector('#search-input')

    if (!modal) return

    const openModal = () => {
        modal.classList.remove('hidden')
        requestAnimationFrame(() => {
            input?.focus()
            window.createIcons()
        })
    }

    const closeModal = () => {
        modal.classList.add('hidden')
        if (input) input.value = ''
    }

    // Trigger button in sidebar
    trigger?.addEventListener('click', openModal)

    // Backdrop click closes modal
    backdrop?.addEventListener('click', closeModal)

    // Keyboard shortcuts
    hotkeys('command+k, ctrl+k', (e) => {
        e.preventDefault()
        if (modal.classList.contains('hidden')) {
            openModal()
        } else {
            closeModal()
        }
    })

    hotkeys('escape', () => {
        if (!modal.classList.contains('hidden')) {
            closeModal()
        }
    })

    // TODO: Implement search functionality
    input?.addEventListener('input', (e) => {
        const query = e.target.value.trim()
        // Future: fetch results and display
        console.log('Search query:', query)
    })
}

/**
 * Autosubmit - submit form when elements with data-autosubmit change
 */
function initAutosubmit() {
    document.querySelectorAll('[data-autosubmit]').forEach(element => {
        const form = element.closest('form')
        if (!form) return

        // Skip elements managed by search-filters controller (they use applyFilters)
        if (element.hasAttribute('data-search-filters-target')) return

        element.addEventListener('change', () => {
            // Disable filter fields to prevent them from being submitted
            form.querySelectorAll('[data-search-filters-target="field"]').forEach(field => {
                field.disabled = true
            })
            form.submit()
        })
    })
}

/**
 * Dropdown menus - simple toggle functionality
 */
function initDropdowns() {
    document.querySelectorAll('[data-controller="dropdown"]').forEach(container => {
        const trigger = container.querySelector('[data-dropdown-target="trigger"]')
        const menu = container.querySelector('[data-dropdown-target="menu"]')

        if (!trigger || !menu) return

        let isOpen = false

        const open = () => {
            menu.classList.remove('hidden')
            isOpen = true
        }

        const close = () => {
            menu.classList.add('hidden')
            isOpen = false
        }

        const toggle = () => {
            if (isOpen) close()
            else open()
        }

        trigger.addEventListener('click', (e) => {
            e.preventDefault()
            e.stopPropagation()
            toggle()
        })

        // Close on click outside
        document.addEventListener('click', (e) => {
            if (isOpen && !container.contains(e.target)) {
                close()
            }
        })

        // Close on Escape
        hotkeys('escape', () => {
            if (isOpen) close()
        })
    })
}

/**
 * Batch selection - checkbox handling for list views
 * Dispatches 'batch:action' CustomEvent for project-specific handling.
 */
function initBatchSelection() {
    const selectAll = document.querySelector('#select-all')
    const rowCheckboxes = document.querySelectorAll('.row-checkbox')
    const batchDropdown = document.querySelector('[data-batch-dropdown]')
    const batchTrigger = document.querySelector('[data-batch-actions-trigger]')
    const batchMenu = document.querySelector('[data-batch-menu]')
    const batchCount = document.querySelector('[data-batch-count]')

    if (!selectAll || rowCheckboxes.length === 0) return

    let isMenuOpen = false
    let previousCount = 0

    const openMenu = () => {
        if (batchMenu && !isMenuOpen) {
            batchMenu.classList.remove('hidden')
            isMenuOpen = true
            window.createIcons?.()
        }
    }

    const closeMenu = () => {
        if (batchMenu && isMenuOpen) {
            batchMenu.classList.add('hidden')
            isMenuOpen = false
        }
    }

    const updateBatchState = (autoOpen = false) => {
        const checked = document.querySelectorAll('.row-checkbox:checked')
        const count = checked.length

        // Update count badge
        if (batchCount) {
            batchCount.textContent = count
        }

        // Show/hide batch actions button with animation
        if (batchTrigger) {
            if (count > 0) {
                batchTrigger.classList.remove('opacity-0', 'pointer-events-none')
                batchTrigger.classList.add('opacity-100', 'pointer-events-auto')

                // Auto-open menu when first item is selected
                if (autoOpen && previousCount === 0 && count > 0) {
                    openMenu()
                }
            } else {
                batchTrigger.classList.add('opacity-0', 'pointer-events-none')
                batchTrigger.classList.remove('opacity-100', 'pointer-events-auto')
                closeMenu()
            }
        }

        // Update select all checkbox state
        if (count === 0) {
            selectAll.checked = false
            selectAll.indeterminate = false
        } else if (count === rowCheckboxes.length) {
            selectAll.checked = true
            selectAll.indeterminate = false
        } else {
            selectAll.checked = false
            selectAll.indeterminate = true
        }

        previousCount = count
    }

    // Toggle menu on trigger click
    batchTrigger?.addEventListener('click', (e) => {
        e.preventDefault()
        e.stopPropagation()
        if (isMenuOpen) closeMenu()
        else openMenu()
    })

    // Close on click outside (but not when clicking checkboxes)
    document.addEventListener('click', (e) => {
        if (isMenuOpen && batchDropdown && !batchDropdown.contains(e.target)) {
            // Don't close if clicking on a checkbox (row selection)
            if (e.target.matches('.row-checkbox, #select-all')) return
            closeMenu()
        }
    })

    // Close on Escape
    hotkeys('escape', () => {
        if (isMenuOpen) closeMenu()
    })

    // Select all toggle
    selectAll.addEventListener('change', () => {
        const shouldCheck = selectAll.checked
        rowCheckboxes.forEach(cb => cb.checked = shouldCheck)
        updateBatchState(true)
    })

    // Individual row checkbox
    rowCheckboxes.forEach(cb => {
        cb.addEventListener('change', () => updateBatchState(true))
    })

    // Batch action handlers — dispatch event for project-specific handling
    document.querySelectorAll('[data-batch-action]').forEach(btn => {
        btn.addEventListener('click', () => {
            const action = btn.dataset.batchAction
            const selected = Array.from(document.querySelectorAll('.row-checkbox:checked'))
                .map(cb => cb.value)

            if (selected.length === 0) return

            document.dispatchEvent(new CustomEvent('batch:action', {
                detail: { action, selected }
            }))

            closeMenu()
        })
    })

    // Initial state
    updateBatchState(false)
}

