# New JSX Page Pattern

Reference: #file:resources/js/Pages/Items/Index.jsx  
Reference: #file:resources/js/theme.js  
Reference: #file:resources/js/app.jsx

## Rules

- File lives at `resources/js/Pages/<Section>/Index.jsx`.
- `AppLayout` is auto-applied via `resolve()` in `app.jsx` — do **not** import or wrap with it.
- No in-component data fetching. All data arrives as Inertia props passed from the controller.
- Use `useForm` from `@inertiajs/react` for all mutations (POST/PUT/DELETE).
- Use `router.delete()` / `router.get()` for programmatic navigation.
- Use `<Link>` from `@inertiajs/react` for nav links (not `<a>`).
- Use `ui.*` tokens from `@/theme` instead of raw Tailwind strings.

## CRUD pane toggle pattern

```jsx
const [mode, setMode] = useState(null); // null | 'add' | recordId

const openAdd  = () => { setMode('add'); form.reset(); };
const openEdit = (record) => { setMode(record.id); form.setData({...}); };
const closeForm = () => { setMode(null); form.reset(); };

// In JSX:
{mode === 'add' && <AddForm form={form} onClose={closeForm} />}
{typeof mode === 'number' && <EditForm form={form} onClose={closeForm} />}
```

No sub-routes — toggle via `mode` state only.

## useForm mutation pattern

```jsx
const form = useForm({
    name: '',
    unit_id: '',
    journal_date: '',
});

const handleSubmit = (e) => {
    e.preventDefault();
    form.post('/section', { onSuccess: closeForm });
};

const handleUpdate = (e) => {
    e.preventDefault();
    form.put(`/section/${record.id}`, { onSuccess: closeForm });
};

const handleDelete = (id) => {
    router.delete(`/section/${id}`);
};
```

## Date propagation

```jsx
import { usePage } from '@inertiajs/react';

const { props } = usePage();
// Pre-fill journal_date from the shared currentDate prop
form.setData('journal_date', props.currentDate);
```

The `currentDate` prop is injected by `HandleInertiaRequests` middleware — always use it for `journal_date` defaults.

## Minimal page scaffold

```jsx
import React, { useState } from 'react';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import { ui } from '@/theme';

const SectionIndex = ({ items }) => {
    const { props } = usePage();
    const [mode, setMode] = useState(null);

    const form = useForm({
        name: '',
        journal_date: '',
    });

    const openAdd = () => {
        form.reset();
        form.setData('journal_date', props.currentDate);
        setMode('add');
    };

    const closeForm = () => setMode(null);

    const handleSubmit = (e) => {
        e.preventDefault();
        form.post('/section', { onSuccess: closeForm });
    };

    return (
        <>
            <Head title="Section" />
            <div className={ui.card}>
                <button onClick={openAdd} className={ui.button.primary}>Add</button>

                {mode === 'add' && (
                    <form onSubmit={handleSubmit}>
                        <input
                            className={ui.input}
                            value={form.data.name}
                            onChange={e => form.setData('name', e.target.value)}
                        />
                        {form.errors.name && <p>{form.errors.name}</p>}
                        <button type="submit" disabled={form.processing}>Save</button>
                        <button type="button" onClick={closeForm}>Cancel</button>
                    </form>
                )}

                {items.map(item => (
                    <div key={item.id}>{item.name}</div>
                ))}
            </div>
        </>
    );
};

export default SectionIndex;
```

## ui token reference

```js
// from resources/js/theme.js
ui.card              // card container
ui.button.primary    // primary action button
ui.button.secondary  // secondary/cancel button
ui.button.danger     // destructive action
ui.input             // text input
ui.label             // form label
ui.badge.*           // status badges
```

Always compose from `ui.*` — never write ad-hoc Tailwind class strings.
