/**
 * Alerts Triggers - Entry points for creating alerts
 *
 * Provides:
 * - window.toast global API
 * - Symfony flash messages integration
 * - Custom event listener for external triggers
 */

import { alertsStore } from './store.js'

/**
 * Expose global toast API
 * Usage:
 *   window.toast.success('Title')
 *   window.toast.success('Title', 'Message')
 *   window.toast.success('Title', { message: 'Message', icon: 'trophy' })
 */
export function initGlobalApi() {
    const createToast = (type, title, options = {}) => {
        if (typeof options === 'string') {
            options = { message: options }
        }
        return alertsStore.add(type, title, options.message || '', options.icon || null)
    }

    window.toast = {
        success: (title, options) => createToast('success', title, options),
        warning: (title, options) => createToast('warning', title, options),
        error: (title, options) => createToast('error', title, options),
    }
}

/**
 * Process Symfony flash messages from a data attribute
 * Supports structured format: {title, message?, icon?}
 * @param {HTMLElement} element - Element with data-flash-messages attribute
 */
export function processFlashMessages(element) {
    const flashData = element?.dataset?.flashMessages
    if (!flashData) return

    try {
        const messages = JSON.parse(flashData)

        Object.entries(messages).forEach(([type, items]) => {
            const mappedType = mapFlashType(type)
            items.forEach(item => {
                if (typeof item === 'string') {
                    alertsStore.add(mappedType, item)
                } else {
                    alertsStore.add(mappedType, item.title, item.message || '', item.icon || null)
                }
            })
        })

        // Clear the attribute to prevent reprocessing
        delete element.dataset.flashMessages
    } catch (e) {
        console.error('[Alerts] Failed to parse flash messages:', e)
    }
}

/**
 * Map Symfony flash types to alert types
 */
function mapFlashType(type) {
    const mapping = {
        'success': 'success',
        'notice': 'success',
        'error': 'error',
        'danger': 'error',
        'warning': 'warning',
        'info': 'warning',
    }
    return mapping[type] || 'warning'
}

/**
 * Listen for custom events to trigger alerts
 * Usage: document.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', title: 'Done!', message: 'Details', icon: 'trophy' }}))
 */
export function initEventListener() {
    document.addEventListener('toast', (e) => {
        const { type = 'warning', title, message = '', icon = null } = e.detail || {}
        if (title) {
            alertsStore.add(type, title, message, icon)
        }
    })
}

/**
 * Initialize all triggers
 */
export function initTriggers(flashElement = null) {
    initGlobalApi()
    initEventListener()

    if (flashElement) {
        processFlashMessages(flashElement)
    }
}
