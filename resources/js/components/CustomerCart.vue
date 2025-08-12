<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { useCartStore } from '@/stores/cartStore';
import { useForm, usePage, router } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, Clock, Minus, Plus, ShoppingCart } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const page = usePage();
const processing = computed(() => page.props.processing);
const errors = computed(() => page.props.errors || {});

// Props
interface Props {
    restaurantId: number;
    tableCode: string;
    payBefore: boolean;
    activeOrder?: any;
    orderHistory?: any[];
}

const props = defineProps<Props>();

// Use the cart store
const cartStore = useCartStore();

// Tab state
const activeTab = ref('cart'); // 'cart' or 'history'

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

// Method to request the bill
const requestBill = () => {
    // Call the route to request a bill
    useForm({
        order_id: props.activeOrder?.id
    }).post(
        route('public.bill.request', {
            restaurantCode: props.restaurantId,
            tableCode: props.tableCode,
        }),
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                // Show success message
                alert('Bill requested successfully!');
            },
            onError: (errors) => {
                console.error('Bill request errors:', errors);
            },
        }
    );
};

// Method to submit the order
const submitOrder = () => {
    // Prepare order items
    const items = cartStore.prepareOrderItems();


    // Submit the form using named route
    useForm({
        items,
        customer_notes: '',
        ...(props.payBefore
            ? {
                  slip_image: null,
                  qr_code_data: null,
              }
            : {}),
    }).post(
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
                
                router.reload({ only: ['orderHistory'] })
                
                // Make sure orderHistory exists and is an array before setting it
                if (props.orderHistory && Array.isArray(props.orderHistory)) {
                    cartStore.setOrderHistory(props.orderHistory);
                }
                
                // Also update the cart store with the new order history when it arrives
                // setTimeout(() => {
                //     if (props.orderHistory && Array.isArray(props.orderHistory)) {
                //         cartStore.setOrderHistory(props.orderHistory);
                //     }
                // }, 500);
                
                // Switch to history tab after a short delay
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
            :class="{'animate-pulse': cartStore.hasActiveOrderItems && !cartStore.showCart}"
        >
            <ShoppingCart class="mr-2 h-4 w-4" />
            <span v-if="cartStore.totalItemCount > 0">
                {{ cartStore.totalItemCount }} items
                <template v-if="cartStore.cartTotal > 0"> · {{ cartStore.formatPrice(cartStore.cartTotal) }}</template>
                <template v-if="cartStore.hasActiveOrderItems">
                    <span class="ml-1 inline-flex h-2 w-2 rounded-full bg-green-500"></span>
                </template>
            </span>
            <span v-else>Cart</span>
            <ChevronUp v-if="cartStore.showCart" class="ml-2 h-4 w-4" />
            <ChevronDown v-else class="ml-2 h-4 w-4" />
        </Button>

        <!-- Cart Panel -->
        <Card v-if="cartStore.showCart" class="absolute right-0 bottom-full left-0 md:left-auto md:w-[400px] mt-2 mb-2 max-h-[70vh] overflow-y-auto z-30">
            <CardHeader>
                <CardTitle>Your Order</CardTitle>
                <CardDescription>Table {{ props.tableCode }}</CardDescription>
                
                <!-- Tabs for Cart and Order History -->
                <div class="mt-4 border-b">
                    <div class="flex">
                        <button 
                            @click="activeTab = 'cart'" 
                            data-tab="cart"
                            class="px-4 py-2 -mb-px text-sm font-medium" 
                            :class="{
                                'border-b-2 border-yellow-500 text-yellow-600': activeTab === 'cart',
                                'text-gray-500 hover:text-gray-700': activeTab !== 'cart'
                            }">
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
                            class="px-4 py-2 -mb-px text-sm font-medium" 
                            :class="{
                                'border-b-2 border-yellow-500 text-yellow-600': activeTab === 'history',
                                'text-gray-500 hover:text-gray-700': activeTab !== 'history'
                            }">
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
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-medium text-gray-800">New Items</h3>
                            <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full">Not ordered yet</span>
                        </div>

                        <div class="space-y-4">
                            <div v-for="(cartItem, index) in cartStore.cart" :key="index" class="flex items-start justify-between border-b border-yellow-200 pb-4">
                                <div>
                                    <h4 class="font-medium">{{ cartItem.item.name }}</h4>
                                    <p class="text-sm text-muted-foreground">{{ cartStore.formatPrice(cartItem.item.price) }} each</p>
                                    <!-- Display selected options -->
                                    <div v-if="cartItem.selectedOptions.length > 0" class="mt-1">
                                        <div v-for="(option, optIdx) in cartItem.selectedOptions" :key="optIdx" class="text-xs text-muted-foreground">
                                            <span class="font-medium">{{ option.optionName }}:</span>
                                            {{ option.choices.join(', ') }}
                                            <span v-if="option.additionalPrice > 0"> (+{{ cartStore.formatPrice(option.additionalPrice) }}) </span>
                                        </div>
                                    </div>
                                    <!-- Display notes if any -->
                                    <p v-if="cartItem.notes" class="mt-1 text-xs italic">"{{ cartItem.notes }}"</p>
                                    <div class="mt-2 flex items-center">
                                        <Button size="icon" variant="outline" @click="cartStore.decrementQuantity(index)" class="h-6 w-6">
                                            <Minus class="h-3 w-3" />
                                        </Button>
                                        <span class="mx-2">{{ cartItem.quantity }}</span>
                                        <Button size="icon" variant="outline" @click="cartStore.incrementQuantity(index)" class="h-6 w-6">
                                            <Plus class="h-3 w-3" />
                                        </Button>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium">{{ cartStore.formatPrice(cartItem.item.price * cartItem.quantity) }}</p>
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
                        <!-- Most Recent Active Order (if available) -->
                        <div v-if="cartStore.hasActiveOrderItems" class="rounded-lg bg-white border p-4 mb-6">
                            <!-- Order ID and Info -->
                            <div class="mb-4 flex justify-between items-center">
                                <h3 class="text-sm font-medium text-gray-500">ORD-{{ props.activeOrder?.code || '000000' }}</h3>
                                <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full">Current Order</span>
                            </div>
                            
                            <!-- Order Items -->
                            <div class="space-y-3">
                                <!-- Pending Items -->
                                <div v-for="(item, index) in cartStore.pendingItems" :key="'pending-'+index" class="flex justify-between items-center">
                                    <div class="flex items-center">
                                        <span class="mr-2">{{ item.quantity || 1 }}x</span>
                                        <span>{{ item.name }}</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="mr-2">{{ cartStore.formatPrice(item.price) }}</span>
                                        <span class="rounded-full bg-yellow-100 px-2 py-1 text-xs font-medium text-yellow-800">pending</span>
                                    </div>
                                </div>
                                
                                <!-- Cooking Items -->
                                <div v-for="(item, index) in cartStore.cookingItems" :key="'cooking-'+index" class="flex justify-between items-center">
                                    <div class="flex items-center">
                                        <span class="mr-2">{{ item.quantity || 1 }}x</span>
                                        <span>{{ item.name }}</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="mr-2">{{ cartStore.formatPrice(item.price) }}</span>
                                        <span class="rounded-full bg-orange-100 px-2 py-1 text-xs font-medium text-orange-800">cooking</span>
                                    </div>
                                </div>
                                
                                <!-- Ready Items -->
                                <div v-for="(item, index) in cartStore.readyItems" :key="'ready-'+index" class="flex justify-between items-center">
                                    <div class="flex items-center">
                                        <span class="mr-2">{{ item.quantity || 1 }}x</span>
                                        <span>{{ item.name }}</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="mr-2">{{ cartStore.formatPrice(item.price) }}</span>
                                        <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800">ready</span>
                                    </div>
                                </div>
                                
                                <!-- Served Items -->
                                <div v-for="(item, index) in cartStore.servedItems" :key="'served-'+index" class="flex justify-between items-center">
                                    <div class="flex items-center">
                                        <span class="mr-2">{{ item.quantity || 1 }}x</span>
                                        <span>{{ item.name }}</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="mr-2">{{ cartStore.formatPrice(item.price) }}</span>
                                        <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-800">served</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Past Orders from History -->
                        <div v-for="(order, orderIndex) in cartStore.orderHistory" :key="'order-'+orderIndex" class="rounded-lg bg-gray-50 border p-4 mb-4">
                            <!-- Order ID and Info -->
                            <div class="mb-4 flex justify-between items-center">
                                <div>
                                    <h3 class="text-sm font-medium text-gray-500">ORD-{{ order.code || '000000' }}</h3>
                                    <p class="text-xs text-gray-500 mt-1">{{ new Date(order.created_at).toLocaleString() }}</p>
                                </div>
                                <span class="text-xs bg-gray-100 text-gray-800 px-2 py-1 rounded-full">
                                    {{ order.is_paid ? 'Paid' : 'Unpaid' }}
                                </span>
                            </div>
                            
                            <!-- Order Items -->
                            <div class="space-y-2">
                                <div v-if="!order.order_items || order.order_items.length === 0" class="text-center py-2 text-gray-500 text-sm">
                                    No items in this order
                                </div>
                                <div v-for="(item, itemIndex) in order.order_items || []" :key="'item-'+orderIndex+'-'+itemIndex" class="flex justify-between items-start py-1">
                                    <div class="flex flex-col">
                                        <div class="flex items-center">
                                            <span class="mr-2">{{ item.quantity || 1 }}x</span>
                                            <span>{{ item.name }}</span>
                                        </div>
                                        <div v-if="item.special_instructions" class="text-xs text-gray-500 italic ml-5 mt-1">
                                            {{ item.special_instructions }}
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center">
                                        <span class="mr-2">{{ cartStore.formatPrice(item.price) }}</span>
                                        <span class="rounded-full px-2 py-1 text-xs font-medium"
                                            :class="{
                                                'bg-yellow-100 text-yellow-800': item.status === 'pending',
                                                'bg-orange-100 text-orange-800': item.status === 'cooking',
                                                'bg-green-100 text-green-800': item.status === 'ready',
                                                'bg-gray-100 text-gray-800': item.status === 'served'
                                            }">
                                            {{ item.status }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Order Total -->
                            <div class="mt-4 pt-2 border-t border-gray-200 flex justify-between items-center">
                                <span class="font-medium">Total</span>
                                <span class="font-medium">{{ cartStore.formatPrice(order.total_amount) }}</span>
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
                        <Button class="mt-4 w-full" :disabled="cartStore.cart.length === 0" @click="submitOrder">
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
                            <span v-else>Place Order</span>
                        </Button>
                    </div>
                    
                    <!-- Order History Tab Footer -->
                    <div v-if="activeTab === 'history' && cartStore.hasActiveOrderItems">
                        <div class="flex justify-between py-2">
                            <span class="font-medium">Total for all orders:</span>
                            <span class="font-medium">{{ cartStore.formatPrice(cartStore.getOrderTotal()) }}</span>
                        </div>
                        <Button class="mt-4 w-full bg-green-600 hover:bg-green-700" @click="requestBill">
                            <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            Request Bill
                        </Button>
                        {{ errors.slip_image }}
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
            </CardContent>

            <CardFooter class="flex justify-between">
                <Button variant="outline" @click="cartStore.closeOptionModal">Cancel</Button>
                <Button @click="cartStore.confirmAddToCart">Add to Cart</Button>
            </CardFooter>
        </Card>
    </div>
</template>

<style scoped>
/* Add any custom styles here */
</style>
