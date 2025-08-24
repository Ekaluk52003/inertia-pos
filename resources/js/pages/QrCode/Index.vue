<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItemType } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { PlusIcon, RefreshCcwIcon, TrashIcon } from 'lucide-vue-next';
import { computed, reactive, watch } from 'vue';
import QrCodeDisplay from '../../components/QrCode/QrCodeDisplay.vue';

interface QrCode {
    id: number;
    restaurant_id: number;
    table_number: number;
    code: string;
    is_active: boolean;
    status: string;
    created_at: string;
    updated_at: string;
}

interface Restaurant {
    id: number;
    name: string;
}

interface Props {
    restaurant: Restaurant;
    qrCodes: QrCode[];
}

const props = defineProps<Props>();

// Breadcrumbs
const breadcrumbItems = computed(() => {
    return [
        { title: 'Restaurants', href: route('restaurants.index') },
        { title: props.restaurant.name, href: route('restaurants.show', props.restaurant.id) },
        { title: 'QR Codes', href: route('qrcodes.index', props.restaurant.id) },
    ] as BreadcrumbItemType[];
});

// Generate the public menu URL for a QR code
const getPublicMenuUrl = (qrCode: QrCode) => {
    return `${window.location.origin}/public/menu/${props.restaurant.id}/${qrCode.code}`;
};

// Local reactive map of active states for optimistic updates
const activeStates = reactive<Record<number, boolean>>({});

// Sync activeStates from incoming props (handles navigation / initial render)
watch(
    () => props.qrCodes,
    (newList) => {
        newList.forEach((q) => (activeStates[q.id] = q.is_active));
    },
    { immediate: true },
);

// Handle toggle from the Switch component (optimistic UI with rollback on error)
const handleToggle = (qrCode: QrCode, newValue: boolean) => {
    // activeStates is already updated via v-model; send request
    router.patch(
        route('qrcodes.toggle', [props.restaurant.id, qrCode.id]),
        {},
        {
            preserveScroll: true,
            showProgress: false,
            onError: () => {
                // Rollback the optimistic UI change
                activeStates[qrCode.id] = !newValue;
            },
        },
    );
};

// Delete QR code
const deleteQrCode = (qrCode: QrCode) => {
    if (confirm('Are you sure you want to delete this QR code?')) {
        router.delete(route('qrcodes.destroy', [props.restaurant.id, qrCode.id]));
    }
};

// Regenerate QR code
const regenerateQrCode = (qrCode: QrCode) => {
    if (confirm('Are you sure you want to regenerate this QR code? The old code will no longer work.')) {
        router.post(route('qrcodes.regenerate', [props.restaurant.id, qrCode.id]));
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`QR Codes - ${restaurant.name}`" />

        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">QR Codes</h1>
                <Button @click="router.get(route('qrcodes.create', restaurant.id))" class="cursor-pointer">
                    <PlusIcon class="mr-2 h-4 w-4" />
                    Create QR Code
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Table QR Codes</CardTitle>
                    <CardDescription>
                        Manage QR codes for your restaurant tables. Customers can scan these to access the menu and place orders.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div v-if="qrCodes.length === 0" class="py-8 text-center">
                        <p class="mb-4 text-muted-foreground">No QR codes found. Create your first QR code to get started.</p>
                        <Button @click="router.get(route('qrcodes.create', restaurant.id))" class="cursor-pointer">
                            <PlusIcon class="mr-2 h-4 w-4" />
                            Create QR Code
                        </Button>
                    </div>

                    <div v-else>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Table</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead class="w-24 text-center">Enabled</TableHead>
                                    <TableHead>QR Status</TableHead>
                                    <TableHead>QR Code</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="qrCode in qrCodes" :key="qrCode.id">
                                    <TableCell class="font-medium">{{ qrCode.table_number }}</TableCell>
                                    <TableCell>
                                        <Badge :variant="qrCode.is_active ? 'default' : 'outline'">
                                            {{ qrCode.is_active ? 'Active' : 'Inactive' }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="w-24 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <Switch
                                                :id="`qrcode-active-${qrCode.id}`"
                                                :modelValue="activeStates[qrCode.id] ?? qrCode.is_active"
                                                @update:modelValue="
                                                    (value) => {
                                                        activeStates[qrCode.id] = Boolean(value);
                                                        handleToggle(qrCode, value);
                                                    }
                                                "
                                                :title="activeStates[qrCode.id] ? 'Deactivate' : 'Activate'"
                                                aria-label="Toggle QR code active state"
                                            />
                                            <span class="hidden text-sm text-muted-foreground sm:inline-block">{{
                                                (activeStates[qrCode.id] ?? qrCode.is_active) ? 'On' : 'Off'
                                            }}</span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="qrCode.status === 'checked' ? 'default' : 'outline'">
                                            {{ qrCode.status ?? 'unknown' }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Dialog>
                                            <DialogTrigger as-child>
                                                <Button variant="outline" size="sm">View QR Code</Button>
                                            </DialogTrigger>
                                            <DialogContent>
                                                <DialogHeader>
                                                    <DialogTitle>Table {{ qrCode.table_number }} QR Code</DialogTitle>
                                                    <DialogDescription> Scan this QR code to access the menu and place orders. </DialogDescription>
                                                </DialogHeader>
                                                <div class="flex flex-col items-center justify-center py-4">
                                                    <QrCodeDisplay
                                                        :value="getPublicMenuUrl(qrCode)"
                                                        :table-number="qrCode.table_number"
                                                        :restaurant-name="restaurant.name"
                                                    />
                                                    <a
                                                        :href="getPublicMenuUrl(qrCode)"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="mt-4 text-center text-sm break-words text-muted-foreground underline"
                                                    >
                                                        {{ getPublicMenuUrl(qrCode) }}
                                                    </a>
                                                </div>
                                                <DialogFooter>
                                                    <Button variant="outline" @click="regenerateQrCode(qrCode)">
                                                        <RefreshCcwIcon class="mr-2 h-4 w-4" />
                                                        Regenerate
                                                    </Button>
                                                </DialogFooter>
                                            </DialogContent>
                                        </Dialog>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <Button variant="outline" size="icon" @click="deleteQrCode(qrCode)" title="Delete">
                                            <TrashIcon class="h-4 w-4" />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
