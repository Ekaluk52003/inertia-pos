<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { router } from '@inertiajs/vue3';
import { formatDate } from '@/utils/format';

const props = defineProps({
    event: Object,
});

const deleteEvent = (id: number) => {
    if (confirm('Are you sure you want to delete this event?')) {
        router.delete(route('events.destroy', id));
    }
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Events',
        href: route('events.index'),
    },
    {
        title: props.event?.name || 'Event Details',
        href: route('events.show', props.event?.id),
    },
];
</script>

<template>
    <Head :title="props.event?.name || 'Event Details'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
            <Card class="w-full">
                <CardHeader>
                    <CardTitle class="text-2xl font-bold">{{ props.event?.name || 'Event' }}</CardTitle>
                    <CardDescription>Event Details</CardDescription>
                </CardHeader>
                
                <CardContent class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <h3 class="text-lg font-semibold">Start Date & Time</h3>
                            <p class="text-gray-700 dark:text-gray-300">{{ formatDate(props.event?.from_datetime) }}</p>
                        </div>
                        
                        <div class="space-y-2">
                            <h3 class="text-lg font-semibold">End Date & Time</h3>
                            <p class="text-gray-700 dark:text-gray-300">{{ formatDate(props.event?.to_datetime) }}</p>
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <h3 class="text-lg font-semibold">Location</h3>
                        <p class="text-gray-700 dark:text-gray-300">{{ props.event?.location || 'Not specified' }}</p>
                    </div>
                    
                    <div class="space-y-2">
                        <h3 class="text-lg font-semibold">Event ID</h3>
                        <p class="text-gray-700 dark:text-gray-300">#{{ props.event?.id || 'N/A' }}</p>
                    </div>
                </CardContent>
                
                <CardFooter class="flex justify-end space-x-4">
                    <Button variant="outline" @click="router.visit(route('events.edit', props.event?.id))">Edit Event</Button>
                    <Button variant="destructive" size="sm" @click="deleteEvent(props.event?.id)">
                                    Delete
                                </Button>
                </CardFooter>
            </Card>
        </div>
    </AppLayout>
</template>
