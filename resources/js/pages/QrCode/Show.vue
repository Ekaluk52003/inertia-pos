<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItemType } from '@/types';
import { computed } from 'vue';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { RefreshCcwIcon, TrashIcon, ToggleLeftIcon, ToggleRightIcon, PrinterIcon, DownloadIcon } from 'lucide-vue-next';
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
  qrCode: QrCode;
}

const props = defineProps<Props>();

// Breadcrumbs
const breadcrumbItems = computed(() => {
  return [
    { title: 'Restaurants', href: route('restaurants.index') },
    { title: props.restaurant.name, href: route('restaurants.show', props.restaurant.id) },
    { title: 'QR Codes', href: route('qrcodes.index', props.restaurant.id) },
    { title: props.qrCode.table_number, href: route('qrcodes.show', [props.restaurant.id, props.qrCode.id]) },
  ] as BreadcrumbItemType[];
});

// Generate the public menu URL for a QR code
const publicMenuUrl = computed(() => {
  return `${window.location.origin}/public/menu/${props.restaurant.id}/${props.qrCode.code}`;
});

// Format date
const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleString();
};

// Toggle QR code active status
const toggleActive = () => {
  router.patch(route('qrcodes.toggle', [props.restaurant.id, props.qrCode.id]));
};

// Delete QR code
const deleteQrCode = () => {
  if (confirm('Are you sure you want to delete this QR code?')) {
    router.delete(route('qrcodes.destroy', [props.restaurant.id, props.qrCode.id]));
  }
};

// Regenerate QR code
const regenerateQrCode = () => {
  if (confirm('Are you sure you want to regenerate this QR code? The old code will no longer work.')) {
    router.post(route('qrcodes.regenerate', [props.restaurant.id, props.qrCode.id]));
  }
};

// Print QR code
const printQrCode = () => {
  window.print();
};

// Download QR code
const downloadQrCode = () => {
  // This is a placeholder - in a real implementation, you would generate and download the QR code
  alert('Download functionality will be implemented here');
};
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head :title="`QR Code Table ${qrCode.table_number} - ${restaurant.name}`" />
    
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-semibold">QR Code: {{ qrCode.table_number }}</h1>
        <div class="flex gap-2">
          <Button variant="outline" as="a" :href="route('qrcodes.index', restaurant.id)">
            Back to QR Codes
          </Button>
        </div>
      </div>
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- QR Code Display -->
        <Card>
          <CardHeader>
            <CardTitle>QR Code</CardTitle>
            <CardDescription>
              Scan this QR code to access the menu and place orders.
            </CardDescription>
          </CardHeader>
          
          <CardContent class="flex justify-center">
            <QrCodeDisplay 
              :value="publicMenuUrl" 
              :table-number="qrCode.table_number" 
              :restaurant-name="restaurant.name"
            />
          </CardContent>
          
          <CardFooter class="flex justify-center gap-2">
            <Button variant="outline" @click="printQrCode">
              <PrinterIcon class="h-4 w-4 mr-2" />
              Print
            </Button>
            <Button variant="outline" @click="downloadQrCode">
              <DownloadIcon class="h-4 w-4 mr-2" />
              Download
            </Button>
          </CardFooter>
        </Card>
        
        <!-- QR Code Details -->
        <Card>
          <CardHeader>
            <CardTitle>Details</CardTitle>
            <CardDescription>
              Information about this QR code.
            </CardDescription>
          </CardHeader>
          
          <CardContent>
            <div class="space-y-4">
              <div>
                <h3 class="text-sm font-medium text-muted-foreground">Table Identifier</h3>
                <p>{{ qrCode.table_number }}</p>
              </div>
              
              <div>
                <h3 class="text-sm font-medium text-muted-foreground">Status</h3>
                <Badge :variant="qrCode.is_active ? 'default' : 'outline'" class="mt-1">
                  {{ qrCode.is_active ? 'Active' : 'Inactive' }}
                </Badge>
              </div>
              
              <div>
                <h3 class="text-sm font-medium text-muted-foreground">Code</h3>
                <p class="text-xs font-mono break-all">{{ qrCode.code }}</p>
              </div>
              
              <div>
                <h3 class="text-sm font-medium text-muted-foreground">URL</h3>
                <p class="text-xs break-all">{{ publicMenuUrl }}</p>
              </div>
              
              <div>
                <h3 class="text-sm font-medium text-muted-foreground">Created</h3>
                <p>{{ formatDate(qrCode.created_at) }}</p>
              </div>
              
              <div>
                <h3 class="text-sm font-medium text-muted-foreground">Last Updated</h3>
                <p>{{ formatDate(qrCode.updated_at) }}</p>
              </div>
            </div>
          </CardContent>
          
          <CardFooter class="flex justify-between">
            <Button 
              variant="outline" 
              @click="toggleActive()" 
            >
              <ToggleRightIcon v-if="qrCode.is_active" class="h-4 w-4 mr-2" />
              <ToggleLeftIcon v-else class="h-4 w-4 mr-2" />
              {{ qrCode.is_active ? 'Deactivate' : 'Activate' }}
            </Button>
            
            <div class="flex gap-2">
              <Button 
                variant="outline" 
                @click="regenerateQrCode()"
              >
                <RefreshCcwIcon class="h-4 w-4 mr-2" />
                Regenerate
              </Button>
              
              <Button 
                variant="destructive" 
                @click="deleteQrCode()"
              >
                <TrashIcon class="h-4 w-4 mr-2" />
                Delete
              </Button>
            </div>
          </CardFooter>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>

<style>
@media print {
  body * {
    visibility: hidden;
  }
  .qr-code-print, .qr-code-print * {
    visibility: visible;
  }
  .qr-code-print {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
  }
}
</style>
