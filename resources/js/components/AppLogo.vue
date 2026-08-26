<template>
    <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
        <img src="/images/TB_Logo.png" alt="Logo" class="size-5 object-contain" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate font-semibold leading-none">Laravel Starter Kit</span>
    </div>
</template>

<script setup lang="ts">
interface Props {
    class?: string;
}

defineProps<Props>();
</script>
