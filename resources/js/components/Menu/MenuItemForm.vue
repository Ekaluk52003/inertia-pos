<script setup lang="ts">
// No need for URL declaration since we're using window.URL || window.webkitURL
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface AttributeValue {
    name: string;
    price: number;
}

interface MenuItemAttribute {
    name: string;
    values: AttributeValue[];
    required?: boolean;
    multiple?: boolean;
}

interface MenuItem {
    id?: number;
    name: string;
    description: string | null;
    price: number;
    category: string;
    is_available: boolean;
    image_path?: string;
    options?: Array<MenuItemAttribute> | null;
}

interface Props {
    restaurantId: number;
    menuItem?: MenuItem;
    submitUrl: string;
    submitMethod?: string;
}

const props = withDefaults(defineProps<Props>(), {
    menuItem: () => ({
        id: undefined,
        name: '',
        description: '',
        price: 0,
        category: '',
        is_available: true,
    }),
    submitMethod: 'post',
});

// Available categories
const categories = ['Appetizers', 'Main Courses', 'Desserts', 'Beverages', 'Sides', 'Specials'];

// Image handling
const imageInputType = ref<'file' | 'url'>('file');
const imageUrl = ref<string>(props.menuItem.image_path && props.menuItem.image_path.startsWith('http') ? props.menuItem.image_path : '');
const imageFile = ref<File | null>(null);

// Computed property for image preview URL
const previewUrl = computed(() => {
    if (!imageFile.value) return '';
    try {
        return typeof window !== 'undefined' ? URL.createObjectURL(imageFile.value) : '';
    } catch (e) {
        console.error('Error creating object URL:', e);
        return '';
    }
});

// Initialize reactive state for attributes
const attributes = ref<MenuItemAttribute[]>(
    (props.menuItem.options || []).map((option) => ({
        ...option,
        required: Boolean(option.required ?? true),
        multiple: Boolean(option.multiple ?? false),
    })),
);

const newAttributeName = ref('');
// Create a map to store new values and prices for each attribute
const attributeValues = ref<Map<number, { value: string; price: number }>>(new Map());

// Helper function to get or initialize attribute values
const getAttributeValues = (index: number) => {
    if (!attributeValues.value.has(index)) {
        attributeValues.value.set(index, { value: '', price: 0 });
    }
    return attributeValues.value.get(index)!;
};

// Add a new attribute
const addAttribute = () => {
    if (newAttributeName.value.trim()) {
        attributes.value.push({
            name: newAttributeName.value.trim(),
            values: [],
            required: true, // Default to required (boolean)
            multiple: false, // Default to single selection (boolean)
        });
        newAttributeName.value = '';
        // Update form immediately
        updateFormOptions();
    }
};

// Add a new value to an attribute
const addValueToAttribute = (attributeIndex: number) => {
    const attrValues = getAttributeValues(attributeIndex);

    if (attrValues.value.trim() && attributeIndex >= 0 && attributeIndex < attributes.value.length) {
        attributes.value[attributeIndex].values.push({
            name: attrValues.value.trim(),
            price: attrValues.price,
        });
        // Reset values after adding
        attrValues.value = '';
        attrValues.price = 0;
        // Update form immediately after adding value
        updateFormOptions();
    }
};

// Remove a value from an attribute
const removeValueFromAttribute = (attributeIndex: number, valueIndex: number) => {
    if (attributeIndex >= 0 && attributeIndex < attributes.value.length) {
        attributes.value[attributeIndex].values.splice(valueIndex, 1);
        // Update form immediately
        updateFormOptions();
    }
};

// Remove an attribute
const removeAttribute = (index: number) => {
    attributes.value.splice(index, 1);
    // Clean up the attributeValues map
    attributeValues.value.delete(index);
    // Re-index the remaining entries
    const newMap = new Map();
    let newIndex = 0;
    for (let i = 0; i < attributes.value.length; i++) {
        if (attributeValues.value.has(i)) {
            newMap.set(newIndex, attributeValues.value.get(i));
        }
        newIndex++;
    }
    attributeValues.value = newMap;
    // Update form immediately
    updateFormOptions();
};

// Helper function to update form options
const updateFormOptions = () => {
    const filteredAttributes = attributes.value.filter((attr) => attr.name.trim());
    form.options = JSON.parse(JSON.stringify(filteredAttributes)) as any;
};

// Create form with Inertia
const form = useForm({
    _method: props.submitMethod, // For method spoofing
    name: props.menuItem.name || '',
    description: props.menuItem.description || '',
    price: props.menuItem.price || 0,
    category: props.menuItem.category || '',
    is_available: props.menuItem.is_available ?? true,
    image: null as File | null,
    image_url: props.menuItem.image_path || '',
    options: JSON.parse(JSON.stringify(props.menuItem.options || [])) as any,
});

// Log form data for debugging
console.log('Form initialized with is_available:', form.is_available);

// Watch for changes in attributes and update form
watch(
    attributes,
    () => {
        updateFormOptions();
    },
    { deep: true },
);

// Initialize form options
updateFormOptions();

// Handle file input change
const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        form.image = target.files[0];
        imageFile.value = target.files[0];
        // Clear image URL when file is selected
        form.image_url = '';
        imageUrl.value = '';
    }
};

// Watch for changes in image URL input
watch(imageUrl, (newValue) => {
    form.image_url = newValue || '';
    // Clear file input when URL is entered
    if (newValue) {
        form.image = null;
        imageFile.value = null;
    }
});

// Toggle between file upload and URL input
const toggleImageInputType = () => {
    imageInputType.value = imageInputType.value === 'file' ? 'url' : 'file';
    // Clear the other input type when switching
    if (imageInputType.value === 'file') {
        form.image_url = '';
        imageUrl.value = '';
    } else {
        form.image = null;
        imageFile.value = null;
    }
};

// Price is now handled directly with v-model.number

// Form submission
const submit = () => {
    // Ensure form data is properly set before submission
    form.name = String(form.name || '').trim();
    form.description = String(form.description || '').trim() || '';
    form.category = String(form.category || '').trim() || '';
    form.price = Number(form.price) || 0;
    form.is_available = Boolean(form.is_available);

    // Filter attributes that have values for final submission
    const validAttributes = attributes.value.filter((attr) => attr.name.trim() && attr.values && attr.values.length > 0);
    form.options = JSON.parse(JSON.stringify(validAttributes)) as any;

    // If we have a file, set it directly on the form
    if (imageFile.value) {
        form.image = imageFile.value;
    }

    // Log form data for debugging
    console.log('Submitting form data:', {
        name: form.name,
        description: form.description,
        price: form.price,
        category: form.category,
        is_available: form.is_available,
        options: form.options,
        image: form.image,
        image_url: form.image_url,
    });

    form.post(props.submitUrl, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            console.log('Form submission successful');
        },
        onError: (errors) => {
            console.error('Form submission failed:', errors);
        },
    });
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ props.menuItem.id ? 'Edit Menu Item' : 'Create Menu Item' }}</CardTitle>
            <CardDescription>
                {{ props.menuItem.id ? 'Update the details of this menu item.' : 'Add a new item to your restaurant menu.' }}
            </CardDescription>
        </CardHeader>
        <CardContent>
            <form @submit.prevent="submit" class="space-y-6">
                <div class="space-y-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        placeholder="Enter menu item name"
                        :disabled="form.processing"
                        :class="{ 'border-red-500': form.errors.name }"
                    />
                    <p v-if="form.errors.name" class="text-sm text-red-500">{{ form.errors.name }}</p>
                </div>

                <div class="space-y-2">
                    <Label for="description">Description (optional)</Label>
                    <Textarea
                        id="description"
                        v-model="form.description"
                        placeholder="Enter a description of the menu item"
                        :disabled="form.processing"
                        :class="{ 'border-red-500': form.errors.description }"
                    />
                    <p v-if="form.errors.description" class="text-sm text-red-500">{{ form.errors.description }}</p>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="price">Price (฿)</Label>
                        <Input
                            id="price"
                            v-model.number="form.price"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            :disabled="form.processing"
                            :class="{ 'border-red-500': form.errors.price }"
                        />
                        <p v-if="form.errors.price" class="text-sm text-red-500">{{ form.errors.price }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="category">Category</Label>
                        <Select v-model="form.category" :disabled="form.processing">
                            <SelectTrigger :class="{ 'border-red-500': form.errors.category }">
                                <SelectValue placeholder="Select a category" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="category in categories" :key="category" :value="category">
                                    {{ category }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.category" class="text-sm text-red-500">{{ form.errors.category }}</p>
                    </div>
                </div>

                <!-- Image Upload/URL Section -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label>Menu Item Image</Label>
                        <Button type="button" variant="outline" size="sm" @click="toggleImageInputType" :disabled="form.processing">
                            Switch to {{ imageInputType === 'file' ? 'URL' : 'File Upload' }}
                        </Button>
                    </div>

                    <!-- File Upload Input -->
                    <div v-if="imageInputType === 'file'" class="space-y-2">
                        <Input
                            type="file"
                            accept="image/*"
                            @change="handleFileChange"
                            :disabled="form.processing"
                            :class="{ 'border-red-500': form.errors.image }"
                        />
                        <p v-if="form.errors.image" class="text-sm text-red-500">{{ form.errors.image }}</p>
                        <p class="text-xs text-muted-foreground">Upload an image file (JPEG, PNG, etc.)</p>
                    </div>

                    <!-- Image URL Input -->
                    <div v-else class="space-y-2">
                        <Input
                            v-model="imageUrl"
                            placeholder="https://example.com/image.jpg"
                            :disabled="form.processing"
                            :class="{ 'border-red-500': form.errors.image_url }"
                        />
                        <p v-if="form.errors.image_url" class="text-sm text-red-500">{{ form.errors.image_url }}</p>
                        <p class="text-xs text-muted-foreground">Enter a direct URL to an image</p>
                    </div>

                    <!-- Preview current image if available -->
                    <div v-if="props.menuItem.image_path || imageFile || imageUrl" class="mt-2">
                        <p class="mb-1 text-sm font-medium">Image Preview:</p>
                        <div class="max-w-xs rounded-md border p-2">
                            <img v-if="imageFile" :src="previewUrl" alt="Preview" class="mx-auto max-h-40 object-contain" />
                            <img v-else-if="imageUrl" :src="imageUrl" alt="Preview" class="mx-auto max-h-40 object-contain" />
                            <img
                                v-else-if="props.menuItem.image_path"
                                :src="
                                    props.menuItem.image_path.startsWith('http') ? props.menuItem.image_path : `/storage/${props.menuItem.image_path}`
                                "
                                alt="Current image"
                                class="mx-auto max-h-40 object-contain"
                            />
                        </div>
                    </div>
                </div>

                <!-- Attributes Section -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <Label>Menu Item Attributes</Label>
                        <p class="text-xs text-muted-foreground">Add customizable options like size, cooking preference, etc.</p>
                    </div>

                    <!-- Add new attribute -->
                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <Label for="new-attribute">New Attribute</Label>
                            <Input
                                id="new-attribute"
                                v-model="newAttributeName"
                                placeholder="e.g., Size, Cooking Preference"
                                :disabled="form.processing"
                            />
                        </div>
                        <Button type="button" variant="outline" @click="addAttribute" :disabled="form.processing || !newAttributeName.trim()">
                            Add
                        </Button>
                    </div>

                    <!-- Attribute list -->
                    <div v-if="attributes.length > 0" class="space-y-4 rounded-md border p-4">
                        <div v-for="(attribute, attrIndex) in attributes" :key="attrIndex" class="space-y-3 border-b pb-3 last:border-b-0">
                            <div class="flex items-center justify-between">
                                <h4 class="font-medium">{{ attribute.name }}</h4>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="removeAttribute(attrIndex)"
                                    :disabled="form.processing"
                                    class="text-red-500 hover:bg-red-50 hover:text-red-700"
                                >
                                    Remove
                                </Button>
                            </div>

                            <!-- Option settings -->
                            <div class="flex items-center gap-4">
                                <div class="flex items-center space-x-2">
                                    <Switch
                                        :id="`required-${attrIndex}`"
                                        :modelValue="Boolean(attribute.required)"
                                        @update:modelValue="
                                            (value) => {
                                                console.log('Required changed to:', value);
                                                attribute.required = Boolean(value);
                                            }
                                        "
                                        :disabled="form.processing"
                                    />
                                    <Label :for="`required-${attrIndex}`">Required</Label>
                                    <span class="text-xs text-muted-foreground">({{ attribute.required ? 'Yes' : 'No' }})</span>
                                </div>

                                <div class="flex items-center space-x-2">
                                    <Switch
                                        :id="`multiple-${attrIndex}`"
                                        :modelValue="Boolean(attribute.multiple)"
                                        @update:modelValue="
                                            (value) => {
                                                console.log('Multiple changed to:', value);
                                                attribute.multiple = Boolean(value);
                                            }
                                        "
                                        :disabled="form.processing"
                                    />
                                    <Label :for="`multiple-${attrIndex}`">Allow multiple selections</Label>
                                    <span class="text-xs text-muted-foreground">({{ attribute.multiple ? 'Yes' : 'No' }})</span>
                                </div>
                            </div>

                            <!-- Add values to attribute -->
                            <div class="flex items-end gap-2">
                                <div class="flex-1">
                                    <Label :for="`attr-value-${attrIndex}`">Add Value</Label>
                                    <Input
                                        :id="`attr-value-${attrIndex}`"
                                        v-model="getAttributeValues(attrIndex).value"
                                        placeholder="e.g., Small, Medium, Large"
                                        :disabled="form.processing"
                                    />
                                </div>
                                <div class="w-1/3">
                                    <Label :for="`attr-price-${attrIndex}`">Price</Label>
                                    <Input
                                        :id="`attr-price-${attrIndex}`"
                                        v-model.number="getAttributeValues(attrIndex).price"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="Additional price"
                                        :disabled="form.processing"
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="addValueToAttribute(attrIndex)"
                                    :disabled="form.processing || !getAttributeValues(attrIndex).value.trim()"
                                >
                                    Add
                                </Button>
                            </div>

                            <!-- Value chips/tags -->
                            <div class="flex flex-wrap gap-2">
                                <div
                                    v-for="(value, valueIndex) in attribute.values"
                                    :key="valueIndex"
                                    class="flex items-center gap-2 rounded-md bg-muted px-3 py-1 text-sm"
                                >
                                    <span>{{ value.name }}</span>
                                    <span v-if="value.price > 0" class="text-xs text-muted-foreground">+{{ value.price }}</span>
                                    <button
                                        type="button"
                                        @click="removeValueFromAttribute(attrIndex, valueIndex)"
                                        :disabled="form.processing"
                                        class="ml-1 text-muted-foreground hover:text-foreground"
                                    >
                                        &times;
                                    </button>
                                </div>
                                <div v-if="attribute.values.length === 0" class="text-sm text-muted-foreground">No values added yet</div>
                            </div>
                        </div>
                    </div>

                    <div v-if="attributes.length === 0" class="rounded-md border p-4 text-center text-sm text-muted-foreground">
                        No attributes added yet. Add attributes like "Size" or "Cooking Preference" to give customers options.
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="text-sm font-medium">Availability</div>
                    <div class="flex items-center space-x-2">
                        <Switch
                            id="is_available"
                            :modelValue="Boolean(form.is_available)"
                            @update:modelValue="
                                (value) => {
                                    form.is_available = Boolean(value);
                                }
                            "
                            :disabled="form.processing"
                        />
                        <Label for="is_available">Available for customers</Label>
                        <span class="text-xs text-muted-foreground">({{ form.is_available ? 'Available' : 'Unavailable' }})</span>
                    </div>
                </div>

                <div class="flex justify-end space-x-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="form.processing"
                        @click="$inertia.visit(route('menu.index', props.restaurantId))"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ props.menuItem.id ? 'Update Menu Item' : 'Create Menu Item' }}
                    </Button>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
