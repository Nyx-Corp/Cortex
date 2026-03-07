/**
 * Alerts Store - State management for alerts
 *
 * Manages:
 * - List of active alerts
 * - Auto-dismiss timers
 * - Visibility states (visible/hidden)
 * - Hover pause/resume
 *
 * Emits custom events for the view layer to react to.
 */

const DEFAULTS = {
    duration: 2000,
}

class AlertsStore {
    constructor() {
        this.alerts = new Map()
        this.isHovering = false
        this.idCounter = 0
        this.duration = DEFAULTS.duration
    }

    /**
     * Add a new alert
     * @param {string} type - 'success' | 'warning' | 'error'
     * @param {string} title
     * @param {string} message - optional
     * @param {string} icon - optional custom icon (SVG or lucide icon name)
     * @returns {string} alert id
     */
    add(type, title, message = '', icon = null) {
        const id = `alert-${++this.idCounter}`

        const alert = {
            id,
            type,
            title,
            message,
            icon,
            hidden: false,
            timeout: null,
        }

        // Show all hidden alerts when a new one is added
        for (const existingAlert of this.alerts.values()) {
            if (existingAlert.hidden) {
                this.show(existingAlert.id)
            }
        }

        this.alerts.set(id, alert)
        this.emit('alert:added', alert)

        // Start auto-hide timer for ALL alerts (unless hovering)
        if (!this.isHovering) {
            for (const a of this.alerts.values()) {
                this.startTimer(a)
            }
        }

        return id
    }

    /**
     * Start auto-hide timer for an alert
     */
    startTimer(alert) {
        if (alert.timeout) clearTimeout(alert.timeout)

        alert.timeout = setTimeout(() => {
            this.hide(alert.id)
        }, this.duration)
    }

    /**
     * Clear timer for an alert
     */
    clearTimer(alert) {
        if (alert.timeout) {
            clearTimeout(alert.timeout)
            alert.timeout = null
        }
    }

    /**
     * Hide an alert (fade out, but keep in store for hover)
     */
    hide(id) {
        const alert = this.alerts.get(id)
        if (!alert || alert.hidden) return

        alert.hidden = true
        this.clearTimer(alert)
        this.emit('alert:hidden', alert)
    }

    /**
     * Show a hidden alert (hover reappearance)
     */
    show(id) {
        const alert = this.alerts.get(id)
        if (!alert || !alert.hidden) return

        alert.hidden = false
        this.emit('alert:shown', alert)
    }

    /**
     * Dismiss an alert permanently (mark as read)
     */
    dismiss(id) {
        const alert = this.alerts.get(id)
        if (!alert) return

        this.clearTimer(alert)
        this.alerts.delete(id)
        this.emit('alert:dismissed', alert)
        this.emit('alert:count', this.count)
    }

    /**
     * Dismiss all alerts
     */
    dismissAll() {
        for (const id of this.alerts.keys()) {
            this.dismiss(id)
        }
    }

    /**
     * Pause all timers (hover start)
     */
    pause() {
        this.isHovering = true

        // Show all hidden alerts
        for (const alert of this.alerts.values()) {
            this.clearTimer(alert)
            if (alert.hidden) {
                this.show(alert.id)
            }
        }
    }

    /**
     * Resume all timers (hover end)
     */
    resume() {
        this.isHovering = false

        // Restart timers for all alerts
        for (const alert of this.alerts.values()) {
            this.startTimer(alert)
        }
    }

    /**
     * Get current alert count
     */
    get count() {
        return this.alerts.size
    }

    /**
     * Emit custom event
     */
    emit(name, detail) {
        document.dispatchEvent(new CustomEvent(name, { detail }))
    }
}

// Singleton instance
export const alertsStore = new AlertsStore()
