<script setup>
import { computed } from 'vue';
import { marked } from 'marked';

const props = defineProps({ source: { type: String, default: '' } });

marked.setOptions({ gfm: true, breaks: true });

const html = computed(() => marked.parse(props.source || ''));
</script>

<template>
    <div class="prose-md text-sm leading-relaxed text-gray-800 dark:text-gray-200" v-html="html"></div>
</template>
