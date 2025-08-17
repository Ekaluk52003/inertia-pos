<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Bell, BellOff } from 'lucide-vue-next';
import { ref } from 'vue';
// Import the useEcho hook for private channels
import { useEcho } from '@laravel/echo-vue';

// Simple date formatting function
const formatDate = (dateString: string) => {
    try {
        const date = new Date(dateString);
        return date.toLocaleString();
    } catch {
        // Return the original string if date parsing fails
        return dateString;
    }
};

// Define props interface
interface Props {
    restaurant: {
        id: number;
        name: string;
    };
    activeOrders: Order[];
    ordersByTable: {
        table_number: string;
        orders: Order[];
    }[];
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
    options?: any[] | null;
}

const props = defineProps<Props>();

// Breadcrumb items
const breadcrumbItems = [
    { title: 'Restaurants', href: route('restaurants.index') },
    { title: props.restaurant.name, href: route('restaurants.show', props.restaurant.id) },
    { title: 'Kitchen View', href: route('kitchen.show', props.restaurant.id), current: true },
];

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

// Vibrant gradient palette for order items (similar to menu cards)
const gradients = [
    'bg-gradient-to-br from-yellow-300 to-pink-500 text-white',
    'bg-gradient-to-br from-indigo-400 to-purple-600 text-white',
    'bg-gradient-to-br from-emerald-300 to-green-600 text-white',
    'bg-gradient-to-br from-rose-300 to-orange-400 text-white',
    'bg-gradient-to-br from-sky-300 to-blue-600 text-white',
];

// Choose gradient based on item status so color represents status
const gradientFor = (status: string) => {
    switch (status) {
        case 'pending':
            return 'bg-gradient-to-br from-yellow-300 to-yellow-500 text-black';
        case 'cooking':
            return 'bg-gradient-to-br from-orange-300 to-orange-500 text-white';
        case 'ready':
            return 'bg-gradient-to-br from-emerald-300 to-green-600 text-white';
        case 'served':
            return 'bg-gradient-to-br from-sky-200 to-blue-400 text-white';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

// Audio notification system
const audioRef = ref<HTMLAudioElement | null>(null);
const userInteracted = ref(false);
const notificationEnabled = ref(false);

// Play notification sound if enabled and user has interacted with the page
const playNotification = () => {
    if (audioRef.value && userInteracted.value && notificationEnabled.value) {
        audioRef.value.play().catch((error) => {
            console.warn('Could not play notification sound:', error);
        });
    } else {
        console.log(
            'Notification sound not played: ' +
                (!userInteracted.value ? 'No user interaction yet. ' : '') +
                (!notificationEnabled.value ? 'Notifications not enabled. ' : '') +
                (!audioRef.value ? 'Audio element not found.' : ''),
        );
    }
};

// Enable notifications after user interaction
const enableNotifications = () => {
    userInteracted.value = true;
    notificationEnabled.value = true;
    // Play a test sound to confirm notifications are working
    if (audioRef.value) {
        audioRef.value.volume = 0.2; // Lower volume for test sound
        audioRef.value
            .play()
            .then(() => {
                console.log('Notifications enabled successfully!');
                audioRef.value!.volume = 1.0; // Reset volume to normal
            })
            .catch((error) => {
                console.warn('Could not enable notifications:', error);
            });
    }
};

// Toggle notifications on/off. When disabling, stop any playing audio.
const toggleNotifications = () => {
    if (notificationEnabled.value) {
        // Disable notifications
        notificationEnabled.value = false;
        try {
            if (audioRef.value) {
                audioRef.value.pause();
                audioRef.value.currentTime = 0;
            }
        } catch (e) {
            console.warn('Error stopping audio when disabling notifications', e);
        }
        console.log('Notifications disabled');
    } else {
        // Enable (reuse enableNotifications to play test sound)
        enableNotifications();
    }
};

// Tab state management
const activeTab = ref<string | number>('table'); // 'table' or 'order'

// Function to handle data reload
const handleDataReload = (shouldNotify: boolean = false) => {
    console.log('Reloading kitchen data...');
    if (shouldNotify) {
        playNotification();
    }
    router.reload({ only: ['activeOrders', 'ordersByTable'] });
};

// Setup Echo listener for new orders using the private channel
const channelName = `restaurant.${props.restaurant.id}`;
useEcho(channelName, 'NewOrder', (event: any) => {
    console.log('New order received:', event);
    handleDataReload(true); // Play notification for new orders
});

// Log that we're listening
console.log(`Subscribed to private channel: ${channelName}`);

// Add visibility change detection to handle tab focus changes
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        console.log('Tab is now visible...');
        handleDataReload(false); // Don't play notification when just returning to tab
    }
});

// Clean up is handled automatically by the hook

// Track processing status for each item
const processingItems = ref<Record<string, boolean>>({});

// Update item status using Inertia.js
const updateItemStatus = (orderId: number, itemId: number, newStatus: string) => {
    // Set this specific item as processing
    const itemKey = `${orderId}-${itemId}`;
    processingItems.value[itemKey] = true;
    // Use Inertia router to make a PATCH request
    router.patch(
        route('orders.update-item-status', { restaurant: props.restaurant.id, order: orderId }),
        {
            order_item_id: itemId,
            status: newStatus,
        },
        {
            preserveScroll: true,
            showProgress: false, // Disable NProgress for status updates
            onFinish: () => {
                processingItems.value[itemKey] = false;
            },
        },
    );
};

// Check if an item is currently being processed
const isProcessing = (orderId: number, itemId: number) => {
    const itemKey = `${orderId}-${itemId}`;
    return processingItems.value[itemKey] === true;
};

// Get next status
const getNextStatus = (currentStatus: string) => {
    switch (currentStatus) {
        case 'pending':
            return 'cooking';
        case 'cooking':
            return 'ready';
        case 'ready':
            return 'served';
        default:
            return currentStatus;
    }
};

// Get next status button text
const getNextStatusText = (currentStatus: string) => {
    switch (currentStatus) {
        case 'pending':
            return 'Start Cooking';
        case 'cooking':
            return 'Mark Ready';
        case 'ready':
            return 'Mark Served';
        default:
            return 'Update';
    }
};

// Choose a high-contrast class for action buttons placed on gradient backgrounds
const getActionButtonClass = (status: string) => {
    // Use a white background + dark text to guarantee readability on vibrant gradients
    return 'bg-white text-black shadow-sm';
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Kitchen View - ${props.restaurant.name}`" />

        <audio ref="audioRef" src="/audio/notification.mp3" preload="auto"></audio>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="mb-6 flex items-center justify-between">
                    <div class="flex items-center">
                        <Link :href="route('restaurants.show', props.restaurant.id)">
                            <Button variant="outline" class="flex items-center gap-2">
                                <ArrowLeft class="h-4 w-4" />
                                Back to Orders
                            </Button>
                        </Link>
                        <h1 class="ml-4 text-2xl font-semibold text-gray-900">Kitchen View</h1>
                    </div>

                    <!-- Notification toggle button (icon-only circular) -->
                    <Button
                        @click="toggleNotifications"
                        :class="notificationEnabled ? 'bg-yellow-500 text-white hover:bg-gray-50' : 'border bg-white text-gray-700 hover:bg-gray-50'"
                        class="flex h-10 w-10 items-center justify-center rounded-full"
                        :title="notificationEnabled ? 'Disable notifications' : 'Enable notifications'"
                        aria-label="Toggle notifications"
                    >
                        <Bell v-if="notificationEnabled" class="h-5 w-5" />
                        <BellOff v-else class="h-5 w-5" />
                    </Button>
                </div>

                <!-- Tabs for different views -->
                <Tabs :default-value="activeTab" @update:model-value="(value) => (activeTab = value)" class="mb-6">
                    <TabsList class="grid w-full grid-cols-2">
                        <TabsTrigger value="table">View by Table</TabsTrigger>
                        <TabsTrigger value="order">View by Order</TabsTrigger>
                    </TabsList>

                    <!-- No orders message -->
                    <div v-if="!props.ordersByTable || props.ordersByTable.length === 0" class="py-12 text-center">
                        <p class="text-lg text-gray-500">No active orders at the moment.</p>
                    </div>

                    <!-- View by Table Tab Content -->
                    <TabsContent value="table" class="mt-0">
                        <div
                            v-if="props.ordersByTable && props.ordersByTable.length > 0"
                            class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3"
                        >
                            <!-- Group by table number -->
                            <div v-for="tableGroup in props.ordersByTable" :key="tableGroup.table_number" class="h-full">
                                <Card class="h-full">
                                    <CardHeader class="bg-gray-50">
                                        <div class="flex items-center justify-between">
                                            <CardTitle class="text-xl font-bold">Table {{ tableGroup.table_number }}</CardTitle>
                                            <Badge variant="outline">{{ tableGroup.orders.length }} Order(s)</Badge>
                                        </div>
                                    </CardHeader>
                                    <CardContent class="p-0">
                                        <!-- Loop through each order for this table -->
                                        <div v-for="order in tableGroup.orders" :key="order.id" class="border-b last:border-b-0">
                                            <div class="p-4">
                                                <div class="mb-2 flex items-center justify-between">
                                                    <div class="text-sm font-medium">Order #{{ order.code.substring(0, 8) }}...</div>
                                                    <div class="text-sm text-gray-500">{{ formatDate(order.created_at) }}</div>
                                                </div>

                                                <!-- Debug info to show raw order items data -->
                                                <div class="mb-3 rounded bg-blue-50 p-2 text-sm">
                                                    Order items count: {{ order.orderItems ? order.orderItems.length : 0 }}
                                                </div>

                                                <ul v-if="order.orderItems && order.orderItems.length > 0" class="space-y-3">
                                                    <li
                                                        v-for="(item, itemIndex) in order.orderItems"
                                                        :key="item.id"
                                                        class="rounded-md p-2"
                                                        :class="[gradientFor(item.status), 'shadow-sm']"
                                                    >
                                                        <div class="flex items-start justify-between">
                                                            <div>
                                                                <div class="font-medium">{{ item.name }} × {{ item.quantity }}</div>
                                                                <!-- Display selected options if available -->
                                                                <div
                                                                    v-if="item.options && item.options.length > 0"
                                                                    class="mt-1 text-xs text-gray-600"
                                                                >
                                                                    <div v-for="(option, optIdx) in item.options" :key="optIdx">
                                                                        <span class="font-medium">{{ option.option_name }}:</span>
                                                                        {{ option.choices.join(', ') }}
                                                                        <span v-if="option.additional_price > 0">
                                                                            (+{{ option.additional_price }}฿)</span
                                                                        >
                                                                    </div>
                                                                </div>
                                                                <div v-if="item.special_instructions" class="mt-1 text-xs italic">
                                                                    {{ item.special_instructions }}
                                                                </div>
                                                                <div class="mt-1 text-xs text-gray-500">Status: {{ item.status }}</div>
                                                            </div>
                                                            <Button
                                                                v-if="item.status !== 'served'"
                                                                @click="updateItemStatus(order.id, item.id, getNextStatus(item.status))"
                                                                size="sm"
                                                                variant="outline"
                                                                :disabled="isProcessing(order.id, item.id)"
                                                                :class="getActionButtonClass(item.status)"
                                                            >
                                                                <span v-if="isProcessing(order.id, item.id)" class="mr-1">⏳</span>
                                                                {{ getNextStatusText(item.status) }}
                                                            </Button>
                                                            <Badge v-else variant="outline" class="bg-blue-50">Served</Badge>
                                                        </div>
                                                    </li>
                                                </ul>

                                                <!-- Show message if no items in this order -->
                                                <div
                                                    v-if="!order.orderItems || order.orderItems.length === 0"
                                                    class="py-2 text-center text-sm text-gray-500"
                                                >
                                                    No items in this order
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    </TabsContent>

                    <!-- View by Order Tab Content -->
                    <TabsContent value="order" class="mt-0">
                        <div v-if="props.activeOrders && props.activeOrders.length > 0" class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <!-- Display orders without grouping by table -->
                            <Card v-for="order in props.activeOrders" :key="order.id" class="h-full">
                                <CardHeader class="bg-gray-50">
                                    <div class="flex items-center justify-between">
                                        <CardTitle class="text-lg font-bold">
                                            Order #{{ order.code.substring(0, 8) }}...
                                            <span class="ml-2 text-sm font-normal">Table {{ order.table_number }}</span>
                                        </CardTitle>
                                        <Badge variant="outline">{{ order.orderItems ? order.orderItems.length : 0 }} Items</Badge>
                                    </div>
                                    <div class="mt-1 text-sm text-gray-500">{{ formatDate(order.created_at) }}</div>
                                </CardHeader>
                                <CardContent>
                                    <!-- Order items -->
                                    <ul v-if="order.orderItems && order.orderItems.length > 0" class="space-y-3">
                                        <li
                                            v-for="(item, itemIndex) in order.orderItems"
                                            :key="item.id"
                                            class="rounded-md p-2"
                                            :class="[gradientFor(item.status), 'shadow-sm']"
                                        >
                                            <div class="flex items-start justify-between">
                                                <div>
                                                    <div class="font-medium">{{ item.name }} × {{ item.quantity }}</div>
                                                    <!-- Display selected options if available -->
                                                    <div v-if="item.options && item.options.length > 0" class="mt-1 ml-5 text-xs text-gray-600">
                                                        <div v-for="(option, optIdx) in item.options" :key="optIdx">
                                                            <span class="font-medium">{{ option.option_name }}:</span>
                                                            {{ option.choices.join(', ') }}
                                                            <span v-if="option.additional_price > 0"> (+{{ option.additional_price }}฿)</span>
                                                        </div>
                                                    </div>
                                                    <div v-if="item.special_instructions" class="mt-1 text-xs italic">
                                                        {{ item.special_instructions }}
                                                    </div>
                                                    <div class="mt-1 text-xs text-gray-500">Status: {{ item.status }}</div>
                                                </div>
                                                <Button
                                                    v-if="item.status !== 'served'"
                                                    @click="updateItemStatus(order.id, item.id, getNextStatus(item.status))"
                                                    size="sm"
                                                    variant="outline"
                                                    :disabled="isProcessing(order.id, item.id)"
                                                    :class="getActionButtonClass(item.status)"
                                                >
                                                    <span v-if="isProcessing(order.id, item.id)" class="mr-1">⏳</span>
                                                    {{ getNextStatusText(item.status) }}
                                                </Button>
                                                <Badge v-else variant="outline" class="bg-blue-50">Served</Badge>
                                            </div>
                                        </li>
                                    </ul>

                                    <!-- Show message if no items in this order -->
                                    <div v-if="!order.orderItems || order.orderItems.length === 0" class="py-2 text-center text-sm text-gray-500">
                                        No items in this order
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                        <div v-else class="py-12 text-center">
                            <p class="text-lg text-gray-500">No active orders at the moment.</p>
                        </div>
                    </TabsContent>
                </Tabs>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Add any component-specific styles here */
</style>
