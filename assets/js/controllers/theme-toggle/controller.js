import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['icon', 'btn']

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
        const isDark = theme === 'dark'
        document.documentElement.classList.toggle('dark', isDark)
        if (this.hasBtnTarget) {
            this.btnTarget.classList.toggle('!bg-white/10', isDark)
            this.btnTarget.classList.toggle('!text-sidebar-foreground', isDark)
        }
        if (this.hasIconTarget) {
            this.iconTarget.setAttribute('data-lucide', isDark ? 'sun' : 'moon')
            window.createIcons?.()
        }
    }
}
