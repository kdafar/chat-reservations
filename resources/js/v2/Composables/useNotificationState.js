// Cross-component reactive state for the notification subsystem.
// AppLayout reads `unreadCount` for the bell badge; NotificationPoller
// writes to it after each poll. `pushToast` is wired by FlashToasts on
// mount so any module can dispatch a toast without prop-drilling.

import { ref } from 'vue'

export const unreadCount = ref(0)

let pushFn = null

export function registerToastPusher(fn) {
    pushFn = fn
}

export function pushToast(toast) {
    if (typeof pushFn === 'function') pushFn(toast)
}
