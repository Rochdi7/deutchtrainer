<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Chart, LineController, BarController, LineElement, BarElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend, Filler } from 'chart.js';

Chart.register(LineController, BarController, LineElement, BarElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend, Filler);

const props = defineProps({
    chart: { type: Object, required: true }, // { labels, sessions, accuracy }
});

const canvas = ref(null);
let instance = null;

function isDark() {
    return document.documentElement.classList.contains('dark');
}

function build() {
    if (!canvas.value) return;
    instance?.destroy();
    const dark = isDark();
    const grid = dark ? 'rgba(148,163,184,0.15)' : 'rgba(100,116,139,0.15)';
    const tick = dark ? '#94a3b8' : '#64748b';

    instance = new Chart(canvas.value, {
        data: {
            labels: props.chart.labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Sessions',
                    data: props.chart.sessions,
                    backgroundColor: 'rgba(51,121,255,0.35)',
                    borderColor: '#3379ff',
                    borderWidth: 1,
                    borderRadius: 4,
                    yAxisID: 'y',
                },
                {
                    type: 'line',
                    label: 'Genauigkeit %',
                    data: props.chart.accuracy,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16,185,129,0.15)',
                    spanGaps: true,
                    tension: 0.3,
                    pointRadius: 3,
                    fill: false,
                    yAxisID: 'y1',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { labels: { color: tick } } },
            scales: {
                x: { ticks: { color: tick, maxTicksLimit: 10 }, grid: { color: grid } },
                y: { beginAtZero: true, ticks: { color: tick, precision: 0 }, grid: { color: grid }, title: { display: true, text: 'Sessions', color: tick } },
                y1: { position: 'right', min: 0, max: 100, ticks: { color: tick }, grid: { drawOnChartArea: false }, title: { display: true, text: '%', color: tick } },
            },
        },
    });
}

let observer;
onMounted(() => {
    build();
    observer = new MutationObserver(build);
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});
watch(() => props.chart, build, { deep: true });
onBeforeUnmount(() => {
    observer?.disconnect();
    instance?.destroy();
});
</script>

<template>
    <div class="h-64 w-full sm:h-72">
        <canvas ref="canvas" role="img" aria-label="Sessions und Genauigkeit der letzten 30 Tage"></canvas>
    </div>
</template>
