<script setup lang="ts">
import CustomerCart from '@/components/CustomerCart.vue';
import CustomerInvoice from '@/components/CustomerInvoice.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import CustomerLayout from '@/layouts/customerLayout.vue';
import { useCartStore } from '@/stores/cartStore';
import { Head, usePage } from '@inertiajs/vue3';
import { AlertCircle, CheckCircle2, ShoppingCart, X, ZoomIn } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

// Initialize cart store
const cartStore = useCartStore();

// Get page props for flash messages and debug data
const page = usePage();
const flash = computed(() => (page.props as any).flash as any);

// Debug restaurant data
console.log('Full restaurant object:', page.props.restaurant);

// Show/hide flash message
const showFlash = ref(false);
const flashTimeout = ref<number | null>(null);

// Watch for flash messages and handle them
watch(
    () => flash.value,
    (newFlash) => {
        if (newFlash.success || newFlash.error) {
            showFlash.value = true;

            // Auto-hide flash message after 5 seconds
            if (flashTimeout.value) {
                clearTimeout(flashTimeout.value);
            }

            flashTimeout.value = window.setTimeout(() => {
                showFlash.value = false;
            }, 5000);
        }

        // If we have a new order created, make sure to refresh the order history tab
        if (newFlash.flash_order_created && props.activeOrder) {
            // Update the cart store with the latest active order
            cartStore.setActiveOrder(props.activeOrder);

            // Switch to the history tab to show the new order
            if (cartStore.showCart) {
                setTimeout(() => {
                    const historyTabButton = document.querySelector('[data-tab="history"]');
                    if (historyTabButton) {
                        (historyTabButton as HTMLElement).click();
                    }
                }, 300);
            }
        }
    },
    { immediate: true },
);

// Clear flash timeout on component unmount
onMounted(() => {
    if (flash.value.success || flash.value.error) {
        showFlash.value = true;

        flashTimeout.value = window.setTimeout(() => {
            showFlash.value = false;
        }, 5000);
    }
});

interface Props {
    restaurant: {
        id: number;
        name: string;
        description?: string;
        payBefore: boolean;
        promptPayId?: string;
    };
    table: {
        number: number;
        code: string;
    };
    menuItems: MenuItem[];
    categories: string[];
    activeOrder: any | null;
    orderHistory: any[];
}

const props = defineProps<Props>();

interface MenuItem {
    id: number;
    name: string;
    description: string | null;
    price: number;
    category: string;
    image_path?: string;
    options?: MenuItemOption[];
    is_available: boolean;
}

interface MenuItemOption {
    name: string;
    required?: boolean;
    multiple?: boolean;
    choices?: {
        name: string;
        price?: number;
    }[];
    values?: {
        name: string;
        price?: number;
    }[];
}

// Local state
const activeCategory = ref(props.categories[0] || '');

// Set active order and order history in cart store
if (props.activeOrder) {
    cartStore.setActiveOrder(props.activeOrder);
}

if (props.orderHistory && Array.isArray(props.orderHistory)) {
    cartStore.setOrderHistory(props.orderHistory);
}

// Watch for changes to activeOrder prop and update cart store
watch(
    () => props.activeOrder,
    (newActiveOrder) => {
        if (newActiveOrder) {
            cartStore.setActiveOrder(newActiveOrder);
        }
    },
    { deep: true },
);

// Helper function to check if an item has valid options with values
const hasValidOptions = (item: MenuItem): boolean => {
    if (!item.options || !Array.isArray(item.options) || item.options.length === 0) {
        return false;
    }

    // Check if any option has values (database format) or choices (our test format)
    const hasValidOptionValues = item.options.some((option) => {
        // Check if option is a valid object with a name
        if (!option || typeof option !== 'object' || !option.name) {
            return false;
        }

        // Check for values array (database format)
        if (option.values && Array.isArray(option.values) && option.values.length > 0) {
            return true;
        }

        // Check for choices array (our test format)
        if (option.choices && Array.isArray(option.choices) && option.choices.length > 0) {
            return true;
        }

        return false;
    });

    return hasValidOptionValues;
};

// Computed properties
const menuItemsByCategory = computed(() => {
    const result: Record<string, MenuItem[]> = {};

    // Group menu items by category
    props.categories.forEach((category: string) => {
        result[category] = props.menuItems
            .filter((item: MenuItem) => item.category === category)
            .map((item: MenuItem) => {
                // Return the item as is, without adding test options
                return item;
            });
    });

    return result;
});

// Determine if the table's QR code is in billing status (block menu and force invoice)
const qrStatus = computed(() => {
    // prefer explicit page prop, then props.table.qr_code if present
    // use optional chaining defensively
    // @ts-ignore - page.props is dynamic
    return (page.props as any).qr_code?.status ?? (props.table as any)?.qr_code?.status ?? (page.props as any).table?.qr_code?.status ?? null;
});

// Treat both 'billing' and 'checked' QR statuses as billing states
const isBilling = computed(() => {
    const status = qrStatus.value ?? '';
    return ['billing', 'checked'].includes(status);
});

// Methods
const openOptionModal = (item: MenuItem) => {
    if (isBilling.value) return;
    // Check if the item has valid options with choices or values
    if (!hasValidOptions(item)) {
        cartStore.addToCart(item as any);
        return;
    }

    // Open the modal for items with valid options
    cartStore.openOptionModal(item as any);
};

const addToCart = (item: MenuItem) => {
    if (isBilling.value) return;
    // Check if the item has valid options with choices
    if (hasValidOptions(item)) {
        openOptionModal(item);
        return;
    }

    cartStore.addToCart(item as any);
};

const formatPrice = (price: number) => {
    return new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB' }).format(price);
};

// Color gradients to use for cards. We keep a short palette and pick by index so cards are colorful.
const gradients = [
    'from-yellow-400 to-pink-400',
    'from-orange-400 to-rose-400',
    'from-emerald-300 to-teal-400',
    'from-indigo-400 to-sky-400',
    'from-rose-200 to-orange-300',
    'from-amber-300 to-yellow-400',
    'from-fuchsia-400 to-purple-400',
    'from-lime-300 to-emerald-400',
];

const gradientFor = (categoryIndex: number, itemIndex: number): string => {
    const idx = (categoryIndex * 10 + itemIndex) % gradients.length;
    return `bg-gradient-to-br ${gradients[idx]} text-white`;
};

// Image modal state
const showImageModal = ref(false);
const modalImageSrc = ref<string | null>(null);

const openImageModal = (src: string | undefined) => {
    if (!src) return;
    modalImageSrc.value = src;
    showImageModal.value = true;
    // prevent background scroll
    document.body.style.overflow = 'hidden';
};

const closeImageModal = () => {
    showImageModal.value = false;
    modalImageSrc.value = null;
    document.body.style.overflow = '';
};

// ensure cleanup
onBeforeUnmount(() => {
    document.body.style.overflow = '';
});

const scrollToCategory = (category: string) => {
    activeCategory.value = category;
    const element = document.getElementById(`category-${category}`);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
    }
};
</script>

<template>
    <CustomerLayout>
        <Head :title="`${restaurant.name} - Table ${table.number}`" />

        <!-- Flash Messages -->
        <div v-if="showFlash" class="fixed top-4 right-4 left-4 z-50">
            <Alert v-if="flash.success" class="mb-2 border-green-200 bg-green-50">
                <CheckCircle2 class="h-4 w-4 text-green-500" />
                <AlertTitle class="text-green-800">Success</AlertTitle>
                <AlertDescription class="text-green-700">{{ flash.success }}</AlertDescription>
            </Alert>

            <Alert v-if="flash.error" class="border-red-200 bg-red-50">
                <AlertCircle class="h-4 w-4 text-red-500" />
                <AlertTitle class="text-red-800">Error</AlertTitle>
                <AlertDescription class="text-red-700">{{ flash.error }}</AlertDescription>
            </Alert>
        </div>

        <!-- Invoice View (when bill is requested or table is in billing state) -->
        <div v-if="cartStore.shouldShowInvoice || isBilling" class="flex h-full flex-1 flex-col p-4">
            <CustomerInvoice
                :restaurant="restaurant"
                :table="table"
                :active-order="cartStore.activeOrder"
                :show-payment-qr="cartStore.shouldShowPaymentQR || isBilling"
            />
        </div>

        <!-- Menu View (when order is active) - hidden when table is in billing state -->
        <div v-if="cartStore.shouldShowMenu && !isBilling" class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4 pb-24 md:pb-4">
            <!-- Restaurant Header -->
            <div class="py-4 text-center">
                <h1 class="text-2xl font-bold">{{ restaurant.name }}</h1>
                <p v-if="restaurant.description" class="mt-1 text-muted-foreground">{{ restaurant.description }}</p>
                <p class="mt-2 text-sm">Table {{ table.number }}</p>
            </div>

            <!-- Category Navigation -->
            <div class="sticky top-0 z-0 flex gap-2 overflow-x-auto bg-white pb-2">
                <Button
                    v-for="category in categories"
                    :key="category"
                    :variant="activeCategory === category ? 'default' : 'outline'"
                    size="sm"
                    @click="scrollToCategory(category)"
                    class="whitespace-nowrap"
                >
                    {{ category }}
                </Button>
            </div>

            <!-- Menu Items by Category -->
            <div v-for="(category, cidx) in categories" :key="category" :id="`category-${category}`" class="mb-8">
                <h2 class="mb-4 text-xl font-semibold">{{ category }}</h2>

                <div class="grid grid-cols-2 gap-2 md:grid-cols-3 lg:grid-cols-4">
                    <Card
                        v-for="(item, idx) in menuItemsByCategory[category]"
                        :key="item.id"
                        :class="[gradientFor(cidx, idx), 'aspect-square', 'rounded-lg', 'overflow-hidden', 'p-0']"
                    >
                        <div class="flex h-full flex-col">
                            <!-- Image area fills most of the card; overlay text on top -->
                            <div class="relative w-full flex-1 overflow-hidden">
                                <img
                                    v-if="item.image_path"
                                    :src="item.image_path"
                                    :alt="item.name"
                                    class="absolute inset-0 h-full w-full object-cover"
                                    @error="() => console.error('Image failed to load:', item.image_path)"
                                />

                                <!-- overlay gradient + content -->
                                <div class="absolute inset-0 flex flex-col justify-end bg-gradient-to-t from-black/60 to-transparent p-3 text-white">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h3 class="text-sm leading-tight font-medium sm:text-base">{{ item.name }}</h3>
                                            <p v-if="item.description" class="mt-1 line-clamp-2 text-xs text-white/90 sm:text-sm">
                                                {{ item.description }}
                                            </p>
                                        </div>
                                        <div v-if="hasValidOptions(item)" class="ml-2">
                                            <span class="rounded-full bg-white/20 px-2 py-0.5 text-xs">Customizable</span>
                                        </div>
                                    </div>

                                    <div class="mt-2 text-right">
                                        <span class="text-sm font-semibold">{{ formatPrice(item.price) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Cart + Zoom buttons below image -->
                            <div class="flex justify-center gap-2 p-2">
                                <Button
                                    size="sm"
                                    variant="default"
                                    @click="addToCart(item)"
                                    class="max-w-xs flex-1 border-white/10 bg-white/10 px-3 py-1 text-sm text-white hover:bg-white/20"
                                    :title="isBilling ? 'Ordering disabled while billing' : hasValidOptions(item) ? 'Select options' : 'Add to cart'"
                                    :disabled="isBilling"
                                >
                                    <ShoppingCart class="mr-2 h-4 w-4" />
                                    <span>Add</span>
                                </Button>

                                <button
                                    v-if="item.image_path"
                                    @click.stop="openImageModal(item.image_path)"
                                    class="inline-flex items-center justify-center rounded-md border border-white/10 bg-white/5 px-3 py-1 text-sm text-white hover:bg-white/10"
                                    :title="`Enlarge ${item.name}`"
                                >
                                    <ZoomIn class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </Card>
                </div>
            </div>

            <!-- Request Bill button moved into the floating cart control (see CustomerCart.vue) -->
        </div>

        <!-- Cart Component (only show when menu is active) -->
        <CustomerCart
            v-if="cartStore.shouldShowMenu && !isBilling"
            :restaurant-id="props.restaurant.id"
            :table-code="props.table.code"
            :pay-before="props.restaurant.payBefore"
            :prompt-pay-id="props.restaurant.promptPayId"
            :active-order="props.activeOrder"
            :order-history="props.orderHistory"
        />
    </CustomerLayout>

    <!-- Image modal -->
    <div v-if="showImageModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60" @click="closeImageModal"></div>
        <div class="relative z-10 flex max-h-full max-w-full flex-col items-center">
            <!-- clearer close button placed above the image -->
            <div class="mb-3 flex w-full justify-end">
                <button
                    @click="closeImageModal"
                    aria-label="Close image"
                    class="inline-flex items-center justify-center rounded-full bg-white p-2 text-black shadow-md hover:bg-gray-100"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <div class="rounded-md bg-transparent">
                <img v-if="modalImageSrc" :src="modalImageSrc" class="max-h-[86vh] max-w-[96vw] rounded-md object-contain shadow-lg" />
            </div>
        </div>
    </div>

    <!-- Option Selection Modal is now handled by the cart store and CustomerCart component -->
</template>

<style scoped>
/* Add any custom styles here */
</style>
