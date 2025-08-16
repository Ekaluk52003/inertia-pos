<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue';
import generatePayload from 'promptpay-qr';
// @ts-expect-error - Missing type definitions for qrcode
import qrcode from 'qrcode';

/**
 * Props:
 *   - promptPayId: string (PromptPay phone number or ID)
 *   - amount: number (Baht)
 *   - label: string (optional)
 */
const props = defineProps({
  promptPayId: {
    type: String,
    required: false,
    default: ''
  },
  amount: {
    type: [Number, String],
    required: true
  },
  label: {
    type: String,
    default: ''
  }
});

// Ensure amount is always a number
const numericAmount = ref(
  typeof props.amount === 'string' ? parseFloat(props.amount) : props.amount
);

// Watch for changes in the amount prop
watch(() => props.amount, (newAmount) => {
  numericAmount.value = typeof newAmount === 'string' ? parseFloat(newAmount) : newAmount;
});

// Create a ref for the canvas element
const canvasRef = ref(null);

// Check if the promptPayId is valid (not empty and has proper format)
const isValidPromptPayId = computed(() => {
  return props.promptPayId && props.promptPayId.trim().length > 0;
});

// Generate QR code when component is mounted or when payload changes
const generateQRCode = () => {
  // Only generate QR code if we have a valid promptPayId and a canvas reference
  if (canvasRef.value && isValidPromptPayId.value) {
    try {
      const payload = generatePayload(props.promptPayId, {
        amount: isNaN(numericAmount.value) ? 0 : numericAmount.value,
        ...(props.label ? { message: props.label } : {})
      });
      
      qrcode.toCanvas(canvasRef.value, payload, { width: 150, margin: 1 }, (err: Error | null) => {
        if (err) console.error('QR code generation error:', err);
      });
    } catch (error) {
      console.error('Error generating PromptPay payload:', error);
    }
  }
};

// Run generateQRCode when component is mounted
onMounted(() => {
  generateQRCode();
});

// Watch for changes in promptPayId or numericAmount
watch([() => props.promptPayId, numericAmount], () => {
  // Only attempt to generate QR code if promptPayId is valid
  if (isValidPromptPayId.value) {
    generateQRCode();
  }
});
</script>

<template>
  <div class="flex items-center justify-between bg-white p-3 rounded-lg shadow-sm border border-orange-100">
    <div v-if="isValidPromptPayId" class="flex-shrink-0">
      <canvas ref="canvasRef" style="width: 150px; height: 150px"></canvas>
    </div>
    <div v-else class="flex-shrink-0 flex items-center justify-center bg-gray-100" style="width: 150px; height: 150px">
      <div class="text-xs text-gray-500 text-center p-2">
        No valid PromptPay ID configured
      </div>
    </div>
    <div class="flex-1 pl-4 text-sm">
      <div class="font-semibold text-orange-600 mb-1">PromptPay</div>
      <div v-if="isValidPromptPayId" class="text-gray-700 mb-1">{{ promptPayId }}</div>
      <div v-else class="text-red-500 mb-1">Missing PromptPay ID</div>
      <div v-if="numericAmount > 0" class="text-orange-500 font-medium">
        ฿{{ numericAmount.toFixed(2) }}
      </div>
      <div v-if="label" class="text-xs text-gray-500 mt-1">{{ label }}</div>
      <div v-if="isValidPromptPayId" class="text-xs text-gray-500 mt-2">Scan to pay</div>
      <div v-else class="text-xs text-red-500 mt-2">Please contact restaurant staff</div>
    </div>
  </div>
</template>
