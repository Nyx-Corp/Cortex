/**
 * Alerts Controller - Stimulus controller for rendering alerts
 *
 * Listens to store events and manipulates the DOM.
 * Uses <template> for alert markup.
 */

import { Controller } from '@hotwired/stimulus'
import { createIcons, icons } from 'lucide'
import { alertsStore } from './store.js'
import { initTriggers } from './triggers.js'

export default class extends Controller {
    static targets = ['container', 'template', 'badge']

    // Map alert IDs to DOM elements
    elements = new Map()

    connect() {
        // Listen to store events FIRST (before processing flash messages)
        this.boundHandlers = {
            added: (e) => this.onAdded(e.detail),
            hidden: (e) => this.onHidden(e.detail),
            shown: (e) => this.onShown(e.detail),
            dismissed: (e) => this.onDismissed(e.detail),
            count: (e) => this.updateBadge(e.detail),
        }

        document.addEventListener('alert:added', this.boundHandlers.added)
        document.addEventListener('alert:hidden', this.boundHandlers.hidden)
        document.addEventListener('alert:shown', this.boundHandlers.shown)
        document.addEventListener('alert:dismissed', this.boundHandlers.dismissed)
        document.addEventListener('alert:count', this.boundHandlers.count)

        // Initialize triggers AFTER listeners are attached
        initTriggers(this.element)

        // Initial badge update
        this.updateBadge(alertsStore.count)
    }

    disconnect() {
        document.removeEventListener('alert:added', this.boundHandlers.added)
        document.removeEventListener('alert:hidden', this.boundHandlers.hidden)
        document.removeEventListener('alert:shown', this.boundHandlers.shown)
        document.removeEventListener('alert:dismissed', this.boundHandlers.dismissed)
        document.removeEventListener('alert:count', this.boundHandlers.count)
    }

    /**
     * Handle new alert added to store
     */
    onAdded(alert) {
        // FLIP: Record positions of existing alerts before adding new one
        const existingAlerts = Array.from(this.containerTarget.children)
        const firstPositions = new Map()
        existingAlerts.forEach(el => {
            firstPositions.set(el, el.getBoundingClientRect().top)
        })

        // Create and prepend new element
        const element = this.createElement(alert)
        this.elements.set(alert.id, element)
        this.containerTarget.prepend(element)
        this.updateBadge(alertsStore.count)

        // Render Lucide icons if custom icon name provided
        if (alert.icon && !alert.icon.trim().startsWith('<')) {
            createIcons({ icons, nameAttr: 'data-lucide' })
        }

        // FLIP: Animate existing alerts sliding down
        existingAlerts.forEach(el => {
            const firstTop = firstPositions.get(el)
            const lastTop = el.getBoundingClientRect().top
            const deltaY = firstTop - lastTop

            if (deltaY !== 0) {
                // Invert: place element at old position
                el.style.transform = `translateY(${deltaY}px)`
                el.style.transition = 'none'

                // Play: animate to new position
                requestAnimationFrame(() => {
                    el.style.transition = 'transform 150ms ease-out'
                    el.style.transform = 'translateY(0)'
                })
            }
        })

        // Trigger enter animation for new element
        requestAnimationFrame(() => {
            element.classList.remove('opacity-0', 'translate-x-4')
            element.classList.add('opacity-100', 'translate-x-0')
        })
    }

    /**
     * Handle alert hidden (fade out)
     */
    onHidden(alert) {
        const element = this.elements.get(alert.id)
        if (!element) return

        element.classList.remove('opacity-100', 'translate-x-0')
        element.classList.add('opacity-0', 'translate-x-4')
    }

    /**
     * Handle alert shown (hover reappearance)
     */
    onShown(alert) {
        const element = this.elements.get(alert.id)
        if (!element) return

        element.classList.remove('opacity-0', 'translate-x-4')
        element.classList.add('opacity-100', 'translate-x-0')
    }

    /**
     * Handle alert dismissed (remove from DOM)
     */
    onDismissed(alert) {
        const element = this.elements.get(alert.id)
        if (!element) return

        element.classList.remove('opacity-100', 'translate-x-0')
        element.classList.add('opacity-0', 'translate-x-4')

        setTimeout(() => {
            element.remove()
            this.elements.delete(alert.id)
        }, 150)
    }

    /**
     * Create alert DOM element from template
     */
    createElement(alert) {
        const clone = this.templateTarget.content.cloneNode(true)
        const element = clone.querySelector('[data-alert-item]')

        // Set title
        const titleEl = element.querySelector('[data-alert-title]')
        titleEl.textContent = alert.title

        // Set message (optional)
        const messageEl = element.querySelector('[data-alert-message]')
        if (alert.message) {
            messageEl.textContent = alert.message
            messageEl.classList.remove('hidden')
        }

        // Set type-specific styles
        const iconEl = element.querySelector('[data-alert-icon]')
        const styles = this.getStyles(alert.type)

        element.classList.add(styles.bg, styles.border)
        iconEl.classList.add(styles.text)

        // Use custom icon if provided, otherwise default icon for type
        if (alert.icon) {
            iconEl.innerHTML = this.resolveIcon(alert.icon)
        } else {
            iconEl.innerHTML = this.getIcon(alert.type)
        }

        // Store alert ID for actions
        element.dataset.alertId = alert.id

        return element
    }

    /**
     * Resolve icon - can be SVG string or Lucide icon name
     */
    resolveIcon(icon) {
        // If it starts with '<', assume it's SVG
        if (icon.trim().startsWith('<')) {
            return icon
        }

        // Otherwise, it's a Lucide icon name - create placeholder and let Lucide render it
        return `<i data-lucide="${icon}" class="size-6"></i>`
    }

    /**
     * Get styles for alert type
     */
    getStyles(type) {
        const styles = {
            success: { bg: '!bg-card', border: 'border-l-success', text: 'text-success' },
            warning: { bg: '!bg-card', border: 'border-l-warning', text: 'text-warning' },
            error: { bg: '!bg-card', border: 'border-l-error', text: 'text-error' },
        }
        return styles[type] || styles.warning
    }

    /**
     * Get SVG icon for alert type
     */
    getIcon(type) {
        const icons = {
            success: '<svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
            warning: '<svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>',
            error: '<svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
        }
        return icons[type] || icons.warning
    }

    /**
     * Update badge count
     */
    updateBadge(count) {
        if (!this.hasBadgeTarget) return

        this.badgeTarget.textContent = count
        this.badgeTarget.classList.toggle('hidden', count === 0)
    }

    // ==================
    // Actions (from DOM)
    // ==================

    /**
     * Dismiss a single alert (data-action="click->alerts#dismiss")
     */
    dismiss(event) {
        const alertEl = event.target.closest('[data-alert-id]')
        if (alertEl) {
            alertsStore.dismiss(alertEl.dataset.alertId)
        }
    }

    /**
     * Dismiss all alerts (data-action="click->alerts#dismissAll")
     */
    dismissAll() {
        alertsStore.dismissAll()
    }

    /**
     * Pause timers on hover (data-action="mouseenter->alerts#pause")
     */
    pause() {
        alertsStore.pause()
    }

    /**
     * Resume timers on hover end (data-action="mouseleave->alerts#resume")
     */
    resume() {
        alertsStore.resume()
    }
}
