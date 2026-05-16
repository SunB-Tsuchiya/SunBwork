// 在席ステータスの定義（モーダル・バッジ・ボード共通）

// グループ別ボタンカラー（パステル調）+ dot/badge クラスを含む
export const STATUS_GROUPS = {
    green:   { btnBg: 'bg-green-100',   btnText: 'text-green-700',   btnActiveBg: 'bg-green-200',   btnActiveBorder: 'border-green-500',   dot: 'bg-green-400',   badge: 'bg-green-500',   textColor: 'text-green-700'   },
    emerald: { btnBg: 'bg-emerald-100', btnText: 'text-emerald-700', btnActiveBg: 'bg-emerald-200', btnActiveBorder: 'border-emerald-500', dot: 'bg-emerald-400', badge: 'bg-emerald-500', textColor: 'text-emerald-700' },
    gray:    { btnBg: 'bg-gray-100',    btnText: 'text-gray-600',    btnActiveBg: 'bg-gray-200',    btnActiveBorder: 'border-gray-500',    dot: 'bg-gray-400',    badge: 'bg-gray-400',    textColor: 'text-gray-500'    },
    purple:  { btnBg: 'bg-purple-100',  btnText: 'text-purple-700',  btnActiveBg: 'bg-purple-200',  btnActiveBorder: 'border-purple-500',  dot: 'bg-purple-400',  badge: 'bg-purple-500',  textColor: 'text-purple-700'  },
    violet:  { btnBg: 'bg-violet-100',  btnText: 'text-violet-700',  btnActiveBg: 'bg-violet-200',  btnActiveBorder: 'border-violet-500',  dot: 'bg-violet-400',  badge: 'bg-violet-500',  textColor: 'text-violet-700'  },
    indigo:  { btnBg: 'bg-indigo-100',  btnText: 'text-indigo-700',  btnActiveBg: 'bg-indigo-200',  btnActiveBorder: 'border-indigo-500',  dot: 'bg-indigo-400',  badge: 'bg-indigo-500',  textColor: 'text-indigo-700'  },
    sky:     { btnBg: 'bg-sky-100',     btnText: 'text-sky-700',     btnActiveBg: 'bg-sky-200',     btnActiveBorder: 'border-sky-500',     dot: 'bg-sky-400',     badge: 'bg-sky-500',     textColor: 'text-sky-700'     },
    amber:   { btnBg: 'bg-amber-100',   btnText: 'text-amber-700',   btnActiveBg: 'bg-amber-200',   btnActiveBorder: 'border-amber-500',   dot: 'bg-amber-400',   badge: 'bg-amber-500',   textColor: 'text-amber-700'   },
    orange:  { btnBg: 'bg-orange-100',  btnText: 'text-orange-700',  btnActiveBg: 'bg-orange-200',  btnActiveBorder: 'border-orange-500',  dot: 'bg-orange-400',  badge: 'bg-orange-500',  textColor: 'text-orange-700'  },
    cyan:    { btnBg: 'bg-cyan-100',    btnText: 'text-cyan-700',    btnActiveBg: 'bg-cyan-200',    btnActiveBorder: 'border-cyan-500',    dot: 'bg-cyan-400',    badge: 'bg-cyan-500',    textColor: 'text-cyan-700'    },
    teal:    { btnBg: 'bg-teal-100',    btnText: 'text-teal-700',    btnActiveBg: 'bg-teal-200',    btnActiveBorder: 'border-teal-500',    dot: 'bg-teal-400',    badge: 'bg-teal-500',    textColor: 'text-teal-700'    },
    rose:    { btnBg: 'bg-rose-100',    btnText: 'text-rose-700',    btnActiveBg: 'bg-rose-200',    btnActiveBorder: 'border-rose-500',    dot: 'bg-rose-400',    badge: 'bg-rose-500',    textColor: 'text-rose-700'    },
    pink:    { btnBg: 'bg-pink-100',    btnText: 'text-pink-700',    btnActiveBg: 'bg-pink-200',    btnActiveBorder: 'border-pink-500',    dot: 'bg-pink-400',    badge: 'bg-pink-500',    textColor: 'text-pink-700'    },
    fuchsia: { btnBg: 'bg-fuchsia-100', btnText: 'text-fuchsia-700', btnActiveBg: 'bg-fuchsia-200', btnActiveBorder: 'border-fuchsia-500', dot: 'bg-fuchsia-400', badge: 'bg-fuchsia-500', textColor: 'text-fuchsia-700' },
    yellow:  { btnBg: 'bg-yellow-100',  btnText: 'text-yellow-700',  btnActiveBg: 'bg-yellow-200',  btnActiveBorder: 'border-yellow-500',  dot: 'bg-yellow-400',  badge: 'bg-yellow-400',  textColor: 'text-yellow-700'  },
    red:     { btnBg: 'bg-red-100',     btnText: 'text-red-700',     btnActiveBg: 'bg-red-200',     btnActiveBorder: 'border-red-500',     dot: 'bg-red-400',     badge: 'bg-red-500',     textColor: 'text-red-700'     },
};

// カラーピッカー用の選択肢（全グループを日本語名付きで列挙）
export const COLOR_OPTIONS = [
    { key: 'green',   label: '緑'         },
    { key: 'emerald', label: '翠緑'        },
    { key: 'teal',    label: '青緑'        },
    { key: 'cyan',    label: '水色'        },
    { key: 'sky',     label: 'スカイ'      },
    { key: 'indigo',  label: '藍'          },
    { key: 'violet',  label: 'バイオレット' },
    { key: 'purple',  label: '紫'          },
    { key: 'fuchsia', label: '赤紫'        },
    { key: 'pink',    label: 'ピンク'      },
    { key: 'rose',    label: 'ローズ'      },
    { key: 'red',     label: '赤'          },
    { key: 'orange',  label: 'オレンジ'    },
    { key: 'amber',   label: '黄橙'        },
    { key: 'yellow',  label: '黄'          },
    { key: 'gray',    label: 'グレー'      },
];

// 18ステータス定義（6行 × 3列 グリッド順）
export const STATUSES = [
    // Row 1 — 在籍系（緑）
    { slug: 'present',          label: '在席',     group: 'green',   dot: 'bg-green-500',   color: 'bg-green-500',   textColor: 'text-green-700'   },
    { slug: 'present_kodai',    label: '小台在席', group: 'emerald', dot: 'bg-emerald-400', color: 'bg-emerald-400', textColor: 'text-emerald-600' },
    { slug: 'left',             label: '退社',     group: 'gray',    dot: 'bg-gray-400',    color: 'bg-gray-400',    textColor: 'text-gray-500',   special: true },
    // Row 2 — 会議系（紫）
    { slug: 'meeting',          label: '会議',     group: 'purple',  dot: 'bg-purple-500',  color: 'bg-purple-500',  textColor: 'text-purple-700'  },
    { slug: 'discussion',       label: '打合せ',   group: 'violet',  dot: 'bg-violet-500',  color: 'bg-violet-500',  textColor: 'text-violet-700'  },
    { slug: 'client_reception', label: '来客対応', group: 'indigo',  dot: 'bg-indigo-500',  color: 'bg-indigo-500',  textColor: 'text-indigo-700'  },
    // Row 3 — リモート・時刻系
    { slug: 'telework',         label: 'テレワーク', group: 'sky',   dot: 'bg-sky-500',     color: 'bg-sky-500',     textColor: 'text-sky-700'     },
    { slug: 'late',             label: '遅刻',     group: 'amber',   dot: 'bg-amber-500',   color: 'bg-amber-500',   textColor: 'text-amber-700'   },
    { slug: 'early_leave',      label: '早退',     group: 'orange',  dot: 'bg-orange-500',  color: 'bg-orange-500',  textColor: 'text-orange-700'  },
    // Row 4 — 外出系（シアン）
    { slug: 'moving',           label: '移動中',   group: 'cyan',    dot: 'bg-cyan-500',    color: 'bg-cyan-500',    textColor: 'text-cyan-700'    },
    { slug: 'out',              label: '外出',     group: 'teal',    dot: 'bg-teal-500',    color: 'bg-teal-500',    textColor: 'text-teal-700'    },
    { slug: 'out_nr',           label: '外出NR',   group: 'teal',    dot: 'bg-teal-700',    color: 'bg-teal-700',    textColor: 'text-teal-800'    },
    // Row 5 — 休暇系（ピンク）
    { slug: 'paid_leave',       label: '有給休暇', group: 'rose',    dot: 'bg-rose-500',    color: 'bg-rose-500',    textColor: 'text-rose-700'    },
    { slug: 'half_am',          label: 'AM半休',   group: 'pink',    dot: 'bg-pink-500',    color: 'bg-pink-500',    textColor: 'text-pink-700'    },
    { slug: 'half_pm',          label: 'PM半休',   group: 'fuchsia', dot: 'bg-fuchsia-500', color: 'bg-fuchsia-500', textColor: 'text-fuchsia-700' },
    // Row 6 — その他
    { slug: 'away',             label: '離席',     group: 'yellow',  dot: 'bg-yellow-400',  color: 'bg-yellow-400',  textColor: 'text-yellow-700'  },
    { slug: 'train_delay',      label: '電車遅延', group: 'amber',   dot: 'bg-amber-500',   color: 'bg-amber-500',   textColor: 'text-amber-700'   },
    { slug: 'special_leave',    label: '特別休暇', group: 'red',     dot: 'bg-red-500',     color: 'bg-red-500',     textColor: 'text-red-700'     },
];

export function getStatus(slug) {
    return STATUSES.find(s => s.slug === slug) ?? STATUSES[0];
}

export function getStatusLabel(slug) {
    return getStatus(slug).label;
}

/**
 * DBのステータスオーダーレコード（{slug, custom_label, custom_color}）から
 * 表示用オブジェクトを解決する。カスタムラベル・カラーを優先し、
 * 未知のslugはグレーで処理する。
 */
export function resolveStatus(orderRecord) {
    const base     = STATUSES.find(s => s.slug === orderRecord.slug);
    const colorKey = orderRecord.custom_color ?? base?.group ?? 'gray';
    const group    = STATUS_GROUPS[colorKey] ?? STATUS_GROUPS.gray;
    return {
        slug:      orderRecord.slug,
        label:     orderRecord.custom_label ?? base?.label ?? orderRecord.slug,
        group:     colorKey,
        dot:       group.dot,
        color:     group.badge,
        textColor: group.textColor,
        special:   base?.special ?? false,
    };
}

/** モーダルボタンの Tailwind クラス配列を返す */
export function getBtnClasses(status, isActive) {
    const g = STATUS_GROUPS[status.group] ?? STATUS_GROUPS.gray;
    if (isActive) {
        return [g.btnActiveBg, g.btnText, g.btnActiveBorder, 'border', 'font-semibold', 'shadow-sm'];
    }
    return [g.btnBg, g.btnText, status.special ? 'border border-gray-300' : 'border border-transparent', 'hover:opacity-75'];
}
