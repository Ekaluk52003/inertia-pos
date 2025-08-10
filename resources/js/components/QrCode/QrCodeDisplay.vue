<script setup lang="ts">
import { onMounted, ref } from 'vue';
import QRCode from 'qrcode';

interface Props {
  value: string;
  tableNumber: string | number;
  restaurantName: string;
}

const props = defineProps<Props>();
const qrCodeDataUrl = ref('');

onMounted(async () => {
  try {
    qrCodeDataUrl.value = await QRCode.toDataURL(props.value, {
      width: 200,
      margin: 2,
      color: {
        dark: '#000000',
        light: '#FFFFFF'
      }
    });
  } catch (error) {
    console.error('Error generating QR code:', error);
  }
});
</script>

<template>
  <div class="flex flex-col items-center">
    <div class="bg-white p-4 rounded-lg border">
      <img v-if="qrCodeDataUrl" :src="qrCodeDataUrl" alt="QR Code" class="w-48 h-48" />
      <div v-else class="w-48 h-48 flex items-center justify-center bg-gray-100">
        Loading...
      </div>
    </div>
    <div class="mt-2 text-center">
      <p class="font-semibold">{{ restaurantName }}</p>
      <p>Table {{ tableNumber }}</p>
    </div>
  </div>
</template>
