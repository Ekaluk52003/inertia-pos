<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { formatDistanceToNow } from 'date-fns';
import { ArrowLeft, ChefHat, CreditCard } from 'lucide-vue-next';

// Define props interface
interface Props {
    restaurant: {
        id: number;
        name: string;
    };
    order: {
        id: number;
        table_number: string;
        code: string;
        total_amount: number;
        is_paid: boolean;
        status: string;
        created_at: string;
        orderItems: OrderItem[];
        payments: Payment[];
    };
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
    menuItem?: {
        id: number;
        name: string;
        price: number;
        category: string;
    };
}

interface Payment {
    id: number;
    order_id: number;
    trans_ref: string;
    amount: number;
    sender_name: string;
    created_at: string;
}

const props = defineProps<Props>();

// Breadcrumb items
const breadcrumbItems = [
    { title: 'Restaurants', href: route('restaurants.index') },
    { title: props.restaurant.name, href: route('restaurants.show', props.restaurant.id) },
    { title: 'Orders', href: route('orders.index', props.restaurant.id) },
    {
        title: `Order #${props.order.code.substring(0, 8)}...`,
        href: route('orders.show', { restaurant: props.restaurant.id, order: props.order.id }),
        current: true,
    },
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
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

// Calculate order total
const orderTotal = props.order.orderItems.reduce((total, item) => {
    return total + item.price * item.quantity;
}, 0);

// Calculate payments total
const paymentsTotal = props.order.payments.reduce((total, payment) => {
    return total + payment.amount;
}, 0);

// Check if all items are served
const allItemsServed = props.order.orderItems.every((item) => item.status === 'served');

// Handle item status update
const updateItemStatus = (itemId: number, currentStatus: string) => {
    const nextStatus = currentStatus === 'pending' ? 'cooking' : currentStatus === 'cooking' ? 'ready' : 'served';

    router.patch(
        route('orders.update-item-status', {
            restaurant: props.restaurant.id,
            order: props.order.id,
        }),
        {
            order_item_id: itemId,
            status: nextStatus,
        },
    );
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Order Details - ${props.restaurant.name}`" />

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
                        <h1 class="text-2xl font-semibold text-gray-900">Order Details</h1>
                    </div>
                    <div class="flex space-x-2">
                        <Link :href="route('kitchen.show', { restaurant: props.restaurant.id })" class="inline-flex">
                            <Button variant="outline">
                                <ChefHat class="mr-2 h-4 w-4" />
                                Kitchen View
                            </Button>
                        </Link>
                        <Link
                            v-if="!props.order.is_paid"
                            :href="route('orders.mark-paid', { restaurant: props.restaurant.id, order: props.order.id })"
                            method="patch"
                            as="button"
                        >
                            <Button>
                                <CreditCard class="mr-2 h-4 w-4" />
                                Mark as Paid
                            </Button>
                        </Link>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <!-- Order Summary -->
                    <Card class="md:col-span-1">
                        <CardHeader>
                            <CardTitle>Order Summary</CardTitle>
                            <CardDescription>Order #{{ props.order.code.substring(0, 8) }}...</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium">Table Number:</span>
                                    <span class="text-sm">{{ props.order.table_number }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium">Status:</span>
                                    <span class="rounded-full px-2 py-1 text-xs font-medium" :class="getStatusClass(props.order.status)">
                                        {{ props.order.status }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium">Payment Status:</span>
                                    <span
                                        class="rounded-full px-2 py-1 text-xs font-medium"
                                        :class="props.order.is_paid ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                    >
                                        {{ props.order.is_paid ? 'Paid' : 'Unpaid' }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium">Created:</span>
                                    <span class="text-sm">{{ formatDate(props.order.created_at) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium">Total Amount:</span>
                                    <span class="text-sm font-bold">{{ formatPrice(orderTotal) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium">Payments Received:</span>
                                    <span class="text-sm font-bold">{{ formatPrice(paymentsTotal) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium">Balance:</span>
                                    <span class="text-sm font-bold" :class="orderTotal > paymentsTotal ? 'text-red-600' : 'text-green-600'">
                                        {{ formatPrice(orderTotal - paymentsTotal) }}
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Order Items -->
                    <Card class="md:col-span-2">
                        <CardHeader>
                            <CardTitle>Order Items</CardTitle>
                            <CardDescription>Items ordered and their current status</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Item</TableHead>
                                        <TableHead>Quantity</TableHead>
                                        <TableHead>Price</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="item in props.order.orderItems" :key="item.id">
                                        <TableCell>
                                            <div>
                                                <div class="font-medium">{{ item.name }}</div>
                                                <div v-if="item.special_instructions" class="mt-1 text-xs text-gray-500 italic">
                                                    {{ item.special_instructions }}
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell>{{ item.quantity }}</TableCell>
                                        <TableCell>{{ formatPrice(item.price * item.quantity) }}</TableCell>
                                        <TableCell>
                                            <span class="rounded-full px-2 py-1 text-xs font-medium" :class="getStatusClass(item.status)">
                                                {{ item.status }}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <div class="flex space-x-2">
                                                <Button
                                                    v-if="item.status !== 'served'"
                                                    @click="updateItemStatus(item.id, item.status)"
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    {{
                                                        item.status === 'pending'
                                                            ? 'Start Cooking'
                                                            : item.status === 'cooking'
                                                              ? 'Mark Ready'
                                                              : 'Mark Served'
                                                    }}
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <!-- Payments -->
                    <Card class="md:col-span-3" v-if="props.order.payments.length > 0">
                        <CardHeader>
                            <CardTitle>Payment History</CardTitle>
                            <CardDescription>Record of payments received for this order</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Transaction Ref</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Sender</TableHead>
                                        <TableHead>Date</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="payment in props.order.payments" :key="payment.id">
                                        <TableCell class="font-medium">{{ payment.trans_ref }}</TableCell>
                                        <TableCell>{{ formatPrice(payment.amount) }}</TableCell>
                                        <TableCell>{{ payment.sender_name }}</TableCell>
                                        <TableCell>{{ formatDate(payment.created_at) }}</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
