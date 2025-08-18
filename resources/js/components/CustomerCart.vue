<script setup lang="ts">
import PromptPayQRCode from '@/components/PromptPayQRCode.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { useCartStore } from '@/stores/cartStore';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, Clock, Minus, Plus, ShoppingCart, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const page = usePage();
const processing = computed(() => page.props.processing);
const errors = computed(() => page.props.errors || {});

// Define interfaces for type safety
interface OrderItem {
    id: number;
    name: string;
    price: number;
    quantity: number;
    status: 'pending' | 'cooking' | 'ready' | 'served';
    special_instructions?: string | null;
    options?: any[] | null;
    created_at: string;
}

interface Order {
    id: number;
    code: string;
    table_number: string;
    total_amount: number;
    is_paid: boolean;
    items: OrderItem[];
    created_at: string;
}

// Props
interface Props {
    restaurantId: number;
    tableCode: string;
    payBefore: boolean;
    promptPayId?: string;
    activeOrder?: Order;
    orderHistory?: Order[];
}

const props = defineProps<Props>();

// Use the cart store
const cartStore = useCartStore();

// Tab state
const activeTab = ref('cart'); // 'cart' or 'history'

// State to track if payment QR code should be shown
const showPaymentQR = ref(false);
// Quantity selected in the option modal
const modalQuantity = ref(1);

// Payment slip (base64) when pay-before is enabled
const slipImageData = ref<string | null>(null);
const slipFileName = ref<string>('');

const handleSlipUpload = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (!target.files || target.files.length === 0) return;
    const file = target.files[0];

    // Basic size guard (e.g., 5MB)
    const maxBytes = 5 * 1024 * 1024;
    if (file.size > maxBytes) {
        alert('File too large. Maximum size is 5MB.');
        return;
    }

    const reader = new FileReader();
    reader.onload = () => {
        const result = reader.result as string;
        // Keep full data URL so backend can detect image (data:image/...)
        slipImageData.value = result;
        slipFileName.value = file.name;
    };
    reader.readAsDataURL(file);
};

const removeSlip = () => {
    slipImageData.value = null;
    slipFileName.value = '';
};

// Calculate total price for an item. Prefer the persisted `item.price` (it already includes option extras).
// Fall back to computing from base price + options only when `item.price` is not present.
const calculateItemTotal = (item: any): number => {
    const qty = Number(item.quantity || 1);

    // If the item has a persisted price, use it directly (unit price already includes option extras).
    if (item && item.price !== undefined && item.price !== null) {
        return Number(item.price) * qty;
    }

    // Fallback: compute unit price from base price + option additional_price
    let unit = Number(item.price ?? item.base_price ?? 0);
    if (item.options && Array.isArray(item.options)) {
        item.options.forEach((option: any) => {
            if (option.additional_price) {
                unit += Number(option.additional_price || 0);
            }
        });
    }

    return unit * qty;
};

// Calculate total price for an entire order including all items and their options
const calculateOrderTotal = (order: any): number => {
    if (!order || !order.items || !Array.isArray(order.items)) {
        return Number(order?.total_amount || 0);
    }

    return order.items.reduce((total: number, item: any) => {
        return total + calculateItemTotal(item);
    }, 0);
};

// Compute unit price for a cart item including selected options
const cartItemUnitPrice = (cartItem: any): number => {
    const base = Number(cartItem?.item?.price || 0);
    let extras = 0;
    if (cartItem && Array.isArray(cartItem.selectedOptions)) {
        cartItem.selectedOptions.forEach((opt: any) => {
            extras += Number(opt.additional_price || 0);
        });
    }
    return base + extras;
};

// Compute total price for a cart item (unit price * quantity)
const cartItemTotal = (cartItem: any): number => {
    const qty = Number(cartItem?.quantity || 1);
    return cartItemUnitPrice(cartItem) * qty;
};

// Determine the menu/base unit price to display for an order item (prefer menuItem.price or base_price).
const unitPriceForOrderItem = (item: any, cartItem: any = null): number => {
    // If backend attached menuItem with base price
    if (item && item.menuItem && item.menuItem.price !== undefined) {
        return Number(item.menuItem.price);
    }

    // If a base_price field exists on the order item
    if (item && item.base_price !== undefined) {
        return Number(item.base_price);
    }

    // If order item price includes options, try to subtract option extras to get base price
    if (item && item.price !== undefined && item.options && Array.isArray(item.options) && item.options.length > 0) {
        const extras = item.options.reduce((s: number, o: any) => s + Number(o.additional_price || 0), 0);
        return Math.max(0, Number(item.price) - extras);
    }

    // Fallback to cart item's menu price if provided
    if (cartItem && cartItem.item && cartItem.item.price !== undefined) {
        return Number(cartItem.item.price);
    }

    // Last resort: use the persisted item.price
    return Number(item?.price ?? 0);
};

// Set active order if provided in props
if (props.activeOrder) {
    cartStore.setActiveOrder(props.activeOrder);

    // If there are active order items, make sure the cart button shows the count
    if (props.activeOrder.items && props.activeOrder.items.length > 0) {
        // We don't automatically show the cart here to avoid disrupting user experience
        // User will click the cart button to see their orders
    }
}

// Set order history if provided in props
if (props.orderHistory && Array.isArray(props.orderHistory)) {
    cartStore.setOrderHistory(props.orderHistory);
}

// Method to submit the order
const submitOrder = () => {
    console.log('promptPayId received:', props.promptPayId);

    // If payment is required before ordering and QR code isn't shown yet, show it
    if (props.payBefore && !showPaymentQR.value) {
        // Check if the restaurant has a valid promptPayId configured
        if (!props.promptPayId || props.promptPayId.trim() === '') {
            alert(
                'This restaurant requires payment before ordering, but has not configured a valid payment method. Please contact the restaurant staff.',
            );
            return;
        }

        showPaymentQR.value = true;
        return;
    }

    // Prepare order items
    const items = cartStore.prepareOrderItems();

    // Submit the form using named route
    const payload: any = {
        items,
        customer_notes: '',
    };
    if (props.payBefore) {
        // Only send slip_image if provided (validation requires one of slip_image/qr_code_data)
        if (slipImageData.value) payload.slip_image = slipImageData.value;
    }

    useForm(payload).post(
        route('public.order.store', {
            restaurantCode: props.restaurantId,
            tableCode: props.tableCode,
        }),
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                // Clear the cart after successful order
                cartStore.clearCart();

                // Reset payment QR code state
                showPaymentQR.value = false;
                removeSlip();

                router.reload({ only: ['orderHistory'] });

                // Make sure orderHistory exists and is an array before setting it
                if (props.orderHistory && Array.isArray(props.orderHistory)) {
                    cartStore.setOrderHistory(props.orderHistory);
                }

                setTimeout(() => {
                    const historyTabButton = document.querySelector('[data-tab="history"]');
                    if (historyTabButton) {
                        (historyTabButton as HTMLElement).click();
                    }
                }, 300);
            },

            onError: (errors: any) => {
                console.error('Order submission errors:', errors);
            },
        },
    );
};
</script>

<template>
    <div class="fixed right-4 bottom-4 left-4 md:left-auto">
        <Button
            @click="cartStore.toggleCart"
            class="w-full md:w-auto"
            :variant="cartStore.totalItemCount > 0 ? 'default' : 'outline'"
            :class="{ 'animate-pulse': cartStore.hasActiveOrderItems && !cartStore.showCart }"
        >
            <ShoppingCart class="mr-2 h-4 w-4" />
            <span v-if="cartStore.totalItemCount > 0">
                {{ cartStore.totalItemCount }} {{ cartStore.totalItemCount === 1 ? 'item' : 'items' }}
                <template v-if="cartStore.cartTotal > 0"> · {{ cartStore.formatPrice(cartStore.cartTotal) }}</template>
                <template v-if="cartStore.hasActiveOrderItems">
                    <span class="ml-1 inline-flex h-2 w-2 rounded-full bg-green-500"></span>
                </template>
            </span>
            <span v-else>Cart (0)</span>
            <ChevronUp v-if="cartStore.showCart" class="ml-2 h-4 w-4" />
            <ChevronDown v-else class="ml-2 h-4 w-4" />
        </Button>

        <!-- Cart Panel -->
        <Card
            v-if="cartStore.showCart"
            class="absolute right-0 bottom-full left-0 z-30 mt-2 mb-2 max-h-[70vh] overflow-y-auto md:left-auto md:w-[400px]"
        >
            <CardHeader>
                <CardTitle>Your Order</CardTitle>
                <CardDescription>Table {{ props.tableCode }}</CardDescription>

                <!-- Tabs for Cart and Order History -->
                <div class="mt-4 border-b">
                    <div class="flex">
                        <button
                            @click="activeTab = 'cart'"
                            data-tab="cart"
                            class="-mb-px px-4 py-2 text-sm font-medium"
                            :class="{
                                'border-b-2 border-yellow-500 text-yellow-600': activeTab === 'cart',
                                'text-gray-500 hover:text-gray-700': activeTab !== 'cart',
                            }"
                        >
                            <div class="flex items-center">
                                <ShoppingCart class="mr-2 h-4 w-4" />
                                Cart
                                <span v-if="cartStore.cart.length > 0" class="ml-2 rounded-full bg-yellow-100 px-2 py-0.5 text-xs text-yellow-800">
                                    {{ cartStore.cart.length }}
                                </span>
                            </div>
                        </button>
                        <button
                            @click="activeTab = 'history'"
                            data-tab="history"
                            class="-mb-px px-4 py-2 text-sm font-medium"
                            :class="{
                                'border-b-2 border-yellow-500 text-yellow-600': activeTab === 'history',
                                'text-gray-500 hover:text-gray-700': activeTab !== 'history',
                            }"
                        >
                            <div class="flex items-center">
                                <Clock class="mr-2 h-4 w-4" />
                                Order History
                            </div>
                        </button>
                    </div>
                </div>
            </CardHeader>

            <CardContent>
                <!-- Cart Tab Content -->
                <div v-if="activeTab === 'cart'">
                    <!-- Empty Cart Message -->
                    <div v-if="cartStore.cart.length === 0" class="py-8 text-center">
                        <p class="text-muted-foreground">Your cart is empty</p>
                    </div>

                    <!-- Cart Items Section -->
                    <div v-if="cartStore.cart.length > 0" class="rounded-lg bg-yellow-50 p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="font-medium text-gray-800">New Items</h3>
                            <span class="rounded-full bg-yellow-100 px-2 py-1 text-xs text-yellow-800">Not ordered yet</span>
                        </div>

                        <div class="space-y-4">
                            <div
                                v-for="(cartItem, index) in cartStore.cart"
                                :key="index"
                                class="flex items-stretch justify-between border-b border-yellow-200 pb-4"
                            >
                                <div>
                                    <div>
                                        <h4 class="font-medium text-gray-900">{{ cartItem.item.name }}</h4>
                                        <div class="mt-1 flex items-center gap-3 text-sm text-gray-700">
                                            <span>{{ cartItem.quantity }}x</span>
                                            <span class="text-sm text-muted-foreground">{{ cartStore.formatPrice(cartItem.item.price) }}</span>
                                        </div>
                                    </div>
                                    <!-- Display selected options -->
                                    <div v-if="cartItem.selectedOptions.length > 0" class="mt-1">
                                        <div
                                            v-for="(option, optIdx) in cartItem.selectedOptions"
                                            :key="optIdx"
                                            class="flex items-center gap-2 text-xs text-gray-600"
                                        >
                                            <span class="font-medium">{{ option.option_name }}:</span>
                                            <span class="text-sm text-gray-700 italic">{{ option.choices.join(', ') }}</span>
                                            <span v-if="option.additional_price > 0" class="text-xs text-muted-foreground"
                                                >(+{{ cartStore.formatPrice(option.additional_price) }})</span
                                            >
                                        </div>
                                    </div>
                                    <!-- Display notes if any -->
                                    <p v-if="cartItem.notes" class="mt-1 text-xs italic">"{{ cartItem.notes }}"</p>
                                    <div class="mt-2 flex items-center">
                                        <div class="flex items-center">
                                            <Button size="icon" variant="outline" @click="cartStore.decrementQuantity(index)" class="h-6 w-6">
                                                <Minus class="h-3 w-3" />
                                            </Button>
                                            <span class="mx-2">{{ cartItem.quantity }}</span>
                                            <Button size="icon" variant="outline" @click="cartStore.incrementQuantity(index)" class="h-6 w-6">
                                                <Plus class="h-3 w-3" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end justify-between gap-2 text-right">
                                    <p class="font-medium">{{ cartStore.formatPrice(cartItemTotal(cartItem)) }}</p>
                                    <Button
                                        size="icon"
                                        variant="outline"
                                        @click="cartStore.removeFromCart(index)"
                                        class="h-6 w-6"
                                        :title="'Remove item'"
                                    >
                                        <Trash2 class="h-3 w-3" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order History Tab Content -->
                <div v-if="activeTab === 'history'">
                    <!-- No Orders Message -->
                    <div v-if="!cartStore.hasOrderHistory && !cartStore.hasActiveOrderItems" class="py-8 text-center">
                        <p class="text-muted-foreground">No order history available</p>
                    </div>

                    <!-- Order History Section -->
                    <div v-if="cartStore.hasOrderHistory || cartStore.hasActiveOrderItems" class="space-y-6">
                        <!-- Past Orders from History -->
                        <div
                            v-for="(order, orderIndex) in cartStore.orderHistory"
                            :key="'order-' + orderIndex"
                            class="mb-4 rounded-lg border bg-gray-50 p-4"
                        >
                            <!-- Order ID and Info -->
                            <div class="mb-4 flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-medium text-gray-500">ORD-{{ order.code || '000000' }}</h3>
                                    <p class="mt-1 text-xs text-gray-500">{{ new Date(order.created_at).toLocaleString() }}</p>
                                </div>
                                <span class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-800">
                                    {{ order.is_paid ? 'Paid' : 'Unpaid' }}
                                </span>
                            </div>

                            <!-- Order Items -->
                            <div class="space-y-2">
                                <div v-if="!order.items || order.items.length === 0" class="py-2 text-center text-sm text-gray-500">
                                    No items in this order
                                </div>
                                <div
                                    v-for="(item, itemIndex) in order.items || []"
                                    :key="'item-' + orderIndex + '-' + itemIndex"
                                    class="flex items-start justify-between py-1"
                                >
                                    <div class="flex flex-col">
                                        <div>
                                            <div class="font-medium text-gray-900">{{ item.name }}</div>
                                            <div class="mt-1 flex items-center gap-3 text-sm text-gray-700">
                                                <span>{{ item.quantity || 1 }}x</span>
                                                <span class="text-gray-600">({{ cartStore.formatPrice(unitPriceForOrderItem(item)) }})</span>
                                            </div>
                                        </div>
                                        <!-- Display selected options if available -->
                                        <div v-if="item.options && item.options.length > 0" class="mt-1 text-xs text-gray-600">
                                            <div v-for="(option, optIdx) in item.options" :key="optIdx">
                                                <span class="font-medium">{{ option.option_name }}:</span>
                                                {{ option.choices?.join(', ') || option.option_name }}
                                                <span v-if="option.additional_price > 0">
                                                    (+{{ cartStore.formatPrice(option.additional_price) }})</span
                                                >
                                            </div>
                                        </div>
                                        <div v-if="item.special_instructions" class="mt-1 ml-5 text-xs text-gray-500 italic">
                                            {{ item.special_instructions }}
                                        </div>
                                    </div>

                                    <div class="flex items-center">
                                        <span class="mr-2">
                                            {{ cartStore.formatPrice(calculateItemTotal(item)) }}
                                        </span>
                                        <span
                                            class="rounded-full px-2 py-1 text-xs font-medium"
                                            :class="{
                                                'bg-yellow-100 text-yellow-800': item.status === 'pending',
                                                'bg-orange-100 text-orange-800': item.status === 'cooking',
                                                'bg-green-100 text-green-800': item.status === 'ready',
                                                'bg-gray-100 text-gray-800': item.status === 'served',
                                            }"
                                        >
                                            {{ item.status }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Order Total -->
                            <div class="mt-4 flex items-center justify-between border-t border-gray-200 pt-2">
                                <span class="font-medium">Total</span>
                                <span class="font-medium">{{ cartStore.formatPrice(calculateOrderTotal(order)) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>

            <CardFooter>
                <div class="w-full">
                    <!-- Cart Tab Footer -->
                    <div v-if="activeTab === 'cart'">
                        <div class="flex justify-between py-2">
                            <span class="font-medium">Total</span>
                            <span class="font-medium">{{ cartStore.formatPrice(cartStore.cartTotal) }}</span>
                        </div>

                        <!-- PromptPay QR Code (shown when payment is required) -->
                        <div v-if="props.payBefore && showPaymentQR" class="my-4 space-y-3">
                            <PromptPayQRCode :promptPayId="props.promptPayId" :amount="cartStore.cartTotal" :label="'Table ' + props.tableCode" />
                            <p class="text-sm text-gray-500">Scan & pay, then upload your slip below before completing the order.</p>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium">Upload Payment Slip</label>
                                <input
                                    type="file"
                                    accept="image/*"
                                    @change="handleSlipUpload"
                                    class="w-full cursor-pointer rounded border p-2 text-sm"
                                />
                                <div v-if="slipImageData" class="flex items-center justify-between rounded border bg-white p-2 text-xs">
                                    <span class="truncate">{{ slipFileName || 'Slip attached' }}</span>
                                    <button type="button" class="text-red-500 hover:underline" @click="removeSlip">Remove</button>
                                </div>
                                <div v-if="errors.slip_image" class="text-xs text-red-500">{{ errors.slip_image }}</div>
                            </div>
                            <div class="flex justify-end">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="
                                        () => {
                                            showPaymentQR = false;
                                            removeSlip();
                                        }
                                    "
                                >
                                    Cancel Payment
                                </Button>
                            </div>
                        </div>

                        <Button
                            class="mt-4 w-full"
                            :disabled="cartStore.cart.length === 0 || (props.payBefore && showPaymentQR && !slipImageData)"
                            @click="submitOrder"
                        >
                            <span v-if="processing" class="flex items-center">
                                <svg
                                    class="mr-3 -ml-1 h-5 w-5 animate-spin text-white"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                    ></path>
                                </svg>
                                Processing...
                            </span>
                            <span v-else>{{ props.payBefore ? (showPaymentQR ? 'Complete Order' : 'Pay') : 'Place Order' }}</span>
                        </Button>
                    </div>

                    <!-- Order History Tab Footer -->
                    <div v-if="activeTab === 'history' && cartStore.hasActiveOrderItems">
                        <div class="flex justify-between py-2">
                            <span class="font-medium">Total for all orders:</span>
                            <span class="font-medium">{{ cartStore.formatPrice(cartStore.getOrderTotal()) }}</span>
                        </div>
                        <div class="mt-4 text-center text-sm text-muted-foreground">
                            <p>Use the "Request Bill" button below the menu to get your invoice</p>
                        </div>
                    </div>
                    <div v-if="errors.qr_code_data" class="mt-2 text-sm text-red-500">
                        {{ errors.qr_code_data }}
                    </div>
                </div>
            </CardFooter>
        </Card>
    </div>

    <!-- Option Selection Modal -->
    <div v-show="cartStore.showOptionModal && cartStore.currentItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <Card class="max-h-[90vh] w-full max-w-md overflow-y-auto">
            <CardHeader>
                <CardTitle>Customize {{ cartStore.currentItem ? cartStore.currentItem.name : 'Item' }}</CardTitle>
                <CardDescription>
                    <div class="flex items-center justify-between">
                        <span>Select your preferences</span>
                        <span class="font-medium">{{ cartStore.formatPrice(cartStore.currentItem ? cartStore.currentItem.price : 0) }}</span>
                    </div>
                </CardDescription>
            </CardHeader>

            <CardContent>
                <!-- Options -->
                <div v-if="cartStore.currentItem && cartStore.currentItem.options && cartStore.currentItem.options.length > 0" class="space-y-6">
                    <!-- Display each option -->
                    <div v-for="(option, index) in cartStore.currentItem.options" :key="index" class="mb-4 space-y-2 border-b pb-4 last:border-0">
                        <div class="flex items-center justify-between">
                            <h3 class="font-medium">{{ option.name }}</h3>
                            <div class="flex items-center">
                                <span v-if="option.required !== false" class="mr-2 text-xs text-red-500">Required</span>
                                <span v-else class="mr-2 text-xs text-muted-foreground">Optional</span>
                                <span v-if="option.multiple === true" class="rounded bg-gray-100 px-2 py-1 text-xs">Multiple</span>
                            </div>
                        </div>

                        <!-- Display choices (test format) -->
                        <div v-if="option.choices && Array.isArray(option.choices) && option.choices.length > 0" class="space-y-1">
                            <div
                                v-for="choice in option.choices"
                                :key="choice.name"
                                @click="cartStore.toggleOptionChoice(option.name, choice.name, choice.price || 0)"
                                class="flex cursor-pointer items-center justify-between rounded-md p-2 hover:bg-muted"
                                :class="{
                                    'bg-muted': cartStore.isChoiceSelected(option.name, choice.name),
                                    'border-l-2 border-yellow-500': cartStore.isChoiceSelected(option.name, choice.name),
                                }"
                            >
                                <div class="flex items-center">
                                    <div
                                        :class="{
                                            'mr-2 h-4 w-4 rounded-sm border': option.multiple === true,
                                            'mr-2 h-4 w-4 rounded-full border': option.multiple !== true,
                                            'border-yellow-500 bg-yellow-500': cartStore.isChoiceSelected(option.name, choice.name),
                                            'border-gray-300': !cartStore.isChoiceSelected(option.name, choice.name),
                                        }"
                                    ></div>
                                    <span>{{ choice.name }}</span>
                                </div>
                                <span v-if="choice.price && choice.price > 0" class="text-sm text-muted-foreground">
                                    +{{ cartStore.formatPrice(choice.price) }}
                                </span>
                            </div>
                        </div>

                        <!-- Display values (database format) -->
                        <div v-if="option.values && Array.isArray(option.values) && option.values.length > 0" class="space-y-1">
                            <div
                                v-for="value in option.values"
                                :key="value.name"
                                @click="cartStore.toggleOptionChoice(option.name, value.name, value.price || 0)"
                                class="flex cursor-pointer items-center justify-between rounded-md p-2 hover:bg-muted"
                                :class="{
                                    'bg-muted': cartStore.isChoiceSelected(option.name, value.name),
                                    'border-l-2 border-yellow-500': cartStore.isChoiceSelected(option.name, value.name),
                                }"
                            >
                                <div class="flex items-center">
                                    <div
                                        :class="{
                                            'mr-2 h-4 w-4 rounded-sm border': option.multiple === true,
                                            'mr-2 h-4 w-4 rounded-full border': option.multiple !== true,
                                            'border-yellow-500 bg-yellow-500': cartStore.isChoiceSelected(option.name, value.name),
                                            'border-gray-300': !cartStore.isChoiceSelected(option.name, value.name),
                                        }"
                                    ></div>
                                    <span>{{ value.name }}</span>
                                </div>
                                <span v-if="value.price && value.price > 0" class="text-sm text-muted-foreground">
                                    +{{ cartStore.formatPrice(value.price) }}
                                </span>
                            </div>
                        </div>

                        <!-- Show if no options available -->
                        <p
                            v-if="(!option.choices || option.choices.length === 0) && (!option.values || option.values.length === 0)"
                            class="text-sm text-muted-foreground italic"
                        >
                            No options available
                        </p>
                    </div>
                </div>

                <!-- Notes -->
                <div class="mt-6">
                    <label for="notes" class="mb-1 block text-sm font-medium">Special Instructions</label>
                    <textarea
                        id="notes"
                        v-model="cartStore.itemNotes"
                        rows="2"
                        class="w-full rounded-md border p-2 text-sm"
                        placeholder="Any special requests?"
                    ></textarea>
                </div>

                <!-- Quantity selector -->
                <div class="mt-4 flex items-center justify-between">
                    <label class="text-sm font-medium">Quantity</label>
                    <div class="flex items-center gap-2">
                        <Button size="icon" variant="outline" @click="modalQuantity = Math.max(1, modalQuantity - 1)">
                            <Minus class="h-3 w-3" />
                        </Button>
                        <span class="w-8 text-center">{{ modalQuantity }}</span>
                        <Button size="icon" variant="outline" @click="modalQuantity++">
                            <Plus class="h-3 w-3" />
                        </Button>
                    </div>
                </div>
            </CardContent>

            <CardFooter class="flex justify-between">
                <Button variant="outline" @click="cartStore.closeOptionModal">Cancel</Button>
                <Button
                    @click="
                        () => {
                            cartStore.confirmAddToCart(modalQuantity);
                            modalQuantity = 1;
                        }
                    "
                    >Add to Cart</Button
                >
            </CardFooter>
        </Card>
    </div>
</template>

<style scoped>
/* Add any custom styles here */
</style>
