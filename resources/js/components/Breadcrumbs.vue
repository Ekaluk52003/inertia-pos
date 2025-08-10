<script setup lang="ts">
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { Link } from '@inertiajs/vue3';

interface BreadcrumbItemType {
    title: string;
    href?: string;
}

defineProps<{
    breadcrumbs: BreadcrumbItemType[];
}>();
</script>

<template>
    <Breadcrumb class="breadcrumb-clean">
        <BreadcrumbList>
            <template v-for="(item, index) in breadcrumbs" :key="index">
                <BreadcrumbItem class="breadcrumb-item-clean">
                    <template v-if="index === breadcrumbs.length - 1">
                        <BreadcrumbPage class="text-sm">{{ item.title }}</BreadcrumbPage>
                    </template>
                    <template v-else>
                        <BreadcrumbLink as-child class="text-sm">
                            <Link :href="item.href ?? '#'">{{ item.title }}</Link>
                        </BreadcrumbLink>
                    </template>
                </BreadcrumbItem>
                <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" class="mx-1">/</BreadcrumbSeparator>
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>

<style scoped>
.breadcrumb-clean :deep([data-slot="breadcrumb-list"]) {
  display: flex;
  align-items: center;
  height: auto;
  background-color: transparent;
  border-radius: 0;
  padding: 0;
}

.breadcrumb-item-clean :deep([data-slot="breadcrumb-item"]) {
  height: auto;
  padding: 0;
  background-color: transparent;
}

.breadcrumb-clean :deep([data-slot="breadcrumb-separator"]) {
  color: var(--muted-foreground);
  margin: 0 0.25rem;
}
</style>
