import { useCallback, useMemo, useRef, useState } from 'react';
import { sortByTimeline } from './helpers';

/**
 * Custom hook encapsulating all batch-configuration state and logic
 * for the Produce page (sequential + distributed modes, drag-and-drop,
 * auto-assign, reset).
 *
 * @param {object|null} product  Currently selected product (with nested ingredients)
 * @param {number|string} quantity  Production quantity from the form
 */
export function useBatchConfig(product, quantity) {
    const [batchOrders, setBatchOrders] = useState({});           // { [itemId]: [{id, order}] }
    const [batchModes, setBatchModes] = useState({});             // { [itemId]: 'sequential' | 'distributed' }
    const [distributedAmounts, setDistributedAmounts] = useState({}); // { [itemId]: { [batchItemId]: number } }

    // DnD refs
    const dragItem = useRef(null);
    const dragOver = useRef(null);

    const qty = parseFloat(quantity) || 0;

    // ── Mode helpers ────────────────────────────────────────────────────

    const modeFor = (itemId) => batchModes[itemId] ?? 'sequential';

    const setMode = (itemId, mode) =>
        setBatchModes((prev) => ({ ...prev, [itemId]: mode }));

    // ── carryoverPlan ───────────────────────────────────────────────────
    // Shape: { [itemId]: { [batchItemId]: takeAmount } }

    const carryoverPlan = useMemo(() => {
        if (!product?.ingredients?.length || qty <= 0) return {};

        const plan = {};

        product.ingredients.forEach((ing) => {
            const needed = (parseFloat(ing.quantity) || 0) * qty;
            const allLines = ing.item?.restock_batch_items ?? [];
            const mode = modeFor(ing.item_id);

            if (mode === 'distributed') {
                const userAmounts = distributedAmounts[ing.item_id] ?? {};
                const takes = {};
                let totalTaken = 0;
                for (const line of allLines) {
                    const available = parseFloat(line.quantity_added) || 0;
                    const userAmt = parseFloat(userAmounts[line.id]) || 0;
                    const clamped = Math.min(Math.max(0, userAmt), available, needed - totalTaken);
                    if (clamped > 1e-9) {
                        takes[line.id] = parseFloat(clamped.toFixed(4));
                        totalTaken += clamped;
                    }
                }
                plan[ing.item_id] = takes;
            } else {
                // Sequential: user order → timeline-date FIFO fallback
                const orderList = batchOrders[ing.item_id] ?? [];
                const sorted = [...allLines].sort((a, b) => {
                    const oa = orderList.find((o) => o.id === a.id)?.order;
                    const ob = orderList.find((o) => o.id === b.id)?.order;
                    if (oa != null && ob != null) return oa - ob;
                    if (oa != null) return -1;
                    if (ob != null) return 1;
                    return sortByTimeline(a, b);
                });

                const takes = {};
                let remaining = needed;
                for (const line of sorted) {
                    if (remaining <= 1e-9) break;
                    const available = parseFloat(line.quantity_added) || 0;
                    if (available <= 1e-9) continue;
                    const take = Math.min(remaining, available);
                    takes[line.id] = parseFloat(take.toFixed(4));
                    remaining -= take;
                }
                plan[ing.item_id] = takes;
            }
        });

        return plan;
    }, [product, qty, batchOrders, batchModes, distributedAmounts]);

    // ── batchConfigData ─────────────────────────────────────────────────
    // Shape: { [itemId]: { needed, sourced, fulfilled, unit, itemName, batches: [...] } }

    const batchConfigData = useMemo(() => {
        if (!product?.ingredients?.length) return {};
        const data = {};

        product.ingredients.forEach((ing) => {
            const needed = (parseFloat(ing.quantity) || 0) * qty;
            const takes = carryoverPlan[ing.item_id] ?? {};
            const allLines = ing.item?.restock_batch_items ?? [];
            const orderList = batchOrders[ing.item_id] ?? [];
            const mode = modeFor(ing.item_id);

            const sorted = mode === 'distributed'
                ? [...allLines].sort(sortByTimeline)
                : [...allLines].sort((a, b) => {
                    const oa = orderList.find((o) => o.id === a.id)?.order;
                    const ob = orderList.find((o) => o.id === b.id)?.order;
                    if (oa != null && ob != null) return oa - ob;
                    if (oa != null) return -1;
                    if (ob != null) return 1;
                    return sortByTimeline(a, b);
                });

            let runningNeeded = needed;
            const batches = sorted.map((line) => {
                const take = takes[line.id] ?? 0;
                const available = parseFloat(line.quantity_added) || 0;
                runningNeeded = Math.max(0, parseFloat((runningNeeded - take).toFixed(4)));
                return {
                    line,
                    take,
                    available,
                    stillNeeded: runningNeeded,
                    isFull: take > 1e-9 && take >= available - 1e-9,
                    isPartial: take > 1e-9 && take < available - 1e-9,
                    isUntouched: take <= 1e-9,
                };
            });

            const sourced = Object.values(takes).reduce((s, t) => s + t, 0);
            data[ing.item_id] = {
                needed,
                sourced: parseFloat(sourced.toFixed(4)),
                fulfilled: sourced + 1e-9 >= needed && needed > 0,
                unit: ing.item?.unit?.abbreviation ?? '',
                itemName: ing.item?.name ?? '?',
                batches,
            };
        });

        return data;
    }, [product, qty, carryoverPlan, batchOrders, batchModes]);

    // ── Sequential auto-assign (timeline-date FIFO) ─────────────────────

    const autoAssignIngredient = (itemId) => {
        const ing = product?.ingredients?.find((i) => i.item_id === itemId);
        if (!ing) return;
        const lines = [...(ing.item?.restock_batch_items ?? [])].sort(sortByTimeline);
        setBatchOrders((prev) => ({
            ...prev,
            [itemId]: lines.map((line, idx) => ({ id: line.id, order: idx + 1 })),
        }));
    };

    /** FIFO-assign all ingredients of the current product */
    const primeBatchOrders = () => {
        if (!product?.ingredients?.length) return;
        const next = {};
        product.ingredients.forEach((ing) => {
            const lines = [...(ing.item?.restock_batch_items ?? [])].sort(sortByTimeline);
            next[ing.item_id] = lines.map((line, idx) => ({ id: line.id, order: idx + 1 }));
        });
        setBatchOrders(next);
    };

    // ── Distributed helpers ─────────────────────────────────────────────

    const setDistAmount = (itemId, lineId, val) => {
        setDistributedAmounts((prev) => ({
            ...prev,
            [itemId]: { ...(prev[itemId] ?? {}), [lineId]: val },
        }));
    };

    const autoDistributeEvenly = (itemId) => {
        const ing = product?.ingredients?.find((i) => i.item_id === itemId);
        if (!ing) return;
        const needed = (parseFloat(ing.quantity) || 0) * qty;
        const lines = ing.item?.restock_batch_items ?? [];
        const availables = lines
            .map((l) => ({ id: l.id, available: parseFloat(l.quantity_added) || 0 }))
            .filter((l) => l.available > 1e-9);
        if (!availables.length) return;

        let remaining = needed;
        const amounts = {};
        const share = remaining / availables.length;
        const underCap = [];
        for (const l of availables) {
            if (l.available < share) {
                amounts[l.id] = l.available;
                remaining -= l.available;
            } else {
                underCap.push(l);
            }
        }
        if (underCap.length) {
            const perLine = remaining / underCap.length;
            for (const l of underCap) {
                amounts[l.id] = parseFloat(Math.min(perLine, l.available).toFixed(4));
            }
        }
        setDistributedAmounts((prev) => ({ ...prev, [itemId]: amounts }));
    };

    // ── Drag-and-drop (sequential mode) ─────────────────────────────────

    const handleDragStart = useCallback((itemId, lineId) => {
        dragItem.current = { itemId, lineId };
    }, []);

    const handleDragEnter = useCallback((itemId, lineId) => {
        dragOver.current = { itemId, lineId };
    }, []);

    const handleDragEnd = useCallback(() => {
        if (!dragItem.current || !dragOver.current) {
            dragItem.current = null;
            dragOver.current = null;
            return;
        }
        if (dragItem.current.itemId !== dragOver.current.itemId) {
            dragItem.current = null;
            dragOver.current = null;
            return;
        }

        const itemId = dragItem.current.itemId;
        const list = batchOrders[itemId] ?? [];
        const fromIdx = list.findIndex((o) => o.id === dragItem.current.lineId);
        const toIdx = list.findIndex((o) => o.id === dragOver.current.lineId);

        if (fromIdx >= 0 && toIdx >= 0 && fromIdx !== toIdx) {
            const reordered = [...list];
            const [moved] = reordered.splice(fromIdx, 1);
            reordered.splice(toIdx, 0, moved);
            const renumbered = reordered.map((o, idx) => ({ ...o, order: idx + 1 }));
            setBatchOrders((prev) => ({ ...prev, [itemId]: renumbered }));
        }

        dragItem.current = null;
        dragOver.current = null;
    }, [batchOrders]);

    // ── Order helpers (sequential mode) ─────────────────────────────────

    const orderFor = (itemId, lineId) =>
        batchOrders[itemId]?.find((o) => o.id === lineId)?.order ?? '';

    const setOrder = (itemId, lineId, val) => {
        const list = batchOrders[itemId] ?? [];
        const existing = list.find((o) => o.id === lineId);
        let nextList;
        if (existing) {
            nextList = list.map((o) => (o.id === lineId ? { ...o, order: val } : o));
        } else {
            nextList = [...list, { id: lineId, order: val }];
        }
        setBatchOrders((prev) => ({ ...prev, [itemId]: nextList }));
    };

    const normalisedOrders = () => {
        const out = {};
        Object.entries(batchOrders || {}).forEach(([itemId, orders]) => {
            const seq = [...orders]
                .filter((o) => o.order)
                .sort((a, b) => a.order - b.order)
                .map((o) => o.id);
            if (seq.length) out[itemId] = seq;
        });
        return out;
    };

    // ── Reset / init ────────────────────────────────────────────────────

    /** Reset a single ingredient's config (clears orders or amounts based on current mode) */
    const resetIngredient = (itemId) => {
        const mode = modeFor(itemId);
        if (mode === 'sequential') {
            setBatchOrders((prev) => ({ ...prev, [itemId]: [] }));
        } else {
            setDistributedAmounts((prev) => ({ ...prev, [itemId]: {} }));
        }
    };

    /** Initialise batch config for a newly selected product (FIFO + sequential defaults) */
    const initForProduct = (p) => {
        const next = {};
        if (p?.ingredients?.length) {
            p.ingredients.forEach((ing) => {
                const lines = [...(ing.item?.restock_batch_items ?? [])].sort(sortByTimeline);
                next[ing.item_id] = lines.map((line, idx) => ({ id: line.id, order: idx + 1 }));
            });
        }
        setBatchOrders(next);

        const modes = {};
        const dists = {};
        (p.ingredients ?? []).forEach((ing) => {
            modes[ing.item_id] = 'sequential';
            dists[ing.item_id] = {};
        });
        setBatchModes(modes);
        setDistributedAmounts(dists);
    };

    /** Clear all batch config state */
    const resetAll = () => {
        setBatchOrders({});
        setBatchModes({});
        setDistributedAmounts({});
    };

    // ── Public API ──────────────────────────────────────────────────────

    return {
        batchConfigData,
        distributedAmounts,
        modeFor,
        setMode,
        autoAssignIngredient,
        autoDistributeEvenly,
        primeBatchOrders,
        setDistAmount,
        handleDragStart,
        handleDragEnter,
        handleDragEnd,
        orderFor,
        setOrder,
        normalisedOrders,
        resetIngredient,
        initForProduct,
        resetAll,
    };
}
