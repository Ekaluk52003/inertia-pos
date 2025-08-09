<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import moment from "moment";

// Import shadcn components
import Button from '@/components/ui/button/Button.vue';
import { Table, TableHeader, TableBody, TableHead, TableRow, TableCell } from '@/components/ui/table';

interface Event {
    id: number;
    name: string;
    from_datetime: string;
    to_datetime: string;
    location: string;
}

const props = defineProps({
    events: {
        type: Array as () => Event[],
        default: () => []
    },
});

const deleteEvent = (id: number) => {
    if (confirm('Are you sure you want to delete this event?')) {
        router.delete(route('events.destroy', id));
    }
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Events',
        href: '/events',
    },
];
</script>

<template>
    <Head title="Events" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold">Events</h1>
                <Button variant="default" as="a" href="/events/create">
                    Create Event
                </Button>
            </div>
            
            <div v-if="props.events && props.events.length > 0" class="relative overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>From</TableHead>
                            <TableHead>To</TableHead>
                            <TableHead>Location</TableHead>
                            <TableHead>Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="event in props.events" :key="event.id">
                            <TableCell>{{ event.name }}</TableCell>
                            <TableCell>{{ moment(event.from_datetime).format('MMM D, YYYY h:mm A') }}</TableCell>
                            <TableCell>{{ moment(event.to_datetime).format('MMM D, YYYY h:mm A') }}</TableCell>
                            <TableCell>{{ event.location }}</TableCell>
                            <TableCell class="space-x-2">
                                <Button variant="ghost" size="sm" as="a" :href="`/events/${event.id}`">
                                    View
                                </Button>
                                <Button variant="outline" size="sm" as="a" :href="`/events/${event.id}/edit`">
                                    Edit
                                </Button>
                                <Button variant="destructive" size="sm" @click="deleteEvent(event.id)">
                                    Delete
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
            
            <div v-else class="relative overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-8">
                <div class="mx-auto flex max-w-[420px] flex-col items-center justify-center text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-10 w-10 text-muted-foreground mb-4"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"></rect><line x1="16" x2="16" y1="2" y2="6"></line><line x1="8" x2="8" y1="2" y2="6"></line><line x1="3" x2="21" y1="10" y2="10"></line></svg>
                    <h3 class="text-lg font-semibold mb-1">No events found</h3>
                    <p class="text-sm text-muted-foreground mb-4">Create your first event to get started.</p>
                    <Button variant="outline" as="a" href="/events/create">
                        Create Event
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
