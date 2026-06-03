import { computed, ref } from 'vue'

/**
 * Multi-select state for a paginated table. Tracks selected row ids for the
 * rows currently on screen (per-page selection).
 *
 *   const sel = useTableSelect(() => page.value.data)
 *   <input type="checkbox" :checked="sel.isSelected(row.id)" @change="sel.toggle(row.id)">
 */
export function useTableSelect(rowsGetter) {
    const selected = ref([])

    const rows = computed(() => {
        const r = typeof rowsGetter === 'function' ? rowsGetter() : (rowsGetter?.value ?? rowsGetter)
        return Array.isArray(r) ? r : []
    })
    const ids = computed(() => rows.value.map(r => r.id))

    const count = computed(() => selected.value.length)
    const isSelected = (id) => selected.value.includes(id)
    const allSelected = computed(() => ids.value.length > 0 && ids.value.every(id => selected.value.includes(id)))
    const someSelected = computed(() => count.value > 0 && !allSelected.value)

    function toggle(id) {
        const i = selected.value.indexOf(id)
        if (i === -1) selected.value.push(id)
        else selected.value.splice(i, 1)
    }
    function toggleAll() {
        selected.value = allSelected.value ? [] : [...ids.value]
    }
    function clear() { selected.value = [] }

    return { selected, count, isSelected, allSelected, someSelected, toggle, toggleAll, clear }
}
