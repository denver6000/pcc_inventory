import React from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { ui } from '@/theme';

const UnitsIndex = ({ units }) => {
    const form = useForm({ name: '', abbreviation: '' });

    const submit = () => {
        form.post('/units', { onSuccess: () => form.reset() });
    };

    const remove = (unit) => {
        if (window.confirm(`Delete "${unit.name}"?\n\nItems using this unit will have their unit cleared.`)) {
            router.delete(`/units/${unit.id}`);
        }
    };

    const inputClass = ui.input;
    const btnPrimary = ui.button.primary;

    return (
        <>
            <Head title="Units" />

            <div className="flex items-center justify-between mb-5">
                <div>
                    <h1 className={ui.heading}>Units</h1>
                    <p className="text-xs text-slate-500">Create abbreviations used across items.</p>
                </div>
            </div>

            <div className={[ui.card, 'p-5 mb-5'].join(' ')}>
                <p className={[ui.subheading, 'mb-3'].join(' ')}>New Unit</p>
                <div className="flex flex-wrap items-end gap-3">
                    <div className="w-48">
                        <label className={ui.fieldLabel}>
                            Name <span className="text-red-400">*</span>
                        </label>
                        <input
                            type="text"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            className={inputClass}
                            placeholder="Kilogram"
                            onKeyDown={(e) => e.key === 'Enter' && submit()}
                        />
                        {form.errors.name && <p className="text-xs text-red-500 mt-0.5">{form.errors.name}</p>}
                    </div>
                    <div className="w-28">
                        <label className={ui.fieldLabel}>
                            Abbreviation <span className="text-red-400">*</span>
                        </label>
                        <input
                            type="text"
                            value={form.data.abbreviation}
                            onChange={(e) => form.setData('abbreviation', e.target.value)}
                            className={inputClass}
                            placeholder="kg"
                            onKeyDown={(e) => e.key === 'Enter' && submit()}
                        />
                        {form.errors.abbreviation && <p className="text-xs text-red-500 mt-0.5">{form.errors.abbreviation}</p>}
                    </div>
                    <div className="pb-px">
                        <button onClick={submit} disabled={form.processing} className={btnPrimary}>
                            Add Unit
                        </button>
                    </div>
                </div>
            </div>

            <div className={ui.table}>
                <table className="w-full">
                    <thead>
                        <tr className={`border-b border-slate-200 ${ui.tableHead}`}>
                            <th className="py-3 px-4 text-left">Name</th>
                            <th className="py-3 px-4 text-left">Abbreviation</th>
                            <th className="py-3 px-4 text-right">Items</th>
                            <th className="w-20 py-3 px-4"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {units.map((unit) => (
                            <tr
                                key={unit.id}
                                className="border-b border-slate-100 last:border-0 hover:bg-slate-50 transition-colors"
                            >
                                <td className="py-2.5 px-4 text-sm font-medium text-slate-900">{unit.name}</td>
                                <td className="py-2.5 px-4 text-sm text-slate-500">{unit.abbreviation}</td>
                                <td className="py-2.5 px-4 text-sm text-right tabular-nums text-slate-600">{unit.items_count}</td>
                                <td className="py-2.5 px-4 text-right">
                                    <button
                                        onClick={() => remove(unit)}
                                        className="text-xs text-slate-500 hover:text-rose-600 transition-colors"
                                        disabled={unit.items_count > 0}
                                        title={unit.items_count > 0 ? 'Unlink items first' : ''}
                                        style={unit.items_count > 0 ? { opacity: 0.3, cursor: 'not-allowed' } : {}}
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        ))}
                        {!units.length && (
                            <tr>
                                <td colSpan={4} className="py-14 text-center text-xs text-slate-400">
                                    No units yet. Use the form above to add one.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </>
    );
};

export default UnitsIndex;
