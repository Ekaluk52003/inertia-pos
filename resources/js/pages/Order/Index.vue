<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { formatDistanceToNow } from 'date-fns';
import { ChefHat } from 'lucide-vue-next';
import { ref } from 'vue';

// Define props interface
interface Props {
    restaurant: {
        id: number;
        name: string;
    };
    orders: {
        data: Order[];
        links: any[];
        meta: {
            current_page: number;
            from: number;
            last_page: number;
            links: any[];
            path: string;
            per_page: number;
            to: number;
            total: number;
        };
    };
    // Optional table aggregation provided by the server
    tables?: Array<{
        qr_code_id?: number | null;
        table_number: string;
        orders_count: number;
        total_amount: number;
        last_activity: string;
        status: string;
        is_paid: boolean;
        orders: Order[];
    }>;
}

interface Order {
    id: number;
    table_number: string;
    code: string;
    total_amount: number;
    is_paid: boolean;
    status: string;
    created_at: string;
    orderItems: OrderItem[];
}

interface OrderItem {
    id: number;
    order_id: number;
    menu_id: number;
    name: string;
    price: number;
    quantity: number;
    status: string;
    special_instructions: string | null;
}

const props = defineProps<Props>();

// Breadcrumb items
const breadcrumbItems = [
    { title: 'Restaurants', href: route('restaurants.index') },
    { title: props.restaurant.name, href: route('restaurants.show', props.restaurant.id) },
    { title: 'Orders', href: route('orders.index', props.restaurant.id), current: true },
];

// Format currency
const formatPrice = (price: number) => {
    return new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB' }).format(price);
};

// Format date
const formatDate = (dateString: string) => {
    return formatDistanceToNow(new Date(dateString), { addSuffix: true });
};

// Get status badge class
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
        case 'completed':
            return 'bg-gray-100 text-gray-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

// Get payment status badge class
const getPaymentClass = (isPaid: boolean) => {
    return isPaid ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
};

// Calculate item total including options (unit may already include options depending on server)
const calculateItemTotal = (item: any): number => {
    // The server stores `price` as the unit price including selected options. Use it directly.
    return Number(item.price || 0) * (item.quantity || 0);
};

// Track processing state per order so we can show a spinner
const processingOrders = ref<Record<number, boolean>>({});

// Helper to compute a stable processing key for a table row
const getTableKey = (table: any) => {
    return table.qr_code_id ?? table.table_number;
};

// Navigate to an order or table show page without triggering the Inertia progress bar
const goToOrder = (order: Order) => {
    processingOrders.value[order.id] = true;
    router.get(
        route('orders.show', { restaurant: props.restaurant.id, order: order.id }),
        {},
        {
            preserveScroll: true,
            showProgress: false,
            onFinish: () => {
                processingOrders.value[order.id] = false;
            },
            onError: () => {
                processingOrders.value[order.id] = false;
            },
        },
    );
};

// Navigate to a table details (use the first order of that table as the anchor)
const goToTable = (table: any) => {
    const key = table.qr_code_id ?? table.table_number;
    processingOrders.value[key] = true;
    router.get(
        route('orders.table.show', { restaurant: props.restaurant.id, tableNumber: table.table_number }),
        { qrCodeId: table.qr_code_id ?? null },
        {
            preserveScroll: true,
            showProgress: false,
            onFinish: () => {
                processingOrders.value[key] = false;
                // Refresh the table show to reflect the new payment/checked status
                router.get(
                    route('orders.table.show', { restaurant: props.restaurant.id, tableNumber: table.table_number }),
                    { qrCodeId: table.qr_code_id ?? null },
                    { preserveState: false, showProgress: false },
                );
            },
            onError: () => {
                processingOrders.value[key] = false;
            },
        },
    );
};

// Mark an order as paid without triggering the Inertia progress bar
const markOrderPaid = (order: Order) => {
    processingOrders.value[order.id] = true;

    // Compute amount including option prices (fallback to order.total_amount if items not present)
    let amount = Number(order.total_amount || 0);
    if (order.orderItems && Array.isArray(order.orderItems) && order.orderItems.length > 0) {
        amount = order.orderItems.reduce((sum: number, it: any) => {
            return sum + calculateItemTotal(it);
        }, 0);
    }

    router.patch(
        route('orders.mark-paid', { restaurant: props.restaurant.id, order: order.id }),
        { amount },
        {
            preserveScroll: true,
            showProgress: false,
            onFinish: () => {
                processingOrders.value[order.id] = false;
            },
            onError: () => {
                processingOrders.value[order.id] = false;
            },
        },
    );
};

// Mark a table as checked (creates aggregated payment for the table/QR)
const markTableChecked = (table: any) => {
    const key = table.qr_code_id ?? table.table_number;
    processingOrders.value[key] = true;

    router.post(
        route('orders.table.check', { restaurant: props.restaurant.id, tableNumber: table.table_number }),
        {},
        {
            preserveScroll: true,
            showProgress: false,
            onFinish: () => {
                processingOrders.value[key] = false;
                // Refresh the Orders index so the table/QR status is updated in the list
                router.get(route('orders.index', { restaurant: props.restaurant.id }), {}, { preserveState: false, showProgress: false });
            },
            onError: () => {
                processingOrders.value[key] = false;
            },
        },
    );
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Orders - ${props.restaurant.name}`" />

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="mb-6 flex items-center justify-between">
                    <h1 class="text-2xl font-semibold text-gray-900">Orders</h1>
                    <Link :href="route('kitchen.show', { restaurant: props.restaurant.id })" class="inline-flex">
                        <Button>
                            <ChefHat class="mr-2 h-4 w-4" />
                            Kitchen View
                        </Button>
                    </Link>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Orders</CardTitle>
                        <CardDescription>Manage and track all orders for {{ props.restaurant.name }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <caption class="mt-4 mb-2 text-sm text-gray-500">
                                A list of active tables and their aggregated totals.
                            </caption>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Table</TableHead>
                                    <TableHead>Orders</TableHead>
                                    <TableHead>Total</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Last Activity</TableHead>
                                    <TableHead>Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="table in props.tables || []" :key="table.qr_code_id ?? table.table_number">
                                    <TableCell class="font-medium">{{ table.table_number }}</TableCell>
                                    <TableCell>{{ table.orders_count }}</TableCell>
                                    <TableCell>{{ formatPrice(table.total_amount) }}</TableCell>
                                    <TableCell>
                                        <span class="rounded-full px-2 py-1 text-xs font-medium" :class="getStatusClass(table.status)">
                                            {{ table.status }}
                                        </span>
                                    </TableCell>
                                    <TableCell>{{ formatDate(table.last_activity) }}</TableCell>
                                    <TableCell>
                                        <div class="flex space-x-2">
                                            <Button variant="outline" size="sm" @click="goToTable(table)"> View </Button>
                                            <Button
                                                v-if="table.status === 'billing' && table.orders && table.orders.length > 0"
                                                variant="default"
                                                size="sm"
                                                class="bg-purple-600 hover:bg-purple-700"
                                                :disabled="processingOrders[getTableKey(table)] === true"
                                                @click.prevent="markTableChecked(table)"
                                            >
                                                <svg
                                                    v-if="processingOrders[getTableKey(table)]"
                                                    class="mr-2 h-4 w-4 animate-spin"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <circle
                                                        class="opacity-25"
                                                        cx="12"
                                                        cy="12"
                                                        r="10"
                                                        stroke="currentColor"
                                                        stroke-width="4"
                                                        fill="none"
                                                    ></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                                </svg>
                                                Mark Billed
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                    <CardFooter>
                        <!-- Pagination -->
                        <div class="flex w-full items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing {{ props.orders.meta?.from ?? 0 }} to {{ props.orders.meta?.to ?? 0 }} of
                                {{ props.orders.meta?.total ?? 0 }} orders
                            </div>
                            <div class="flex space-x-2">
                                <Link
                                    v-for="(link, i) in props.orders.meta?.links || []"
                                    :key="i"
                                    :href="link.url"
                                    :class="{
                                        'cursor-not-allowed text-gray-500': !link.url,
                                        'text-blue-600 hover:text-blue-800': link.url && !link.active,
                                        'font-bold text-blue-800': link.active,
                                    }"
                                >
                                    <Button v-if="link.url" :variant="link.active ? 'default' : 'outline'" size="sm" :disabled="!link.url">
                                        <span v-html="link.label"></span>
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </CardFooter>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
