/**
 * TabNamespace - Tab/Panel manager with auto-discovery
 *
 * Usage:
 * const tabs = new TabNamespace('metadata', {
 *     onEnable: (key, elements, namespace) => { ... },
 *     onDisable: (key, elements, namespace) => { ... }
 * });
 *
 * HTML:
 * <button data-trigger="metadata:view-0">Button</button>
 * <div data-panel="metadata:view-0">Panel 1</div>
 * <div data-panel="metadata:view-0">Panel 2</div> <!-- Multiple panels OK -->
 */
export class TabNamespace {
    constructor(namespace, options = {}) {
        this.namespace = namespace;
        this.currentKey = null;
        this.panels = new Map(); // key -> Set of elements
        this.triggers = new Map(); // key -> Array of triggers

        // Global callbacks (called once regardless of panel count)
        this.onEnable = options.onEnable || (() => {});
        this.onDisable = options.onDisable || (() => {});

        // Auto-discover panels and triggers
        this.discover();
    }

    /**
     * Discover all panels and triggers in DOM
     */
    discover() {
        this.discoverPanels();
        this.discoverTriggers();
    }

    /**
     * Discover all panels with data-panel="namespace:key"
     */
    discoverPanels() {
        const panels = document.querySelectorAll(`[data-panel^="${this.namespace}:"]`);

        panels.forEach(panel => {
            const [namespace, key] = panel.dataset.panel.split(':');
            if (namespace === this.namespace) {
                if (!this.panels.has(key)) {
                    this.panels.set(key, new Set());
                }
                this.panels.get(key).add(panel);

                // If panel is visible, it's the current one
                if (!panel.classList.contains('hidden')) {
                    this.currentKey = key;
                }
            }
        });
    }

    /**
     * Discover all triggers with data-trigger="namespace:key"
     */
    discoverTriggers() {
        const triggers = document.querySelectorAll(`[data-trigger^="${this.namespace}:"]`);

        triggers.forEach(trigger => {
            const [namespace, key] = trigger.dataset.trigger.split(':');
            if (namespace === this.namespace) {
                if (!this.triggers.has(key)) {
                    this.triggers.set(key, []);
                }
                this.triggers.get(key).push(trigger);

                // Bind click event
                trigger.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.activate(key);
                });

                // Mark as active if current
                if (key === this.currentKey) {
                    trigger.dataset.active = 'true';
                    trigger.classList.add('active');
                }
            }
        });
    }

    /**
     * Activate a panel by key
     * @param {string} key - Panel key to activate
     * @param {Object} options - Options { silent: boolean, force: boolean }
     * @returns {boolean} - True if activation succeeded
     */
    activate(key, options = {}) {
        // If already active, do nothing (unless force = true)
        if (this.currentKey === key && !options.force) {
            return true;
        }

        const panels = this.panels.get(key);
        if (!panels || panels.size === 0) {
            console.warn(`No panels found for: ${this.namespace}:${key}`);
            return false;
        }

        // Deactivate all current panels
        if (this.currentKey) {
            const currentPanels = this.panels.get(this.currentKey);
            if (currentPanels) {
                // Hide ALL panels at once
                currentPanels.forEach(panel => {
                    panel.classList.add('hidden');
                    panel.dataset.active = 'false';
                });

                // ONE onDisable call with ALL elements
                if (!options.silent) {
                    this.onDisable(
                        this.currentKey,
                        Array.from(currentPanels),
                        this.namespace
                    );
                }
            }

            // Deactivate triggers for old panel
            this.updateTriggerStates(this.currentKey, false);
        }

        // Activate ALL new panels
        panels.forEach(panel => {
            panel.classList.remove('hidden');
            panel.dataset.active = 'true';
        });

        // Activate triggers for new panel
        this.updateTriggerStates(key, true);

        // ONE onEnable call with ALL elements
        if (!options.silent) {
            this.onEnable(
                key,
                Array.from(panels),
                this.namespace
            );
        }

        this.currentKey = key;
        return true;
    }

    /**
     * Update visual state of triggers
     * @param {string} key - Trigger key to update
     * @param {boolean} active - True to activate, false to deactivate
     */
    updateTriggerStates(key, active) {
        if (!active) {
            // Deactivate triggers for specific key
            const triggers = this.triggers.get(key);
            if (triggers) {
                triggers.forEach(trigger => {
                    trigger.dataset.active = 'false';
                    trigger.classList.remove('active');
                });
            }
        } else {
            // Deactivate ALL namespace triggers first
            this.triggers.forEach((triggers) => {
                triggers.forEach(trigger => {
                    trigger.dataset.active = 'false';
                    trigger.classList.remove('active');
                });
            });

            // Then activate specific key triggers
            const activeTrigers = this.triggers.get(key);
            if (activeTrigers) {
                activeTrigers.forEach(trigger => {
                    trigger.dataset.active = 'true';
                    trigger.classList.add('active');
                });
            }
        }
    }

    /**
     * Get current state
     * @returns {Object} - { key: string, elements: Array }
     */
    getCurrent() {
        return {
            key: this.currentKey,
            elements: this.currentKey ? Array.from(this.panels.get(this.currentKey) || []) : []
        };
    }

    /**
     * Refresh discovery after dynamic element addition
     * Preserves current state if possible
     */
    refresh() {
        const current = this.currentKey;

        this.panels.clear();
        this.triggers.clear();
        this.discover();

        if (current && this.panels.has(current)) {
            this.activate(current, { silent: true });
        }
    }

    /**
     * Get all available keys
     * @returns {Array}
     */
    getKeys() {
        return Array.from(this.panels.keys());
    }

    /**
     * Check if key exists
     * @param {string} key
     * @returns {boolean}
     */
    hasKey(key) {
        return this.panels.has(key);
    }
}

export default TabNamespace;
