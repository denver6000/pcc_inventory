export const ui = {
  pageBg: 'bg-slate-50',
  shell: 'min-h-screen',
  card: 'bg-white border border-slate-200 shadow-sm rounded-xl',
  insetCard: 'bg-white border border-slate-200 rounded-xl',
  table: 'bg-white border border-slate-200 rounded-xl overflow-hidden',
  tableHead: 'bg-slate-50 text-slate-500 uppercase text-[11px] font-semibold tracking-[0.12em]',
  heading: 'text-base font-semibold text-slate-900',
  subheading: 'text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500',
  muted: 'text-xs text-slate-500',
  badge: 'inline-flex items-center gap-1 rounded-full bg-slate-900 text-white text-[11px] px-2 py-0.5 font-semibold',
  input: 'w-full border border-slate-300 focus:border-slate-500 focus:ring-2 focus:ring-slate-200 rounded-lg px-3 py-2 text-sm bg-white shadow-[0_1px_0_rgba(15,23,42,0.04)]',
  inputDense: 'w-full border border-slate-300 focus:border-slate-500 focus:ring-2 focus:ring-slate-200 rounded-md px-2.5 py-1.5 text-sm bg-white shadow-[0_1px_0_rgba(15,23,42,0.04)]',
  fieldLabel: 'block text-xs font-medium text-slate-500 mb-1',
  tableCell: 'py-2.5 px-3 text-sm text-slate-700',
  tableCellMuted: 'py-2.5 px-3 text-sm text-slate-500',
  pill: (active) => [
    'text-xs px-2.5 py-1 rounded-full border transition-colors shadow-[0_1px_0_rgba(15,23,42,0.04)]',
    active
      ? 'border-slate-900 bg-slate-900 text-white'
      : 'border-slate-200 text-slate-500 hover:border-slate-400 hover:text-slate-900',
  ].join(' '),
  ghost: 'text-xs text-slate-500 hover:text-slate-900 transition-colors',
  button: {
    primary: 'inline-flex items-center gap-1 bg-slate-900 text-white text-xs font-semibold px-4 py-2 rounded-lg shadow-sm hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors',
    secondary: 'inline-flex items-center gap-1 border border-slate-300 text-slate-700 text-xs font-semibold px-4 py-2 rounded-lg shadow-sm hover:border-slate-400 hover:text-slate-900 disabled:opacity-50 disabled:cursor-not-allowed transition-colors',
    ghost: 'inline-flex items-center gap-1 text-xs text-slate-500 hover:text-slate-900 px-2 py-1 transition-colors',
    danger: 'inline-flex items-center gap-1 bg-rose-600 text-white text-xs font-semibold px-4 py-2 rounded-lg shadow-sm hover:bg-rose-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors',
    subtle: 'inline-flex items-center gap-1 bg-slate-100 text-slate-700 text-xs font-semibold px-3 py-1.5 rounded-lg hover:bg-slate-200 transition-colors',
    icon: 'inline-flex items-center justify-center h-8 w-8 rounded-lg border border-slate-300 text-slate-500 hover:border-slate-400 hover:text-slate-900 transition-colors',
  },
};
