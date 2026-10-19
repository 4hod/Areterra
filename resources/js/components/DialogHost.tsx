import { FormEvent, useCallback, useEffect, useState } from 'react';
import { DialogState, resolveConfirm, resolvePrompt, subscribeDialogs } from '../utils/dialogs';
import Modal from './Modal';

export default function DialogHost() {
    const [state, setState] = useState<DialogState>({ confirm: null, prompt: null });
    const [promptValue, setPromptValue] = useState('');

    useEffect(() => subscribeDialogs(setState), []);

    useEffect(() => {
        if (state.prompt) setPromptValue(state.prompt.defaultValue);
    }, [state.prompt]);

    const close = useCallback(() => state.prompt ? resolvePrompt(null) : resolveConfirm(false), [state.prompt]);

    if (!state.confirm && !state.prompt) return null;

    function submitPrompt(e: FormEvent) {
        e.preventDefault();
        resolvePrompt(promptValue);
    }

    return (
        <Modal open title={state.prompt ? 'Add details' : 'Please confirm'} onClose={close}>
            {state.confirm && (
                <>
                    <p className="mb-5 text-sm text-slate-700">{state.confirm.message}</p>
                    <div className="grid grid-cols-2 gap-2">
                        <button type="button" onClick={() => resolveConfirm(false)} className="min-h-11 rounded-lg bg-slate-100 px-4 text-sm font-bold text-slate-700">
                            Cancel
                        </button>
                        <button type="button" onClick={() => resolveConfirm(true)} autoFocus className="min-h-11 rounded-lg bg-brand px-4 text-sm font-bold text-white">
                            Confirm
                        </button>
                    </div>
                </>
            )}

            {state.prompt && (
                <form onSubmit={submitPrompt} className="space-y-4">
                    <label htmlFor="hub-dialog-response" className="block text-sm font-semibold text-brand-dark">
                        {state.prompt.message}
                    </label>
                    <textarea
                        id="hub-dialog-response"
                        autoFocus
                        value={promptValue}
                        onChange={(e) => setPromptValue(e.target.value)}
                        className="w-full rounded-lg border border-slate-300 p-3"
                        rows={3}
                    />
                    <div className="grid grid-cols-2 gap-2">
                        <button type="button" onClick={() => resolvePrompt(null)} className="min-h-11 rounded-lg bg-slate-100 px-4 text-sm font-bold text-slate-700">
                            Cancel
                        </button>
                        <button type="submit" className="min-h-11 rounded-lg bg-brand px-4 text-sm font-bold text-white">
                            Save
                        </button>
                    </div>
                </form>
            )}
        </Modal>
    );
}
