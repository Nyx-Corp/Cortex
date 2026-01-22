import { createIcons, icons } from 'lucide';

const svgCache = new Map();

/**
 * PopFadeSpinner - Overlay spinner with success/failure states
 *
 * Usage:
 *   const spinner = new PopFadeSpinner(containerElement, {
 *     spinnerIcon: "loader-circle",
 *     successIcon: "check",
 *     failureIcon: "x",
 *   });
 *   spinner.start();
 *   spinner.stop(true); // or spinner.stop(false)
 */
export default class PopFadeSpinner {
    constructor(container, options = {}) {
        this.container = container;
        this.options = Object.assign(
            {
                overlayColor: 'bg-black/30',

                spinnerUrl: null,
                spinnerIcon: 'loader-circle',
                spinnerSize: 'w-16 h-16',
                spinnerStroke: null,
                spinnerColor: 'text-white',

                iconSize: 'w-16 h-16',
                iconWidth: 2,
                successIcon: 'check',
                successColor: 'text-green-500',
                failureIcon: 'x',
                failureColor: 'text-red-500',
                duration: 1800,
            },
            options
        );

        this.isFinishing = false;
        this.finishTimeout = null;

        this._createDOM();
    }

    _createDOM() {
        // Create overlay
        this.overlay = document.createElement('div');
        this.overlay.className =
            this.options.overlayColor +
            ' absolute inset-0 flex items-center justify-center z-50 pointer-events-none hidden';
        this.overlay.setAttribute('aria-live', 'polite');
        this.overlay.setAttribute('aria-label', 'Loading');
        this.overlay.setAttribute('role', 'status');

        // Spinner container (visible by default)
        this.spinnerContainer = document.createElement('div');
        this.spinnerContainer.className = 'spinner-container flex items-center justify-center';

        if (this.options.spinnerUrl) {
            this._loadSvgSpinner(this.options.spinnerUrl);
        } else {
            const spinner = document.createElement('i');
            spinner.dataset.lucide = this.options.spinnerIcon;
            spinner.setAttribute('stroke-width', this.options.iconWidth);
            spinner.className = [
                'spinner-icon',
                this.options.spinnerSize,
                'animate-spin',
                this.options.spinnerColor,
            ].join(' ');
            spinner.setAttribute('aria-hidden', 'true');
            this.spinnerContainer.appendChild(spinner);
        }

        // Success container (hidden by default)
        this.successContainer = document.createElement('div');
        this.successContainer.className = 'success-container hidden flex items-center justify-center';

        const successIcon = document.createElement('i');
        successIcon.dataset.lucide = this.options.successIcon;
        successIcon.className = ['success-icon', this.options.iconSize, this.options.successColor].join(' ');
        successIcon.setAttribute('aria-hidden', 'true');
        successIcon.setAttribute('stroke-width', this.options.iconWidth);

        this.successContainer.appendChild(successIcon);

        // Failure container (hidden by default)
        this.failureContainer = document.createElement('div');
        this.failureContainer.className = 'failure-container hidden flex items-center justify-center';

        const failureIcon = document.createElement('i');
        failureIcon.dataset.lucide = this.options.failureIcon;
        failureIcon.className = ['failure-icon', this.options.iconSize, this.options.failureColor].join(' ');
        failureIcon.setAttribute('aria-hidden', 'true');
        failureIcon.setAttribute('stroke-width', this.options.iconWidth);

        this.failureContainer.appendChild(failureIcon);

        // Add all containers to overlay
        this.overlay.appendChild(this.spinnerContainer);
        this.overlay.appendChild(this.successContainer);
        this.overlay.appendChild(this.failureContainer);

        // Force relative position for positioning context
        if (!this.container.style.position || this.container.style.position === 'static') {
            this.container.style.position = 'relative';
        }

        // Ensure overflow-hidden is present
        if (!this.container.className.includes('overflow-')) {
            this.container.classList.add('overflow-hidden');
        }

        this.container.appendChild(this.overlay);

        this.createIcons();
    }

    start() {
        if (!this.options || !this.container) {
            throw new Error('PopFadeSpinner: Cannot start - spinner has been destroyed');
        }

        this.isFinishing = false;
        if (this.finishTimeout) {
            clearTimeout(this.finishTimeout);
            this.finishTimeout = null;
        }

        this.spinnerContainer.classList.remove('hidden');
        this.successContainer.classList.add('hidden');
        this.failureContainer.classList.add('hidden');

        this.overlay.classList.remove('hidden');
        this.overlay.setAttribute('aria-label', 'Loading');

        this.dispatchEvent('started');
    }

    stop(success = true) {
        if (!this.overlay) return;

        this.isFinishing = true;
        this.overlay.setAttribute('aria-label', success ? 'Success' : 'Error');

        if (this.spinnerContainer) {
            this.spinnerContainer.classList.add('hidden');
        }

        const resultContainer = success ? this.successContainer : this.failureContainer;
        if (resultContainer) {
            resultContainer.classList.remove('hidden');
        }

        this.createIcons();

        requestAnimationFrame(() => {
            const svg = resultContainer?.querySelector('svg');
            if (svg) {
                svg.classList.add('animate-pop-fade');
                svg.style.animationDuration = `${this.options.duration}ms`;
            }
        });

        this.finishTimeout = setTimeout(() => this.stopOverlay(), this.options.duration);
        this.dispatchEvent(success ? 'success' : 'error');
    }

    isActive() {
        return this.overlay && !this.overlay.classList.contains('hidden');
    }

    cancel() {
        if (this.finishTimeout) {
            clearTimeout(this.finishTimeout);
            this.finishTimeout = null;
        }
        this.isFinishing = false;
        this.stopOverlay();
        this.dispatchEvent('cancelled');
    }

    stopOverlay() {
        if (this.finishTimeout) {
            clearTimeout(this.finishTimeout);
            this.finishTimeout = null;
        }
        if (this.overlay) {
            this.overlay.classList.add('hidden');
        }
        this.isFinishing = false;
        this.dispatchEvent('stopped');
    }

    createIcons() {
        try {
            if (typeof createIcons === 'function') {
                createIcons({ icons });
            } else {
                console.warn('PopFadeSpinner: Lucide createIcons not available');
            }
        } catch (error) {
            console.error('PopFadeSpinner: Error creating icons', error);
        }
    }

    async _loadSvgSpinner(url) {
        try {
            if (!this.options || !this.spinnerContainer) {
                throw new Error('PopFadeSpinner: Cannot load SVG - spinner has been destroyed');
            }

            let svgText;

            if (svgCache.has(url)) {
                svgText = svgCache.get(url);
            } else {
                const response = await fetch(url);
                svgText = await response.text();
                svgCache.set(url, svgText);
            }

            const parser = new DOMParser();
            const svgDoc = parser.parseFromString(svgText, 'image/svg+xml');
            const svgElement = svgDoc.querySelector('svg');

            if (!svgElement) throw new Error('No SVG found');

            if (!this.options || !this.spinnerContainer) {
                throw new Error('PopFadeSpinner: Spinner was destroyed during SVG loading');
            }

            svgElement.classList.add(
                'spinner-icon',
                ...this.options.spinnerSize.split(' '),
                ...this.options.spinnerColor.split(' ')
            );
            svgElement.setAttribute('aria-hidden', 'true');

            if (this.options.spinnerStroke) {
                svgElement.style.setProperty('--stroke-width', this.options.spinnerStroke);
            }

            this.spinnerContainer.appendChild(svgElement);
        } catch (error) {
            console.error('Failed to load SVG spinner:', error);
            if (this.options && this.spinnerContainer) {
                const fallbackSpinner = document.createElement('i');
                fallbackSpinner.dataset.lucide = 'loader-circle';
                fallbackSpinner.className = [
                    'spinner-icon',
                    this.options.spinnerSize,
                    'animate-spin',
                    this.options.spinnerColor,
                ].join(' ');
                this.spinnerContainer.appendChild(fallbackSpinner);
            }
        }
    }

    destroy() {
        this.cancel();

        if (this.overlay) {
            this.overlay.remove();
        }

        if (this.container && this.container.style.position === 'relative') {
            this.container.style.position = '';
        }

        this.container = null;
        this.overlay = null;
        this.spinnerContainer = null;
        this.successContainer = null;
        this.failureContainer = null;
        this.options = null;
    }

    dispatchEvent(eventType) {
        if (this.container && typeof CustomEvent !== 'undefined') {
            this.container.dispatchEvent(
                new CustomEvent(`spinner:${eventType}`, {
                    detail: { spinner: this },
                    bubbles: true,
                })
            );
        }
    }
}
