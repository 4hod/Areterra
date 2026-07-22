// Branded replacement for window.confirm()/window.prompt() (SPEC.md: "AH.utils.promptModal()
// — branded modal replacing ALL native prompt()/confirm() calls"). A single <DialogHost />
// is mounted once in AppShell; any file can import confirmDialog()/promptDialog() and await
// them like the native functions, but they render as a proper branded modal instead of the
// browser's own dialog chrome.

type ConfirmRequest = { message: string; resolve: (v: boolean) => void };
type PromptRequest = { message: string; defaultValue: string; resolve: (v: string | null) => void };

export interface DialogState {
    confirm: ConfirmRequest | null;
    prompt: PromptRequest | null;
}

let state: DialogState = { confirm: null, prompt: null };
let listeners: Array<(s: DialogState) => void> = [];

function notify() {
    listeners.forEach((fn) => fn(state));
}

export function subscribeDialogs(fn: (s: DialogState) => void) {
    listeners.push(fn);
    return () => {
        listeners = listeners.filter((l) => l !== fn);
    };
}

export function confirmDialog(message: string): Promise<boolean> {
    return new Promise((resolve) => {
        state = { ...state, confirm: { message, resolve } };
        notify();
    });
}

export function promptDialog(message: string, defaultValue = ''): Promise<string | null> {
    return new Promise((resolve) => {
        state = { ...state, prompt: { message, defaultValue, resolve } };
        notify();
    });
}

export function resolveConfirm(value: boolean) {
    state.confirm?.resolve(value);
    state = { ...state, confirm: null };
    notify();
}

export function resolvePrompt(value: string | null) {
    state.prompt?.resolve(value);
    state = { ...state, prompt: null };
    notify();
}
