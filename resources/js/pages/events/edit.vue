<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { useForm } from '@inertiajs/vue3';
import InputError from "@/components/InputError.vue";
import { onMounted } from 'vue';

const props = defineProps({
    event: Object,
});

const form = useForm({
    name: '',
    from_datetime: '',
    to_datetime: '',
    location: '',
});

// Initialize form with event data
onMounted(() => {
    if (props.event) {
        form.name = props.event.name || '';
        
        // Format datetime fields for input
        if (props.event.from_datetime) {
            form.from_datetime = formatDateTimeForInput(props.event.from_datetime);
        }
        
        if (props.event.to_datetime) {
            form.to_datetime = formatDateTimeForInput(props.event.to_datetime);
        }
        
        form.location = props.event.location || '';
    }
});

// Helper function to format datetime for input fields
function formatDateTimeForInput(dateTimeString: string): string {
    try {
        const date = new Date(dateTimeString);
        if (isNaN(date.getTime())) return '';
        
        // Format as YYYY-MM-DDThh:mm (format required by datetime-local input)
        return date.toISOString().slice(0, 16);
    } catch (error) {
        console.error('Error formatting date for input:', error);
        return '';
    }
}

const submitForm = () => {
    if (!props.event?.id) return;
    
    form.put(route('events.update', props.event.id), {
        onSuccess: () => {
            // Form submitted successfully
        },
    });
};

const cancelEdit = () => {
    if (props.event?.id) {
        window.location.href = route('events.show', props.event.id);
    } else {
        window.location.href = route('events.index');
    }
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Events',
        href: route('events.index'),
    },
    {
        title: props.event?.name || 'Edit Event',
        href: route('events.edit', props.event?.id),
    },
];
</script>

<template>
    <Head :title="`Edit ${props.event?.name || 'Event'}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
            <Card class="w-full space-x-1 space-y-1">
                <CardHeader>
                    <CardTitle>Edit Event</CardTitle>
                    <CardDescription>Update the event details</CardDescription>
                </CardHeader>
                
                <CardContent>
                    <form @submit.prevent="submitForm" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Event Name</Label>
                            <Input 
                                id="name" 
                                type="text" 
                                v-model="form.name"
                                class="w-full"
                                placeholder="Enter event name" 
                                :disabled="form.processing"
                            />
                            <InputError :message="form.errors.name" />
                        </div>
                        
                        <div class="space-y-2">
                            <Label for="from_datetime">Start Date & Time</Label>
                            <Input 
                                id="from_datetime" 
                                type="datetime-local" 
                                v-model="form.from_datetime" 
                                :disabled="form.processing"
                            />
                            <InputError :message="form.errors.from_datetime" />
                        </div>
                        
                        <div class="space-y-2">
                            <Label for="to_datetime">End Date & Time</Label>
                            <Input 
                                id="to_datetime" 
                                type="datetime-local" 
                                v-model="form.to_datetime" 
                                :disabled="form.processing"
                            />
                            <InputError :message="form.errors.to_datetime" />
                        </div>
                        
                        <div class="space-y-2">
                            <Label for="location">Location</Label>
                            <Input 
                                id="location" 
                                type="text" 
                                v-model="form.location" 
                                placeholder="Enter location" 
                                :disabled="form.processing"
                            />
                            <InputError :message="form.errors.location" />
                        </div>
                    </form>
                </CardContent>
                
                <CardFooter class="flex justify-between">
                    <Button variant="outline" @click="cancelEdit" :disabled="form.processing">Cancel</Button>
                    <Button type="submit" @click="submitForm" :disabled="form.processing" :class="{ 'opacity-75': form.processing }">
                        {{ form.processing ? 'Updating...' : 'Update Event' }}
                    </Button>
                </CardFooter>
            </Card>
        </div>
    </AppLayout>
</template>
