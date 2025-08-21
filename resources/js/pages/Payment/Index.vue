<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { formatDistanceToNow } from 'date-fns';
import { CreditCard, FileText } from 'lucide-vue-next';
import { computed } from 'vue';

// Define props interface
interface Props {
    restaurant: {
        id: number;
        name: string;
    };
    payments: {
        data?: Payment[];
        links?: any[];
        meta?: {
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
    // Server-provided diagnostics / totals (added in PaymentController@index)
    total_payments?: number;
    total_payments_direct?: number;
}

interface Payment {
    id: number;
    amount: number | string;
    trans_ref: string;
    sender_name: string | null;
    sender_display_name: string | null;
    sending_bank: string | null;
    table_number: string;
    restaurant_id: number;
    qr_code_id: number;
    created_at: string;
}

const props = defineProps<Props>();

// Breadcrumb items
const breadcrumbItems = [
    { title: 'Restaurants', href: route('restaurants.index') },
    { title: props.restaurant.name, href: route('restaurants.show', props.restaurant.id) },
    { title: 'Payments', href: route('payments.index', props.restaurant.id), current: true },
];

// Format currency (accept numeric strings too)
const formatPrice = (price: number | string) => {
    const n = Number(price);
    return new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB' }).format(Number.isFinite(n) ? n : 0);
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
        case 'completed':
            return 'bg-blue-100 text-blue-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

// Get bank name from code (common Thai banks)
const getBankName = (bankCode: string | null) => {
    if (!bankCode) return 'Unknown Bank';

    const banks: Record<string, string> = {
        '002': 'Bangkok Bank',
        '004': 'Kasikornbank',
        '006': 'Krung Thai Bank',
        '011': 'TMBThanachart Bank',
        '014': 'Siam Commercial Bank',
        '025': 'Krungsri Bank',
        '030': 'Government Savings Bank',
        '034': 'Bank for Agriculture',
        'DEV BANK': 'Development Mode',
    };

    return banks[bankCode] || `Bank ${bankCode}`;
};

// Calculate total payments amount, coercing amounts to numbers to avoid NaN when amounts are strings
const totalAmount = computed(() => {
    return (props.payments.data || []).reduce((sum, payment) => {
        const n = Number(payment.amount);
        return sum + (Number.isFinite(n) ? n : 0);
    }, 0);
});

// Compute page range and total with fallbacks when paginator meta is missing
const pageFrom = computed(() => {
    const meta = props.payments?.meta;
    const dataLen = props.payments?.data?.length ?? 0;
    if (!meta) {
        return dataLen > 0 ? 1 : 0;
    }
    if (meta.from && meta.from > 0) return meta.from;
    if (meta.current_page && meta.per_page && dataLen > 0) {
        return (meta.current_page - 1) * meta.per_page + 1;
    }
    return 0;
});

const pageTo = computed(() => {
    const meta = props.payments?.meta;
    const dataLen = props.payments?.data?.length ?? 0;
    if (!meta) {
        return dataLen > 0 ? dataLen : 0;
    }
    if (meta.to && meta.to > 0) return meta.to;
    if (pageFrom.value > 0) return pageFrom.value + dataLen - 1;
    return 0;
});

const totalDisplay = computed(() => {
    return props.total_payments ?? props.payments?.meta?.total ?? 0;
});

// Prev/Next urls for simple pagination controls
const prevUrl = computed(() => {
    const meta = props.payments?.meta;
    if (!meta || !meta.current_page) return null;
    const prev = meta.current_page - 1;
    if (prev < 1) return null;
    try {
        const base = route('payments.index', props.restaurant.id);
        return `${base}?page=${prev}`;
    } catch (e) {
        // Fallback to meta.path when route helper isn't available
        return `${meta.path}?page=${prev}`;
    }
});

const nextUrl = computed(() => {
    const meta = props.payments?.meta;
    if (!meta || !meta.current_page || !meta.last_page) return null;
    const next = meta.current_page + 1;
    if (next > meta.last_page) return null;
    try {
        const base = route('payments.index', props.restaurant.id);
        return `${base}?page=${next}`;
    } catch (e) {
        return `${meta.path}?page=${next}`;
    }
});

const showPagination = computed(() => {
    const meta = props.payments?.meta;
    const linksLen = props.payments?.links?.length ?? 0;
    return (meta && meta.last_page && meta.last_page > 1) || linksLen > 1;
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Payments - ${props.restaurant.name}`" />

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="space-y-6">
                    <!-- Header -->
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-3xl font-bold tracking-tight">Payments</h2>
                            <p class="text-muted-foreground">Manage payment records for {{ props.restaurant.name }}</p>
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="grid gap-4 md:grid-cols-3">
                        <Card>
                            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle class="text-sm font-medium">Total Payments</CardTitle>
                                <CreditCard class="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div class="text-2xl font-bold">{{ props.total_payments ?? props.payments.meta?.total ?? 0 }}</div>
                                <p class="text-xs text-muted-foreground">Payment transactions</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle class="text-sm font-medium">Total Amount</CardTitle>
                                <FileText class="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div class="text-2xl font-bold">{{ formatPrice(totalAmount) }}</div>
                                <p class="text-xs text-muted-foreground">Total revenue from payments</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle class="text-sm font-medium">This Page</CardTitle>
                                <FileText class="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div class="text-2xl font-bold">{{ props.payments.data?.length || 0 }}</div>
                                <p class="text-xs text-muted-foreground">Showing {{ pageFrom }}-{{ pageTo }} of {{ totalDisplay }}</p>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Payments Table -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Payment Records</CardTitle>
                            <CardDescription> Recent payment transactions and slip verifications </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Transaction Ref</TableHead>
                                            <TableHead>Table</TableHead>
                                            <TableHead>Amount</TableHead>
                                            <TableHead>Sender</TableHead>
                                            <TableHead>Bank</TableHead>
                                            <TableHead>Date</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="payment in props.payments.data || []" :key="payment.id">
                                            <TableCell class="font-medium">
                                                <div class="flex flex-col">
                                                    <span class="font-mono text-sm">{{ payment.trans_ref }}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Link :href="route('orders.table.show', [props.restaurant.id, payment.table_number])">
                                                    <Badge variant="outline"> Table {{ payment.table_number }} </Badge>
                                                </Link>
                                            </TableCell>

                                            <TableCell>
                                                <span class="font-semibold text-green-600">
                                                    {{ formatPrice(payment.amount) }}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <div class="flex flex-col">
                                                    <span class="font-medium">{{
                                                        payment.sender_display_name || payment.sender_name || 'Unknown'
                                                    }}</span>
                                                    <span
                                                        v-if="payment.sender_name && payment.sender_display_name"
                                                        class="text-sm text-muted-foreground"
                                                    >
                                                        {{ payment.sender_name }}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <span class="text-sm">{{ getBankName(payment.sending_bank) }}</span>
                                            </TableCell>

                                            <TableCell>
                                                <div class="flex flex-col">
                                                    <span class="text-sm">{{ formatDate(payment.created_at) }}</span>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                        <TableRow v-if="!props.payments.data || props.payments.data.length === 0">
                                            <TableCell colspan="8" class="py-8 text-center">
                                                <div class="flex flex-col items-center gap-2">
                                                    <CreditCard class="h-8 w-8 text-muted-foreground" />
                                                    <p class="text-muted-foreground">No payments found</p>
                                                    <p class="text-sm text-muted-foreground">
                                                        Payments will appear here once customers make purchases
                                                    </p>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </CardContent>

                        <!-- Pagination -->
                        <CardFooter v-if="showPagination" class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <p class="text-sm text-muted-foreground">Showing {{ pageFrom }}-{{ pageTo }} of {{ totalDisplay }} payments</p>
                            </div>
                            <div class="flex items-center space-x-2">
                                <Link v-if="prevUrl" :href="prevUrl">
                                    <Button variant="outline" size="sm">Prev</Button>
                                </Link>
                                <Button v-else disabled variant="outline" size="sm">Prev</Button>

                                <template v-for="link in props.payments.links || []" :key="link.label">
                                    <Link v-if="link.url" :href="link.url">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            v-html="link.label"
                                            :class="{ 'bg-primary text-primary-foreground': link.active }"
                                        />
                                    </Link>
                                    <Button v-else disabled variant="outline" size="sm" v-html="link.label" />
                                </template>

                                <Link v-if="nextUrl" :href="nextUrl">
                                    <Button variant="outline" size="sm">Next</Button>
                                </Link>
                                <Button v-else disabled variant="outline" size="sm">Next</Button>
                            </div>
                        </CardFooter>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
