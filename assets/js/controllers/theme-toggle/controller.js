import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['icon']

    connect() {
        this.#apply(this.#preference())
    }

    toggle() {
        const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark'
        localStorage.setItem('theme', next)
        this.#apply(next)
    }

    #preference() {
        return localStorage.getItem('theme')
            ?? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
    }

    #apply(theme) {
        document.documentElement.classList.toggle('dark', theme === 'dark')
        if (this.hasIconTarget) {
            this.iconTarget.setAttribute('data-lucide', theme === 'dark' ? 'sun' : 'moon')
            window.createIcons?.()
        }
    }
}
