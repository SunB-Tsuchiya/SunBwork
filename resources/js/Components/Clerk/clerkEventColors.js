// Prepress/Board.vue の CARD_COLORS と同じキー・同じ配色（Clerk カレンダー用）
export const CLERK_EVENT_COLORS = {
    indigo: { swatch: 'bg-indigo-400', hex: '#818cf8', text: '#ffffff' },
    blue:   { swatch: 'bg-blue-400',   hex: '#60a5fa', text: '#ffffff' },
    cyan:   { swatch: 'bg-cyan-400',   hex: '#22d3ee', text: '#ffffff' },
    teal:   { swatch: 'bg-teal-500',   hex: '#14b8a6', text: '#ffffff' },
    green:  { swatch: 'bg-green-500',  hex: '#22c55e', text: '#ffffff' },
    yellow: { swatch: 'bg-yellow-400', hex: '#facc15', text: '#78350f' },
    orange: { swatch: 'bg-orange-400', hex: '#fb923c', text: '#ffffff' },
    red:    { swatch: 'bg-red-400',    hex: '#f87171', text: '#ffffff' },
    pink:   { swatch: 'bg-pink-400',   hex: '#f472b6', text: '#ffffff' },
    purple: { swatch: 'bg-purple-400', hex: '#c084fc', text: '#ffffff' },
    gray:   { swatch: 'bg-gray-400',   hex: '#9ca3af', text: '#ffffff' },
};

export const CLERK_EVENT_COLOR_KEYS = Object.keys(CLERK_EVENT_COLORS);

export const CLERK_DEFAULT_COLOR_KEY = 'indigo';
