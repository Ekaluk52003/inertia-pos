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

const form = useForm({
    name: '',
    from_datetime: '',
    to_datetime: '',
    location: '',
});

const submitForm = () => {
    form.post(route('events.store'));
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Events',
        href: '/events',
    },
    {
        title: 'New Event',
        href: '/events/create',
    },
];
</script>

<template>
    <Head title="Create Event" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
            <Card class="w-full space-x-1 space-y-1">
            <CardHeader>
                <CardTitle>Create New Event</CardTitle>
                <CardDescription>Fill in the details to create a new event</CardDescription>
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
            
            <CardFooter >
     
                <Button type="submit" @click="submitForm" :disabled="form.processing" :class="{ 'opacity-75': form.processing }">
                    {{ form.processing ? 'Creating...' : 'Create Event' }}
                </Button>
            </CardFooter>
        </Card>
        </div>
      
    </AppLayout>
</template>
