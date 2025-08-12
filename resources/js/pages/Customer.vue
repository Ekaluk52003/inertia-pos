<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import CustomerCart from '@/components/CustomerCart.vue';
import CustomerLayout from '@/layouts/customerLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { useCartStore } from '@/stores/cartStore';
import { ref, computed, watch, onMounted } from 'vue';
import { Plus, CheckCircle2, AlertCircle } from 'lucide-vue-next';

// Initialize cart store
const cartStore = useCartStore();

// Get page props for flash messages
const page = usePage();
const flash = computed(() => page.props.flash);

// Show/hide flash message
const showFlash = ref(false);
const flashTimeout = ref<number | null>(null);

// Watch for flash messages and handle them
watch(() => flash.value, (newFlash) => {
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
}, { immediate: true });

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
    description?: string;
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

// Methods
const openOptionModal = (item: MenuItem) => {
    // Check if the item has valid options with choices or values
    if (!hasValidOptions(item)) {
        cartStore.addToCart(item);
        return;
    }

    // Open the modal for items with valid options
    cartStore.openOptionModal(item);
};

const addToCart = (item: MenuItem) => {
    // Check if the item has valid options with choices
    if (hasValidOptions(item)) {
        openOptionModal(item);
        return;
    }

    cartStore.addToCart(item);
};

const formatPrice = (price: number) => {
    return new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB' }).format(price);
};

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
        <div v-if="showFlash" class="fixed top-4 left-4 right-4 z-50">
            <Alert v-if="flash.success" class="bg-green-50 border-green-200 mb-2">
                <CheckCircle2 class="h-4 w-4 text-green-500" />
                <AlertTitle class="text-green-800">Success</AlertTitle>
                <AlertDescription class="text-green-700">{{ flash.success }}</AlertDescription>
            </Alert>
            
            <Alert v-if="flash.error" class="bg-red-50 border-red-200">
                <AlertCircle class="h-4 w-4 text-red-500" />
                <AlertTitle class="text-red-800">Error</AlertTitle>
                <AlertDescription class="text-red-700">{{ flash.error }}</AlertDescription>
            </Alert>
        </div>

        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4 pb-24 md:pb-4">
            <!-- Restaurant Header -->
            <div class="py-4 text-center">
                <h1 class="text-2xl font-bold">{{ restaurant.name }}</h1>
                <p v-if="restaurant.description" class="mt-1 text-muted-foreground">{{ restaurant.description }}</p>
                <p class="mt-2 text-sm">Table {{ table.number }}</p>
            </div>

            <!-- Category Navigation -->
            <div class="sticky top-0 z-10 flex gap-2 overflow-x-auto bg-white pb-2">
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
            <div v-for="category in categories" :key="category" :id="`category-${category}`" class="mb-8">
                <h2 class="mb-4 text-xl font-semibold">{{ category }}</h2>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <Card v-for="item in menuItemsByCategory[category]" :key="item.id" class="overflow-hidden">
                        <div class="flex">
                            <div v-if="item.image_path" class="h-24 w-24 flex-shrink-0 bg-gray-100">
                                <img :src="item.image_path" :alt="item.name" class="h-full w-full object-cover" />
                            </div>

                            <div class="flex-1 p-4">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <h3 class="font-medium">{{ item.name }}</h3>
                                        <p v-if="item.description" class="mt-1 text-sm text-muted-foreground">{{ item.description }}</p>
                                        <!-- Options indicator -->
                                        <div v-if="hasValidOptions(item)" class="mt-1 flex items-center">
                                            <span class="rounded-full bg-yellow-100 px-2 py-0.5 text-xs text-yellow-800">Customizable</span>
                                        </div>
                                    </div>
                                    <Button
                                        size="icon"
                                        variant="outline"
                                        @click="addToCart(item)"
                                        class="h-8 w-8 flex-shrink-0"
                                        :title="hasValidOptions(item) ? 'Select options' : 'Add to cart'"
                                    >
                                        <Plus class="h-4 w-4" />
                                    </Button>
                                </div>
                                <p class="mt-2 font-medium text-yellow-500">{{ formatPrice(item.price) }}</p>
                            </div>
                        </div>
                    </Card>
                </div>
            </div>
        </div>

        <!-- Cart Component -->
        <CustomerCart 
            :restaurant-id="props.restaurant.id" 
            :table-code="props.table.code" 
            :pay-before="props.restaurant.payBefore"
            :active-order="props.activeOrder"
            :order-history="props.orderHistory"
        />
    </CustomerLayout>

    <!-- Option Selection Modal is now handled by the cart store and CustomerCart component -->
</template>

<style scoped>
/* Add any custom styles here */
</style>
