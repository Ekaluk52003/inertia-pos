<script setup lang="ts">
import PromptPayQRCode from '@/components/PromptPayQRCode.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useCartStore } from '@/stores/cartStore';
import { CheckCircle2, Receipt } from 'lucide-vue-next';
import { computed } from 'vue';

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
    activeOrder: any | null;
    showPaymentQr: boolean;
}

const props = defineProps<Props>();
const cartStore = useCartStore();

// Calculate total price for an item including options
const calculateItemTotal = (item: any): number => {
    const qty = Number(item.quantity || 1);

    // If the item has a persisted price, use it directly
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

// Format price for display
const formatPrice = (price: number) => {
    return new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB' }).format(price);
};

// Computed properties for display
const invoiceTitle = computed(() => {
    if (props.restaurant.payBefore) {
        return 'Invoice - Payment Received';
    }

    if (cartStore.isBilled) {
        return 'Invoice - Payment Complete';
    }

    return 'Invoice - Please Pay';
});

const statusMessage = computed(() => {
    if (props.restaurant.payBefore) {
        return 'Payment was received during ordering';
    }

    if (cartStore.isBilled) {
        return 'Thank you for your payment!';
    }

    return 'Bill requested - please pay when ready';
});

const statusIcon = computed(() => {
    return props.restaurant.payBefore || cartStore.isBilled ? CheckCircle2 : Receipt;
});

const statusColor = computed(() => {
    return props.restaurant.payBefore || cartStore.isBilled ? 'text-green-600' : 'text-blue-600';
});
</script>

<template>
    <div class="mx-auto max-w-2xl">
        <Card class="w-full">
            <CardHeader class="text-center">
                <div class="mb-4 flex justify-center">
                    <component :is="statusIcon" :class="[statusColor, 'h-12 w-12']" />
                </div>
                <CardTitle class="text-2xl">{{ invoiceTitle }}</CardTitle>
                <p class="mt-2 text-muted-foreground">{{ statusMessage }}</p>

                <!-- PAID Stamp for prepaid orders -->
                <div v-if="restaurant.payBefore" class="mt-4">
                    <div class="inline-block rounded-lg border-2 border-green-300 bg-green-100 px-4 py-2 font-bold text-green-800">PAID</div>
                </div>
            </CardHeader>

            <CardContent>
                <!-- Restaurant and Table Info -->
                <div class="mb-6 border-b pb-4 text-center">
                    <h3 class="text-lg font-semibold">{{ restaurant.name }}</h3>
                    <p class="text-muted-foreground">Table {{ table.number }}</p>
                    <p v-if="activeOrder" class="mt-1 text-sm text-muted-foreground">Order: {{ activeOrder.code }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ new Date().toLocaleString() }}
                    </p>
                </div>

                <!-- Order Items -->
                <div v-if="activeOrder && activeOrder.items && activeOrder.items.length > 0" class="mb-6 space-y-4">
                    <h4 class="text-lg font-semibold">Order Details</h4>

                    <div
                        v-for="(item, index) in activeOrder.items"
                        :key="index"
                        class="flex items-start justify-between border-b py-2 last:border-b-0"
                    >
                        <div class="flex-1">
                            <div class="font-medium">{{ item.name }}</div>
                            <div class="text-sm text-muted-foreground">Qty: {{ item.quantity }}</div>

                            <!-- Display selected options -->
                            <div v-if="item.options && item.options.length > 0" class="mt-1">
                                <div v-for="(option, optIdx) in item.options" :key="optIdx" class="text-xs text-muted-foreground">
                                    <span class="font-medium">{{ option.option_name }}:</span>
                                    {{ option.choices?.join(', ') || option.option_name }}
                                    <span v-if="option.additional_price > 0"> (+{{ formatPrice(option.additional_price) }}) </span>
                                </div>
                            </div>

                            <!-- Special instructions -->
                            <div v-if="item.special_instructions" class="mt-1 text-xs text-muted-foreground italic">
                                {{ item.special_instructions }}
                            </div>
                        </div>

                        <div class="ml-4 text-right">
                            <div class="font-medium">{{ formatPrice(calculateItemTotal(item)) }}</div>
                        </div>
                    </div>
                </div>

                <!-- Order Total -->
                <div class="mt-6 border-t pt-4">
                    <div class="flex items-center justify-between text-xl font-bold">
                        <span>Total Amount</span>
                        <span>{{ formatPrice(activeOrder?.total_amount || 0) }}</span>
                    </div>
                </div>

                <!-- Payment QR Code (only for pay_after restaurants in billing status) -->
                <div v-if="showPaymentQr && !restaurant.payBefore" class="mt-8 text-center">
                    <div class="rounded-lg bg-gray-50 p-6">
                        <h5 class="mb-4 font-semibold">Scan to Pay</h5>
                        <div class="mb-4 flex justify-center">
                            <PromptPayQRCode
                                :promptPayId="restaurant.promptPayId"
                                :amount="activeOrder?.total_amount || 0"
                                :label="`Table ${table.number}`"
                            />
                        </div>
                        <p class="text-sm text-muted-foreground">Please scan the QR code above to complete your payment</p>
                    </div>
                </div>

                <!-- Payment confirmation message -->
                <div v-if="cartStore.isBilled || restaurant.payBefore" class="mt-8 text-center">
                    <div class="rounded-lg border border-green-200 bg-green-50 p-4">
                        <div class="mb-2 flex items-center justify-center">
                            <CheckCircle2 class="mr-2 h-6 w-6 text-green-600" />
                            <span class="font-semibold text-green-800">Payment Confirmed</span>
                        </div>
                        <p class="text-sm text-green-700">
                            {{
                                restaurant.payBefore
                                    ? 'Payment was received when you placed your order.'
                                    : 'Thank you for your payment. Your order is complete!'
                            }}
                        </p>
                    </div>
                </div>

                <!-- Footer message -->
                <div class="mt-8 text-center text-sm text-muted-foreground">
                    <p>Thank you for dining with us!</p>
                    <p class="mt-1">{{ restaurant.name }}</p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>

<style scoped>
/* Add any custom styles here */
</style>
