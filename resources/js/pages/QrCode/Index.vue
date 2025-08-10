<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItemType } from '@/types';
import { computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { PlusIcon, RefreshCcwIcon, TrashIcon, ToggleLeftIcon, ToggleRightIcon } from 'lucide-vue-next';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import QrCodeDisplay from '../../components/QrCode/QrCodeDisplay.vue';

interface QrCode {
  id: number;
  restaurant_id: number;
  table_number: number;
  code: string;
  is_active: boolean;
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

// Toggle QR code active status
const toggleActive = (qrCode: QrCode) => {
  router.patch(route('qrcodes.toggle', [props.restaurant.id, qrCode.id]));
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
    
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-semibold">QR Codes</h1>
        <Button @click="router.get(route('qrcodes.create', restaurant.id))" class="cursor-pointer">
          <PlusIcon class="h-4 w-4 mr-2" />
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
          <div v-if="qrCodes.length === 0" class="text-center py-8">
            <p class="text-muted-foreground mb-4">No QR codes found. Create your first QR code to get started.</p>
            <Button @click="router.get(route('qrcodes.create', restaurant.id))" class="cursor-pointer">
              <PlusIcon class="h-4 w-4 mr-2" />
              Create QR Code
            </Button>
          </div>
          
          <div v-else>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Table Identifier</TableHead>
                  <TableHead>Status</TableHead>
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
                  <TableCell>
                    <Dialog>
                      <DialogTrigger as-child>
                        <Button variant="outline" size="sm">View QR Code</Button>
                      </DialogTrigger>
                      <DialogContent>
                        <DialogHeader>
                          <DialogTitle>Table {{ qrCode.table_number }} QR Code</DialogTitle>
                          <DialogDescription>
                            Scan this QR code to access the menu and place orders.
                          </DialogDescription>
                        </DialogHeader>
                        <div class="flex flex-col items-center justify-center py-4">
                          <QrCodeDisplay 
                            :value="getPublicMenuUrl(qrCode)" 
                            :table-number="qrCode.table_number" 
                            :restaurant-name="restaurant.name"
                          />
                          <p class="mt-4 text-sm text-muted-foreground text-center">
                            {{ getPublicMenuUrl(qrCode) }}
                          </p>
                        </div>
                        <DialogFooter>
                          <Button variant="outline" @click="regenerateQrCode(qrCode)">
                            <RefreshCcwIcon class="h-4 w-4 mr-2" />
                            Regenerate
                          </Button>
                        </DialogFooter>
                      </DialogContent>
                    </Dialog>
                  </TableCell>
                  <TableCell class="text-right">
                    <div class="flex justify-end gap-2">
                      <Button 
                        variant="outline" 
                        size="icon" 
                        @click="toggleActive(qrCode)" 
                        :title="qrCode.is_active ? 'Deactivate' : 'Activate'"
                      >
                        <ToggleRightIcon v-if="qrCode.is_active" class="h-4 w-4" />
                        <ToggleLeftIcon v-else class="h-4 w-4" />
                      </Button>
                      <Button 
                        variant="outline" 
                        size="icon" 
                        @click="deleteQrCode(qrCode)"
                        title="Delete"
                      >
                        <TrashIcon class="h-4 w-4" />
                      </Button>
                    </div>
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
