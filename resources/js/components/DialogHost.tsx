import { FormEvent, useEffect, useState } from 'react';
import { DialogState, resolveConfirm, resolvePrompt, subscribeDialogs } from '../utils/dialogs';

export default function DialogHost() {
    const [state, setState] = useState<DialogState>({ confirm: null, prompt: null });
    const [promptValue, setPromptValue] = useState('');

    useEffect(() => subscribeDialogs(setState), []);

    useEffect(() => {
        if (state.prompt) setPromptValue(state.prompt.defaultValue);
    }, [state.prompt]);

    if (!state.confirm && !state.prompt) return null;

    function submitPrompt(e: FormEvent) {
        e.preventDefault();
        resolvePrompt(promptValue);
    }

    return (
        <div className="fixed inset-0 z-[60] bg-black/40 flex items-center justify-center px-4">
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-sm p-5">
                {state.confirm && (
                    <>
                        <p className="text-sm font-medium text-brand-dark mb-5">{state.confirm.message}</p>
                        <div className="flex gap-2 justify-end">
                            <button
                                onClick={() => resolveConfirm(false)}
                                className="rounded-full bg-slate-100 text-slate-600 font-semibold text-sm px-4 py-2"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={() => resolveConfirm(true)}
                                autoFocus
                                className="rounded-full bg-brand text-white font-semibold text-sm px-4 py-2"
                            >
                                OK
                            </button>
                        </div>
                    </>
                )}

                {state.prompt && (
                    <form onSubmit={submitPrompt}>
                        <p className="text-sm font-medium text-brand-dark mb-2">{state.prompt.message}</p>
                        <input
                            autoFocus
                            value={promptValue}
                            onChange={(e) => setPromptValue(e.target.value)}
                            className="w-full rounded-lg border border-slate-300 px-3 mb-5"
                        />
                        <div className="flex gap-2 justify-end">
                            <button
                                type="button"
                                onClick={() => resolvePrompt(null)}
                                className="rounded-full bg-slate-100 text-slate-600 font-semibold text-sm px-4 py-2"
                            >
                                Cancel
                            </button>
                            <button type="submit" className="rounded-full bg-brand text-white font-semibold text-sm px-4 py-2">
                                OK
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </div>
    );
}
