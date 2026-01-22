/**
 * Cortex - General utilities
 */

// ================================
// DOM Utilities
// ================================
export const dom = {
    /**
     * Simple querySelector
     * @param {string} selector
     * @returns {Element}
     * @throws {Error} if element not found
     */
    $(selector) {
        const element = document.querySelector(selector);
        if (!element) {
            throw new Error(`Element not found: ${selector}`);
        }
        return element;
    },

    /**
     * querySelectorAll as array
     * @param {string} selector
     * @returns {Array}
     * @throws {Error} if no elements found
     */
    $$(selector) {
        const elements = Array.from(document.querySelectorAll(selector));
        if (elements.length === 0) {
            throw new Error(`No elements found: ${selector}`);
        }
        return elements;
    },

    /**
     * Find closest parent matching selector
     * @param {Element} element
     * @param {string} selector
     * @returns {Element|null}
     */
    closest(element, selector) {
        return element.closest(selector);
    },

    /**
     * Add class(es) to element
     * @param {Element} element
     * @param {string|string[]} classes
     */
    addClass(element, classes) {
        if (!element) return;
        const classList = Array.isArray(classes) ? classes : [classes];
        element.classList.add(...classList);
    },

    /**
     * Remove class(es) from element
     * @param {Element} element
     * @param {string|string[]} classes
     */
    removeClass(element, classes) {
        if (!element) return;
        const classList = Array.isArray(classes) ? classes : [classes];
        element.classList.remove(...classList);
    },

    /**
     * Toggle class on element
     * @param {Element} element
     * @param {string} className
     * @param {boolean} force
     */
    toggleClass(element, className, force) {
        if (!element) return;
        return element.classList.toggle(className, force);
    },

    /**
     * Check if element has class
     * @param {Element} element
     * @param {string} className
     * @returns {boolean}
     */
    hasClass(element, className) {
        return element ? element.classList.contains(className) : false;
    },

};

// ================================
// String Utilities
// ================================
export const str = {
    /**
     * Convert string to camelCase
     * @param {string} string
     * @returns {string}
     */
    toCamelCase(string) {
        return string.replace(/-([a-z])/g, (match, letter) => letter.toUpperCase());
    },

    /**
     * Convert string to kebab-case
     * @param {string} string
     * @returns {string}
     */
    toKebabCase(string) {
        return string.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase();
    },

    /**
     * Capitalize first letter
     * @param {string} string
     * @returns {string}
     */
    capitalize(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    },

    /**
     * Clean and normalize string
     * @param {string} string
     * @returns {string}
     */
    clean(string) {
        return string.trim().replace(/\s+/g, ' ');
    }
};

// ================================
// Object Utilities
// ================================
export const obj = {
    /**
     * Check if object is empty
     * @param {object} obj
     * @returns {boolean}
     */
    isEmpty(obj) {
        return Object.keys(obj).length === 0;
    },

    /**
     * Deep clone object
     * @param {*} obj
     * @returns {*}
     */
    deepClone(obj) {
        return JSON.parse(JSON.stringify(obj));
    },

    /**
     * Merge two objects
     * @param {object} target
     * @param {object} source
     * @returns {object}
     */
    merge(target, source) {
        return { ...target, ...source };
    }
};

// ================================
// Array Utilities
// ================================
export const arr = {
    /**
     * Remove duplicates from array
     * @param {Array} array
     * @returns {Array}
     */
    unique(array) {
        return [...new Set(array)];
    },

    /**
     * Group array elements by key
     * @param {Array} array
     * @param {string|function} key
     * @returns {object}
     */
    groupBy(array, key) {
        return array.reduce((groups, item) => {
            const groupKey = typeof key === 'function' ? key(item) : item[key];
            groups[groupKey] = groups[groupKey] || [];
            groups[groupKey].push(item);
            return groups;
        }, {});
    }
};

// ================================
// Type Checking
// ================================
export const is = {
    string(value) {
        return typeof value === 'string';
    },

    number(value) {
        return typeof value === 'number' && !isNaN(value);
    },

    object(value) {
        return value !== null && typeof value === 'object' && !Array.isArray(value);
    },

    array(value) {
        return Array.isArray(value);
    },

    function(value) {
        return typeof value === 'function';
    },

    nullish(value) {
        return value == null;
    }
};

// ================================
// Async Utilities
// ================================
export const async = {
    /**
     * Wait for delay
     * @param {number} ms
     * @returns {Promise}
     */
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    },

    /**
     * Debounce a function
     * @param {function} func
     * @param {number} delay
     * @returns {function}
     */
    debounce(func, delay) {
        let timeoutId;
        return (...args) => {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => func.apply(this, args), delay);
        };
    },

    /**
     * Throttle a function
     * @param {function} func
     * @param {number} delay
     * @returns {function}
     */
    throttle(func, delay) {
        let lastCall = 0;
        return (...args) => {
            const now = Date.now();
            if (now - lastCall >= delay) {
                lastCall = now;
                return func.apply(this, args);
            }
        };
    }
};

// Default export with all utilities
export default {
    dom,
    str,
    obj,
    arr,
    is,
    async,
    // Aliases
    $: dom.$,
    $$: dom.$$
};
