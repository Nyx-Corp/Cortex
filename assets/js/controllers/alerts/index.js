/**
 * Alerts module - Unified notification system
 *
 * Architecture:
 * - store.js   : State management (alerts list, timers, hover state)
 * - triggers.js: Entry points (window.toast, flash messages, events)
 * - controller.js: Stimulus controller for DOM rendering
 */

export { alertsStore } from './store.js'
export { initTriggers, processFlashMessages } from './triggers.js'
export { default as AlertsController } from './controller.js'
