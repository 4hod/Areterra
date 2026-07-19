import { MOOD_EMOJI, Mood } from '../types';

export default function MoodPicker({
    value,
    onChange,
}: {
    value: Mood | null;
    onChange: (mood: Mood) => void;
}) {
    return (
        <div className="flex gap-1.5">
            {(Object.keys(MOOD_EMOJI) as Mood[]).map((mood) => (
                <button
                    key={mood}
                    type="button"
                    aria-label={mood}
                    aria-pressed={value === mood}
                    onClick={() => onChange(mood)}
                    className={`h-11 w-11 rounded-full text-2xl transition ${
                        value === mood
                            ? 'bg-brand/15 ring-2 ring-brand scale-110'
                            : 'bg-slate-100 hover:bg-slate-200'
                    }`}
                >
                    {MOOD_EMOJI[mood]}
                </button>
            ))}
        </div>
    );
}
