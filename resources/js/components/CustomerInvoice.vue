<script setup lang="ts">
import PromptPayQRCode from '@/components/PromptPayQRCode.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useCartStore } from '@/stores/cartStore';
import { router, useForm } from '@inertiajs/vue3';
import { Receipt } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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
const emit = defineEmits<{
    (e: 'slip-upload', payload: { slip: string; fileName: string }): void;
    (e: 'slip-verified', payload: { success: boolean; data?: any; error?: any }): void;
}>();

// Slip upload state for customers to attach payment proof on the invoice page
// We post immediately to /public/slip/verify on file selection instead of showing the image.
const slipFileName = ref<string>('');
const slipStatus = ref<null | 'pending' | 'success' | 'error'>(null);
const slipMessage = ref<string>('');
const slipInputRef = ref<HTMLInputElement | null>(null);

// Inertia form for slip verification (uses FormData automatically when file present)
// include items, restaurantCode and tableCode because server expects them
const slipForm = useForm({
    slip_image: null as File | null,
    amount: 0,
    items: [] as any[],
    restaurantCode: props.restaurant?.id ?? null,
    tableCode: props.table?.code ?? null,
});

// this will call verifySlip in order controller.
// we will not create order again for this case as this invoice component will show when resturant pay_before == false meaning that customer can place order before pay
const handleSlipUpload = (e: Event) => {
    const input = e.target as HTMLInputElement | null;
    const file = input?.files?.[0] ?? null;

    if (!file) {
        return; // no file selected (dialog cancelled)
    }

    // Populate form fields required/used by backend validation
    slipFileName.value = file.name;
    slipForm.slip_image = file; // triggers FormData usage
    slipForm.restaurantCode = (props as any)?.restaurant?.id ?? null; // backend accepts nullable
    slipForm.tableCode = (props as any)?.table?.code ?? null; // backend accepts nullable
    // Optional context (not required by validation but can be useful)
    slipForm.amount = invoiceTotal.value;
    slipForm.items = [];

    // Notify parent that an upload started
    emit('slip-upload', { slip: '', fileName: file.name });

    slipForm.post(route('public.slip.verify'), {
        forceFormData: true,
        preserveScroll: true,
        onStart: () => {
            slipStatus.value = 'pending';
            slipMessage.value = 'Verifying…';
        },
        onError: (errors) => {
            const first = (Object.values(errors)[0] as string | undefined) || '';
            slipStatus.value = 'error';
            slipMessage.value = first || 'Verification failed';
            emit('slip-verified', { success: false, error: errors });
        },
        onSuccess: (page: any) => {
            const flash = page.props?.flash || {};
            const slipFlash = flash.slip_verification;
            if (slipFlash && slipFlash.message) {
                slipStatus.value = 'success';
                slipMessage.value = slipFlash.message;
                emit('slip-verified', { success: true, data: slipFlash.data || null });
            } else if (flash.success) {
                slipStatus.value = 'success';
                slipMessage.value = flash.success;
            } else if (flash.slip_error) {
                slipStatus.value = 'error';
                slipMessage.value = flash.slip_error;
            } else {
                slipStatus.value = 'success';
                slipMessage.value = 'Verified';
            }

            // Refresh current page props so invoice/orders reflect new is_paid and QR status
            if (slipStatus.value === 'success') {
                // Optimistically mark current table's orders as paid so UI reflects immediately
                try {
                    // Mark active order
                    if (cartStore.activeOrder) {
                        cartStore.activeOrder.is_paid = true;
                        if (cartStore.activeOrder.qr_code) {
                            cartStore.activeOrder.qr_code.status = 'checked';
                        }
                    }

                    // Mark order history for this table as paid
                    if (Array.isArray(cartStore.orderHistory)) {
                        const updated = cartStore.orderHistory.map((o: any) => {
                            if (!o) return o;
                            const sameTable =
                                o.table_number === props.table.number ||
                                o.table_code === props.table.code ||
                                o.qr_code?.table_code === props.table.code;
                            if (sameTable) {
                                return {
                                    ...o,
                                    is_paid: true,
                                    qr_code: o.qr_code ? { ...o.qr_code, status: 'checked' } : o.qr_code,
                                };
                            }
                            return o;
                        });
                        // Prefer store setter if available to ensure reactivity
                        if (typeof (cartStore as any).setOrderHistory === 'function') {
                            (cartStore as any).setOrderHistory(updated);
                        } else {
                            (cartStore as any).orderHistory = updated;
                        }
                    }
                } catch (e) {
                    // noop – optimistic update best-effort
                }

                router.reload();
            }
        },
        onFinish: () => {
            // Clear the input so the same file can be re-selected if needed
            if (slipInputRef.value) {
                slipInputRef.value.value = '';
            }
        },
    });
};

const retrySlip = () => {
    if (slipInputRef.value) {
        slipInputRef.value.value = '';
        slipStatus.value = null;
        slipMessage.value = '';
        // trigger native file selector
        slipInputRef.value.click();
    }
};

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

// Get the unit/base price for an order item excluding any selected option additional prices
const baseUnitPrice = (item: any): number => {
    // Prefer explicit base_price if provided by backend
    if (item && item.base_price !== undefined && item.base_price !== null) {
        return Number(item.base_price);
    }

    // If menuItem relation exists, prefer its price
    if (item && item.menuItem && item.menuItem.price !== undefined) {
        return Number(item.menuItem.price);
    }

    // If the item.price already includes options, subtract option additional prices if available
    if (item && item.price !== undefined && item.options && Array.isArray(item.options) && item.options.length > 0) {
        const extras = item.options.reduce((s: number, o: any) => s + Number(o.additional_price || 0), 0);
        return Math.max(0, Number(item.price) - extras);
    }

    // Fallback to item.price
    return Number(item.price ?? 0);
};

// Sum of option additional prices (per unit)
const optionExtras = (item: any): number => {
    if (!item || !item.options || !Array.isArray(item.options)) return 0;
    return item.options.reduce((s: number, o: any) => s + Number(o.additional_price || 0), 0);
};

const statusMessage = computed(() => {
    // Static status message — keep minimal so order row statuses show payment tracking
    return '';
});

const statusIcon = computed(() => {
    // static icon for invoice header; order-level paid/unpaid shown per-order
    return Receipt;
});

const statusColor = computed(() => {
    return 'text-blue-600';
});

// Normalize restaurant pay-before flag: accept either camelCase or snake_case from server
const isPayBefore = computed(() => {
    if (!props.restaurant) return false;
    return (props.restaurant as any).payBefore ?? (props.restaurant as any).pay_before ?? false;
});

// Show prompt-pay QR only when the QR code status is explicitly 'billing'
const showPaymentQRLocal = computed(() => {
    // Prefer explicit table qr_code (server-provided), fallback to active order qr_code
    const tableQrStatus = (props.table as any)?.qr_code?.status;
    if (tableQrStatus === 'billing') return true;

    const activeQrStatus = cartStore.activeOrder?.qr_code?.status;
    return activeQrStatus === 'billing';
});

// Build list of orders to include on the invoice: include the activeOrder if present
// and any orders from orderHistory that are in billing/billed/active/completed states.
const ordersForInvoice = computed(() => {
    // Include orders that are in billing/billed/completed lifecycle according to qr_code or have items
    const statuses = ['billing', 'billed', 'completed', 'active'];
    const orders: any[] = [];

    if (
        cartStore.activeOrder &&
        (cartStore.activeOrder.is_paid || (cartStore.activeOrder.qr_code && statuses.includes(cartStore.activeOrder.qr_code.status || '')))
    ) {
        orders.push(cartStore.activeOrder);
    }

    if (cartStore.orderHistory && Array.isArray(cartStore.orderHistory)) {
        cartStore.orderHistory.forEach((o: any) => {
            if (!o) return;
            const qrStatus = o.qr_code?.status || '';
            if (o.is_paid || (qrStatus && statuses.includes(qrStatus))) {
                if (!cartStore.activeOrder || o.id !== cartStore.activeOrder.id) {
                    orders.push(o);
                }
            }
        });
    }

    return orders;
});

const invoiceTotal = computed(() => {
    // Sum totals across all ordersForInvoice
    return ordersForInvoice.value.reduce((sum: number, ord: any) => {
        if (!ord || !ord.items) return sum;
        const orderSum = ord.items.reduce((t: number, item: any) => {
            const qty = Number(item.quantity || 1);
            const unit = Number(item.price || item.base_price || 0);
            return t + unit * qty;
        }, 0);
        return sum + orderSum;
    }, 0);
});

// Compute total for a single order safely (used in template)
const orderTotal = (ord: any): number => {
    if (!ord) return 0;
    if (ord.total_amount !== undefined && ord.total_amount !== null) {
        return Number(ord.total_amount);
    }

    if (!ord.items || !Array.isArray(ord.items)) return 0;

    return ord.items.reduce((sum: number, it: any) => {
        const qty = Number(it.quantity || 1);
        const unit = Number(it.price || it.base_price || 0);
        return sum + unit * qty;
    }, 0);
};
</script>

<template>
    <div class="mx-auto max-w-2xl">
        <Card class="w-full">
            <CardHeader class="text-center">
                <div class="mb-4 flex justify-center">
                    <component :is="statusIcon" :class="[statusColor, 'h-12 w-12']" />
                </div>
                <CardTitle class="text-2xl">Invoice</CardTitle>
                <p class="mt-2 text-muted-foreground">{{ statusMessage }}</p>

                <!-- Order-level payment status shown per-order; no global PAID stamp -->
            </CardHeader>

            <CardContent>
                <!-- Restaurant and Table Info -->
                <div class="mb-6 border-b pb-4 text-center">
                    <h3 class="text-lg font-semibold">{{ restaurant.name }}</h3>
                    <p class="text-muted-foreground">Table {{ table.number }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">Orders: {{ ordersForInvoice.length }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ new Date().toLocaleString() }}
                    </p>
                </div>

                <!-- Orders and Items -->
                <div v-if="ordersForInvoice && ordersForInvoice.length > 0" class="mb-6 space-y-6">
                    <div v-for="(ord, oidx) in ordersForInvoice" :key="ord.id || oidx" class="rounded-lg border p-4">
                        <div class="mb-2 flex items-center justify-between">
                            <div>
                                <div class="text-sm font-medium">ORD-{{ ord.code || ord.id }}</div>
                                <div class="text-xs text-muted-foreground">{{ new Date(ord.created_at).toLocaleString() }}</div>
                            </div>
                            <div class="text-sm font-medium">{{ ord.is_paid ? 'Paid' : 'Unpaid' }}</div>
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="(item, index) in ord.items || []"
                                :key="index"
                                class="flex items-start justify-between border-b py-2 last:border-b-0"
                            >
                                <div class="flex-1">
                                    <div class="font-medium">{{ item.name }}</div>
                                    <div class="text-sm text-muted-foreground">Qty: {{ item.quantity }}</div>

                                    <div v-if="item.options && item.options.length > 0" class="mt-1">
                                        <div v-for="(option, optIdx) in item.options" :key="optIdx" class="text-xs text-muted-foreground">
                                            <span class="font-medium">{{ option.option_name }}:</span>
                                            {{ option.choices?.join(', ') || option.option_name }}
                                            <span v-if="option.additional_price > 0"> (+{{ formatPrice(option.additional_price) }}) </span>
                                        </div>
                                    </div>

                                    <!-- Show base price and extras per unit -->
                                    <div class="mt-1 text-xs text-muted-foreground">
                                        <span class="font-medium">Unit:</span>
                                        {{ formatPrice(baseUnitPrice(item)) }}
                                        <span v-if="optionExtras(item) > 0"> + extras {{ formatPrice(optionExtras(item)) }} per unit</span>
                                    </div>

                                    <div v-if="item.special_instructions" class="mt-1 text-xs text-muted-foreground italic">
                                        {{ item.special_instructions }}
                                    </div>
                                </div>

                                <div class="ml-4 text-right">
                                    <div class="font-medium">{{ formatPrice(calculateItemTotal(item)) }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 flex items-center justify-between border-t pt-2">
                            <span class="font-medium">Order Total</span>
                            <span class="font-medium">{{ formatPrice(orderTotal(ord)) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Invoice Total -->
                <div class="mt-6 border-t pt-4">
                    <div class="flex items-center justify-between text-xl font-bold">
                        <span>Total Amount</span>
                        <span>{{ formatPrice(invoiceTotal) }}</span>
                    </div>
                </div>

                <!-- Payment QR Code (only for pay_after restaurants when QR status === 'billing') -->
                <div v-if="showPaymentQRLocal" class="mt-8 text-center">
                    <div class="rounded-lg bg-gray-50 p-6">
                        <h5 class="mb-4 font-semibold">Scan to Pay</h5>
                        <div class="mb-4 flex justify-center">
                            <PromptPayQRCode :promptPayId="restaurant.promptPayId" :amount="invoiceTotal" :label="`Table ${table.number}`" />
                        </div>
                        <p class="text-sm text-muted-foreground">Please scan the QR code above to complete your payment</p>

                        <!-- Slip upload UI: allows customer to attach slip image as proof -->
                        <div class="mt-6 text-left">
                            <label class="block text-sm font-medium">Upload Payment Slip</label>
                            <input
                                ref="slipInputRef"
                                type="file"
                                accept="image/*"
                                class="mt-2 w-full cursor-pointer rounded border p-2 text-sm disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="slipStatus === 'pending'"
                                @change="handleSlipUpload"
                            />

                            <div v-if="slipStatus === 'pending'" class="mt-3 rounded border bg-white p-2 text-xs text-gray-700">
                                <div>Verifying slip: {{ slipFileName }}</div>
                                <div v-if="slipForm.progress" class="mt-2">
                                    <div class="h-2 w-full overflow-hidden rounded bg-gray-200">
                                        <div
                                            class="h-full bg-blue-500 transition-all"
                                            :style="{ width: (slipForm.progress.percentage || 0) + '%' }"
                                        ></div>
                                    </div>
                                    <div class="mt-1 text-right text-[10px] tracking-wide text-gray-500">{{ slipForm.progress.percentage }}%</div>
                                </div>
                            </div>
                            <div v-else-if="slipStatus === 'success'" class="mt-3 rounded border bg-green-50 p-2 text-xs text-green-700">
                                Verified: {{ slipMessage }}
                            </div>
                            <div
                                v-else-if="slipStatus === 'error'"
                                class="mt-3 flex items-start justify-between gap-2 rounded border bg-red-50 p-2 text-xs text-red-700"
                            >
                                <div class="flex-1">Verification failed: {{ slipMessage }}</div>
                                <button
                                    type="button"
                                    @click="retrySlip"
                                    class="inline-flex items-center rounded bg-red-600 px-2 py-0.5 text-[10px] font-medium text-white hover:bg-red-700"
                                >
                                    Retry
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment confirmation message removed: invoice only shows order status per-order -->

                <!-- Footer actions/messages -->
                <div class="mt-8 flex flex-col items-center gap-3 text-center">
                    <div class="text-sm text-muted-foreground">
                        <p>Thank you for dining with us!</p>
                        <p class="mt-1">{{ restaurant.name }}</p>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>

<style scoped>
/* Add any custom styles here */
</style>
