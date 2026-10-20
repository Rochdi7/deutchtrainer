<script setup>
defineProps({
    text: { type: String, default: '' },
    tag: { type: String, default: 'span' },
});
</script>

<template>
    <component :is="tag" dir="rtl" lang="ar" class="ar"><slot>{{ text }}</slot></component>
</template>
