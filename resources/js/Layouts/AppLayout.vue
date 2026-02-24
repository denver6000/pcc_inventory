<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const url = computed(() => page.url);
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <nav class="bg-white border-b border-gray-200">
            <div class="max-w-5xl mx-auto px-6 h-12 flex items-center gap-6">
                <span class="text-sm font-semibold tracking-tight">PCC Inventory</span>
                <div class="flex items-center gap-1 ml-2">
                    <Link
                        href="/pos"
                        class="text-sm px-3 py-1 transition-colors"
                        :class="url.startsWith('/pos') ? 'text-black font-medium' : 'text-gray-400 hover:text-black'"
                    >
                        POS
                    </Link>
                    <Link
                        href="/"
                        class="text-sm px-3 py-1 transition-colors"
                        :class="url === '/' ? 'text-black font-medium' : 'text-gray-400 hover:text-black'"
                    >
                        Items
                    </Link>
                    <Link
                        href="/products"
                        class="text-sm px-3 py-1 transition-colors"
                        :class="url.startsWith('/products') ? 'text-black font-medium' : 'text-gray-400 hover:text-black'"
                    >
                        Products
                    </Link>
                    <Link
                        href="/units"
                        class="text-sm px-3 py-1 transition-colors"
                        :class="url.startsWith('/units') ? 'text-black font-medium' : 'text-gray-400 hover:text-black'"
                    >
                        Units
                    </Link>
                </div>
            </div>
        </nav>
        <main class="max-w-5xl mx-auto px-6 py-8">
            <slot />
        </main>
    </div>
</template>
