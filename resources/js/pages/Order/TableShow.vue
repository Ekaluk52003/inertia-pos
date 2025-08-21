<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { formatDistanceToNow } from 'date-fns';
// removed reactive import since we always show items
import { ArrowLeft } from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    restaurant: { id: number; name: string };
    table_number: string;
    tableOrders: Array<any>;
    qr_code?: { id?: number; status?: string };
}

const props = defineProps<Props>();

const formatPrice = (price: number) => {
    return new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB' }).format(price);
};

const formatDate = (dateString: string) => {
    return formatDistanceToNow(new Date(dateString), { addSuffix: true });
};

// Sum of additional option prices per unit for an order item.
// Prefer to compute option extra as (saved item.price - menuItem.price) when menuItem is available
// to avoid double-counting when the server already persisted price including options.
const optionExtraPerUnit = (item: any): number => {
    if (!item) return 0;

    const savedUnit = Number(item.price || 0);
    const menuUnit = item.menuItem && typeof item.menuItem.price !== 'undefined' ? Number(item.menuItem.price || 0) : null;

    if (menuUnit !== null) {
        const extra = savedUnit - menuUnit;
        return extra > 0 ? extra : 0;
    }

    if (!item.options || !Array.isArray(item.options)) return 0;
    return item.options.reduce((sum: number, opt: any) => sum + (Number(opt.additional_price || 0) || 0), 0);
};

// Base unit price before options: prefer menuItem.price when available
const unitBase = (item: any): number => {
    if (!item) return 0;
    if (item.menuItem && typeof item.menuItem.price !== 'undefined') {
        return Number(item.menuItem.price || 0);
    }
    return Number(item.price || 0);
};

const lineTotal = (item: any): number => {
    const base = unitBase(item);
    const extra = optionExtraPerUnit(item);
    const qty = Number(item.quantity || 0);
    return (base + extra) * qty;
};

const getStatusClass = (status: string) => {
    switch (status) {
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'cooking':
            return 'bg-orange-100 text-orange-800';
        case 'ready':
            return 'bg-green-100 text-green-800';
        case 'served':
            return 'bg-blue-100 text-blue-800';
        case 'active':
            return 'bg-blue-100 text-blue-800';
        case 'billing':
            return 'bg-purple-100 text-purple-800';
        case 'billed':
            return 'bg-green-100 text-green-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const getTableStatusClass = (status?: string) => {
    switch (status) {
        case 'active':
            return 'bg-yellow-100 text-yellow-800';
        case 'billing':
            return 'bg-orange-100 text-orange-800';
        case 'billed':
            return 'bg-green-100 text-green-800';
        case 'checked':
            return 'bg-gray-100 text-gray-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const markOrderBilled = (order: any) => {
    router.post(route('orders.mark-billed', { restaurant: props.restaurant.id, order: order.id }));
};

const markOrderPaid = (order: any) => {
    router.patch(route('orders.mark-paid', { restaurant: props.restaurant.id, order: order.id }));
};

const processingTableChecked = ref(false);

const markTableChecked = () => {
    // show processing indicator
    processingTableChecked.value = true;

    // Post to server then refresh the current table show page to get updated qr_code/status
    router.post(
        route('orders.table.check', { restaurant: props.restaurant.id, tableNumber: props.table_number }),
        {},
        {
            preserveScroll: true,
            showProgress: false,
            onFinish: () => {
                processingTableChecked.value = false;
                router.get(
                    route('orders.table.show', { restaurant: props.restaurant.id, tableNumber: props.table_number }),
                    {},
                    { preserveState: false, showProgress: false },
                );
            },
            onError: () => {
                processingTableChecked.value = false;
            },
        },
    );
};

// always show items; no toggle state required
</script>

<template>
    <AppLayout>
        <Head :title="`Table ${props.table_number} - ${props.restaurant.name}`" />

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="mb-6 flex items-center justify-between">
                    <div class="flex items-center">
                        <Link :href="route('orders.index', { restaurant: props.restaurant.id })" class="mr-4">
                            <Button variant="outline" size="sm">
                                <ArrowLeft class="mr-2 h-4 w-4" />
                                Back to Orders
                            </Button>
                        </Link>
                        <h1 class="text-2xl font-semibold text-gray-900">Table {{ props.table_number }}</h1>
                        <span class="ml-4 inline-flex items-center">
                            <span class="rounded-full px-2 py-1 text-xs font-medium" :class="getTableStatusClass(props.qr_code?.status)">
                                {{ props.qr_code?.status ?? 'unknown' }}
                            </span>
                        </span>
                    </div>
                    <div class="flex items-center space-x-2" v-if="props.qr_code?.status !== 'checked'">
                        <Button
                            size="sm"
                            class="bg-indigo-600 hover:bg-indigo-700"
                            @click.prevent="markTableChecked"
                            :disabled="processingTableChecked === true"
                        >
                            <svg v-if="processingTableChecked" class="mr-2 h-4 w-4 animate-spin" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            Mark table Checked
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Orders for table {{ props.table_number }}</CardTitle>
                        <CardDescription> All orders currently associated with this table. </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Order</TableHead>
                                    <TableHead>Total</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Payment</TableHead>
                                    <TableHead>Created</TableHead>
                                    <TableHead>Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <template v-for="order in props.tableOrders" :key="order.id">
                                    <TableRow>
                                        <TableCell class="font-medium">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <div>{{ order.code.substring(0, 8) }}...</div>
                                                    <div class="text-xs text-gray-500">Items: {{ order.orderItems.length }}</div>
                                                </div>
                                                <div class="text-sm text-gray-500">Items: {{ order.orderItems.length }}</div>
                                            </div>
                                        </TableCell>
                                        <TableCell>{{ formatPrice(order.total_amount) }}</TableCell>
                                        <TableCell>
                                            <span class="rounded-full px-2 py-1 text-xs font-medium" :class="getStatusClass(order.status)">
                                                {{ order.status }}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <span
                                                :class="
                                                    order.is_paid
                                                        ? 'rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800'
                                                        : 'rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-800'
                                                "
                                            >
                                                {{ order.is_paid ? 'Paid' : 'Unpaid' }}
                                            </span>
                                        </TableCell>
                                        <TableCell>{{ formatDate(order.created_at) }}</TableCell>
                                        <TableCell>
                                            <div class="flex space-x-2">
                                                <!-- Items are always visible; toggle removed -->
                                                <Button
                                                    v-if="!order.is_paid"
                                                    size="sm"
                                                    class="bg-green-600 hover:bg-green-700"
                                                    @click="markOrderPaid(order)"
                                                    >Mark Paid</Button
                                                >
                                                <Button
                                                    v-if="order.status === 'billing'"
                                                    size="sm"
                                                    class="bg-purple-600 hover:bg-purple-700"
                                                    @click="markOrderBilled(order)"
                                                >
                                                    Mark Billed
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>

                                    <TableRow>
                                        <TableCell :colspan="6">
                                            <div class="space-y-4">
                                                <div
                                                    v-for="item in order.orderItems"
                                                    :key="item.id"
                                                    class="flex items-center justify-between rounded border p-3"
                                                >
                                                    <div>
                                                        <div class="font-medium">{{ item.menuItem?.name ?? item.name }}</div>
                                                        <div class="text-sm text-gray-500">{{ item.special_instructions }}</div>
                                                        <div class="text-xs text-gray-500">
                                                            <template v-if="item.options && item.options.length">
                                                                Options:
                                                                <span v-for="(opt, idx) in item.options" :key="idx" class="ml-2 text-xs">
                                                                    {{ opt.option_name }}: {{ opt.choices.join(', ') }} ({{
                                                                        formatPrice(opt.additional_price)
                                                                    }})
                                                                </span>
                                                            </template>
                                                        </div>
                                                    </div>
                                                    <div class="text-right">
                                                        <div>Qty: {{ item.quantity }}</div>
                                                        <div class="text-sm text-gray-500">Unit: {{ formatPrice(unitBase(item)) }}</div>
                                                        <div v-if="item.options && item.options.length" class="text-sm text-gray-500">
                                                            Options per unit: {{ formatPrice(optionExtraPerUnit(item)) }}
                                                        </div>
                                                        <div class="font-medium">
                                                            {{ formatPrice(lineTotal(item)) }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                </template>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
